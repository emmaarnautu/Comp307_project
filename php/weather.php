<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

/**
 * Sends GET request Open-Meteo API
 * @param string $url API URL with parameters
 * @return array Decoded JSON
 */
function api_request(string $url): array {
    $response = file_get_contents($url);

    if ($response === false) {
        throw new RuntimeException("Weather API failed");
    }

    $data = json_decode($response, true);

    if (!is_array($data)) {
        throw new RuntimeException("Invalid weather data");
    }

    return $data;
}

/**
 * Converts a weather code into description
 * @param int $code weather code
 * @return string Weather description
 */
function weather_description(int $code): string {
    return match ($code) {
        0 => 'Clear',
        1 => 'Mainly Clear',
        2 => 'Partly Cloudy',
        3 => 'Overcast',
        45, 48 => 'Fog',
        51, 53, 55 => 'Drizzle',
        56, 57 => 'Freezing Drizzle',
        61, 63, 65 => 'Rain',
        66, 67 => 'Freezing Rain',
        71, 73, 75, 77 => 'Snow',
        80, 81, 82 => 'Rain Showers',
        85, 86 => 'Snow Showers',
        95, 96, 99 => 'Thunderstorm',
        default => 'Unknown'
    };
}

/**
 * @param float $latitude Latitude
 * @param float $longitude Longitude
 * @return array Date, timezone, weather, avg temp
 */
function fetch_weather(float $latitude, float $longitude): array {
    $params = http_build_query([
        'latitude' => $latitude,
        'longitude' => $longitude,
        'daily' => 'weather_code,temperature_2m_mean',
        'temperature_unit' => 'celsius',
        'timezone' => 'auto',
        'forecast_days' => 1
    ]);

    $url = 'https://api.open-meteo.com/v1/forecast?'.$params;
    $data = api_request($url);
    $date = $data['daily']['time'][0] ?? null;
    $code = $data['daily']['weather_code'][0] ?? null;
    $temperature = $data['daily']['temperature_2m_mean'][0] ?? null;
    $timezone = $data['timezone'] ?? null;

    if ($date === null || $code === null || $temperature === null || $timezone === null) {
        throw new RuntimeException('Weather unavailable');
    }
    return [
        'log_date' => $date,
        'timezone' => $timezone,
        'weather' => weather_description($code),
        'temperature' => (float) $temperature
    ];
}

/**
 * @param int $user_id user ID
 * @return array|null Saved location or null
 */
function get_location(int $user_id): ?array {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT latitude, longitude, timezone FROM users WHERE user_id = ?
        AND latitude IS NOT NULL AND longitude IS NOT NULL AND timezone IS NOT NULL
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        return null;
    }

    return $result;
}

/**
 * @param int $user_id ID of the user
 * @param string $date Date in YYYY-MM-DD format
 * @param string $weather Weather description
 * @param float $temperature Daily average temperature in Celsius
 * @return bool True if the database operation succeeds
 */
function save_weather( int $user_id, string $date, string $weather, float $temperature): bool {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO daily_logs(user_id, log_date, weather, temperature) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            weather = VALUES(weather),
            temperature = VALUES(temperature)
    ");

    return $stmt->execute([
        $user_id,
        $date,
        $weather,
        $temperature
    ]);
}

/**
 * @param int $user_id UserID
 * @param string|null $date Requested date, or null for today
 * @return array|null Stored weather or null
 */
function get_weather( int $user_id, ?string $date = null): ?array {
    global $pdo;
    if ($date === null) {
        $location = get_location($user_id);
        if ($location === null) {
            return null;
        }
        $timezone = new DateTimeZone($location['timezone']);
        $date = new DateTime("now", $timezone);
        $date = $date->format("Y-m-d");
    }

    $stmt = $pdo->prepare("
        SELECT log_date, weather, temperature FROM daily_logs WHERE user_id = ?
        AND log_date = ?
        AND weather IS NOT NULL
        AND temperature IS NOT NULL
    ");
    $stmt->execute([$user_id, $date]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$result) {
        return null;
    }

    $result['temperature'] = (float) $result['temperature'];
    return $result;
}

/**
 * @param int $user_id user ID
 * @param float $latitude latitude
 * @param float $longitude longitude
 * @return array Today's weather info
 */
function set_location( int $user_id, float $latitude, float $longitude): array {
    global $pdo;

    if ( !is_finite($latitude) || !is_finite($longitude) || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        throw new InvalidArgumentException('Invalid coord');
    }
    $latitude = round($latitude, 2);
    $longitude = round($longitude, 2);
    $location = get_location($user_id);

    // Avoid unnecessary API requests for unchanged locations.
    if ($location !== null && (float) $location['latitude'] === $latitude && (float) $location['longitude'] === $longitude) {
        $existing = get_weather($user_id);
        if ($existing !== null) {
            return $existing;
        }
    }

    $weather = fetch_weather($latitude, $longitude);
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("UPDATE users SET latitude = ?, longitude = ?, timezone = ?WHERE user_id = ?");
        $stmt->execute([$latitude, $longitude, $weather['timezone'], $user_id]);
        save_weather($user_id, $weather['log_date'], $weather['weather'], $weather['temperature']);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return [
        'log_date' => $weather['log_date'],
        'weather' => $weather['weather'],
        'temperature' => $weather['temperature']
    ];
}

function set_weather(): void {
    global $pdo;

    $users = $pdo->query("
        SELECT user_id, latitude, longitude, timezone FROM users WHERE latitude IS NOT NULL
        AND longitude IS NOT NULL
        AND timezone IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
    $cache = [];
    foreach ($users as $user) {
        try {
            $user_id = (int) $user['user_id'];
            $date = new DateTimeImmutable('now', new DateTimeZone($user['timezone']));

            $date = $date->format('Y-m-d');

            if (get_weather($user_id, $date) !== null) {
                continue;
            }

            // Identify users sharing the same location.
            $key = implode('|', [
                $user['latitude'],
                $user['longitude'],
                $date
            ]);

            // Fetch weather only once per location.
            if (!isset($cache[$key])) {
                $cache[$key] = fetch_weather(
                    (float) $user['latitude'],
                    (float) $user['longitude']
                );
            }

            $weather = $cache[$key];
            save_weather($user_id, $weather['log_date'], $weather['weather'], $weather['temperature']);

        } catch (Throwable $e) {
            error_log('Weather update failed' . $user['user_id'] . ': ' . $e->getMessage());
        }
    }
}

/**
 * @param string $date Date in YYYY-MM-DD
 * @return bool True if date valid, false otherwise
 */
function valid_weather_date(string $date): bool {
    $parts = explode("-", $date);
    if (count($parts) !== 3) {
        return false;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
}

/**
 * @param bool $success Whether succeeded
 * @param string $message Description of result
 * @param array $extra Additional response data
 * @param int $status HTTP response status
 * @return void
 */
function weather_response(bool $success, string $message, array $extra = [], int $status = 200): void {
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

// Automatic
if (PHP_SAPI === 'cli') {
    set_weather();
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    weather_response(false, 'POST required', [], 405);
}

$ticket = $_POST['ticket'] ?? '';
$action = $_POST['action'] ?? '';

try {
    if (!is_string($ticket) || $ticket === '') {
        weather_response(false, 'Ticket required', [], 401);
    }

    $user_id = check_ticket($ticket);

    if (!$user_id) {
        weather_response(false, 'Invalid ticket', [], 401);
    }

    if ($action === 'set_location') {
        $latitude = $_POST['latitude'] ?? null;
        $longitude = $_POST['longitude'] ?? null;

        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            weather_response(false, 'Invalid coordinates', [], 400);
        }

        $weather = set_location($user_id, (float) $latitude, (float) $longitude);

        weather_response(true, 'Location saved', [
            'weather' => $weather
        ]);
    }

    if ($action === 'get_weather') {
        $date = $_POST['date'] ?? null;
        if ($date !== null) {
            if (!is_string($date) || !valid_weather_date($date)) {
                weather_response(false, 'Invalid date', [], 400);
            }
        }
        $weather = get_weather($user_id, $date);
        if ($weather === null) {
            weather_response(false, 'Weather unavailable', [], 404);
        }
        weather_response(true, 'Weather retrieved', [
            'weather' => $weather
        ]);
    }
    weather_response(false, 'Invalid action', [], 400);
} catch (InvalidArgumentException $e) {
    weather_response(false, $e->getMessage(), [], 400);
} catch (Throwable $e) {
    error_log($e->getMessage());
    weather_response(false, 'Internal server error', [], 500);
}
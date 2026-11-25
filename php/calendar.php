<?php

/**To call: use POST with functionName
 * STORE_LOG: stores a log for given user and data, WILL OVERWRITE PREVIOUS LOG OF THE SAME DAY!
 *      Input parameters: "user_id", "mood", "weather", "temperature"
 *
 *  SHOW_LOG: for a single day
 *       Input parameters: "user_id", "date"
 *          Returns:
 *                $response =
 *                      'mood' =>
 *                      'weather' =>
 *                      'temperature' =>
 *                      'tree_stage' =>
 *                      'daily_goal' =>
 *                      'goal_met' =>
 *
 *   SHOW_LOGS: for all days
 *        Input parameters: "user_id"
 *           Returns:
 *                 $response =
 *                      'entries'=>
 *                           'log_date' =>
 *                           'mood' =>
 *                           'weather' =>
 *                           'temperature' =>
 *                           'tree_stage' =>
 *                           'daily_goal' =>
 *                           'goal_met' =>
 *
 */
/**Daily Log format:
 * `log_id` int(11) NOT NULL,
 * `user_id` int(11) NOT NULL,
 * `log_date` date NOT NULL,
 * `mood` tinyint(4) DEFAULT NULL,
 * `weather` varchar(30) DEFAULT NULL,
 * `temperature` decimal(5,2) DEFAULT NULL,
 * `tree_stage` int(11) DEFAULT NULL,
 * `daily_goal` int(11) DEFAULT NULL,
 * `goal_met` tinyint(1) NOT NULL DEFAULT 0
 */

//initialise db
require_once "db.php";

//automatic setup
if (PHP_SAPI === 'cli') {
    store_log();
}

//method menu
$method = '0';
if (isset($_POST['functionName'])) {
    $method = strtoupper(trim($_POST['functionName']));
}
switch ($method) {
    case "STORE_LOG" :
        store_log();
    case "SHOW_LOG" :
        show_log();
    case "SHOW_LOGS" :
        show_logs();
    default :
        $response = [
            'error' => "Invalid request",
        ];
        echo json_encode($response);
        exit;
}

function store_log() : void {
    global $pdo;
    //obtain all values for input:
        //  `user_id` int(11) NOT NULL,
        //  `log_date` date NOT NULL,
        //  `mood` tinyint(4) DEFAULT NULL,
        //  `weather` varchar(30) DEFAULT NULL,
        //  `temperature` decimal(5,2) DEFAULT NULL,
        //  `tree_stage` int(11) DEFAULT NULL,
        //  `daily_goal` int(11) DEFAULT NULL,
        //  `goal_met` tinyint(1) NOT NULL DEFAULT 0
    $userID = $_POST["user_id"] ?? "";
    $mood = $_POST["mood"] ?? "";
    $weather  = $_POST["weather"] ?? "";
    $temperature = $_POST["temperature"] ?? "";

    if($userID == "" || $mood == "" || $weather == "" || $temperature == "") {
        $response = [
            'error' => "Valid userID mood, weather and temperature required!",
        ];
        echo json_encode($response);
        exit;
    }

    $today = (new DATETIME('today'))->format('Y-m-d');

    $query = $pdo->prepare("SELECT growth_stage FROM trees WHERE user_id = :id");
    $query->execute([
        'id' => $userID
    ]);
    $tree_stage = $query->fetchColumn();

    $query = $pdo->prepare("SELECT daily_goal FROM users WHERE user_id = :id");
    $query->execute([
        'id' => $userID
    ]);
    $daily_goal = $query->fetchColumn();

    //count habits done today, check if goal reached
    $query = $pdo->prepare("
    SELECT COUNT(*)
        FROM habits
        WHERE user_id = :id
        AND last_completion_date = :today
        AND is_active = 1
");

    $query->execute([
        'today' => $today,
        'id'    => $userID
    ]);

    $habitCount = (int)$query->fetchColumn();

    $goal_met = ($habitCount >= (int)$daily_goal);

    //either update or insert depending on whether log already exists
    $query = $pdo->prepare("SELECT log_id FROM daily_logs WHERE user_id = :id AND log_date = :date");
    $query->execute([
        'id' => $userID,
        'date' => $today]);
    $isFound = $query->fetch();

    if($isFound) { //need to update
        $query = $pdo->prepare("
        UPDATE daily_logs 
            SET mood = :mood,
                weather = :weather,
                temperature = :temperature,
                tree_stage = :tree_stage,
                daily_goal = :daily_goal,
                goal_met = :goal_met
            WHERE log_id = :log_id 
        ");
        $query->execute([
            'mood' => $mood,
            'weather' => $weather,
            'temperature' => $temperature,
            'tree_stage' => $tree_stage,
            'daily_goal' => $daily_goal,
            'goal_met' => $goal_met,
            'log_id' => $isFound['log_id']
        ]);
        exit;
    }
    //need to insert
    $query = $pdo->prepare("
        INSERT INTO daily_logs (user_id, log_date, mood, weather, temperature, tree_stage, daily_goal, goal_met) 
            VALUES (:user_id, :date, :mood, :weather, :temperature, :tree_stage, :daily_goal, :goal_met)");
    $query->execute([
        'user_id' => $userID,
        'date' => $today,
        'mood' => $mood,
        'weather' => $weather,
        'temperature' => $temperature,
        'tree_stage' => $tree_stage,
        'daily_goal' => $daily_goal,
        'goal_met' => $goal_met
        ]);
    exit;
}

/**
 * $response =
 *               'mood' =>
 *               'weather' =>
 *               'temperature' =>
 *               'tree_stage' =>
 *               'daily_goal =>
 *               'goal_met' =>
 */
function show_log() : void {
    global $pdo;
    $userID = $_POST["user_id"] ?? "";
    $date = $_POST["date"] ?? "";

    if($userID == "" || $date == "") {
        $response = [
            'error' => "Invalid user_id or date!",
        ];
        echo json_encode($response);
        exit;
    }

    $query = $pdo->prepare("
        SELECT mood, weather, temperature, tree_stage, daily_goal, goal_met FROM daily_logs 
                WHERE user_id = :id 
                AND log_date = :date");

    $query->execute([
        'id' => $userID,
        'date' => $date
    ]);
    $log = $query->fetch();

    if (!$log) {
        echo json_encode([
            'error' => 'No log found for this date'
        ]);
        exit;
    }

    $response = [
        'mood' => $log['mood'],
        'weather' => $log['weather'],
        'temperature' => $log['temperature'],
        'tree_stage' => $log['tree_stage'],
        'daily_goal' => $log['daily_goal'],
        'goal_met' => $log['goal_met']
    ];
    echo json_encode($response);
    exit;


}
function show_logs() : void {
    global $pdo;
    $userID = $_POST["user_id"] ?? "";

    if($userID == "") {
        $response = [
            'error' => "Invalid user_id!",
        ];
        echo json_encode($response);
        exit;
    }

    $query = $pdo->prepare("
        SELECT log_date, mood, weather, temperature, tree_stage, daily_goal, goal_met FROM daily_logs 
                WHERE user_id = :id 
                ORDER BY log_date
           ");

    $query->execute([
        'id' => $userID
    ]);
    $logs = $query->fetchAll();

    //iterate over all logs, storing each as entries in response -> entries ->...
    $entries = [];
    foreach ($logs as $log) {
    $entries[] = [
        'date' => $log['log_date'],
        'mood' => $log['mood'],
        'weather' => $log['weather'],
        'temperature' => $log['temperature'],
        'tree_stage' => $log['tree_stage'],
        'daily_goal' => $log['daily_goal'],
        'goal_met' => $log['goal_met']
        ];
    }
    $response = [
        'entries' => $entries
    ];
    echo json_encode($response);
    exit;
}

?>
<?php
require_once "db.php";

// Creates a new ticket for a user after successful login
function create_ticket($userId) {
    global $pdo;

    $ticket = bin2hex(random_bytes(32));
    $ticketHash = hash('sha256', $ticket);

    $stmt = $pdo->prepare("
        INSERT INTO tickets (user_id, ticket_code, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
    ");

    $stmt->execute([$userId, $ticketHash]);

    // Return the original ticket to the user
    return $ticket;
}

// Checks if a ticket exists and has not expired
// Returns the user ID if valid, or null otherwise
function check_ticket(string $ticket): ?int {
    global $pdo;

    $pdo->exec("DELETE FROM tickets WHERE expires_at <= NOW()");

    $ticketHash = hash('sha256', $ticket);

    $stmt = $pdo->prepare("
        SELECT user_id
        FROM tickets
        WHERE ticket_code = ?
    ");
    $stmt->execute([$ticketHash]);

    $userId = $stmt->fetchColumn();

    return $userId === false ? null : (int) $userId;
}

// Deletes a ticket when the user logs out
// Returns true if a ticket was deleted, false otherwise
function delete_ticket(string $ticket): bool {
    global $pdo;

    $ticketHash = hash('sha256', $ticket);

    $stmt = $pdo->prepare("
        DELETE FROM tickets
        WHERE ticket_code = ?
    ");
    $stmt->execute([$ticketHash]);

    return $stmt->rowCount() > 0;
}

// Run this code only when session.php is called directly,
// not when another PHP file imports it
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    header('Content-Type: application/json');

    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Use POST']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $ticket = $_POST['ticket'] ?? '';

    if ($action == 'check') {
        $userId = check_ticket($ticket);

        if ($userId === null) {
            http_response_code(401);
            echo json_encode(['valid' => false]);
        } else {
            echo json_encode([
                'valid' => true,
                'user_id' => $userId
            ]);
        }

    } elseif ($action == 'logout') {
        $deleted = delete_ticket($ticket);
        echo json_encode(['success' => $deleted]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
}

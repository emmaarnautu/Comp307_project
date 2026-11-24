<?php
require_once "db.php";

/**
 * Creates a new authentication ticket after login
 * @param int $userId The ID of the logged-in user
 * @return string The generated ticket
 * @post A hashed ticket is stored in the database with a 7-day expiration
 */
function create_ticket($userId) {
    global $pdo;
    $ticket = bin2hex(random_bytes(32));
    $ticketHash = hash('sha256', $ticket);
    $stmt = $pdo->prepare("INSERT INTO tickets (user_id, ticket_code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))");
    $stmt->execute([$userId, $ticketHash]);
    return $ticket;
}

/**
 * Checks whether a ticket exists and has not expired
 * @param string $ticket The ticket to check
 * @return int|null The user ID if valid, or null otherwise
 * @post Expired tickets are deleted from the database
 */
function check_ticket(string $ticket): ?int {
    global $pdo;
    $pdo->exec("DELETE FROM tickets WHERE expires_at <= NOW()");
    $ticketHash = hash('sha256', $ticket);
    $stmt = $pdo->prepare("SELECT user_id FROM tickets WHERE ticket_code = ?");
    $stmt->execute([$ticketHash]);
    $userId = $stmt->fetchColumn();
    return $userId === false ? null : (int) $userId;
}

/**
 * Deletes an authentication ticket when the user logs out
 * @param string $ticket The ticket to delete
 * @return bool True if a ticket was deleted, false otherwise
 * @post The matching ticket is removed from the database if it exists
 */
function delete_ticket(string $ticket): bool {
    global $pdo;
    $ticketHash = hash('sha256', $ticket);
    $stmt = $pdo->prepare("DELETE FROM tickets WHERE ticket_code = ?");
    $stmt->execute([$ticketHash]);
    return $stmt->rowCount() > 0;
}

// Run this code only when session.php is called directly
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    header('Content-Type: application/json');

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

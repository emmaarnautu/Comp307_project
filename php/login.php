<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');

/**
 * Sends a JSON response to the frontend and stops execution
 * @param bool $success Whether the operation succeeded
 * @param string $message Response message
 * @param array $extra Additional data to include in the response
 * @param int $status HTTP status code
 * @post The HTTP status and JSON response are sent
 */
function output($success, $message, $extra = [], $status = 200) {
    http_response_code($status);
    echo json_encode(array_merge(['success' => $success,'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    output(false, 'POST required', [], 405);
}

$action = $_POST['action'] ?? '';
global $pdo;

if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        output(false, 'Email and password required', [], 400);
    }

    $stmt = $pdo->prepare("SELECT user_id, username, password_hash, email_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        output(false, 'Invalid email or password', [], 401);
    }

    if (!$user['email_verified']) {
        output(false, 'Please verify your email first', [], 403);
    }

    $ticket = create_ticket($user['user_id']);
    output(true, 'Login successful', ['ticket' => $ticket,'user_id' => $user['user_id'],'username' => $user['username']]);
}
elseif ($action === 'logout') {
    $ticket = $_POST['ticket'] ?? '';
    if ($ticket === '') {
        output(false, 'Ticket required', [], 400);
    }
    delete_ticket($ticket);
    output(true, 'Logged out successfully');
}
else {
    output(false, 'Invalid action', [], 400);
}

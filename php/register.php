<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json');

// Return JSON to frontend
function respond($success, $message, $status = 200) {
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

// Find user by email
function find_user($email) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Send verification email
function send_email($email, $code, $subject) {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;

    $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
    $mail->addAddress($email);

    $mail->Subject = $subject;
    $mail->Body = "Your Blossom code is: $code\n\n"
        . "This code expires in 10 minutes.";

    $mail->send();
}

// Create verification or reset code
function create_code($userId, $table = 'email_verifications') {
    global $pdo;

    if (!in_array($table, ['email_verifications', 'password_resets'], true)) {
        throw new InvalidArgumentException('Invalid code table');
    }

    $code = (string) random_int(100000, 999999);
    $hash = password_hash($code, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO $table (user_id, code_hash, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
        ON DUPLICATE KEY UPDATE
            code_hash = VALUES(code_hash),
            expires_at = VALUES(expires_at)
    ");

    $stmt->execute([$userId, $hash]);

    return $code;
}

// Check verification or reset code
function check_code($userId, $code, $table = 'email_verifications') {
    global $pdo;

    if (!in_array($table, ['email_verifications', 'password_resets'], true)) {
        return false;
    }

    if (!preg_match('/^[0-9]{6}$/', (string)$code)) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT code_hash
        FROM $table
        WHERE user_id = ? AND expires_at > NOW()
    ");

    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();

    return $hash !== false && password_verify($code, $hash);
}

// Delete used code
function delete_code($userId, $table = 'email_verifications') {
    global $pdo;

    if (!in_array($table, ['email_verifications', 'password_resets'], true)) {
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM $table WHERE user_id = ?");
    $stmt->execute([$userId]);
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Use POST', 405);
}

$action = $_POST['action'] ?? '';
$email = strtolower(trim($_POST['email'] ?? ''));
global $pdo;

// REGISTER
if ($action === 'register') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (
        $firstName === '' ||
        $lastName === '' ||
        $username === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($password) < 8
    ) {
        respond(false, 'Invalid registration details', 400);
    }

    $user = find_user($email);

    if ($user && $user['email_verified']) {
        respond(false, 'Email already registered', 409);
    }

    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $usernameOwner = $stmt->fetchColumn();

    if (
        $usernameOwner !== false &&
        (!$user || $usernameOwner != $user['user_id'])
    ) {
        respond(false, 'Username already taken', 409);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    if ($user) {
        $stmt = $pdo->prepare("
            UPDATE users
            SET first_name = ?, last_name = ?,
                username = ?, password_hash = ?
            WHERE user_id = ? AND email_verified = 0
        ");

        $stmt->execute([
            $firstName, $lastName, $username,
            $passwordHash, $user['user_id']
        ]);

        $userId = $user['user_id'];
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO users
                (first_name, last_name, username, email, password_hash, email_verified)
            VALUES (?, ?, ?, ?, ?, 0)
        ");

        $stmt->execute([
            $firstName, $lastName, $username,
            $email, $passwordHash
        ]);

        $userId = $pdo->lastInsertId();
    }

    $code = create_code($userId);

    try {
        send_email($email, $code, 'Blossom Email Verification');
    } catch (Exception $e) {
        respond(false, 'Could not send verification email', 500);
    }

    respond(true, 'Verification code sent');
}


// VERIFY EMAIL
elseif ($action === 'verify') {
    $code = $_POST['code'] ?? '';
    $user = find_user($email);

    if (!$user || !check_code($user['user_id'], $code)) {
        respond(false, 'Invalid or expired code', 400);
    }

    $stmt = $pdo->prepare("
        UPDATE users SET email_verified = 1 WHERE user_id = ?
    ");
    $stmt->execute([$user['user_id']]);

    delete_code($user['user_id']);

    respond(true, 'Email verified');
}


// RESEND VERIFICATION CODE
elseif ($action === 'resend') {
    $user = find_user($email);

    if (!$user || $user['email_verified']) {
        respond(false, 'No unverified account found', 400);
    }

    $code = create_code($user['user_id']);

    try {
        send_email($email, $code, 'Blossom Email Verification');
    } catch (Exception $e) {
        respond(false, 'Could not send email', 500);
    }

    respond(true, 'New verification code sent');
}


// CHANGE PASSWORD
elseif ($action === 'change_password') {
    $ticket = $_POST['ticket'] ?? '';
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';

    $userId = check_ticket($ticket);

    if ($userId === null) {
        respond(false, 'Unauthorized', 401);
    }

    if (strlen($newPassword) < 8) {
        respond(false, 'Password must be at least 8 characters', 400);
    }

    $stmt = $pdo->prepare("
        SELECT password_hash FROM users WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $oldHash = $stmt->fetchColumn();

    if (!$oldHash || !password_verify($currentPassword, $oldHash)) {
        respond(false, 'Incorrect current password', 400);
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        UPDATE users SET password_hash = ? WHERE user_id = ?
    ");
    $stmt->execute([$newHash, $userId]);

    $stmt = $pdo->prepare("DELETE FROM tickets WHERE user_id = ?");
    $stmt->execute([$userId]);

    respond(true, 'Password changed. Please log in again');
}


// FORGOT PASSWORD
elseif ($action === 'forgot_password') {
    $user = find_user($email);

    if ($user) {
        $code = create_code($user['user_id'], 'password_resets');
        try {
            send_email($email, $code, 'Blossom Password Reset');
        } catch (Exception $e) {
            // Keep response generic
        }
    }

    respond(true, 'If the account exists, a code will be sent');
}
// RESET PASSWORD
elseif ($action === 'reset_password') {
    $code = $_POST['code'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    if (strlen($newPassword) < 8) {
        respond(false, 'Password must be at least 8 characters', 400);
    }
    $user = find_user($email);

    if (!$user || !check_code($user['user_id'], $code, 'password_resets')) {
        respond(false, 'Invalid or expired code', 400);
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        UPDATE users SET password_hash = ? WHERE user_id = ?
    ");
    $stmt->execute([$newHash, $user['user_id']]);

    delete_code($user['user_id'], 'password_resets');

    $stmt = $pdo->prepare("DELETE FROM tickets WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);

    respond(true, 'Password reset successfully');
}

else {
    respond(false, 'Invalid action', 400);
}

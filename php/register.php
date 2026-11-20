<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mail_config.php';
use PHPMailer\PHPMailer\PHPMailer;
header('Content-Type: application/json');

/**
 * Sends a JSON response to the frontend and stops execution
 * @param bool $success Whether the operation succeeded
 * @param string $message Response message
 * @param int $status HTTP status code
 * @post The HTTP status and JSON response are sent
 */
function output($success, $message, $status = 200) {
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

/**
 * Finds a user in the database by email
 * @param string $email Email address to search for
 * @return array User data if found, false otherwise
 * @post Returns the matching user record or false if not found
 */
function find_user($email) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Sends a verification or password reset code by email
 * @param string $email Recipient's email address
 * @param string $code Verification or reset code
 * @param string $subject Email subject
 * @post Sends email containing the code is sent if no exception occurs
 */
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
    $mail->Body = "Your Blossom code is: $code\n\n"."This code expires in 10 minutes.";
    $mail->send();
}

/**
 * Creates a six-digit verification or password reset code
 * @param int $userId ID of the user
 * @param string $table Table used to store the code
 * @return string Generated six-digit code
 * @post The hashed code is stored with a 10-minute expiration
 */
function create_code($userId, $table = 'email_verifications') {
    global $pdo;
    if (!in_array($table, ['email_verifications', 'password_resets'], true)) {
        throw new InvalidArgumentException('Invalid code table');
    }

    $code = (string) random_int(100000, 999999);
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO $table (user_id, code_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE)) ON DUPLICATE KEY UPDATE code_hash = VALUES(code_hash), expires_at = VALUES(expires_at)");
    $stmt->execute([$userId, $hash]);

    return $code;
}

/**
 * Checks whether a verification or reset code is valid
 * @param int $userId ID of the user
 * @param string $code Code to validate
 * @param string $table Table containing the code
 * @return bool True if the code is valid, false otherwise
 */
function check_code($userId, $code, $table = 'email_verifications') {
    global $pdo;
    if (!in_array($table, ['email_verifications', 'password_resets'], true)) {
        return false;
    }
    if (!preg_match('/^[0-9]{6}$/', (string)$code)) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT code_hash FROM $table WHERE user_id = ? AND expires_at > NOW()");
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();

    return $hash !== false && password_verify($code, $hash);
}

/**
 * Deletes a user's verification or password reset code
 * @param int $userId ID of the user
 * @param string $table Table containing the code
 * @post The user's code is deleted if the table is valid
 */
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
    output(false, 'Use POST', 405);
}

$action = $_POST['action'] ?? '';
$email = strtolower(trim($_POST['email'] ?? ''));
global $pdo;

//Choose right action
if ($action === 'register') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($firstName === ''||$lastName === '' ||$username === ''||!filter_var($email, FILTER_VALIDATE_EMAIL)||strlen($password) < 8) {
        output(false, 'Invalid registration details', 400);
    }

    $user = find_user($email);

    if ($user && $user['email_verified']) {
        output(false, 'Email already registered', 409);
    }

    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $usernameOwner = $stmt->fetchColumn();

    if (
        $usernameOwner !== false &&
        (!$user || $usernameOwner != $user['user_id'])
    ) {
        output(false, 'Username already taken', 409);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    if ($user) {
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ?, password_hash = ? WHERE user_id = ? AND email_verified = 0");
        $stmt->execute([$firstName, $lastName, $username,$passwordHash, $user['user_id']]);
        $userId = $user['user_id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO users(first_name, last_name, username, email, password_hash, email_verified) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->execute([$firstName, $lastName, $username,$email, $passwordHash]);
        $userId = $pdo->lastInsertId();
    }

    $code = create_code($userId);

    try {
        send_email($email, $code, 'Blossom Email Verification');
    } catch (Exception $e) {
        output(false, 'Could not send verification email', 500);
    }

    output(true, 'Verification code sent');
}
elseif ($action === 'verify') {
    $code = $_POST['code'] ?? '';
    $user = find_user($email);

    if (!$user || !check_code($user['user_id'], $code)) {
        output(false, 'Invalid or expired code', 400);
    }

    $stmt = $pdo->prepare("UPDATE users SET email_verified = 1 WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    delete_code($user['user_id']);

    output(true, 'Email verified');
}
elseif ($action === 'resend') {
    $user = find_user($email);

    if (!$user || $user['email_verified']) {
        output(false, 'No unverified account found', 400);
    }

    $code = create_code($user['user_id']);

    try {
        send_email($email, $code, 'Blossom Email Verification');
    } catch (Exception $e) {
        output(false, 'Could not send email', 500);
    }

    output(true, 'New verification code sent');
}
elseif ($action === 'change_password') {
    $ticket = $_POST['ticket'] ?? '';
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $userId = check_ticket($ticket);

    if ($userId === null) {
        output(false, 'Unauthorized', 401);
    }

    if (strlen($newPassword) < 8) {
        output(false, 'Password must be at least 8 characters', 400);
    }

    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $oldHash = $stmt->fetchColumn();

    if (!$oldHash || !password_verify($currentPassword, $oldHash)) {
        output(false, 'Incorrect current password', 400);
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $stmt->execute([$newHash, $userId]);
    $stmt = $pdo->prepare("DELETE FROM tickets WHERE user_id = ?");
    $stmt->execute([$userId]);

    output(true, 'Password changed. Please log in again');
}
elseif ($action === 'forgot_password') {
    $user = find_user($email);

    if ($user) {
        $code = create_code($user['user_id'], 'email_verifications');
        try {
            send_email($email, $code, 'Blossom Password Reset');
        } catch (Exception $e) {
        }
    }

    output(true, 'If the account exists, a code will be sent');
}
elseif ($action === 'reset_password') {
    $code = $_POST['code'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    if (strlen($newPassword) < 8) {
        output(false, 'Password must be at least 8 characters', 400);
    }
    $user = find_user($email);

    if (!$user || !check_code($user['user_id'], $code, 'email_verifications')) {
        output(false, 'Invalid or expired code', 400);
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $stmt->execute([$newHash, $user['user_id']]);
    delete_code($user['user_id'], 'email_verifications');
    $stmt = $pdo->prepare("DELETE FROM tickets WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);

    output(true, 'Password reset successfully');
}
else {
    output(false, 'Invalid action', 400);
}

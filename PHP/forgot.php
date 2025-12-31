<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ==========================
   JSON RESPONSE HELPER
========================== */
function respond($status, $message = '', $data = []) {
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message
    ], $data));
    exit;
}

/* ==========================
   SEND PIN EMAIL
========================== */
function sendPinEmail($email, $pin) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'noreplyhr007@gmail.com'; 
        $mail->Password = 'zcnoxlhjyolghnxr';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('chalscastijon@gmail.com', 'HRMS Team');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'HRMS Password Reset PIN';
        $mail->Body = "
        <div style='font-family:Arial,sans-serif;color:#343a40;line-height:1.5'>
            <h2 style='color:#0056b3'>HRMS Password Reset PIN</h2>
            <p>Dear <strong>{$email}</strong>,</p>
            <p>Use the following PIN to reset your password:</p>
            <h3 style='text-align:center;color:#dc3545;font-size:32px'>{$pin}</h3>
            <p>This PIN will expire in 15 minutes.</p>
            <p>If you did not request this, please ignore this email.</p>
            <p>Best regards,<br><strong>HRMS Team</strong></p>
        </div>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

/* ==========================
   ROUTER
========================== */
$action = $_POST['action'] ?? '';

if ($action === 'forgot') {
    $email = trim($_POST['email'] ?? '');
    if (!$email) respond('error', 'Please provide your email.');

    // Check if email exists
    $stmt = $pdo->prepare("SELECT employee_id FROM employee WHERE email=?");
    $stmt->execute([$email]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) respond('error', 'This email is not registered.');

    // Generate 6-digit PIN
    $pin = random_int(100000, 999999);
    $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Save PIN in DB
    $stmt = $pdo->prepare("UPDATE employee SET reset_token=?, reset_token_expiry=? WHERE email=?");
    $stmt->execute([$pin, $expiry, $email]);

    if (!sendPinEmail($email, $pin)) {
        respond('error', 'Failed to send PIN. Try again later.');
    }

    respond('ok', 'A PIN has been sent to your email.', ['email' => $email]);
}

if ($action === 'verify_pin') {
    $email = $_POST['email'] ?? '';
    $pin = $_POST['pin'] ?? '';

    if (!$email || !$pin) respond('error', 'Email and PIN are required.');

    $stmt = $pdo->prepare("SELECT employee_id, reset_token_expiry FROM employee WHERE email=? AND reset_token=?");
    $stmt->execute([$email, $pin]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) respond('error', 'Invalid PIN.');
    if (new DateTime() > new DateTime($employee['reset_token_expiry'])) respond('error', 'PIN expired.');

    // PIN valid → allow password reset
    $_SESSION['reset_email'] = $email;
    respond('ok', 'PIN verified. You can now reset your password.');
}

if ($action === 'reset_password') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $email = $_SESSION['reset_email'] ?? '';

    if (!$password || !$confirm) respond('error', 'Please fill all fields.');
    if ($password !== $confirm) respond('error', 'Passwords do not match.');
    if (!$email) respond('error', 'Session expired. Please request PIN again.');

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE employee SET password=?, reset_token=NULL, reset_token_expiry=NULL WHERE email=?");
    $stmt->execute([$hash, $email]);

    unset($_SESSION['reset_email']);
    respond('ok', 'Password reset successfully.');
}

respond('error', 'Invalid request.');

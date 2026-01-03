<?php
$host = 'localhost';
$db   = 'redefence';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=$charset",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$mailerConfig = [
    'mailer'      => 'smtp',
    'host'        => 'smtp.gmail.com',
    'port'        => 587,
    'username'    => 'your-email@gmail.com',
    'password'    => 'your-email-password',
    'encryption'  => 'tls',
    'fromEmail'   => 'your-email@gmail.com',
    'fromName'    => 'HRMS System',
];

// 2FA Configuration
$twoFAConfig = [
    'email_subject' => 'Your HRMS 2FA Code',
    'sms_prefix' => 'HRMS Code: ',
    'code_length' => 6,
    'code_expiry_minutes' => 10,
    'max_attempts' => 3
];

// Simple email function for 2FA (for localhost testing)
function send2FAEmail($to, $code) {
    $subject = "Your HRMS 2FA Verification Code";
    $message = "Your verification code is: <strong>$code</strong><br><br>";
    $message .= "This code will expire in 10 minutes.<br><br>";
    $message .= "If you didn't request this code, please ignore this email.";
    
    $headers = "From: hrms@localhost.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // For localhost, we'll just log it since we can't actually send emails
    error_log("2FA Email to $to: Code = $code");
    
    // In production, you would use: mail($to, $subject, $message, $headers);
    return true;
}

// Simple SMS simulation function (for localhost testing)
function send2FASMS($phone, $code) {
    // Since this is localhost and educational purposes, we'll just log it
    error_log("2FA SMS to $phone: Code = $code");
    
    // For actual SMS, you would need an SMS gateway API
    // This is just a simulation for capstone defense
    return true;
}

// --- REAL-TIME ACTIVITY TRACKING ---
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * If the employee is logged in, update their 'last_active' timestamp
 * This keeps their green "Online" light on in the sidebar.
 */
if (isset($_SESSION['employee_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?");
        $stmt->execute([$_SESSION['employee_id']]);
    } catch (PDOException $e) {
        // Log error but don't stop the page from loading
        error_log("Activity update failed: " . $e->getMessage());
    }
}
?>
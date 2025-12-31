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
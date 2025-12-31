<?php
session_start();
require_once __DIR__ . '/config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!$token || !$password || !$confirm) {
        die("All fields are required.");
    }

    if ($password !== $confirm) {
        die("Passwords do not match.");
    }

    // Validate token
    $stmt = $pdo->prepare("SELECT employee_id, reset_token_expiry FROM employee WHERE reset_token=?");
    $stmt->execute([$token]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) die("Invalid token.");
    if (new DateTime() > new DateTime($employee['reset_token_expiry'])) die("Token expired.");

    // Update password and clear token
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE employee SET password=?, reset_token=NULL, reset_token_expiry=NULL WHERE employee_id=?");
    $stmt->execute([$hash, $employee['employee_id']]);

    echo "Password reset successfully. You can now <a href='login.html'>login</a>.";
    exit;
}

// GET method (show form)
$token = $_GET['token'] ?? '';
if (!$token) die("Invalid request.");
?>
<form method="POST">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <input type="password" name="password" placeholder="New Password" required><br>
    <input type="password" name="confirm" placeholder="Confirm Password" required><br>
    <button type="submit">Reset Password</button>
</form>

<?php
// includes/submit_retirement_application.php
session_start();
require_once 'config.php';
require_once 'hr_notification_helper.php';

header('Content-Type: application/json');

// Validation
if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// CSRF validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Security token invalid']);
    exit;
}

$employeeId = $_POST['employee_id'] ?? $_SESSION['employee_id'];

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // 1. Save retirement application to database
    $stmt = $pdo->prepare("
        INSERT INTO retirement_applications 
        (employee_id, retirement_type, last_day, reason, status, submitted_at) 
        VALUES (?, ?, ?, ?, 'Pending', NOW())
    ");
    
    $stmt->execute([
        $employeeId,
        $_POST['retirement_type'],
        $_POST['last_day'],
        $_POST['reason']
    ]);
    
    $applicationId = $pdo->lastInsertId();
    $trackingNumber = 'RET-' . date('Y') . '-' . str_pad($applicationId, 6, '0', STR_PAD_LEFT);
    
    // 2. Send notification to all HR staff
    $hrNotified = sendRetirementNotification($employeeId, 'retirement_application');
    
    // 3. Create a notification for the employee too
    $employeeNotification = "INSERT INTO notifications (employee_id, title, message, type, is_read, created_at) 
                             VALUES (?, 'Application Submitted', 'Your retirement application (Tracking: $trackingNumber) has been submitted to HR.', 'Retirement', 0, NOW())";
    $pdo->exec($employeeNotification);
    
    // 4. Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Retirement application submitted successfully',
        'hr_notified' => $hrNotified,
        'application_id' => $applicationId,
        'tracking_number' => $trackingNumber
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Retirement application error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
<?php
// includes/mark_notification_read.php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['employee_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    $employee_id = $_SESSION['employee_id'];
    $notification_id = intval($_POST['notification_id']);
    
    try {
        // Verify the notification belongs to the current user
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND employee_id = ?");
        $stmt->execute([$notification_id, $employee_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update notification'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}
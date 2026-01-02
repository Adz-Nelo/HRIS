<?php
// includes/mark_all_read.php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['employee_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_SESSION['employee_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE employee_id = ? AND is_read = 0");
        $stmt->execute([$employee_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    } catch (PDOException $e) {
        error_log("Error marking all as read: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update notifications'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
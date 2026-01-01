<?php
// includes/get_notification_count.php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE employee_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['employee_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['count' => $result['count'] ?? 0]);
} catch (PDOException $e) {
    echo json_encode(['count' => 0]);
}
?>
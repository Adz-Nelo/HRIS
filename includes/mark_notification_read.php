<?php
require_once 'config/db.php';
require_once 'notification_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['notification_id']) && isset($data['employee_id'])) {
        $success = markNotificationAsRead($data['notification_id'], $data['employee_id']);
        echo json_encode(['success' => $success]);
    }
}
?>
<?php
require_once 'config/db.php';
require_once 'notification_helper.php';

header('Content-Type: application/json');

if (isset($_GET['employee_id'])) {
    $notifications = getUnreadNotifications($_GET['employee_id']);
    echo json_encode(['count' => count($notifications)]);
}
?>
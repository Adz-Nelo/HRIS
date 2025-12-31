<?php
// includes/notification_helper.php

function sendNotification($employee_id, $title, $message, $type = 'Leave Application') {
    global $conn;
    
    $sql = "INSERT INTO notifications (employee_id, title, message, type, is_read, created_at) 
            VALUES (?, ?, ?, ?, 0, NOW())";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$employee_id, $title, $message, $type]);
}

function getHRStaffIds() {
    global $conn;
    
    // Get all HR Staff employee IDs
    $sql = "SELECT employee_id FROM employee WHERE role = 'HR Staff' AND status = 'Active'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    $hrStaffIds = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hrStaffIds[] = $row['employee_id'];
    }
    
    return $hrStaffIds;
}

function notifyAllHRStaff($title, $message, $type = 'Leave Application') {
    $hrStaffIds = getHRStaffIds();
    $results = [];
    
    foreach ($hrStaffIds as $staffId) {
        $results[] = sendNotification($staffId, $title, $message, $type);
    }
    
    return !in_array(false, $results, true);
}

function getUnreadNotifications($employee_id) {
    global $conn;
    
    $sql = "SELECT * FROM notifications 
            WHERE employee_id = ? AND is_read = 0 
            ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$employee_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function markNotificationAsRead($notification_id, $employee_id) {
    global $conn;
    
    $sql = "UPDATE notifications SET is_read = 1 
            WHERE notification_id = ? AND employee_id = ?";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$notification_id, $employee_id]);
}

function markAllAsRead($employee_id) {
    global $conn;
    
    $sql = "UPDATE notifications SET is_read = 1 WHERE employee_id = ?";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$employee_id]);
}
?>
<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/notification_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['employee_id'])) {
    // Process benefit claim submission...
    
    if ($success) {
        $employee_id = $_SESSION['employee_id'];
        
        // Get employee details
        $stmt = $conn->prepare("SELECT first_name, last_name FROM employee WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch();
        
        $employee_name = $employee['first_name'] . ' ' . $employee['last_name'];
        $title = "New Benefit Claim";
        $message = $employee_name . " has submitted a benefit claim application.";
        
        // Notify all HR Staff
        notifyAllHRStaff($title, $message, 'Balance Update');
        
        echo json_encode(['success' => true, 'message' => 'Benefit claim submitted and HR notified']);
    }
}
?>
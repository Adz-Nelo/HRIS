<?php
// Add at the top
require_once '../../includes/notification_helper.php';

// After successful leave application submission, add this:
if ($success) {
    // Get employee name
    $employee_name = $employee['first_name'] . ' ' . $employee['last_name'];
    $title = "New Leave Application";
    $message = $employee_name . " has submitted a new leave application (Ref: " . $reference_no . ")";
    
    // Notify all HR Staff
    notifyAllHRStaff($title, $message, 'Leave Application');
}
?>
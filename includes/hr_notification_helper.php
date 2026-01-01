<?php
// /HRIS/includes/hr_notification_helper.php

function sendHRNotification($employeeId, $title, $message, $type = 'Document Request') {
    global $pdo;
    
    try {
        // Get employee details for the notification
        $stmtEmp = $pdo->prepare("SELECT first_name, last_name, department_id FROM employee WHERE employee_id = ?");
        $stmtEmp->execute([$employeeId]);
        $employee = $stmtEmp->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            error_log("Employee not found for ID: $employeeId");
            return 0;
        }
        
        $employeeName = $employee['first_name'] . ' ' . $employee['last_name'];
        $department = $employee['department_id'];
        
        // Find all active HR users (HR Officer, HR Staff, Admin)
        $hrRoles = ['HR Officer', 'HR Staff', 'Admin'];
        $placeholders = str_repeat('?,', count($hrRoles) - 1) . '?';
        
        $sql = "SELECT employee_id FROM employee WHERE role IN ($placeholders) AND status = 'Active'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($hrRoles);
        $hrUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $notificationsCount = 0;
        
        // Create notification for each HR user
        foreach ($hrUsers as $hrUser) {
            $hrUserId = $hrUser['employee_id'];
            
            // Customize message for each notification
            $customMessage = "$message\n\nRequested by: $employeeName\nDepartment: $department";
            
            $insertSql = "INSERT INTO notifications (employee_id, title, message, type, is_read, created_at) 
                          VALUES (?, ?, ?, ?, 0, NOW())";
            $insertStmt = $pdo->prepare($insertSql);
            
            if ($insertStmt->execute([$hrUserId, $title, $customMessage, $type])) {
                $notificationsCount++;
            }
        }
        
        return $notificationsCount;
        
    } catch (PDOException $e) {
        error_log("Error sending HR notification: " . $e->getMessage());
        return 0;
    }
}

// Function to send retirement-specific notifications
function sendRetirementNotification($employeeId, $requestType) {
    $employeeInfo = "Employee ID: $employeeId";
    
    switch($requestType) {
        case 'service_record':
            $title = "Service Record Request";
            $message = "A new Service Record request has been submitted for retirement processing.";
            break;
            
        case 'retirement_application':
            $title = "Retirement Application Submitted";
            $message = "A new retirement application has been submitted for review.";
            break;
            
        case 'clearance_request':
            $title = "Clearance Request";
            $message = "A retirement clearance request has been initiated.";
            break;
            
        default:
            $title = "HR Request";
            $message = "A new request has been submitted to HR.";
    }
    
    $message .= "\n\n$employeeInfo";
    
    return sendHRNotification($employeeId, $title, $message, 'Retirement Request');
}
?>
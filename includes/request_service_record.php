<?php
// /HRIS/includes/request_service_record.php
session_start();
require_once '../config/config.php';

// Check if the helper file exists, if not, define the function here
if (!function_exists('sendRetirementNotification')) {
    function sendHRNotification($employeeId, $title, $message, $type = 'Document Request') {
        global $pdo;
        
        try {
            // Get employee details for the notification
            $stmtEmp = $pdo->prepare("SELECT first_name, last_name, department_id FROM employee WHERE employee_id = ?");
            $stmtEmp->execute([$employeeId]);
            $employee = $stmtEmp->fetch(PDO::FETCH_ASSOC);
            
            if (!$employee) {
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
}

header('Content-Type: application/json');

// Validate session
if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Check if input is valid
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

// Validate CSRF token
if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Security token invalid']);
    exit;
}

// Get request data
$employeeId = $input['employee_id'] ?? $_SESSION['employee_id'];
$requestType = $input['request_type'] ?? 'service_record';

try {
    // Send notification to HR
    $hrNotified = sendRetirementNotification($employeeId, $requestType);
    
    if ($hrNotified > 0) {
        // Log the request in hr_requests table
        $stmt = $pdo->prepare("
            INSERT INTO hr_requests 
            (employee_id, request_type, status, requested_at, hr_notified_count) 
            VALUES (?, ?, 'Pending', NOW(), ?)
        ");
        $stmt->execute([$employeeId, $requestType, $hrNotified]);
        
        // Also log in service_record_requests table
        if ($requestType === 'service_record') {
            try {
                $stmtSR = $pdo->prepare("
                    INSERT INTO service_record_requests 
                    (employee_id, request_type, status, requested_at) 
                    VALUES (?, 'Retirement', 'Pending', NOW())
                    ON DUPLICATE KEY UPDATE 
                    status = 'Pending', 
                    requested_at = NOW()
                ");
                $stmtSR->execute([$employeeId]);
            } catch (PDOException $e) {
                // Table might not exist, ignore this error
                error_log("Service record table error: " . $e->getMessage());
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Request submitted successfully',
            'hr_notified' => $hrNotified,
            'request_id' => $pdo->lastInsertId(),
            'estimated_time' => '3-5 working days'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to notify HR staff. Please contact HR directly.'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Service record request error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
}
?>
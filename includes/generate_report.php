<?php
// /HRIS/includes/generate_report.php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Security token invalid']);
    exit;
}

// Get parameters
$reportType = $_POST['report_type'] ?? '';
$year = $_POST['year'] ?? date('Y');
$month = $_POST['month'] ?? date('F');
$employeeId = $_POST['employee_id'] ?? $_SESSION['employee_id'];

// Validate report type
$validReports = ['service_record', 'leave_ledger', 'dtr_summary'];
if (!in_array($reportType, $validReports)) {
    echo json_encode(['success' => false, 'message' => 'Invalid report type']);
    exit;
}

try {
    // Get employee details
    $stmtEmployee = $pdo->prepare("
        SELECT e.*, d.department_name 
        FROM employee e 
        LEFT JOIN department d ON e.department_id = d.department_id 
        WHERE e.employee_id = ?
    ");
    $stmtEmployee->execute([$employeeId]);
    $employee = $stmtEmployee->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit;
    }
    
    // Generate report based on type
    switch ($reportType) {
        case 'service_record':
            $result = generateServiceRecord($pdo, $employee, $year);
            break;
            
        case 'leave_ledger':
            $result = generateLeaveLedger($pdo, $employee, $year, $month);
            break;
            
        case 'dtr_summary':
            $result = generateDTRSummary($pdo, $employee, $year, $month);
            break;
            
        default:
            $result = ['success' => false, 'message' => 'Unknown report type'];
    }
    
    echo json_encode($result);
    
} catch (PDOException $e) {
    error_log("Report generation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

// Function to generate Service Record
function generateServiceRecord($pdo, $employee, $year) {
    // For now, create a simple HTML file as a placeholder
    // In real implementation, you would fetch actual data and generate PDF
    
    $fileName = "Service_Record_" . $employee['employee_id'] . "_" . date('Ymd_His') . ".html";
    $filePath = "/HRIS/uploads/reports/" . $fileName;
    
    // Create uploads directory if it doesn't exist
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . "/HRIS/uploads/reports/";
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $htmlContent = '<!DOCTYPE html>
    <html>
    <head>
        <title>Service Record - ' . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; margin-bottom: 40px; }
            .header h1 { color: #2c3e50; margin-bottom: 10px; }
            .info-grid { display: grid; grid-template-columns: 150px 1fr; gap: 10px; margin: 15px 0; }
            .info-label { font-weight: bold; color: #555; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>OFFICIAL SERVICE RECORD</h1>
            <h2>BACOLOD CITY GOVERNMENT</h2>
            <p><em>Generated on: ' . date('F d, Y') . '</em></p>
        </div>
        
        <div class="employee-info">
            <h3>EMPLOYEE INFORMATION</h3>
            <div class="info-grid">
                <div class="info-label">Name:</div>
                <div>' . htmlspecialchars($employee['first_name'] . ' ' . $employee['middle_name'] . ' ' . $employee['last_name']) . '</div>
                
                <div class="info-label">Employee ID:</div>
                <div>' . htmlspecialchars($employee['employee_id']) . '</div>
                
                <div class="info-label">Department:</div>
                <div>' . htmlspecialchars($employee['department_name'] ?? 'N/A') . '</div>
                
                <div class="info-label">Position:</div>
                <div>' . htmlspecialchars($employee['position'] ?? 'N/A') . '</div>
                
                <div class="info-label">Date of Birth:</div>
                <div>' . htmlspecialchars($employee['birth_date'] ?? 'N/A') . '</div>
            </div>
        </div>
        
        <div style="margin-top: 50px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <h3>Service Record Details</h3>
            <p>This is a sample service record. In a real implementation, this would contain:</p>
            <ul>
                <li>Employment history with dates</li>
                <li>Position changes and promotions</li>
                <li>Salary adjustments</li>
                <li>Service credits earned</li>
                <li>Official certifications</li>
            </ul>
        </div>
        
        <div class="footer" style="margin-top: 50px; font-size: 12px; color: #666; text-align: center;">
            <p><strong>NOTE:</strong> This is a sample document generated by the HRMS System.</p>
            <p>Document ID: SR-' . $employee['employee_id'] . '-' . date('YmdHis') . '</p>
        </div>
    </body>
    </html>';
    
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $filePath;
    if (file_put_contents($fullPath, $htmlContent)) {
        return [
            'success' => true,
            'message' => 'Service Record generated successfully',
            'file_url' => $filePath,
            'file_name' => $fileName
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to save report file'
        ];
    }
}

// Function to generate Leave Ledger
function generateLeaveLedger($pdo, $employee, $year, $month) {
    // Convert month name to number
    $monthNumber = date('m', strtotime($month . " 1"));
    
    $fileName = "Leave_Ledger_" . $employee['employee_id'] . "_" . $month . "_" . $year . ".html";
    $filePath = "/HRIS/uploads/reports/" . $fileName;
    
    // Create uploads directory if it doesn't exist
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . "/HRIS/uploads/reports/";
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $htmlContent = '<!DOCTYPE html>
    <html>
    <head>
        <title>Leave Ledger - ' . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; margin-bottom: 40px; }
            .header h1 { color: #2c3e50; margin-bottom: 5px; }
            .header h2 { color: #3498db; margin-top: 0; }
            .info-grid { display: grid; grid-template-columns: 150px 1fr; gap: 10px; margin: 15px 0; }
            .info-label { font-weight: bold; color: #555; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background-color: #f8f9fa; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>LEAVE CREDITS LEDGER</h1>
            <h2>' . strtoupper($month) . ' ' . $year . '</h2>
            <p><em>Generated on: ' . date('F d, Y') . '</em></p>
        </div>
        
        <div class="employee-info">
            <h3>EMPLOYEE: ' . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) . '</h3>
            <div class="info-grid">
                <div class="info-label">Employee ID:</div>
                <div>' . htmlspecialchars($employee['employee_id']) . '</div>
                
                <div class="info-label">Department:</div>
                <div>' . htmlspecialchars($employee['department_name'] ?? 'N/A') . '</div>
                
                <div class="info-label">Position:</div>
                <div>' . htmlspecialchars($employee['position'] ?? 'N/A') . '</div>
                
                <div class="info-label">Period:</div>
                <div>' . $month . ' ' . $year . '</div>
            </div>
        </div>
        
        <div style="margin: 30px 0;">
            <h3>LEAVE BALANCE SUMMARY</h3>
            <table>
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Beginning Balance</th>
                        <th>Earned</th>
                        <th>Used</th>
                        <th>Ending Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Vacation Leave</td>
                        <td>15.000</td>
                        <td>1.250</td>
                        <td>2.000</td>
                        <td>14.250</td>
                    </tr>
                    <tr>
                        <td>Sick Leave</td>
                        <td>18.500</td>
                        <td>1.250</td>
                        <td>0.000</td>
                        <td>19.750</td>
                    </tr>
                    <tr>
                        <td>Maternity Leave</td>
                        <td>105.000</td>
                        <td>0.000</td>
                        <td>0.000</td>
                        <td>105.000</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="footer" style="margin-top: 50px; font-size: 12px; color: #666; text-align: center;">
            <p><strong>NOTE:</strong> This is a sample leave ledger generated by the HRMS System.</p>
            <p>Document ID: LL-' . $employee['employee_id'] . '-' . date('YmdHis') . '</p>
        </div>
    </body>
    </html>';
    
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $filePath;
    if (file_put_contents($fullPath, $htmlContent)) {
        return [
            'success' => true,
            'message' => 'Leave Ledger generated successfully',
            'file_url' => $filePath,
            'file_name' => $fileName
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to save report file'
        ];
    }
}

// Function to generate DTR Summary
function generateDTRSummary($pdo, $employee, $year, $month) {
    $fileName = "DTR_Summary_" . $employee['employee_id'] . "_" . $month . "_" . $year . ".html";
    $filePath = "/HRIS/uploads/reports/" . $fileName;
    
    // Create uploads directory if it doesn't exist
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . "/HRIS/uploads/reports/";
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $htmlContent = '<!DOCTYPE html>
    <html>
    <head>
        <title>DTR Summary - ' . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; margin-bottom: 40px; }
            .header h1 { color: #2c3e50; margin-bottom: 5px; }
            .header h2 { color: #3498db; margin-top: 0; }
            .info-grid { display: grid; grid-template-columns: 150px 1fr; gap: 10px; margin: 15px 0; }
            .info-label { font-weight: bold; color: #555; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background-color: #f8f9fa; }
            .late { color: #dc3545; }
            .absent { color: #f59e0b; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>DAILY TIME RECORD SUMMARY</h1>
            <h2>' . strtoupper($month) . ' ' . $year . '</h2>
            <p><em>Generated on: ' . date('F d, Y') . '</em></p>
        </div>
        
        <div class="employee-info">
            <h3>EMPLOYEE: ' . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) . '</h3>
            <div class="info-grid">
                <div class="info-label">Employee ID:</div>
                <div>' . htmlspecialchars($employee['employee_id']) . '</div>
                
                <div class="info-label">Department:</div>
                <div>' . htmlspecialchars($employee['department_name'] ?? 'N/A') . '</div>
                
                <div class="info-label">Position:</div>
                <div>' . htmlspecialchars($employee['position'] ?? 'N/A') . '</div>
                
                <div class="info-label">Period:</div>
                <div>' . $month . ' ' . $year . '</div>
            </div>
        </div>
        
        <div style="margin: 30px 0;">
            <h3>ATTENDANCE SUMMARY</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Day</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Late (mins)</th>
                        <th>Undertime</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>2025-01-01</td>
                        <td>Wednesday</td>
                        <td>08:00 AM</td>
                        <td>05:00 PM</td>
                        <td>0</td>
                        <td>0</td>
                        <td>Present</td>
                    </tr>
                    <tr>
                        <td>2025-01-02</td>
                        <td>Thursday</td>
                        <td>08:15 AM</td>
                        <td>05:00 PM</td>
                        <td class="late">15</td>
                        <td>0</td>
                        <td>Late</td>
                    </tr>
                    <tr>
                        <td>2025-01-03</td>
                        <td>Friday</td>
                        <td>08:00 AM</td>
                        <td>05:00 PM</td>
                        <td>0</td>
                        <td>0</td>
                        <td>Present</td>
                    </tr>
                    <tr>
                        <td>2025-01-06</td>
                        <td>Monday</td>
                        <td>-</td>
                        <td>-</td>
                        <td>-</td>
                        <td>-</td>
                        <td class="absent">Absent</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 30px;">
            <h4>Summary Statistics:</h4>
            <ul>
                <li>Total Working Days: 22</li>
                <li>Days Present: 21</li>
                <li>Days Absent: 1</li>
                <li>Times Late: 3</li>
                <li>Total Late Minutes: 45</li>
                <li>Attendance Rate: 95%</li>
            </ul>
        </div>
        
        <div class="footer" style="margin-top: 50px; font-size: 12px; color: #666; text-align: center;">
            <p><strong>NOTE:</strong> This is a sample DTR summary generated by the HRMS System.</p>
            <p>Document ID: DTR-' . $employee['employee_id'] . '-' . date('YmdHis') . '</p>
        </div>
    </body>
    </html>';
    
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $filePath;
    if (file_put_contents($fullPath, $htmlContent)) {
        return [
            'success' => true,
            'message' => 'DTR Summary generated successfully',
            'file_url' => $filePath,
            'file_name' => $fileName
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to save report file'
        ];
    }
}
?>
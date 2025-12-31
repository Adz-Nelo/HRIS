<?php
session_start();
require_once '../../config/config.php';

// Access control
$allowed_roles = ['Admin', 'HR Officer', 'HR Staff', 'Department Head'];
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'] ?? $_SESSION['role'] ?? '', $allowed_roles)) {
    die("Access denied.");
}

try {
    // Fetch leave analytics data
    $stmt = $pdo->query("
        SELECT 
            e.employee_id,
            CONCAT(e.last_name, ', ', e.first_name) as employee_name,
            d.department_name,
            COALESCE(lb.vacation_leave, 0) as vacation_balance,
            COALESCE(lb.sick_leave, 0) as sick_balance,
            COALESCE(lb.maternity_leave, 0) as maternity_balance,
            COALESCE(lb.paternity_leave, 0) as paternity_balance,
            COALESCE(lb.special_leave, 0) as special_balance,
            (SELECT COUNT(*) FROM leave_application la 
             WHERE la.employee_id = e.employee_id AND la.status = 'Approved') as approved_leaves,
            (SELECT COUNT(*) FROM leave_application la 
             WHERE la.employee_id = e.employee_id AND la.status = 'Pending') as pending_leaves
        FROM employee e
        LEFT JOIN department d ON e.department_id = d.department_id
        LEFT JOIN leave_balance lb ON e.employee_id = lb.employee_id AND lb.is_latest = 1
        WHERE e.status = 'Active'
        ORDER BY d.department_name, e.last_name
    ");
    
    $analytics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate CSV filename
    $filename = "Leave_Analytics_" . date('Y-m-d_H-i-s') . ".csv";
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    // Add headers
    fputcsv($output, [
        'Employee ID', 
        'Employee Name', 
        'Department', 
        'Vacation Leave Balance', 
        'Sick Leave Balance',
        'Maternity Leave Balance',
        'Paternity Leave Balance',
        'Special Leave Balance',
        'Approved Leaves Count',
        'Pending Leaves Count'
    ]);
    
    // Add data rows
    foreach ($analytics as $row) {
        fputcsv($output, [
            $row['employee_id'],
            $row['employee_name'],
            $row['department_name'],
            $row['vacation_balance'],
            $row['sick_balance'],
            $row['maternity_balance'],
            $row['paternity_balance'],
            $row['special_balance'],
            $row['approved_leaves'],
            $row['pending_leaves']
        ]);
    }
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    die("Error generating report: " . $e->getMessage());
}
?>
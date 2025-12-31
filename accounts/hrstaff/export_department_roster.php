<?php
session_start();
require_once '../../config/config.php';

// Access control
$allowed_roles = ['Admin', 'HR Officer', 'HR Staff', 'Department Head'];
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'] ?? $_SESSION['role'] ?? '', $allowed_roles)) {
    die("Access denied.");
}

try {
    // Department roster with headcount
    $stmt = $pdo->query("
        SELECT 
            d.department_id,
            d.department_name,
            COUNT(e.employee_id) as total_employees,
            SUM(CASE WHEN TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) >= 60 THEN 1 ELSE 0 END) as eligible_for_retirement,
            SUM(CASE WHEN TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) BETWEEN 55 AND 59 THEN 1 ELSE 0 END) as approaching_retirement,
            ROUND(AVG(TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE())), 1) as average_age,
            MIN(TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE())) as youngest_age,
            MAX(TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE())) as oldest_age,
            GROUP_CONCAT(CONCAT(e.last_name, ', ', e.first_name, ' (', e.position, ')') SEPARATOR '; ') as employee_list
        FROM department d
        LEFT JOIN employee e ON d.department_id = e.department_id AND e.status = 'Active'
        GROUP BY d.department_id, d.department_name
        ORDER BY d.department_name
    ");
    
    $rosters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate CSV filename
    $filename = "Department_Roster_" . date('Y-m-d_H-i-s') . ".csv";
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    // Add headers
    fputcsv($output, [
        'Department ID', 
        'Department Name', 
        'Total Employees', 
        'Eligible for Retirement (60+)', 
        'Approaching Retirement (55-59)',
        'Average Age',
        'Youngest Age',
        'Oldest Age',
        'Employee List'
    ]);
    
    // Add data rows
    foreach ($rosters as $roster) {
        // Truncate employee list if too long
        $employee_list = $roster['employee_list'];
        if (strlen($employee_list) > 500) {
            $employee_list = substr($employee_list, 0, 500) . '... (truncated)';
        }
        
        fputcsv($output, [
            $roster['department_id'],
            $roster['department_name'],
            $roster['total_employees'] ?: 0,
            $roster['eligible_for_retirement'] ?: 0,
            $roster['approaching_retirement'] ?: 0,
            $roster['average_age'] ?: 'N/A',
            $roster['youngest_age'] ?: 'N/A',
            $roster['oldest_age'] ?: 'N/A',
            $employee_list ?: 'No employees'
        ]);
    }
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    die("Error generating report: " . $e->getMessage());
}
?>
<?php
session_start();
require_once '../../config/config.php';

// Access control
$allowed_roles = ['Admin', 'HR Officer', 'HR Staff', 'Department Head'];
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'] ?? $_SESSION['role'] ?? '', $allowed_roles)) {
    die("Access denied.");
}

try {
    // Fetch all active employees
    $stmt = $pdo->query("
        SELECT 
            e.employee_id,
            e.first_name,
            e.last_name,
            e.position,
            e.department_id,
            d.department_name,
            e.email,
            e.contact_number,
            e.birth_date,
            TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) as age,
            e.status,
            DATE_FORMAT(e.created_at, '%Y-%m-%d') as date_hired
        FROM employee e
        LEFT JOIN department d ON e.department_id = d.department_id
        WHERE e.status = 'Active'
        ORDER BY e.last_name, e.first_name
    ");
    
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate CSV content
    $filename = "Employee_Masterlist_" . date('Y-m-d_H-i-s') . ".csv";
    
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
        'Last Name', 
        'First Name', 
        'Position', 
        'Department', 
        'Email', 
        'Contact Number',
        'Birth Date',
        'Age',
        'Status',
        'Date Hired'
    ]);
    
    // Add data rows
    foreach ($employees as $employee) {
        fputcsv($output, [
            $employee['employee_id'],
            $employee['last_name'],
            $employee['first_name'],
            $employee['position'],
            $employee['department_name'] ?? $employee['department_id'],
            $employee['email'],
            $employee['contact_number'],
            $employee['birth_date'],
            $employee['age'],
            $employee['status'],
            $employee['date_hired']
        ]);
    }
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    die("Error generating report: " . $e->getMessage());
}
?>
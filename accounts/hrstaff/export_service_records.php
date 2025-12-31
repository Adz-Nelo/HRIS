<?php
session_start();
require_once '../../config/config.php';

// Access control
$allowed_roles = ['Admin', 'HR Officer', 'HR Staff', 'Department Head'];
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'] ?? $_SESSION['role'] ?? '', $allowed_roles)) {
    die("Access denied.");
}

try {
    // Fetch service records with retirement info
    $stmt = $pdo->query("
        SELECT 
            e.employee_id,
            e.first_name,
            e.last_name,
            e.position,
            d.department_name,
            e.birth_date,
            TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) as current_age,
            e.status,
            CASE 
                WHEN e.status = 'Retired' THEN 'Retired'
                WHEN TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) >= 60 THEN 'Eligible for Retirement'
                WHEN TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) >= 55 THEN 'Approaching Retirement'
                ELSE 'Active'
            END as retirement_status,
            YEAR(DATE_ADD(e.birth_date, INTERVAL 60 YEAR)) as optional_retirement_year,
            YEAR(DATE_ADD(e.birth_date, INTERVAL 65 YEAR)) as compulsory_retirement_year,
            DATE_FORMAT(e.created_at, '%Y-%m-%d') as date_joined,
            DATE_FORMAT(e.updated_at, '%Y-%m-%d') as last_updated
        FROM employee e
        LEFT JOIN department d ON e.department_id = d.department_id
        WHERE e.birth_date IS NOT NULL AND e.birth_date != '0000-00-00'
        ORDER BY 
            CASE e.status 
                WHEN 'Retired' THEN 1
                WHEN 'Active' THEN 2
                ELSE 3
            END,
            e.last_name
    ");
    
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate CSV filename
    $filename = "Service_Records_" . date('Y-m-d_H-i-s') . ".csv";
    
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
        'Birth Date',
        'Current Age',
        'Status',
        'Retirement Status',
        'Optional Retirement Year',
        'Compulsory Retirement Year',
        'Date Joined',
        'Last Updated'
    ]);
    
    // Add data rows
    foreach ($records as $record) {
        fputcsv($output, [
            $record['employee_id'],
            $record['last_name'],
            $record['first_name'],
            $record['position'],
            $record['department_name'],
            $record['birth_date'],
            $record['current_age'],
            $record['status'],
            $record['retirement_status'],
            $record['optional_retirement_year'],
            $record['compulsory_retirement_year'],
            $record['date_joined'],
            $record['last_updated']
        ]);
    }
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    die("Error generating report: " . $e->getMessage());
}
?>
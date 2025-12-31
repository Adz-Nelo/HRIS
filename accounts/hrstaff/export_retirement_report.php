<?php
session_start();
require_once '../../config/config.php';

// Access control
$allowed_roles = ['Admin', 'HR Officer', 'HR Staff', 'Department Head'];
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'] ?? $_SESSION['role'] ?? '', $allowed_roles)) {
    die("Access denied.");
}

try {
    // Comprehensive retirement report
    $stmt = $pdo->query("
        SELECT 
            e.employee_id,
            CONCAT(e.last_name, ', ', e.first_name) as full_name,
            d.department_name,
            e.position,
            e.birth_date,
            TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) as current_age,
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) >= 60 THEN 'Eligible Now'
                WHEN TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) >= 55 THEN 'Approaching (5 Years)'
                ELSE 'Not Yet Eligible'
            END as eligibility_status,
            YEAR(DATE_ADD(e.birth_date, INTERVAL 60 YEAR)) as optional_retirement_year,
            YEAR(DATE_ADD(e.birth_date, INTERVAL 65 YEAR)) as compulsory_retirement_year,
            DATEDIFF(DATE_ADD(e.birth_date, INTERVAL 60 YEAR), CURDATE()) as days_to_optional,
            DATEDIFF(DATE_ADD(e.birth_date, INTERVAL 65 YEAR), CURDATE()) as days_to_compulsory,
            e.email,
            e.contact_number,
            e.status,
            DATE_FORMAT(e.created_at, '%Y-%m-%d') as service_start_date,
            TIMESTAMPDIFF(YEAR, e.created_at, CURDATE()) as years_of_service
        FROM employee e
        LEFT JOIN department d ON e.department_id = d.department_id
        WHERE e.status = 'Active' 
            AND e.birth_date IS NOT NULL 
            AND e.birth_date != '0000-00-00'
        ORDER BY 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) >= 60 THEN 1
                WHEN TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) >= 55 THEN 2
                ELSE 3
            END,
            DATE_ADD(e.birth_date, INTERVAL 60 YEAR)
    ");
    
    $retirement_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate CSV filename
    $filename = "Retirement_Report_" . date('Y-m-d_H-i-s') . ".csv";
    
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
        'Full Name', 
        'Department', 
        'Position', 
        'Birth Date',
        'Current Age',
        'Eligibility Status',
        'Optional Retirement Year',
        'Compulsory Retirement Year',
        'Days to Optional',
        'Days to Compulsory',
        'Years of Service',
        'Service Start Date',
        'Email',
        'Contact Number',
        'Status'
    ]);
    
    // Add data rows
    foreach ($retirement_data as $data) {
        fputcsv($output, [
            $data['employee_id'],
            $data['full_name'],
            $data['department_name'],
            $data['position'],
            $data['birth_date'],
            $data['current_age'],
            $data['eligibility_status'],
            $data['optional_retirement_year'],
            $data['compulsory_retirement_year'],
            $data['days_to_optional'],
            $data['days_to_compulsory'],
            $data['years_of_service'],
            $data['service_start_date'],
            $data['email'],
            $data['contact_number'],
            $data['status']
        ]);
    }
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    die("Error generating report: " . $e->getMessage());
}
?>
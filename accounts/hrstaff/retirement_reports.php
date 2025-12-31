<?php
// retirement_reports.php - Retirement Analytics & Benefits Utilization System
session_start();
require_once '../../config/config.php';


// ============================================
// 1. SECURITY & ACCESS CONTROL
// ============================================
$debug_mode = false;
error_reporting($debug_mode ? E_ALL : 0);
ini_set('display_errors', $debug_mode ? 1 : 0);

// Check if user is HR Staff/Officer/Admin
$employee_id = $_SESSION['employee_id'] ?? null;
$user_role = $_SESSION['role'] ?? null;

// Allowed roles: HR Staff, HR Officer, Admin
// $allowed_roles = ['HR Staff', 'HR Officer', 'Admin', 'Department Head'];
// if (!$employee_id || !in_array($user_role, $allowed_roles)) {
//     header('Location: /HRIS/login.php');
//     exit;
// }

// ============================================
// 2. GET REPORT FILTERS FROM URL
// ============================================
$current_year = date('Y');
$filter_year = $_GET['year'] ?? $current_year;
$filter_month = $_GET['month'] ?? 'all';
$filter_department = $_GET['department'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$report_type = $_GET['type'] ?? 'summary';

// Validate year
if (!is_numeric($filter_year) || $filter_year < 1900 || $filter_year > 2100) {
    $filter_year = $current_year;
}

// ============================================
// 3. FETCH DEPARTMENTS FOR FILTER
// ============================================
try {
    $stmtDept = $pdo->query("SELECT department_id, department_name FROM department ORDER BY department_name");
    $departments = $stmtDept->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
    error_log("Department fetch error: " . $e->getMessage());
}

// ============================================
// 4. ANALYTICS DATA QUERIES
// ============================================

// A. Retirement Growth Projection (2026 and beyond)
try {
    $growth_query = "
        SELECT 
            YEAR(DATE_ADD(birth_date, INTERVAL 60 YEAR)) as retirement_year,
            COUNT(*) as projected_retirees,
            GROUP_CONCAT(CONCAT(first_name, ' ', last_name, ' (', YEAR(birth_date), ')') ORDER BY birth_date) as retirees_list
        FROM employee 
        WHERE status = 'Active' 
            AND birth_date IS NOT NULL 
            AND birth_date != '0000-00-00'
            AND YEAR(DATE_ADD(birth_date, INTERVAL 60 YEAR)) >= ?
        GROUP BY YEAR(DATE_ADD(birth_date, INTERVAL 60 YEAR))
        ORDER BY retirement_year
        LIMIT 10
    ";
    
    $stmtGrowth = $pdo->prepare($growth_query);
    $stmtGrowth->execute([$filter_year]);
    $growth_data = $stmtGrowth->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $growth_data = [];
    error_log("Growth projection error: " . $e->getMessage());
}

// B. Current Retirees List (Age 60+)
try {
    $retirees_query = "
        SELECT 
            e.employee_id,
            CONCAT(e.first_name, ' ', e.last_name) as full_name,
            e.department_id,
            d.department_name,
            e.position,
            e.birth_date,
            TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) as current_age,
            YEAR(DATE_ADD(e.birth_date, INTERVAL 60 YEAR)) as optional_retirement_year,
            YEAR(DATE_ADD(e.birth_date, INTERVAL 65 YEAR)) as compulsory_retirement_year,
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) >= 60 THEN 'Eligible'
                WHEN TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) >= 55 THEN 'Approaching (5 Years)'
                ELSE 'Not Yet Eligible'
            END as eligibility_status,
            e.status,
            e.email,
            e.contact_number
        FROM employee e
        LEFT JOIN department d ON e.department_id = d.department_id
        WHERE e.status = 'Active' 
            AND e.birth_date IS NOT NULL 
            AND e.birth_date != '0000-00-00'
            AND TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) >= 55
        ORDER BY current_age DESC, last_name
    ";
    
    if ($filter_department != 'all') {
        $retirees_query .= " AND e.department_id = :dept";
    }
    
    $stmtRetirees = $pdo->prepare($retirees_query);
    
    if ($filter_department != 'all') {
        $stmtRetirees->bindParam(':dept', $filter_department);
    }
    
    $stmtRetirees->execute();
    $retirees = $stmtRetirees->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $retirees = [];
    error_log("Retirees list error: " . $e->getMessage());
}

// C. Service Year Analysis
try {
    // Calculate approximate service years based on age (assuming start at age 20)
    $service_query = "
        SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) - 20 < 5 THEN '0-4 Years'
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) - 20 BETWEEN 5 AND 9 THEN '5-9 Years'
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) - 20 BETWEEN 10 AND 14 THEN '10-14 Years'
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) - 20 BETWEEN 15 AND 19 THEN '15-19 Years'
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) - 20 BETWEEN 20 AND 24 THEN '20-24 Years'
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) - 20 BETWEEN 25 AND 29 THEN '25-29 Years'
                WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) - 20 BETWEEN 30 AND 34 THEN '30-34 Years'
                ELSE '35+ Years'
            END as service_range,
            COUNT(*) as employee_count,
            ROUND(AVG(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())), 1) as avg_age,
            GROUP_CONCAT(CONCAT(last_name, ', ', first_name, ' (', TIMESTAMPDIFF(YEAR, birth_date, CURDATE()), ' yrs)') SEPARATOR '; ') as sample_employees
        FROM employee 
        WHERE status = 'Active' 
            AND birth_date IS NOT NULL 
            AND birth_date != '0000-00-00'
        GROUP BY service_range
        ORDER BY 
            CASE service_range
                WHEN '0-4 Years' THEN 1
                WHEN '5-9 Years' THEN 2
                WHEN '10-14 Years' THEN 3
                WHEN '15-19 Years' THEN 4
                WHEN '20-24 Years' THEN 5
                WHEN '25-29 Years' THEN 6
                WHEN '30-34 Years' THEN 7
                ELSE 8
            END
    ";
    
    $stmtService = $pdo->query($service_query);
    $service_data = $stmtService->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $service_data = [];
    error_log("Service analysis error: " . $e->getMessage());
}

// D. Benefits Utilization Analysis
try {
    // Check if retirement_applications table exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'retirement_applications'");
    $table_exists = $table_check->fetch();
    
    if ($table_exists) {
        $benefits_query = "
            SELECT 
                YEAR(created_at) as application_year,
                MONTH(created_at) as application_month,
                retirement_type,
                COUNT(*) as application_count,
                SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_count
            FROM retirement_applications 
            WHERE YEAR(created_at) = ?
            GROUP BY YEAR(created_at), MONTH(created_at), retirement_type
            ORDER BY application_year DESC, application_month, retirement_type
        ";
        
        $stmtBenefits = $pdo->prepare($benefits_query);
        $stmtBenefits->execute([$filter_year]);
        $benefits_data = $stmtBenefits->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $benefits_data = [];
    }
} catch (PDOException $e) {
    $benefits_data = [];
    error_log("Benefits utilization error: " . $e->getMessage());
}

// E. Age Distribution Analysis
try {
    $age_query = "
        SELECT 
            CASE 
                WHEN age < 25 THEN 'Under 25'
                WHEN age BETWEEN 25 AND 29 THEN '25-29'
                WHEN age BETWEEN 30 AND 34 THEN '30-34'
                WHEN age BETWEEN 35 AND 39 THEN '35-39'
                WHEN age BETWEEN 40 AND 44 THEN '40-44'
                WHEN age BETWEEN 45 AND 49 THEN '45-49'
                WHEN age BETWEEN 50 AND 54 THEN '50-54'
                WHEN age BETWEEN 55 AND 59 THEN '55-59'
                WHEN age BETWEEN 60 AND 64 THEN '60-64'
                ELSE '65+'
            END as age_group,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM employee WHERE status = 'Active' AND birth_date IS NOT NULL), 1) as percentage
        FROM (
            SELECT 
                TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) as age
            FROM employee 
            WHERE status = 'Active' 
                AND birth_date IS NOT NULL 
                AND birth_date != '0000-00-00'
        ) as age_calc
        GROUP BY age_group
        ORDER BY 
            CASE age_group
                WHEN 'Under 25' THEN 1
                WHEN '25-29' THEN 2
                WHEN '30-34' THEN 3
                WHEN '35-39' THEN 4
                WHEN '40-44' THEN 5
                WHEN '45-49' THEN 6
                WHEN '50-54' THEN 7
                WHEN '55-59' THEN 8
                WHEN '60-64' THEN 9
                ELSE 10
            END
    ";
    
    $stmtAge = $pdo->query($age_query);
    $age_data = $stmtAge->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $age_data = [];
    error_log("Age distribution error: " . $e->getMessage());
}

// F. Department-wise Retirement Analysis
try {
    $dept_analysis_query = "
        SELECT 
            COALESCE(d.department_name, e.department_id) as department,
            COUNT(*) as total_employees,
            SUM(CASE WHEN TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) >= 60 THEN 1 ELSE 0 END) as eligible_for_retirement,
            SUM(CASE WHEN TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) BETWEEN 55 AND 59 THEN 1 ELSE 0 END) as approaching_retirement,
            ROUND(AVG(TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE())), 1) as average_age,
            MIN(TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE())) as youngest_age,
            MAX(TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE())) as oldest_age
        FROM employee e
        LEFT JOIN department d ON e.department_id = d.department_id
        WHERE e.status = 'Active' 
            AND e.birth_date IS NOT NULL 
            AND e.birth_date != '0000-00-00'
        GROUP BY COALESCE(d.department_name, e.department_id)
        HAVING COUNT(*) > 0
        ORDER BY eligible_for_retirement DESC, total_employees DESC
    ";
    
    $stmtDeptAnalysis = $pdo->query($dept_analysis_query);
    $dept_analysis = $stmtDeptAnalysis->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $dept_analysis = [];
    error_log("Department analysis error: " . $e->getMessage());
}

// ============================================
// 5. SUMMARY STATISTICS
// ============================================
$total_employees = 0;
$eligible_count = 0;
$approaching_count = 0;
$avg_age = 0;
$oldest_age = 0;

if (!empty($retirees)) {
    $total_employees = count($retirees);
    $eligible_count = count(array_filter($retirees, function($r) {
        return $r['current_age'] >= 60;
    }));
    $approaching_count = count(array_filter($retirees, function($r) {
        return $r['current_age'] >= 55 && $r['current_age'] < 60;
    }));
    
    $ages = array_column($retirees, 'current_age');
    $avg_age = $ages ? round(array_sum($ages) / count($ages), 1) : 0;
    $oldest_age = $ages ? max($ages) : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Retirement Analytics & Benefits Utilization - HRMS</title>
    
    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">
    <link rel="stylesheet" href="/HRIS/assets/css/style.css">
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Chart.js for Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* Report-specific styles */
        .report-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .report-filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #4b5563;
            font-size: 14px;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 10px 15px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border-top: 5px solid;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .stat-card.primary {
            border-color: #4f46e5;
        }
        
        .stat-card.success {
            border-color: #10b981;
        }
        
        .stat-card.warning {
            border-color: #f59e0b;
        }
        
        .stat-card.danger {
            border-color: #ef4444;
        }
        
        .stat-card .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            margin: 10px 0;
            color: #1f2937;
        }
        
        .stat-card .stat-label {
            color: #6b7280;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin: 25px 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .chart-container h3 {
            margin: 0 0 20px 0;
            color: #374151;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .report-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin: 25px 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .report-section h3 {
            margin: 0 0 20px 0;
            color: #374151;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #f3f4f6;
            padding-bottom: 15px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .data-table th {
            background: #f9fafb;
            color: #4b5563;
            font-weight: 600;
            text-align: left;
            padding: 15px;
            border-bottom: 2px solid #e5e7eb;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
            color: #4b5563;
        }
        
        .data-table tr:hover {
            background: #f9fafb;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .export-options {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .export-btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: white;
            color: #4b5563;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .export-btn:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }
        
        .export-btn.pdf {
            border-color: #ef4444;
            color: #ef4444;
        }
        
        .export-btn.excel {
            border-color: #10b981;
            color: #10b981;
        }
        
        .export-btn.print {
            border-color: #4f46e5;
            color: #4f46e5;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
            margin: 20px 0;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 25px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
            border-left: 4px solid #4f46e5;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -28px;
            top: 20px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #4f46e5;
            border: 3px solid white;
            box-shadow: 0 0 0 3px #4f46e5;
        }
        
        .timeline-year {
            font-weight: bold;
            color: #4f46e5;
            margin-bottom: 5px;
        }
        
        .timeline-count {
            font-size: 1.5em;
            font-weight: bold;
            color: #1f2937;
        }
        
        .timeline-employees {
            font-size: 13px;
            color: #6b7280;
            margin-top: 8px;
            line-height: 1.4;
        }
        
        /* SCREEN-ONLY ELEMENTS */
        .no-print {
            /* Empty - will be overridden in print */
        }
        
        /* HIDE PRINT LAYOUT ON SCREEN */
        .print-document {
            display: none;
        }
        
        /* ============================================
           PRINT-SPECIFIC STYLES - DOCUMENT STYLE
        ============================================ */
        
        @media print {
            /* Hide everything on screen except print document */
            * {
                box-shadow: none !important;
                text-shadow: none !important;
            }
            
            body {
                margin: 0 !important;
                padding: 0 !important;
                font-size: 11pt !important;
                line-height: 1.4 !important;
                color: #000 !important;
                background: #fff !important;
                font-family: "Arial", "Helvetica", sans-serif !important;
            }
            
            /* Hide all screen elements */
            .wrapper,
            #sidebar-placeholder,
            #topbar-placeholder,
            #rightbar-placeholder,
            .main-content,
            .dashboard-wrapper,
            .report-header,
            .report-filters,
            .stats-grid,
            .stat-card,
            .chart-container,
            .report-section,
            .data-table,
            .export-options,
            .export-btn,
            button,
            .filter-row,
            .filter-group,
            .bi,
            .fas,
            .fa,
            i,
            .timeline,
            .timeline-item,
            canvas,
            .no-print {
                display: none !important;
                visibility: hidden !important;
                height: 0 !important;
                width: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            /* Show print document */
            .print-document {
                display: block !important;
                visibility: visible !important;
                height: auto !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 20px !important;
            }
            
            /* Page breaks */
            .page-break {
                page-break-before: always;
            }
            
            .avoid-break {
                page-break-inside: avoid;
                break-inside: avoid;
            }
            
            /* Document header */
            .document-header {
                text-align: center;
                border-bottom: 3px double #000;
                padding-bottom: 15px;
                margin-bottom: 30px;
                page-break-after: avoid;
            }
            
            .document-header h1 {
                color: #000 !important;
                font-size: 22pt !important;
                margin: 0 0 5px 0 !important;
            }
            
            .document-header .subtitle {
                color: #555 !important;
                font-size: 12pt !important;
                margin-bottom: 10px !important;
            }
            
            .document-header .metadata {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 15px;
                margin-top: 20px;
                font-size: 9pt;
                color: #666;
            }
            
            .document-header .metadata div {
                text-align: center;
                padding: 8px;
                background: #f5f5f5 !important;
                border: 1px solid #ddd !important;
                border-radius: 4px;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .document-header .metadata strong {
                display: block;
                color: #000;
                margin-bottom: 3px;
            }
            
            /* Document sections */
            .document-section {
                margin-bottom: 30px;
                page-break-inside: avoid;
                padding: 0;
            }
            
            .document-section h2 {
                color: #000 !important;
                font-size: 14pt !important;
                margin: 0 0 15px 0 !important;
                padding-bottom: 8px;
                border-bottom: 2px solid #000;
                page-break-after: avoid;
            }
            
            /* Document tables */
            .document-table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0 25px 0;
                font-size: 9pt;
                page-break-inside: avoid;
            }
            
            .document-table th {
                background: #f5f5f5 !important;
                color: #000 !important;
                border: 1px solid #ddd !important;
                padding: 10px 8px !important;
                font-weight: bold;
                text-align: left;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .document-table td {
                border: 1px solid #ddd !important;
                padding: 8px !important;
                vertical-align: top;
                color: #000 !important;
            }
            
            .document-table tr:nth-child(even) {
                background: #fafafa !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            /* Statistics grid for print */
            .print-stats-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 15px;
                margin: 20px 0;
                page-break-inside: avoid;
            }
            
            .print-stat-box {
                border: 1px solid #ddd;
                padding: 15px;
                text-align: center;
                background: #f9f9f9 !important;
                border-radius: 4px;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .print-stat-value {
                font-size: 18pt;
                font-weight: bold;
                color: #000;
                margin: 10px 0;
                display: block;
            }
            
            .print-stat-label {
                font-size: 9pt;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            /* Timeline for print */
            .print-timeline {
                margin: 20px 0;
            }
            
            .print-timeline-item {
                display: flex;
                align-items: flex-start;
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px solid #eee;
            }
            
            .print-timeline-year {
                min-width: 80px;
                font-weight: bold;
                color: #000;
            }
            
            .print-timeline-content {
                flex: 1;
            }
            
            .print-timeline-count {
                font-weight: bold;
                color: #000;
                margin-bottom: 5px;
            }
            
            /* Charts replacement for print */
            .print-chart-replacement {
                background: #f9f9f9 !important;
                padding: 15px;
                margin: 20px 0;
                border: 1px solid #ddd;
                border-left: 4px solid #4f46e5;
                page-break-inside: avoid;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .print-chart-replacement h4 {
                margin: 0 0 10px 0;
                color: #000;
                font-size: 11pt;
            }
            
            .print-chart-data {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                font-size: 9pt;
            }
            
            .print-chart-row {
                display: flex;
                justify-content: space-between;
                padding: 5px 0;
                border-bottom: 1px dashed #eee;
            }
            
            /* Footer */
            .print-footer {
                margin-top: 40px;
                padding-top: 15px;
                border-top: 1px solid #ddd;
                font-size: 8pt;
                color: #666;
                position: fixed;
                bottom: 0;
                width: 100%;
            }
            
            .print-footer table {
                width: 100%;
            }
            
            .print-footer td {
                padding: 5px 0;
            }
            
            .page-number {
                text-align: center;
                font-size: 9pt;
                color: #666;
                margin-top: 10px;
            }
            
            /* Badges for print */
            .print-badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 8pt;
                font-weight: bold;
                border: 1px solid #ccc;
                background: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            /* Landscape tables */
            .landscape-table {
                transform: rotate(0);
                width: 100%;
            }
            
            /* Utilities */
            .text-center {
                text-align: center;
            }
            
            .text-right {
                text-align: right;
            }
            
            .mb-10 {
                margin-bottom: 10px;
            }
            
            .mb-20 {
                margin-bottom: 20px;
            }
            
            .mt-20 {
                margin-top: 20px;
            }
            
            .strong {
                font-weight: bold;
            }
            
            .muted {
                color: #666;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .report-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>

<body>
    <!-- SCREEN CONTENT -->
    <div class="wrapper">
        <div id="sidebar-placeholder"></div>
        
        <main class="main-content" id="main-content">
            <div id="topbar-placeholder"></div>
            
            <div class="dashboard-wrapper">
                <!-- REPORT HEADER -->
                <div class="report-header">
                    <div>
                        <h1 style="margin: 0; color: white; font-size: 1.8em;">
                            <i class="fas fa-chart-line me-2"></i> Retirement Analytics & Benefits Utilization
                        </h1>
                        <p style="margin: 8px 0 0 0; opacity: 0.9; font-size: 1.1em;">
                            Comprehensive analysis of retirement trends, benefits usage, and workforce demographics
                        </p>
                    </div><br>
                    <div class="no-print">
                        <button onclick="printReport()" 
                                style="background: white; color: #4f46e5; border: none; padding: 12px 25px; 
                                       border-radius: 8px; font-weight: bold; cursor: pointer; 
                                       transition: all 0.3s; display: flex; align-items: center;">
                            <i class="fas fa-print me-2"></i>&nbsp; Print Report
                        </button>
                    </div>
                </div>
                
                <!-- REPORT FILTERS -->
                <div class="report-filters no-print">
                    <h3 style="margin: 0 0 20px 0; color: #374151; font-size: 1.2em;">
                        <i class="fas fa-filter me-2"></i> Report Filters
                    </h3>
                    <form method="GET" action="" id="reportFilterForm">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="year"><i class="fas fa-calendar-alt me-1"></i> Report Year</label>
                                <select name="year" id="year" onchange="this.form.submit()">
                                    <?php for($y = $current_year + 5; $y >= $current_year - 5; $y--): ?>
                                        <option value="<?= $y ?>" <?= $y == $filter_year ? 'selected' : '' ?>>
                                            <?= $y ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="department"><i class="fas fa-building me-1"></i> Department</label>
                                <select name="department" id="department" onchange="this.form.submit()">
                                    <option value="all" <?= $filter_department == 'all' ? 'selected' : '' ?>>All Departments</option>
                                    <?php foreach($departments as $dept): ?>
                                        <option value="<?= htmlspecialchars($dept['department_id']) ?>" 
                                                <?= $filter_department == $dept['department_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="report_type"><i class="fas fa-chart-bar me-1"></i> Report Type</label>
                                <select name="type" id="report_type" onchange="this.form.submit()">
                                    <option value="summary" <?= $report_type == 'summary' ? 'selected' : '' ?>>Summary Dashboard</option>
                                    <option value="growth" <?= $report_type == 'growth' ? 'selected' : '' ?>>Growth Projection</option>
                                    <option value="retirees" <?= $report_type == 'retirees' ? 'selected' : '' ?>>Retirees List</option>
                                    <option value="service" <?= $report_type == 'service' ? 'selected' : '' ?>>Service Year Analysis</option>
                                    <option value="benefits" <?= $report_type == 'benefits' ? 'selected' : '' ?>>Benefits Utilization</option>
                                    <option value="department" <?= $report_type == 'department' ? 'selected' : '' ?>>Department Analysis</option>
                                </select>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <button type="submit" 
                                    style="background: #4f46e5; color: white; border: none; padding: 10px 20px; 
                                           border-radius: 8px; font-weight: bold; cursor: pointer; 
                                           display: flex; align-items: center;">
                                <i class="fas fa-sync-alt me-2"></i>&nbsp; Apply Filters
                            </button>
                            <button type="button" onclick="resetFilters()"
                                    style="background: #f3f4f6; color: #4b5563; border: 1px solid #d1d5db; padding: 10px 20px; 
                                           border-radius: 8px; cursor: pointer; display: flex; align-items: center;">
                                <i class="fas fa-times me-2"></i>&nbsp; Reset
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- SCREEN CONTENT CONTINUES HERE -->
                <?php if ($report_type == 'summary'): ?>
                <div class="stats-grid">
                    <div class="stat-card primary">
                        <div class="stat-value"><?= number_format($total_employees) ?></div>
                        <div class="stat-label">Total Employees Analyzed</div>
                        <div style="margin-top: 10px; font-size: 12px; color: #9ca3af;">
                            Age 55 and above
                        </div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-value"><?= number_format($eligible_count) ?></div>
                        <div class="stat-label">Eligible for Retirement (60+)</div>
                        <div style="margin-top: 10px; font-size: 12px; color: #9ca3af;">
                            Can apply for optional retirement
                        </div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-value"><?= number_format($approaching_count) ?></div>
                        <div class="stat-label">Approaching Retirement (55-59)</div>
                        <div style="margin-top: 10px; font-size: 12px; color: #9ca3af;">
                            Within 5 years of eligibility
                        </div>
                    </div>
                    
                    <div class="stat-card danger">
                        <div class="stat-value"><?= $avg_age ?> yrs</div>
                        <div class="stat-label">Average Age</div>
                        <div style="margin-top: 10px; font-size: 12px; color: #9ca3af;">
                            Oldest: <?= $oldest_age ?> years
                        </div>
                    </div>
                </div>
                
                <!-- Age Distribution Chart -->
                <div class="chart-container">
                    <h3><i class="fas fa-chart-pie me-2"></i> Age Distribution Analysis</h3>
                    <canvas id="ageDistributionChart" height="100"></canvas>
                </div>
                
                <!-- Department Analysis -->
                <div class="report-section">
                    <h3><i class="fas fa-building me-2"></i> Department-wise Retirement Analysis</h3>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Total Employees</th>
                                    <th>Eligible for Retirement</th>
                                    <th>Approaching Retirement</th>
                                    <th>Average Age</th>
                                    <th>Youngest</th>
                                    <th>Oldest</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($dept_analysis as $dept): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($dept['department']) ?></strong></td>
                                    <td><?= $dept['total_employees'] ?></td>
                                    <td>
                                        <span class="badge <?= $dept['eligible_for_retirement'] > 0 ? 'badge-success' : 'badge-info' ?>">
                                            <?= $dept['eligible_for_retirement'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-warning">
                                            <?= $dept['approaching_retirement'] ?>
                                        </span>
                                    </td>
                                    <td><?= $dept['average_age'] ?> yrs</td>
                                    <td><?= $dept['youngest_age'] ?> yrs</td>
                                    <td><?= $dept['oldest_age'] ?> yrs</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Service Year Analysis -->
                <div class="report-section">
                    <h3><i class="fas fa-business-time me-2"></i> Service Year Analysis</h3>
                    <canvas id="serviceYearChart" height="80"></canvas>
                    <div style="overflow-x: auto; margin-top: 20px;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Service Range</th>
                                    <th>Employee Count</th>
                                    <th>Percentage</th>
                                    <th>Average Age</th>
                                    <th>Sample Employees</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($service_data as $service): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($service['service_range']) ?></strong></td>
                                    <td><?= $service['employee_count'] ?></td>
                                    <td><?= $service['employee_count'] ?> employees</td>
                                    <td><?= $service['avg_age'] ?> yrs</td>
                                    <td style="font-size: 12px; color: #6b7280;">
                                        <?= htmlspecialchars(implode(', ', array_slice(explode('; ', $service['sample_employees']), 0, 3))) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($report_type == 'growth'): ?>
                <!-- GROWTH PROJECTION REPORT -->
                <div class="report-section">
                    <h3><i class="fas fa-chart-line me-2"></i> Retirement Growth Projection (<?= $filter_year ?> and Beyond)</h3>
                    
                    <?php if (!empty($growth_data)): ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                        <!-- Timeline -->
                        <div class="timeline">
                            <?php foreach($growth_data as $growth): ?>
                            <div class="timeline-item">
                                <div class="timeline-year">Year <?= $growth['retirement_year'] ?></div>
                                <div class="timeline-count"><?= $growth['projected_retirees'] ?> Employees</div>
                                <div class="timeline-employees">
                                    <?php 
                                    $employees = explode(',', $growth['retirees_list']);
                                    $first_three = array_slice($employees, 0, 3);
                                    echo htmlspecialchars(implode(', ', $first_three));
                                    if (count($employees) > 3) echo ' and ' . (count($employees) - 3) . ' more';
                                    ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Growth Chart -->
                        <div>
                            <canvas id="growthChart" height="200"></canvas>
                        </div>
                    </div>
                    
                    <!-- Detailed Table -->
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Retirement Year</th>
                                    <th>Projected Retirees</th>
                                    <th>Affected Employees</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($growth_data as $growth): ?>
                                <tr>
                                    <td><strong><?= $growth['retirement_year'] ?></strong></td>
                                    <td>
                                        <span class="badge badge-danger" style="font-size: 1.1em;">
                                            <?= $growth['projected_retirees'] ?> employees
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px; color: #6b7280; line-height: 1.4;">
                                            <?= htmlspecialchars(str_replace(',', ', ', $growth['retirees_list'])) ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div style="text-align: center; padding: 40px; background: #f9fafb; border-radius: 8px;">
                        <i class="fas fa-chart-line fa-3x" style="color: #d1d5db; margin-bottom: 20px;"></i>
                        <h4 style="color: #6b7280; margin-bottom: 10px;">No Growth Projection Data</h4>
                        <p style="color: #9ca3af;">No retirement projections available for <?= $filter_year ?> and beyond.</p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($report_type == 'retirees'): ?>
                <!-- RETIREES LIST REPORT -->
                <div class="report-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0;">
                            <i class="fas fa-users me-2"></i> Retirees List (Age 55+)
                        </h3>
                        <div class="export-options no-print">
                            <button class="export-btn pdf" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf me-1"></i> PDF
                            </button>
                            <button class="export-btn excel" onclick="exportToExcel()">
                                <i class="fas fa-file-excel me-1"></i> Excel
                            </button>
                            <button class="export-btn print" onclick="printReport()">
                                <i class="fas fa-print me-1"></i> Print
                            </button>
                        </div>
                    </div>
                    
                    <?php if (!empty($retirees)): ?>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Full Name</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>Age</th>
                                    <th>Birth Date</th>
                                    <th>Optional Ret. Year</th>
                                    <th>Compulsory Ret. Year</th>
                                    <th>Eligibility Status</th>
                                    <th>Contact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($retirees as $retiree): ?>
                                <tr>
                                    <td><?= $retiree['employee_id'] ?></td>
                                    <td><strong><?= htmlspecialchars($retiree['full_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($retiree['department_name'] ?? $retiree['department_id']) ?></td>
                                    <td><?= htmlspecialchars($retiree['position']) ?></td>
                                    <td>
                                        <span class="badge <?= $retiree['current_age'] >= 60 ? 'badge-success' : 'badge-warning' ?>">
                                            <?= $retiree['current_age'] ?> yrs
                                        </span>
                                    </td>
                                    <td><?= $retiree['birth_date'] ?></td>
                                    <td><?= $retiree['optional_retirement_year'] ?></td>
                                    <td><?= $retiree['compulsory_retirement_year'] ?></td>
                                    <td>
                                        <?php if($retiree['current_age'] >= 60): ?>
                                            <span class="badge badge-success">Eligible Now</span>
                                        <?php elseif($retiree['current_age'] >= 55): ?>
                                            <span class="badge badge-warning">Approaching</span>
                                        <?php else: ?>
                                            <span class="badge badge-info">Not Yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($retiree['email']): ?>
                                            <div style="font-size: 12px;"><?= htmlspecialchars($retiree['email']) ?></div>
                                        <?php endif; ?>
                                        <?php if($retiree['contact_number']): ?>
                                            <div style="font-size: 12px; color: #6b7280;"><?= htmlspecialchars($retiree['contact_number']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="margin-top: 20px; padding: 15px; background: #f0f9ff; border-radius: 8px; border-left: 4px solid #0ea5e9;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-info-circle" style="color: #0ea5e9;"></i>
                            <div>
                                <strong>Total Records:</strong> <?= count($retirees) ?> employees
                                <span style="margin: 0 10px;">•</span>
                                <strong>Eligible (60+):</strong> <?= $eligible_count ?>
                                <span style="margin: 0 10px;">•</span>
                                <strong>Approaching (55-59):</strong> <?= $approaching_count ?>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="text-align: center; padding: 40px; background: #f9fafb; border-radius: 8px;">
                        <i class="fas fa-users fa-3x" style="color: #d1d5db; margin-bottom: 20px;"></i>
                        <h4 style="color: #6b7280; margin-bottom: 10px;">No Retirees Found</h4>
                        <p style="color: #9ca3af;">No employees aged 55 or above in the selected criteria.</p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($report_type == 'benefits'): ?>
                <!-- BENEFITS UTILIZATION REPORT -->
                <div class="report-section">
                    <h3><i class="fas fa-hand-holding-usd me-2"></i> Benefits Utilization Report (<?= $filter_year ?>)</h3>
                    
                    <?php if (!empty($benefits_data)): ?>
                    <div style="margin-bottom: 30px;">
                        <canvas id="benefitsChart" height="120"></canvas>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Year</th>
                                    <th>Month</th>
                                    <th>Retirement Type</th>
                                    <th>Applications</th>
                                    <th>Approved</th>
                                    <th>Pending</th>
                                    <th>Rejected</th>
                                    <th>Approval Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $month_names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                                $total_apps = 0;
                                $total_approved = 0;
                                $total_pending = 0;
                                $total_rejected = 0;
                                ?>
                                
                                <?php foreach($benefits_data as $benefit): 
                                    $total_apps += $benefit['application_count'];
                                    $total_approved += $benefit['approved_count'];
                                    $total_pending += $benefit['pending_count'];
                                    $total_rejected += $benefit['rejected_count'];
                                    
                                    $approval_rate = $benefit['application_count'] > 0 ? 
                                        round(($benefit['approved_count'] / $benefit['application_count']) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><?= $benefit['application_year'] ?></td>
                                    <td><?= $month_names[$benefit['application_month'] - 1] ?? $benefit['application_month'] ?></td>
                                    <td>
                                        <span class="badge <?= $benefit['retirement_type'] == 'optional' ? 'badge-info' : 'badge-warning' ?>">
                                            <?= ucfirst($benefit['retirement_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= $benefit['application_count'] ?></td>
                                    <td>
                                        <span class="badge badge-success">
                                            <?= $benefit['approved_count'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-warning">
                                            <?= $benefit['pending_count'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-danger">
                                            <?= $benefit['rejected_count'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="width: 60px; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                                <div style="width: <?= $approval_rate ?>%; height: 100%; background: #10b981;"></div>
                                            </div>
                                            <span><?= $approval_rate ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <!-- Totals Row -->
                                <tr style="background: #f9fafb; font-weight: bold;">
                                    <td colspan="3">TOTAL</td>
                                    <td><?= $total_apps ?></td>
                                    <td><?= $total_approved ?></td>
                                    <td><?= $total_pending ?></td>
                                    <td><?= $total_rejected ?></td>
                                    <td>
                                        <?php 
                                        $overall_rate = $total_apps > 0 ? round(($total_approved / $total_apps) * 100, 1) : 0;
                                        echo $overall_rate . '%';
                                        ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div style="text-align: center; padding: 40px; background: #f9fafb; border-radius: 8px;">
                        <i class="fas fa-chart-bar fa-3x" style="color: #d1d5db; margin-bottom: 20px;"></i>
                        <h4 style="color: #6b7280; margin-bottom: 10px;">No Benefits Data Available</h4>
                        <p style="color: #9ca3af;">
                            Retirement applications data not available for <?= $filter_year ?>.
                            <?php if (!$table_exists): ?>
                            <br><small>The retirement_applications table may not exist yet.</small>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
        
        <div id="rightbar-placeholder"></div>
    </div>

    <!-- PRINT DOCUMENT (HIDDEN ON SCREEN, SHOWN ONLY WHEN PRINTING) -->
    <div class="print-document">
        <div class="document-header">
            <h1>RETIREMENT ANALYTICS REPORT</h1>
            <div class="subtitle">Comprehensive Analysis of Retirement Trends & Benefits Utilization</div>
            <div class="subtitle" style="font-size: 10pt;">Generated on: <?= date('F d, Y \a\t h:i A') ?></div>
            
            <div class="metadata">
                <div>
                    <strong>Report Period</strong>
                    <?= $filter_year ?>
                </div>
                <div>
                    <strong>Department</strong>
                    <?php 
                    $dept_name = 'All Departments';
                    if ($filter_department != 'all') {
                        foreach($departments as $dept) {
                            if ($dept['department_id'] == $filter_department) {
                                $dept_name = htmlspecialchars($dept['department_name']);
                                break;
                            }
                        }
                    }
                    echo $dept_name;
                    ?>
                </div>
                <div>
                    <strong>Total Employees</strong>
                    <?= number_format($total_employees) ?>
                </div>
                <div>
                    <strong>Report Type</strong>
                    <?= ucfirst($report_type) ?>
                </div>
            </div>
        </div>
        
        <?php if ($report_type == 'summary'): ?>
        <!-- SUMMARY REPORT PRINT LAYOUT -->
        <div class="document-section">
            <h2>EXECUTIVE SUMMARY</h2>
            
            <div class="print-stats-grid">
                <div class="print-stat-box">
                    <span class="print-stat-label">Total Analyzed</span>
                    <span class="print-stat-value"><?= number_format($total_employees) ?></span>
                    <div class="muted">Age 55+</div>
                </div>
                <div class="print-stat-box">
                    <span class="print-stat-label">Eligible (60+)</span>
                    <span class="print-stat-value"><?= number_format($eligible_count) ?></span>
                    <div class="muted">Optional Retirement</div>
                </div>
                <div class="print-stat-box">
                    <span class="print-stat-label">Approaching (55-59)</span>
                    <span class="print-stat-value"><?= number_format($approaching_count) ?></span>
                    <div class="muted">Within 5 Years</div>
                </div>
                <div class="print-stat-box">
                    <span class="print-stat-label">Average Age</span>
                    <span class="print-stat-value"><?= $avg_age ?></span>
                    <div class="muted">Oldest: <?= $oldest_age ?></div>
                </div>
            </div>
        </div>
        
        <div class="document-section">
            <h2>AGE DISTRIBUTION</h2>
            
            <div class="print-chart-replacement">
                <h4>Employee Count by Age Group</h4>
                <div class="print-chart-data">
                    <?php foreach($age_data as $age): ?>
                    <div class="print-chart-row">
                        <span class="strong"><?= $age['age_group'] ?></span>
                        <span><?= $age['count'] ?> employees (<?= $age['percentage'] ?>%)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="document-section">
            <h2>DEPARTMENT ANALYSIS</h2>
            
            <table class="document-table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Total</th>
                        <th>Eligible</th>
                        <th>Approaching</th>
                        <th>Avg Age</th>
                        <th>Youngest</th>
                        <th>Oldest</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($dept_analysis as $dept): ?>
                    <tr>
                        <td class="strong"><?= htmlspecialchars($dept['department']) ?></td>
                        <td><?= $dept['total_employees'] ?></td>
                        <td>
                            <span class="print-badge"><?= $dept['eligible_for_retirement'] ?></span>
                        </td>
                        <td>
                            <span class="print-badge"><?= $dept['approaching_retirement'] ?></span>
                        </td>
                        <td><?= $dept['average_age'] ?> yrs</td>
                        <td><?= $dept['youngest_age'] ?> yrs</td>
                        <td><?= $dept['oldest_age'] ?> yrs</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="document-section">
            <h2>SERVICE YEAR ANALYSIS</h2>
            
            <table class="document-table">
                <thead>
                    <tr>
                        <th>Service Range</th>
                        <th>Employees</th>
                        <th>Avg Age</th>
                        <th>Sample Employees</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($service_data as $service): ?>
                    <tr>
                        <td class="strong"><?= htmlspecialchars($service['service_range']) ?></td>
                        <td><?= $service['employee_count'] ?></td>
                        <td><?= $service['avg_age'] ?> yrs</td>
                        <td class="muted" style="font-size: 8pt;">
                            <?= htmlspecialchars(implode(', ', array_slice(explode('; ', $service['sample_employees']), 0, 2))) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if ($report_type == 'growth'): ?>
        <!-- GROWTH PROJECTION PRINT LAYOUT -->
        <div class="document-section">
            <h2>RETIREMENT GROWTH PROJECTION</h2>
            <div class="subtitle" style="font-size: 10pt; margin-bottom: 15px;">
                Projection from <?= $filter_year ?> onwards
            </div>
            
            <div class="print-timeline">
                <?php foreach($growth_data as $growth): ?>
                <div class="print-timeline-item">
                    <div class="print-timeline-year"><?= $growth['retirement_year'] ?></div>
                    <div class="print-timeline-content">
                        <div class="print-timeline-count"><?= $growth['projected_retirees'] ?> Employees</div>
                        <div class="muted" style="font-size: 9pt;">
                            <?php 
                            $employees = explode(',', $growth['retirees_list']);
                            echo htmlspecialchars(implode(', ', array_slice($employees, 0, 3)));
                            if (count($employees) > 3) echo ' and ' . (count($employees) - 3) . ' more';
                            ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (!empty($growth_data)): ?>
            <div class="page-break"></div>
            <table class="document-table">
                <thead>
                    <tr>
                        <th>Retirement Year</th>
                        <th>Projected Retirees</th>
                        <th>Affected Employees</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($growth_data as $growth): ?>
                    <tr>
                        <td class="strong"><?= $growth['retirement_year'] ?></td>
                        <td><?= $growth['projected_retirees'] ?></td>
                        <td style="font-size: 9pt;" class="muted">
                            <?= htmlspecialchars(str_replace(',', ', ', $growth['retirees_list'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($report_type == 'retirees'): ?>
        <!-- RETIREES LIST PRINT LAYOUT -->
        <div class="document-section">
            <h2>RETIREES LIST (AGE 55+)</h2>
            <div class="subtitle" style="font-size: 10pt; margin-bottom: 15px;">
                Total: <?= count($retirees) ?> employees • Eligible: <?= $eligible_count ?> • Approaching: <?= $approaching_count ?>
            </div>
            
            <table class="document-table landscape-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Age</th>
                        <th>Birth Date</th>
                        <th>Optional Year</th>
                        <th>Compulsory Year</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($retirees as $retiree): ?>
                    <tr>
                        <td><?= $retiree['employee_id'] ?></td>
                        <td class="strong"><?= htmlspecialchars($retiree['full_name']) ?></td>
                        <td><?= htmlspecialchars($retiree['department_name'] ?? $retiree['department_id']) ?></td>
                        <td>
                            <span class="print-badge"><?= $retiree['current_age'] ?> yrs</span>
                        </td>
                        <td><?= $retiree['birth_date'] ?></td>
                        <td><?= $retiree['optional_retirement_year'] ?></td>
                        <td><?= $retiree['compulsory_retirement_year'] ?></td>
                        <td>
                            <?php if($retiree['current_age'] >= 60): ?>
                            <span class="print-badge">Eligible</span>
                            <?php elseif($retiree['current_age'] >= 55): ?>
                            <span class="print-badge">Approaching</span>
                            <?php else: ?>
                            <span class="print-badge">Not Yet</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if ($report_type == 'benefits'): ?>
        <!-- BENEFITS UTILIZATION PRINT LAYOUT -->
        <div class="document-section">
            <h2>BENEFITS UTILIZATION REPORT</h2>
            <div class="subtitle" style="font-size: 10pt; margin-bottom: 15px;">
                Year: <?= $filter_year ?> • Applications: <?= $total_apps ?? 0 ?>
            </div>
            
            <?php if (!empty($benefits_data)): ?>
            <table class="document-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Type</th>
                        <th>Applications</th>
                        <th>Approved</th>
                        <th>Pending</th>
                        <th>Rejected</th>
                        <th>Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $month_names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    $total_apps = 0;
                    $total_approved = 0;
                    $total_pending = 0;
                    $total_rejected = 0;
                    ?>
                    
                    <?php foreach($benefits_data as $benefit): 
                        $total_apps += $benefit['application_count'];
                        $total_approved += $benefit['approved_count'];
                        $total_pending += $benefit['pending_count'];
                        $total_rejected += $benefit['rejected_count'];
                        $approval_rate = $benefit['application_count'] > 0 ? 
                            round(($benefit['approved_count'] / $benefit['application_count']) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td class="strong"><?= $month_names[$benefit['application_month'] - 1] ?? $benefit['application_month'] ?></td>
                        <td><?= ucfirst($benefit['retirement_type']) ?></td>
                        <td><?= $benefit['application_count'] ?></td>
                        <td><?= $benefit['approved_count'] ?></td>
                        <td><?= $benefit['pending_count'] ?></td>
                        <td><?= $benefit['rejected_count'] ?></td>
                        <td>
                            <span class="print-badge"><?= $approval_rate ?>%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <tr style="background: #f5f5f5 !important;">
                        <td colspan="2" class="strong">TOTAL</td>
                        <td class="strong"><?= $total_apps ?></td>
                        <td class="strong"><?= $total_approved ?></td>
                        <td><?= $total_pending ?></td>
                        <td><?= $total_rejected ?></td>
                        <td class="strong">
                            <?php 
                            $overall_rate = $total_apps > 0 ? round(($total_approved / $total_apps) * 100, 1) : 0;
                            echo $overall_rate . '%';
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- DOCUMENT FOOTER -->
        <div class="print-footer">
            <table>
                <tr>
                    <td>
                        <strong>Report Generated:</strong><br>
                        <?= date('F d, Y h:i A') ?><br>
                        HRMS Analytics System
                    </td>
                    <td style="text-align: center;">
                        <strong>Classification:</strong><br>
                        Confidential - HR Department Only
                    </td>
                    <td style="text-align: right;">
                        <strong>Report ID:</strong><br>
                        RET-<?= date('Ymd-His') ?><br>
                        Page <span class="page-number">[Page]</span>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    <script src="/HRIS/assets/js/script.js"></script>
    <script>
    // ============================================
    // CHART CONFIGURATIONS
    // ============================================
    
    // Age Distribution Chart
    <?php if (!empty($age_data) && $report_type == 'summary'): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const ageCtx = document.getElementById('ageDistributionChart')?.getContext('2d');
        if (ageCtx) {
            const ageLabels = <?= json_encode(array_column($age_data, 'age_group')) ?>;
            const ageCounts = <?= json_encode(array_column($age_data, 'count')) ?>;
            const agePercentages = <?= json_encode(array_column($age_data, 'percentage')) ?>;
            
            new Chart(ageCtx, {
                type: 'bar',
                data: {
                    labels: ageLabels,
                    datasets: [{
                        label: 'Employee Count',
                        data: ageCounts,
                        backgroundColor: [
                            '#6366f1', '#8b5cf6', '#a855f7', '#d946ef', 
                            '#ec4899', '#f43f5e', '#ef4444', '#f97316',
                            '#f59e0b', '#84cc16'
                        ],
                        borderColor: '#4f46e5',
                        borderWidth: 1,
                        yAxisID: 'y'
                    }, {
                        label: 'Percentage %',
                        data: agePercentages,
                        type: 'line',
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Employee Count'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Percentage %'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                            min: 0,
                            max: 100
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label.includes('Percentage')) {
                                        return label + ': ' + context.parsed.y + '%';
                                    }
                                    return label + ': ' + context.parsed.y + ' employees';
                                }
                            }
                        },
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });
        }
        
        // Service Year Chart
        const serviceCtx = document.getElementById('serviceYearChart')?.getContext('2d');
        if (serviceCtx) {
            const serviceLabels = <?= json_encode(array_column($service_data, 'service_range')) ?>;
            const serviceCounts = <?= json_encode(array_column($service_data, 'employee_count')) ?>;
            
            new Chart(serviceCtx, {
                type: 'bar',
                data: {
                    labels: serviceLabels,
                    datasets: [{
                        label: 'Employees by Service Years',
                        data: serviceCounts,
                        backgroundColor: [
                            '#93c5fd', '#60a5fa', '#3b82f6', '#2563eb',
                            '#1d4ed8', '#1e40af', '#1e3a8a', '#172554'
                        ],
                        borderColor: '#1d4ed8',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Employees'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Service Years Range'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    });
    <?php endif; ?>
    
    // Growth Projection Chart
    <?php if (!empty($growth_data) && $report_type == 'growth'): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const growthCtx = document.getElementById('growthChart')?.getContext('2d');
        if (growthCtx) {
            const years = <?= json_encode(array_column($growth_data, 'retirement_year')) ?>;
            const counts = <?= json_encode(array_column($growth_data, 'projected_retirees')) ?>;
            
            new Chart(growthCtx, {
                type: 'line',
                data: {
                    labels: years,
                    datasets: [{
                        label: 'Projected Retirees',
                        data: counts,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#ef4444',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Employees'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Retirement Year'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y + ' employees';
                                }
                            }
                        }
                    }
                }
            });
        }
    });
    <?php endif; ?>
    
    // Benefits Utilization Chart
    <?php if (!empty($benefits_data) && $report_type == 'benefits'): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const benefitsCtx = document.getElementById('benefitsChart')?.getContext('2d');
        if (benefitsCtx) {
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const monthlyData = {};
            
            // Organize data by month
            <?php foreach($benefits_data as $benefit): ?>
            const monthKey = <?= $benefit['application_month'] ?> - 1;
            if (!monthlyData[monthKey]) monthlyData[monthKey] = {optional: 0, compulsory: 0};
            monthlyData[monthKey][<?= json_encode($benefit['retirement_type']) ?>] += <?= $benefit['application_count'] ?>;
            <?php endforeach; ?>
            
            // Prepare dataset
            const optionalData = [];
            const compulsoryData = [];
            for (let i = 0; i < 12; i++) {
                optionalData.push(monthlyData[i]?.optional || 0);
                compulsoryData.push(monthlyData[i]?.compulsory || 0);
            }
            
            new Chart(benefitsCtx, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'Optional Retirement',
                            data: optionalData,
                            backgroundColor: '#3b82f6',
                            borderColor: '#1d4ed8',
                            borderWidth: 1
                        },
                        {
                            label: 'Compulsory Retirement',
                            data: compulsoryData,
                            backgroundColor: '#f59e0b',
                            borderColor: '#d97706',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            stacked: false,
                        },
                        y: {
                            stacked: false,
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Applications'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });
        }
    });
    <?php endif; ?>
    
    // ============================================
    // REPORT FUNCTIONS
    // ============================================
    
    function resetFilters() {
        document.getElementById('year').value = '<?= $current_year ?>';
        document.getElementById('department').value = 'all';
        document.getElementById('report_type').value = 'summary';
        document.getElementById('reportFilterForm').submit();
    }
    
    function printReport() {
        window.print();
    }
    
    function exportToPDF() {
        showAlert('PDF export feature will be implemented soon.', 'info');
        // In production, use libraries like jsPDF or make server-side PDF generation
    }
    
    function exportToExcel() {
        showAlert('Excel export feature will be implemented soon.', 'info');
        // In production, use libraries like SheetJS or make server-side Excel generation
    }
    
    function showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            z-index: 10000;
            animation: slideIn 0.3s ease-out;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        
        if (type === 'success') {
            alertDiv.style.backgroundColor = '#10b981';
            alertDiv.innerHTML = `<i class="fas fa-check-circle me-2"></i> ${message}`;
        } else if (type === 'warning') {
            alertDiv.style.backgroundColor = '#f59e0b';
            alertDiv.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i> ${message}`;
        } else if (type === 'error') {
            alertDiv.style.backgroundColor = '#ef4444';
            alertDiv.innerHTML = `<i class="fas fa-times-circle me-2"></i> ${message}`;
        } else {
            alertDiv.style.backgroundColor = '#3b82f6';
            alertDiv.innerHTML = `<i class="fas fa-info-circle me-2"></i> ${message}`;
        }
        
        document.body.appendChild(alertDiv);
        
        // Remove after 5 seconds
        setTimeout(() => {
            alertDiv.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 300);
        }, 5000);
    }
    
    // Animation styles
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>
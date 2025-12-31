<?php
// gender_report.php - Gender Distribution Report
session_start();
require_once '../../config/config.php';
// Remove the session_helper.php if it's causing issues
// require_once '../../includes/session_helper.php';
// requireAdmin();

// Check if admin is logged in - FIXED VERSION
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.html");
    exit();
}

// Check if user is admin - handle both session variable names
if (isset($_SESSION['role_name'])) {
    $userRole = strtolower($_SESSION['role_name']);
} elseif (isset($_SESSION['role'])) {
    $userRole = strtolower($_SESSION['role']);
} else {
    header("Location: ../../login.html");
    exit();
}

// Only allow 'admin' role
if ($userRole !== 'admin') {
    header("Location: ../../login.html");
    exit();
}

// Initialize variables to prevent undefined errors
$genderStats = [];
$totalMale = 0;
$totalFemale = 0;
$totalEmployees = 0;
$malePercentage = 0;
$femalePercentage = 0;
$error = null;

// Fix: Use the correct table name from your database - it's 'department' not 'departments'
try {
    // Get all departments
    $deptStmt = $pdo->query("SELECT * FROM department ORDER BY department_name");
    $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if we got departments
    if (!$departments) {
        $error = "No departments found in the database.";
    } else {
        foreach ($departments as $dept) {
            // Count males in department - fix status field name (it's 'status' not 'employment_status')
            $maleStmt = $pdo->prepare("SELECT COUNT(*) as male_count FROM employee 
                                       WHERE department_id = ? AND gender = 'Male' AND status = 'Active'");
            $maleStmt->execute([$dept['department_id']]);
            $maleCount = $maleStmt->fetchColumn();
            
            // Count females in department
            $femaleStmt = $pdo->prepare("SELECT COUNT(*) as female_count FROM employee 
                                         WHERE department_id = ? AND gender = 'Female' AND status = 'Active'");
            $femaleStmt->execute([$dept['department_id']]);
            $femaleCount = $femaleStmt->fetchColumn();
            
            $totalDept = $maleCount + $femaleCount;
            
            $genderStats[] = [
                'department_id' => $dept['department_id'],
                'department_name' => $dept['department_name'],
                'male_count' => $maleCount,
                'female_count' => $femaleCount,
                'total' => $totalDept,
                'male_percentage' => $totalDept > 0 ? round(($maleCount / $totalDept) * 100, 1) : 0,
                'female_percentage' => $totalDept > 0 ? round(($femaleCount / $totalDept) * 100, 1) : 0
            ];
            
            $totalMale += $maleCount;
            $totalFemale += $femaleCount;
            $totalEmployees += $totalDept;
        }
        
        // Calculate overall percentages
        $totalEmployees = $totalMale + $totalFemale;
        $malePercentage = $totalEmployees > 0 ? round(($totalMale / $totalEmployees) * 100, 1) : 0;
        $femalePercentage = $totalEmployees > 0 ? round(($totalFemale / $totalEmployees) * 100, 1) : 0;
    }
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    // For debugging, you can uncomment the next line:
    // error_log("Gender Report Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gender Distribution Report - Admin</title>
    <link rel="stylesheet" href="/HRIS/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ... your existing styles ... */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.8rem;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            transition: background 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .nav-links a.active {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border: 1px solid #eaeaea;
        }
        
        .card h2 {
            color: #2d3748;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            border-top: 4px solid;
        }
        
        .summary-card.male {
            border-top-color: #3498db;
        }
        
        .summary-card.female {
            border-top-color: #e84393;
        }
        
        .summary-card.total {
            border-top-color: #2ecc71;
        }
        
        .summary-card h3 {
            font-size: 2.5rem;
            margin: 10px 0;
        }
        
        .chart-container {
            height: 400px;
            margin: 30px 0;
        }
        
        .gender-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .gender-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }
        
        .gender-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .gender-table tr:hover {
            background: #f8f9fa;
        }
        
        .progress-bar-container {
            width: 100px;
            height: 20px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            display: inline-block;
            margin: 0 10px;
        }
        
        .progress-bar {
            height: 100%;
            display: inline-block;
        }
        
        .progress-male {
            background: #3498db;
        }
        
        .progress-female {
            background: #e84393;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .btn-export {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .btn-pdf {
            background: #dc3545;
            color: white;
        }
        
        .btn-excel {
            background: #28a745;
            color: white;
        }
        
        .btn-print {
            background: #17a2b8;
            color: white;
        }
        
        .legend {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            justify-content: center;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
        
        .legend-male {
            background: #3498db;
        }
        
        .legend-female {
            background: #e84393;
        }
        
        .department-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .count-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .male-badge {
            background: #3498db;
            color: white;
        }
        
        .female-badge {
            background: #e84393;
            color: white;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        /* HIDE PRINT DOCUMENT ON SCREEN */
        .print-document {
            display: none;
        }
        
        /* ============================================
           PRINT-SPECIFIC STYLES - DOCUMENT STYLE
        ============================================ */
        
        @media print {
            /* Reset for print */
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
            .header,
            .nav-links,
            .container .card:first-child,
            .summary-cards,
            .export-buttons,
            .btn-export,
            .chart-container,
            canvas,
            .legend,
            .alert,
            .container .card:last-child,
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
                grid-template-columns: repeat(3, 1fr);
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
                grid-template-columns: repeat(3, 1fr);
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
            
            /* Utilities */
            .text-center {
                text-align: center;
            }
            
            .text-right {
                text-align: right;
            }
            
            .strong {
                font-weight: bold;
            }
            
            .muted {
                color: #666;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .export-buttons {
                flex-direction: column;
            }
            
            .chart-container {
                height: 300px;
            }
        }
    </style>
</head>

<body>
    <!-- SCREEN CONTENT -->
    <div class="header">
        <h1><i class="fas fa-chart-pie"></i> Gender Distribution Report</h1>
        <div class="nav-links">
            <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="auto_backup.php"><i class="fas fa-database"></i> Auto Backup</a>
            <a href="gender_report.php" class="active"><i class="fas fa-chart-pie"></i> Reports</a>
            <a href="../../login.html?logout=true"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <div class="card">
            <h1><i class="fas fa-chart-pie me-2"></i> Gender Distribution Report</h1>
            <p>Male and female employee count per department</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card male">
                <i class="fas fa-male fa-3x" style="color: #3498db; margin-bottom: 15px;"></i>
                <h3><?php echo $totalMale; ?></h3>
                <p>Total Male Employees</p>
                <div style="font-size: 1.2rem; font-weight: bold; color: #3498db;"><?php echo $malePercentage; ?>%</div>
            </div>
            
            <div class="summary-card female">
                <i class="fas fa-female fa-3x" style="color: #e84393; margin-bottom: 15px;"></i>
                <h3><?php echo $totalFemale; ?></h3>
                <p>Total Female Employees</p>
                <div style="font-size: 1.2rem; font-weight: bold; color: #e84393;"><?php echo $femalePercentage; ?>%</div>
            </div>
            
            <div class="summary-card total">
                <i class="fas fa-users fa-3x" style="color: #2ecc71; margin-bottom: 15px;"></i>
                <h3><?php echo $totalEmployees; ?></h3>
                <p>Total Active Employees</p>
                <div style="font-size: 1.2rem; font-weight: bold; color: #2ecc71;">100%</div>
            </div>
        </div>
        
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2><i class="fas fa-table me-2"></i>Gender Distribution by Department</h2>
                <div class="export-buttons">
                    <button class="btn-export btn-pdf" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                    <button class="btn-export btn-excel" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                    <button class="btn-export btn-print" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
            
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color legend-male"></div>
                    <span>Male Employees</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-female"></div>
                    <span>Female Employees</span>
                </div>
            </div>
            
            <div class="chart-container">
                <canvas id="genderChart"></canvas>
            </div>
            
            <?php if (empty($genderStats)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No employee data found or no departments with active employees.
                </div>
            <?php else: ?>
                <table class="gender-table">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Male</th>
                            <th>Female</th>
                            <th>Total</th>
                            <th>Male %</th>
                            <th>Female %</th>
                            <th>Gender Ratio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($genderStats as $stat): ?>
                            <tr>
                                <td class="department-name"><?php echo htmlspecialchars($stat['department_name']); ?></td>
                                <td>
                                    <span class="count-badge male-badge"><?php echo $stat['male_count']; ?></span>
                                </td>
                                <td>
                                    <span class="count-badge female-badge"><?php echo $stat['female_count']; ?></span>
                                </td>
                                <td><strong><?php echo $stat['total']; ?></strong></td>
                                <td>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar progress-male" style="width: <?php echo $stat['male_percentage']; ?>%"></div>
                                    </div>
                                    <?php echo $stat['male_percentage']; ?>%
                                </td>
                                <td>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar progress-female" style="width: <?php echo $stat['female_percentage']; ?>%"></div>
                                    </div>
                                    <?php echo $stat['female_percentage']; ?>%
                                </td>
                                <td>
                                    <?php 
                                        if ($stat['female_count'] > 0) {
                                            $ratio = $stat['male_count'] / $stat['female_count'];
                                            echo number_format($ratio, 2) . ':1';
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f8f9fa; font-weight: bold;">
                            <td>TOTAL</td>
                            <td><?php echo $totalMale; ?></td>
                            <td><?php echo $totalFemale; ?></td>
                            <td><?php echo $totalEmployees; ?></td>
                            <td><?php echo $malePercentage; ?>%</td>
                            <td><?php echo $femalePercentage; ?>%</td>
                            <td>
                                <?php 
                                    if ($totalFemale > 0) {
                                        $overallRatio = $totalMale / $totalFemale;
                                        echo number_format($overallRatio, 2) . ':1';
                                    } else {
                                        echo 'N/A';
                                    }
                                ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-chart-bar me-2"></i>Department Comparison</h2>
            <div class="chart-container">
                <canvas id="deptComparisonChart"></canvas>
            </div>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-info-circle me-2"></i>Report Information</h2>
            <ul style="list-style: none; padding: 0;">
                <li style="padding: 10px 0; border-bottom: 1px solid #eee;"><i class="fas fa-database me-2"></i> <strong>Data Source:</strong> Employee Table (Active Employees Only)</li>
                <li style="padding: 10px 0; border-bottom: 1px solid #eee;"><i class="fas fa-filter me-2"></i> <strong>Filter:</strong> Shows only employees with 'Active' status</li>
                <li style="padding: 10px 0; border-bottom: 1px solid #eee;"><i class="fas fa-calendar me-2"></i> <strong>Report Date:</strong> <?php echo date('F d, Y'); ?></li>
                <li style="padding: 10px 0;"><i class="fas fa-clock me-2"></i> <strong>Generated:</strong> <?php echo date('h:i A'); ?></li>
            </ul>
        </div>
    </div>

    <!-- PRINT DOCUMENT (HIDDEN ON SCREEN, SHOWN ONLY WHEN PRINTING) -->
    <div class="print-document">
        <div class="document-header">
            <h1>GENDER DISTRIBUTION REPORT</h1>
            <div class="subtitle">Male and Female Employee Analysis by Department</div>
            <div class="subtitle" style="font-size: 10pt;">Generated on: <?php echo date('F d, Y \a\t h:i A'); ?></div>
            
            <div class="metadata">
                <div>
                    <strong>Total Male</strong>
                    <?php echo $totalMale; ?> (<?php echo $malePercentage; ?>%)
                </div>
                <div>
                    <strong>Total Female</strong>
                    <?php echo $totalFemale; ?> (<?php echo $femalePercentage; ?>%)
                </div>
                <div>
                    <strong>Total Employees</strong>
                    <?php echo $totalEmployees; ?>
                </div>
            </div>
        </div>
        
        <div class="document-section">
            <h2>EXECUTIVE SUMMARY</h2>
            
            <div class="print-stats-grid">
                <div class="print-stat-box">
                    <span class="print-stat-label">Male Employees</span>
                    <span class="print-stat-value"><?php echo $totalMale; ?></span>
                    <div class="muted"><?php echo $malePercentage; ?>% of total</div>
                </div>
                <div class="print-stat-box">
                    <span class="print-stat-label">Female Employees</span>
                    <span class="print-stat-value"><?php echo $totalFemale; ?></span>
                    <div class="muted"><?php echo $femalePercentage; ?>% of total</div>
                </div>
                <div class="print-stat-box">
                    <span class="print-stat-label">Gender Ratio</span>
                    <span class="print-stat-value">
                        <?php 
                            if ($totalFemale > 0) {
                                $overallRatio = $totalMale / $totalFemale;
                                echo number_format($overallRatio, 1) . ':1';
                            } else {
                                echo 'N/A';
                            }
                        ?>
                    </span>
                    <div class="muted">Male:Female ratio</div>
                </div>
            </div>
        </div>
        
        <div class="document-section">
            <h2>DETAILED DEPARTMENT ANALYSIS</h2>
            
            <?php if (empty($genderStats)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-info-circle" style="font-size: 48px; margin-bottom: 20px;"></i>
                    <p>No employee data available for printing.</p>
                </div>
            <?php else: ?>
                <table class="document-table">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Male</th>
                            <th>Female</th>
                            <th>Total</th>
                            <th>Male %</th>
                            <th>Female %</th>
                            <th>Gender Ratio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($genderStats as $stat): ?>
                            <?php if ($stat['total'] > 0): ?>
                                <tr>
                                    <td class="strong"><?php echo htmlspecialchars($stat['department_name']); ?></td>
                                    <td>
                                        <span class="print-badge"><?php echo $stat['male_count']; ?></span>
                                    </td>
                                    <td>
                                        <span class="print-badge"><?php echo $stat['female_count']; ?></span>
                                    </td>
                                    <td><?php echo $stat['total']; ?></td>
                                    <td><?php echo $stat['male_percentage']; ?>%</td>
                                    <td><?php echo $stat['female_percentage']; ?>%</td>
                                    <td>
                                        <?php 
                                            if ($stat['female_count'] > 0) {
                                                $ratio = $stat['male_count'] / $stat['female_count'];
                                                echo number_format($ratio, 1) . ':1';
                                            } else {
                                                echo 'N/A';
                                            }
                                        ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f5f5f5 !important;">
                            <td class="strong">TOTAL</td>
                            <td class="strong"><?php echo $totalMale; ?></td>
                            <td class="strong"><?php echo $totalFemale; ?></td>
                            <td class="strong"><?php echo $totalEmployees; ?></td>
                            <td class="strong"><?php echo $malePercentage; ?>%</td>
                            <td class="strong"><?php echo $femalePercentage; ?>%</td>
                            <td class="strong">
                                <?php 
                                    if ($totalFemale > 0) {
                                        $overallRatio = $totalMale / $totalFemale;
                                        echo number_format($overallRatio, 1) . ':1';
                                    } else {
                                        echo 'N/A';
                                    }
                                ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($genderStats) && $totalEmployees > 0): ?>
        <div class="document-section">
            <h2>KEY FINDINGS</h2>
            <div style="margin: 15px 0;">
                <?php 
                // Find department with most males
                $maxMale = max(array_column($genderStats, 'male_count'));
                $maxMaleDepts = array_filter($genderStats, fn($stat) => $stat['male_count'] == $maxMale);
                
                // Find department with most females
                $maxFemale = max(array_column($genderStats, 'female_count'));
                $maxFemaleDepts = array_filter($genderStats, fn($stat) => $stat['female_count'] == $maxFemale);
                
                // Find most balanced department
                $mostBalanced = null;
                $minDifference = PHP_INT_MAX;
                
                foreach($genderStats as $stat) {
                    if ($stat['total'] > 0) {
                        $difference = abs($stat['male_percentage'] - $stat['female_percentage']);
                        if ($difference < $minDifference) {
                            $minDifference = $difference;
                            $mostBalanced = $stat;
                        }
                    }
                }
                ?>
                
                <?php if ($maxMale > 0): ?>
                <div style="margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-left: 4px solid #3498db;">
                    <strong>Largest Male Presence:</strong> 
                    <?php
                    foreach($maxMaleDepts as $dept) {
                        echo htmlspecialchars($dept['department_name']) . " ({$dept['male_count']} employees)";
                    }
                    ?>
                </div>
                <?php endif; ?>
                
                <?php if ($maxFemale > 0): ?>
                <div style="margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-left: 4px solid #e84393;">
                    <strong>Largest Female Presence:</strong> 
                    <?php
                    foreach($maxFemaleDepts as $dept) {
                        echo htmlspecialchars($dept['department_name']) . " ({$dept['female_count']} employees)";
                    }
                    ?>
                </div>
                <?php endif; ?>
                
                <?php if ($mostBalanced && $mostBalanced['total'] > 0): ?>
                <div style="padding: 10px; background: #f9f9f9; border-left: 4px solid #2ecc71;">
                    <strong>Most Balanced Department:</strong> 
                    <?php
                    echo htmlspecialchars($mostBalanced['department_name']) . 
                         " (M: {$mostBalanced['male_percentage']}%, F: {$mostBalanced['female_percentage']}%)";
                    ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- DOCUMENT FOOTER -->
        <div class="print-footer">
            <table>
                <tr>
                    <td>
                        <strong>Report Generated:</strong><br>
                        <?php echo date('F d, Y h:i A'); ?><br>
                        HRMS Gender Analysis Report
                    </td>
                    <td style="text-align: center;">
                        <strong>Classification:</strong><br>
                        Internal Use - HR Department Only
                    </td>
                    <td style="text-align: right;">
                        <strong>Report ID:</strong><br>
                        GENDER-<?php echo date('Ymd-His'); ?><br>
                        Page <span class="page-number">[Page]</span>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <script>
    // Initialize charts
    document.addEventListener('DOMContentLoaded', function() {
        // Data from PHP
        const departments = <?php echo !empty($genderStats) ? json_encode(array_column($genderStats, 'department_name')) : '[]'; ?>;
        const maleData = <?php echo !empty($genderStats) ? json_encode(array_column($genderStats, 'male_count')) : '[]'; ?>;
        const femaleData = <?php echo !empty($genderStats) ? json_encode(array_column($genderStats, 'female_count')) : '[]'; ?>;
        
        // Only create charts if we have data
        if (<?php echo $totalEmployees; ?> > 0) {
            // Pie Chart - Overall Gender Distribution
            const ctx1 = document.getElementById('genderChart');
            if (ctx1) {
                new Chart(ctx1.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: ['Male', 'Female'],
                        datasets: [{
                            data: [<?php echo $totalMale; ?>, <?php echo $totalFemale; ?>],
                            backgroundColor: ['#3498db', '#e84393'],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        let value = context.raw;
                                        let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        let percentage = Math.round((value / total) * 100);
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Bar Chart - Department Comparison (only if we have departments with data)
            const ctx2 = document.getElementById('deptComparisonChart');
            if (ctx2 && departments.length > 0) {
                new Chart(ctx2.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: departments,
                        datasets: [
                            {
                                label: 'Male',
                                data: maleData,
                                backgroundColor: '#3498db',
                                borderWidth: 1
                            },
                            {
                                label: 'Female',
                                data: femaleData,
                                backgroundColor: '#e84393',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                stacked: true,
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Employees'
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
        } else {
            // Hide chart containers if no data
            document.querySelectorAll('.chart-container').forEach(container => {
                container.innerHTML = '<div style="text-align: center; padding: 60px; color: #666;"><i class="fas fa-chart-pie fa-3x"></i><p>No data available for charts</p></div>';
            });
        }
    });
    
    function exportToPDF() {
    // Show instructions for saving as PDF
    const message = 'To save as PDF:\n\n' +
                   '1. Click the PRINT button below\n' +
                   '2. In the print dialog, change "Destination" to "Save as PDF"\n' +
                   '3. Click "Save"\n\n' +
                   'OR use the keyboard shortcut:\n' +
                   '• Windows: Ctrl+P, then choose "Microsoft Print to PDF"\n' +
                   '• Mac: Cmd+P, then click "PDF" > "Save as PDF"';
    
    if (confirm(message + '\n\nDo you want to open the print dialog now?')) {
        window.print();
    }
}
    
    function exportToExcel() {
        // Create Excel data
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Gender Distribution Report\r\n";
        csvContent += "Generated: " + new Date().toLocaleDateString() + "\r\n\r\n";
        csvContent += "Department,Male Employees,Female Employees,Total,Male %,Female %,Gender Ratio\r\n";
        
        <?php if (!empty($genderStats)): ?>
            <?php foreach ($genderStats as $stat): ?>
                csvContent += "<?php echo addslashes($stat['department_name']); ?>,<?php echo $stat['male_count']; ?>,<?php echo $stat['female_count']; ?>,<?php echo $stat['total']; ?>,<?php echo $stat['male_percentage']; ?>%,<?php echo $stat['female_percentage']; ?>%,";
                csvContent += "<?php echo ($stat['female_count'] > 0) ? number_format($stat['male_count']/$stat['female_count'], 2) . ':1' : 'N/A'; ?>\r\n";
            <?php endforeach; ?>
        <?php endif; ?>
        
        csvContent += "TOTAL,<?php echo $totalMale; ?>,<?php echo $totalFemale; ?>,<?php echo $totalEmployees; ?>,<?php echo $malePercentage; ?>%,<?php echo $femalePercentage; ?>%,";
        csvContent += "<?php echo ($totalFemale > 0) ? number_format($totalMale/$totalFemale, 2) . ':1' : 'N/A'; ?>\r\n";
        
        // Create download link
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "gender_report_<?php echo date('Y-m-d'); ?>.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    </script>
</body>
</html>
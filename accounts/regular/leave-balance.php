<?php
session_start();
require_once '../../config/config.php'; 

// --- FETCH LOGGED-IN USER DATA ---
$displayName = "User"; 
$displayRole = "Staff"; 
$employee_id = $_SESSION['employee_id'] ?? null;

if ($employee_id) {
    try {
        $stmtUser = $pdo->prepare("SELECT first_name, last_name, role FROM employee WHERE employee_id = ?");
        $stmtUser->execute([$employee_id]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $displayName = $user['first_name'];
            $displayRole = $user['role'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching user data: " . $e->getMessage());
    }
}

// --- CSC LEAVE ACCRUAL LOGIC ---
$vl_balance = "0.000";
$sl_balance = "0.000";
$fl_remaining = 0;
$monthly_accrual = 1.250; // Standard CSC Constant

if ($employee_id) {
    try {
        // Fetch the LATEST active balance
        $stmtBal = $pdo->prepare("SELECT * FROM leave_balance WHERE employee_id = ? AND is_latest = 1 LIMIT 1");
        $stmtBal->execute([$employee_id]);
        $balanceData = $stmtBal->fetch(PDO::FETCH_ASSOC);

        if ($balanceData) {
            $vl_balance = number_format($balanceData['vacation_leave'], 3);
            $sl_balance = number_format($balanceData['sick_leave'], 3);
            
            // CSC Rule: Mandatory 5-day forced leave if VL >= 15
            if ($balanceData['vacation_leave'] >= 15) {
                // Deduct what has already been used this year (mock logic or from a 'leave_taken' table)
                $fl_remaining = 5 - (int)($balanceData['forced_leave_deducted_year'] ?? 0);
                $fl_remaining = max(0, $fl_remaining);
            }
        }

        // --- FETCH LEDGER (Shows the monthly 1.250 updates) ---
        $stmtLedger = $pdo->prepare("
            SELECT month_year as date, remarks as particulars, 
                   earned_vacation as vl_plus, 0 as vl_minus, 
                   earned_sick as sl_plus, 0 as sl_minus 
            FROM leave_balance 
            WHERE employee_id = ? 
            ORDER BY month_year DESC LIMIT 12
        ");
        $stmtLedger->execute([$employee_id]);
        $ledger = $stmtLedger->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Leave Balance Ledger</title>
    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">
    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* PRINT-SPECIFIC STYLES */
        @media print {
            /* Hide all non-essential elements */
            #sidebar-placeholder,
            #topbar-placeholder,
            #rightbar-placeholder,
            #main-content > .dashboard-wrapper > .welcome-header,
            .stats-grid,
            .main-dashboard-grid > .side-info-container,
            .main-dashboard-grid > .feed-container > .content-card > .card-header > button,
            .content-container,
            .btn-more,
            .no-print {
                display: none !important;
            }
            
            /* Reset page margins and background */
            @page {
                margin: 0.5in;
                size: letter;
            }
            
            body {
                margin: 0;
                padding: 0;
                background: white !important;
                font-size: 11pt;
                line-height: 1.4;
                color: #000 !important;
                font-family: "Arial", "Helvetica", sans-serif !important;
            }
            
            .wrapper {
                display: block !important;
            }
            
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            
            /* PRINT HEADER STYLES */
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 15px;
                border-bottom: 3px solid #000;
            }
            
            .print-header h1 {
                color: #000 !important;
                font-size: 20pt !important;
                margin: 0 0 10px 0 !important;
            }
            
            .print-header .employee-info {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
                margin-top: 20px;
                font-size: 10pt;
            }
            
            .print-header .employee-info div {
                text-align: center;
                padding: 8px;
                background: #f5f5f5 !important;
                border: 1px solid #ddd !important;
                border-radius: 4px;
            }
            
            /* LEDGER TABLE PRINT STYLES */
            .ledger-table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin: 20px 0 !important;
                font-size: 9pt !important;
            }
            
            .ledger-table th,
            .ledger-table td {
                border: 1px solid #000 !important;
                padding: 8px 6px !important;
                text-align: center !important;
                vertical-align: middle !important;
                color: #000 !important;
            }
            
            .ledger-table th {
                background: #f0f0f0 !important;
                font-weight: bold !important;
                border-bottom: 2px solid #000 !important;
            }
            
            .ledger-table .vl-header,
            .ledger-table .sl-header {
                background: #e8f4ff !important;
            }
            
            .text-green {
                color: #006400 !important;
                font-weight: bold !important;
            }
            
            .text-red {
                color: #8b0000 !important;
                font-weight: bold !important;
            }
            
            .font-weight-600 {
                font-weight: bold !important;
            }
            
            /* PRINT BALANCE SUMMARY */
            .print-balance-summary {
                display: grid !important;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
                margin: 25px 0;
                page-break-inside: avoid;
            }
            
            .print-balance-box {
                border: 2px solid #000;
                padding: 15px;
                text-align: center;
                border-radius: 8px;
            }
            
            .print-balance-value {
                font-size: 18pt;
                font-weight: bold;
                color: #000;
                margin: 10px 0;
                display: block;
            }
            
            .print-balance-label {
                font-size: 9pt;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            /* PRINT FOOTER */
            .print-footer {
                margin-top: 40px;
                padding-top: 15px;
                border-top: 1px solid #000;
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
            
            /* Ensure tables don't break across pages */
            table {
                page-break-inside: avoid;
            }
            
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            
            /* Page break utilities */
            .page-break {
                page-break-before: always;
            }
            
            .avoid-break {
                page-break-inside: avoid;
            }
        }
        
        /* SCREEN STYLES (unchanged) */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .stat-icon.blue { background: #3b82f6; }
        .stat-icon.orange { background: #f97316; }
        .stat-icon.red { background: #ef4444; }
        .stat-icon.green { background: #10b981; }
        
        .main-dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 1024px) {
            .main-dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* PRINT HEADER - HIDDEN ON SCREEN */
        .print-header {
            display: none;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div id="sidebar-placeholder"></div>
        <main class="main-content" id="main-content">
            <div id="topbar-placeholder"></div>
            <div class="dashboard-wrapper">
                
                <!-- PRINT HEADER (only visible when printing) -->
                <div class="print-header">
                    <h1>LEAVE LEDGER CARD</h1>
                    <div style="text-align: center; margin-bottom: 10px;">
                        <strong>Human Resource Management System - Bacolod City</strong><br>
                        <small>Generated on: <?php echo date('F d, Y \a\t h:i A'); ?></small>
                    </div>
                    
                    <div class="employee-info">
                        <div>
                            <strong>Employee Name</strong><br>
                            <?= htmlspecialchars($displayName) ?>
                        </div>
                        <div>
                            <strong>Employee ID</strong><br>
                            <?= $employee_id ?>
                        </div>
                        <div>
                            <strong>Position</strong><br>
                            <?= htmlspecialchars($displayRole) ?>
                        </div>
                    </div>
                </div>

                <!-- SCREEN CONTENT -->
                <div class="welcome-header no-print">
                    <div class="welcome-text">
                        <h1>Leave Balance Overview</h1>
                        <p>Detailed breakdown of your available credits for <strong><?= htmlspecialchars($displayName) ?></strong> (<?= htmlspecialchars($displayRole) ?>).</p>
                    </div>
                    <div class="date-time-widget">
                        <div class="time" id="real-time">--:--:-- --</div>
                        <div class="date" id="real-date">Loading...</div>
                    </div>
                </div>

                <div class="stats-grid no-print">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-umbrella-beach"></i></div>
                        <div class="stat-info">
                            <h3><?= $vl_balance ?></h3>
                            <p>Vacation Leave (VL)</p>
                            <small style="color: #16a34a;">+1.250 Earned/mo</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange"><i class="fa-solid fa-briefcase-medical"></i></div>
                        <div class="stat-info">
                            <h3><?= $sl_balance ?></h3>
                            <p>Sick Leave (SL)</p>
                            <small style="color: #16a34a;">+1.250 Earned/mo</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon <?= ($fl_remaining > 0) ? 'red' : 'green' ?>">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= number_format($fl_remaining, 1) ?></h3>
                            <p>Forced Leave Balance</p>
                            <small>Annual Requirement: 5.0</small>
                        </div>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    <div class="feed-container">
                        <div class="content-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h2>Leave Ledger Card</h2>
                                <button class="btn-more" onclick="printLedger()" style="font-size: 11px; padding: 5px 15px; background: #2563eb; color: white; border: none; border-radius: 5px; cursor: pointer;">
                                    <i class="fa-solid fa-print me-1"></i> Print Ledger
                                </button>
                            </div>

                            <!-- PRINT BALANCE SUMMARY (visible only when printing) -->
                            <div class="print-balance-summary no-print">
                                <div class="print-balance-box">
                                    <span class="print-balance-label">Vacation Leave Balance</span>
                                    <span class="print-balance-value"><?= $vl_balance ?></span>
                                    <small>+1.250 earned monthly</small>
                                </div>
                                <div class="print-balance-box">
                                    <span class="print-balance-label">Sick Leave Balance</span>
                                    <span class="print-balance-value"><?= $sl_balance ?></span>
                                    <small>+1.250 earned monthly</small>
                                </div>
                                <div class="print-balance-box">
                                    <span class="print-balance-label">Forced Leave Remaining</span>
                                    <span class="print-balance-value"><?= number_format($fl_remaining, 1) ?></span>
                                    <small>Annual requirement: 5.0 days</small>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="ledger-table avoid-break">
                                    <thead>
                                        <tr>
                                            <th rowspan="2">Date</th>
                                            <th rowspan="2">Particulars</th>
                                            <th colspan="2" class="text-center vl-header">Vacation Leave</th>
                                            <th colspan="2" class="text-center sl-header">Sick Leave</th>
                                        </tr>
                                        <tr>
                                            <th class="sub-th">Earned</th>
                                            <th class="sub-th">Used</th>
                                            <th class="sub-th">Earned</th>
                                            <th class="sub-th">Used</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(!empty($ledger)): ?>
                                            <?php foreach($ledger as $entry): ?>
                                            <tr>
                                                <td><?= date('M d, Y', strtotime($entry['date'])) ?></td>
                                                <td class="font-weight-600"><?= htmlspecialchars($entry['particulars'] ?? 'Monthly Accrual') ?></td>
                                                <td class="text-green"><?= number_format($entry['vl_plus'], 3) ?></td>
                                                <td class="text-red"><?= $entry['vl_minus'] > 0 ? '-'.number_format($entry['vl_minus'], 3) : '0.000' ?></td>
                                                <td class="text-green"><?= number_format($entry['sl_plus'], 3) ?></td>
                                                <td class="text-red"><?= $entry['sl_minus'] > 0 ? '-'.number_format($entry['sl_minus'], 3) : '0.000' ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="6" style="text-align:center;">No records found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr style="background: #f8f9fa;">
                                            <td colspan="2" style="text-align: right; font-weight: bold;">Current Balance:</td>
                                            <td colspan="2" style="text-align: center; font-weight: bold; color: #1e40af;"><?= $vl_balance ?></td>
                                            <td colspan="2" style="text-align: center; font-weight: bold; color: #1e40af;"><?= $sl_balance ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            
                            <!-- PRINT FOOTER (visible only when printing) -->
                            <div class="print-footer no-print">
                                <table>
                                    <tr>
                                        <td style="width: 33%;">
                                            <strong>Generated By:</strong><br>
                                            <?= htmlspecialchars($displayName) ?><br>
                                            <?= htmlspecialchars($displayRole) ?>
                                        </td>
                                        <td style="width: 33%; text-align: center;">
                                            <strong>Classification:</strong><br>
                                            Employee Leave Record<br>
                                            Confidential
                                        </td>
                                        <td style="width: 33%; text-align: right;">
                                            <strong>Report ID:</strong><br>
                                            LEDGER-<?= $employee_id ?>-<?= date('Ymd') ?><br>
                                            Page <span class="page-number">1 of 1</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="side-info-container no-print">
                        <div class="content-card bg-light shadow-sm">
                            <div class="card-header">
                                <h2>CSC Earning Logic</h2>
                            </div>
                            <div class="p-3">
                                <div class="info-item mb-3">
                                    <small class="text-secondary d-block">Monthly VL Earned</small>
                                    <strong>1.250 Points</strong>
                                </div>
                                <div class="info-item mb-3">
                                    <small class="text-secondary d-block">Monthly SL Earned</small>
                                    <strong>1.250 Points</strong>
                                </div>
                                <div class="info-item mb-3" style="background: #f0fdf4; padding: 10px; border-radius: 5px;">
                                    <small class="text-success d-block">Total Monthly Credit</small>
                                    <strong style="font-size: 1.2rem; color: #16a34a;">2.500 Points</strong>
                                </div>
                                <hr>
                                <div class="info-item">
                                    <small class="text-danger d-block font-weight-bold">Mandatory Leave Rule</small>
                                    <p class="small text-muted" style="line-height: 1.4;">
                                        Employees with 15+ days of VL must take <strong>5 days</strong> of Mandatory/Forced Leave annually.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="content-container" id="contents-placeholder"></div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>
    <script src="/HRIS/assets/js/script.js"></script>
    <script>
    function printLedger() {
        // Show print instructions
        const instructions = `📄 PRINT INSTRUCTIONS:\n\n1. This will open the print dialog\n2. To save as PDF:\n   • Chrome/Edge: Select "Save as PDF" as destination\n   • Firefox: Select "Microsoft Print to PDF"\n   • Safari: Click "PDF" > "Save as PDF"\n3. Adjust margins if needed (recommended: 0.5in)\n4. Click "Print" or "Save"\n\nProceed to print dialog?`;
        
        if (confirm(instructions)) {
            // Add a small delay to ensure print styles are applied
            setTimeout(() => {
                window.print();
            }, 100);
        }
    }
    
    // Optional: Auto-add page numbers for multi-page prints
    window.onbeforeprint = function() {
        const pageCount = Math.ceil(document.querySelector('.ledger-table').offsetHeight / 800); // Approx pages
        document.querySelectorAll('.page-number').forEach(el => {
            el.textContent = `1 of ${pageCount}`;
        });
    };
    </script>
</body>
</html>
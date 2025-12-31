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
    <title>Regular Employee Dashboard</title>
    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">
    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="wrapper">
        <div id="sidebar-placeholder"></div>
        <main class="main-content" id="main-content">
            <div id="topbar-placeholder"></div>
            <div class="dashboard-wrapper">
                <div class="welcome-header">
                    <div class="welcome-text">
                        <h1>Leave Balance Overview</h1>
                        <p>Detailed breakdown of your available credits for <strong><?= htmlspecialchars($displayName) ?></strong> (<?= htmlspecialchars($displayRole) ?>).</p>
                    </div>
                    <div class="date-time-widget">
                        <div class="time" id="real-time">--:--:-- --</div>
                        <div class="date" id="real-date">Loading...</div>
                    </div>
                </div>

                <div class="stats-grid">
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
                                <button class="btn-more" onclick="window.print()" style="font-size: 11px; padding: 5px 15px; background: #2563eb; color: white; border: none; border-radius: 5px; cursor: pointer;">
                                    <i class="fa-solid fa-print me-1"></i> Print Ledger
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="ledger-table">
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
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="side-info-container">
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
</body>
</html>
<?php
session_start();
require_once '../../config/config.php'; 

// Redirect if not logged in
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.html");
    exit();
}

$employee_id = $_SESSION['employee_id'];
$displayName = "User"; 
$displayRole = "Staff"; 

// --- DYNAMIC DATE TRACKING ---
$currentMonthName = date('F'); 
$currentYear = date('Y');

// Initialize Variables
$total_earned_lifetime = 0.000;
$total_used_lifetime = 0.000;
$current_balance = 0.000;
$history = [];

try {
    // 1. FETCH LOGGED-IN USER DATA
    $stmtUser = $pdo->prepare("SELECT first_name, last_name, role FROM employee WHERE employee_id = ?");
    $stmtUser->execute([$employee_id]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $displayName = $user['first_name'];
        $displayRole = $user['role'];
    }

    // 2. FETCH TOTAL EARNED
    $stmtStats = $pdo->prepare("
        SELECT SUM(earned_vacation + earned_sick) as total_earned
        FROM leave_balance 
        WHERE employee_id = ?
    ");
    $stmtStats->execute([$employee_id]);
    $statsData = $stmtStats->fetch(PDO::FETCH_ASSOC);
    $total_earned_lifetime = $statsData['total_earned'] ?? 0.000;

    // 3. FETCH TOTAL USED 
    // Logic: We subtract days ONLY if Approved or still in the process of being Cancelled.
    // Once status is 'Cancelled', it no longer counts as 'Used'.
    $stmtUsed = $pdo->prepare("
        SELECT SUM(working_days) as total_used 
        FROM leave_application 
        WHERE employee_id = ? 
        AND (status = 'Approved' OR status = 'Cancellation Pending')
    ");
    $stmtUsed->execute([$employee_id]);
    $usedData = $stmtUsed->fetch(PDO::FETCH_ASSOC);
    $total_used_lifetime = $usedData['total_used'] ?? 0.000;

    // 4. FETCH REAL-TIME TRACKING BALANCE
    $stmtBal = $pdo->prepare("
        SELECT (vacation_leave + sick_leave) as month_bal 
        FROM leave_balance 
        WHERE employee_id = ? 
        AND (DATE_FORMAT(month_year, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') OR is_latest = 1)
        ORDER BY month_year DESC 
        LIMIT 1
    ");
    $stmtBal->execute([$employee_id]);
    $balData = $stmtBal->fetch(PDO::FETCH_ASSOC);
    $current_balance = $balData['month_bal'] ?? 0.000;

    // 5. FETCH HISTORY RECORDS (Including Cancelled for visibility)
    $stmtHistory = $pdo->prepare("
        SELECT 
            la.reference_no, la.date_filing, la.start_date, la.end_date, la.working_days, la.status,
            lt.name as leave_type_name
        FROM leave_application la
        INNER JOIN leave_types lt ON la.leave_type_id = lt.leave_types_id
        WHERE la.employee_id = ? 
        AND la.status IN ('Approved', 'Cancellation Pending', 'Cancelled')
        ORDER BY la.date_filing DESC
    ");
    $stmtHistory->execute([$employee_id]);
    $history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Leave History - <?= htmlspecialchars($displayName) ?></title>
    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">
    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .status-badge {
            font-weight: bold; 
            font-size: 10px; 
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-approved { background: #dcfce7; color: #16a34a; }
        .status-pending-cancel { background: #fff7ed; color: #ea580c; }
        .status-cancelled { background: #f1f5f9; color: #64748b; text-decoration: line-through; }
        
        .used-count { font-weight: bold; }
        .used-cancelled { color: #94a3b8; text-decoration: line-through; }
        .used-active { color: #dc2626; }
    </style>
</head>

<body>
    <div class="wrapper">
        <div id="sidebar-placeholder"></div>

        <main class="main-content" id="main-content">
            <div id="topbar-placeholder"></div>

            <div class="dashboard-wrapper">
                <div class="welcome-header">
                    <div class="welcome-text">
                        <h1>Leave Credit History</h1>
                        <p>Real-time balance tracking for <strong><?= $currentMonthName ?> <?= $currentYear ?></strong></p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-star"></i></div>
                        <div class="stat-info">
                            <h3><?= number_format($total_earned_lifetime, 3) ?></h3>
                            <p>Total Credits Earned</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange"><i class="fa-solid fa-minus-circle"></i></div>
                        <div class="stat-info">
                            <h3>-<?= number_format($total_used_lifetime, 3) ?></h3>
                            <p>Total Credits Used</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-scale-balanced"></i></div>
                        <div class="stat-info">
                            <h3><?= number_format($current_balance, 3) ?></h3>
                            <p>Available Balance (<?= $currentMonthName ?>)</p>
                        </div>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    <div class="feed-container">
                        <div class="content-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h2>Transaction Ledger</h2>
                                <button class="btn-more" onclick="window.print()" style="font-size: 11px; padding: 5px 15px; background: #2563eb; color: white; border: none; border-radius: 5px; cursor: pointer;">
                                    <i class="fa-solid fa-file-export me-1"></i> Export PDF
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="ledger-table">
                                    <thead>
                                        <tr>
                                            <th>Reference No</th>
                                            <th>Inclusive Dates</th>
                                            <th class="text-center">Type</th>
                                            <th class="text-center">Credits</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    
                                    <tbody>
                                        <?php if(!empty($history)): ?>
                                            <?php foreach($history as $row): 
                                                $s = strtoupper($row['status']);
                                                $isVoid = ($s === 'CANCELLED');
                                                $isPending = ($s === 'CANCELLATION PENDING');
                                            ?>
                                            <tr>
                                                <td style="font-size: 11px; font-weight: 600; color: #1e293b;">
                                                    <?= htmlspecialchars($row['reference_no']) ?>
                                                </td>
                                                <td>
                                                    <small><?= date('M d', strtotime($row['start_date'])) ?> - <?= date('M d, Y', strtotime($row['end_date'])) ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <small class="badge" style="background: #f1f5f9; padding: 2px 8px; border-radius: 4px;"><?= htmlspecialchars($row['leave_type_name']) ?></small>
                                                </td>
                                                <td class="text-center used-count <?= $isVoid ? 'used-cancelled' : 'used-active' ?>">
                                                    <?= $isVoid ? '0.000' : '-' . number_format($row['working_days'], 3) ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if($isVoid): ?>
                                                        <span class="status-badge status-cancelled">CANCELLED</span>
                                                    <?php elseif($isPending): ?>
                                                        <span class="status-badge status-pending-cancel">PENDING CANCELLATION</span>
                                                    <?php else: ?>
                                                        <span class="status-badge status-approved">APPROVED</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="view_details.php?ref=<?= $row['reference_no'] ?>" style="color: #2563eb; font-size: 14px;">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="6" class="text-center" style="padding: 40px; color: #94a3b8;">No records found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="side-info-container">
                        <div class="content-card shadow-sm">
                            <div class="card-header">
                                <h2>Ledger Summary</h2>
                            </div>
                            <div class="p-3">
                                <div class="info-item mb-2">
                                    <small class="d-block" style="color: #0369a1; font-weight: bold; letter-spacing: 0.5px; margin-bottom: 4px;">RECALL POLICY</small>
                                    <p class="small text-muted" style="line-height: 1.5; border-bottom: 1px solid #eee; padding-bottom: 12px; margin-bottom: 12px;">
                                        Cancelled leaves do not deduct from your credits. If a leave is cancelled after approval, the days are automatically recalled to your balance.
                                    </p>
                                </div>
                                <div class="info-item">
                                    <p class="small text-muted" style="line-height: 1.5;">
                                        Balance is updated in real-time. Contact HR for credit disputes.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div> 
                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>
    
    <script src="/HRIS/assets/js/script.js"></script>
</body>
</html>
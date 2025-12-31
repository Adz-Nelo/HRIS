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

// --- 1. FETCH LOGGED-IN USER DATA ---
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

// --- 2. FETCH LEAVE STATISTICS (Including Cancelled) ---
$stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'cancelled' => 0];
try {
    $stmtStats = $pdo->prepare("
        SELECT status, COUNT(*) as total 
        FROM leave_application 
        WHERE employee_id = ? 
        GROUP BY status
    ");
    $stmtStats->execute([$employee_id]);
    while ($row = $stmtStats->fetch(PDO::FETCH_ASSOC)) {
        $s = $row['status'];
        if (in_array($s, ['Pending', 'HR Staff Reviewed', 'Officer Recommended'])) {
            $stats['pending'] += $row['total'];
        } elseif ($s === 'Approved') {
            $stats['approved'] = $row['total'];
        } elseif ($s === 'Rejected') {
            $stats['rejected'] = $row['total'];
        } elseif ($s === 'Cancelled') {
            $stats['cancelled'] = $row['total'];
        }
    }
} catch (PDOException $e) {
    error_log("Stats Error: " . $e->getMessage());
}

// --- 3. FETCH NON-APPROVED LEAVES FOR THE TABLE ---
try {
    $stmtLeaves = $pdo->prepare("
        SELECT * FROM leave_application 
        WHERE employee_id = ? 
        AND status != 'Approved'
        ORDER BY created_at DESC
    ");
    $stmtLeaves->execute([$employee_id]);
    $leaveApplications = $stmtLeaves->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Leave Fetch Error: " . $e->getMessage());
    $leaveApplications = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Leave Tracker - <?= htmlspecialchars($displayName) ?></title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">
    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Add specific color for cancelled stats */
        .stat-icon.gray { background: #f1f5f9; color: #64748b; }
        
        /* Ensure 4 columns on desktop for the new stat card */
        @media (min-width: 992px) {
            .stats-grid { grid-template-columns: repeat(4, 1fr); }
        }
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
                        <h1>Track Leave Applications</h1>
                        <p>Monitor the status of your active, rejected, and cancelled requests.</p>
                    </div>
                    <div class="date-time-widget">
                        <div class="time" id="real-time">--:--:-- --</div>
                        <div class="date" id="real-date">Loading...</div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-clock"></i></div>
                        <div class="stat-info">
                            <h3><?= $stats['pending'] ?></h3>
                            <p>Pending</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
                        <div class="stat-info">
                            <h3><?= $stats['approved'] ?></h3>
                            <p>Approved</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-circle-xmark"></i></div>
                        <div class="stat-info">
                            <h3><?= $stats['rejected'] ?></h3>
                            <p>Rejected</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon gray"><i class="fa-solid fa-ban"></i></div>
                        <div class="stat-info">
                            <h3><?= $stats['cancelled'] ?></h3>
                            <p>Cancelled</p>
                        </div>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    <div class="feed-container">
                        <div class="content-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h2>Application History (Non-Approved)</h2>
                            </div>

                            <div class="table-responsive">
                                <table class="ledger-table">
                                    <thead>
                                        <tr>
                                            <th>Reference No</th>
                                            <th>Date Filed</th>
                                            <th>Inclusive Dates</th>
                                            <th>Days</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($leaveApplications)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No active, rejected, or cancelled applications found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($leaveApplications as $leave): ?>
                                                <tr>
                                                    <td style="font-size: 11px; font-weight: 600; color: #1e293b;">
                                                        <?= htmlspecialchars($leave['reference_no']) ?>
                                                    </td>
                                                    <td><?= date('M d, Y', strtotime($leave['date_filing'])) ?></td>
                                                    <td>
                                                        <small><?= date('M d', strtotime($leave['start_date'])) ?> - <?= date('M d, Y', strtotime($leave['end_date'])) ?></small>
                                                    </td>
                                                    <td><?= number_format($leave['working_days'], 1) ?></td>
                                                    <td class="text-center">
                                                        <?php 
                                                            $status = $leave['status'];
                                                            $style = "background: #fef3c7; color: #d97706;"; // Default Pending
                                                            
                                                            if ($status == 'Rejected') {
                                                                $style = "background: #fee2e2; color: #dc2626;";
                                                            } elseif ($status == 'Cancelled') {
                                                                $style = "background: #f1f5f9; color: #64748b;";
                                                            } elseif (in_array($status, ['HR Staff Reviewed', 'Officer Recommended'])) {
                                                                $style = "background: #e0f2fe; color: #0369a1;"; 
                                                            }
                                                        ?>
                                                        <span class="status-badge" style="<?= $style ?> padding: 4px 10px; border-radius: 12px; font-size: 10px; font-weight: 700; text-transform: uppercase;">
                                                            <?= htmlspecialchars($status) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="pending_view_details.php?ref=<?= urlencode($leave['reference_no']) ?>" class="btn-view" style="color: #64748b;">
                                                            <i class="fa-solid fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="side-info-container">
                        <div class="content-card bg-light shadow-sm">
                            <div class="card-header">
                                <h2>Status Guide</h2>
                            </div>
                            <div class="p-3">
                                <div class="info-item mb-3">
                                    <small class="d-block" style="color: #d97706; font-weight: bold;">PENDING</small>
                                    <p class="small text-muted">Awaiting initial HR review.</p>
                                </div>
                                <div class="info-item mb-3">
                                    <small class="d-block" style="color: #0369a1; font-weight: bold;">PROCESSING</small>
                                    <p class="small text-muted">Awaiting final signature from Official.</p>
                                </div>
                                <div class="info-item mb-3">
                                    <small class="d-block" style="color: #64748b; font-weight: bold;">CANCELLED</small>
                                    <p class="small text-muted">Application withdrawn by you or HR.</p>
                                </div>
                                <div class="info-item mb-3">
                                    <small class="d-block" style="color: #dc2626; font-weight: bold;">REJECTED</small>
                                    <p class="small text-muted">Disapproved or insufficient credits.</p>
                                </div>
                                <hr>
                                <p class="small text-muted">
                                    Approved requests are removed from this list and added to your <b>Leave Ledger</b>.
                                </p>
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
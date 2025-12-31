<?php
session_start();
require_once '../../config/config.php';

// ✅ FIX 1: Access Control (Updated to role_name + HR Staff)
$allowed_roles = ['Admin', 'HR Officer', 'HR Staff'];
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'], $allowed_roles)) {
    header("Location: ../../login.html");
    exit();
}

// ✅ FIX 2: Heartbeat Update
try {
    $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?")
        ->execute([$_SESSION['employee_id']]);
} catch (PDOException $e) { 
    /* silent fail */ 
}

$default_profile_image = '../../assets/images/default_user.png';
$message = "";
$messageType = "";

// --- CLAIM APPROVAL LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_claim'])) {
    $app_id = $_POST['application_id'];
    $emp_id = $_POST['employee_id'];
    $days_to_deduct = floatval($_POST['working_days']);

    try {
        $pdo->beginTransaction();
        
        // Check current balance (using row locking 'FOR UPDATE' for data integrity)
        $checkStmt = $pdo->prepare("SELECT vacation_leave FROM leave_balance WHERE employee_id = ? AND is_latest = 1 FOR UPDATE");
        $checkStmt->execute([$emp_id]);
        $current_bal = $checkStmt->fetchColumn();

        if ($current_bal !== false && $current_bal >= $days_to_deduct) {
            // Deduct from balance
            $updateBal = $pdo->prepare("UPDATE leave_balance SET vacation_leave = vacation_leave - ? WHERE employee_id = ? AND is_latest = 1");
            $updateBal->execute([$days_to_deduct, $emp_id]);

            // Update application status
            $updateApp = $pdo->prepare("UPDATE leave_application SET status = 'Approved', authorized_official_id = ? WHERE application_id = ?");
            $updateApp->execute([$_SESSION['employee_id'], $app_id]);

            $pdo->commit();
            $message = "Claim validated successfully.";
            $messageType = "success";
        } else {
            $pdo->rollBack();
            $message = "Insufficient balance (" . ($current_bal ?: 0) . " days available).";
            $messageType = "error";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Approval Error: " . $e->getMessage());
        $message = "Error: Processing failed.";
        $messageType = "error";
    }
}

// --- FILTERS & PAGINATION ---
$dept_filter = $_GET['dept'] ?? '';
$search_query = $_GET['search'] ?? '';
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

try {
    // Stats Fetching (Standardized for specific leave detail IDs)
    $total_pending = $pdo->query("SELECT COUNT(*) FROM leave_application WHERE status = 'Pending' AND leave_detail_id IN (8, 9)")->fetchColumn();
    $total_approved_today = $pdo->query("SELECT COUNT(*) FROM leave_application WHERE status = 'Approved' AND DATE(date_filing) = CURDATE() AND leave_detail_id IN (8, 9)")->fetchColumn();

    $departments = $pdo->query("SELECT * FROM department ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Main Query Construction
    $sql = "SELECT la.*, e.first_name, e.last_name, e.profile_pic, d.department_name, ld.name as claim_name, lb.vacation_leave
            FROM leave_application la
            JOIN employee e ON la.employee_id = e.employee_id
            LEFT JOIN department d ON e.department_id = d.department_id
            JOIN leave_details ld ON la.leave_detail_id = ld.leave_details_id
            JOIN leave_balance lb ON e.employee_id = lb.employee_id AND lb.is_latest = 1
            WHERE la.status = 'Pending' AND la.leave_detail_id IN (8, 9)";
    
    $params = [];
    if (!empty($dept_filter)) { 
        $sql .= " AND e.department_id = ?"; 
        $params[] = $dept_filter; 
    }
    if (!empty($search_query)) {
        $sql .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR la.reference_no LIKE ?)";
        $search_val = "%$search_query%";
        $params = array_merge($params, [$search_val, $search_val, $search_val]);
    }

    $sql .= " ORDER BY la.date_filing ASC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_pages = ceil($total_pending / $limit);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    die("A technical error occurred. Please try again later.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Validation - HRMS</title>
    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .dashboard-wrapper { gap: 15px !important; }
        .db-filter-bar {
            padding: 12px 20px;
            background: #fbfcfd;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .db-filter-bar input, .db-filter-bar select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            font-size: 13px;
        }
        .emp-info-cell { display: flex; align-items: center; gap: 10px; }
        .emp-profile-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 10px; font-weight: 700; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .ref-no { font-family: monospace; color: #1d4ed8; font-weight: 700; }
        
        .alert { padding: 12px 20px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
    </style>
</head>

<body>
    <div class="wrapper">
        <div id="sidebar-placeholder"></div>

        <main class="main-content">
            <div id="topbar-placeholder"></div>

            <div class="dashboard-wrapper">
                <div class="welcome-header">
                    <div class="welcome-text">
                        <h1>Claim Validation</h1>
                        <p>Process Monetization and Terminal Leave requests.</p>
                    </div>
                    <div class="date-time-widget">
                        <div class="time" id="real-time">--:--:-- --</div>
                        <div class="date" id="real-date">Loading...</div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                        <div class="stat-info">
                            <h3><?= $total_pending ?></h3>
                            <p>Pending Claims</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-calendar-check"></i></div>
                        <div class="stat-info">
                            <h3><?= $total_approved_today ?></h3>
                            <p>Processed Today</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <div class="stat-info">
                            <h3>High</h3>
                            <p>Priority Queue</p>
                        </div>
                    </div>
                </div>

                <?php if($message): ?>
                    <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
                <?php endif; ?>

                <div class="main-dashboard-grid">
                    <div class="content-card table-card">
                        <div class="report-log-header">
                            <h2>Pending Monetization / Terminal Claims</h2>
                        </div>

                        <form method="GET" class="db-filter-bar">
                            <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search name or Ref #" style="flex: 2;">
                            <select name="dept" style="flex: 1;">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['department_id'] ?>" <?= $dept_filter == $d['department_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-primary" style="padding: 8px 16px;">
                                <i class="fa-solid fa-filter"></i> Filter
                            </button>
                        </form>

                        <div class="table-container">
                            <table class="ledger-table">
                                <thead>
                                    <tr>
                                        <th>Ref No.</th>
                                        <th>Employee</th>
                                        <th>Claim Type</th>
                                        <th>Days</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($claims)): ?>
                                        <?php foreach ($claims as $claim): 
                                            $canApprove = ($claim['vacation_leave'] >= $claim['working_days']);
                                        ?>
                                        <tr>
                                            <td><span class="ref-no"><?= htmlspecialchars($claim['reference_no']) ?></span></td>
                                            <td>
                                                <div class="emp-info-cell">
                                                    <img src="<?= (!empty($claim['profile_pic'])) ? $claim['profile_pic'] : $default_profile_image ?>" class="emp-profile-img">
                                                    <div>
                                                        <div style="font-weight: 600;"><?= htmlspecialchars($claim['last_name'] . ', ' . $claim['first_name']) ?></div>
                                                        <small style="color:#64748b;"><?= htmlspecialchars($claim['department_name']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span style="font-weight: 700; color: #475569; font-size: 11px;"><?= strtoupper(htmlspecialchars($claim['claim_name'])) ?></span></td>
                                            <td>
                                                <div style="font-weight: 700;"><?= number_format($claim['working_days'], 3) ?></div>
                                                <small style="color:<?= $canApprove ? '#16a34a' : '#dc2626' ?>; font-size: 10px; font-weight:600;">
                                                    Bal: <?= number_format($claim['vacation_leave'], 3) ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <span class="status-badge status-pending">PENDING</span>
                                            </td>
                                            <td class="text-center">
                                                <div style="display: flex; gap: 5px; justify-content: center;">
                                                    <?php if($canApprove): ?>
                                                        <form method="POST" onsubmit="return confirm('Approve claim and deduct credits?');" style="display:inline;">
                                                            <input type="hidden" name="application_id" value="<?= $claim['application_id'] ?>">
                                                            <input type="hidden" name="employee_id" value="<?= $claim['employee_id'] ?>">
                                                            <input type="hidden" name="working_days" value="<?= $claim['working_days'] ?>">
                                                            <button type="submit" name="approve_claim" class="icon-btn" title="Approve" style="color: #16a34a; background: #dcfce7; border:none; width: 30px; height: 30px; border-radius: 4px; cursor: pointer;">
                                                                <i class="fa-solid fa-check"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button class="icon-btn" title="Insufficient Balance" disabled style="color: #94a3b8; background: #f1f5f9; border:none; width: 30px; height: 30px; border-radius: 4px;">
                                                            <i class="fa-solid fa-ban"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <a href="view-claim.php?id=<?= $claim['application_id'] ?>" class="icon-btn" title="View Details" style="color: #1d4ed8; background: #dbeafe; padding: 6px 8px; border-radius: 4px;">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" style="text-align:center; padding: 50px; color: #94a3b8;">No pending claims found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="content-card side-info-card">
                        <div class="card-header" style="margin-bottom: 15px;">
                            <h2>Validation Policy</h2>
                        </div>
                        <p style="font-size: 12px; color: #64748b; margin-bottom: 15px;">
                            Monetization requires a minimum balance of 15 days to remain after deduction.
                        </p>
                        
                        <div style="background: #f1f5f9; height: 8px; border-radius: 10px; margin-bottom: 5px;">
                            <div style="background: #16a34a; width: 100%; height: 100%; border-radius: 10px;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 11px; font-weight: 600;">
                            <span>System Integrity</span>
                            <span>Auto-Check Active</span>
                        </div>

                        <hr style="margin: 20px 0; border: none; border-top: 1px solid #f1f5f9;">

                        <div class="info-item">
                            <strong style="font-size: 12px; display: block; margin-bottom: 8px;">Auditor Reminder</strong>
                            <p style="font-size: 12px; color: #64748b; line-height: 1.5;">
                                Approving a claim will automatically deduct the specified days from the employee's Vacation Leave balance. This action is recorded for the ledger.
                            </p>
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
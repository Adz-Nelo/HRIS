<?php
session_start();
require_once '../../config/config.php';

// --- ACCESS CONTROL ---
$allowed_roles = ['Admin', 'HR Officer', 'HR Staff'];
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role_name']) || !in_array($_SESSION['role_name'], $allowed_roles)) {
    header("Location: ../../login.html?error=unauthorized");
    exit();
}

// --- HEARTBEAT UPDATE ---
try {
    $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?")
        ->execute([$_SESSION['employee_id']]);
} catch (PDOException $e) { 
    error_log("Heartbeat error: " . $e->getMessage());
}

$default_profile_image = '../../assets/images/default_user.png';
$message = "";
$messageType = "";

// --- VALIDATE CANCELLATION & RECALL BALANCE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recall_balance'])) {
    $app_id = $_POST['application_id'];
    $emp_id = $_POST['employee_id'];
    $days = floatval($_POST['working_days']);
    $type_id = intval($_POST['leave_type_id']);
    $hr_staff_id = $_SESSION['employee_id']; // The HR person performing the action

    $column = '';
    switch ($type_id) {
        case 1: case 2: $column = 'vacation_leave'; break;
        case 3: $column = 'sick_leave'; break;
        case 4: $column = 'maternity_leave'; break;
        case 5: $column = 'paternity_leave'; break;
        case 6: $column = 'special_leave'; break;
        case 7: $column = 'solo_parent_leave'; break;
        case 8: $column = 'study_leave'; break;
        case 12: $column = 'calamity_leave'; break;
    }

    try {
        $pdo->beginTransaction();
        
        $check = $pdo->prepare("SELECT rejection_reason, status FROM leave_application WHERE application_id = ? FOR UPDATE");
        $check->execute([$app_id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        $reason = $row['rejection_reason'] ?: '';

        if (strpos($reason, '[BALANCE_RECALLED]') !== false) {
            throw new Exception("Balance already restored.");
        }

        if (!empty($column)) {
            // 1. Restore to the latest balance record
            $updateBalance = $pdo->prepare("UPDATE leave_balance SET $column = $column + ? WHERE employee_id = ? AND is_latest = 1");
            $updateBalance->execute([$days, $emp_id]);

            // 2. Update Application: Set status to Cancelled, record validator, and add recall tag
            $updateApp = $pdo->prepare("UPDATE leave_application SET 
                status = 'Cancelled', 
                cancel_hr_validated_by = ?, 
                rejection_reason = CONCAT(IFNULL(rejection_reason,''), ' [BALANCE_RECALLED]') 
                WHERE application_id = ?");
            $updateApp->execute([$hr_staff_id, $app_id]);

            $pdo->commit();
            $message = "Success! Cancellation validated and $days days restored to " . str_replace('_', ' ', $column);
            $messageType = "success";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// --- DATA FETCHING ---
$dept_filter = $_GET['dept'] ?? '';
$search_query = $_GET['search'] ?? '';

try {
    $departments = $pdo->query("SELECT * FROM department ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // SQL modified to include 'Cancellation Pending' and join with the HR Validator name
    $sql = "SELECT la.*, e.first_name, e.last_name, e.profile_pic, d.department_name, lt.name as leave_type_name,
                   v.first_name as hr_fname, v.last_name as hr_lname
            FROM leave_application la
            JOIN employee e ON la.employee_id = e.employee_id 
            LEFT JOIN employee v ON la.cancel_hr_validated_by = v.employee_id
            LEFT JOIN department d ON e.department_id = d.department_id
            LEFT JOIN leave_types lt ON la.leave_type_id = lt.leave_types_id
            WHERE la.status IN ('Cancelled', 'Cancellation Pending')";
    
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

    // Sort: Pending requests first, then by date
    $sql .= " ORDER BY (la.status = 'Cancellation Pending') DESC, la.cancellation_requested_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cancelled_leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stats Logic
    $total_records = count($cancelled_leaves);
    $pending_recall = 0;
    $recalled_count = 0;
    foreach($cancelled_leaves as $l) {
        if(strpos($l['rejection_reason'] ?? '', '[BALANCE_RECALLED]') !== false) {
            $recalled_count++;
        } else {
            $pending_recall++;
        }
    }
} catch (PDOException $e) {
    error_log("Recall Page Error: " . $e->getMessage());
    die("A database error occurred.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cancelled Leaves - HRMS</title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">
    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        .dashboard-wrapper { display: flex; flex-direction: column; gap: 15px !important; padding: 20px; }
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .emp-info-cell { display: flex; align-items: center; gap: 12px; }
        .emp-profile-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #f1f5f9; }
        
        /* Status Colors */
        .status-badge.Cancelled { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
        .status-badge.Cancellation-Pending { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
        
        .btn-recall { background: #10b981; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 11px; cursor: pointer; font-weight: 600; transition: background 0.2s; }
        .btn-recall:hover { background: #059669; }
        .btn-recalled { color: #10b981; font-weight: 700; font-size: 10px; display: flex; flex-direction: column; gap: 2px; }
        
        .reason-box { font-size: 11px; color: #64748b; max-width: 180px; font-style: italic; margin-bottom: 4px; line-height: 1.2; }
        .proof-link { font-size: 11px; color: #2563eb; text-decoration: none; font-weight: 600; }
        
        .filter-container { display: flex; gap: 10px; align-items: center; }
        .filter-select, .search-input { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; color: #475569; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border: 1px solid transparent; }
        .alert-success { background: #dcfce7; color: #16a34a; border-color: #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border-color: #fecaca; }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; display: flex; align-items: center; gap: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .stat-icon { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .stat-icon.blue { background: #eff6ff; color: #3b82f6; }
        .stat-icon.green { background: #f0fdf4; color: #10b981; }
        .stat-icon.red { background: #fef2f2; color: #ef4444; }
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
                        <h1>Leave Cancellations</h1>
                        <p>Validate requests and restore credits to employee balances.</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-file-circle-xmark"></i></div>
                        <div class="stat-info">
                            <h3><?= number_format($total_records) ?></h3>
                            <p>Total Requests</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-rotate-left"></i></div>
                        <div class="stat-info">
                            <h3><?= number_format($recalled_count) ?></h3>
                            <p>Balances Restored</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-clock-rotate-left"></i></div>
                        <div class="stat-info">
                            <h3><?= $pending_recall ?></h3>
                            <p>Pending Validation</p>
                        </div>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    <div class="feed-container" style="grid-column: span 3;">
                        <?php if($message): ?>
                            <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
                        <?php endif; ?>

                        <div class="content-card">
                            <div class="card-header d-flex justify-content-between align-items-center" style="padding: 15px;">
                                <h2 style="font-size: 1.1rem; font-weight: 700;">Cancellation Management</h2>
                                
                                <form method="GET" class="filter-container">
                                    <select name="dept" class="filter-select" onchange="this.form.submit()">
                                        <option value="">All Departments</option>
                                        <?php foreach($departments as $dept): ?>
                                            <option value="<?= $dept['department_id'] ?>" <?= $dept_filter == $dept['department_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($dept['department_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="search-box">
                                        <input type="text" name="search" class="search-input" placeholder="Search Ref # or Name..." value="<?= htmlspecialchars($search_query) ?>">
                                    </div>
                                    <?php if(!empty($dept_filter) || !empty($search_query)): ?>
                                        <a href="cancelled-leave.php" style="color: #ef4444; font-size: 13px; text-decoration: none;"><i class="fa-solid fa-circle-xmark"></i></a>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="ledger-table">
                                    <thead>
                                        <tr>
                                            <th>Ref No.</th>
                                            <th>Employee</th>
                                            <th>Leave Type</th>
                                            <th>Reason & Proof</th>
                                            <th class="text-center">Status</th>
                                            <th>Action / Validated By</th>
                                            <th class="text-center">View</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($cancelled_leaves)): ?>
                                            <?php foreach ($cancelled_leaves as $leave): 
                                                $isRecalled = (strpos($leave['rejection_reason'] ?? '', '[BALANCE_RECALLED]') !== false);
                                                $statusClass = str_replace(' ', '-', $leave['status']);
                                            ?>
                                            <tr>
                                                <td><strong style="color: #2563eb;"><?= htmlspecialchars($leave['reference_no']) ?></strong></td>
                                                <td>
                                                    <div class="emp-info-cell">
                                                        <img src="<?= (!empty($leave['profile_pic']) && file_exists($leave['profile_pic'])) ? $leave['profile_pic'] : $default_profile_image ?>" class="emp-profile-img">
                                                        <div class="emp-details-text">
                                                            <span class="emp-name" style="display: block; font-weight: 600;"><?= htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']) ?></span>
                                                            <small style="color: #64748b;"><?= htmlspecialchars($leave['department_name']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="role-badge" style="background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 4px; font-size: 11px;"><?= htmlspecialchars($leave['leave_type_name']) ?></span><br>
                                                    <small><strong><?= $leave['working_days'] ?> Days</strong></small>
                                                </td>
                                                <td>
                                                    <div class="reason-box"><?= htmlspecialchars($leave['cancel_reason'] ?: 'N/A') ?></div>
                                                    <?php if($leave['cancel_proof_path']): ?>
                                                        <a href="<?= $leave['cancel_proof_path'] ?>" target="_blank" class="proof-link"><i class="fa-solid fa-paperclip"></i> View Proof</a>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="status-badge <?= $statusClass ?>"><?= strtoupper($leave['status']) ?></span>
                                                </td>
                                                <td>
                                                    <?php if(!$isRecalled): ?>
                                                        <form method="POST" onsubmit="return confirm('Validate cancellation and restore leave balance?');">
                                                            <input type="hidden" name="application_id" value="<?= $leave['application_id'] ?>">
                                                            <input type="hidden" name="employee_id" value="<?= $leave['employee_id'] ?>">
                                                            <input type="hidden" name="working_days" value="<?= $leave['working_days'] ?>">
                                                            <input type="hidden" name="leave_type_id" value="<?= $leave['leave_type_id'] ?>">
                                                            <button type="submit" name="recall_balance" class="btn-recall">
                                                                <i class="fa-solid fa-check-circle"></i> Validate & Recall
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <div class="btn-recalled">
                                                            <span><i class="fa-solid fa-circle-check"></i> RECALLED</span>
                                                            <small style="color:#64748b">By: <?= htmlspecialchars($leave['hr_fname'] . ' ' . $leave['hr_lname']) ?></small>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="review-leave.php?id=<?= $leave['application_id'] ?>" style="color: #64748b;">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="7" class="text-center" style="padding: 60px; color: #94a3b8;">No cancellation records found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
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
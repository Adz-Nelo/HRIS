<?php
session_start();
require_once '../../config/config.php';

// 1. Access Control
$allowed_roles = ['Department Head', 'Admin']; 
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'], $allowed_roles)) {
    header("Location: /HRIS/login.html");
    exit();
}

$my_emp_id = $_SESSION['employee_id'];
$my_role = $_SESSION['role_name'];
// Use 'department_id' from session (ensure this is set at login)
$my_dept_id = $_SESSION['department_id'] ?? null; 

$default_profile_image = '../../assets/images/default_user.png';

// --- FILTERS & PAGINATION ---
$search_query = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // 2. LOGIC: Target applications marked 'Officer Recommended'
    $where_clauses = ["la.status = 'Officer Recommended'"];
    $params = [];

    // --- CRITICAL FIX: ROLE-BASED VISIBILITY ---
    // If NOT Admin, strictly filter by the Head's Department ID
    if ($my_role !== 'Admin') {
        if ($my_dept_id) {
            $where_clauses[] = "e.department_id = ?";
            $params[] = $my_dept_id;
        } else {
            // Safety: If for some reason dept_id is missing, show nothing (prevent leak)
            $where_clauses[] = "1 = 0"; 
        }
    }
    // If Admin, no department filter is added, showing all 'Officer Recommended'

    if (!empty($search_query)) {
        $where_clauses[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR la.reference_no LIKE ?)";
        $search_val = "%$search_query%";
        array_push($params, $search_val, $search_val, $search_val);
    }

    $where_sql = " WHERE " . implode(" AND ", $where_clauses);

    // 3. STATS: Count pending final approvals (Filtered by Dept)
    $stats_stmt = $pdo->prepare("
        SELECT COUNT(*) FROM leave_application la
        JOIN employee e ON la.employee_id = e.employee_id
        $where_sql
    ");
    $stats_stmt->execute($params);
    $total_to_approve = $stats_stmt->fetchColumn();

    // 4. STATS: Approved today (Only by this specific user)
    $approved_today_stmt = $pdo->prepare("
        SELECT COUNT(*) FROM leave_application 
        WHERE status = 'Approved' 
        AND authorized_official_id = ? 
        AND DATE(authorized_official_sign_date) = CURDATE()
    ");
    $approved_today_stmt->execute([$my_emp_id]);
    $approved_today = $approved_today_stmt->fetchColumn();

    $total_pages = ceil($total_to_approve / $limit);

    // 5. FETCH DATA
    $sql = "SELECT la.*, e.first_name, e.last_name, e.profile_pic, d.department_name, lt.name as leave_type_name
            FROM leave_application la
            JOIN employee e ON la.employee_id = e.employee_id
            LEFT JOIN department d ON e.department_id = d.department_id
            JOIN leave_types lt ON la.leave_type_id = lt.leave_types_id
            $where_sql
            ORDER BY la.authorized_officer_sign_date DESC 
            LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Final Approval Error: " . $e->getMessage());
    die("Database Error: Unable to load records.");
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Final Approval Queue - HRMS</title>
    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">
    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .dashboard-wrapper { gap: 15px !important; } 
        .content-card.table-card { margin-top: 0 !important; padding: 0 !important; }
        .report-log-header { padding: 15px 25px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .integrated-filter { padding: 12px 25px; background: #fbfcfd; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; gap: 15px; }
        .filter-inputs { display: flex; gap: 10px; flex: 1; }
        .filter-inputs input { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 5px; font-size: 13px; }
        
        .emp-info-cell { display: flex; align-items: center; gap: 12px; }
        .emp-profile-img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1px solid #e2e8f0; }
        .emp-details { display: flex; flex-direction: column; line-height: 1.2; }
        .emp-name { font-weight: 700; color: #1e293b; font-size: 13px; }
        .emp-office { font-size: 11px; color: #64748b; font-weight: 500; }

        .ref-no { font-family: monospace; color: #1d4ed8; font-weight: 600; font-size: 12px; }
        .status-badge.recommended { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        
        .action-btn { background: #059669; color: white; padding: 8px 14px; border-radius: 5px; text-decoration: none; font-size: 12px; font-weight: 700; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 6px; }
        .action-btn:hover { background: #047857; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        
        .ledger-table th { background: #f8fafc; padding: 12px 25px; text-align: left; color: #64748b; font-size: 11px; text-transform: uppercase; }
        .ledger-table td { padding: 12px 25px; border-bottom: 1px solid #f1f5f9; }
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
                        <h1 style="font-size: 22px;">Final Approval Queue</h1>
                        <p style="font-size: 13px;">Applications recommended by Officers awaiting your final signature.</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-stamp"></i></div>
                        <div class="stat-info">
                            <h3><?= $total_to_approve ?></h3>
                            <p>Pending My Approval</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-calendar-check"></i></div>
                        <div class="stat-info">
                            <h3><?= $approved_today ?></h3>
                            <p>Approved Today</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-building-circle-check"></i></div>
                        <div class="stat-info">
                            <h3>Active</h3>
                            <p>Dept: <?= htmlspecialchars($_SESSION['dept_name'] ?? 'Assigned') ?></p>
                        </div>
                    </div>
                </div>

                <div class="content-card table-card">
                    <div class="report-log-header">
                        <h2 style="font-size: 16px;">Recommended Leave Applications</h2>
                    </div>

                    <form method="GET" class="integrated-filter">
                        <div class="filter-inputs">
                            <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search name or Ref #" style="width: 300px;">
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 13px;">
                                <i class="fa-solid fa-magnifying-glass"></i> Search
                            </button>
                            <a href="?" class="btn-secondary" style="padding: 8px 16px; font-size: 13px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; text-decoration: none; border-radius: 5px;">
                                <i class="fa-solid fa-rotate-left"></i> Reset
                            </a>
                        </div>
                    </form>

                    <div class="table-container">
                        <table class="ledger-table">
                            <thead>
                                <tr>
                                    <th>Ref No.</th>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>Days</th>
                                    <th class="text-center">Current Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($leaves)): ?>
                                    <?php foreach ($leaves as $leave): ?>
                                    <tr>
                                        <td><span class="ref-no"><?= htmlspecialchars($leave['reference_no']) ?></span></td>
                                        <td>
                                            <div class="emp-info-cell">
                                                <img src="<?= (!empty($leave['profile_pic'])) ? htmlspecialchars($leave['profile_pic']) : $default_profile_image ?>" class="emp-profile-img">
                                                <div class="emp-details">
                                                    <span class="emp-name"><?= htmlspecialchars($leave['last_name'] . ', ' . $leave['first_name']) ?></span>
                                                    <span class="emp-office"><?= htmlspecialchars($leave['department_name']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><strong><?= strtoupper(htmlspecialchars($leave['leave_type_name'])) ?></strong></td>
                                        <td>
                                            <div style="font-weight: 700; font-size: 13px;"><?= number_format($leave['working_days'], 1) ?></div>
                                        </td>
                                        <td class="text-center">
                                            <span class="status-badge recommended">Officer Recommended</span>
                                        </td>
                                        <td class="text-center">
                                            <a href="approve-leave-final.php?id=<?= $leave['application_id'] ?>" class="action-btn">
                                                <i class="fa-solid fa-check-double"></i> Final Action
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" style="text-align:center; padding: 50px; color: #94a3b8;">No applications are awaiting your final approval.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <div class="pagination-container" style="padding: 15px 25px; border-top: 1px solid #edf2f7;">
                        <div class="pagination">
                            <button onclick="window.location.href='?page=<?= max(1, $page-1) ?>&search=<?= urlencode($search_query) ?>'" <?= $page <= 1 ? 'disabled' : '' ?>><i class="fa-solid fa-chevron-left"></i></button>
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <button onclick="window.location.href='?page=<?= $i ?>&search=<?= urlencode($search_query) ?>'" class="<?= $page == $i ? 'active' : '' ?>"><?= $i ?></button>
                            <?php endfor; ?>
                            <button onclick="window.location.href='?page=<?= min($total_pages, $page+1) ?>&search=<?= urlencode($search_query) ?>'" <?= $page >= $total_pages ? 'disabled' : '' ?>><i class="fa-solid fa-chevron-right"></i></button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>
    <script src="/HRIS/assets/js/script.js"></script>
</body>
</html>
<?php
session_start();
require_once '../../config/config.php';

// 1. ACCESS CONTROL
$allowed_roles = ['Department Head', 'Admin'];
$user_role = $_SESSION['role'] ?? $_SESSION['role_name'] ?? null;

if (!isset($_SESSION['employee_id']) || !in_array($user_role, $allowed_roles)) {
    header("Location: /HRIS/login.html");
    exit();
}

$my_id = $_SESSION['employee_id'];
$search_query = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$default_profile_image = '../../assets/images/default_user.png';

try {
    // 2. FETCH STATS (Approved by me)
    // Approved Today
    $stmt_today = $pdo->prepare("SELECT COUNT(*) FROM leave_application WHERE status = 'Approved' AND authorized_official_id = ? AND DATE(authorized_official_sign_date) = CURDATE()");
    $stmt_today->execute([$my_id]);
    $approved_today = $stmt_today->fetchColumn();

    // Total Approved by me
    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM leave_application WHERE status = 'Approved' AND authorized_official_id = ?");
    $stmt_total->execute([$my_id]);
    $total_approved = $stmt_total->fetchColumn();

    // 3. FETCH DATA WITH SEARCH & PAGINATION
    $search_param = "%$search_query%";
    $sql = "SELECT la.*, e.first_name, e.last_name, e.profile_pic, d.department_name, lt.name as leave_type_name
            FROM leave_application la
            JOIN employee e ON la.employee_id = e.employee_id
            LEFT JOIN department d ON e.department_id = d.department_id
            LEFT JOIN leave_types lt ON la.leave_type_id = lt.leave_types_id
            WHERE la.authorized_official_id = ? 
            AND la.status = 'Approved'
            AND (la.reference_no LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)
            ORDER BY la.authorized_official_sign_date DESC
            LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$my_id, $search_param, $search_param, $search_param]);
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pagination total
    $count_sql = "SELECT COUNT(*) FROM leave_application la 
                  JOIN employee e ON la.employee_id = e.employee_id 
                  WHERE la.authorized_official_id = ? AND la.status = 'Approved'
                  AND (la.reference_no LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute([$my_id, $search_param, $search_param, $search_param]);
    $total_rows = $count_stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Approved Leaves - HRMS</title>
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
        .status-badge.approved { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        
        .action-btn { background: #3b82f6; color: white; padding: 8px 14px; border-radius: 5px; text-decoration: none; font-size: 12px; font-weight: 700; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 6px; }
        .action-btn:hover { background: #2563eb; }
        
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
                        <h1 style="font-size: 22px;">Approved Leaves History</h1>
                        <p style="font-size: 13px;">Records of all leave applications that received your final approval.</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-file-circle-check"></i></div>
                        <div class="stat-info">
                            <h3><?= $total_approved ?></h3>
                            <p>Total Final Approvals</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-calendar-day"></i></div>
                        <div class="stat-info">
                            <h3><?= $approved_today ?></h3>
                            <p>Approved Today</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange"><i class="fa-solid fa-user-check"></i></div>
                        <div class="stat-info">
                            <h3>Active</h3>
                            <p>Dept Head Mode</p>
                        </div>
                    </div>
                </div>

                <div class="content-card table-card">
                    <div class="report-log-header">
                        <h2 style="font-size: 16px;">Completed Approvals</h2>
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
                                    <th>Approval Date</th>
                                    <th class="text-center">Status</th>
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
                                        <td><div style="font-weight: 700;"><?= number_format($leave['working_days'], 1) ?></div></td>
                                        <td><span style="font-size: 12px; color: #64748b;"><?= date('M d, Y', strtotime($leave['authorized_official_sign_date'])) ?></span></td>
                                        <td class="text-center">
                                            <span class="status-badge approved">Approved</span>
                                        </td>
                                        <td class="text-center">
                                            <a href="view-details.php?id=<?= $leave['application_id'] ?>" class="action-btn">
                                                <i class="fa-solid fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" style="text-align:center; padding: 50px; color: #94a3b8;">No approved applications found in your history.</td></tr>
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
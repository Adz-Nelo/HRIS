<?php
session_start();
require_once '../../config/config.php';

// ✅ FIX 1: Access Control (Standardized to role_name + HR Staff)
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

// --- FILTERS & PAGINATION ---
$search_query = $_GET['search'] ?? '';
$dept_filter  = $_GET['dept'] ?? '';
$page         = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit        = 10;
$offset       = ($page - 1) * $limit;

try {
    // Fetch departments for filter dropdown
    $departments = $pdo->query("SELECT * FROM department ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // ✅ FIX 3: Robust SQL Construction
    // This base condition will be shared by both the count and the data query
    $where_clauses = ["l.status IN ('Approved', 'Officer Recommended')"];
    $params = [];

    if (!empty($search_query)) {
        $where_clauses[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR l.reference_no LIKE ?)";
        $search_val = "%$search_query%";
        $params = array_merge($params, [$search_val, $search_val, $search_val]);
    }

    if (!empty($dept_filter)) {
        $where_clauses[] = "e.department_id = ?";
        $params[] = $dept_filter;
    }

    $where_sql = " WHERE " . implode(" AND ", $where_clauses);

    // 1. Get Total Count for Pagination
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM leave_application l 
        JOIN employee e ON l.employee_id = e.employee_id 
        $where_sql
    ");
    $count_stmt->execute($params);
    $total_rows = $count_stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // 2. Fetch Approved Leave Data
    $sql = "SELECT l.*, e.first_name, e.last_name, e.profile_pic, d.department_name, lt.name as leave_type_name
            FROM leave_application l
            JOIN employee e ON l.employee_id = e.employee_id
            LEFT JOIN department d ON e.department_id = d.department_id
            JOIN leave_types lt ON l.leave_type_id = lt.leave_types_id
            $where_sql
            ORDER BY l.authorized_officer_sign_date DESC 
            LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $approved_leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Approved Leaves Fetch Error: " . $e->getMessage());
    die("Database Error: Unable to retrieve approved leave records.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Leaves - HRMS</title>
    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .dashboard-wrapper { gap: 15px !important; } 
        .content-card.table-card { margin-top: 0 !important; padding: 0 !important; }
        .report-log-header { padding: 15px 25px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .report-log-header h2 { font-size: 16px; font-weight: 700; color: #1e293b; margin: 0; }
        .integrated-filter { padding: 12px 25px; background: #fbfcfd; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; gap: 15px; }
        .filter-inputs { display: flex; gap: 10px; flex: 1; }
        .filter-inputs input, .filter-inputs select { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 5px; font-size: 13px; }

        /* FIXED EMPLOYEE CELL: Name beside Profile, Office below Name */
        .emp-info-cell { display: flex; align-items: center; gap: 12px; }
        .emp-profile-img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1px solid #e2e8f0; }
        .emp-details { display: flex; flex-direction: column; line-height: 1.2; }
        .emp-name { font-weight: 700; color: #1e293b; font-size: 13px; }
        .emp-office { font-size: 11px; color: #64748b; font-weight: 500; }

        .ref-no { font-family: monospace; color: #1d4ed8; font-weight: 600; font-size: 12px; }
        .status-pill { padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: 800; text-transform: uppercase; background: #dcfce7; color: #166534; }
        .ledger-table th { background: #f8fafc; padding: 12px 25px; text-align: left; font-size: 11px; text-transform: uppercase; color: #64748b; }
        .ledger-table td { padding: 12px 25px; border-bottom: 1px solid #f1f5f9; }

        /* Action Buttons */
        .action-container { display: flex; gap: 10px; justify-content: center; }
        .btn-action { width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; font-size: 14px; transition: 0.2s; }
        .btn-view { color: #3b82f6; background: #eff6ff; }
        .btn-view:hover { background: #dbeafe; }
        .btn-print { color: #059669; background: #ecfdf5; }
        .btn-print:hover { background: #d1fae5; }
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
                        <h1 style="font-size: 22px;">Approved Applications</h1>
                        <p style="font-size: 13px;">View and print finalized leave requests.</p>
                    </div>
                </div>

                <div class="content-card table-card">
                    <div class="report-log-header">
                        <h2>Approval History</h2>
                    </div>

                    <form method="GET" class="integrated-filter">
                        <div class="filter-inputs">
                            <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search Name or Ref #" style="width: 250px;">
                            <select name="dept">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['department_id'] ?>" <?= $dept_filter == $d['department_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 13px; cursor: pointer;">
                                <i class="fa-solid fa-magnifying-glass"></i> Search
                            </button>
                            <a href="approve-leave.php" class="btn-secondary" style="padding: 8px 16px; font-size: 13px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; text-decoration: none; border-radius: 5px;">
                                <i class="fa-solid fa-rotate-left"></i> Reset
                            </a>
                        </div>
                    </form>

                    <div class="table-container">
                        <table class="ledger-table">
                            <thead>
                                <tr>
                                    <th>Employee & Office</th>
                                    <th>Ref No.</th>
                                    <th>Type</th>
                                    <th>Approval Date</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($approved_leaves)): ?>
                                    <?php foreach ($approved_leaves as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="emp-info-cell">
                                                <img src="<?= $row['profile_pic'] ?: $default_profile_image ?>" class="emp-profile-img">
                                                <div class="emp-details">
                                                    <span class="emp-name"><?= htmlspecialchars($row['last_name'] . ', ' . $row['first_name']) ?></span>
                                                    <span class="emp-office"><?= htmlspecialchars($row['department_name']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="ref-no"><?= htmlspecialchars($row['reference_no']) ?></span></td>
                                        <td><span style="font-weight: 700; color: #475569; font-size: 11px;"><?= strtoupper(htmlspecialchars($row['leave_type_name'])) ?></span></td>
                                        <td style="font-size: 12px;"><?= date('M d, Y', strtotime($row['authorized_officer_sign_date'] ?? $row['created_at'])) ?></td>
                                        <td class="text-center">
                                            <span class="status-pill">APPROVED</span>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-container">
                                                <a href="view-leave.php?id=<?= $row['application_id'] ?>" class="btn-action btn-view" title="View Details">
                                                    <i class="fa-solid fa-magnifying-glass"></i>
                                                </a>
                                                <a href="print-leave.php?id=<?= $row['application_id'] ?>" class="btn-action btn-print" title="Print Application">
                                                    <i class="fa-solid fa-print"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" style="text-align:center; padding: 50px; color: #94a3b8;">No approved applications found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <div class="pagination-container" style="padding: 15px 25px; border-top: 1px solid #edf2f7;">
                        <div class="pagination">
                            <button onclick="window.location.href='?page=<?= max(1, $page-1) ?>&dept=<?= $dept_filter ?>&search=<?= urlencode($search_query) ?>'" <?= $page <= 1 ? 'disabled' : '' ?>><i class="fa-solid fa-chevron-left"></i></button>
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <button onclick="window.location.href='?page=<?= $i ?>&dept=<?= $dept_filter ?>&search=<?= urlencode($search_query) ?>'" class="<?= $page == $i ? 'active' : '' ?>"><?= $i ?></button>
                            <?php endfor; ?>
                            <button onclick="window.location.href='?page=<?= min($total_pages, $page+1) ?>&dept=<?= $dept_filter ?>&search=<?= urlencode($search_query) ?>'" <?= $page >= $total_pages ? 'disabled' : '' ?>><i class="fa-solid fa-chevron-right"></i></button>
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
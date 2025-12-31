<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/config.php';

// ✅ FIX 1: Access Control (Updated to role_name and login.html)
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'], ['Admin', 'HR Officer'])) {
    header("Location: ../../login.html");
    exit();
}

// ✅ FIX 2: REAL-TIME HEARTBEAT
try {
    $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?")
        ->execute([$_SESSION['employee_id']]);
} catch (PDOException $e) { /* silent fail */ }

// Get Department ID from URL
$dept_id = $_GET['dept_id'] ?? '';

if (empty($dept_id)) {
    header("Location: department.php");
    exit();
}

// Filters & Pagination Settings
$search_query = $_GET['search'] ?? '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // 1. Fetch Department Details
    $dept_stmt = $pdo->prepare("SELECT * FROM department WHERE department_id = ?");
    $dept_stmt->execute([$dept_id]);
    $department = $dept_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$department) {
        die("Department not found.");
    }

    // 2. Build Query with Filters
    // Added last_active to check online status in the list
    $where_sql = "WHERE department_id = ?";
    $params = [$dept_id];

    if (!empty($search_query)) {
        $where_sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR employee_id LIKE ?)";
        $search_val = "%$search_query%";
        $params[] = $search_val;
        $params[] = $search_val;
        $params[] = $search_val;
    }

    // 3. Count for Pagination (Optimized)
    $count_sql = "SELECT COUNT(*) FROM employee $where_sql";
    $total_rows_stmt = $pdo->prepare($count_sql);
    $total_rows_stmt->execute($params);
    $filtered_total = (int)$total_rows_stmt->fetchColumn();
    $total_pages = ($filtered_total > 0) ? ceil($filtered_total / $limit) : 1;

    // 4. Fetch Paginated Results
    $sql = "SELECT employee_id, first_name, last_name, email, role, position, status, last_active 
            FROM employee 
            $where_sql 
            ORDER BY last_name ASC 
            LIMIT $limit OFFSET $offset";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Dept View Error: " . $e->getMessage());
    die("Database Error: Could not load department members.");
}

// Utility for pagination links
function getPaginationUrl($p) {
    $params = $_GET;
    $params['page'] = $p;
    return "?" . http_build_query($params);
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members: <?= htmlspecialchars($department['department_name']) ?> - HRMS</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/HRMS.png">
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <link rel="stylesheet" href="../../assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Reusing your exact styles for consistency */
        .dashboard-wrapper { gap: 15px !important; } 
        .content-card.table-card { margin-top: 0 !important; padding: 0 !important; }
        .report-log-header { padding: 15px 25px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .integrated-filter { padding: 12px 25px; background: #fbfcfd; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; gap: 15px; }
        .filter-inputs { display: flex; gap: 10px; flex: 1; }
        .filter-inputs input { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 5px; font-size: 13px; width: 100%; max-width: 300px; }
        
        .id-badge { font-family: monospace; font-weight: 600; color: #1d4ed8; background: #eff6ff; padding: 2px 6px; border-radius: 4px; }
        .status-pill { padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #f1f5f9; color: #475569; }

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
                        <a href="department.php" style="text-decoration:none; font-size:12px; font-weight:700; color:#3b82f6;">
                            <i class="fa-solid fa-arrow-left"></i> BACK TO DIRECTORY
                        </a>
                        <h1 style="font-size: 22px; margin-top:5px;"><?= htmlspecialchars($department['department_name']) ?></h1>
                        <p style="font-size: 13px;">Listing members of Dept ID: <b><?= htmlspecialchars($department['department_id']) ?></b></p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-user-group"></i></div>
                        <div class="stat-info"><h3><?= $filtered_total ?></h3><p>Total Members</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-shield-check"></i></div>
                        <div class="stat-info"><h3>Active</h3><p>Employment Status</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-calendar-day"></i></div>
                        <div class="stat-info"><h3>Today</h3><p><?= date('M d, Y') ?></p></div>
                    </div>
                </div>

                <div class="content-card table-card">
                    <div class="report-log-header">
                        <h2 style="font-size: 16px;">Employee List</h2>
                        <div style="font-size: 12px; color: #64748b;">
                            Showing <?= count($employees) ?> staff members
                        </div>
                    </div>

                    <form method="GET" class="integrated-filter">
                        <input type="hidden" name="dept_id" value="<?= htmlspecialchars($dept_id) ?>">
                        <div class="filter-inputs">
                            <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search name or employee ID...">
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                            <a href="view-department-employees.php?dept_id=<?= $dept_id ?>" class="btn-secondary" style="text-decoration:none;"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                        </div>
                    </form>

                    <div class="table-container">
                        <table class="ledger-table">
                            <thead>
                                <tr>
                                    <th>Emp ID</th>
                                    <th>Full Name</th>
                                    <th>Position</th>
                                    <th>System Role</th>
                                    <th>Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($employees)): ?>
                                    <?php foreach ($employees as $emp): ?>
                                    <tr>
                                        <td><span class="id-badge"><?= htmlspecialchars($emp['employee_id']) ?></span></td>
                                        <td>
                                            <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?></div>
                                            <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($emp['email']) ?></div>
                                        </td>
                                        <td style="font-weight: 600; color: #475569;"><?= htmlspecialchars($emp['position'] ?: 'Unassigned') ?></td>
                                        <td><span style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($emp['role']) ?></span></td>
                                        <td>
                                            <span class="status-pill <?= $emp['status'] == 'Active' ? 'status-active' : 'status-inactive' ?>">
                                                <?= htmlspecialchars($emp['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="../admin/view-employee.php?id=<?= $emp['employee_id'] ?>" class="icon-btn" title="View Profile" style="color: #3b82f6; background: #eff6ff; width: 32px; height: 32px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                                                <i class="fa-solid fa-user-tie"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center; padding: 60px; color: #64748b;">
                                            <i class="fa-solid fa-users-slash" style="font-size: 40px; opacity: 0.3; margin-bottom: 10px;"></i>
                                            <p>No employees found in this department.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="report-log-header" style="border-top: 1px solid #f1f5f9; border-bottom: none;">
                        <div style="font-size: 12px; color: #64748b; font-weight: 600;">
                            Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $filtered_total) ?> of <?= $filtered_total ?> members
                        </div>
                        <div class="pagination-container">
                            <div class="pagination">
                                <button onclick="window.location.href='?dept_id=<?= $dept_id ?>&page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search_query) ?>'" <?= $page <= 1 ? 'disabled' : '' ?>>
                                    <i class="fa-solid fa-chevron-left"></i>
                                </button>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <button class="<?= $i == $page ? 'active' : '' ?>" onclick="window.location.href='?dept_id=<?= $dept_id ?>&page=<?= $i ?>&search=<?= urlencode($search_query) ?>'">
                                        <?= $i ?>
                                    </button>
                                <?php endfor; ?>
                                <button onclick="window.location.href='?dept_id=<?= $dept_id ?>&page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search_query) ?>'" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                                    <i class="fa-solid fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>

    <script src="../../assets/js/script.js"></script>
</body>
</html>
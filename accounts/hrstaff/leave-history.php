<?php
session_start();
require_once '../../config/config.php';

// Check if logged in
$employee_id = $_SESSION['employee_id'] ?? null;
if (!$employee_id) {
    header("Location: login.php");
    exit();
}

$default_profile_image = '../../assets/images/default_user.png';

// Handle Filters
$dept_filter = $_GET['dept'] ?? '';
$search_query = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // 1. Fetch Departments for filter dropdown
    $dept_stmt = $pdo->query("SELECT * FROM department ORDER BY department_name ASC");
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Build Query - UPDATED: Added status check to exclude 'Pending'
    // This will show only Approved, Rejected, Cancelled, etc.
    $count_sql = "SELECT COUNT(*) FROM leave_application l 
                  JOIN employee e ON l.employee_id = e.employee_id 
                  WHERE l.status != 'Pending'";
    
    $sql = "SELECT l.*, e.first_name, e.last_name, e.profile_pic, d.department_name 
            FROM leave_application l
            JOIN employee e ON l.employee_id = e.employee_id
            LEFT JOIN department d ON e.department_id = d.department_id
            WHERE l.status != 'Pending'";
    
    $params = [];
    if (!empty($dept_filter)) {
        $count_sql .= " AND e.department_id = ?";
        $sql .= " AND e.department_id = ?";
        $params[] = $dept_filter;
    }
    if (!empty($search_query)) {
        $search_cond = " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR l.reference_no LIKE ?)";
        $count_sql .= $search_cond;
        $sql .= $search_cond;
        $search_param = "%$search_query%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    $sql .= " ORDER BY l.created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leave_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave History - HRMS</title>


    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* Design preserved exactly as requested */
        .emp-info-cell { display: flex; align-items: center; gap: 10px; }
        .emp-profile-img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: none; flex-shrink: 0; }
        
        .integrated-filter {
            padding: 15px 20px;
            background: #fff;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .filter-inputs { display: flex; gap: 10px; flex-grow: 1; }
        .filter-inputs input, .filter-inputs select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            outline: none;
        }
        .filter-inputs input { width: 250px; }
        
        .status { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        .status.approved { background: #dcfce7; color: #166534; }
        .status.pending { background: #fef3c7; color: #92400e; }
        .status.rejected { background: #fee2e2; color: #991b1b; }
        .status.cancelled { background: #f1f5f9; color: #475569; }

        .ref-no { font-family: monospace; color: #1d4ed8; font-weight: 600; font-size: 12px; }
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
                        <h1 style="font-size: 22px; margin-bottom: 0;">Leave Application History</h1>
                        <p style="color: #64748b; font-size: 14px; margin-top: 2px;">Review and manage employee leave submissions.</p>
                    </div>
                </div>

                <div class="content-card">
                    <form method="GET" class="integrated-filter">
                        <div class="filter-inputs">
                            <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search Name or Reference #">
                            
                            <select name="dept" onchange="this.form.submit()">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['department_id'] ?>" <?= $dept_filter == $d['department_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-buttons" style="display: flex; gap: 8px;">
                            <button type="submit" class="btn-primary" style="background: #1d4ed8; color: white; border:none; padding: 8px 16px; border-radius: 6px; cursor:pointer; font-size: 13px;">
                                <i class="fa-solid fa-magnifying-glass"></i> Search
                            </button>
                            <a href="leave-history.php" style="text-decoration: none; padding: 8px 16px; background: #f1f5f9; color: #475569; border-radius: 6px; font-size: 13px;">
                                <i class="fa-solid fa-rotate-left"></i> Reset
                            </a>
                        </div>
                    </form>

                    <div class="table-container">
                        <table class="accounts-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Ref No.</th>
                                    <th>Leave Period</th>
                                    <th>Department</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($leave_history)): ?>
                                    <?php foreach ($leave_history as $leave): ?>
                                    <tr>
                                        <td>
                                            <div class="emp-info-cell">
                                                <img src="<?= (!empty($leave['profile_pic'])) ? $leave['profile_pic'] : $default_profile_image ?>" class="emp-profile-img">
                                                <div style="display: flex; flex-direction: column;">
                                                    <span style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($leave['last_name'] . ', ' . $leave['first_name']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="ref-no"><?= htmlspecialchars($leave['reference_no']) ?></span></td>
                                        <td>
                                            <div style="font-size: 13px; font-weight: 500;">
                                                <?= date('M d', strtotime($leave['start_date'])) ?> - <?= date('M d, Y', strtotime($leave['end_date'])) ?>
                                            </div>
                                            <small style="color: #94a3b8;"><?= $leave['working_days'] ?> Days</small>
                                        </td>
                                        <td><span style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($leave['department_name'] ?: 'N/A') ?></span></td>
                                        <td class="text-center">
                                            <?php 
                                                $s = $leave['status'];
                                                $class = strtolower(str_replace(' ', '', in_array($s, ['Approved', 'Officer Recommended']) ? 'approved' : $s));
                                            ?>
                                            <span class="status <?= $class ?>"><?= strtoupper($s) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <a href="view-leave.php?id=<?= $leave['application_id'] ?>" class="icon-btn" title="View"><i class="fa-solid fa-eye"></i></a>
                                            <a href="print-leave.php?id=<?= $leave['application_id'] ?>" class="icon-btn" style="color: #059669;" title="Print"><i class="fa-solid fa-print"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" style="text-align:center; padding: 50px; color: #94a3b8;">No records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <div class="pagination-container" style="padding: 15px; border-top: 1px solid #edf2f7;">
                        <div class="pagination">
                            <button onclick="window.location.href='?page=<?= max(1, $page-1) ?>&dept=<?= $dept_filter ?>&search=<?= urlencode($search_query) ?>'" <?= $page <= 1 ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-chevron-left"></i>
                            </button>
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <button onclick="window.location.href='?page=<?= $i ?>&dept=<?= $dept_filter ?>&search=<?= urlencode($search_query) ?>'" class="<?= $page == $i ? 'active' : '' ?>"><?= $i ?></button>
                            <?php endfor; ?>
                            <button onclick="window.location.href='?page=<?= min($total_pages, $page+1) ?>&dept=<?= $dept_filter ?>&search=<?= urlencode($search_query) ?>'" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-chevron-right"></i>
                            </button>
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
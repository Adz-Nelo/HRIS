<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/config.php';

// Access Control
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'], ['Admin', 'HR Officer'])) {
    header("Location: ../../login.html");
    exit();
}

// Heartbeat Update
try {
    $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?")
        ->execute([$_SESSION['employee_id']]);
} catch (PDOException $e) { }

$default_dept_icon = '../../assets/images/department_icon.png';
$error = '';
$success = '';

// 1. Handle Delete Request (Quiet Fail)
if (isset($_GET['delete_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM department WHERE department_id = ?");
        $stmt->execute([$_GET['delete_id']]);
        header("Location: department.php?deleted=1");
        exit();
    } catch (PDOException $e) {
        $error = "Department is currently in use and cannot be removed.";
    }
}

// 2. Filters & Pagination Settings
$search_query = $_GET['search'] ?? '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Stats Fetching
    $total_depts_all = $pdo->query("SELECT COUNT(*) FROM department")->fetchColumn() ?: 0;
    $total_staff = $pdo->query("SELECT COUNT(*) FROM employee")->fetchColumn() ?: 0;

    // 3. Main Query
    $sql = "SELECT d.*, COUNT(e.employee_id) as employee_count 
            FROM department d 
            LEFT JOIN employee e ON d.department_id = e.department_id 
            WHERE 1=1";
    
    $params = [];
    $search_val = null;
    if (!empty($search_query)) {
        $sql .= " AND (d.department_name LIKE ? OR d.department_id LIKE ?)";
        $search_val = "%$search_query%";
        $params[] = $search_val; 
        $params[] = $search_val;
    }

    $sql .= " GROUP BY d.department_id, d.department_name";

    // 4. Pagination Calculation
    $count_sql = "SELECT COUNT(*) FROM department";
    if (!empty($search_query)) {
        $count_sql .= " WHERE department_name LIKE ? OR department_id LIKE ?";
        $total_rows_stmt = $pdo->prepare($count_sql);
        $total_rows_stmt->execute([$search_val, $search_val]);
    } else {
        $total_rows_stmt = $pdo->query($count_sql);
    }
    
    $filtered_total = (int)$total_rows_stmt->fetchColumn();
    $total_pages = ($filtered_total > 0) ? ceil($filtered_total / $limit) : 1;

    // 5. Paginated Results
    $sql .= " ORDER BY d.department_name ASC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $departments = [];
    $total_pages = 1;
    $filtered_total = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments - HRMS</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/HRMS.png">
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <link rel="stylesheet" href="../../assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .dashboard-wrapper { gap: 15px !important; } 
        .content-card.table-card { margin-top: 0 !important; padding: 0 !important; }
        .report-log-header { padding: 15px 25px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .integrated-filter { padding: 12px 25px; background: #fbfcfd; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; gap: 15px; }
        .filter-inputs { display: flex; gap: 10px; flex: 1; }
        .filter-inputs input { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 5px; font-size: 13px; width: 100%; max-width: 300px; }
        .dept-info-cell { display: flex; align-items: center; gap: 12px; }
        .dept-icon-circle { width: 38px; height: 38px; border-radius: 8px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #64748b; border: 1px solid #e2e8f0; }
        .dept-name { font-weight: 700; color: #1e293b; font-size: 13px; }
        .id-badge { font-family: monospace; font-weight: 600; color: #1d4ed8; background: #eff6ff; padding: 2px 6px; border-radius: 4px; }
        .ledger-table th { background: #f8fafc; padding: 12px 25px; text-align: left; color: #64748b; font-size: 11px; text-transform: uppercase; }
        .ledger-table td { padding: 12px 25px; border-bottom: 1px solid #f1f5f9; }
        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .modal-content { background: white; width: 450px; border-radius: 20px; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); overflow: hidden; animation: zoomIn 0.3s ease; }
        .modal-header-bg { background: #1e293b; height: 80px; }
        .modal-body { padding: 0 30px 30px; text-align: center; margin-top: -50px; }
        .modal-pic-container { width: 100px; height: 100px; border-radius: 20%; border: 5px solid #fff; background: #3b82f6; color: white; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; font-size: 40px; }
        .info-list { text-align: left; background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #f1f5f9; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13px; border-bottom: 1px solid #edf2f7; padding-bottom: 8px; }
        .label { font-weight: 700; color: #64748b; text-transform: uppercase; font-size: 10px; }
        .val { font-weight: 600; color: #0f172a; }
        .val.highlight { color: #1d4ed8; font-size: 15px; }
        .close-btn { position: absolute; top: 15px; right: 20px; color: #fff; font-size: 24px; cursor: pointer; transition: 0.3s; }
        .close-btn:hover { color: #ef4444; }
        .modal-name { font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 20px; }
        .modal-actions { margin-top: 20px; display: flex; flex-direction: column; gap: 10px; }
        .btn-view-members { background: #1d4ed8; color: white; padding: 12px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 13px; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.3s; }
        .btn-view-members:hover { background: #1e40af; }
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
                        <h1 style="font-size: 22px;">Department Directory</h1>
                        <p style="font-size: 13px;">Displaying <?= count($departments) ?> of <?= $filtered_total ?> records.</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-building"></i></div>
                        <div class="stat-info"><h3><?= $total_depts_all ?></h3><p>Total Departments</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-users"></i></div>
                        <div class="stat-info"><h3><?= $total_staff ?></h3><p>Total Employees</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-sitemap"></i></div>
                        <div class="stat-info"><h3>Active</h3><p>Structure Status</p></div>
                    </div>
                </div>

                <div class="content-card table-card">
                    <div class="report-log-header">
                        <h2 style="font-size: 16px;">Department List</h2>
                        <a href="add-department.php" class="btn-primary" style="text-decoration:none;">
                            <i class="fa-solid fa-plus"></i> Add New Department
                        </a>
                    </div>

                    <form method="GET" class="integrated-filter">
                        <div class="filter-inputs">
                            <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search department name or ID...">
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                            <a href="department.php" class="btn-secondary" style="text-decoration:none;"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                        </div>
                    </form>

                    <div class="table-container">
                        <table class="ledger-table">
                            <thead>
                                <tr>
                                    <th>Dept ID</th>
                                    <th>Department Full Name</th>
                                    <th class="text-center">Staff Count</th>
                                    <th>Created Date</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($departments)): ?>
                                    <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td><span class="id-badge"><?= htmlspecialchars($dept['department_id']) ?></span></td>
                                        <td>
                                            <div class="dept-info-cell">
                                                <div class="dept-icon-circle"><i class="fa-solid fa-building-user"></i></div>
                                                <span class="dept-name"><?= htmlspecialchars($dept['department_name']) ?></span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span style="font-weight: 700; color: #475569;"><?= $dept['employee_count'] ?></span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($dept['created_at'])) ?></td>
                                        <td class="text-center">
                                            <div style="display: flex; gap: 5px; justify-content: center;">
                                                <button onclick='openDeptModal(<?= json_encode($dept) ?>)' class="icon-btn" title="View Details" style="color: #64748b; background: #f1f5f9; width: 32px; height: 32px; border-radius: 4px; border:none; cursor:pointer;">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                                <a href="edit-department.php?id=<?= $dept['department_id'] ?>" class="icon-btn" style="color: #3b82f6; background: #eff6ff; width: 32px; height: 32px; border-radius: 4px; display: flex; align-items: center; justify-content: center; text-decoration: none;">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <button onclick="confirmDelete('<?= $dept['department_id'] ?>')" class="icon-btn" style="color: #ef4444; background: #fef2f2; width: 32px; height: 32px; border-radius: 4px; border:none; cursor:pointer;">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align:center; padding: 40px; color: #64748b;">No departments found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="report-log-header" style="border-top: 1px solid #f1f5f9; border-bottom: none;">
                        <div style="font-size: 12px; color: #64748b; font-weight: 600;">
                            Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $filtered_total) ?> of <?= $filtered_total ?> results
                        </div>
                        <div class="pagination-container">
                            <div class="pagination">
                                <button onclick="window.location.href='?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search_query) ?>'" <?= $page <= 1 ? 'disabled' : '' ?>>
                                    <i class="fa-solid fa-chevron-left"></i>
                                </button>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <button class="<?= $i == $page ? 'active' : '' ?>" onclick="window.location.href='?page=<?= $i ?>&search=<?= urlencode($search_query) ?>'">
                                        <?= $i ?>
                                    </button>
                                <?php endfor; ?>
                                <button onclick="window.location.href='?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search_query) ?>'" <?= $page >= $total_pages ? 'disabled' : '' ?>>
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

    <div id="deptViewModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeDeptModal()">&times;</span>
            <div class="modal-header-bg"></div>
            <div class="modal-body">
                <div class="modal-pic-container"><i class="fa-solid fa-building"></i></div>
                <div class="modal-name" id="m-dept-name"></div>
                <div class="info-list">
                    <div class="info-row">
                        <span class="label">Department ID:</span> 
                        <span class="val" id="m-dept-id"></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Total Employees:</span> 
                        <span class="val highlight" id="m-dept-staff">0</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Date Created:</span> 
                        <span class="val" id="m-dept-date"></span>
                    </div>
                </div>
                <div class="modal-actions">
                    <a href="#" id="check-members-btn" class="btn-view-members">
                        <i class="fa-solid fa-users-viewfinder"></i> Check Department Members
                    </a>
                    <button onclick="closeDeptModal()" class="btn-secondary" style="width: 100%;">Close Preview</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/script.js"></script>
    <script>
        function openDeptModal(dept) {
            document.getElementById('m-dept-name').innerText = dept.department_name;
            document.getElementById('m-dept-id').innerText = dept.department_id;
            document.getElementById('m-dept-staff').innerText = dept.employee_count + ' Members';
            document.getElementById('m-dept-date').innerText = new Date(dept.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
            document.getElementById('check-members-btn').href = 'view-department-employees.php?dept_id=' + encodeURIComponent(dept.department_id);
            document.getElementById('deptViewModal').style.display = 'flex';
        }
        function closeDeptModal() { document.getElementById('deptViewModal').style.display = 'none'; }
        function confirmDelete(id) {
            if(confirm('Are you sure you want to delete this department?')) {
                window.location.href = 'department.php?delete_id=' + id;
            }
        }
        window.onclick = function(e) { if (e.target == document.getElementById('deptViewModal')) closeDeptModal(); }
    </script>
</body>
</html>
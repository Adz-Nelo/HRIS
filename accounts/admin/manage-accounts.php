<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/config.php';

/**
 * 1. ACCESS CONTROL
 * Ensure role matches the 'role_name' set in login.php
 */
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'], ['Admin', 'HR Officer'])) {
    header("Location: ../../login.html");
    exit();
}

/**
 * 2. REAL-TIME HEARTBEAT
 * Updates last_active every time the page is loaded/refreshed
 */
try {
    $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?")
        ->execute([$_SESSION['employee_id']]);
} catch (PDOException $e) { 
    error_log("Status Update Error: " . $e->getMessage());
}

$default_profile_image = '../../assets/images/default_user.png';

/**
 * 3. FILTERS & PAGINATION
 */
$dept_filter  = $_GET['dept'] ?? '';
$search_query = $_GET['search'] ?? '';

// ✅ FIX: Corrected typo from $get to $_GET
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$limit  = 10;
$offset = ($page - 1) * $limit;

try {
    // Basic Statistics
    $total_accounts  = $pdo->query("SELECT COUNT(*) FROM employee")->fetchColumn();
    $active_accounts = $pdo->query("SELECT COUNT(*) FROM employee WHERE status = 'Active'")->fetchColumn();
    
    // Fetch Departments for filter dropdown
    $departments = $pdo->query("SELECT * FROM department ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    /**
     * 4. DYNAMIC QUERY BUILDING
     */
    $where_clauses = ["1=1"];
    $params = [];

    if (!empty($dept_filter)) { 
        $where_clauses[] = "e.department_id = ?"; 
        $params[] = $dept_filter; 
    }
    
    if (!empty($search_query)) {
        $where_clauses[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ?)";
        $search_val = "%$search_query%";
        $params[] = $search_val; 
        $params[] = $search_val; 
        $params[] = $search_val;
    }

    $where_sql = implode(" AND ", $where_clauses);

    /**
     * 5. PAGINATION CALCULATION
     */
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM employee e WHERE $where_sql");
    $count_stmt->execute($params);
    $filtered_total = (int)$count_stmt->fetchColumn();
    
    // Prevent division by zero if no results found
    $total_pages = ($filtered_total > 0) ? ceil($filtered_total / $limit) : 1;

    /**
     * 6. FETCH RESULTS
     */
    $sql = "SELECT e.*, d.department_name 
            FROM employee e 
            LEFT JOIN department d ON e.department_id = d.department_id 
            WHERE $where_sql
            ORDER BY e.created_at DESC 
            LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

/**
 * UTILITY: Helper to generate pagination links while keeping search filters
 */
function getPaginationUrl($p) {
    $current_params = $_GET;
    $current_params['page'] = $p;
    return "?" . http_build_query($current_params);
}
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts - HRMS</title>

    <link rel="icon" type="image/x-icon" href="../../assets/images/HRMS.png">
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <link rel="stylesheet" href="../../assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .dashboard-wrapper { gap: 15px !important; } 
        .content-card.table-card { margin-top: 0 !important; padding: 0 !important; display: flex; flex-direction: column; }
        .report-log-header { padding: 15px 25px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .integrated-filter { padding: 12px 25px; background: #fbfcfd; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; gap: 15px; }
        .filter-inputs { display: flex; gap: 10px; flex: 1; }
        .filter-inputs input, .filter-inputs select { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 5px; font-size: 13px; }
        
        .emp-info-cell { display: flex; align-items: center; gap: 12px; }
        .emp-profile-img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1px solid #e2e8f0; }
        .emp-details { display: flex; flex-direction: column; line-height: 1.2; }
        .emp-name { font-weight: 700; color: #1e293b; font-size: 13px; }
        .emp-office { font-size: 11px; color: #64748b; font-weight: 500; }

        .role-badge { padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; background:rgb(100, 117, 149); color:rgb(254, 254, 254); border: 1px solidrgb(37, 40, 44); }
        .status-badge-active { padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; background: #dcfce7; color: #166534; }
        
        /* Pagination Footer Styling */
        .table-footer { padding: 15px 25px; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #fff; border-radius: 0 0 12px 12px; }
        .pagination-info { font-size: 12px; color: #64748b; font-weight: 600; }
        .pagination-btns { display: flex; gap: 5px; }
        .pg-btn { padding: 6px 12px; border: 1px solid #e2e8f0; background: white; border-radius: 6px; font-size: 12px; font-weight: 700; color: #475569; text-decoration: none; transition: 0.2s; }
        .pg-btn.active { background: #3b82f6; color: white; border-color: #3b82f6; }
        .pg-btn:hover:not(.active) { background: #f8fafc; }
        .pg-btn.disabled { pointer-events: none; opacity: 0.5; background: #f1f5f9; }

        /* MODAL STYLING */
        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .modal-content { background: white; width: 450px; border-radius: 20px; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); overflow: hidden; animation: zoomIn 0.3s ease; }
        @keyframes zoomIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        
        .modal-header-bg { background: #1e293b; height: 80px; }
        .modal-body { padding: 0 30px 30px; text-align: center; margin-top: -50px; }
        .modal-pic { width: 100px; height: 100px; border-radius: 50%; border: 5px solid #fff; object-fit: cover; background: #fff; margin-bottom: 15px; }
        
        .donut-ring { width: 110px; height: 110px; border-radius: 50%; border: 8px solid #f1f5f9; border-top: 8px solid #10b981; display: flex; flex-direction: column; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .age-num { font-size: 28px; font-weight: 800; color: #0f172a; line-height: 1; }
        .age-txt { font-size: 9px; color: #64748b; font-weight: 800; letter-spacing: 0.5px; margin-top: 4px; }

        .modal-name { font-size: 18px; font-weight: 800; color: #1e293b; margin-bottom: 20px; text-transform: uppercase; }
        .info-list { text-align: left; background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #f1f5f9; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13px; border-bottom: 1px solid #edf2f7; padding-bottom: 8px; }
        .info-row:last-child { border: none; margin-bottom: 0; padding-bottom: 0; }
        .label { font-weight: 700; color: #64748b; text-transform: uppercase; font-size: 10px; }
        .val { font-weight: 600; color: #0f172a; }
        .close-btn { position: absolute; top: 15px; right: 20px; color: #fff; font-size: 24px; cursor: pointer; }
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
                        <h1 style="font-size: 22px;">Manage Accounts</h1>
                        <p style="font-size: 13px;">Overview of all registered employees and system access roles.</p>
                    </div>
                    <div class="date-time-widget">
                        <div class="time" id="real-time">--:--:-- --</div>
                        <div class="date" id="real-date">Loading...</div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-users-gear"></i></div>
                        <div class="stat-info"><h3><?= $total_accounts ?></h3><p>Total Registered</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-user-check"></i></div>
                        <div class="stat-info"><h3><?= $active_accounts ?></h3><p>Active Status</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-shield-halved"></i></div>
                        <div class="stat-info"><h3>Admin</h3><p>Access Level</p></div>
                    </div>
                </div>

                <div class="content-card table-card">
                    <div class="report-log-header">
                        <h2 style="font-size: 16px;">Employee Account Directory</h2>
                        <a href="add-employee.php" class="btn-primary" style="text-decoration:none;">
                            <i class="fa-solid fa-plus"></i> Create Account
                        </a>
                    </div>

                    <form method="GET" class="integrated-filter">
                        <div class="filter-inputs">
                            <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search name or ID...">
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
                            <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                            <a href="manage-accounts.php" class="btn-secondary" style="text-decoration:none;"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                        </div>
                    </form>

                    <div class="table-container">
                        <table class="ledger-table">
                            <thead>
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Full Name & Department</th>
                                    <th>System Role</th>
                                    <th>Join Date</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($employees)): ?>
                                    <?php foreach ($employees as $emp): 
                                        $bday = !empty($emp['birth_date']) ? $emp['birth_date'] : '1990-01-01';
                                        $currentAge = date_diff(date_create($bday), date_create('today'))->y;
                                        $remain = 65 - $currentAge;
                                        $remain = ($remain < 0) ? 0 : $remain;
                                    ?>
                                    <tr>
                                        <td><span style="font-family: monospace; font-weight: 600; color: #1d4ed8;"><?= htmlspecialchars($emp['employee_id']) ?></span></td>
                                        <td>
                                            <div class="emp-info-cell">
                                                <img src="<?= (!empty($emp['profile_pic'])) ? $emp['profile_pic'] : $default_profile_image ?>" class="emp-profile-img">
                                                <div class="emp-details">
                                                    <span class="emp-name"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?></span>
                                                    <span class="emp-office"><?= htmlspecialchars($emp['department_name'] ?: 'No Dept') ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="role-badge"><?= htmlspecialchars($emp['role']) ?></span></td>
                                        <td><?= date('M d, Y', strtotime($emp['created_at'])) ?></td>
                                        <td class="text-center"><span class="status-badge-active">Active</span></td>
                                        <td class="text-center">
                                            <div style="display: flex; gap: 5px; justify-content: center;">
                                                <button onclick='openViewModal(<?= json_encode($emp) ?>, <?= $remain ?>)' class="icon-btn" title="Quick View" style="color: #64748b; background: #f1f5f9; width: 32px; height: 32px; border-radius: 4px; border:none; cursor:pointer;">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                                <a href="edit-account.php?id=<?= $emp['employee_id'] ?>" class="icon-btn" style="color: #3b82f6; background: #eff6ff; width: 32px; height: 32px; border-radius: 4px; display: flex; align-items: center; justify-content: center; text-decoration: none;">
                                                    <i class="fa-solid fa-user-pen"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center">No employees found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-footer">
                        <div class="pagination-info">
                            Showing <?= count($employees) ?> of <?= $filtered_total ?> results
                        </div>
                        <div class="pagination-btns">
                            <a href="<?= getPaginationUrl(max(1, $page - 1)) ?>" class="pg-btn <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="<?= getPaginationUrl($i) ?>" class="pg-btn <?= ($i == $page) ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <a href="<?= getPaginationUrl(min($total_pages, $page + 1)) ?>" class="pg-btn <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>

    <div id="qViewModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeViewModal()">&times;</span>
            <div class="modal-header-bg"></div>
            <div class="modal-body">
                <img id="m-pic" src="" class="modal-pic">
                <div class="donut-ring">
                    <span class="age-num" id="m-remain">0</span>
                    <span class="age-txt">YEARS REMAINING</span>
                </div>
                <div class="modal-name" id="m-name"></div>
                <div class="info-list">
                    <div class="info-row"><span class="label">ID:</span> <span class="val" id="m-id"></span></div>
                    <div class="info-row"><span class="label">Position:</span> <span class="val" id="m-pos">N/A</span></div>
                    <div class="info-row"><span class="label">Department:</span> <span class="val" id="m-dept"></span></div>
                    <div class="info-row"><span class="label">Email:</span> <span class="val" id="m-email"></span></div>
                    <div class="info-row"><span class="label">Gender:</span> <span class="val" id="m-gender"></span></div>
                    <div class="info-row"><span class="label">Birth Date:</span> <span class="val" id="m-bday"></span></div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/script.js"></script>
    <script>
        function openViewModal(emp, remain) {
            document.getElementById('m-name').innerText = emp.first_name + ' ' + (emp.middle_name ? emp.middle_name + ' ' : '') + emp.last_name;
            document.getElementById('m-id').innerText = emp.employee_id;
            document.getElementById('m-pos').innerText = (emp.position && emp.position.trim() !== "") ? emp.position : 'N/A';
            document.getElementById('m-dept').innerText = emp.department_name || 'HR Management';
            document.getElementById('m-email').innerText = emp.email || 'N/A';
            document.getElementById('m-gender').innerText = emp.gender || 'Not Specified';
            document.getElementById('m-bday').innerText = emp.birth_date ? new Date(emp.birth_date).toLocaleDateString('en-GB') : 'N/A';
            document.getElementById('m-remain').innerText = remain;
            document.getElementById('m-pic').src = emp.profile_pic || '../../assets/images/default_user.png';
            document.getElementById('qViewModal').style.display = 'flex';
        }
        function closeViewModal() { document.getElementById('qViewModal').style.display = 'none'; }
        window.onclick = function(e) { if (e.target == document.getElementById('qViewModal')) closeViewModal(); }
    </script>
</body>
</html>
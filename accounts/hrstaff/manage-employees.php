<?php
session_start();
require_once '../../config/config.php';

// ✅ FIX 1: Access Control (Standardized to role_name + HR Staff)
$allowed_roles = ['Admin', 'HR Officer', 'HR Staff'];
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'], $allowed_roles)) {
    header("Location: ../../login.html");
    exit();
}

// ✅ FIX 2: REAL-TIME HEARTBEAT
try {
    $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?")
        ->execute([$_SESSION['employee_id']]);
} catch (PDOException $e) { 
    /* silent fail to maintain UI performance */ 
}

$default_profile_image = '../../assets/images/default_user.png';

// --- FILTERS & SEARCH ---
$dept_filter = $_GET['dept'] ?? '';
$search_query = $_GET['search'] ?? '';

try {
    // 1. Fetch Departments for the filter dropdown
    $dept_stmt = $pdo->query("SELECT * FROM department ORDER BY department_name ASC");
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Build the Employee Query with Filters
    // Added a WHERE 1=1 to allow easy appending of AND conditions
    $sql = "SELECT e.*, d.department_name 
            FROM employee e 
            LEFT JOIN department d ON e.department_id = d.department_id 
            WHERE 1=1";
    
    $params = [];

    if (!empty($dept_filter)) {
        $sql .= " AND e.department_id = ?";
        $params[] = $dept_filter;
    }

    if (!empty($search_query)) {
        $sql .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ?)";
        $search_val = "%$search_query%";
        // Use array_merge to stay consistent with previous updates
        $params = array_merge($params, [$search_val, $search_val, $search_val]);
    }

    $sql .= " ORDER BY e.last_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Stats Logic (Real-time count of total and active status)
    $stat_stmt = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active FROM employee");
    $stats = $stat_stmt->fetch(PDO::FETCH_ASSOC);
    $totalEmployees = $stats['total'] ?? 0;
    $activeEmployees = $stats['active'] ?? 0;

} catch (PDOException $e) {
    // Secure error logging for production
    error_log("Employee Directory Error: " . $e->getMessage());
    die("Database Error: Unable to load directory.");
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manage Employees - HRMS</title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .emp-info-cell { display: flex; align-items: center; gap: 12px; }
        .emp-profile-img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid #f1f5f9; flex-shrink: 0; }
        .emp-details-text { display: flex; flex-direction: column; line-height: 1.2; }
        .emp-name { font-weight: 600; color: #1e293b; font-size: 14px; }
        .emp-email { font-size: 12px; color: #64748b; }
        .role-badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
        .status-badge.Active { background: #dcfce7; color: #16a34a; }
        .status-badge.Inactive { background: #fee2e2; color: #dc2626; }
        .status-badge.Retired { background: #f1f5f9; color: #64748b; }
        
        .btn-add { background: #2563eb; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; }
        .btn-add:hover { background: #1d4ed8; color: white; }

        /* Filter Styling */
        .filter-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .filter-select, .search-input {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            outline: none;
            font-size: 14px;
            color: #475569;
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
                        <h1>Employee Management</h1>
                        <p>Maintain and monitor system users and personnel records.</p>
                    </div>
                    <div class="action-buttons">
                        <a href="add-employee.php" class="btn-add">
                            <i class="fa-solid fa-user-plus"></i> Add New Employee
                        </a>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-users"></i></div>
                        <div class="stat-info">
                            <h3><?= number_format($totalEmployees) ?></h3>
                            <p>Total Records</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-user-check"></i></div>
                        <div class="stat-info">
                            <h3><?= number_format($activeEmployees) ?></h3>
                            <p>Active Personnel</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-user-shield"></i></div>
                        <div class="stat-info">
                            <h3><?= ($totalEmployees - $activeEmployees) ?></h3>
                            <p>Inactive/Retired</p>
                        </div>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    <div class="feed-container" style="grid-column: span 3;">
                        <div class="content-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h2>Employee Masterlist</h2>
                                
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
                                        <input type="text" name="search" class="search-input" placeholder="Search name or ID..." value="<?= htmlspecialchars($search_query) ?>">
                                        <button type="submit" style="display:none;"></button>
                                    </div>

                                    <?php if(!empty($dept_filter) || !empty($search_query)): ?>
                                        <a href="manage-employees.php" title="Clear Filters" style="color: #ef4444; font-size: 14px; text-decoration: none;">
                                            <i class="fa-solid fa-circle-xmark"></i> Reset
                                        </a>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="ledger-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Employee Details</th>
                                            <th>Department & Position</th>
                                            <th>Role</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="employeeTable">
                                        <?php if (!empty($employees)): ?>
                                            <?php foreach ($employees as $emp): ?>
                                            <tr>
                                                <td><strong>#<?= htmlspecialchars($emp['employee_id']) ?></strong></td>
                                                <td>
                                                    <div class="emp-info-cell">
                                                        <img src="<?= (!empty($emp['profile_pic']) && file_exists($emp['profile_pic'])) ? $emp['profile_pic'] : $default_profile_image ?>" class="emp-profile-img">
                                                        <div class="emp-details-text">
                                                            <span class="emp-name"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?></span>
                                                            <span class="emp-email"><?= htmlspecialchars($emp['email'] ?: 'no-email@system.com') ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style="font-size: 13px; font-weight: 500;"><?= htmlspecialchars($emp['department_name'] ?: 'Unassigned') ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($emp['position'] ?: 'N/A') ?></small>
                                                </td>
                                                <td><span class="role-badge"><?= htmlspecialchars($emp['role']) ?></span></td>
                                                <td class="text-center">
                                                    <span class="status-badge <?= $emp['status'] ?>" style="padding: 4px 10px; border-radius: 12px; font-size: 10px; font-weight: 700;">
                                                        <?= strtoupper($emp['status']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="view-employee.php?id=<?= $emp['employee_id'] ?>" title="View Profile" style="color: #64748b; margin-right: 15px;">
                                                        <i class="fa-solid fa-id-badge"></i>
                                                    </a>
                                                    <a href="edit-employee.php?id=<?= $emp['employee_id'] ?>" title="Edit Record" style="color: #2563eb;">
                                                        <i class="fa-solid fa-user-gear"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center" style="padding: 60px; color: #94a3b8;">
                                                    <i class="fa-solid fa-user-slash d-block mb-3" style="font-size: 2.5rem;"></i>
                                                    No employee records found.
                                                </td>
                                            </tr>
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
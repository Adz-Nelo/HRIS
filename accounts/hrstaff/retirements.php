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

// --- FILTERS ---
$dept_filter = $_GET['dept'] ?? '';
$search_query = $_GET['search'] ?? '';

try {
    // 1. Fetch Departments for filter dropdown
    $departments = $pdo->query("SELECT * FROM department ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Build Query with Join
    $sql = "SELECT e.*, d.department_name 
            FROM employee e 
            LEFT JOIN department d ON e.department_id = d.department_id 
            WHERE e.status = 'Retired'";
    
    $params = [];
    if (!empty($dept_filter)) {
        $sql .= " AND e.department_id = ?";
        $params[] = $dept_filter;
    }
    if (!empty($search_query)) {
        $sql .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ?)";
        $search_param = "%$search_query%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    $sql .= " ORDER BY e.last_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $retired_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Stats logic
    $total_retired = count($retired_employees);
    
    // Logic for "Near Retirement" (Employees 60 years old and above who are still Active)
    $stmt_near = $pdo->prepare("SELECT COUNT(*) FROM employee WHERE status = 'Active' AND TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) >= 60");
    $stmt_near->execute();
    $near_retirement = $stmt_near->fetchColumn();

} catch (PDOException $e) {
    error_log("Retired List Error: " . $e->getMessage());
    die("Database Error: Unable to retrieve records.");
}
?>





<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Retirement Records - HRMS</title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">


    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* GAP FIX: Pulling table up */
        .content-card { margin-top: 0 !important; }

        .emp-info-cell { display: flex; align-items: center; gap: 12px; }
        .emp-profile-img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: none; }
        .emp-name { font-weight: 600; color: #1e293b; display: block; }
        .emp-id-sub { font-size: 11px; color: #94a3b8; font-weight: 500; }
        
        .stat-card.orange { border-left: 4px solid #f59e0b; }
        .stat-icon.orange { background: #fef3c7; color: #d97706; }

        /* Integrated Filter Bar */
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
                        <h1 style="margin-bottom: 0;">Retirement Management</h1>
                        <p style="margin-top: 2px;">Records of employees who have reached retirement status.</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card orange">
                        <div class="stat-icon orange"><i class="fa-solid fa-user-slash"></i></div>
                        <div class="stat-info">
                            <h3><?= number_format($total_retired) ?></h3>
                            <p>Total Retired</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-hourglass-half"></i></div>
                        <div class="stat-info">
                            <h3><?= number_format($near_retirement) ?></h3>
                            <p>Approaching (60+)</p>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <form method="GET" class="integrated-filter">
                        <div class="filter-inputs">
                            <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search name or ID...">
                            
                            <select name="dept" onchange="this.form.submit()">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['department_id'] ?>" <?= $dept_filter == $d['department_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-actions" style="display: flex; gap: 8px;">
                            <button type="submit" class="btn-primary" style="background-color: #1d4ed8; color: white; border:none; padding: 9px 18px; border-radius: 6px; cursor:pointer; font-size: 13px;">
                                <i class="fa-solid fa-filter"></i> Apply
                            </button>
                            <a href="retirements.php" style="text-decoration: none; padding: 9px 18px; background: #f1f5f9; color: #475569; border-radius: 6px; font-size: 13px;">
                                <i class="fa-solid fa-rotate"></i> Reset
                            </a>
                        </div>
                    </form>

                    <div class="table-container">
                        <table class="accounts-table">
                            <thead>
                                <tr>
                                    <th>Retiree Details</th>
                                    <th>Department</th>
                                    <th>Last Position</th>
                                    <th>Contact Info</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="retireeTableBody">
                                <?php if (!empty($retired_employees)): ?>
                                    <?php foreach ($retired_employees as $emp): ?>
                                    <tr>
                                        <td>
                                            <div class="emp-info-cell">
                                                <img src="<?= (!empty($emp['profile_pic'])) ? $emp['profile_pic'] : $default_profile_image ?>" class="emp-profile-img">
                                                <div>
                                                    <span class="emp-name"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']) ?></span>
                                                    <span class="emp-id-sub">ID: #<?= htmlspecialchars($emp['employee_id']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span style="font-size: 13px;"><?= htmlspecialchars($emp['department_name'] ?: 'N/A') ?></span></td>
                                        <td><?= htmlspecialchars($emp['position'] ?: 'Former Staff') ?></td>
                                        <td>
                                            <div style="font-size: 12px; color: #475569;">
                                                <i class="fa-solid fa-envelope" style="width: 14px;"></i> <?= htmlspecialchars($emp['email']) ?><br>
                                                <i class="fa-solid fa-phone" style="width: 14px;"></i> <?= htmlspecialchars($emp['contact_number']) ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="status" style="background-color: #f1f5f9; color: #64748b; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700;">RETIRED</span>
                                        </td>
                                        <td class="text-center">
                                            <a href="view-employee.php?id=<?= $emp['employee_id'] ?>" class="icon-btn" title="View Profile"><i class="fa-solid fa-eye"></i></a>
                                            <a href="edit-employee.php?id=<?= $emp['employee_id'] ?>" class="icon-btn success" title="Edit Record"><i class="fa-solid fa-pen-to-square"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 50px; color: #94a3b8;">No retiree records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>

        <div id="rightbar-placeholder"></div>
    </div>

    <script src="/HRIS/assets/js/script.js"></script>
    <script>
        document.querySelector('input[name="search"]').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#retireeTableBody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
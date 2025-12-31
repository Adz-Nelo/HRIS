<?php
session_start();
require_once '../../config/config.php';

// ✅ FIX 1: Access Control (Updated to role_name and added HR Staff)
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

// --- FILTERS & SEARCH ---
$dept_filter = $_GET['dept'] ?? '';
$search_query = $_GET['search'] ?? '';

try {
    // 1. Fetch Departments for filter dropdown
    $departments = $pdo->query("SELECT * FROM department ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Query for Retired Employees 
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
        $search_val = "%$search_query%";
        $params = array_merge($params, [$search_val, $search_val, $search_val]);
    }

    // Order by most recently retired/updated
    $sql .= " ORDER BY e.updated_at DESC"; 
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $retired_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_retired = count($retired_list);

} catch (PDOException $e) {
    // Secure error logging
    error_log("Database Error (Retired List): " . $e->getMessage());
    die("A database error occurred. Please contact the administrator.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retirement Database - HRMS</title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* GAP & LAYOUT OVERRIDES (Matching Reports Style) */
        .dashboard-wrapper { gap: 15px !important; } 
        .welcome-header { margin-bottom: 0 !important; }
        .stats-grid { margin-bottom: 0 !important; }
        
        /* Table Card Tweaks */
        .content-card.table-card { 
            margin-top: 0 !important; 
            padding: 0 !important; 
        }

        .report-log-header {
            padding: 15px 25px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .report-log-header h2 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        /* Filter Area */
        .db-filter-bar {
            padding: 12px 25px;
            background: #fbfcfd;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .db-filter-bar input, .db-filter-bar select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            font-size: 13px;
        }

        /* Employee Info Cells */
        .emp-info-cell { display: flex; align-items: center; gap: 10px; }
        .emp-profile-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
        
        .retired-badge {
            background: rgba(100, 116, 139, 0.1);
            color: #64748b;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        /* Table Widths */
        .ledger-table th, .ledger-table td { padding: 12px 25px; }

        /* Side Info Card specific */
        .side-info-card { padding: 20px !important; height: fit-content; }
        .info-item { margin-top: 15px; border-top: 1px solid #f1f5f9; padding-top: 15px; }
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
                        <h1>Retirement Database</h1>
                        <p>Archive of all retired personnel and their service history.</p>
                    </div>
                    <div class="date-time-widget">
                        <div class="time" id="real-time">--:--:-- --</div>
                        <div class="date" id="real-date">Loading...</div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-box-archive"></i></div>
                        <div class="stat-info">
                            <h3><?= $total_retired ?></h3>
                            <p>Total Retirees</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-file-export"></i></div>
                        <div class="stat-info">
                            <h3 style="font-size: 18px;">Export List</h3>
                            <p>Generate CSV Archive</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-shield-halved"></i></div>
                        <div class="stat-info">
                            <h3 style="font-size: 18px;">Immutable</h3>
                            <p>Read-only Records</p>
                        </div>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    <div class="content-card table-card">
                        <div class="report-log-header">
                            <h2>Retired Personnel Masterlist</h2>
                        </div>

                        <form method="GET" class="db-filter-bar">
                            <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search name or ID..." style="flex: 2;">
                            <select name="dept" style="flex: 1;">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['department_id'] ?>" <?= $dept_filter == $d['department_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-primary" style="padding: 8px 16px;">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </button>
                        </form>

                        <div class="table-container">
                            <table class="ledger-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Last Department</th>
                                        <th>Years Served</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(!empty($retired_list)): ?>
                                        <?php foreach($retired_list as $row): ?>
                                        <tr>
                                            <td>
                                                <div class="emp-info-cell">
                                                    <img src="<?= $row['profile_pic'] ?: $default_profile_image ?>" class="emp-profile-img">
                                                    <div>
                                                        <div style="font-weight: 600;"><?= $row['last_name'] . ', ' . $row['first_name'] ?></div>
                                                        <small style="color:#64748b;">ID: <?= $row['employee_id'] ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($row['department_name']) ?></td>
                                            <td>
                                                <?php 
                                                    $start = new DateTime($row['hire_date']);
                                                    $end = new DateTime(); 
                                                    echo $start->diff($end)->y . " Years";
                                                ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="retired-badge">RETIRED</span>
                                            </td>
                                            <td class="text-center">
                                                <a href="#" class="icon-btn" title="View Service Record"><i class="fa-solid fa-folder-open"></i></a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" style="text-align:center; padding: 50px; color: #94a3b8;">No retired records found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="content-card side-info-card">
                        <div class="card-header" style="margin-bottom: 15px; padding-bottom: 10px;">
                            <h2>Database Info</h2>
                        </div>
                        <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.6;">
                            Records in this database are moved from the active list once status is changed to <strong>"Retired"</strong>.
                        </p>
                        
                        <div class="info-item">
                            <strong style="font-size: 12px; display: block; margin-bottom: 5px;">Retention Policy</strong>
                            <p style="font-size: 12px; color: var(--text-secondary);">Records are kept permanently for pension verification and service history audits.</p>
                        </div>

                        <div style="margin-top: 20px;">
                            <button class="btn-secondary" style="width: 100%; justify-content: center; padding: 10px;">
                                <i class="fa-solid fa-print"></i> Print Masterlist
                            </button>
                        </div>
                    </div>
                </div> </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>


    <script src="/HRIS/assets/js/script.js"></script>
</body>
</html>
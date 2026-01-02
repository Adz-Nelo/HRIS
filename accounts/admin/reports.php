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

// 1. Pagination Settings
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 5; 
$offset = ($page - 1) * $limit;

try {
    // 2. Global Gender Stats (Added COALESCE to ensure numbers even if table is empty)
    $gender_stats = $pdo->query("
        SELECT 
            COALESCE(SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END), 0) as male_count,
            COALESCE(SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END), 0) as female_count,
            COUNT(*) as total_count
        FROM employee
    ")->fetch(PDO::FETCH_ASSOC);

    // 3. Count total departments for pagination
    $total_depts = $pdo->query("SELECT COUNT(*) FROM department")->fetchColumn();
    $total_pages = ($total_depts > 0) ? ceil($total_depts / $limit) : 1;

    // 4. Departmental Analytics with Pagination
    // ✅ FIX: Using Prepared Statement or direct query with LIMIT/OFFSET
    $dept_sql = "
        SELECT 
            d.department_id,
            d.department_name,
            COUNT(CASE WHEN e.gender = 'Male' THEN 1 END) as male,
            COUNT(CASE WHEN e.gender = 'Female' THEN 1 END) as female,
            COUNT(e.employee_id) as total
        FROM department d
        LEFT JOIN employee e ON d.department_id = e.department_id
        GROUP BY d.department_id, d.department_name
        ORDER BY d.department_name ASC
        LIMIT $limit OFFSET $offset
    ";
    $dept_analytics = $pdo->query($dept_sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Report Error: " . $e->getMessage());
    die("Database Error: A problem occurred while generating the report.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Analytics - HRMS</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/HRMS.png">
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <link rel="stylesheet" href="../../assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-wrapper { gap: 15px !important; } 
        .analytics-grid { display: grid; grid-template-columns: 1fr 350px; gap: 15px; margin-bottom: 20px; }
        .content-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; }
        .chart-container { padding: 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 300px; }
        
        .gender-bar-container { width: 100%; background: #f1f5f9; height: 8px; border-radius: 10px; margin-top: 8px; overflow: hidden; display: flex; }
        .bar-male { background: #3b82f6; height: 100%; }
        .bar-female { background: #ec4899; height: 100%; }
        
        .stat-val-male { color: #1d4ed8; font-weight: 800; }
        .stat-val-female { color: #be185d; font-weight: 800; }

        .btn-print-sm { background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; color: #475569; cursor: pointer; transition: 0.2s; }
        .btn-print-sm:hover { background: #3b82f6; color: white; border-color: #3b82f6; }

        .pagination-footer { padding: 15px 25px; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
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
                        <h1 style="font-size: 22px;">Workforce Insights</h1>
                        <p style="font-size: 13px;">Real-time gender distribution and department rosters.</p>
                    </div>
                </div>

                <div class="analytics-grid">
                    <div class="content-card">
                        <div class="report-log-header" style="padding: 15px 25px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                            <h2 style="font-size: 15px; font-weight: 700;">Departmental Breakdown</h2>
                        </div>
                        <div class="table-container">
                            <table class="ledger-table">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th class="text-center">Total</th>
                                        <th width="30%">Gender Ratio</th>
                                        <th class="text-center">Roster</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dept_analytics as $row): 
                                        $total = $row['total'] > 0 ? $row['total'] : 1;
                                        $m_perc = ($row['male'] / $total) * 100;
                                        $f_perc = ($row['female'] / $total) * 100;
                                    ?>
                                    <tr>
                                        <td style="font-weight: 700; color: #1e293b; font-size: 13px;"><?= htmlspecialchars($row['department_name']) ?></td>
                                        <td class="text-center" style="font-weight: 700;"><?= $row['total'] ?></td>
                                        <td>
                                            <div class="gender-bar-container">
                                                <div class="bar-male" style="width: <?= $m_perc ?>%"></div>
                                                <div class="bar-female" style="width: <?= $f_perc ?>%"></div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <button onclick="printDeptRoster('<?= $row['department_id'] ?>', '<?= addslashes($row['department_name']) ?>')" class="btn-print-sm">
                                                <i class="fa-solid fa-print"></i> PRINT
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="pagination-footer">
                            <span style="font-size: 12px; color: #64748b; font-weight: 600;">Page <?= $page ?> of <?= $total_pages ?></span>
                            <div class="pagination">
                                <button onclick="location.href='?page=<?= max(1, $page-1) ?>'" <?= $page <= 1 ? 'disabled' : '' ?>><i class="fa-solid fa-chevron-left"></i></button>
                                <?php for($i=1; $i<=$total_pages; $i++): ?>
                                    <button class="<?= $i == $page ? 'active' : '' ?>" onclick="location.href='?page=<?= $i ?>'"><?= $i ?></button>
                                <?php endfor; ?>
                                <button onclick="location.href='?page=<?= min($total_pages, $page+1) ?>'" <?= $page >= $total_pages ? 'disabled' : '' ?>><i class="fa-solid fa-chevron-right"></i></button>
                            </div>
                        </div>
                    </div>
                    <br>
                    <div class="content-card" style="margin: auto;">
                        <div class="report-log-header" style="padding: 15px 25px; border-bottom: 1px solid #f1f5f9;">
                            <h2 style="font-size: 15px; font-weight: 700;">Overall Ratio</h2>
                        </div>
                        <div class="chart-container">
                            <canvas id="genderPieChart"></canvas>
                        </div>
                        <div style="padding: 0 25px 20px; text-align: center;">
                            <p style="font-size: 12px; color: #64748b; font-weight: 600;">Total Workforce: <span style="color: #0f172a; font-weight: 800;"><?= $gender_stats['total_count'] ?></span></p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>

    <iframe id="printFrame" style="display:none;"></iframe>

    <script src="../../assets/js/script.js"></script>
    <script>
        // Pie Chart
        const ctx = document.getElementById('genderPieChart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Male', 'Female'],
                datasets: [{
                    data: [<?= $gender_stats['male_count'] ?>, <?= $gender_stats['female_count'] ?>],
                    backgroundColor: ['#3b82f6', '#ec4899'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, font: { weight: 'bold' } } }
                }
            }
        });

        // Print Function
        function printDeptRoster(id, name) {
            const frame = document.getElementById('printFrame');
            frame.src = 'print-roster.php?dept_id=' + id + '&dept_name=' + encodeURIComponent(name);
            frame.onload = function() {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            };
        }
    </script>
</body>
</html>
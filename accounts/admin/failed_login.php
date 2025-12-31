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

// --- UNBLOCK LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unblock_id'])) {
    try {
        // We set access_status to 'Unblock' to match your login.php logic
        $stmt = $pdo->prepare("UPDATE login_attempts SET access_status = 'Unblock', attempts = 0, status = 'Success' WHERE employee_id = ?");
        $stmt->execute([$_POST['unblock_id']]);
        header("Location: failed_login.php?status=success");
        exit();
    } catch (PDOException $e) {
        error_log("Unblock Error: " . $e->getMessage());
        $error = "Failed to unblock user.";
    }
}

$default_profile_image = '../../assets/images/default_user.png';

// Pagination & Search Settings
$search_query = $_GET['search'] ?? '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 15;
$offset = ($page - 1) * $limit;

try {
    // Stats for the dashboard cards
    $total_failures = $pdo->query("SELECT SUM(attempts) FROM login_attempts")->fetchColumn() ?: 0;
    $blocked_accounts = $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE access_status = 'Block'")->fetchColumn();

    $params = [];
    $where_clause = "";
    if (!empty($search_query)) {
        $where_clause = " WHERE (e.first_name LIKE ? OR e.last_name LIKE ? OR la.employee_id LIKE ? OR la.ip_address LIKE ?)";
        $search_val = "%$search_query%";
        $params = [$search_val, $search_val, $search_val, $search_val];
    }

    // Pagination Count
    $count_sql = "SELECT COUNT(*) FROM login_attempts la LEFT JOIN employee e ON la.employee_id = e.employee_id" . $where_clause;
    $total_rows_stmt = $pdo->prepare($count_sql);
    $total_rows_stmt->execute($params);
    $filtered_total = (int)$total_rows_stmt->fetchColumn();
    $total_pages = ($filtered_total > 0) ? ceil($filtered_total / $limit) : 1;

    // Main Query
    // Note: la.updated_at is used to show the most recent failed attempts first
    $sql = "SELECT la.*, e.first_name, e.last_name, e.middle_name, e.profile_pic, e.email, e.gender, e.position, d.department_name 
            FROM login_attempts la
            LEFT JOIN employee e ON la.employee_id = e.employee_id
            LEFT JOIN department d ON e.department_id = d.department_id
            $where_clause
            ORDER BY la.last_login DESC
            LIMIT $limit OFFSET $offset";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    die("A system error occurred. Please check logs.");
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
    <title>Security Audit - HRMS</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/HRMS.png">
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <link rel="stylesheet" href="../../assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .dashboard-wrapper { gap: 15px !important; } 
        .content-card.table-card { margin-top: 0 !important; padding: 0 !important; }
        .report-log-header { padding: 15px 25px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .integrated-filter { padding: 12px 25px; background: #fbfcfd; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; gap: 15px; }
        .filter-inputs input { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 5px; font-size: 13px; width: 100%; max-width: 300px; }
        .emp-info-cell { display: flex; align-items: center; gap: 12px; }
        .emp-profile-img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1px solid #e2e8f0; }
        .emp-details { display: flex; flex-direction: column; line-height: 1.2; }
        .emp-name { font-weight: 700; color: #1e293b; font-size: 13px; }
        .emp-office { font-size: 11px; color: #64748b; font-weight: 500; }
        .status-failed { padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .status-block { background: #1e293b; color: #fff; padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: 700; }
        .ip-text { font-family: monospace; font-weight: 600; color: #1d4ed8; font-size: 13px; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .modal-content { background: white; width: 450px; border-radius: 20px; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); overflow: hidden; animation: zoomIn 0.3s ease; }
        @keyframes zoomIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .modal-header-bg { background: #991b1b; height: 80px; }
        .modal-body { padding: 0 30px 30px; text-align: center; margin-top: -50px; }
        .modal-pic { width: 100px; height: 100px; border-radius: 50%; border: 5px solid #fff; object-fit: cover; background: #fff; margin-bottom: 15px; }
        .donut-ring { width: 110px; height: 110px; border-radius: 50%; border: 8px solid #f1f5f9; border-top: 8px solid #ef4444; display: flex; flex-direction: column; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .age-num { font-size: 28px; font-weight: 800; color: #0f172a; line-height: 1; }
        .age-txt { font-size: 9px; color: #64748b; font-weight: 800; letter-spacing: 0.5px; margin-top: 4px; }
        .modal-name { font-size: 18px; font-weight: 800; color: #1e293b; margin-bottom: 20px; text-transform: uppercase; }
        .info-list { text-align: left; background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #f1f5f9; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13px; border-bottom: 1px solid #edf2f7; padding-bottom: 8px; }
        .info-row:last-child { border: none; margin-bottom: 0; padding-bottom: 0; }
        .label { font-weight: 700; color: #64748b; text-transform: uppercase; font-size: 10px; }
        .val { font-weight: 600; color: #0f172a; }
        .close-btn { position: absolute; top: 15px; right: 20px; color: #fff; font-size: 24px; cursor: pointer; }
        
        /* Buttons */
        .unblock-btn { color: #10b981; background: #ecfdf5; border: 1px solid #a7f3d0; width: 32px; height: 32px; border-radius: 4px; cursor: pointer; transition: 0.2s; }
        .unblock-btn:hover { background: #10b981; color: #fff; }
        .btn-primary { display: flex; align-items: center; gap: 8px; }
        .btn-secondary { display: flex; align-items: center; gap: 8px; }

        /* Unblock Modal Specific */
        .unblock-confirm-bg { background: #10b981 !important; height: 80px; }
        .unblock-icon-container { width: 80px; height: 80px; background: #ecfdf5; color: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: -40px auto 15px; border: 4px solid #fff; }
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
                        <h1 style="font-size: 22px;">Security & Login Audit</h1>
                        <p style="font-size: 13px;">Monitoring <?= $filtered_total ?> authentication events.</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <div class="stat-info"><h3><?= $total_failures ?></h3><p>Total Failures</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-user-lock"></i></div>
                        <div class="stat-info"><h3><?= $blocked_accounts ?></h3><p>Blocked Users</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-shield-halved"></i></div>
                        <div class="stat-info"><h3>Secure</h3><p>System Status</p></div>
                    </div>
                </div>

                <div class="content-card table-card">
                    <div class="report-log-header">
                        <h2 style="font-size: 16px;">Authentication Logs</h2>
                    </div>
                    <form method="GET" class="integrated-filter">
                        <div class="filter-inputs">
                            <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search name, ID or IP...">
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="btn-primary">
                                <i class="fa-solid fa-magnifying-glass"></i> Search
                            </button>
                            <a href="failed_login.php" class="btn-secondary" style="text-decoration:none;">
                                <i class="fa-solid fa-rotate-left"></i> Reset
                            </a>
                        </div>
                    </form>

                    <div class="table-container">
                        <table class="ledger-table">
                            <thead>
                                <tr>
                                    <th>IP Address</th>
                                    <th>Employee</th>
                                    <th>Attempts</th>
                                    <th>Last Attempt</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Access</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><span class="ip-text"><?= htmlspecialchars($log['ip_address']) ?></span></td>
                                    <td>
                                        <div class="emp-info-cell">
                                            <img src="<?= (!empty($log['profile_pic'])) ? $log['profile_pic'] : $default_profile_image ?>" class="emp-profile-img">
                                            <div class="emp-details">
                                                <span class="emp-name"><?= htmlspecialchars($log['last_name'] . ', ' . $log['first_name']) ?></span>
                                                <span class="emp-office">ID: <?= $log['employee_id'] ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><b style="color: #ef4444;"><?= $log['attempts'] ?></b></td>
                                    <td style="font-size: 12px;"><?= date('M d, Y | h:i A', strtotime($log['updated_at'])) ?></td>
                                    <td class="text-center"><span class="status-failed"><?= $log['status'] ?></span></td>
                                    <td class="text-center">
                                        <?php if($log['access_status'] == 'Block'): ?>
                                            <span class="status-block">BLOCKED</span>
                                        <?php else: ?>
                                            <span style="color: #cbd5e1; font-size: 10px;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center" style="display: flex; justify-content: center; gap: 5px; padding: 12px 0;">
                                        <button onclick='openViewModal(<?= json_encode($log) ?>)' class="icon-btn" title="View Audit" style="color: #64748b; background: #f1f5f9; width: 32px; height: 32px; border-radius: 4px; border:none; cursor:pointer;">
                                            <i class="fa-solid fa-magnifying-glass-chart"></i>
                                        </button>
                                        
                                        <?php if($log['access_status'] == 'Block'): ?>
                                            <button type="button" onclick="openUnblockModal('<?= $log['employee_id'] ?>', '<?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?>')" class="unblock-btn" title="Unblock Account">
                                                <i class="fa-solid fa-unlock"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="report-log-header" style="border-top: 1px solid #f1f5f9; border-bottom: none;">
                        <div style="font-size: 12px; color: #64748b; font-weight: 600;">Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $filtered_total) ?> of <?= $filtered_total ?></div>
                        <div class="pagination">
                            <button onclick="window.location.href='?page=<?= max(1, $page-1) ?>&search=<?= urlencode($search_query) ?>'" <?= $page <= 1 ? 'disabled' : '' ?>><i class="fa-solid fa-chevron-left"></i></button>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <button class="<?= $i == $page ? 'active' : '' ?>" onclick="window.location.href='?page=<?= $i ?>&search=<?= urlencode($search_query) ?>'"><?= $i ?></button>
                            <?php endfor; ?>
                            <button onclick="window.location.href='?page=<?= min($total_pages, $page+1) ?>&search=<?= urlencode($search_query) ?>'" <?= $page >= $total_pages ? 'disabled' : '' ?>><i class="fa-solid fa-chevron-right"></i></button>
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
                <div class="donut-ring"><span class="age-num" id="m-attempts">0</span><span class="age-txt">FAILED ATTEMPTS</span></div>
                <div class="modal-name" id="m-name"></div>
                <div class="info-list">
                    <div class="info-row"><span class="label">IP:</span> <span class="val" id="m-ip"></span></div>
                    <div class="info-row"><span class="label">Dept:</span> <span class="val" id="m-dept"></span></div>
                    <div class="info-row"><span class="label">Pos:</span> <span class="val" id="m-pos"></span></div>
                    <div class="info-row"><span class="label">Time:</span> <span class="val" id="m-time"></span></div>
                    <div class="info-row"><span class="label">Agent:</span> <span class="val" id="m-agent" style="font-size: 9px;"></span></div>
                </div>
            </div>
        </div>
    </div>

    <div id="unblockModal" class="modal">
        <div class="modal-content" style="width: 400px;">
            <div class="modal-header-bg unblock-confirm-bg"></div>
            <div class="modal-body">
                <div class="unblock-icon-container">
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <h2 style="font-size: 20px; color: #1e293b; margin-bottom: 10px;">Restore Access?</h2>
                <p style="font-size: 14px; color: #64748b; margin-bottom: 25px;">You are about to unblock access for <br><strong id="unblock-user-name" style="color: #1e293b;"></strong>.</p>
                
                <form method="POST" id="unblockForm">
                    <input type="hidden" name="unblock_id" id="unblock-emp-id">
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <button type="button" onclick="closeUnblockModal()" class="btn-secondary" style="padding: 10px 20px;">Cancel</button>
                        <button type="submit" class="btn-primary" style="padding: 10px 20px; background: #10b981;">Confirm Unblock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../../assets/js/script.js"></script>
    <script>
        // View Audit Functions
        function openViewModal(log) {
            document.getElementById('m-name').innerText = log.first_name + ' ' + log.last_name;
            document.getElementById('m-attempts').innerText = log.attempts;
            document.getElementById('m-ip').innerText = log.ip_address;
            document.getElementById('m-dept').innerText = log.department_name || 'N/A';
            document.getElementById('m-pos').innerText = log.position || 'N/A';
            document.getElementById('m-time').innerText = log.updated_at;
            document.getElementById('m-agent').innerText = log.user_agent;
            document.getElementById('m-pic').src = log.profile_pic || '../../assets/images/default_user.png';
            document.getElementById('qViewModal').style.display = 'flex';
        }
        function closeViewModal() { document.getElementById('qViewModal').style.display = 'none'; }

        // Unblock Modal Functions
        function openUnblockModal(empId, fullName) {
            document.getElementById('unblock-emp-id').value = empId;
            document.getElementById('unblock-user-name').innerText = fullName;
            document.getElementById('unblockModal').style.display = 'flex';
        }
        function closeUnblockModal() { document.getElementById('unblockModal').style.display = 'none'; }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
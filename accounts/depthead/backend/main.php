<?php
session_start();
require_once '../../../config/config.php'; 

// --- 1. FETCH LOGGED-IN USER DATA ---
$displayName = "User"; 
$displayRole = "Staff"; 

if (isset($_SESSION['employee_id'])) {
    try {
        $stmtUser = $pdo->prepare("SELECT first_name, last_name, role FROM employee WHERE employee_id = ?");
        $stmtUser->execute([$_SESSION['employee_id']]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $displayName = $user['first_name'];
            $displayRole = $user['role'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching user data: " . $e->getMessage());
    }
}

// --- 2. INITIALIZE VARIABLES (Prevents Undefined Variable Warnings) ---
$recentLogs = [];
$deptReports = [];
$leaveHistory = [];

try {
    /**
     * LOGIC: Reset activity logs every day at 8:00 AM
     */
    $threshold = (date('H') < 8) ? date('Y-m-d 08:00:00', strtotime('yesterday')) : date('Y-m-d 08:00:00');

    // Fetch Recent Login Logs
    $stmtLogs = $pdo->prepare("
        SELECT e.first_name, e.last_name, l.updated_at 
        FROM login_attempts l
        JOIN employee e ON l.employee_id = e.employee_id
        WHERE l.updated_at >= ?
        ORDER BY l.updated_at DESC
    ");
    $stmtLogs->execute([$threshold]);
    $recentLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Departmental Leave Distribution
    // Note: This assumes you have a 'departments' and 'leave_applications' table
    $stmtDept = $pdo->query("
        SELECT d.dept_name, COUNT(la.id) as total_requests 
        FROM departments d
        LEFT JOIN leave_applications la ON d.dept_id = la.dept_id
        GROUP BY d.dept_name
    ");
    $deptReports = $stmtDept->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Leave History for the sidebar
    $stmtHistory = $pdo->query("
        SELECT e.first_name, e.last_name, la.leave_type, la.status, la.updated_at 
        FROM leave_applications la
        JOIN employee e ON la.employee_id = e.employee_id
        ORDER BY la.updated_at DESC 
        LIMIT 10
    ");
    $leaveHistory = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
}
?>

<style>
    /* ========== SCROLLABLE LOGS CSS ========== */
    .activity-list {
        max-height: 240px; 
        overflow-y: auto;
        padding: 0;
        margin: 0;
        list-style: none;
        scrollbar-width: none;  
        -ms-overflow-style: none;  
    }

    .activity-list::-webkit-scrollbar {
        display: none; 
    }

    .activity-list li {
        display: flex;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        height: 80px; 
        box-sizing: border-box;
    }

    .activity-list li i {
        font-size: 1.2rem;
        margin-right: 15px;
        color: #3498db;
    }

    .act-details strong {
        display: block;
        font-size: 0.95rem;
    }

    .act-details small {
        color: #888;
        font-size: 0.8rem;
    }

    /* ========== STATS GRID ADJUSTMENT ========== */
    .stat-info h3 {
        font-size: 1.1rem;
        margin-bottom: 2px;
    }
    .stat-info p {
        font-size: 0.8rem;
        color: #666;
        margin: 0;
    }
    .text-orange { color: #f39c12; }
    .text-green { color: #27ae60; }
    .text-red { color: #e74c3c; }
    .text-blue { color: #3498db; }
</style>

<div class="dashboard-wrapper">
    <div class="welcome-header">
        <div class="welcome-text">
            <h1>Welcome back, <?= htmlspecialchars($displayName) ?></h1>
            <p>You are logged in as <strong><?= htmlspecialchars($displayRole) ?></strong>. Activity reset at 8:00 AM.</p>
        </div>
        <div class="date-time-widget">
            <div class="time" id="real-time">--:--:-- --</div>
            <div class="date" id="real-date">Loading...</div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card clickable" onclick="window.location.href='pending-leave.php'">
            <div class="stat-icon blue"><i class="fa-solid fa-calendar-check"></i></div>
            <div class="stat-info">
                <h3>Pending Leave</h3>
                <p>Review leave requests</p>
            </div>
        </div>

        <div class="stat-card clickable" onclick="window.location.href='manage-employees.php'">
            <div class="stat-icon green"><i class="fa-solid fa-user-gear"></i></div>
            <div class="stat-info">
                <h3>Manage Employees</h3>
                <p>Edit profiles and staff records</p>
            </div>
        </div>

        <div class="stat-card clickable" onclick="window.location.href='retirements.php'">
            <div class="stat-icon orange"><i class="fa-solid fa-database"></i></div>
            <div class="stat-info">
                <h3>Retiree Database</h3>
                <p>Monitor retirement status</p>
            </div>
        </div>

        <div class="stat-card clickable" onclick="window.location.href='leave-history.php'">
            <div class="stat-icon red"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div class="stat-info">
                <h3>Leave History</h3>
                <p>Records of past leaves</p>
            </div>
        </div>
    </div>

    <div class="main-dashboard-grid">
        <div class="feed-container">
            <div class="content-card">
                <div class="card-header">
                    <h2>Departmental Leave Distribution</h2>
                    <button class="btn-more" onclick="window.location.href='reports.php'">
                        <i class="fas fa-chart-pie"></i>
                    </button>
                </div>
                <div class="table-container" style="padding: 15px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                        <thead>
                            <tr style="text-align: left; color: #64748b; border-bottom: 1px solid #eee;">
                                <th style="padding-bottom: 10px;">Department</th>
                                <th style="padding-bottom: 10px;">Application Count</th>
                                <th style="padding-bottom: 10px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($deptReports)): ?>
                                <?php foreach ($deptReports as $report): ?>
                                <tr style="border-bottom: 1px solid #f9f9f9;">
                                    <td style="padding: 12px 0; font-weight: 600;"><?= htmlspecialchars($report['dept_name']) ?></td>
                                    <td><?= htmlspecialchars($report['total_requests']) ?> Requests</td>
                                    <td><span class="badge-info" style="font-size: 10px; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 10px;">Active Queue</span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" style="text-align:center; padding: 20px;">No departmental data found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="side-info-container">
            <div class="content-card">
                <div class="card-header">
                    <h2>Leave History</h2>
                </div>
                <ul class="activity-list">
                    <?php if (!empty($leaveHistory)): ?>
                        <?php foreach ($leaveHistory as $log): 
                            $statusColor = 'text-orange'; 
                            $statusLabel = $log['status'];
                            if ($statusLabel == 'Approved') $statusColor = 'text-green';
                            if ($statusLabel == 'Rejected') $statusColor = 'text-red';
                            if ($statusLabel == 'Officer Recommended') $statusColor = 'text-blue';
                        ?>
                            <li>
                                <i class="fas fa-history <?= $statusColor ?>"></i>
                                <div class="act-details">
                                    <strong><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></strong> 
                                    applied for <?= htmlspecialchars($log['leave_type']) ?>
                                    <br><small>Status: <strong class="<?= $statusColor ?>"><?= htmlspecialchars($statusLabel) ?></strong> • <?= date('M d, h:i A', strtotime($log['updated_at'])) ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="act-details" style="padding: 20px;">No recent leave history.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    function updateDateTime() {
        const now = new Date();
        document.getElementById('real-time').innerText = now.toLocaleTimeString();
        document.getElementById('real-date').innerText = now.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }
    setInterval(updateDateTime, 1000);
    updateDateTime();
</script>
<?php
session_start();
// Ensure this path correctly points to your PDO connection file
require_once '../../../config/config.php'; 

try {
    // 1. Get Total Employees count
    $totalEmployees = $pdo->query("SELECT COUNT(*) FROM employee")->fetchColumn();

    // 2. Get Total Departments count
    $totalDepartments = $pdo->query("SELECT COUNT(*) FROM department")->fetchColumn();

    // 3. Get Total Failed Login Attempts
    // Note: This sums the 'attempts' column for failed status rows
    $totalFailed = $pdo->query("SELECT SUM(attempts) FROM login_attempts WHERE status = 'Failed'")->fetchColumn() ?: 0;

    // 4. Get Total Blocked Accounts
    $totalBlocked = $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE access_status = 'Block'")->fetchColumn();

    // 5. Get Recent Logs (Join with employee table to get actual names)
    $stmtLogs = $pdo->query("
        SELECT e.first_name, e.last_name, l.updated_at 
        FROM login_attempts l
        JOIN employee e ON l.employee_id = e.employee_id
        ORDER BY l.updated_at DESC 
        LIMIT 5
    ");
    $recentLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Basic error handling
    error_log($e->getMessage());
    $totalEmployees = $totalDepartments = $totalFailed = $totalBlocked = 0;
    $recentLogs = [];
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
</style>




<div class="dashboard-wrapper">
    <div class="welcome-header">
        <div class="welcome-text">
            <h1>Welcome back, Admin</h1>
            <p>Here's what's happening with your workforce today.</p>
        </div>
        <div class="date-time-widget">
            <div class="time" id="real-time">--:--:-- --</div>
            <div class="date" id="real-date">Loading...</div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card clickable" onclick="window.location.href='manage-accounts.php'">
            <div class="stat-icon blue"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <h3><?= number_format($totalEmployees) ?></h3>
                <p>Total Employee</p>
            </div>
        </div>

        <div class="stat-card clickable" onclick="window.location.href='department.php'">
            <div class="stat-icon green"><i class="fas fa-building"></i></div>
            <div class="stat-info">
                <h3><?= number_format($totalDepartments) ?></h3>
                <p>Total Department</p>
            </div>
        </div>

        <div class="stat-card clickable" onclick="window.location.href='failed_login.php'">
            <div class="stat-icon orange"><i class="fas fa-shield-alt"></i></div>
            <div class="stat-info">
                <h3><?= number_format($totalFailed) ?></h3>
                <p>Failed Login</p>
            </div>
        </div>

        <div class="stat-card clickable" onclick="window.location.href='failed_login.php'">
            <div class="stat-icon red"><i class="fas fa-user-slash"></i></div>
            <div class="stat-info">
                <h3><?= number_format($totalBlocked) ?></h3>
                <p>Blocked</p>
            </div>
        </div>
    </div>

    <div class="main-dashboard-grid">
        <div class="feed-container">
            <div class="content-card">
                <div class="card-header">
                    <h2>HRMS Announcements</h2>
                    <button class="btn-more">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>
                <div class="announcement-item">
                    <div class="ann-date">
                        <span class="month"><?= date('M') ?></span>
                        <span class="day"><?= date('d') ?></span>
                    </div>
                    <div class="ann-text">
                        <h4>System Synchronized</h4>
                        <p>Pulling live data from the <strong>redefence</strong> database. Reset cycle: 8:00 AM daily.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="side-info-container">
            <div class="content-card">
                <div class="card-header">
                    <h2>Recent Logs</h2>
                </div>

                <ul class="activity-list">
                    <?php if (!empty($recentLogs)): ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <li>
                                <i class="fas fa-history text-blue"></i>
                                <div class="act-details">
                                    <strong><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></strong> 
                                    active in system
                                    <small><?= date('h:i A', strtotime($log['updated_at'])) ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li style="height: auto; justify-content: center; padding: 30px;">
                            <div class="act-details" style="text-align: center;">No activity since 8:00 AM.</div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
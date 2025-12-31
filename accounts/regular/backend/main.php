<?php
session_start();
require_once '../../../config/config.php'; 

// --- FETCH LOGGED-IN USER DATA ---
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

try {
    /**
     * LOGIC: Reset activity logs every day at 8:00 AM
     */
    $threshold = (date('H') < 8) ? date('Y-m-d 08:00:00', strtotime('yesterday')) : date('Y-m-d 08:00:00');

    $stmtLogs = $pdo->prepare("
        SELECT e.first_name, e.last_name, l.updated_at 
        FROM login_attempts l
        JOIN employee e ON l.employee_id = e.employee_id
        WHERE l.updated_at >= ?
        ORDER BY l.updated_at DESC
    ");
    $stmtLogs->execute([$threshold]);
    $recentLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
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
            <h1>Welcome back, <?= htmlspecialchars($displayName) ?></h1>
            <p>You are logged in as <strong><?= htmlspecialchars($displayRole) ?></strong>. Activity reset at 8:00 AM.</p>
        </div>
        <div class="date-time-widget">
            <div class="time" id="real-time">--:--:-- --</div>
            <div class="date" id="real-date">Loading...</div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card clickable" onclick="window.location.href='apply-leave.php'">
            <div class="stat-icon blue"><i class="fa-solid fa-paper-plane"></i></div>
            <div class="stat-info">
                <h3>Apply Leave</h3>
                <p>Submit a new request</p>
            </div>
        </div>

        <div class="stat-card clickable" onclick="window.location.href='leave-balance.php'">
            <div class="stat-icon green"><i class="fa-solid fa-scale-balanced"></i></div>
            <div class="stat-info">
                <h3>Leave Balance</h3>
                <p>View employee balances</p>
            </div>
        </div>

        <div class="stat-card clickable" onclick="window.location.href='track-leave.php'">
            <div class="stat-icon orange"><i class="fa-solid fa-route"></i></div>
            <div class="stat-info">
                <h3>Track Leave</h3>
                <p>Monitor leave status</p>
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
                        <p>Pulling live data from the database. Reset cycle: 8:00 AM daily.</p>
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
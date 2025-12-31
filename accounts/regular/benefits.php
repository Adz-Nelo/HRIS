<?php
session_start();
require_once '../../config/config.php'; 

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

$employee_id = $_SESSION['employee_id'] ?? null;

// Fetch Employee Tenure (Example logic)
// In a real scenario, you'd calculate this from the 'hire_date' in your database
$hire_date = "2020-05-15"; 
$years_service = date_diff(date_create($hire_date), date_create('now'))->y;

// Define Loyalty Award Milestones
$milestones = [
    ['years' => 5, 'reward' => 'Bronze Pin & Cash Gift', 'status' => ($years_service >= 5)],
    ['years' => 10, 'reward' => 'Silver Medal & 1 Month Salary', 'status' => ($years_service >= 10)],
    ['years' => 15, 'reward' => 'Gold Medal & Vacation Package', 'status' => ($years_service >= 15)],
    ['years' => 20, 'reward' => 'Plaque of Excellence & Luxury Watch', 'status' => ($years_service >= 20)],
];

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Loyalty Rewards - HRMS</title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* Loyalty Specific Styles */
        .milestone-badge {
            background: #eff6ff;
            color: #2563eb;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.8rem;
        }
        
        .loyalty-timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 25px;
            border-left: 2px dashed #e2e8f0;
            padding-left: 25px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -9px;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            border: 3px solid #cbd5e1;
        }

        .timeline-item.active::before {
            border-color: #2563eb;
            background: #2563eb;
        }

        .timeline-item.active {
            border-left: 2px solid #2563eb;
        }

        .timeline-year {
            font-weight: 800;
            color: #1e293b;
            font-size: 1.1rem;
            margin-bottom: 2px;
        }

        .reward-title {
            color: #2563eb;
            font-weight: 700;
            font-size: 0.95rem;
        }

        .reward-desc {
            font-size: 0.85rem;
            color: #64748b;
        }

        .status-tag {
            font-size: 10px;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        .tag-achieved { background: #dcfce7; color: #16a34a; }
        .tag-locked { background: #f1f5f9; color: #94a3b8; }
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
                        <h1>Loyalty Award & Recognition</h1>
                        <p>“Loyalty is more than a word; it's a commitment. We value every year you spend growing with us.”</p>
                    </div>
                    <div class="date-time-widget text-end">
                        <div class="time fw-bold" id="real-time">--:--:-- --</div>
                        <div class="date text-muted" id="real-date">Loading...</div>
                    </div>
                </div>


                <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fa-solid fa-award"></i>
            </div>
            <div class="stat-info">
                <h3>Loyalty Award</h3>
                <p>Current Milestone Status</p>
            </div>
        </div>
        
        <?php 
        $next = 5;
        foreach($milestones as $m) { if(!$m['status']) { $next = $m['years']; break; } }
        ?>
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <div class="stat-info">
                <h3><?= $next - ($years_service % $next) ?> Years</h3>
                <p>Until Next Award</p>
            </div>
        </div>
    </div>

    <div class="main-dashboard-grid">
        <div class="feed-container">
            <div class="content-card">
                <div class="card-header">
                    <h2>Loyalty Reward Progression</h2>
                </div>
                
                <?php foreach($milestones as $m): ?>
                <div class="announcement-item" style="opacity: <?= $m['status'] ? '1' : '0.5' ?>;">
                    <div class="ann-date" style="border-color: <?= $m['status'] ? '#f39c12' : '#e2e8f0' ?>;">
                        <span class="month">YEAR</span>
                        <span class="day"><?= $m['years'] ?></span>
                    </div>
                    <div class="ann-text">
                        <h4 class="d-flex align-items-center gap-2">
                            <?= $m['reward'] ?>
                            <?php if($m['status']): ?>
                                <i class="fa-solid fa-circle-check text-green" style="font-size: 14px;"></i>
                            <?php endif; ?>
                        </h4>
                        <p><?= $m['status'] ? 'Awarded and recognized.' : 'Stay with us to unlock this reward!' ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="side-info-container">
            <div class="content-card">
                <div class="card-header">
                    <h2>Award Information</h2>
                </div>
                <ul class="activity-list">
                    <li>
                        <i class="fa-solid fa-circle-info text-blue"></i>
                        <div>
                            <strong>Eligibility</strong>
                            <small>Continuous service without any major disciplinary records.</small>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-calendar-check text-orange"></i>
                        <div>
                            <strong>Awarding Date</strong>
                            <small>Loyalty awards are given every annual Founding Anniversary.</small>
                        </div>
                    </li>
                </ul>
                
                <div style="margin-top: 25px; padding: 15px; background: #f8fafc; border-radius: 5px; border: 1px dashed #cbd5e1;">
                    <p style="font-size: 12px; color: var(--text-secondary); line-height: 1.4; margin: 0;">
                        <i class="fa-solid fa-quote-left" style="margin-right: 5px; color: #cbd5e1;"></i>
                        Loyalty is more than a word; it's a commitment. We value every year you spend growing with us.
                    </p>
                </div>
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
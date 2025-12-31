<?php
session_start();
require_once '../../../config/config.php'; // Adjusted path

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

$session_emp_id = $_SESSION['employee_id'];

try {
    $stmtNotif = $pdo->prepare("SELECT * FROM notifications WHERE employee_id = ? ORDER BY created_at DESC");
    $stmtNotif->execute([$session_emp_id]);
    $notifications = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);

    $stmtUser = $pdo->prepare("SELECT * FROM employee WHERE employee_id = ?");
    $stmtUser->execute([$session_emp_id]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $profilePic = !empty($user['profile_pic']) ? $user['profile_pic'] : '/HRIS/assets/images/default_user.png';

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - HRMS</title>
    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* Keep all your existing CSS styles */
        .notif-grid { display: flex; flex-direction: column; gap: 15px; }
        .notif-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: 0.2s;
        }
        .notif-card.unread { border-left: 4px solid #2563eb; background: #f8fafc; }
        .notif-icon {
            width: 45px; height: 45px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
        }
        .icon-login { background: #eff6ff; color: #2563eb; }
        .icon-leave { background: #fff7ed; color: #ea580c; }
        .icon-approval { background: #f0fdf4; color: #16a34a; }
        
        .notif-body { flex-grow: 1; }
        .notif-title { font-weight: 700; color: #1e293b; margin-bottom: 2px; display: block; }
        .notif-text { color: #64748b; font-size: 0.9rem; }
        .notif-time { font-size: 0.75rem; color: #94a3b8; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- This will be replaced by your regular sidebar -->
        <div id="sidebar-placeholder"></div>

        <main class="main-content">
            <div id="topbar-placeholder"></div>

            <div class="dashboard-wrapper">
                <div class="welcome-header">
                    <div class="welcome-text">
                        <h1>Messages & Notifications</h1>
                        <p>Track your login activity, leave requests, and balance updates.</p>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    <div class="feed-container">
                        <div class="content-card">
                            <div class="card-header">
                                <h2><i class="fa-solid fa-bell me-2"></i>Recent Notifications</h2>
                            </div>
                            <div class="p-4">
                                <div class="notif-grid">
                                    <?php if (empty($notifications)): ?>
                                        <p class="text-center text-muted">No messages found.</p>
                                    <?php else: ?>
                                        <?php foreach ($notifications as $n): ?>
                                            <?php 
                                                $icon = 'fa-bell'; $class = 'icon-login';
                                                if($n['type'] == 'Login') { $icon = 'fa-shield-halved'; $class='icon-login'; }
                                                if($n['type'] == 'Leave Application') { $icon = 'fa-file-signature'; $class='icon-leave'; }
                                                if($n['type'] == 'Leave Approval') { $icon = 'fa-circle-check'; $class='icon-approval'; }
                                            ?>
                                            <div class="notif-card <?= $n['is_read'] ? '' : 'unread' ?>">
                                                <div class="notif-icon <?= $class ?>"><i class="fa-solid <?= $icon ?>"></i></div>
                                                <div class="notif-body">
                                                    <span class="notif-title"><?= htmlspecialchars($n['title']) ?></span>
                                                    <div class="notif-text"><?= htmlspecialchars($n['message']) ?></div>
                                                    <div class="notif-time"><?= date('M d, Y h:i A', strtotime($n['created_at'])) ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="side-info-container">
                        <div class="content-card">
                            <div class="card-header"><h2>Quick Actions</h2></div>
                            <div class="p-3">
                                <button class="btn-dl w-100 mb-2"><i class="fa-solid fa-check-double"></i> Mark All as Read</button>
                                <div class="security-note">
                                    <small class="text-muted">Notifications are kept for 30 days to track your account security.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>

    <!-- Add this JavaScript BEFORE the closing </body> tag -->
    <!-- <script>
    async function includeHTML(id, file) {
        const element = document.getElementById(id);
        if (!element) return;

        try {
            const response = await fetch(file);
            element.innerHTML = response.ok ? await response.text() : "Error loading " + file;
        } catch (err) {
            console.error("Fetch error:", err);
        }
    }

    // Initialize the sidebar/topbar/rightbar
    async function initMessagesPage() {
        // IMPORTANT: Adjust these paths for the regular employee folder
        await includeHTML('sidebar-placeholder', '../backend/sidebar.php');
        await includeHTML('topbar-placeholder', '../backend/topbar.php');
        await includeHTML('rightbar-placeholder', '../backend/rightbar.php');
    }

    // Call initialization
    document.addEventListener('DOMContentLoaded', initMessagesPage);
    </script> -->
    
    <!-- <script src="/HRIS/assets/js/script.js"></script> -->
</body>
</html>
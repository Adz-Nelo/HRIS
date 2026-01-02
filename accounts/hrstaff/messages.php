<?php
session_start();
require_once '../../config/config.php'; 

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.php");
    exit();
}

$session_emp_id = $_SESSION['employee_id'];

// --- MARK ALL AS READ FUNCTIONALITY ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE employee_id = ? AND is_read = 0");
        $stmt->execute([$session_emp_id]);
        
        // Send success response
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
        exit();
    } catch (PDOException $e) {
        error_log("Error marking all as read: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to update notifications']);
        exit();
    }
}

// --- FETCH NOTIFICATIONS ---
try {
    $stmtNotif = $pdo->prepare("SELECT * FROM notifications WHERE employee_id = ? ORDER BY created_at DESC");
    $stmtNotif->execute([$session_emp_id]);
    $notifications = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);

    // Fetch user info for sidebar
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
        /* Icon Colors */
        .icon-login { background: #eff6ff; color: #2563eb; }
        .icon-leave { background: #fff7ed; color: #ea580c; }
        .icon-approval { background: #f0fdf4; color: #16a34a; }
        .icon-balance { background: #f5f3ff; color: #7c3aed; }
        
        .notif-body { flex-grow: 1; }
        .notif-title { font-weight: 700; color: #1e293b; margin-bottom: 2px; display: block; }
        .notif-text { color: #64748b; font-size: 0.9rem; }
        .notif-time { font-size: 0.75rem; color: #94a3b8; margin-top: 5px; }

        #markAllReadBtn {
            background-color:rgba(37, 100, 235, 0.93);
            border: transparent;
            padding: 8px;
            border-radius: 5px;
            color: #FFF;
            cursor: pointer;
        }

        #markAllReadBtn:hover {
            background-color:rgb(41, 94, 210);
            border: transparent;
        }
        
        /* Toast notification styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .toast {
            background: #10b981;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideIn 0.3s ease;
            min-width: 250px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .toast.error { background: #ef4444; }
        .toast.info { background: #3b82f6; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
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
                        <h1>Messages & Notifications</h1>
                        <p>Track your login activity, leave requests, and balance updates.</p>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    <div class="feed-container">
                        <div class="content-card">
                            <div class="card-header">
                                <h2><i class="fa-solid fa-bell me-2"></i> Recent Notifications</h2>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary" id="unreadCount">0</span>
                                    <span class="text-muted">unread</span>
                                </div>
                            </div>
                            <div class="p-4">
                                <div class="notif-grid" id="notificationsContainer">
                                    <?php if (empty($notifications)): ?>
                                        <p class="text-center text-muted">No messages found.</p>
                                    <?php else: ?>
                                        <?php 
                                        $unreadCount = 0;
                                        foreach ($notifications as $n): 
                                            if (!$n['is_read']) $unreadCount++;
                                        ?>
                                            <?php 
                                                $icon = 'fa-bell'; 
                                                $class = 'icon-login';
                                                if($n['type'] == 'Login') { 
                                                    $icon = 'fa-shield-halved'; 
                                                    $class='icon-login'; 
                                                } else if($n['type'] == 'Leave Application') { 
                                                    $icon = 'fa-file-signature'; 
                                                    $class='icon-leave'; 
                                                } else if($n['type'] == 'Leave Approval') { 
                                                    $icon = 'fa-circle-check'; 
                                                    $class='icon-approval'; 
                                                } else if($n['type'] == 'Balance Update') { 
                                                    $icon = 'fa-coins'; 
                                                    $class='icon-balance'; 
                                                }
                                            ?>
                                            <div class="notif-card <?= $n['is_read'] ? '' : 'unread' ?>" data-notification-id="<?= $n['notification_id'] ?>">
                                                <div class="notif-icon <?= $class ?>"><i class="fa-solid <?= $icon ?>"></i></div>
                                                <div class="notif-body">
                                                    <span class="notif-title"><?= htmlspecialchars($n['title']) ?></span>
                                                    <div class="notif-text"><?= htmlspecialchars($n['message']) ?></div>
                                                    <div class="notif-time"><?= date('M d, Y h:i A', strtotime($n['created_at'])) ?></div>
                                                </div>
                                                <?php if (!$n['is_read']): ?>
                                                    <span class="badge bg-primary badge-sm ms-2">New</span>
                                                <?php endif; ?>
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
                                <button class="btn-dl w-100 mb-2" id="markAllReadBtn">
                                    <i class="fa-solid fa-check-double"></i> Mark All as Read
                                </button>
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
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        const notificationsContainer = document.getElementById('notificationsContainer');
        const unreadCountElement = document.getElementById('unreadCount');
        
        // Count initial unread notifications
        updateUnreadCount();
        
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to mark all notifications as read?')) {
                    // Disable button and show loading state
                    const originalText = markAllReadBtn.innerHTML;
                    markAllReadBtn.disabled = true;
                    markAllReadBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
                    
                    // Send AJAX request
                    const formData = new FormData();
                    formData.append('mark_all_read', 'true');
                    
                    fetch('messages.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            showToast(data.message, 'success');
                            
                            // Remove 'unread' class and badges from all notification cards
                            document.querySelectorAll('.notif-card.unread').forEach(card => {
                                card.classList.remove('unread');
                                const badge = card.querySelector('.badge');
                                if (badge) badge.remove();
                            });
                            
                            // Update unread count
                            unreadCountElement.textContent = '0';
                            
                            // Update button text temporarily
                            markAllReadBtn.innerHTML = '<i class="fa-solid fa-check"></i> All Read';
                            setTimeout(() => {
                                markAllReadBtn.innerHTML = originalText;
                                markAllReadBtn.disabled = false;
                            }, 2000);
                            
                            // Update notification count in bell icon if exists
                            updateNotificationBell(0);
                            
                        } else {
                            showToast(data.message || 'Failed to mark as read', 'error');
                            markAllReadBtn.innerHTML = originalText;
                            markAllReadBtn.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('An error occurred. Please try again.', 'error');
                        markAllReadBtn.innerHTML = originalText;
                        markAllReadBtn.disabled = false;
                    });
                }
            });
        }
        
        // Function to update unread count
        function updateUnreadCount() {
            const unreadCards = document.querySelectorAll('.notif-card.unread');
            const count = unreadCards.length;
            unreadCountElement.textContent = count;
        }
        
        // Function to update notification bell in topbar
        function updateNotificationBell(count) {
            // Try to update any notification badge in the topbar
            const notificationBadges = document.querySelectorAll('.notification-badge, .badge.bg-danger');
            notificationBadges.forEach(badge => {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            });
            
            // Update page title if it contains notification count
            const title = document.title;
            const newTitle = title.replace(/^\(\d+\)\s*/, '');
            document.title = count > 0 ? `(${count}) ${newTitle}` : newTitle;
        }
        
        // Function to show toast messages
        function showToast(message, type = 'success') {
            // Create toast container if it doesn't exist
            let toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.className = 'toast-container';
                document.body.appendChild(toastContainer);
            }
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast ${type === 'error' ? 'error' : type === 'info' ? 'info' : ''}`;
            toast.innerHTML = `
                <i class="fa-solid ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
                <span>${message}</span>
            `;
            
            toastContainer.appendChild(toast);
            
            // Remove toast after 3 seconds
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // Optional: Mark individual notification as read when clicked
        notificationsContainer.addEventListener('click', function(e) {
            const notifCard = e.target.closest('.notif-card');
            if (notifCard && notifCard.classList.contains('unread')) {
                const notificationId = notifCard.dataset.notificationId;
                if (notificationId) {
                    // Send AJAX to mark this notification as read
                    fetch('/HRIS/includes/mark_notification_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `notification_id=${notificationId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            notifCard.classList.remove('unread');
                            const badge = notifCard.querySelector('.badge');
                            if (badge) badge.remove();
                            updateUnreadCount();
                            updateNotificationBell(parseInt(unreadCountElement.textContent) - 1);
                        }
                    });
                }
            }
        });
    });
    </script>
    
    <script src="/HRIS/assets/js/script.js"></script>
</body>
</html>
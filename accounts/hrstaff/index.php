<?php
// hrstaff/index.php
session_start();
require_once '../../config/config.php';

// Get notification count for the HR staff
$notificationCount = 0;
$notifications = [];

try {
    // Get unread notifications for this HR user
    $stmt = $pdo->prepare("
        SELECT n.*, e.first_name, e.last_name, e.department_id 
        FROM notifications n
        LEFT JOIN employee e ON n.employee_id = e.employee_id
        WHERE n.employee_id = ? AND n.is_read = 0
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['employee_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $notificationCount = count($notifications);
} catch (PDOException $e) {
    error_log("Notification fetch error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>HR Staff Dashboard</title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">
    <link rel="stylesheet" href="/HRIS/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .notification-bell {
            position: relative;
            cursor: pointer;
            font-size: 1.5rem;
            color: #667eea;
        }
        
        .notification-bell .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
            display: none;
        }
        
        .notification-dropdown {
            display: none;
            position: absolute;
            top: 50px;
            right: 20px;
            width: 400px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 1000;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: flex-start;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item.empty {
            text-align: center;
            color: #6b7280;
            padding: 30px;
        }
        
        .notification-icon {
            margin-right: 10px;
            color: #667eea;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-content strong {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        
        .notification-content p {
            margin: 5px 0;
            color: #666;
            white-space: pre-line;
        }
        
        .notification-content small {
            display: block;
            color: #888;
            font-size: 0.8rem;
        }
        
        .mark-read-btn {
            background: none;
            border: none;
            color: #10b981;
            cursor: pointer;
            padding: 5px;
        }
        
        .notification-footer {
            padding: 10px;
            text-align: center;
            border-top: 1px solid #eee;
        }
    </style>
</head>

<body>
    <!-- EMPLOYEE TOPBAR / SIDEBAR / RIGHTBAR -->
    <div id="topbar-placeholder"></div>
    <div id="sidebar-placeholder"></div>
    <div id="rightbar-placeholder"></div>

    <!-- NOTIFICATION BELL -->
    <div class="notification-bell" onclick="toggleNotifications()">
        <i class="fas fa-bell"></i>
        <?php if ($notificationCount > 0): ?>
            <span class="badge"><?= $notificationCount ?></span>
        <?php endif; ?>
    </div>

    <!-- NOTIFICATION DROPDOWN -->
    <div class="notification-dropdown" id="notificationDropdown">
        <?php if (empty($notifications)): ?>
            <div class="notification-item empty">
                <i class="fas fa-check-circle fa-2x mb-3"></i>
                <span>No new notifications</span>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-item" data-id="<?= $notification['notification_id'] ?>">
                    <div class="notification-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="notification-content">
                        <strong><?= htmlspecialchars($notification['title']) ?></strong>
                        <p><?= nl2br(htmlspecialchars($notification['message'])) ?></p>
                        <small>
                            <?php if ($notification['first_name']): ?>
                                From: <?= htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name']) ?>
                            <?php endif; ?>
                            <?php if ($notification['department_id']): ?>
                                | Dept: <?= htmlspecialchars($notification['department_id']) ?>
                            <?php endif; ?>
                        </small>
                        <small class="text-muted">
                            <?= date('M d, Y h:i A', strtotime($notification['created_at'])) ?>
                        </small>
                    </div>
                    <button class="mark-read-btn" onclick="markNotificationRead(<?= $notification['notification_id'] ?>)">
                        <i class="fas fa-check"></i>
                    </button>
                </div>
            <?php endforeach; ?>
            <div class="notification-footer">
                <a href="/HRIS/accounts/hrstaff/notifications.php">View All Notifications</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- EMPLOYEE MAIN CONTENT -->
    <main class="main-content" id="main-content">
        <div class="content-container" id="contents-placeholder"></div>
    </main>

    <script>
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

        async function init() {
            await includeHTML('topbar-placeholder', 'backend/topbar.php');
            await includeHTML('sidebar-placeholder', 'backend/sidebar.php');
            await includeHTML('rightbar-placeholder', 'backend/rightbar.php');
            await includeHTML('contents-placeholder', 'backend/main.php');
        }

        init();
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notificationDropdown');
            const bell = document.querySelector('.notification-bell');
            
            if (!bell.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.style.display = 'none';
            }
        });
    </script>

    <!-- REAL-TIME CLOCK -->
    <script>
        (function() {
            function updateClock() {
                const timeEl = document.getElementById('real-time');
                const dateEl = document.getElementById('real-date');
                if (!timeEl || !dateEl) return;

                const now = new Date();
                let h = now.getHours();
                const m = String(now.getMinutes()).padStart(2, '0');
                const s = String(now.getSeconds()).padStart(2, '0');
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;

                timeEl.textContent = `${h}:${m}:${s} ${ampm}`;
                dateEl.textContent = now.toLocaleDateString('en-US', {
                    weekday: 'long',
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
            }

            if (window.clockInterval) clearInterval(window.clockInterval);
            window.clockInterval = setInterval(updateClock, 1000);
            updateClock();
        })();
    </script>

    <script>
        // CSRF token (you need to pass this from PHP)
        const csrfToken = "<?php echo $_SESSION['csrf_token'] ?? ''; ?>";
        
        // Toggle notification dropdown
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }

        // Mark notification as read
        function markNotificationRead(notificationId) {
            fetch('/HRIS/includes/mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    notification_id: notificationId,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove notification from UI
                    const notificationElement = document.querySelector(`[data-id="${notificationId}"]`);
                    if (notificationElement) {
                        notificationElement.remove();
                    }

                    // Update badge count
                    updateNotificationBadge();
                    
                    // If no notifications left, show empty state
                    const items = document.querySelectorAll('.notification-item[data-id]');
                    if (items.length === 0) {
                        const dropdown = document.getElementById('notificationDropdown');
                        dropdown.innerHTML = `
                            <div class="notification-item empty">
                                <i class="fas fa-check-circle fa-2x mb-3"></i>
                                <span>No new notifications</span>
                            </div>
                        `;
                    }
                }
            });
        }

        // Update notification badge count
        function updateNotificationBadge() {
            fetch('/HRIS/includes/get_notification_count.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.querySelector('.notification-bell .badge');
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                });
        }

        // Auto-refresh notifications every 30 seconds
        setInterval(updateNotificationBadge, 30000);
    </script>

</body>
</html>
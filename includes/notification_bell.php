<?php
// includes/notification_bell.php
require_once 'notification_helper.php';

function renderNotificationBell($employee_id) {
    $notifications = getUnreadNotifications($employee_id);
    $unread_count = count($notifications);
    
    ob_start();
    ?>
    <div class="notification-wrapper">
        <div class="dropdown">
            <button class="btn btn-light position-relative" type="button" id="notificationDropdown" 
                    data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-bell fs-5"></i>
                <?php if ($unread_count > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    <?php echo $unread_count; ?>
                </span>
                <?php endif; ?>
            </button>
            
            <div class="dropdown-menu dropdown-menu-end p-0" style="width: 350px; max-height: 400px; overflow-y: auto;">
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                    <h6 class="mb-0">Notifications</h6>
                    <?php if ($unread_count > 0): ?>
                    <button class="btn btn-sm btn-link text-decoration-none" 
                            onclick="markAllAsRead(<?php echo $employee_id; ?>)">
                        Mark all as read
                    </button>
                    <?php endif; ?>
                </div>
                
                <div class="notification-list">
                    <?php if (empty($notifications)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-bell-slash fs-1"></i>
                        <p class="mt-2">No new notifications</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item border-bottom p-3 <?php echo !$notification['is_read'] ? 'bg-light' : ''; ?>" 
                             data-id="<?php echo $notification['notification_id']; ?>"
                             onclick="markAsRead(<?php echo $notification['notification_id']; ?>, <?php echo $employee_id; ?>)">
                            <div class="d-flex">
                                <div class="me-3">
                                    <?php 
                                    $icons = [
                                        'Leave Application' => 'bi-calendar-event',
                                        'Leave Approval' => 'bi-check-circle',
                                        'Balance Update' => 'bi-cash-coin',
                                        'Login' => 'bi-box-arrow-in-right'
                                    ];
                                    $icon = $icons[$notification['type']] ?? 'bi-bell';
                                    ?>
                                    <i class="bi <?php echo $icon; ?> text-primary fs-4"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                    <p class="mb-1 small text-muted"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <small class="text-muted">
                                        <?php echo time_ago($notification['created_at']); ?>
                                    </small>
                                </div>
                                <?php if (!$notification['is_read']): ?>
                                <span class="badge bg-primary rounded-pill">New</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="border-top p-2 text-center">
                    <a href="notifications.php" class="text-decoration-none small">View all notifications</a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function markAsRead(notificationId, employeeId) {
        fetch('../../includes/mark_notification_read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                notification_id: notificationId,
                employee_id: employeeId
            })
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  location.reload();
              }
          });
    }
    
    function markAllAsRead(employeeId) {
        fetch('../../includes/mark_all_read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({employee_id: employeeId})
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  location.reload();
              }
          });
    }
    
    // Auto-refresh notifications every 30 seconds
    setInterval(() => {
        const bell = document.querySelector('.notification-wrapper');
        if (bell) {
            // Refresh only if dropdown is not open
            const dropdown = document.getElementById('notificationDropdown');
            if (!dropdown.classList.contains('show')) {
                // Just update the count via AJAX
                fetch(`../../includes/get_notification_count.php?employee_id=${<?php echo $employee_id; ?>}`)
                    .then(response => response.json())
                    .then(data => {
                        const badge = document.querySelector('.notification-wrapper .badge');
                        if (data.count > 0) {
                            if (badge) {
                                badge.textContent = data.count;
                            } else {
                                // Add badge if it doesn't exist
                                const bellIcon = document.querySelector('.notification-wrapper .bi-bell');
                                if (bellIcon) {
                                    const newBadge = document.createElement('span');
                                    newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                                    newBadge.textContent = data.count;
                                    bellIcon.parentNode.appendChild(newBadge);
                                }
                            }
                        } else if (badge) {
                            badge.remove();
                        }
                    });
            }
        }
    }, 30000);
    </script>
    <?php
    return ob_get_clean();
}

// Helper function for time display
function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return "Just now";
    if ($diff < 3600) return floor($diff/60) . " min ago";
    if ($diff < 86400) return floor($diff/3600) . " hours ago";
    if ($diff < 604800) return floor($diff/86400) . " days ago";
    return date("M d, Y", $time);
}
?>
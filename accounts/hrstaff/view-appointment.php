<?php
session_start();
require_once '../../config/config.php';

// ✅ FIX 1: Access Control (Updated to role_name and standardized)
$allowed_roles = ['Admin', 'HR Officer', 'HR Staff'];
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'], $allowed_roles)) {
    header("Location: ../../login.html");
    exit();
}

// ✅ FIX 2: Heartbeat Update
try {
    $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?")
        ->execute([$_SESSION['employee_id']]);
} catch (PDOException $e) {
    /* silent fail */
}

// Handle appointment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $appointment_id = $_POST['appointment_id'] ?? null;
    $notes = $_POST['notes'] ?? '';

    // Validate action
    $valid_actions = ['confirm', 'decline', 'cancel', 'reschedule'];
    if (!in_array($action, $valid_actions) || !$appointment_id) {
        $_SESSION['error'] = "Invalid action or missing appointment ID.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    try {
        $new_status = '';
        switch ($action) {
            case 'confirm':
                $new_status = 'Confirmed';
                break;
            case 'decline':
                $new_status = 'Declined';
                break;
            case 'cancel':
                $new_status = 'Cancelled';
                break;
            // For reschedule, you might want to redirect to a different page
            case 'reschedule':
                $_SESSION['reschedule_id'] = $appointment_id;
                header("Location: reschedule-appointment.php?id=" . $appointment_id);
                exit();
        }

        if ($new_status) {
            // Update appointment status
            $stmt = $pdo->prepare("UPDATE appointments SET status = ?, notes = CONCAT(IFNULL(notes, ''), ?), updated_at = NOW() WHERE appointment_id = ?");
            $additional_notes = "\n\n[" . date('Y-m-d H:i:s') . "] Status changed to: " . $new_status . " by HR Staff.";
            if (!empty($notes)) {
                $additional_notes .= "\nNote: " . $notes;
            }
            $stmt->execute([$new_status, $additional_notes, $appointment_id]);

            // Create notification for employee
            $stmt = $pdo->prepare("SELECT employee_id FROM appointments WHERE appointment_id = ?");
            $stmt->execute([$appointment_id]);
            $appointment = $stmt->fetch();

            if ($appointment) {
                $notification_title = "Appointment " . $new_status;
                $notification_message = "Your appointment has been " . strtolower($new_status) . " by HR.";

                $notif_stmt = $pdo->prepare("INSERT INTO notifications (employee_id, title, message, type, created_at) VALUES (?, ?, ?, 'Leave Application', NOW())");
                $notif_stmt->execute([$appointment['employee_id'], $notification_title, $notification_message]);
            }

            $_SESSION['success'] = "Appointment has been " . strtolower($new_status) . " successfully.";
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (PDOException $e) {
        error_log("Appointment Action Error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to update appointment. Please try again.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// --- FETCH APPOINTMENTS WITH FILTERS ---
$status_filter = $_GET['status'] ?? '';
$search_query = $_GET['search'] ?? '';

// Build query with filters
$query = "SELECT a.*, 
                 e.first_name, e.last_name, e.middle_name, e.profile_pic,
                 d.department_name
          FROM appointments a
          JOIN employee e ON a.employee_id = e.employee_id
          LEFT JOIN department d ON e.department_id = d.department_id
          WHERE 1=1";

$params = [];

if ($status_filter) {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
}

if ($search_query) {
    $query .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.middle_name LIKE ?)";
    $search_term = "%" . $search_query . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get appointment statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'Confirmed' AND DATE(appointment_date) = CURDATE() THEN 1 ELSE 0 END) as confirmed_today,
            SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointments
    ");
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    // Get today's capacity
    $capacity_stmt = $pdo->prepare("
        SELECT COUNT(*) as today_count 
        FROM appointments 
        WHERE status = 'Confirmed' 
        AND DATE(appointment_date) = CURDATE()
    ");
    $capacity_stmt->execute();
    $capacity = $capacity_stmt->fetch(PDO::FETCH_ASSOC);
    $today_count = $capacity['today_count'] ?? 0;
    $daily_limit = 15;
    $capacity_percentage = ($today_count / $daily_limit) * 100;
} catch (PDOException $e) {
    error_log("Fetch Error: " . $e->getMessage());
    $appointments = [];
    $stats = ['total' => 0, 'pending' => 0, 'confirmed_today' => 0, 'cancelled' => 0];
    $today_count = 0;
    $capacity_percentage = 0;
}

// Display success/error messages
$success_msg = $_SESSION['success'] ?? '';
$error_msg = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - HRMS</title>
    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">
    <link rel="stylesheet" href="/HRIS/assets/css/style.css">
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        .dashboard-wrapper {
            gap: 15px !important;
        }

        /* Filter Bar */
        .db-filter-bar {
            padding: 12px 25px;
            background: #fbfcfd;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .db-filter-bar input,
        .db-filter-bar select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            font-size: 13px;
        }

        /* Employee Info Cells */
        .emp-info-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .emp-profile-img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #e2e8f0;
        }

        /* Status Styling */
        .status-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-confirmed {
            background: #dcfce7;
            color: #166534;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-declined {
            background: #f3f4f6;
            color: #374151;
        }

        /* Action Buttons */
        .btn-action {
            padding: 6px 10px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-confirm {
            background: #166534;
            color: white;
        }

        .btn-confirm:hover {
            background: #15803d;
        }

        .btn-decline {
            background: #991b1b;
            color: white;
        }

        .btn-decline:hover {
            background: #b91c1c;
        }

        .btn-cancel {
            background: #dc2626;
            color: white;
        }

        .btn-cancel:hover {
            background: #b91c1c;
        }

        .icon-btn {
            background: none;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px 10px;
            cursor: pointer;
            color: #64748b;
            transition: all 0.2s;
        }

        .icon-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            margin: 0;
            color: #1e293b;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #64748b;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #334155;
        }

        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            font-size: 14px;
            resize: vertical;
            min-height: 80px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        /* Toast Messages */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 1001;
            animation: slideIn 0.3s ease;
        }

        .toast.success {
            background: #10b981;
        }

        .toast.error {
            background: #ef4444;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Notes popover */
        .notes-popover {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            z-index: 100;
            max-width: 300px;
            font-size: 12px;
            color: #64748b;
        }

        .capacity-bar {
            background: #f1f5f9;
            height: 8px;
            border-radius: 10px;
            margin-bottom: 5px;
            overflow: hidden;
        }

        .capacity-fill {
            background: #3b82f6;
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
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
                        <h1>Appointment Requests</h1>
                        <p>Review and schedule meetings requested by employees.</p>
                    </div>
                    <div class="date-time-widget">
                        <div class="time" id="real-time">--:--:-- --</div>
                        <div class="date" id="real-date">Loading...</div>
                    </div>
                </div>

                <!-- Toast Messages -->
                <?php if ($success_msg): ?>
                    <div class="toast success" id="success-toast">
                        <i class="fa-solid fa-check-circle me-2"></i><?= htmlspecialchars($success_msg) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="toast error" id="error-toast">
                        <i class="fa-solid fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_msg) ?>
                    </div>
                <?php endif; ?>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-envelope-open-text"></i></div>
                        <div class="stat-info">
                            <h3><?= $stats['pending'] ?? 0 ?></h3>
                            <p>New Requests</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-calendar-check"></i></div>
                        <div class="stat-info">
                            <h3><?= $stats['confirmed_today'] ?? 0 ?></h3>
                            <p>Confirmed Today</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-calendar-xmark"></i></div>
                        <div class="stat-info">
                            <h3><?= $stats['cancelled'] ?? 0 ?></h3>
                            <p>Cancelled</p>
                        </div>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    <div class="content-card table-card">
                        <div class="report-log-header">
                            <h2>Master Appointment List</h2>
                        </div>

                        <form method="GET" class="db-filter-bar">
                            <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search employee..." style="flex: 2;">
                            <select name="status" style="flex: 1;">
                                <option value="">All Status</option>
                                <option value="Pending" <?= $status_filter == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Confirmed" <?= $status_filter == 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                <option value="Cancelled" <?= $status_filter == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                <option value="Declined" <?= $status_filter == 'Declined' ? 'selected' : '' ?>>Declined</option>
                            </select>
                            <button type="submit" class="btn-primary" style="padding: 8px 16px;">
                                <i class="fa-solid fa-filter"></i> Filter
                            </button>
                        </form>

                        <div class="table-container">
                            <?php if (empty($appointments)): ?>
                                <div style="text-align: center; padding: 40px 20px; color: #64748b;">
                                    <i class="fa-solid fa-calendar-xmark" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 15px;"></i>
                                    <h3 style="margin-bottom: 10px;">No Appointments Found</h3>
                                    <p>No appointments match your search criteria.</p>
                                </div>
                            <?php else: ?>
                                <table class="ledger-table">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Date & Time</th>
                                            <th>Purpose</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments as $appt):
                                            $full_name = $appt['last_name'] . ', ' . $appt['first_name'];
                                            if (!empty($appt['middle_name'])) {
                                                $full_name .= ' ' . substr($appt['middle_name'], 0, 1) . '.';
                                            }

                                            $profile_pic = !empty($appt['profile_pic']) ? $appt['profile_pic'] : '/HRIS/assets/images/default_user.png';
                                            $status_class = 'status-' . strtolower($appt['status']);
                                            $formatted_date = date('M d, Y', strtotime($appt['appointment_date']));
                                            $formatted_time = date('h:i A', strtotime($appt['appointment_time']));
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="emp-info-cell">
                                                        <img src="<?= $profile_pic ?>" class="emp-profile-img" onerror="this.src='/HRIS/assets/images/default_user.png'">
                                                        <div>
                                                            <div style="font-weight: 600;"><?= htmlspecialchars($full_name) ?></div>
                                                            <small style="color:#64748b;"><?= htmlspecialchars($appt['department_name'] ?? 'N/A') ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style="font-weight: 500;"><?= $formatted_date ?></div>
                                                    <small style="color: #3b82f6; font-weight: 700;"><?= $formatted_time ?></small>
                                                </td>
                                                <td>
                                                    <span style="font-size: 13px;"><?= htmlspecialchars($appt['purpose']) ?></span>
                                                    <?php if (!empty($appt['notes'])): ?>
                                                        <i class="fa-solid fa-circle-info" style="color: #94a3b8; margin-left: 5px; cursor: pointer;"
                                                            onclick="showNotes('<?= addslashes($appt['notes']) ?>')"
                                                            title="View Notes"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="status-badge <?= $status_class ?>"><?= $appt['status'] ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <div style="display: flex; gap: 5px; justify-content: center;">
                                                        <?php if ($appt['status'] == 'Pending'): ?>
                                                            <button class="btn-action btn-confirm" onclick="openActionModal('confirm', <?= $appt['appointment_id'] ?>)">
                                                                Confirm
                                                            </button>
                                                            <button class="btn-action btn-decline" onclick="openActionModal('decline', <?= $appt['appointment_id'] ?>)">
                                                                Decline
                                                            </button>
                                                        <?php elseif ($appt['status'] == 'Confirmed'): ?>
                                                            <button class="btn-action btn-cancel" onclick="openActionModal('cancel', <?= $appt['appointment_id'] ?>)">
                                                                Cancel
                                                            </button>
                                                            <button class="icon-btn" title="View Details" onclick="viewDetails(<?= $appt['appointment_id'] ?>)">
                                                                <i class="fa-solid fa-eye"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="icon-btn" title="View Details" onclick="viewDetails(<?= $appt['appointment_id'] ?>)">
                                                                <i class="fa-solid fa-eye"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="content-card side-info-card">
                        <div class="card-header" style="margin-bottom: 15px;">
                            <h2>HR Daily Capacity</h2>
                        </div>
                        <p style="font-size: 12px; color: #64748b; margin-bottom: 15px;">
                            Daily limit: <?= $daily_limit ?> appointments.
                        </p>

                        <div class="capacity-bar">
                            <div class="capacity-fill" style="width: <?= min($capacity_percentage, 100) ?>%;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 11px; font-weight: 600;">
                            <span><?= round($capacity_percentage) ?>% Capacity Filled</span>
                            <span><?= $today_count ?>/<?= $daily_limit ?></span>
                        </div>

                        <hr style="margin: 20px 0; border: none; border-top: 1px solid #f1f5f9;">

                        <div class="info-item">
                            <strong style="font-size: 12px; display: block; margin-bottom: 8px;">Quick Reminder</strong>
                            <p style="font-size: 12px; color: #64748b; line-height: 1.5;">
                                When declining or cancelling an appointment, please provide a reason so the employee can reschedule properly.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <div id="rightbar-placeholder"></div>
    </div>

    <!-- Action Modal -->
    <div class="modal" id="actionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Confirm Appointment</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form id="actionForm" method="POST">
                <input type="hidden" name="action" id="actionType">
                <input type="hidden" name="appointment_id" id="appointmentId">

                <div class="form-group">
                    <label for="notes">Notes (Optional):</label>
                    <textarea name="notes" id="notes" placeholder="Add any additional notes or instructions..."></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="icon-btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-action" id="submitBtn">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Notes Popover -->
    <div class="notes-popover" id="notesPopover"></div>

    <script src="/HRIS/assets/js/script.js"></script>
    <script>
        // Toast messages auto-hide
        setTimeout(() => {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            });
        }, 4000);

        // Modal functions
        function openActionModal(action, appointmentId) {
            const modal = document.getElementById('actionModal');
            const modalTitle = document.getElementById('modalTitle');
            const actionType = document.getElementById('actionType');
            const appointmentIdField = document.getElementById('appointmentId');
            const submitBtn = document.getElementById('submitBtn');

            // Set modal content based on action
            let title = '';
            let btnText = '';
            let btnClass = '';

            switch (action) {
                case 'confirm':
                    title = 'Confirm Appointment';
                    btnText = 'Confirm Appointment';
                    btnClass = 'btn-confirm';
                    break;
                case 'decline':
                    title = 'Decline Appointment';
                    btnText = 'Decline Appointment';
                    btnClass = 'btn-decline';
                    break;
                case 'cancel':
                    title = 'Cancel Appointment';
                    btnText = 'Cancel Appointment';
                    btnClass = 'btn-cancel';
                    break;
            }

            modalTitle.textContent = title;
            actionType.value = action;
            appointmentIdField.value = appointmentId;
            submitBtn.textContent = btnText;
            submitBtn.className = 'btn-action ' + btnClass;

            modal.style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('actionModal').style.display = 'none';
            document.getElementById('notes').value = '';
        }

        // Show notes in popover
        function showNotes(notes) {
            const popover = document.getElementById('notesPopover');
            popover.innerHTML = '<strong>Notes:</strong><br>' + notes.replace(/\n/g, '<br>');
            popover.style.display = 'block';

            // Position near cursor
            const x = event.clientX;
            const y = event.clientY;
            popover.style.left = (x + 10) + 'px';
            popover.style.top = (y + 10) + 'px';

            // Hide on click elsewhere
            setTimeout(() => {
                document.addEventListener('click', function hidePopover(e) {
                    if (!popover.contains(e.target)) {
                        popover.style.display = 'none';
                        document.removeEventListener('click', hidePopover);
                    }
                });
            }, 100);
        }

        // View details function (you can expand this)
        function viewDetails(appointmentId) {
            // You can redirect to a details page or show a modal
            window.location.href = 'appointment-details.php?id=' + appointmentId;
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('actionModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Replace the current updateDateTime() function with this improved version:

        // Real-time clock
        function updateDateTime() {
            const now = new Date();
            const timeElement = document.getElementById('real-time');
            const dateElement = document.getElementById('real-date');

            if (timeElement) {
                // Format time with leading zeros
                let hours = now.getHours();
                let minutes = now.getMinutes();
                let seconds = now.getSeconds();
                const ampm = hours >= 12 ? 'PM' : 'AM';

                // Convert to 12-hour format
                hours = hours % 12;
                hours = hours ? hours : 12; // 0 should be 12

                // Add leading zeros
                hours = hours < 10 ? '0' + hours : hours;
                minutes = minutes < 10 ? '0' + minutes : minutes;
                seconds = seconds < 10 ? '0' + seconds : seconds;

                timeElement.textContent = `${hours}:${minutes}:${seconds} ${ampm}`;
            }

            if (dateElement) {
                // Cache the date string to avoid flickering
                const today = now.toDateString();

                // Check if date has changed since last update
                if (typeof dateElement.lastDate !== 'undefined' && dateElement.lastDate === today) {
                    return; // Date hasn't changed, skip update
                }

                // Update only if date changed
                dateElement.lastDate = today;

                // Use a simpler, more stable date format
                const weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

                const weekday = weekdays[now.getDay()];
                const month = months[now.getMonth()];
                const day = now.getDate();
                const year = now.getFullYear();

                dateElement.textContent = `${weekday}, ${month} ${day}, ${year}`;
            }
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            updateDateTime();
            // Update clock every second
            setInterval(updateDateTime, 1000);
        });
    </script>
</body>

</html>
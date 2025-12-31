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

// --- FILTERS & SEARCH ---
$status_filter = $_GET['status'] ?? '';
$search_query = $_GET['search'] ?? '';

// ✅ FIX 3: Placeholder for Appointment Actions (Example Logic)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['appointment_id'])) {
    $action = $_POST['action']; // e.g., 'Approved', 'Rejected'
    $appt_id = $_POST['appointment_id'];

    try {
        $update_stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
        $update_stmt->execute([$action, $appt_id]);
        
        // Redirect to refresh and show changes
        header("Location: " . $_SERVER['PHP_SELF'] . "?msg=updated");
        exit();
    } catch (PDOException $e) {
        error_log("Update Error: " . $e->getMessage());
    }
}
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
        .dashboard-wrapper { gap: 15px !important; }
        
        /* Filter Bar Matching Retirement Style */
        .db-filter-bar {
            padding: 12px 25px;
            background: #fbfcfd;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .db-filter-bar input, .db-filter-bar select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            font-size: 13px;
        }

        /* Employee Info Cells */
        .emp-info-cell { display: flex; align-items: center; gap: 10px; }
        .emp-profile-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid #e2e8f0; }

        /* Status Styling */
        .status-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

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
        .btn-confirm { background: #166534; color: white; }
        .btn-confirm:hover { background: #15803d; }
        .btn-decline { background: #991b1b; color: white; }
        .btn-decline:hover { background: #b91c1c; }

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

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-envelope-open-text"></i></div>
                        <div class="stat-info">
                            <h3>8</h3>
                            <p>New Requests</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-calendar-check"></i></div>
                        <div class="stat-info">
                            <h3>12</h3>
                            <p>Confirmed Today</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-calendar-xmark"></i></div>
                        <div class="stat-info">
                            <h3>2</h3>
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
                            </select>
                            <button type="submit" class="btn-primary" style="padding: 8px 16px;">
                                <i class="fa-solid fa-filter"></i> Filter
                            </button>
                        </form>

                        <div class="table-container">
                            <table class="ledger-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Date & Time</th>
                                        <th>Purpose</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Management Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="emp-info-cell">
                                                <img src="../../assets/images/default_user.png" class="emp-profile-img">
                                                <div>
                                                    <div style="font-weight: 600;">Dela Cruz, Juan</div>
                                                    <small style="color:#64748b;">IT Department</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;">Oct 28, 2023</div>
                                            <small style="color: #3b82f6; font-weight: 700;">02:30 PM</small>
                                        </td>
                                        <td>
                                            <span style="font-size: 13px;">Benefit Inquiry</span>
                                            <i class="fa-solid fa-circle-info" style="color: #94a3b8; margin-left: 5px; cursor: pointer;" title="View Notes"></i>
                                        </td>
                                        <td class="text-center">
                                            <span class="status-badge status-pending">Pending</span>
                                        </td>
                                        <td class="text-center">
                                            <div style="display: flex; gap: 5px; justify-content: center;">
                                                <button class="btn-action btn-confirm">Confirm</button>
                                                <button class="btn-action btn-decline">Decline</button>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td>
                                            <div class="emp-info-cell">
                                                <img src="../../assets/images/default_user.png" class="emp-profile-img">
                                                <div>
                                                    <div style="font-weight: 600;">Santos, Maria</div>
                                                    <small style="color:#64748b;">Finance Dept</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;">Oct 27, 2023</div>
                                            <small style="color: #3b82f6; font-weight: 700;">10:00 AM</small>
                                        </td>
                                        <td><span style="font-size: 13px;">HR Consultation</span></td>
                                        <td class="text-center">
                                            <span class="status-badge status-confirmed">Confirmed</span>
                                        </td>
                                        <td class="text-center">
                                            <button class="icon-btn" title="Reschedule"><i class="fa-solid fa-clock-rotate-left"></i></button>
                                            <button class="icon-btn" title="View Details"><i class="fa-solid fa-eye"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="content-card side-info-card">
                        <div class="card-header" style="margin-bottom: 15px;">
                            <h2>HR Daily Capacity</h2>
                        </div>
                        <p style="font-size: 12px; color: #64748b; margin-bottom: 15px;">
                            Daily limit: 15 appointments.
                        </p>
                        
                        <div style="background: #f1f5f9; height: 8px; border-radius: 10px; margin-bottom: 5px;">
                            <div style="background: #3b82f6; width: 80%; height: 100%; border-radius: 10px;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 11px; font-weight: 600;">
                            <span>80% Capacity Filled</span>
                            <span>12/15</span>
                        </div>

                        <hr style="margin: 20px 0; border: none; border-top: 1px solid #f1f5f9;">

                        <div class="info-item">
                            <strong style="font-size: 12px; display: block; margin-bottom: 8px;">Quick Reminder</strong>
                            <p style="font-size: 12px; color: #64748b; line-height: 1.5;">
                                When declining an appointment, please provide a reason so the employee can reschedule properly.
                            </p>
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
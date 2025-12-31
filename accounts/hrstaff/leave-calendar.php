<?php
session_start();
require_once '../../config/config.php';

// ✅ FIX 1: Access Control (Updated to role_name)
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

/**
 * We fetch approved leave requests to display on the calendar.
 * We color code them: Vacation (Blue), Sick (Green), Others (Orange)
 */
try {
    // Note: Ensure your table name matches (leave_requests vs leave_application)
    $stmt = $pdo->prepare("
        SELECT 
            lr.leave_request_id,
            lr.leave_type,
            lr.start_date,
            lr.end_date,
            e.first_name,
            e.last_name,
            lr.status
        FROM leave_requests lr
        JOIN employee e ON lr.employee_id = e.employee_id
        WHERE lr.status = 'Approved'
    ");
    $stmt->execute();
    
    $events = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Define color scheme for FullCalendar
        $color = '#3b82f6'; // Default Blue (Vacation)
        if ($row['leave_type'] == 'Sick Leave') $color = '#10b981'; // Green
        if ($row['leave_type'] == 'Emergency Leave') $color = '#f59e0b'; // Orange

        $events[] = [
            'title' => $row['last_name'] . ' (' . $row['leave_type'] . ')',
            'start' => $row['start_date'],
            // ✅ FullCalendar FIX: end date is exclusive, so we add +1 day for proper display
            'end'   => date('Y-m-d', strtotime($row['end_date'] . ' +1 day')), 
            'backgroundColor' => $color,
            'borderColor' => $color,
            'allDay' => true,
            'extendedProps' => [
                'full_name' => $row['first_name'] . ' ' . $row['last_name']
            ]
        ];
    }
} catch (PDOException $e) {
    error_log("Calendar Fetch Error: " . $e->getMessage());
    $events = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Calendar - HRMS</title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

    <style>
        .dashboard-wrapper { 
            display: flex;
            flex-direction: column;
            gap: 15px !important; 
        }

        /* Calendar Styling */
        .calendar-card {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        #calendar {
            max-width: 100%;
            height: 700px;
        }

        /* Legend Styling */
        .legend-box {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
        }
        .legend-item { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; }
        .dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }

        /* Full Blur Overlay logic for any modals on this page */
        .modal-overlay { 
            display: none; 
            position: fixed; 
            top: 0; left: 0; width: 100vw; height: 100vh; 
            background: rgba(15, 23, 42, 0.5); 
            backdrop-filter: blur(8px); 
            -webkit-backdrop-filter: blur(8px);
            z-index: 99999; 
        }

        /* FullCalendar Custom Overrides */
        .fc-toolbar-title { font-size: 1.2rem !important; font-weight: 700; color: #1e293b; }
        .fc-button-primary { background-color: #3b82f6 !important; border-color: #3b82f6 !important; }
        .fc-event { cursor: pointer; padding: 2px 4px; border-radius: 4px; font-size: 11px; }
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
                        <h1>Leave Calendar</h1>
                        <p>Visual overview of all approved employee absences.</p>
                    </div>

                    <div class="date-time-widget">
                        <div class="time" id="real-time">--:--:-- --</div>
                        <div class="date" id="real-date">Loading...</div>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    <div class="feed-container">
                        <div class="calendar-card">
                            <div class="legend-box">
                                <div class="legend-item"><span class="dot" style="background: #3b82f6;"></span> Vacation</div>
                                <div class="legend-item"><span class="dot" style="background: #10b981;"></span> Sick</div>
                                <div class="legend-item"><span class="dot" style="background: #f59e0b;"></span> Emergency</div>
                            </div>
                            <div id="calendar"></div>
                        </div>
                    </div>

                    <div class="side-info-container">
                        <div class="content-card">
                            <div class="card-header"><h2>Upcoming Absences</h2></div>
                            <div style="padding: 20px;" id="upcoming-list">
                                <p style="font-size: 12px; color: #64748b;">Loading upcoming data...</p>
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
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek'
                },
                events: <?php echo json_encode($events); ?>,
                eventClick: function(info) {
                    alert('Leave: ' + info.event.title);
                },
                height: 'auto',
                editable: false,
                selectable: true
            });
            calendar.render();
        });
    </script>
    <script src="/HRIS/assets/js/script.js"></script>
    </body>
</html>
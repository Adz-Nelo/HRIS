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
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Leave Calendar - HRMS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* Unified Calendar Styles */
        .calendar-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            animation: fadeInUp 0.8s ease forwards;
        }

        .calendar-nav-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #f0f2f5;
        }

        .calendar-nav-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
        }

        /* Nav Buttons Gradient Style */
        .btn-cal-nav {
            background: linear-gradient(135deg, #1f6feb, #3399ff);
            border: none;
            color: #fff;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            transition: 0.3s;
        }
        .btn-cal-nav:hover {
            background: linear-gradient(135deg, #174ea6, #1f6feb);
            color: #fff;
            transform: translateY(-1px);
        }

        /* Calendar Table Styling */
        .calendar-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: fixed;
        }

        .calendar-table th {
            background: #f8fafc;
            padding: 12px;
            text-align: center;
            font-weight: 600;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            font-size: 0.75rem;
        }

        .calendar-table td {
            height: 100px;
            vertical-align: top;
            padding: 8px;
            border-right: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: 0.2s;
        }

        .calendar-table td:hover {
            background-color: #f8fafc;
        }

        .calendar-table td.other-month {
            background-color: #fcfcfc;
            color: #cbd5e1;
        }

        .day-num {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 4px;
            display: block;
        }

        /* Leave Indicator Classes */
        .calendar-table td.today {
            background: #eff6ff;
            border: 2px solid #1f6feb !important;
        }
        .calendar-table td.today .day-num {
            color: #1f6feb;
        }

        .leave-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            color: white;
            display: block;
            margin-top: 4px;
            text-align: center;
            font-weight: 500;
        }
        .bg-vacation { background-color: #4dabf7; }
        .bg-sick { background-color: #ff6b6b; }

        /* Animation */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media print {
            .sidebar, .topbar, .btn-cal-nav, .view-buttons { display: none !important; }
            .main-content { margin: 0; padding: 0; }
        }
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
                        <p>View your scheduled leaves and company holidays at a glance.</p>
                    </div>
                    <div class="date-time-widget text-end">
                        <div class="time fw-bold" id="real-time">--:--:-- --</div>
                        <div class="date text-muted" id="real-date">Loading...</div>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    <div class="feed-container">
                        <div class="calendar-card content-card border-0">
                            <div class="calendar-nav-header">
                                <div class="d-flex gap-2">
                                    <button class="btn-cal-nav" id="prev"><i class="fa-solid fa-chevron-left"></i></button>
                                    <button class="btn-cal-nav px-3" id="todayBtn">Today</button>
                                    <button class="btn-cal-nav" id="next"><i class="fa-solid fa-chevron-right"></i></button>
                                </div>
                                <h2 id="monthYear">December 2025</h2>
                                <div class="view-buttons d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-primary border-0 fw-600" id="monthView">Month</button>
                                    <button class="btn btn-sm btn-outline-primary border-0 fw-600" id="weekView">Week</button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="calendar-table">
                                    <thead>
                                        <tr id="calendarHeader">
                                            </tr>
                                    </thead>
                                    <tbody id="calendarBody">
                                        </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="side-info-container">
                        <div class="content-card bg-light shadow-sm">
                            <div class="card-header">
                                <h2>Calendar Legend</h2>
                            </div>
                            <div class="p-3">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="bg-vacation d-inline-block rounded me-2" style="width:15px; height:15px;"></span>
                                    <span class="small fw-600">Vacation Leave</span>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="bg-sick d-inline-block rounded me-2" style="width:15px; height:15px;"></span>
                                    <span class="small fw-600">Sick Leave</span>
                                </div>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="border border-primary border-2 d-inline-block rounded me-2" style="width:15px; height:15px; background: #eff6ff;"></span>
                                    <span class="small fw-600">Current Day</span>
                                </div>
                                <hr>
                                <div class="alert alert-info py-2 px-3 border-0 small text-muted">
                                    <i class="bi bi-info-circle-fill me-1"></i>
                                    Click on a date to see more details about leaves on that day.
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
        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        const weekDays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

        let today = new Date();
        let currentDate = new Date(today);
        let currentView = "month";

        // Mock Leave Data (Integrate PHP Array here later)
        const leaveData = {
            "2025-12-05": "Vacation",
            "2025-12-12": "Sick",
            "2025-12-18": "Vacation",
            "2025-12-25": "Holiday"
        };

        const monthYear = document.getElementById("monthYear");
        const calendarHeader = document.getElementById("calendarHeader");
        const calendarBody = document.getElementById("calendarBody");

        function renderCalendar() {
            calendarHeader.innerHTML = "";
            calendarBody.innerHTML = "";
            monthYear.textContent = monthNames[currentDate.getMonth()] + " " + currentDate.getFullYear();

            if (currentView === "month") renderMonth();
            else renderWeek();
        }

        function renderMonth() {
            weekDays.forEach(day => {
                let th = document.createElement("th");
                th.textContent = day;
                calendarHeader.appendChild(th);
            });

            let firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1).getDay();
            let daysInMonth = 32 - new Date(currentDate.getFullYear(), currentDate.getMonth(), 32).getDate();
            let daysInPrevMonth = 32 - new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 32).getDate();

            let dateNum = 1;
            let nextMonthDate = 1;

            for (let i = 0; i < 6; i++) {
                let row = document.createElement("tr");
                for (let j = 0; j < 7; j++) {
                    let cell = document.createElement("td");
                    
                    if (i === 0 && j < firstDay) {
                        cell.classList.add("other-month");
                        cell.innerHTML = `<span class="day-num">${daysInPrevMonth - firstDay + j + 1}</span>`;
                    } else if (dateNum > daysInMonth) {
                        cell.classList.add("other-month");
                        cell.innerHTML = `<span class="day-num">${nextMonthDate++}</span>`;
                    } else {
                        let dateStr = `${currentDate.getFullYear()}-${(currentDate.getMonth() + 1).toString().padStart(2, '0')}-${dateNum.toString().padStart(2, '0')}`;
                        cell.innerHTML = `<span class="day-num">${dateNum}</span>`;

                        if (dateNum === today.getDate() && currentDate.getMonth() === today.getMonth() && currentDate.getFullYear() === today.getFullYear()) {
                            cell.classList.add("today");
                        }

                        if (leaveData[dateStr]) {
                            let type = leaveData[dateStr];
                            let badgeClass = type === "Vacation" ? "bg-vacation" : (type === "Sick" ? "bg-sick" : "bg-dark");
                            cell.innerHTML += `<span class="leave-badge ${badgeClass}">${type}</span>`;
                        }
                        dateNum++;
                    }
                    row.appendChild(cell);
                }
                calendarBody.appendChild(row);
                if (dateNum > daysInMonth && i > 3) break; 
            }
        }

        // Navigation
        document.getElementById("prev").onclick = () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        };
        document.getElementById("next").onclick = () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        };
        document.getElementById("todayBtn").onclick = () => {
            currentDate = new Date(today);
            renderCalendar();
        };



        renderCalendar();
    </script>
    <script src="/HRIS/assets/js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
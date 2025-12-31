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

$employee_id = $_SESSION['employee_id'];

try {
    // ✅ FIX 3: Robust Data Retrieval
    // HR Staff needs to see ALL applications that are currently 'Pending'
    $stmt = $pdo->prepare("
        SELECT la.*, e.first_name, e.last_name, lt.name as leave_name
        FROM leave_application la
        JOIN employee e ON la.employee_id = e.employee_id
        LEFT JOIN leave_types lt ON la.leave_type_id = lt.leave_types_id
        WHERE la.status = 'Pending'
        ORDER BY la.date_filing ASC
    ");
    $stmt->execute();
    $pendingLeaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- STATS LOGIC ---
    $totalPending = count($pendingLeaves);
    
    // Count applications processed (Reviewed/Rejected/Approved) today
    $stmtCount = $pdo->prepare("
        SELECT COUNT(*) 
        FROM leave_application 
        WHERE status != 'Pending' 
        AND (DATE(hr_staff_reviewed_at) = CURDATE() OR DATE(updated_at) = CURDATE())
    ");
    $stmtCount->execute();
    $reviewedToday = $stmtCount->fetchColumn();

} catch (PDOException $e) {
    error_log("Pending Leave Fetch Error: " . $e->getMessage());
    die("Database Error: Unable to load pending applications.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Leave Validation Queue - HR Staff</title>
    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">


    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* Applying your requested gap updates */
        .dashboard-wrapper { 
            display: flex;
            flex-direction: column;
            gap: 15px !important; 
        }

        .db-filter-bar {
            padding: 12px 20px;
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
            outline: none;
        }

        .db-filter-bar input:focus { border-color: #3b82f6; }

        .status-badge {
            background: #fef3c7; 
            color: #d97706; 
            padding: 4px 10px; 
            border-radius: 12px; 
            font-size: 11px; 
            font-weight: 600;
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
                        <h1>Leave Validation Queue</h1>
                        <p>Review and validate new leave applications before endorsing to officers.</p>
                    </div>
                    <div class="date-time-widget">
                        <div class="time" id="real-time">--:--:-- --</div>
                        <div class="date" id="real-date">Loading...</div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-clock"></i></div>
                        <div class="stat-info">
                            <h3><?= $totalPending ?></h3>
                            <p>Pending Validation</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-clipboard-check"></i></div>
                        <div class="stat-info">
                            <h3><?= $reviewedToday ?></h3>
                            <p>Reviewed Today</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <div class="stat-info">
                            <h3>High</h3>
                            <p>Priority</p>
                        </div>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    <div class="feed-container">
                        <div class="content-card">
                            <div class="db-filter-bar">
                                <i class="fa-solid fa-filter" style="color: #64748b;"></i>
                                <input type="text" id="searchInput" placeholder="Search employee or leave type...">
                                <select id="typeFilter">
                                    <option value="">All Leave Types</option>
                                    <option value="Vacation">Vacation Leave</option>
                                    <option value="Sick">Sick Leave</option>
                                </select>
                            </div>

                            <div class="table-responsive">
                                <table class="ledger-table" id="leaveTable">
                                    <thead>
                                        <tr>
                                            <th>Date Filed</th>
                                            <th>Employee</th>
                                            <th>Leave Type</th>
                                            <th>Inclusive Dates</th>
                                            <th>Days</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($pendingLeaves)): ?>
                                            <?php foreach ($pendingLeaves as $leave): ?>
                                            <tr>
                                                <td><?= date('M d, Y', strtotime($leave['date_filing'])) ?></td>
                                                <td class="font-weight-600">
                                                    <?= htmlspecialchars($leave['last_name'] . ', ' . $leave['first_name']) ?>
                                                </td>
                                                <td><?= htmlspecialchars($leave['leave_name']) ?></td>
                                                <td>
                                                    <small>
                                                        <?= date('M d', strtotime($leave['start_date'])) ?> - <?= date('M d, Y', strtotime($leave['end_date'])) ?>
                                                    </small>
                                                </td>
                                                <td><?= number_format($leave['working_days'], 1) ?></td>
                                                <td class="text-center">
                                                    <span class="status-badge">PENDING</span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="review_leave.php?id=<?= $leave['application_id'] ?>" style="color: #3b82f6; text-decoration: none; font-weight: 600;">
                                                        <i class="fa-solid fa-magnifying-glass-chart"></i> Review
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center" style="padding: 40px; color: #64748b;">
                                                    <i class="fa-solid fa-circle-check d-block mb-2" style="font-size: 2rem; color: #16a34a;"></i>
                                                    All caught up! No pending applications.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="side-info-container">
                        <div class="content-card bg-light shadow-sm">
                            <div class="card-header">
                                <h2>HR Staff Tasks</h2>
                            </div>
                            <div class="p-3">
                                <div class="info-item mb-3">
                                    <small class="d-block" style="color: #3b82f6; font-weight: bold;">1. VALIDATE</small>
                                    <p class="small text-muted">Check if leave credits are sufficient in the ledger.</p>
                                </div>
                                <div class="info-item mb-3">
                                    <small class="d-block" style="color: #3b82f6; font-weight: bold;">2. VERIFY</small>
                                    <p class="small text-muted">Ensure supporting documents (Medical Cert) are attached.</p>
                                </div>
                                <hr>
                                <p class="small text-muted" style="font-style: italic;">
                                    Tip: Endorsements should be handled within 24 hours.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>

    <script>

        // Table Filtering Logic
        document.getElementById('searchInput').addEventListener('keyup', filterTable);
        document.getElementById('typeFilter').addEventListener('change', filterTable);

        function filterTable() {
            const searchText = document.getElementById('searchInput').value.toLowerCase();
            const typeValue = document.getElementById('typeFilter').value.toLowerCase();
            const rows = document.querySelectorAll('#leaveTable tbody tr');

            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                const matchesSearch = text.includes(searchText);
                const matchesType = typeValue === "" || text.includes(typeValue);
                
                row.style.display = (matchesSearch && matchesType) ? "" : "none";
            });
        }
    </script>
    <script src="/HRIS/assets/js/script.js"></script>
    </body>
</html>
<?php
session_start();
require_once '../../config/config.php';

// ✅ FIX 1: Access Control (Standardized to role_name + HR Staff)
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

$default_profile_image = '../../assets/images/default_user.png';

// ✅ FIX 3: Robust Age Calculation Logic
// Uses 365.25 to account for leap years in retirement planning
try {
    $sql = "SELECT e.*, d.department_name, 
            FLOOR(DATEDIFF(CURDATE(), e.birth_date) / 365.25) AS current_age
            FROM employee e
            LEFT JOIN department d ON e.department_id = d.department_id
            WHERE e.status = 'Active' 
            AND FLOOR(DATEDIFF(CURDATE(), e.birth_date) / 365.25) >= 59
            ORDER BY current_age DESC, e.last_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $retirees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- STATS CALCULATION ---
    $total_incoming = count($retirees);
    $mandatory_count = 0; // 65+ years old
    $optional_count = 0;  // 60-64 years old

    foreach($retirees as $r) {
        if($r['current_age'] >= 65) {
            $mandatory_count++;
        } elseif($r['current_age'] >= 60) {
            $optional_count++;
        }
    }

} catch (PDOException $e) {
    error_log("Retirement Report Error: " . $e->getMessage());
    die("Database Error: Unable to process retirement data.");
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incoming Retirees - HRMS</title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* CSS FIXES TO MATCH RETIREMENT DATABASE STYLE */
        .dashboard-wrapper { gap: 15px !important; display: flex; flex-direction: column; } 
        .welcome-header { margin-bottom: 0 !important; }
        .stats-grid { margin-bottom: 0 !important; gap: 15px !important; }
        
        /* Layout Structure */
        .main-dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 300px; /* Table on left, Guide on right */
            gap: 15px;
            align-items: start;
        }

        .content-card.table-card { 
            margin-top: 0 !important; 
            padding: 0 !important; 
        }

        .report-log-header {
            padding: 15px 25px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .report-log-header h2 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        /* Employee Info Cells */
        .emp-info-cell { display: flex; align-items: center; gap: 10px; }
        .emp-profile-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }

        /* Badge Styling */
        .status-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        /* Side Info Card specific */
        .side-info-card { padding: 20px !important; height: fit-content; }
        .info-item { margin-top: 15px; border-top: 1px solid #f1f5f9; padding-top: 15px; }
        
        @media (max-width: 1024px) {
            .main-dashboard-grid { grid-template-columns: 1fr; }
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
                        <h1>Incoming Retirements</h1>
                        <p>Monitoring employees reaching mandatory (65) or optional (60) retirement age.</p>
                    </div>
                    <div class="date-time-widget">
                        <div class="time" id="real-time">--:--:-- --</div>
                        <div class="date" id="real-date">Loading...</div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-person-walking-luggage"></i></div>
                        <div class="stat-info">
                            <h3><?= $mandatory_count ?></h3>
                            <p>Mandatory (65+)</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-user-clock"></i></div>
                        <div class="stat-info">
                            <h3><?= $optional_count ?></h3>
                            <p>Optional (60-64)</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-users"></i></div>
                        <div class="stat-info">
                            <h3><?= $total_incoming ?></h3>
                            <p>Total Monitoring</p>
                        </div>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    
                    <div class="content-card table-card">
                        <div class="report-log-header">
                            <h2>Retirement Monitoring List</h2>
                        </div>

                        <div class="table-container">
                            <table class="ledger-table">
                                <thead>
                                    <tr>
                                        <th>Employee Name</th>
                                        <th>Department</th>
                                        <th>Birth Date</th>
                                        <th>Age</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($retirees as $row): 
                                        $age = $row['current_age'];
                                        if($age >= 65) {
                                            $badge_style = 'background: #fee2e2; color: #dc2626;';
                                            $label = 'MANDATORY';
                                        } elseif($age >= 60) {
                                            $badge_style = 'background: #fef3c7; color: #d97706;';
                                            $label = 'OPTIONAL';
                                        } else {
                                            $badge_style = 'background: #dcfce7; color: #16a34a;';
                                            $label = 'PREPARING';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="emp-info-cell">
                                                <img src="<?= $row['profile_pic'] ?: $default_profile_image ?>" class="emp-profile-img">
                                                <div style="font-weight: 600;"><?= htmlspecialchars($row['last_name'] . ', ' . $row['first_name']) ?></div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($row['department_name']) ?></td>
                                        <td><?= date('M d, Y', strtotime($row['birth_date'])) ?></td>
                                        <td><strong><?= $age ?></strong></td>
                                        <td class="text-center">
                                            <span class="status-badge" style="<?= $badge_style ?>"><?= $label ?></span>
                                        </td>
                                        <td class="text-center">
                                            <button class="icon-btn" title="View Profile"><i class="fa-solid fa-id-card"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if(empty($retirees)): ?>
                                        <tr><td colspan="6" style="text-align:center; padding: 50px; color: #94a3b8;">No incoming retirees found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="content-card side-info-card">
                        <div class="card-header" style="margin-bottom: 15px; padding-bottom: 10px;">
                            <h2>Retirement Guide</h2>
                        </div>
                        
                        <div class="info-item" style="border-top: none; margin-top: 0; padding-top: 0;">
                            <strong style="color: #dc2626; font-size: 11px;">MANDATORY (65+)</strong>
                            <p style="font-size: 12px; color: #64748b; line-height: 1.4; margin-top: 5px;">Required retirement under standard policy.</p>
                        </div>

                        <div class="info-item">
                            <strong style="color: #d97706; font-size: 11px;">OPTIONAL (60-64)</strong>
                            <p style="font-size: 12px; color: #64748b; line-height: 1.4; margin-top: 5px;">Eligible for early retirement if requested.</p>
                        </div>

                        <div class="info-item">
                            <strong style="color: #16a34a; font-size: 11px;">PREPARING (59)</strong>
                            <p style="font-size: 12px; color: #64748b; line-height: 1.4; margin-top: 5px;">Within 12 months of optional retirement age.</p>
                        </div>

                        <div style="margin-top: 25px; background: #f8fafc; padding: 12px; border-radius: 6px; border-left: 3px solid #3b82f6;">
                            <p style="font-size: 11px; color: #475569; font-style: italic; margin: 0;">
                                <strong>Reminder:</strong> Update GSIS/SSS docs 6 months before effective date.
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
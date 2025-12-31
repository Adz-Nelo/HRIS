<?php
session_start();
require_once '../../config/config.php';

// ✅ FIX 1: Access Control (Standardized to role_name)
$allowed_roles = ['Admin', 'HR Officer', 'HR Staff'];
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'], $allowed_roles)) {
    header("Location: ../../login.html?error=unauthorized");
    exit();
}

// ✅ FIX 2: Heartbeat Update
try {
    $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?")
        ->execute([$_SESSION['employee_id']]);
} catch (PDOException $e) { 
    /* silent fail */ 
}

$application_id = $_GET['id'] ?? null;
if (!$application_id) {
    header("Location: pending-leave.php");
    exit();
}

$default_profile_image = '../../assets/images/default_user.png';
$success_msg = "";
$error_msg = "";

// ✅ FIX 3: Handle Decision Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $action = $_POST['action']; 
    $reason = $_POST['rejection_reason'] ?? '';
    $hr_staff_id = $_SESSION['employee_id'];

    try {
        if ($action === 'review') {
            $update_sql = "UPDATE leave_application SET 
                           status = 'HR Staff Reviewed', 
                           hr_staff_id = ?,
                           hr_staff_reviewed_at = CURRENT_TIMESTAMP 
                           WHERE application_id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$hr_staff_id, $application_id]);
            $success_msg = "Application marked as Reviewed by HR.";
        } else if ($action === 'reject') {
            $update_sql = "UPDATE leave_application SET 
                           status = 'Rejected', 
                           rejection_reason = ?, 
                           hr_staff_id = ?,
                           hr_staff_reviewed_at = CURRENT_TIMESTAMP 
                           WHERE application_id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$reason, $hr_staff_id, $application_id]);
            $success_msg = "Application has been rejected.";
        }
    } catch (PDOException $e) {
        error_log("Review Error: " . $e->getMessage());
        $error_msg = "Database Error: Could not update the application.";
    }
}

// ✅ FIX 4: Optimized Application & Employee Data Fetch
try {
    $sql = "SELECT la.*, e.first_name, e.last_name, e.profile_pic, e.position, d.department_name,
                   lb.vacation_leave, lb.sick_leave, 
                   lt.name as leave_type_name,
                   ld.name as leave_detail_name
            FROM leave_application la
            JOIN employee e ON la.employee_id = e.employee_id 
            LEFT JOIN department d ON e.department_id = d.department_id
            LEFT JOIN leave_types lt ON la.leave_type_id = lt.leave_types_id
            LEFT JOIN leave_details ld ON la.leave_detail_id = ld.leave_details_id
            LEFT JOIN leave_balance lb ON e.employee_id = lb.employee_id AND lb.is_latest = 1
            WHERE la.application_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$application_id]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave) {
        die("Error: Application not found.");
    }
} catch (PDOException $e) {
    error_log("Fetch Error: " . $e->getMessage());
    die("Database Error: Unable to fetch application details.");
}
?>






<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Leave - <?= htmlspecialchars($leave['reference_no']) ?></title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root { --primary: #3b82f6; --success: #10b981; --danger: #ef4444; --border: #e2e8f0; }
        
        /* UNIFORM DASHBOARD WRAPPER: Matches previous layout */
        .dashboard-wrapper { 
            display: flex;
            flex-direction: column;
            gap: 15px !important; 
            margin-top: -10px; /* Pulls container up */
            padding: 0 20px 20px;
        }

        .review-card { 
            background: white; 
            border-radius: 4px; 
            border: 1px solid var(--border); 
            overflow: hidden; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.05); 
            margin-top: 5px;
        }
        
        .emp-info-cell { display: flex; align-items: center; gap: 15px; }
        
        /* CIRCLE PROFILE: No border, 50% radius */
        .emp-profile-img { 
            width: 60px; 
            height: 60px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: none; 
        }
        
        .donut-container { display: flex; gap: 20px; margin-top: 15px; }
        .circle { 
            width: 80px; height: 80px; border-radius: 50%; border: 5px solid #f1f5f9; 
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .circle.vacation { border-color: var(--primary); }
        .circle.sick { border-color: var(--success); }
        .circle-val { font-size: 14px; font-weight: 800; color: #1e293b; }
        .circle-lbl { font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; }

        .info-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 0; }
        .grid-col { padding: 30px; }
        .left-col { border-right: 1px solid var(--border); }
        .right-col { background: #fafafa; }

        .data-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 8px; display: block; letter-spacing: 0.5px; }
        .data-value { font-size: 14px; color: #1e293b; font-weight: 600; }
        
        .status-badge { padding: 6px 14px; border-radius: 4px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .status-Pending { background: #fef9c3; color: #a16207; border: 1px solid #fef3c7; }
        .status-HRStaffReviewed { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }

        .form-textarea { width: 100%; border: 1px solid var(--border); border-radius: 4px; padding: 12px; font-size: 14px; margin-top: 8px; resize: none; }
        
        .btn { padding: 12px 20px; border-radius: 4px; font-weight: 700; cursor: pointer; border: none; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; }
        .btn-review { background: var(--primary); color: white; }
        .btn-reject { background: transparent; border: 1px solid var(--danger); color: var(--danger); }
        
        .back-container { 
            margin-top: 10px; 
            text-align: right; 
            border-top: 1px solid var(--border); 
            padding-top: 15px; 
        }
        .btn-back { 
            text-decoration: none; 
            color: #64748b; 
            font-size: 13px; 
            font-weight: 700; 
            text-transform: uppercase; 
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div id="sidebar-placeholder"></div>
        
        <main class="main-content" id="main-content">
            <div id="topbar-placeholder"></div>

            <div class="dashboard-wrapper">
                <div class="welcome-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <div class="welcome-text">
                        <h1 style="font-size: 22px; font-weight: 800; margin-bottom: 2px;">Application Review</h1>
                        <p style="color: #64748b; font-size: 13px;">Ref No: <strong><?= htmlspecialchars($leave['reference_no']) ?></strong></p>
                    </div>
                    <span class="status-badge status-<?= str_replace(' ', '', $leave['status']) ?>">
                        <?= $leave['status'] ?>
                    </span>
                </div>

                <?php if(isset($success_msg)): ?>
                    <div style="background: #dcfce7; color: #16a34a; padding: 12px; border-radius: 4px; border: 1px solid #bdf0d0; font-size: 14px; font-weight: 600;">
                        <i class="fa-solid fa-circle-check"></i> <?= $success_msg ?>
                    </div>
                <?php endif; ?>

                <div class="review-card">
                    <div class="info-grid">
                        <div class="grid-col left-col">
                            <span class="data-label">Employee Information</span>
                            <div class="emp-info-cell">
                                <img src="<?= (!empty($leave['profile_pic'])) ? $leave['profile_pic'] : $default_profile_image ?>" class="emp-profile-img">
                                <div class="emp-details-text">
                                    <span class="data-value" style="font-size: 16px;"><?= htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']) ?></span>
                                    <span style="font-size: 12px; color: #64748b; display: block;"><?= htmlspecialchars($leave['position']) ?> • <?= htmlspecialchars($leave['department_name']) ?></span>
                                </div>
                            </div>

                            <div class="donut-container">
                                <div class="donut-item">
                                    <div class="circle vacation">
                                        <span class="circle-val"><?= number_format($leave['vacation_leave'] ?? 0, 2) ?></span>
                                    </div>
                                    <span class="circle-lbl">Vacation</span>
                                </div>
                                <div class="donut-item">
                                    <div class="circle sick">
                                        <span class="circle-val"><?= number_format($leave['sick_leave'] ?? 0, 2) ?></span>
                                    </div>
                                    <span class="circle-lbl">Sick Leave</span>
                                </div>
                            </div>

                            <div style="margin-top: 25px;">
                                <span class="data-label">Leave Type & Details</span>
                                <div style="background: #eff6ff; border: 1px solid #dbeafe; padding: 15px; border-radius: 4px;">
                                    <span style="font-weight: 800; color: #1e293b; display: block; font-size: 15px;"><?= $leave['leave_type_name'] ?></span>
                                    
                                    <?php if(!empty($leave['leave_detail_name'])): ?>
                                        <span style="color: #3b82f6; font-weight: 700; font-size: 12px; display: block; margin-top: 4px;">
                                            <i class="fa-solid fa-caret-right"></i> <?= htmlspecialchars($leave['leave_detail_name']) ?>
                                        </span>
                                    <?php endif; ?>

                                    <p style="margin: 10px 0 0; color: #475569; font-size: 13px; line-height: 1.5; border-top: 1px solid #dbeafe; padding-top: 8px;">
                                        <?= nl2br(htmlspecialchars($leave['details_description'] ?: 'No specific details provided.')) ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid-col right-col">
                            <div style="margin-bottom: 20px;">
                                <span class="data-label">Requested Schedule</span>
                                <div style="display: flex; justify-content: space-between; align-items: center; background: white; padding: 12px; border: 1px solid var(--border); border-radius: 4px;">
                                    <div>
                                        <div class="data-value"><?= date('M d, Y', strtotime($leave['start_date'])) ?> - <?= date('M d, Y', strtotime($leave['end_date'])) ?></div>
                                        <small style="color: #64748b;">Filing Date: <?= date('M d, Y', strtotime($leave['date_filing'])) ?></small>
                                    </div>
                                    <div style="text-align: right;">
                                        <span class="data-label" style="margin:0">Total Days</span>
                                        <div style="font-size: 22px; font-weight: 900; color: var(--danger);"><?= $leave['working_days'] ?></div>
                                    </div>
                                </div>
                            </div>

                            <div style="margin-bottom: 20px;">
                                <span class="data-label">Commutation Status</span>
                                <div class="data-value"><?= htmlspecialchars($leave['commutation']) ?></div>
                            </div>

                            <?php if($leave['status'] === 'Pending'): ?>
                            <div style="border-top: 1px solid var(--border); padding-top: 15px;">
                                <form method="POST">
                                    <span class="data-label">HR Reviewer Notes</span>
                                    <textarea name="rejection_reason" class="form-textarea" rows="3" placeholder="Enter review remarks..."></textarea>
                                    
                                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                                        <button type="submit" name="action" value="review" class="btn btn-review" style="flex: 2;">
                                            <i class="fa-solid fa-check-double"></i> MARK AS REVIEWED
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-reject" style="flex: 1;" onclick="return confirm('Reject this leave?')">
                                            REJECT
                                        </button>
                                        <input type="hidden" name="submit_review" value="1">
                                    </div>
                                </form>
                            </div>
                            <?php else: ?>
                                <div style="padding: 15px; background: white; border: 1px solid var(--border); border-radius: 4px; text-align: center;">
                                    <i class="fa-solid fa-info-circle" style="color: var(--primary);"></i>
                                    <p style="font-size: 13px; color: #64748b; margin-top: 5px;">
                                        Processed on <?= date('M d, Y h:i A', strtotime($leave['hr_staff_reviewed_at'])) ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="back-container">
                    <a href="pending-leave.php" class="btn-back">
                        <i class="fa-solid fa-arrow-left"></i> Back to Leave Applications
                    </a>
                </div>
            </div>
        </main>
        
        <div id="rightbar-placeholder"></div>
    </div>

    <script src="/HRIS/assets/js/script.js"></script>
</body>
</html>
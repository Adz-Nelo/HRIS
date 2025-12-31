<?php
session_start();
require_once '../../config/config.php';

// --- ACCESS CONTROL ---
$allowed_roles = ['Admin', 'HR Officer', 'HR Staff'];
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'], $allowed_roles)) {
    header("Location: ../../login.html?error=unauthorized");
    exit();
}

$application_id = $_GET['id'] ?? null;
$current_hr_id = $_SESSION['employee_id'];

if (!$application_id) {
    header("Location: canceled-leave.php");
    exit();
}

// --- HANDLE VALIDATION / RECALL ACTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_validate'])) {
    try {
        $update_sql = "UPDATE leave_application 
                       SET status = 'Cancelled', 
                           cancel_hr_validated_by = ?, 
                           hr_staff_reviewed_at = NOW() 
                       WHERE application_id = ?";
        $stmt = $pdo->prepare($update_sql);
        if ($stmt->execute([$current_hr_id, $application_id])) {
            $success_msg = "Application has been successfully validated and recalled.";
        }
    } catch (PDOException $e) {
        $error_msg = "Update failed: " . $e->getMessage();
    }
}

$default_profile_image = '../../assets/images/default_user.png';

try {
    // Selection includes all cancellation fields you provided
    $sql = "SELECT la.*, e.first_name, e.last_name, e.profile_pic, e.position, d.department_name, 
                   lt.name as leave_type_name, lb.vacation_leave, lb.sick_leave,
                   v.first_name as hr_fname, v.last_name as hr_lname
            FROM leave_application la
            JOIN employee e ON la.employee_id = e.employee_id
            LEFT JOIN employee v ON la.cancel_hr_validated_by = v.employee_id
            LEFT JOIN department d ON e.department_id = d.department_id
            LEFT JOIN leave_types lt ON la.leave_type_id = lt.leave_types_id
            LEFT JOIN leave_balance lb ON e.employee_id = lb.employee_id AND lb.is_latest = 1
            WHERE la.application_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$application_id]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave) {
        header("Location: canceled-leave.php");
        exit();
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
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
        :root { --primary: #3b82f6; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; --border: #e2e8f0; }
        .dashboard-wrapper { display: flex; flex-direction: column; gap: 15px !important; padding: 0 20px 20px; }
        .review-card { background: white; border-radius: 4px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .emp-info-cell { display: flex; align-items: center; gap: 15px; }
        .emp-profile-img { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; }
        .donut-container { display: flex; gap: 20px; margin-top: 15px; }
        .circle { width: 70px; height: 70px; border-radius: 50%; border: 4px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .circle.vacation { border-color: var(--primary); }
        .circle.sick { border-color: var(--success); }
        .circle-val { font-size: 13px; font-weight: 800; color: #1e293b; }
        .circle-lbl { font-size: 8px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .info-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 0; }
        .grid-col { padding: 30px; }
        .left-col { border-right: 1px solid var(--border); }
        .right-col { background: #fafafa; }
        .data-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 8px; display: block; }
        .data-value { font-size: 14px; color: #1e293b; font-weight: 600; }
        .status-badge { padding: 6px 14px; border-radius: 4px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .status-Cancelled { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .status-CancellationPending { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
        .cancellation-box { background: #fffbeb; border: 1px dashed #f59e0b; padding: 20px; border-radius: 4px; margin-top: 20px; }
        .proof-link { display: block; margin-top: 10px; cursor: zoom-in; }
        .proof-preview { max-width: 100%; border-radius: 4px; border: 1px solid #fed7aa; transition: 0.2s; }
        .proof-preview:hover { opacity: 0.9; transform: scale(1.01); }
        .btn-validate { background: var(--warning); color: white; width: 100%; padding: 12px; border: none; border-radius: 4px; font-weight: 800; cursor: pointer; margin-top: 15px; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-validate:hover { background: #d97706; }
        .back-container { margin-top: 10px; text-align: right; border-top: 1px solid var(--border); padding-top: 15px; }
        .btn-back { text-decoration: none; color: #64748b; font-size: 13px; font-weight: 700; text-transform: uppercase; }
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
                            <span class="data-label">Employee Details</span>
                            <div class="emp-info-cell">
                                <img src="<?= (!empty($leave['profile_pic'])) ? $leave['profile_pic'] : $default_profile_image ?>" class="emp-profile-img">
                                <div class="emp-details-text">
                                    <span class="data-value" style="font-size: 16px;"><?= htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']) ?></span>
                                    <span style="font-size: 12px; color: #64748b; display: block;"><?= htmlspecialchars($leave['position'] ?? 'Employee') ?> • <?= htmlspecialchars($leave['department_name']) ?></span>
                                </div>
                            </div>

                            <div class="donut-container">
                                <div class="donut-item"><div class="circle vacation"><span class="circle-val"><?= number_format($leave['vacation_leave'] ?? 0, 1) ?></span></div><span class="circle-lbl">Vacation</span></div>
                                <div class="donut-item"><div class="circle sick"><span class="circle-val"><?= number_format($leave['sick_leave'] ?? 0, 1) ?></span></div><span class="circle-lbl">Sick Leave</span></div>
                            </div>

                            <div style="margin-top: 25px;">
                                <span class="data-label">Original Purpose of Leave</span>
                                <div style="background: #f8fafc; border: 1px solid var(--border); padding: 15px; border-radius: 4px;">
                                    <span style="font-weight: 800; color: #1e293b; display: block; font-size: 14px;"><?= htmlspecialchars($leave['leave_type_name']) ?></span>
                                    <p style="margin: 8px 0 0; color: #475569; font-size: 13px; line-height: 1.5;">
                                        <?= nl2br(htmlspecialchars($leave['reason'] ?? 'No reason provided in original filing.')) ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid-col right-col">
                            <div style="margin-bottom: 20px;">
                                <span class="data-label">Leave Schedule</span>
                                <div style="display: flex; justify-content: space-between; align-items: center; background: white; padding: 12px; border: 1px solid var(--border); border-radius: 4px;">
                                    <div>
                                        <div class="data-value"><?= date('M d, Y', strtotime($leave['start_date'])) ?> - <?= date('M d, Y', strtotime($leave['end_date'])) ?></div>
                                        <small style="color: #64748b;">Filed: <?= date('M d, Y', strtotime($leave['applicant_sign_date'] ?? $leave['created_at'])) ?></small>
                                    </div>
                                    <div style="text-align: right;">
                                        <span class="data-label" style="margin:0">Total Days</span>
                                        <div style="font-size: 22px; font-weight: 900; color: var(--danger);"><?= $leave['working_days'] ?></div>
                                    </div>
                                </div>
                            </div>

                            <?php if(!empty($leave['cancel_reason'])): ?>
                                <div class="cancellation-box">
                                    <span class="data-label" style="color: #b45309;"><i class="fa-solid fa-triangle-exclamation"></i> Cancellation Request</span>
                                    <p style="font-size: 13px; color: #92400e; margin-top: 5px; font-weight: 600;">
                                        <?= nl2br(htmlspecialchars($leave['cancel_reason'])) ?>
                                    </p>
                                    
                                    <?php if($leave['cancel_proof_path']): ?>
                                        <a href="<?= htmlspecialchars($leave['cancel_proof_path']) ?>" target="_blank" class="proof-link">
                                            <img src="<?= htmlspecialchars($leave['cancel_proof_path']) ?>" class="proof-preview" alt="Cancellation Proof">
                                        </a>
                                    <?php endif; ?>

                                    <?php if($leave['status'] === 'Cancellation Pending'): ?>
                                        <form method="POST">
                                            <button type="submit" name="action_validate" class="btn-validate" onclick="return confirm('Validate this cancellation and recall credits?')">
                                                <i class="fa-solid fa-check-to-slot"></i> VALIDATE & RECALL
                                            </button>
                                        </form>
                                    <?php elseif($leave['status'] === 'Cancelled'): ?>
                                        <div style="margin-top: 15px; border-top: 1px solid #fed7aa; padding-top: 10px; font-size: 11px; color: #b45309;">
                                            <i class="fa-solid fa-user-check"></i> <strong>Validated By:</strong> <?= htmlspecialchars($leave['hr_fname'] . ' ' . $leave['hr_lname']) ?><br>
                                            <i class="fa-solid fa-clock"></i> <strong>Date:</strong> <?= date('M d, Y h:i A', strtotime($leave['hr_staff_reviewed_at'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="back-container">
                    <a href="canceled-leave.php" class="btn-back">
                        <i class="fa-solid fa-arrow-left"></i> Back to Cancellation List
                    </a>
                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>
    <script src="/HRIS/assets/js/script.js"></script>
</body>
</html>
<?php
session_start();
require_once '../../config/config.php';

// 1. STANDARDIZED ACCESS CONTROL
$allowed_roles = ['Department Head', 'Admin'];
$user_role = $_SESSION['role'] ?? $_SESSION['role_name'] ?? null;

if (!isset($_SESSION['employee_id']) || !in_array($user_role, $allowed_roles)) {
    header("Location: /HRIS/login.html");
    exit();
}

$application_id = $_GET['id'] ?? null;
$success_msg = "";
$error_msg = "";
$default_profile_image = '../../assets/images/default_user.png';

// 2. HANDLE FINAL APPROVAL ACTION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $application_id) {
    $action = $_POST['action']; 
    $remarks = $_POST['official_remarks'] ?? '';
    $sig_ref = $_POST['official_signature'] ?? '';
    $official_id = $_SESSION['employee_id'];

    try {
        $pdo->beginTransaction();

        $stmt_head = $pdo->prepare("SELECT first_name, last_name, position FROM employee WHERE employee_id = ?");
        $stmt_head->execute([$official_id]);
        $head_info = $stmt_head->fetch(PDO::FETCH_ASSOC);

        $fullname = $head_info['first_name'] . ' ' . $head_info['last_name'];
        $pos = $head_info['position'];
        $new_status = ($action === 'approve') ? 'Approved' : 'Rejected';

        // Update Application
        $update_sql = "UPDATE leave_application SET 
                        status = ?, 
                        rejection_reason = ?, 
                        authorized_official_id = ?,
                        authorized_official_name = ?,
                        authorized_official_position = ?,
                        authorized_official_signature = ?, 
                        authorized_official_sign_date = NOW(),
                        updated_at = NOW()
                        WHERE application_id = ? AND status = 'Officer Recommended'";
        
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$new_status, $remarks, $official_id, $fullname, $pos, $sig_ref, $application_id]);
        
        // 3. DEDUCTION LOGIC
        if ($update_stmt->rowCount() > 0 && $new_status === 'Approved') {
            $fetch_stmt = $pdo->prepare("SELECT employee_id, leave_type_id, working_days FROM leave_application WHERE application_id = ?");
            $fetch_stmt->execute([$application_id]);
            $app_data = $fetch_stmt->fetch(PDO::FETCH_ASSOC);

            // Mapping based on your provided Leave Types
            $leave_column_map = [
                1  => 'vacation_leave',    // Vacation
                2  => 'vacation_leave',    // Forced Leave (deducts from VL)
                3  => 'sick_leave',        // Sick
                4  => 'maternity_leave',   // Maternity
                5  => 'paternity_leave',   // Paternity
                6  => 'special_leave',     // Special Privilege
                7  => 'solo_parent_leave',  // Solo Parent
                8  => 'study_leave',       // Study
                12 => 'calamity_leave'     // Calamity
            ];

            $type_id = (int)$app_data['leave_type_id'];
            $days_to_deduct = (float)$app_data['working_days'];
            $target_emp_id = $app_data['employee_id'];

            if (isset($leave_column_map[$type_id])) {
                $column = $leave_column_map[$type_id];
                $deduct_sql = "UPDATE leave_balance SET $column = $column - ?, updated_at = NOW() 
                               WHERE employee_id = ? AND is_latest = 1";
                $deduct_stmt = $pdo->prepare($deduct_sql);
                $deduct_stmt->execute([$days_to_deduct, $target_emp_id]);
            }
        }

        $pdo->commit();
        $success_msg = "Application has been " . $new_status . " successfully.";
        header("Refresh: 2; url=pending-leave.php");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_msg = "Error: " . $e->getMessage();
    }
}

// 4. FETCH DATA FOR DISPLAY (Notice: corrected column alias for remarks)
$leave = null;
if ($application_id) {
    try {
        $sql = "SELECT la.*, e.first_name, e.last_name, e.profile_pic, e.position, d.department_name,
                       lt.name as leave_type_name,
                       offi.first_name as off_fn, offi.last_name as off_ln
                FROM leave_application la
                JOIN employee e ON la.employee_id = e.employee_id 
                LEFT JOIN department d ON e.department_id = d.department_id
                LEFT JOIN leave_types lt ON la.leave_type_id = lt.leave_types_id
                LEFT JOIN employee offi ON la.authorized_officer_id = offi.employee_id
                WHERE la.application_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$application_id]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $error_msg = $e->getMessage(); }
}

if (!$leave) die("Error: Leave application not found.");
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Final Approval - <?= htmlspecialchars($leave['reference_no']) ?></title>
    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">
    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .action-button-group { display: flex; gap: 10px; margin-bottom: 10px; }
        .btn-signature {
            background-color: #64748b; color: white; border: 1px solid #475569;
            flex: 1; display: inline-flex; align-items: center; justify-content: center;
            gap: 8px; padding: 10px; border-radius: 5px; cursor: pointer; font-weight: 600;
        }
        .btn-signature:hover { background-color: #475569; }
        .btn-approve { flex: 2; background-color: #16a34a !important; border-color: #16a34a !important; }
        .text-blue { color: #2563eb; }
        .text-green { color: #16a34a; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .status-officer-recommended { background: #dcfce7; color: #166534; }
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
                        <h1>Final Approval Authorization</h1>
                        <p>Reference No: <strong class="text-blue"><?= htmlspecialchars($leave['reference_no']) ?></strong></p>
                    </div>
                    <div class="date-time-widget">
                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $leave['status'])) ?>">
                            <?= $leave['status'] ?>
                        </span>
                    </div>
                </div>

                <?php if(!empty($success_msg)): ?>
                    <div class="content-card" style="border-left: 5px solid #31a24c; background: #f0fff4; margin-bottom: 15px; padding: 15px;">
                        <p class="text-green"><i class="fa-solid fa-circle-check"></i> <?= $success_msg ?></p>
                    </div>
                <?php endif; ?>

                <?php if(!empty($error_msg)): ?>
                    <div class="content-card" style="border-left: 5px solid #dc2626; background: #fef2f2; margin-bottom: 15px; padding: 15px;">
                        <p style="color: #dc2626;"><i class="fa-solid fa-circle-xmark"></i> <?= $error_msg ?></p>
                    </div>
                <?php endif; ?>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-calendar-day"></i></div>
                        <div class="stat-info">
                            <h3><?= number_format($leave['working_days'], 1) ?></h3>
                            <p>Total Working Days</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-clock"></i></div>
                        <div class="stat-info">
                            <h3 style="font-size: 18px;"><?= date('M d', strtotime($leave['start_date'])) ?></h3>
                            <p>Start Date</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange"><i class="fa-solid fa-plane-departure"></i></div>
                        <div class="stat-info">
                            <h3 style="font-size: 18px;"><?= htmlspecialchars($leave['leave_type_name']) ?></h3>
                            <p>Leave Category</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-file-invoice"></i></div>
                        <div class="stat-info">
                            <h3 style="font-size: 18px;"><?= date('M d', strtotime($leave['date_filing'])) ?></h3>
                            <p>Date Filed</p>
                        </div>
                    </div>
                </div>

                <div class="main-dashboard-grid" style="display: grid; gap: 15px;">
                    <div class="content-card">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-user-tie"></i> Applicant Information</h2>
                        </div>
                        <div style="display: flex; gap: 20px; align-items: center; margin-bottom: 25px;">
                            <img src="<?= (!empty($leave['profile_pic'])) ? $leave['profile_pic'] : $default_profile_image ?>" 
                                 style="width: 85px; height: 85px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.1); object-fit: cover;">
                            <div>
                                <h4 style="font-size: 18px; margin-bottom: 5px;"><?= htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']) ?></h4>
                                <p class="text-blue" style="font-weight: 700;"><?= htmlspecialchars($leave['position']) ?></p>
                                <p style="font-size: 13px; color: var(--text-secondary);"><?= htmlspecialchars($leave['department_name'] ?? 'N/A') ?></p>
                            </div>
                        </div>

                        <div class="announcement-item" style="background: #f0f9ff; padding: 15px; border-radius: 5px; border: 1px solid #bae6fd;">
                            <div class="ann-text">
                                <h4 class="text-blue"><i class="fa-solid fa-shield-check"></i> Recommendation Summary</h4>
                                <p style="font-style: italic; margin-bottom: 8px;">
                                    "<?= htmlspecialchars($leave['authorized_officer_remarks'] ?? 'Recommended for approval.') ?>"
                                </p>
                                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #bae6fd; padding-top: 8px;">
                                    <small style="color: #0369a1;">
                                        <strong>Officer:</strong> <?= htmlspecialchars(($leave['off_fn'] ?? 'HR') . ' ' . ($leave['off_ln'] ?? 'Officer')) ?>
                                    </small>
                                    <small style="color: #0369a1;">
                                        <i class="fa-regular fa-calendar-check"></i> 
                                        <?= !empty($leave['authorized_officer_sign_date']) ? date('M d, Y', strtotime($leave['authorized_officer_sign_date'])) : 'Date N/A' ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="content-card">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-pen-fancy"></i> Final Decision (Dept Head)</h2>
                        </div>
                        <?php if($leave['status'] === 'Officer Recommended'): ?>
                            <form method="POST" id="finalForm">
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label style="font-weight: 600; font-size: 14px; margin-bottom: 8px; display: block;">Final Remarks / Instructions</label>
                                    <textarea name="official_remarks" 
                                              style="width: 100%; min-height: 100px; padding: 12px; border: 1px solid #d1d5db; border-radius: 5px; font-family: inherit; font-size: 14px;"
                                              placeholder="Optional: Enter remarks for the employee..."></textarea>
                                </div>

                                <input type="hidden" name="official_signature" id="finalSigInput" value="">

                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <div class="action-button-group">
                                        <button type="submit" name="action" value="approve" class="btn-primary btn-approve" style="justify-content: center; color: white; border-radius: 5px; cursor: pointer;">
                                            <i class="fa-solid fa-check-double"></i> FINAL APPROVE
                                        </button>
                                        <button type="button" class="btn-signature" onclick="getFinalSignature()">
                                            <i class="fa-solid fa-signature"></i> SIGN
                                        </button>
                                    </div>
                                    <button type="submit" name="action" value="reject" class="btn-danger" style="justify-content: center; width: 100%; background: #dc2626; color: white; padding: 10px; border: none; border-radius: 5px; cursor: pointer;" 
                                            onclick="return confirm('Are you sure you want to REJECT this application?')">
                                        <i class="fa-solid fa-xmark"></i> DISAPPROVE / REJECT
                                    </button>
                                    <a href="pending-leave.php" class="btn-secondary" style="text-align: center; text-decoration: none; padding: 10px; border: 1px solid #ccc; border-radius: 5px; color: #333;">Back to Queue</a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div style="text-align: center; padding: 30px;">
                                <i class="fa-solid fa-lock" style="font-size: 45px; color: #cbd5e1; margin-bottom: 15px; display: block;"></i>
                                <p style="color: var(--text-secondary);">This application is currently <strong><?= htmlspecialchars($leave['status']) ?></strong>.</p>
                                <p style="font-size: 12px; color: #94a3b8;">Final approval is only available for 'Officer Recommended' status.</p>
                                <a href="pending-leave.php" class="btn-secondary" style="margin-top: 20px; display: inline-flex; text-decoration: none; border: 1px solid #ccc; padding: 8px 15px; border-radius: 5px;">Return to List</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>

    <script>
        function getFinalSignature() {
            const code = prompt("Enter Final Approval Security Code / Signature Reference:");
            if(code) {
                document.getElementById('finalSigInput').value = code;
                alert("Signature Reference Locked: " + code);
            }
        }
    </script>
    <script src="/HRIS/assets/js/script.js"></script>
</body>
</html>
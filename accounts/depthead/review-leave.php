<?php
session_start();
require_once '../../config/config.php';

// Access Control - Only HR and Admin
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role'], ['Admin', 'HR Officer'])) {
    header("Location: ../../login.php");
    exit();
}

$application_id = $_GET['id'] ?? null;
if (!$application_id) {
    header("Location: pending-leave.php");
    exit();
}

$default_profile_image = '../../assets/images/default_user.png';

// Handle Authorized Officer Decision
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action']; 
    $reason = $_POST['officer_remarks'] ?? '';
    $signature_data = $_POST['officer_signature'] ?? ''; 
    $officer_id = $_SESSION['employee_id'];

    try {
        // FETCH UPDATED POSITION: Get the latest position from the employee table for the logged-in officer
        $stmt_pos = $pdo->prepare("SELECT first_name, last_name, position FROM employee WHERE employee_id = ?");
        $stmt_pos->execute([$officer_id]);
        $officer_info = $stmt_pos->fetch(PDO::FETCH_ASSOC);

        $officer_name = $officer_info['first_name'] . ' ' . $officer_info['last_name'];
        $officer_position = $officer_info['position'] ?? 'Authorized Officer';

        if ($action === 'recommend') {
            $new_status = 'Officer Recommended';
        } else {
            $new_status = 'Rejected';
        }
        
        $update_sql = "UPDATE leave_application SET 
                        status = ?, 
                        rejection_reason = ?, 
                        authorized_officer_id = ?,
                        authorized_officer_name = ?,
                        authorized_officer_position = ?,
                        authorized_officer_signature = ?, 
                        authorized_officer_sign_date = CURRENT_TIMESTAMP 
                        WHERE application_id = ?";
        
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([
            $new_status, 
            $reason, 
            $officer_id, 
            $officer_name, 
            $officer_position, 
            $signature_data, 
            $application_id
        ]);
        
        $success_msg = "Application status updated to " . $new_status . " successfully.";
        header("Refresh: 2; url=pending-leave.php");
    } catch (PDOException $e) {
        $error_msg = "Database Error: " . $e->getMessage();
    }
}

// Fetch Application & Employee Data for Display
try {
    $sql = "SELECT la.*, e.first_name, e.last_name, e.profile_pic, e.position, d.department_name,
                   lt.name as leave_type_name,
                   ld.name as leave_detail_name,
                   hr.first_name as hr_fn, hr.last_name as hr_ln
            FROM leave_application la
            JOIN employee e ON la.employee_id = e.employee_id 
            LEFT JOIN department d ON e.department_id = d.department_id
            LEFT JOIN leave_types lt ON la.leave_type_id = lt.leave_types_id
            LEFT JOIN leave_details ld ON la.leave_detail_id = ld.leave_details_id
            LEFT JOIN employee hr ON la.hr_staff_id = hr.employee_id
            WHERE la.application_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$application_id]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave) die("Error: Application not found.");
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Review - <?= htmlspecialchars($leave['reference_no']) ?></title>


    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .action-button-group {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        .btn-signature {
            background-color: #64748b;
            color: white;
            border: 1px solid #475569;
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }
        .btn-signature:hover { background-color: #475569; }
        .btn-recommend { flex: 2; }
        .text-blue { color: #2563eb; }
        .text-green { color: #16a34a; }
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
                        <h1>Review Authorization</h1>
                        <p>Reference No: <strong class="text-blue"><?= htmlspecialchars($leave['reference_no']) ?></strong></p>
                    </div>
                    <div class="date-time-widget">
                        <span class="status <?= strtolower(str_replace(' ', '-', $leave['status'])) ?>">
                            <?= $leave['status'] ?>
                        </span>
                    </div>
                </div>

                <?php if(isset($success_msg)): ?>
                    <div class="content-card" style="border-left: 5px solid #31a24c; background: #f0fff4; margin-bottom: 15px; padding: 15px;">
                        <p class="text-green"><i class="fa-solid fa-circle-check"></i> <?= $success_msg ?></p>
                    </div>
                <?php endif; ?>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-calendar-day"></i></div>
                        <div class="stat-info">
                            <h3><?= $leave['working_days'] ?></h3>
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
                            <h3 style="font-size: 18px;"><?= $leave['leave_type_name'] ?></h3>
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
                                <p style="font-size: 13px; color: var(--text-secondary);"><?= htmlspecialchars($leave['department_name']) ?></p>
                            </div>
                        </div>

                        <div class="announcement-item" style="background: #f8fafc; padding: 15px; border-radius: 5px; border: 1px solid #e2e8f0;">
                            <div class="ann-text">
                                <h4 class="text-blue"><i class="fa-solid fa-clipboard-check"></i> HR Review Status</h4>
                                <?php if ($leave['hr_staff_id']): ?>
                                    <p style="font-style: italic; margin-bottom: 8px;">"<?= htmlspecialchars($leave['rejection_reason'] ?: 'No specific HR remarks.') ?>"</p>
                                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0; padding-top: 8px;">
                                        <small style="color: #64748b;"><strong>Reviewed by:</strong> <?= htmlspecialchars($leave['hr_fn'] . ' ' . $leave['hr_ln']) ?></small>
                                        <small style="color: #94a3b8;"><i class="fa-regular fa-clock"></i> <?= date('M d, Y h:i A', strtotime($leave['hr_staff_reviewed_at'])) ?></small>
                                    </div>
                                <?php else: ?>
                                    <p style="color: #94a3b8; font-style: italic;">Pending HR Staff Review...</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="content-card">
                        <div class="card-header">
                            <h2><i class="fa-solid fa-pen-to-square"></i> Take Action</h2>
                        </div>

                        <?php if(in_array($leave['status'], ['Pending', 'HR Staff Reviewed'])): ?>
                            <form method="POST" id="reviewForm">
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label style="font-weight: 600; font-size: 14px; margin-bottom: 8px; display: block;">Reviewing Officer Remarks</label>
                                    <textarea name="officer_remarks" 
                                              style="width: 100%; min-height: 120px; padding: 12px; border: 1px solid #d1d5db; border-radius: 5px; font-family: inherit; font-size: 14px;"
                                              placeholder="Enter decision details here..."></textarea>
                                </div>

                                <input type="hidden" name="officer_signature" id="signatureInput" value="">

                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <div class="action-button-group">
                                        <button type="submit" name="action" value="recommend" class="btn-primary btn-recommend" style="justify-content: center; background: #f39c12; border-color: #f39c12; color: white; border-radius: 5px; cursor: pointer;">
                                            <i class="fa-solid fa-thumbs-up"></i> Recommend Approval
                                        </button>
                                        
                                        <button type="button" class="btn-signature" onclick="openSignaturePad()">
                                            <i class="fa-solid fa-signature"></i> Signature
                                        </button>
                                    </div>

                                    <button type="submit" name="action" value="disapprove" class="btn-danger" style="justify-content: center; width: 100%; background: #dc2626; color: white; padding: 10px; border: none; border-radius: 5px; cursor: pointer;" 
                                            onclick="return confirm('Are you sure you want to reject this application?')">
                                        <i class="fa-solid fa-xmark"></i> Disapprove / Reject
                                    </button>
                                    <a href="pending-leave.php" class="btn-secondary" style="text-align: center; text-decoration: none; padding: 10px; border: 1px solid #ccc; border-radius: 5px; color: #333;">Cancel</a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div style="text-align: center; padding: 30px;">
                                <i class="fa-solid fa-lock" style="font-size: 45px; color: #cbd5e1; margin-bottom: 15px; display: block;"></i>
                                <p style="color: var(--text-secondary);">Action already taken. This application is <strong><?= $leave['status'] ?></strong>.</p>
                                <a href="pending-leave.php" class="btn-secondary" style="margin-top: 20px; display: inline-flex; text-decoration: none; border: 1px solid #ccc; padding: 8px 15px; border-radius: 5px;">Back to List</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function openSignaturePad() {
            // Placeholder logic - replace with a real canvas modal if needed
            const sig = prompt("Enter Signature Reference (e.g. SIG-2024-001):");
            if(sig) {
                document.getElementById('signatureInput').value = sig;
                alert("Signature Reference attached: " + sig);
            }
        }
    </script>
    <script src="assets/js/script.js"></script>
</body>
</html>
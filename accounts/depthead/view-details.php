<?php
session_start();
require_once '../../config/config.php';

// 1. ACCESS CONTROL - AUTHENTICATION
if (!isset($_SESSION['employee_id'])) {
    header("Location: /HRIS/login.html");
    exit();
}

$application_id = $_GET['id'] ?? null;
if (!$application_id) {
    header("Location: leave-history.php");
    exit();
}

// 2. HEARTBEAT UPDATE
try {
    $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?")
        ->execute([$_SESSION['employee_id']]);
} catch (PDOException $e) { /* silent fail */ }

// 3. FETCH DATA & PERMISSION CHECK
try {
    $user_id = $_SESSION['employee_id'];
    $user_role = $_SESSION['role_name'] ?? '';
    $user_dept = $_SESSION['department_id'] ?? null;

    // Define permission groups
    $is_hr_admin = in_array($user_role, ['Admin', 'HR Officer', 'HR Staff']);
    $is_dept_head = ($user_role === 'Department Head');

    $query = "
        SELECT l.*, 
               e.first_name, e.last_name, e.middle_name, e.profile_pic, 
               e.position AS emp_position, e.department_id AS applicant_dept_id,
               d.department_name,
               lt.name AS leave_type_name,
               lt.description AS leave_legal_basis
        FROM leave_application l
        JOIN employee e ON l.employee_id = e.employee_id
        LEFT JOIN department d ON e.department_id = d.department_id
        LEFT JOIN leave_types lt ON l.leave_type_id = lt.leave_types_id
        WHERE l.application_id = ?
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$application_id]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave) {
        die("Record not found.");
    }

    // --- PERMISSION GATEKEEPER ---
    $access_granted = false;

    if ($is_hr_admin) {
        $access_granted = true; // HR/Admin can see all
    } elseif ($leave['employee_id'] == $user_id) {
        $access_granted = true; // Employee can see their own
    } elseif ($is_dept_head) {
        // Access if Head is in the same department OR if they are the designated official for this leave
        if (($user_dept && $user_dept == $leave['applicant_dept_id']) || 
            ($leave['authorized_official_id'] == $user_id)) {
            $access_granted = true;
        }
    }

    if (!$access_granted) {
        error_log("Unauthorized access attempt by ID: $user_id on Leave: $application_id");
        die("<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>
                <h2>Access Denied</h2>
                <p>You do not have permission to view this department's records.</p>
                <a href='javascript:history.back()'>Go Back</a>
             </div>");
    }

} catch (PDOException $e) {
    error_log("Leave Detail Error: " . $e->getMessage());
    die("Database Error: Unable to retrieve details.");
}

$default_profile_image = '../../assets/images/default_user.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Leave - <?= htmlspecialchars($leave['reference_no']) ?></title>
    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">
    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .wide-card { background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .card-top-bar { padding: 25px 30px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }

        .user-profile-info { display: flex; align-items: center; gap: 15px; }
        .user-profile-info img { width: 65px; height: 65px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .user-details h2 { margin: 0; font-size: 20px; color: #1e293b; }
        .user-details p { margin: 2px 0 0; color: #64748b; font-size: 14px; }

        .details-body { padding: 30px; }
        .details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .detail-item label { display: block; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px; }
        .detail-item span { display: block; font-size: 14px; color: #1e293b; font-weight: 600; }

        .section-divider { height: 1px; background: #f1f5f9; margin: 40px 0 25px; position: relative; }
        .section-divider::after { content: attr(data-label); position: absolute; top: -10px; left: 0; background: #fff; padding-right: 15px; font-size: 11px; font-weight: 800; color: #1d4ed8; text-transform: uppercase; }

        .status-badge { padding: 6px 15px; border-radius: 50px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .status-badge.approved { background: rgba(49, 162, 76, 0.1); color: #31a24c; }
        .status-badge.pending { background: rgba(245, 158, 11, 0.1); color: #d97706; }
        .status-badge.rejected { background: rgba(231, 76, 60, 0.1); color: #e74c3c; }

        .signatures-chain { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px; }
        .sig-box { text-align: center; background: #fcfcfd; padding: 20px; border-radius: 8px; border: 1px solid #f1f5f9; }
        .sig-img { max-width: 140px; height: 50px; object-fit: contain; margin-bottom: 10px; mix-blend-mode: multiply; }
        .sig-placeholder { height: 50px; display: flex; align-items: center; justify-content: center; color: #cbd5e1; font-size: 11px; font-style: italic; }
        .sig-line { border-top: 1px solid #e2e8f0; margin-top: 10px; padding-top: 10px; }
        .sig-name { font-weight: 700; color: #1e293b; font-size: 13px; display: block; line-height: 1.2; }
        .sig-title { font-size: 10px; color: #64748b; text-transform: uppercase; font-weight: 700; margin-top: 4px; display: block; }

        .bottom-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; padding-bottom: 50px; }
        .print-btn { background: #1d4ed8; color: #fff; border: none; padding: 10px 25px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; }
        
        @media print {
            .bottom-actions, #sidebar-placeholder, #topbar-placeholder, #rightbar-placeholder, .welcome-header { display: none !important; }
            .main-content { padding: 0 !important; margin: 0 !important; }
            .wide-card { border: none !important; box-shadow: none !important; }
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
                        <h1 style="font-size: 22px; margin-bottom: 0;">Leave Application Details</h1>
                        <p style="color: #64748b; font-size: 14px; margin-top: 2px;">Reference: <?= htmlspecialchars($leave['reference_no']) ?></p>
                    </div>
                </div>

                <div class="wide-card">
                    <div class="card-top-bar">
                        <div class="user-profile-info">
                            <img src="<?= (!empty($leave['profile_pic'])) ? $leave['profile_pic'] : $default_profile_image ?>" alt="Profile">
                            <div class="user-details">
                                <h2><?= htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']) ?></h2>
                                <p><?= htmlspecialchars($leave['emp_position']) ?> • <?= htmlspecialchars($leave['department_name']) ?></p>
                            </div>
                        </div>
                        <div>
                            <?php 
                                $s = $leave['status'];
                                $class = strtolower(in_array($s, ['Approved', 'Officer Recommended']) ? 'approved' : ($s == 'Rejected' ? 'rejected' : 'pending'));
                            ?>
                            <span class="status-badge <?= $class ?>"><?= $s ?></span>
                        </div>
                    </div>

                    <div class="details-body">
                        <div class="section-divider" data-label="Leave Information"></div>
                        <div class="details-grid">
                            <div class="detail-item">
                                <label>Reference Number</label>
                                <span style="color:#1d4ed8; font-family:monospace;"><?= htmlspecialchars($leave['reference_no']) ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Leave Type</label>
                                <span><?= htmlspecialchars($leave['leave_type_name'] ?? 'Others') ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Date of Filing</label>
                                <span><?= date('F d, Y', strtotime($leave['date_filing'])) ?></span>
                            </div>
                        </div>

                        <div class="section-divider" data-label="Inclusive Dates & Computation"></div>
                        <div class="details-grid">
                            <div class="detail-item">
                                <label>Duration</label>
                                <span><?= date('M d, Y', strtotime($leave['start_date'])) ?> — <?= date('M d, Y', strtotime($leave['end_date'])) ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Working Days</label>
                                <span style="font-size:16px; color:#1d4ed8;"><?= number_format($leave['working_days'], 1) ?> Days</span>
                            </div>
                            <div class="detail-item">
                                <label>Commutation</label>
                                <span><?= htmlspecialchars($leave['commutation']) ?></span>
                            </div>
                        </div>

                        <div class="section-divider" data-label="Approval Chain"></div>
                        <div class="signatures-chain">
                            <div class="sig-box">
                                <?php if($leave['applicant_signature']): ?>
                                    <img src="<?= $leave['applicant_signature'] ?>" class="sig-img">
                                <?php else: ?>
                                    <div class="sig-placeholder">E-Signed</div>
                                <?php endif; ?>
                                <div class="sig-line">
                                    <span class="sig-name"><?= htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']) ?></span>
                                    <span class="sig-title">Applicant</span>
                                </div>
                            </div>

                            <div class="sig-box">
                                <?php if($leave['authorized_officer_signature']): ?>
                                    <img src="<?= $leave['authorized_officer_signature'] ?>" class="sig-img">
                                <?php else: ?>
                                    <div class="sig-placeholder">Pending</div>
                                <?php endif; ?>
                                <div class="sig-line">
                                    <span class="sig-name"><?= htmlspecialchars($leave['authorized_officer_name'] ?: '---') ?></span>
                                    <span class="sig-title">Recommending Officer</span>
                                </div>
                            </div>

                            <div class="sig-box">
                                <?php if($leave['authorized_official_signature']): ?>
                                    <img src="<?= $leave['authorized_official_signature'] ?>" class="sig-img">
                                <?php else: ?>
                                    <div class="sig-placeholder">Pending</div>
                                <?php endif; ?>
                                <div class="sig-line">
                                    <span class="sig-name"><?= htmlspecialchars($leave['authorized_official_name'] ?: '---') ?></span>
                                    <span class="sig-title">Authorized Official</span>
                                </div>
                            </div>
                        </div>

                        <?php if($leave['status'] == 'Rejected'): ?>
                            <div class="section-divider" data-label="Disapproval Remarks"></div>
                            <div style="background: #fff1f2; padding: 15px; border-radius: 8px; border: 1px solid #fecdd3; color: #991b1b;">
                                <p style="margin:0; font-size: 14px;">
                                    <strong>Reason:</strong> <?= nl2br(htmlspecialchars($leave['rejection_reason'])) ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bottom-actions">
                    <button onclick="history.back()" class="back-btn" style="padding:10px 20px; cursor:pointer; background:white; border:1px solid #ccc; border-radius:6px;">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </button>
                    <button onclick="window.print()" class="print-btn">
                        <i class="fa-solid fa-print"></i> Print Application
                    </button>
                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>
    <script src="/HRIS/assets/js/script.js"></script>
</body>
</html>
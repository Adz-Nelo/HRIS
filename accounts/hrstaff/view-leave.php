<?php
session_start();
require_once '../../config/config.php';

// ✅ FIX 1: Access Control (Updated to include HR Staff and use role_name)
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

// --- DATA RETRIEVAL ---

$application_id = $_GET['id'] ?? null;

if (!$application_id) {
    header("Location: leave-history.php");
    exit();
}

try {
    // Comprehensive query joining Employee, Department, and Leave Types
    $stmt = $pdo->prepare("
        SELECT l.*, 
               e.first_name, e.last_name, e.middle_name, e.profile_pic, e.position AS emp_position,
               d.detailed_department_name,
               lt.name AS leave_type_name,
               lt.description AS leave_legal_basis
        FROM leave_application l
        JOIN employee e ON l.employee_id = e.employee_id
        LEFT JOIN detailed_department d ON e.detailed_department_id = d.detailed_department_id
        LEFT JOIN leave_types lt ON l.leave_type_id = lt.leave_types_id
        WHERE l.application_id = ?
    ");
    $stmt->execute([$application_id]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave) {
        die("Application not found.");
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    die("A database error occurred while fetching application details.");
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
        .view-header { margin-bottom: 25px; }
        .view-header h1 { font-size: 24px; color: #1e293b; margin: 0; }
        .view-header p { color: #64748b; margin: 5px 0 0; font-size: 14px; }

        .wide-card { background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .card-top-bar { padding: 25px 30px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }

        .user-profile-info { display: flex; align-items: center; gap: 15px; }
        .user-profile-info img { width: 65px; height: 65px; border-radius: 50%; object-fit: cover; }
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
        .sig-box { text-align: center; background: #fcfcfc; padding: 20px; border-radius: 8px; border: 1px solid #f1f5f9; }
        .sig-img { max-width: 140px; height: 50px; object-fit: contain; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; }
        .sig-placeholder { height: 50px; display: flex; align-items: center; justify-content: center; color: #cbd5e1; font-size: 11px; font-style: italic; }
        .sig-line { border-top: 1px solid #e2e8f0; margin-top: 10px; padding-top: 10px; }
        .sig-name { font-weight: 700; color: #1e293b; font-size: 13px; display: block; line-height: 1.2; }
        .sig-title { font-size: 10px; color: #64748b; text-transform: uppercase; font-weight: 700; margin-top: 4px; display: block; }

        /* Bottom Actions Styling */
        .bottom-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; padding-bottom: 50px; }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #fff; border: 1px solid #d1d5db; border-radius: 6px; color: #374151; text-decoration: none; font-size: 14px; font-weight: 600; transition: 0.2s; }
        .back-btn:hover { background: #f9fafb; border-color: #9ca3af; color: #1e293b; }
        .print-btn { background: #1d4ed8; color: #fff; border: none; padding: 10px 25px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; }
        .print-btn:hover { background: #1e40af; }

        @media print {
            .bottom-actions, #sidebar-placeholder, #topbar-placeholder, #rightbar-placeholder { display: none !important; }
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
                        <h1 style="font-size: 22px; margin-bottom: 0;">Leave Application History</h1>
                        <p style="color: #64748b; font-size: 14px; margin-top: 2px;">Review and manage employee leave submissions.</p>
                    </div>
                </div>

                <div class="wide-card">
                    <div class="card-top-bar">
                        <div class="user-profile-info">
                            <img src="<?= (!empty($leave['profile_pic'])) ? $leave['profile_pic'] : $default_profile_image ?>" alt="Profile">
                            <div class="user-details">
                                <h2><?= htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']) ?></h2>
                                <p><?= htmlspecialchars($leave['emp_position']) ?> • <?= htmlspecialchars($leave['detailed_department_name']) ?></p>
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
                        <div class="section-divider" data-label="Leave Application Details"></div>
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
                            <div class="detail-item">
                                <label>Commutation</label>
                                <span><?= htmlspecialchars($leave['commutation']) ?></span>
                            </div>
                        </div>

                        <div class="section-divider" data-label="Duration & Legal Basis"></div>
                        <div class="details-grid">
                            <div class="detail-item">
                                <label>Inclusive Dates</label>
                                <span><?= date('M d, Y', strtotime($leave['start_date'])) ?> — <?= date('M d, Y', strtotime($leave['end_date'])) ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Total Working Days</label>
                                <span style="font-size:16px; color:#1d4ed8;"><?= number_format($leave['working_days'], 1) ?> Days</span>
                            </div>
                            <div class="detail-item" style="grid-column: span 2;">
                                <label>Legal Basis</label>
                                <span style="font-weight:normal; color:#64748b; font-size:13px;"><?= htmlspecialchars($leave['leave_legal_basis'] ?: 'N/A') ?></span>
                            </div>
                        </div>

                        <div class="section-divider" data-label="Approval Chain"></div>
                        <div class="signatures-chain">
                            <div class="sig-box">
                                <?php if($leave['applicant_signature']): ?>
                                    <img src="<?= $leave['applicant_signature'] ?>" class="sig-img">
                                <?php else: ?>
                                    <div class="sig-placeholder">No Signature</div>
                                <?php endif; ?>
                                <div class="sig-line">
                                    <span class="sig-name"><?= htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']) ?></span>
                                    <span class="sig-title">Applicant Signature</span>
                                </div>
                            </div>

                            <div class="sig-box">
                                <?php if($leave['authorized_officer_signature']): ?>
                                    <img src="<?= $leave['authorized_officer_signature'] ?>" class="sig-img">
                                <?php else: ?>
                                    <div class="sig-placeholder">Pending Recommendation</div>
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
                                    <div class="sig-placeholder">Pending Approval</div>
                                <?php endif; ?>
                                <div class="sig-line">
                                    <span class="sig-name"><?= htmlspecialchars($leave['authorized_official_name'] ?: '---') ?></span>
                                    <span class="sig-title">Authorized Official</span>
                                </div>
                            </div>
                        </div>

                        <?php if($leave['status'] == 'Rejected'): ?>
                            <div class="section-divider" data-label="Disapproval Info"></div>
                            <div style="background: #fff1f2; padding: 20px; border-radius: 8px; border: 1px solid #fecdd3; color: #991b1b;">
                                <p style="margin:0; font-size: 14px;">
                                    <strong>Reason for Rejection:</strong><br>
                                    <?= nl2br(htmlspecialchars($leave['rejection_reason'])) ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bottom-actions">
                    <a href="leave-history.php" class="back-btn">
                        <i class="fa-solid fa-arrow-left"></i> Back to History
                    </a>
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
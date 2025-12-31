<?php
session_start();
require_once '../../config/config.php'; 

// Redirect if not logged in
if (!isset($_SESSION['employee_id']) || !isset($_GET['ref'])) {
    header("Location: track-leave.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];
$ref_no = $_GET['ref'];

// --- 1. FETCH LOGGED-IN USER DATA ---
$displayName = "User"; 
$displayRole = "Staff"; 
try {
    $stmtUser = $pdo->prepare("SELECT first_name, last_name, role FROM employee WHERE employee_id = ?");
    $stmtUser->execute([$employee_id]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $displayName = $user['first_name'];
        $displayRole = $user['role'];
    }
} catch (PDOException $e) {
    error_log("Error fetching user data: " . $e->getMessage());
}

// --- 2. FETCH SPECIFIC LEAVE DETAILS ---
try {
    // Selection includes staff validation details for the timeline
    $stmt = $pdo->prepare("
        SELECT la.*, lt.name as leave_type_name,
               v.first_name as validator_fname, v.last_name as validator_lname
        FROM leave_application la
        JOIN leave_types lt ON la.leave_type_id = lt.leave_types_id
        LEFT JOIN employee v ON la.cancel_hr_validated_by = v.employee_id
        WHERE la.reference_no = ? AND la.employee_id = ?
    ");
    $stmt->execute([$ref_no, $employee_id]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave) {
        header("Location: track-leave.php");
        exit();
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Helper variables for status checks
$status = $leave['status'];
$isCancelled = ($status === 'Cancelled');
$isRejected = ($status === 'Rejected');
$isPendingCancel = ($status === 'Cancellation Pending');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>View Application - <?= htmlspecialchars($ref_no) ?></title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">
    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        .wrapper.modal-blur-active { filter: blur(10px) grayscale(20%); pointer-events: none; user-select: none; transition: filter 0.4s ease; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .badge-orange { background: #fff7ed; color: #ea580c; border: 1px solid #fdba74; }
        .badge-red { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .badge-green { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        
        .info-card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
        .detail-item { background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .detail-item label { display: block; font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
        .detail-item p { margin: 0; font-size: 14px; font-weight: 600; color: #1e293b; }

        .timeline-container { position: relative; padding-left: 26px; margin-top: 20px; }
        .timeline-container::before { content: ''; position: absolute; left: 7px; top: 5px; bottom: 5px; width: 2px; background: #e2e8f0; }
        .timeline-point { position: relative; margin-bottom: 25px; }
        .timeline-point::after { content: ''; position: absolute; left: -28px; top: 4px; width: 14px; height: 14px; border-radius: 50%; background: #cbd5e1; border: 3px solid #fff; box-shadow: 0 0 0 2px #e2e8f0; }
        
        .timeline-point.completed::after { background: #22c55e; box-shadow: 0 0 0 2px #dcfce7; }
        .timeline-point.current::after { background: #3b82f6; box-shadow: 0 0 0 2px #dbeafe; animation: pulse 2s infinite; }
        .timeline-point.cancel-step::after { background: #dc2626; box-shadow: 0 0 0 2px #fee2e2; }

        .timeline-point strong { font-size: 14px; display: block; color: #1e293b; margin-bottom: 2px; }
        .timeline-point p { font-size: 12px; margin: 0; color: #64748b; line-height: 1.4; }
        .timeline-point .status-label { font-size: 10px; font-weight: 800; text-transform: uppercase; margin-top: 4px; display: inline-block; }

        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }

        .btn-withdraw { background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; padding: 8px 16px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; }
        .btn-withdraw:hover { background: #dc2626; color: white; }
    </style>
</head>

<body>
    <div class="wrapper" id="full-page-wrapper">
        <div id="sidebar-placeholder"></div>

        <main class="main-content" id="main-content">
            <div id="topbar-placeholder"></div>

            <div class="dashboard-wrapper">
                <div class="welcome-header">
                    <div class="welcome-text">
                        <a href="track-leave.php" style="text-decoration: none; font-size: 13px; color: var(--primary-color); font-weight: 600;">
                            <i class="fa-solid fa-arrow-left"></i> BACK TO TRACKER
                        </a>
                        <h1 style="margin-top: 10px;">Approval Progress</h1>
                        <p>Detailed tracking for Application <strong>#<?= htmlspecialchars($ref_no) ?></strong></p>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    <div class="feed-container">
                        <div class="content-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h2>Leave Information</h2>
                                <?php if($status == 'Pending'): ?>
                                    <button class="btn-withdraw" onclick="cancelApplication('<?= $ref_no ?>')">
                                        <i class="fa-solid fa-ban me-1"></i> WITHDRAW
                                    </button>
                                <?php endif; ?>
                            </div>

                            <div class="info-card-grid">
                                <div class="detail-item">
                                    <label>Leave Type</label>
                                    <p><?= htmlspecialchars($leave['leave_type_name']) ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>Inclusive Dates</label>
                                    <p><?= date('M d', strtotime($leave['start_date'])) ?> - <?= date('M d, Y', strtotime($leave['end_date'])) ?></p>
                                </div>
                                <div class="detail-item">
                                    <label>Working Days</label>
                                    <p><?= number_format($leave['working_days'], 1) ?> Days</p>
                                </div>
                                <div class="detail-item">
                                    <label>Current Status</label>
                                    <div>
                                        <?php 
                                            $badgeClass = 'badge-orange';
                                            if($isCancelled || $isRejected) $badgeClass = 'badge-red';
                                            if($status == 'Approved') $badgeClass = 'badge-green';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="announcement-item" style="margin-top: 20px; background: #fff; border: 1px solid #e2e8f0;">
                                <div class="ann-date">
                                    <span class="month">NOTE</span>
                                    <span class="day"><i class="fa-solid fa-paperclip"></i></span>
                                </div>
                                <div class="ann-text">
                                    <h4>Reason for Leave</h4>
                                    <p><?= !empty($leave['reason']) ? htmlspecialchars($leave['reason']) : 'No specific details provided.' ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="side-info-container">
                        <div class="content-card">
                            <div class="card-header">
                                <h2>Workflow Status</h2>
                            </div>
                            <div class="timeline-container">
                                <div class="timeline-point completed">
                                    <strong>Application Submitted</strong>
                                    <p>Employee has filed the request</p>
                                    <small class="text-muted"><?= date('M d, Y - h:i A', strtotime($leave['created_at'])) ?></small>
                                </div>

                                <?php if(!$isCancelled): ?>
                                    <?php $isReviewed = in_array($status, ['HR Staff Reviewed', 'Officer Recommended', 'Approved']); ?>
                                    <div class="timeline-point <?= $isReviewed ? 'completed' : ($status == 'Pending' ? 'current' : '') ?>">
                                        <strong>HR Staff Review</strong>
                                        <p>Reviewed by HR Staff</p>
                                        <span class="status-label" style="color: <?= $isReviewed ? '#22c55e' : '#3b82f6' ?>"><?= $isReviewed ? 'Completed' : 'Reviewing...' ?></span>
                                    </div>

                                    <?php $isRec = in_array($status, ['Officer Recommended', 'Approved']); ?>
                                    <div class="timeline-point <?= $isRec ? 'completed' : ($status == 'HR Staff Reviewed' ? 'current' : '') ?>">
                                        <strong>Officer Recommendation</strong>
                                        <p>Verification of leave credits</p>
                                        <span class="status-label"><?= $isRec ? 'Verified' : 'Pending' ?></span>
                                    </div>

                                    <?php $isApp = ($status == 'Approved'); ?>
                                    <div class="timeline-point <?= $isApp ? 'completed' : ($status == 'Officer Recommended' ? 'current' : '') ?>">
                                        <strong>Final Approval</strong>
                                        <p>HR Manager / Admin Head Sign-off</p>
                                        <span class="status-label"><?= $isApp ? 'Completed' : 'Pending' ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if($isPendingCancel): ?>
                                    <div class="timeline-point current cancel-step">
                                        <strong style="color:#dc2626;">Cancellation Review</strong>
                                        <p>HR is validating your request</p>
                                        <span class="status-label" style="color:#dc2626;">Processing recall...</span>
                                    </div>
                                <?php elseif($isCancelled): ?>
                                    <div class="timeline-point completed cancel-step">
                                        <strong style="color:#dc2626;">Cancelled & Recalled</strong>
                                        <p>HR Staff <strong><?= htmlspecialchars($leave['validator_fname'] . ' ' . $leave['validator_lname']) ?></strong> has approved the cancellation.</p>
                                        <p style="font-size: 11px; font-weight: 700; color: #16a34a; margin-top: 5px;">
                                            <i class="fa-solid fa-coins"></i> BALANCE RETURNED: <?= number_format($leave['working_days'], 1) ?> DAYS
                                        </p>
                                        <small class="text-muted"><?= date('M d, Y - h:i A', strtotime($leave['hr_staff_reviewed_at'])) ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="rightbar-placeholder"></div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/HRIS/assets/js/script.js"></script>
    <script>
    function cancelApplication(ref) {
        const fullWrapper = document.getElementById('full-page-wrapper');
        fullWrapper.classList.add('modal-blur-active');

        Swal.fire({
            title: 'Withdraw Leave?',
            text: "Are you sure you want to cancel this application? This cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Withdraw',
            backdrop: `rgba(0,0,10,0.2)`
        }).then((result) => {
            fullWrapper.classList.remove('modal-blur-active');
            if (result.isConfirmed) {
                window.location.href = 'process_cancel_leave.php?ref=' + ref;
            }
        });
    }
    </script>
</body>
</html>
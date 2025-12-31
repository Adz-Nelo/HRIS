<?php
session_start();
require_once '../../config/config.php'; 

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.html");
    exit();
}

if (!isset($_GET['ref'])) {
    header("Location: leave-history.php");
    exit();
}

$reference_no = $_GET['ref'];
$employee_id = $_SESSION['employee_id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            la.*, 
            lt.name as leave_type_name,
            e.first_name, e.last_name
        FROM leave_application la
        INNER JOIN leave_types lt ON la.leave_type_id = lt.leave_types_id
        INNER JOIN employee e ON la.employee_id = e.employee_id
        WHERE la.reference_no = ? AND la.employee_id = ?
    ");
    $stmt->execute([$reference_no, $employee_id]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave) {
        die("Record not found or access denied.");
    }

    $status = strtoupper($leave['status']);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    die("An error occurred fetching the details.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Details - <?= htmlspecialchars($reference_no) ?></title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">
    <link rel="stylesheet" href="/HRIS/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .dashboard-wrapper { gap: 15px !important; }
        .details-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 15px; align-items: start; }
        .info-group { margin-bottom: 20px; }
        .info-label { display: block; font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; margin-bottom: 5px; }
        .info-value { font-size: 14px; color: #1e293b; font-weight: 500; }
        
        /* Timeline Styling */
        .timeline { position: relative; padding-left: 30px; }
        .timeline::before { content: ''; position: absolute; left: 7px; top: 5px; bottom: 5px; width: 2px; background: #e2e8f0; }
        .timeline-item { position: relative; margin-bottom: 25px; }
        .timeline-dot { position: absolute; left: -30px; width: 16px; height: 16px; border-radius: 50%; background: #fff; border: 3px solid #cbd5e1; z-index: 2; }
        .timeline-dot.completed { border-color: #22c55e; background: #22c55e; }
        .timeline-dot.current { border-color: #3b82f6; }
        .timeline-dot.warning { border-color: #f59e0b; background: #f59e0b; }

        .timeline-content h4 { font-size: 13px; margin: 0; color: #1e293b; }
        .timeline-content p { font-size: 12px; margin: 2px 0; color: #64748b; }
        .timeline-content .time { font-size: 11px; color: #94a3b8; }

        .status-header-badge { padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; }
        .badge-approved { background: #dcfce7; color: #16a34a; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        .badge-cancelled { background: #f1f5f9; color: #64748b; }
        .badge-warning { background: #fffbeb; color: #92400e; border: 1px solid #fef3c7; }

        /* PDF Button Styling */
        .btn-view-pdf {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            padding: 6px 14px;
            border: 1px solid #3b82f6;
            border-radius: 6px;
            background: #eff6ff;
            color: #1d4ed8;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-view-pdf:hover {
            background: #dbeafe;
            border-color: #2563eb;
        }

        @media (max-width: 992px) { .details-grid { grid-template-columns: 1fr; } }
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
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <a href="leave-history.php" style="color: #64748b; text-decoration: none; font-size: 20px;">
                                <i class="fa-solid fa-arrow-left"></i>
                            </a>
                            <div>
                                <h1>Application Details</h1>
                                <p>Reference ID: #<?= htmlspecialchars($leave['reference_no']) ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="status-header-badge <?php 
                        if ($status === 'APPROVED') echo 'badge-approved';
                        elseif ($status === 'REJECTED') echo 'badge-rejected';
                        elseif ($status === 'CANCELLED') echo 'badge-cancelled';
                        elseif ($status === 'CANCELLATION PENDING') echo 'badge-warning';
                    ?>">
                        <i class="fa-solid <?= ($status === 'APPROVED') ? 'fa-check-circle' : 'fa-clock' ?>"></i> 
                        <?= ($status === 'CANCELLATION PENDING') ? 'CANCELLATION IN REVIEW' : $status ?>
                    </div>
                </div>

                <div class="details-grid">
                    <div class="content-card" style="padding: 25px;">
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; margin-bottom: 20px; padding-bottom: 15px;">
                            <h2 style="font-size: 16px;">Leave Information</h2>
                            <a href="generate_leave_pdf.php?ref=<?= urlencode($reference_no) ?>" target="_blank" class="btn-view-pdf">
                                <i class="fa-solid fa-file-pdf"></i> View PDF
                            </a>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="info-group">
                                <span class="info-label">Type of Leave</span>
                                <span class="info-value"><?= htmlspecialchars($leave['leave_type_name']) ?></span>
                            </div>
                            <div class="info-group">
                                <span class="info-label">Date Filed</span>
                                <span class="info-value"><?= date('M d, Y', strtotime($leave['date_filing'])) ?></span>
                            </div>
                            <div class="info-group">
                                <span class="info-label">Inclusive Dates</span>
                                <span class="info-value"><?= date('M d', strtotime($leave['start_date'])) ?> - <?= date('M d, Y', strtotime($leave['end_date'])) ?></span>
                            </div>
                            <div class="info-group">
                                <span class="info-label">Total Days</span>
                                <span class="info-value"><?= number_format($leave['working_days'], 3) ?> Working Days</span>
                            </div>
                        </div>

                        <div class="info-group" style="margin-top: 10px; padding: 15px; background: #f8fafc; border-radius: 8px;">
                            <span class="info-label">Reason / Purpose</span>
                            <span class="info-value" style="font-weight: 400; line-height: 1.6;">
                                <?= nl2br(htmlspecialchars(!empty($leave['details_description']) ? $leave['details_description'] : ($leave['other_leave_description'] ?? 'No reason provided'))) ?>
                            </span>
                        </div>
                    </div>

                    <div class="content-card side-info-card" style="padding: 20px;">
                        <div class="card-header" style="margin-bottom: 20px;">
                            <h2 style="font-size: 16px;">Approval Progress</h2>
                        </div>

                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-dot completed"></div>
                                <div class="timeline-content">
                                    <h4>Application Submitted</h4>
                                    <p>Employee has filed the request</p>
                                    <span class="time"><?= date('M d, Y - h:i A', strtotime($leave['date_filing'])) ?></span>
                                </div>
                            </div>

                            <div class="timeline-item">
                                <div class="timeline-dot completed"></div>
                                <div class="timeline-content">
                                    <h4>HR Staff Review</h4>
                                    <p>Reviewed by HR Staff</p>
                                    <span class="time" style="color: #22c55e;">Completed</span>
                                </div>
                            </div>

                            <div class="timeline-item">
                                <div class="timeline-dot completed"></div>
                                <div class="timeline-content">
                                    <h4>Officer Recommendation</h4>
                                    <p>Verification of leave credits</p>
                                    <span class="time" style="color: #22c55e;">Verified</span>
                                </div>
                            </div>

                            <div class="timeline-item">
                                <div class="timeline-dot completed"></div>
                                <div class="timeline-content">
                                    <h4>Final Approval</h4>
                                    <p>HR Manager / Admin Head Sign-off</p>
                                    <span class="time" style="color: #22c55e;">Completed</span>
                                </div>
                            </div>

                            <?php if ($status === 'CANCELLATION PENDING'): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot warning"></div>
                                <div class="timeline-content">
                                    <h4 style="color: #92400e;">Cancellation Review</h4>
                                    <p>HR is validating non-utilization proof</p>
                                    <span class="time" style="color: #f59e0b;">Review in Progress...</span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #f1f5f9;">
                             <?php if ($status === 'CANCELLATION PENDING'): ?>
                                <button class="btn-primary" disabled style="width: 100%; background: #f59e0b; border: none; padding: 10px; font-size: 12px; color: white; border-radius: 6px; cursor: not-allowed; font-weight: bold;">
                                    <i class="fa-solid fa-hourglass-half"></i> Reviewing Cancellation
                                </button>
                             <?php elseif ($status !== 'CANCELLED' && $status !== 'REJECTED'): ?>
                                <button class="btn-primary" onclick="goToCancellation('<?= $leave['reference_no'] ?>')" style="width: 100%; background: #dc2626; border: none; padding: 10px; font-size: 12px; color: white; border-radius: 6px; cursor: pointer; font-weight: bold;">
                                    <i class="fa-solid fa-trash-can"></i> Cancel Application
                                </button>
                             <?php endif; ?>
                             
                             <p style="font-size: 10px; color: #94a3b8; text-align: center; margin-top: 10px;">
                                <?= ($status === 'CANCELLATION PENDING') ? 'Waiting for HR to recall your leave credits.' : 'Cancellation triggers a balance recall process.' ?>
                             </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>

    <script>
        function goToCancellation(ref) {
            window.location.href = "cancel_application.php?ref=" + ref;
        }
    </script>
    <script src="/HRIS/assets/js/script.js"></script>
</body>
</html>
<?php
session_start();
require_once '../../config/config.php'; // Path to your $pdo connection

// 1. Validate Login
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.html");
    exit();
}

// 2. Fetch Application Data based on Reference No
$ref = $_GET['ref'] ?? '';
$employee_id = $_SESSION['employee_id'];
$leave = null;

if (!empty($ref)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM leave_application WHERE reference_no = ? AND employee_id = ?");
        $stmt->execute([$ref, $employee_id]);
        $leave = $stmt->fetch();
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

// 3. Define the Pending State
$current_status = $leave['status'] ?? '';
$is_pending = ($current_status === 'Cancellation Pending');

// Redirect if already fully cancelled or not found
if (!$leave || $current_status === 'Cancelled') {
    header("Location: leave_history.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Application - #<?= htmlspecialchars($ref) ?></title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">
    <link rel="stylesheet" href="/HRIS/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .dashboard-wrapper { gap: 15px !important; }
        .details-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 15px; align-items: start; }
        
        .form-group { margin-bottom: 20px; }
        .info-label { display: block; font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; margin-bottom: 8px; }
        
        .form-input { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #e2e8f0; 
            border-radius: 8px; 
            font-size: 14px; 
            color: #1e293b;
            background: #f8fafc;
            font-family: inherit;
        }
        .form-input:focus { outline: none; border-color: #3b82f6; background: #fff; }
        .form-input:disabled { cursor: not-allowed; opacity: 0.7; background: #f1f5f9; }

        .btn-submit-cancel { 
            background: #dc2626; 
            color: white; 
            border: none; 
            padding: 12px 25px; 
            border-radius: 6px; 
            font-weight: 700; 
            cursor: pointer; 
            display: inline-flex; 
            align-items: center; 
            gap: 10px;
            transition: all 0.3s ease;
        }

        /* Styling for the Pending State */
        .btn-pending {
            background: #f59e0b !important; /* Amber color */
            cursor: not-allowed !important;
            box-shadow: none;
        }

        .instruction-box {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .status-alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            line-height: 1.4;
        }
        .alert-warning { background: #fffbeb; border: 1px solid #fef3c7; color: #92400e; }

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
                            <a href="view_details.php?ref=<?= htmlspecialchars($ref) ?>" style="color: #64748b; text-decoration: none; font-size: 20px;">
                                <i class="fa-solid fa-arrow-left"></i>
                            </a>
                            <div>
                                <h1>Cancel Leave Application</h1>
                                <p>Reference ID: #<?= htmlspecialchars($ref) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="details-grid">
                    <div class="content-card" style="padding: 25px;">
                        <h2 style="font-size: 16px; margin-bottom: 20px; color: #1e293b;">Cancellation Request Form</h2>
                        
                        <?php if ($is_pending): ?>
                            <div class="status-alert alert-warning">
                                <i class="fa-solid fa-clock-rotate-left" style="font-size: 18px;"></i>
                                <div>
                                    <strong>Request in Review:</strong> Your cancellation request has been submitted. 
                                    Please wait for the HR Staff to validate your documents and recall your leave balance.
                                </div>
                            </div>
                        <?php endif; ?>

                        <form action="process_cancellation.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="reference_no" value="<?= htmlspecialchars($ref) ?>">

                            <div class="form-group">
                                <span class="info-label">Reason for Cancellation</span>
                                <textarea name="cancel_reason" class="form-input" rows="8" 
                                    placeholder="<?= $is_pending ? 'Request details submitted...' : 'Please explain why you are requesting cancellation...' ?>" 
                                    <?= $is_pending ? 'disabled' : 'required' ?>><?= htmlspecialchars($leave['cancel_reason'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group">
                                <span class="info-label">Upload Proof of Non-utilization</span>
                                <input type="file" name="cancel_attachment" class="form-input" accept=".pdf,.jpg,.jpeg,.png" <?= $is_pending ? 'disabled' : 'required' ?>>
                                <p style="font-size: 11px; color: #94a3b8; margin-top: 5px;">
                                    <?= $is_pending ? 'Your document has been uploaded for HR verification.' : 'Upload the required HR documents as mentioned in the instructions.' ?>
                                </p>
                            </div>

                            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                                <a href="view_details.php?ref=<?= htmlspecialchars($ref) ?>" class="btn-secondary" style="text-decoration: none; padding: 10px 20px; border: 1px solid #e2e8f0; border-radius: 6px; color: #64748b; font-size: 13px;">Back to Details</a>
                                
                                <?php if ($is_pending): ?>
                                    <button type="button" class="btn-submit-cancel btn-pending">
                                        <i class="fa-solid fa-hourglass-half"></i> Cancellation Pending...
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="btn-submit-cancel">
                                        <i class="fa-solid fa-ban"></i> Confirm Cancellation
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <div class="content-card side-info-card" style="padding: 20px;">
                        <h2 style="font-size: 14px; text-transform: uppercase; color: #94a3b8; margin-bottom: 15px;">HR Requirements</h2>
                        
                        <div style="background: #fff; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px;">
                            <p style="font-size: 13px; color: #1e293b; line-height: 1.6; margin: 0;">
                                <strong>Required Proof:</strong><br>
                                The document that serves as proof you have not taken leave from the Human Resource (HR) department is generally a:
                            </p>
                            
                            <div class="instruction-box">
                                <ul style="margin: 0; padding-left: 18px; font-size: 12.5px; color: #475569; line-height: 1.8;">
                                    <li><strong>Certificate of Leave of Absence Without Pay</strong></li>
                                    <li><strong>Service Record</strong> (with a corresponding <strong>Certificate of Leave Balances</strong>)</li>
                                </ul>
                            </div>
                        </div>

                        <div style="margin-top: 25px; padding: 15px; background: #fef2f2; border-radius: 8px;">
                            <h4 style="font-size: 12px; color: #991b1b; margin-bottom: 5px;"><i class="fa-solid fa-triangle-exclamation"></i> Important Note</h4>
                            <p style="font-size: 12px; color: #b91c1c; line-height: 1.4;">
                                Cancellation triggers a credit recall process. Leave credits will only be reinstated once HR verifies the submitted documents.
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
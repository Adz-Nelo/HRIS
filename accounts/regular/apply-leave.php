<?php
session_start();
require_once '../../config/config.php';

// --- FETCH LOGGED-IN USER DATA ---
$displayName = "User";
$displayRole = "Staff";

if (isset($_SESSION['employee_id'])) {
    try {
        $stmtUser = $pdo->prepare("SELECT first_name, last_name, role FROM employee WHERE employee_id = ?");
        $stmtUser->execute([$_SESSION['employee_id']]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $displayName = $user['first_name'];
            $displayRole = $user['role'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching user data: " . $e->getMessage());
    }
}

try {
    /**
     * LOGIC: Reset activity logs every day at 8:00 AM
     */
    $threshold = (date('H') < 8) ? date('Y-m-d 08:00:00', strtotime('yesterday')) : date('Y-m-d 08:00:00');

    $stmtLogs = $pdo->prepare("
        SELECT e.first_name, e.last_name, l.updated_at 
        FROM login_attempts l
        JOIN employee e ON l.employee_id = e.employee_id
        WHERE l.updated_at >= ?
        ORDER BY l.updated_at DESC
    ");
    $stmtLogs->execute([$threshold]);
    $recentLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $recentLogs = [];
}

$employee_id = $_SESSION['employee_id'];

// ============================
// GET EMPLOYEE INFO
// ============================
$stmt = $pdo->prepare("
    SELECT 
        e.*, 
        d.department_name,
        dd.detailed_department_name
    FROM employee e
    LEFT JOIN department d ON e.department_id = d.department_id
    LEFT JOIN detailed_department dd ON e.detailed_department_id = dd.detailed_department_id
    WHERE e.employee_id = ?
");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    exit("Error: Employee profile not found.");
}

$first_name = $employee['first_name'] ?? '';
$extension  = !empty($employee['extension']) ? ' ' . $employee['extension'] : '';
$middle_name_full = $employee['middle_name'] ?? '';
$last_name = $employee['last_name'] ?? '';
$position = $employee['position'] ?? 'N/A';
$salary = $employee['salary'] ?? '0.00';

$acronym = !empty($employee['department_name']) ?
    implode('', array_map(fn($w) => strtoupper(substr($w, 0, 1)), explode(' ', $employee['department_name']))) : 'N/A';

// ==========================================
// FETCH DASHBOARD STATS
// ==========================================
$stmtBal = $pdo->prepare("SELECT vacation_leave, sick_leave FROM leave_balance WHERE employee_id = ? AND is_latest = 1 LIMIT 1");
$stmtBal->execute([$employee_id]);
$balance = $stmtBal->fetch(PDO::FETCH_ASSOC);
$availableCredits = ($balance) ? ($balance['vacation_leave'] + $balance['sick_leave']) : 0.000;

$stmtPend = $pdo->prepare("SELECT COUNT(*) FROM leave_application WHERE employee_id = ? AND status = 'Pending'");
$stmtPend->execute([$employee_id]);
$pendingRequests = $stmtPend->fetchColumn() ?: 0;

$stmtUsed = $pdo->prepare("SELECT SUM(working_days) FROM leave_application WHERE employee_id = ? AND status = 'Approved' AND YEAR(start_date) = YEAR(CURDATE())");
$stmtUsed->execute([$employee_id]);
$usedLeaves = $stmtUsed->fetchColumn() ?: 0.000;

// FETCH OPTIONS
$leave_types = $pdo->query("SELECT leave_types_id, name, description FROM leave_types WHERE status = 1 ORDER BY leave_types_id ASC")->fetchAll(PDO::FETCH_ASSOC);
$leave_details = $pdo->query("SELECT leave_details_id, leave_type_id, name, requires_specification FROM leave_details WHERE status = 1 ORDER BY leave_type_id ASC, leave_details_id ASC")->fetchAll(PDO::FETCH_ASSOC);

$date_of_filing = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Regular Employee Dashboard</title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css">
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
    <div class="wrapper">

        <div id="sidebar-placeholder"></div>

        <main class="main-content" id="main-content">
            <div id="topbar-placeholder"></div>

            <div class="dashboard-wrapper">
                <div class="welcome-header">
                    <div class="welcome-text">
                        <h1>Apply for Leave</h1>
                        <p>Submit a new leave application for <strong><?= htmlspecialchars($displayName) ?></strong> (<?= htmlspecialchars($displayRole) ?>). Ensure all details are correct.</p>
                    </div>
                    <div class="date-time-widget">
                        <div class="time" id="real-time">--:--:-- --</div>
                        <div class="date" id="real-date">Loading...</div>
                    </div>
                </div>


                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fas fa-calendar-check"></i></div>
                        <div class="stat-info">
                            <h3><?= number_format($availableCredits ?? 0, 3) ?></h3>
                            <p>Available Credits</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fas fa-clock"></i></div>
                        <div class="stat-info">
                            <h3><?= number_format($pendingRequests ?? 0) ?></h3>
                            <p>Pending Requests</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon orange"><i class="fas fa-exclamation-circle"></i></div>
                        <div class="stat-info">
                            <h3><?= number_format($usedLeaves ?? 0, 3) ?></h3>
                            <p>Used Leaves (Yearly)</p>
                        </div>
                    </div>
                </div>
                <div class="main-dashboard-grid" style="grid-template-columns: 1fr;">
                    <div class="feed-container">
                        <div class="content-card">
                            <div class="card-header" style="display: flex; justify-content: center; align-items: center;">
                                <h2 style="text-transform: uppercase; font-size: 2rem; font-weight: bold; color: #3b82f6; margin: 0;">
                                    <i class="fas fa-file-alt" style="margin-right: 10px;"></i> APPLICATION FOR LEAVE
                                </h2>
                            </div>

                            <form method="POST" action="Processing.php" id="leaveForm" enctype="multipart/form-data">
                                <fieldset style="border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                                    <legend style="padding: 0 10px; font-weight: bold; color: #3b82f6; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                        EMPLOYEE INFORMATION
                                    </legend>

                                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                                        <div class="form-group">
                                            <label style="display:block; margin-bottom:8px; font-weight:600; color: #475569; text-transform: uppercase;">OFFICE / DEPARTMENT</label>
                                            <input type="text" value="<?= htmlspecialchars($acronym) ?>" readonly style="background:#f8fafc; border:1px solid #e2e8f0; padding: 12px; border-radius: 6px; width:100%; color: #64748b;">
                                        </div>
                                        <div class="form-group">
                                            <label style="display:block; margin-bottom:8px; font-weight:600; color: #475569; text-transform: uppercase;">FULL NAME</label>
                                            <?php $full_display_name = strtoupper($last_name . ', ' . $first_name . ($middle_name_full ? ' ' . $middle_name_full : '') . $extension); ?>
                                            <input type="text" value="<?= htmlspecialchars($full_display_name) ?>" readonly style="background:#f8fafc; border:1px solid #e2e8f0; padding: 12px; border-radius: 6px; width:100%; color: #64748b;">
                                        </div>
                                    </div>

                                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                                        <div class="form-group">
                                            <label style="display:block; margin-bottom:8px; font-weight:600; color: #475569; text-transform: uppercase;">DATE OF FILING</label>
                                            <input type="date" name="date_of_filing" value="<?= htmlspecialchars($date_of_filing) ?>" style="background:#f8fafc; border:1px solid #e2e8f0; padding: 12px; border-radius: 6px; width:100%; color: #64748b;">
                                        </div>
                                        <div class="form-group">
                                            <label style="display:block; margin-bottom:8px; font-weight:600; color: #475569; text-transform: uppercase;">POSITION</label>
                                            <input type="text" name="position" value="<?= htmlspecialchars($position) ?>" style="background:#f8fafc; border:1px solid #e2e8f0; padding: 12px; border-radius: 6px; width:100%; color: #64748b;">
                                        </div>
                                        <div class="form-group">
                                            <label style="display:block; margin-bottom:8px; font-weight:600; color: #475569; text-transform: uppercase;">SALARY</label>
                                            <input type="text" name="salary" value="<?= htmlspecialchars($salary) ?>" style="background:#f8fafc; border:1px solid #e2e8f0; padding: 12px; border-radius: 6px; width:100%; color: #64748b;">
                                        </div>
                                    </div>
                                </fieldset>

                                <fieldset style="border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                                    <legend style="padding: 0 10px; font-weight: bold; color: #3b82f6; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                        Details of Application
                                    </legend>

                                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                                        <div class="form-group">
                                            <label style="display:block; margin-bottom:8px; font-weight: 600; text-transform: uppercase;">TYPE OF LEAVE TO BE AVAILED OF</label>
                                            <select id="leaveType" name="leave_type_id" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:5px;">
                                                <option value="">-- Select Type --</option>
                                                <?php foreach ($leave_types as $type): ?>
                                                    <option value="<?= $type['leave_types_id'] ?>" data-description="<?= htmlspecialchars($type['description']) ?>">
                                                        <?= htmlspecialchars($type['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small id="leaveDescription" style="display:block; color: #0056b3; margin-top: 8px; font-style: italic; min-height: 1.2em;"></small>
                                        </div>

                                        <div class="form-group">
                                            <label style="display:block; margin-bottom:8px; font-weight: 600; text-transform: uppercase;">DETAILS OF LEAVE</label>
                                            <select id="detailsSelect" name="leave_detail_id" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:5px;">
                                                <option value="" data-leave-type="none">-- Select Detail --</option>
                                                <?php foreach ($leave_details as $detail): ?>
                                                    <option value="<?= $detail['leave_details_id'] ?>"
                                                        data-leave-type="<?= $detail['leave_type_id'] ?>"
                                                        data-requires-spec="<?= $detail['requires_specification'] ?>">
                                                        <?= htmlspecialchars($detail['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                                        <div class="form-group">
                                            <label style="display:block; margin-bottom:8px; font-weight:600; text-transform: uppercase;">NUMBER OF WORKING DAYS</label>
                                            <input type="number" id="number_of_days" name="number_of_days" readonly style="width:100%; padding:12px; border:1px solid #ddd; border-radius:5px;">
                                        </div>
                                        <div class="form-group">
                                            <label style="display:block; margin-bottom:8px; font-weight:600; text-transform: uppercase;">COMMUTATION</label>
                                            <select name="commutation" id="commutation" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:5px;">
                                                <option value="">-- Select Commutation --</option>
                                                <option value="Not Requested">Not Requested</option>
                                                <option value="Requested">Requested</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                                        <div class="form-group">
                                            <label style="display:block; margin-bottom:8px; font-weight:600; text-transform: uppercase;">INCLUSIVE DATE START</label>
                                            <input type="date" id="inclusive_start" name="inclusive_start" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:5px;">
                                        </div>
                                        <div class="form-group">
                                            <label style="display:block; margin-bottom:8px; font-weight:600; text-transform: uppercase;">INCLUSIVE DATE END</label>
                                            <input type="date" id="inclusive_end" name="inclusive_end" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:5px;">
                                        </div>
                                    </div>
                                </fieldset>

                                <div id="specWrapper" style="display: none; margin-bottom: 25px;">
                                    <label style="display:block; margin-bottom:8px; font-weight: 600;">Please Specify</label>
                                    <textarea name="specification" id="specification" rows="2" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:5px;" placeholder="Provide additional details here..."></textarea>
                                </div>

                                <input type="hidden" name="applicant_signature" id="applicant_signature" value="">
                                <input type="hidden" name="applicant_sign_date" id="applicant_sign_date" value="">

                                <div style="text-align: right; margin-top: 20px; display: flex; justify-content: flex-end; gap: 15px;">
                                    <button type="submit" name="save_draft" value="1" class="leave-save-btn" style="padding: 15px 40px; background: #6b7280; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 10px;">
                                        <i class="fas fa-save"></i> Save Draft
                                    </button>
                                    <button type="submit" name="submit_leave" value="1" class="leave-submit-btn" style="padding: 15px 40px; background: #2563eb; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 10px;">
                                        <i class="fas fa-paper-plane"></i> Submit Leave Application
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>






            <div class="content-container" id="contents-placeholder">
            </div>
    </div>
    </main>




    <div id="rightbar-placeholder"></div>
    </div>

    <script src="/HRIS/assets/js/script.js"></script>
</body>

</html>
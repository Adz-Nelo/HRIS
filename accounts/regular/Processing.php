<?php
// ======================
// SECURITY & CONFIG
// ======================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/config.php';

// Redirect to login.html if user is not logged in
if (empty($_SESSION['employee_id'])) {
    header("Location: ../../login.html?error=unauthorized");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employee_id      = $_SESSION['employee_id'];
    
    // Mapping form names to variables
    $leave_type_id    = !empty($_POST['leave_type_id']) ? (int)$_POST['leave_type_id'] : null;
    $leave_detail_id  = !empty($_POST['leave_detail_id']) ? (int)$_POST['leave_detail_id'] : null;
    
    // 'specification' from form maps to details_description in DB
    $details_description = trim($_POST['specification'] ?? ''); 
    
    // 'inclusive_start/end' from form
    $start_date       = $_POST['inclusive_start'] ?? null;
    $end_date         = $_POST['inclusive_end'] ?? null;
    
    // 'date_of_filing' from form
    $date_filing      = $_POST['date_of_filing'] ?? date('Y-m-d');
    $commutation      = $_POST['commutation'] ?? 'Not Requested';

    // ============================
    // VALIDATION
    // ============================
    $errors = [];

    if (!$leave_type_id) {
        $errors[] = "Leave Type is required.";
    }

    if (!$start_date || !$end_date) {
        $errors[] = "Inclusive Start and End Dates are required.";
    }

    // Verify Leave Type exists
    if ($leave_type_id) {
        $stmt = $pdo->prepare("SELECT leave_types_id FROM leave_types WHERE leave_types_id = ?");
        $stmt->execute([$leave_type_id]);
        if (!$stmt->fetchColumn()) {
            $errors[] = "Invalid leave type selected.";
        }
    }

    // Preserve submitted values for repopulation
    $_SESSION['old_form'] = $_POST;

    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        header("Location: apply-leave.php");
        exit;
    }

    // ============================
    // CALCULATE WORKING DAYS
    // ============================
    try {
        $start = new DateTime($start_date);
        $end   = new DateTime($end_date);
        if ($start > $end) {
            $errors[] = "Start Date cannot be after End Date.";
            $_SESSION['form_errors'] = $errors;
            header("Location: apply-leave.php");
            exit;
        }

        $interval = new DatePeriod($start, new DateInterval('P1D'), (clone $end)->modify('+1 day'));
        $working_days = 0;
        foreach ($interval as $date) {
            if ($date->format('N') < 6) { // Mon-Fri
                $working_days++;
            }
        }
    } catch (Exception $e) {
        $_SESSION['form_errors'] = ["Invalid date format."];
        header("Location: apply-leave.php");
        exit;
    }

    // ============================
    // INSERT LEAVE APPLICATION
    // ============================
    // Generate Reference: LV-YEAR-RANDOM
    $ref_no = "LV-" . date('Y') . "-" . strtoupper(substr(md5(uniqid()), 0, 8));

    try {
        // Updated column list to match your standard schema
        $stmt = $pdo->prepare("
            INSERT INTO leave_application 
            (reference_no, employee_id, leave_type_id, leave_detail_id, details_description, working_days, start_date, end_date, date_filing, commutation, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
        ");
        
        $stmt->execute([
            $ref_no,
            $employee_id,
            $leave_type_id,
            $leave_detail_id ?: null,
            $details_description ?: null,
            $working_days,
            $start_date,
            $end_date,
            $date_filing,
            $commutation
        ]);

        $_SESSION['success_message'] = "Leave application submitted successfully. Reference No: $ref_no";
        unset($_SESSION['old_form']); 
        header("Location: apply-leave.php");
        exit;

    } catch (PDOException $e) {
        $_SESSION['form_errors'] = ["Database error: " . $e->getMessage()];
        header("Location: apply-leave.php");
        exit;
    }
}
?>
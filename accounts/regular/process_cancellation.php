<?php
session_start();
// This file defines $pdo
require_once '../../config/config.php'; 

// Redirect if not logged in
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reference_no'])) {
    $ref_no = $_POST['reference_no'];
    $reason = trim($_POST['cancel_reason']);
    $employee_id = $_SESSION['employee_id'];

    try {
        // 1. Check if application exists
        // CHANGED: Using $pdo instead of $conn
        $check_sql = "SELECT application_id, status FROM leave_application WHERE reference_no = ? AND employee_id = ?";
        $stmt = $pdo->prepare($check_sql);
        $stmt->execute([$ref_no, $employee_id]);
        $application = $stmt->fetch();

        if (!$application) {
            header("Location: leave_history.php?error=notfound");
            exit();
        }

        // Prevent resubmission
        if ($application['status'] === 'Cancellation Pending' || $application['status'] === 'Cancelled') {
            header("Location: view_details.php?ref=$ref_no&error=already_processed");
            exit();
        }

        // 2. Handle File Upload
        $upload_dir = '../../uploads/cancellation_proofs/'; 
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = $_FILES['cancel_attachment']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $new_filename = 'CANCEL_' . $ref_no . '_' . time() . '.' . $file_ext;
        $target_path = $upload_dir . $new_filename;

        // Simple validation
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($file_ext, $allowed)) {
            header("Location: cancel_application.php?ref=$ref_no&error=invalid_file");
            exit();
        }

        if (move_uploaded_file($_FILES['cancel_attachment']['tmp_name'], $target_path)) {
            
            // 3. Update Status to 'Cancellation Pending'
            // Using the columns we added to your schema
            $update_sql = "UPDATE leave_application 
                           SET status = 'Cancellation Pending', 
                               cancel_reason = ?, 
                               cancel_proof_path = ?, 
                               cancellation_requested_at = NOW(),
                               updated_at = NOW()
                           WHERE reference_no = ? AND employee_id = ?";
            
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$reason, $target_path, $ref_no, $employee_id]);

            // Success redirect - pointing back to your details page
            header("Location: view_details.php?ref=$ref_no&msg=cancel_submitted");
            exit();
        } else {
            throw new Exception("File upload failed. Check folder permissions.");
        }

    } catch (Exception $e) {
        error_log($e->getMessage());
        header("Location: cancel_application.php?ref=$ref_no&error=system_error");
        exit();
    }
} else {
    header("Location: leave_history.php");
    exit();
}
<?php
session_start();
require_once '../../config/config.php';

// Access Control
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role'], ['Admin', 'HR Officer', 'HR Staff'])) {
    header("Location: ../../login.php?error=unauthorized");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'];
    $vacation_leave = $_POST['vacation_leave'];
    $sick_leave = $_POST['sick_leave'];
    $remarks = $_POST['remarks'] ?? 'Manual Adjustment';
    $current_date = date('Y-m-d');

    try {
        $pdo->beginTransaction();

        // 1. Set all previous records for this employee to NOT latest
        $updateStmt = $pdo->prepare("UPDATE leave_balance SET is_latest = 0 WHERE employee_id = ?");
        $updateStmt->execute([$employee_id]);

        // 2. Insert the new adjusted record
        // We set is_latest = 1 and use the current date for month_year
        $insertStmt = $pdo->prepare("
            INSERT INTO leave_balance (
                employee_id, 
                month_year, 
                vacation_leave, 
                sick_leave, 
                remarks, 
                is_latest, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");

        $insertStmt->execute([
            $employee_id,
            $current_date,
            $vacation_leave,
            $sick_leave,
            $remarks
        ]);

        $pdo->commit();
        
        // Redirect back with success message
        header("Location: leave-balance.php?status=success");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error updating balance: " . $e->getMessage());
    }
} else {
    header("Location: leave-balance.php");
    exit();
}
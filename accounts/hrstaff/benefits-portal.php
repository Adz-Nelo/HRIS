<?php
require_once '../../config/config.php';
require_once '../../includes/session_helper.php';

if (!isLoggedIn()) {
    header('Location: ../../PHP/login.php');
    exit();
}

// Get employee benefits info
$employee_id = $_SESSION['employee_id'];
$stmt = $pdo->prepare("SELECT * FROM employee WHERE employee_id = ?");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();
?>

<div class="container">
    <h1><i class="fa-solid fa-info-circle"></i> Benefits Information Portal</h1>
    
    <div class="row">
        <!-- Current Benefits -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Your Current Benefits</h5>
                </div>
                <div class="card-body">
                    <div class="benefit-list">
                        <div class="benefit-item">
                            <i class="fa-solid fa-check-circle text-success"></i>
                            <span class="ms-2">Health Insurance (Active)</span>
                            <span class="badge bg-success float-end">Covered</span>
                        </div>
                        <div class="benefit-item">
                            <i class="fa-solid fa-check-circle text-success"></i>
                            <span class="ms-2">Retirement Plan (Active)</span>
                            <span class="badge bg-success float-end">Enrolled</span>
                        </div>
                        <div class="benefit-item">
                            <i class="fa-solid fa-times-circle text-danger"></i>
                            <span class="ms-2">Life Insurance</span>
                            <span class="badge bg-danger float-end">Not Enrolled</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Links -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="online-enrollment.php" class="btn btn-outline-primary">
                            <i class="fa-solid fa-user-check"></i> Enroll in Benefits
                        </a>
                        <a href="benefits-claims.php" class="btn btn-outline-success">
                            <i class="fa-solid fa-file-invoice-dollar"></i> File a Claim
                        </a>
                        <a href="manage-dependents.php" class="btn btn-outline-info">
                            <i class="fa-solid fa-users-between-lines"></i> Manage Dependents
                        </a>
                        <a href="benefits-documents.php" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-file-pdf"></i> View Documents
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
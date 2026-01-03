<?php
require_once '../../config/config.php';
require_once '../../includes/session_helper.php';

if (!isLoggedIn()) {
    header('Location: ../../PHP/login.php');
    exit();
}

// Include backend wrapper if needed
// include 'main.php';
?>

<div class="container">
    <h1><i class="fa-solid fa-user-check"></i> Online Benefits Enrollment</h1>
    
    <div class="card">
        <div class="card-header">
            <h5>Available Benefits Programs</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Health Insurance -->
                <div class="col-md-4 mb-3">
                    <div class="benefit-card">
                        <div class="benefit-icon">
                            <i class="fa-solid fa-heart-pulse fa-3x text-danger"></i>
                        </div>
                        <h5>Health Insurance</h5>
                        <p>Medical, dental, and vision coverage</p>
                        <button class="btn btn-primary">Enroll Now</button>
                    </div>
                </div>
                
                <!-- Retirement Plan -->
                <div class="col-md-4 mb-3">
                    <div class="benefit-card">
                        <div class="benefit-icon">
                            <i class="fa-solid fa-piggy-bank fa-3x text-warning"></i>
                        </div>
                        <h5>Retirement Plan</h5>
                        <p>401(k) and pension options</p>
                        <button class="btn btn-primary">Enroll Now</button>
                    </div>
                </div>
                
                <!-- Life Insurance -->
                <div class="col-md-4 mb-3">
                    <div class="benefit-card">
                        <div class="benefit-icon">
                            <i class="fa-solid fa-hand-holding-heart fa-3x text-success"></i>
                        </div>
                        <h5>Life Insurance</h5>
                        <p>Term and whole life coverage</p>
                        <button class="btn btn-primary">Enroll Now</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
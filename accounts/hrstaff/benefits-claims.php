<?php
require_once '../../config/config.php';
require_once '../../includes/session_helper.php';

if (!isLoggedIn()) {
    header('Location: ../../PHP/login.php');
    exit();
}
?>

<div class="container">
    <h1><i class="fa-solid fa-file-invoice-dollar"></i> Benefits Claims Processing</h1>
    
    <div class="card">
        <div class="card-header">
            <h5>Submit New Claim</h5>
        </div>
        <div class="card-body">
            <form>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Claim Type</label>
                            <select class="form-select">
                                <option>Medical Reimbursement</option>
                                <option>Dental Claim</option>
                                <option>Vision Care</option>
                                <option>Hospitalization</option>
                                <option>Medication</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Amount</label>
                            <input type="number" class="form-control" placeholder="Enter amount">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" rows="3" placeholder="Describe the claim..."></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Upload Supporting Documents</label>
                    <input type="file" class="form-control" multiple>
                    <small class="text-muted">Upload receipts, medical certificates, or other supporting documents</small>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fa-solid fa-paper-plane"></i> Submit Claim
                </button>
            </form>
        </div>
    </div>
</div>
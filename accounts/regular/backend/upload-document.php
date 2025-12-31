<?php
// When employee uploads documents (service record, loyalty form, etc.)
if ($upload_success) {
    $employee_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
    $document_type = $_POST['document_type']; // 'service_record', 'loyalty_form', 'benefit_claim'
    
    $titles = [
        'service_record' => 'Service Record Uploaded',
        'loyalty_form' => 'Loyalty Award Form Uploaded',
        'benefit_claim' => 'Benefit Claim Document Uploaded'
    ];
    
    $title = $titles[$document_type] ?? 'Document Uploaded';
    $message = $employee_name . " has uploaded a " . str_replace('_', ' ', $document_type);
    
    notifyAllHRStaff($title, $message, 'Document Upload');
}
?>
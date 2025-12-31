<?php
// Prevent any whitespace from corrupting the PDF output
ob_start();

// =========================
// 1. PATHING & AUTOLOAD
// =========================
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php'; 

use setasign\Fpdi\Fpdi;

$templatePath = __DIR__ . '/LEAVE.pdf'; 

// =========================
// 2. GET REFERENCE NUMBER
// =========================
if (!isset($_GET['ref'])) {
    die("Reference number (ref) is missing in the URL.");
}
$reference_no = $_GET['ref'];

// =========================
// 3. FETCH DATA
// =========================
try {
    $stmt = $pdo->prepare("
        SELECT 
            l.*, 
            e.first_name, e.middle_name, e.last_name, e.extension, e.suffix,
            e.position, e.salary,
            d.department_name, d.department_id AS acronym
        FROM leave_application l
        LEFT JOIN employee e ON l.employee_id = e.employee_id
        LEFT JOIN department d ON e.department_id = d.department_id
        WHERE l.reference_no = :ref
    ");

    $stmt->execute([':ref' => $reference_no]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die("No record found in database for Reference: " . htmlspecialchars($reference_no));
    }

    // Mapping variables
    $leave_type_id    = $data['leave_type_id'];
    $other_leave      = $data['other_leave_description'];
    $leave_detail_id  = $data['leave_detail_id'];
    $details_other    = $data['details_description'];
    $start_date       = $data['start_date'];
    $end_date         = $data['end_date'];
    $working_days     = $data['working_days'];
    $commutation      = $data['commutation'];
    
    $authOfficerName      = $data['authorized_officer_name'] ?? '';
    $authOfficerPosition  = $data['authorized_officer_position'] ?? '';
    $authOfficialName     = $data['authorized_official_name'] ?? '';
    $authOfficialPosition = $data['authorized_official_position'] ?? '';

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// =========================
// 4. GENERATE PDF
// =========================
if (!file_exists($templatePath)) {
    die("Error: The template file 'LEAVE.pdf' was not found.");
}

$pdf = new Fpdi();
$pageCount = $pdf->setSourceFile($templatePath);

try {
    // ---------------------------------------------------------
    // PAGE 1: FILLING DATA
    // ---------------------------------------------------------
    $tpl1 = $pdf->importPage(1);
    $size1 = $pdf->getTemplateSize($tpl1);
    $pdf->AddPage($size1['orientation'], [$size1['width'], $size1['height']]);
    $pdf->useTemplate($tpl1);

    $pdf->SetFont("Arial", "B", 9);

    // --- Fill Header ---
    $pdf->SetXY(28, 48);  
    $pdf->Write(5, strtoupper($data['acronym'] ?? ''));
    
    $pdf->SetXY(96, 48);  $pdf->Write(5, strtoupper($data['last_name']));
    $pdf->SetXY(128, 48); $pdf->Write(5, strtoupper($data['first_name']));
    $pdf->SetXY(158, 48); $pdf->Write(5, strtoupper($data['middle_name'] ?? ''));

    $pdf->SetXY(41, 56);  $pdf->Write(5, date("M d, Y", strtotime($data['date_filing'])));
    $pdf->SetXY(97, 56);  $pdf->Write(5, strtoupper($data['position'] ?? ''));
    $pdf->SetXY(165, 56); $pdf->Write(5, number_format($data['salary'] ?? 0, 2));

    // --- Leave Type Checkboxes ---
    $leaveTypes = [
        1 => [15.5, 77.1], 2 => [15.5, 82.4], 3 => [15.5, 88], 4 => [15.5, 93],
        5 => [15.5, 98], 6 => [15.5, 103], 7 => [15.5, 108], 8 => [15.5, 113],
        9 => [15.5, 118], 10 => [15.5, 123], 11 => [15.5, 128.5], 12 => [15.5, 133.5],
        13 => [15.5, 138.5]
    ];

    if (isset($leaveTypes[$leave_type_id])) {
        [$x, $y] = $leaveTypes[$leave_type_id];
        $pdf->SetFont('ZapfDingbats', '', 8);
        $pdf->SetXY($x, $y - 2);
        $pdf->Write(5, chr(52)); 
    }

    if (!empty($other_leave)) {
        $pdf->SetFont("Arial", "B", 8);
        $pdf->SetXY(27, 147.5);
        $pdf->Write(5, strtoupper($other_leave));
    }

    // --- Leave Details Checkboxes ---
    $leaveDetailCheckboxes = [
        1 => [114.5, 82.5], 2 => [114.5, 88], 3 => [114.5, 97.5], 4 => [114.5, 103],
        6 => [114.5, 133.5], 7 => [114.5, 139], 8 => [114.5, 149], 9 => [114.5, 154]
    ];

    if (!empty($leave_detail_id) && isset($leaveDetailCheckboxes[$leave_detail_id])) {
        [$x, $y] = $leaveDetailCheckboxes[$leave_detail_id];
        $pdf->SetFont('ZapfDingbats', '', 8);
        $pdf->SetXY($x, $y - 2);
        $pdf->Write(5, chr(52));
    }

    // --- Specific Details Text ---
    $leaveDetailTextPositions = [
        1 => [148, 81.2], 2 => [148, 86], 3 => [156, 97], 4 => [156, 102], 5 => [137, 117]
    ];

    if (!empty($leave_detail_id) && !empty($details_other) && isset($leaveDetailTextPositions[$leave_detail_id])) {
        [$tx, $ty] = $leaveDetailTextPositions[$leave_detail_id];
        $pdf->SetFont("Arial", "B", 8);
        $pdf->SetXY($tx, $ty);
        $pdf->MultiCell(55, 4, strtoupper($details_other));
    }

    // --- Working Days & Date Range ---
    $pdf->SetFont("Arial","B",8);
    $daysText = number_format($working_days, 1) . " DAY(S)";
    $pdf->SetXY(20,164); $pdf->Write(5, $daysText);

    $dateRange = date("M d, Y", strtotime($start_date)) . " - " . date("M d, Y", strtotime($end_date));
    $pdf->SetXY(20,174); $pdf->Write(5, strtoupper($dateRange));
    $pdf->SetXY(25,236); $pdf->Write(5, $working_days);

    // --- Commutation ---
    if ($commutation === 'Requested' || $commutation === 'Not Requested') {
        $x = 114.5;
        $y = ($commutation === 'Requested') ? 171 : 166;
        $pdf->SetFont('ZapfDingbats', '', 8);
        $pdf->SetXY($x, $y - 2);
        $pdf->Write(5, chr(52));
    }

    // --- Authorized Official & Officer ---
    // Officer (Left Side)
    if (!empty($authOfficerName)) {
        $pdf->SetFont("Arial", "B", 7);
        $pdf->SetXY(25, 218.5); 
        $pdf->Cell(70, 5, strtoupper($authOfficerName), 0, 1, 'C');
        $pdf->SetFont("Arial", "", 6);
        $pdf->SetX(25);
        $pdf->Cell(70, 1, $authOfficerPosition, 0, 1, 'C');
    }

    // Official (Right Side)
    if (!empty($authOfficialName)) {
        $pdf->SetFont("Arial", "B", 7);
        $pdf->SetXY(68, 258); 
        $pdf->Cell(70, 5, strtoupper($authOfficialName), 0, 1, 'C');
        $pdf->SetFont("Arial", "", 6);
        $pdf->SetXY(68, 262);
        $pdf->Cell(70, 2, $authOfficialPosition, 0, 1, 'C');
    }

    // ---------------------------------------------------------
    // PAGE 2: IMPORTING AUTOMATICALLY
    // ---------------------------------------------------------
    if ($pageCount >= 2) {
        $tpl2 = $pdf->importPage(2);
        $size2 = $pdf->getTemplateSize($tpl2);
        $pdf->AddPage($size2['orientation'], [$size2['width'], $size2['height']]);
        $pdf->useTemplate($tpl2);
    }

    // --- Final Output ---
    if (ob_get_length()) ob_end_clean();
    $pdf->Output("I", "Leave_Application_" . $reference_no . ".pdf");

} catch (Exception $e) {
    die("PDF Generation Error: " . $e->getMessage());
}
exit;
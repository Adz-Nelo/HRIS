<?php
// Start the session to manage the 2FA verification process
session_start();

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    exit;
}

// Ensure all required files and libraries are loaded
require_once __DIR__ . '/../config/config.php';
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use PragmaRX\Google2FA\Google2FA;

/* ==========================
   JSON RESPONSE HELPER
========================= */
function respond($status, $message = '', $redirect = '') {
    // Set content type header for JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'redirect' => $redirect
    ]);
    exit;
}

/* ==========================
   SEND EMAIL
========================= */
function sendVerificationEmail($email, $qrPath) {
    global $mailerConfig;

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $mailerConfig['host'];
        $mail->SMTPAuth   = true;
        // IMPORTANT: Use environment variables or a secure configuration for credentials
        $mail->Username   = 'noreplyhr007@gmail.com'; 
        $mail->Password   = 'zcnoxlhjyolghnxr'; // This should be an App Password, not the account password
        $mail->SMTPSecure = $mailerConfig['encryption'];
        $mail->Port       = $mailerConfig['port'];

        // Recipients
        $mail->setFrom('chalscastijon@gmail.com', 'HRMS Team');
        $mail->addAddress($email);
        
        // Attach the QR Code image
        $mail->addEmbeddedImage($qrPath, 'empqr');

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'HRMS 2FA Setup';

        // Email body: greeting above QR code
        $mail->Body = "
        <div style='font-family:Arial, sans-serif; color:#343a40; line-height:1.5;'>
            <h2 style='color:#0056b3; font-size:24px; margin-bottom:20px;'>HRMS 2FA Setup</h2>
            
            <p style='font-size:18px; font-weight:600; margin-bottom:20px;'>Dear User,</p>

            <p style='font-size:16px; margin-bottom:15px;'>
                To complete your registration and enable Two-Factor Authentication (2FA), please follow these steps:
            </p>
            <ol style='font-size:16px; margin-left:20px; padding-left:0;'>
                <li>Open your Google Authenticator or a similar 2FA app on your mobile device.</li>
                <li>Add a new account and choose the 'Scan a QR code' option.</li>
                <li>Scan the QR code below.</li>
                <li>Enter the 6-digit code from the app on the verification page to finalize your registration.</li>
            </ol>

            <div style='text-align:center; margin: 30px 0;'>
                <img src='cid:empqr' width='200' alt='QR Code for 2FA Setup' style='border: 1px solid #ccc; padding: 10px; border-radius: 5px;'>
            </div>

            <p style='font-size:14px; color:#555;'>
                **Important:** This QR code contains your secret key. Do not share it with anyone.
            </p>

            <p style='margin-top:20px;'>Best regards,<br><strong>HRMS Team</strong></p>
        </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error for debugging (remove for production)
        error_log("Mailer Error ({$email}): {$mail->ErrorInfo}");
        return false;
    }
}

/* ==========================
   ROUTER
========================= */
$action = $_POST['action'] ?? '';

/* ===== CHECK EMPLOYEE (Step 1) ===== */
if ($action === 'check_employee') {
    // Input validation
    if (!isset($_POST['employee_id']) || !is_numeric($_POST['employee_id'])) {
        respond('error', 'Invalid Employee ID format.');
    }
    
    $employee_id = (int)$_POST['employee_id'];

    global $pdo;
    $stmt = $pdo->prepare("SELECT employee_id, password FROM employee WHERE employee_id=?");
    $stmt->execute([$employee_id]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$emp) respond('error', 'Employee ID not found in the system.');
    // Check if the 'password' field is already set, indicating registration is complete
    if (!empty($emp['password'])) respond('error', 'Account already registered. Please log in.');

    respond('ok');
}

/* ===== REGISTER (Step 2 - Email & Password Submit) ===== */
if ($action === 'register') {
    // Input sanitization and validation
    $employee_id = filter_var($_POST['employee_id'], FILTER_VALIDATE_INT);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$employee_id || !$email || empty($password)) {
        respond('error', 'Invalid input for registration.');
    }

    // Check if employee exists and is not registered yet (a redundancy check)
    global $pdo;
    $stmt = $pdo->prepare("SELECT password FROM employee WHERE employee_id=?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) respond('error', 'Employee not found during final registration.');
    if (!empty($employee['password'])) respond('error', 'Account already registered.');

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // ===== GOOGLE 2FA SETUP =====
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();

    // Use employee's email for the QR Code's account name
    $qrPayload = $google2fa->getQRCodeUrl(
        'HRMS Bacolod', // Issuer Name (Company/Application Name)
        $email,        // Account Name (User's identifier, typically email)
        $secret        // Secret key
    );

    // Generate and save QR code image temporarily
    $qrDir = __DIR__ . '/qrcodes';
    if (!is_dir($qrDir)) mkdir($qrDir, 0777, true);

    $qrPath = $qrDir . "/emp-{$employee_id}-temp.png"; // Using temp to avoid overwriting existing if not needed
    
    try {
        (new PngWriter())->write(new QrCode($qrPayload))->saveToFile($qrPath);
    } catch (\Exception $e) {
        // If QR code generation fails, log and respond with an error
        error_log("QR Code Generation Failed: " . $e->getMessage());
        respond('error', 'Registration failed: Could not generate 2FA QR code.');
    }


    // Set expiry timestamp (e.g., 15 minutes) for the verification session
    $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Save all necessary data to the session for the next step (verification)
    $_SESSION['verify'] = [
        'employee_id' => $employee_id,
        'email' => $email,
        'password' => $hashed_password,
        'secret' => $secret,
        'expiry' => $expiry
    ];

    // Send QR code email
    if (!sendVerificationEmail($email, $qrPath)) {
        // Clean up the session data if email fails
        unset($_SESSION['verify']);
        // Delete the temporary QR code file
        if (file_exists($qrPath)) unlink($qrPath);
        respond('error', 'Failed to send verification email. Please contact support.');
    }

    // Clean up the temporary QR code file after successful email sending
    if (file_exists($qrPath)) unlink($qrPath);

    // Redirect to the verification page
    respond('ok', 'Verification email sent. Please check your inbox.', 'verify.html');
}


/* ===== RESEND 2FA CODE ===== */
if ($action === 'resend') {
    if (!isset($_SESSION['verify'])) {
        respond('error', 'Verification session expired. Please register again.');
    }

    $data = $_SESSION['verify'];

    // Re-generate the QR code image
    $qrDir = __DIR__ . '/qrcodes';
    if (!is_dir($qrDir)) mkdir($qrDir, 0777, true);
    $qrPath = $qrDir . "/emp-{$data['employee_id']}-resend.png";

    $google2fa = new Google2FA();
    $qrPayload = $google2fa->getQRCodeUrl(
        'HRMS Bacolod',
        $data['email'],
        $data['secret']
    );

    try {
        (new PngWriter())->write(new QrCode($qrPayload))->saveToFile($qrPath);
    } catch (\Exception $e) {
        error_log("QR Code Generation Failed (Resend): " . $e->getMessage());
        respond('error', 'Failed to generate 2FA QR code for resend.');
    }

    // Send the QR code email again
    if (!sendVerificationEmail($data['email'], $qrPath)) {
        if (file_exists($qrPath)) unlink($qrPath);
        respond('error', 'Failed to send verification email. Please contact support.');
    }

    // Clean up temporary QR code file
    if (file_exists($qrPath)) unlink($qrPath);

    // Reset expiry to another 15 minutes
    $_SESSION['verify']['expiry'] = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    respond('ok', 'Verification code resent. Please check your email.');
}



/* ===== VERIFY CODE (On verify.html submit) ===== */
if ($action === 'verify') {
    if (!isset($_SESSION['verify'])) respond('error', 'Verification session expired. Please register again.');

    $input_code = trim($_POST['code'] ?? '');
    if (empty($input_code) || !is_numeric($input_code)) {
        respond('error', 'Invalid verification code format.');
    }

    $data = $_SESSION['verify'];

    // Check expiry
    if (new DateTime() > new DateTime($data['expiry'])) {
        unset($_SESSION['verify']);
        respond('error', '2FA verification expired. Please register again.');
    }

    $google2fa = new Google2FA();
    // Verify the code against the secret key
    if (!$google2fa->verifyKey($data['secret'], $input_code)) {
        respond('error', 'Invalid 2FA code. Please try again.');
    }

    // Code is valid - finalize registration and save to database
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            UPDATE employee 
            SET email=?, password=?, google2fa_secret=?, two_fa_enabled=1
            WHERE employee_id=?
        ");
        $stmt->execute([
            $data['email'], 
            $data['password'], 
            $data['secret'], 
            $data['employee_id']
        ]);

        // Clear the session data after successful registration
        unset($_SESSION['verify']);
        
        respond('ok', 'Registration complete. Redirecting to login...', 'login.html');

    } catch (\PDOException $e) {
        error_log("Database Update Failed: " . $e->getMessage());
        respond('error', 'A database error occurred during finalization.');
    }
}

// Fallback for an unknown action
respond('error', 'Invalid request action.'); 
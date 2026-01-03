<?php
class TwoFAHelper {
    
    // Generate a 6-digit verification code
    public static function generateCode() {
        return str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    // Send verification code via Email (simulated for localhost)
    public static function sendEmailVerification($email, $code, $employeeName) {
        $subject = 'HRIS Login Verification Code';
        $message = "
        <html>
        <head>
            <title>HRIS Verification Code</title>
            <style>
                body { font-family: Arial, sans-serif; }
                .code { 
                    font-size: 24px; 
                    font-weight: bold; 
                    color: #007bff;
                    padding: 10px;
                    background: #f8f9fa;
                    display: inline-block;
                    margin: 10px 0;
                }
            </style>
        </head>
        <body>
            <h2>Login Verification</h2>
            <p>Hello $employeeName,</p>
            <p>Your HRIS verification code is:</p>
            <div class='code'>$code</div>
            <p>This code will expire in 10 minutes.</p>
            <p>If you didn't request this, please ignore this email.</p>
            <hr>
            <p style='color: #666; font-size: 12px;'>
                This is an automated message from your HRIS system (localhost).
            </p>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: HRIS System <noreply@localhost.hris>' . "\r\n";
        
        // For localhost testing, we'll save to a file instead of actually sending
        if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
            // Save email to a file for testing
            $logDir = __DIR__ . '/../logs/';
            if (!is_dir($logDir)) mkdir($logDir, 0755, true);
            
            $logFile = $logDir . 'email_log_' . date('Y-m-d') . '.txt';
            $logEntry = "[" . date('Y-m-d H:i:s') . "] To: $email\nSubject: $subject\nCode: $code\n\n";
            file_put_contents($logFile, $logEntry, FILE_APPEND);
            
            // Also display on screen during development
            if (isset($_GET['debug'])) {
                echo "<div style='background:#e8f4f8;padding:10px;margin:10px;border-left:4px solid #007bff;'>
                    <strong>DEV MODE - Email would have been sent to:</strong> $email<br>
                    <strong>Verification Code:</strong> $code
                </div>";
            }
            
            return true;
        } else {
            // In production, actually send the email
            return mail($email, $subject, $message, $headers);
        }
    }
    
    // Simulate SMS sending (for localhost)
    public static function sendSMSVerification($phone, $code, $employeeName) {
        // For localhost, we'll just log the SMS
        $logDir = __DIR__ . '/../logs/';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        
        $logFile = $logDir . 'sms_log_' . date('Y-m-d') . '.txt';
        $logEntry = "[" . date('Y-m-d H:i:s') . "] To: $phone\nCode: $code\nMessage: Your HRIS verification code is $code. Valid for 10 minutes.\n\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        
        // Display on screen during development
        if (isset($_GET['debug'])) {
            echo "<div style='background:#e9ffe9;padding:10px;margin:10px;border-left:4px solid #28a745;'>
                <strong>DEV MODE - SMS would have been sent to:</strong> $phone<br>
                <strong>Verification Code:</strong> $code<br>
                <strong>Message:</strong> Hello $employeeName, your HRIS verification code is $code (expires in 10 minutes)
            </div>";
        }
        
        return true;
    }
    
    // Save verification attempt to database
    public static function saveVerification($employee_id, $code, $method, $contact_info) {
        global $conn;
        
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // Delete any previous unverified codes
        $stmt = $conn->prepare("DELETE FROM twofa_verification WHERE employee_id = ? AND verified = 0");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        
        // Insert new verification
        $stmt = $conn->prepare("INSERT INTO twofa_verification (employee_id, verification_code, method, contact_info, expires_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $employee_id, $code, $method, $contact_info, $expires_at);
        
        return $stmt->execute();
    }
    
    // Verify code from database
    public static function verifyCode($employee_id, $code) {
        global $conn;
        
        $stmt = $conn->prepare("SELECT * FROM twofa_verification WHERE employee_id = ? AND verification_code = ? AND verified = 0 AND expires_at > NOW()");
        $stmt->bind_param("is", $employee_id, $code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Check attempts
            if ($row['attempts'] >= 5) {
                return ['success' => false, 'message' => 'Too many failed attempts. Please request a new code.'];
            }
            
            // Mark as verified
            $updateStmt = $conn->prepare("UPDATE twofa_verification SET verified = 1, verified_at = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $row['id']);
            $updateStmt->execute();
            
            return ['success' => true, 'message' => 'Verification successful'];
        }
        
        // Increment attempts if code exists but wrong
        $stmt = $conn->prepare("UPDATE twofa_verification SET attempts = attempts + 1 WHERE employee_id = ? AND expires_at > NOW()");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        
        return ['success' => false, 'message' => 'Invalid or expired verification code'];
    }
    
    // Check if 2FA is required for user
    public static function is2FARequired($employee_id) {
        global $conn;
        
        $stmt = $conn->prepare("SELECT two_fa_enabled, two_fa_method FROM employee WHERE employee_id = ?");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row['two_fa_enabled'] == 1 && $row['two_fa_method'] != 'none';
        }
        
        return false;
    }
}
?>
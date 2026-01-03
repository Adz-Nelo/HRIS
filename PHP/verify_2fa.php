<?php
require_once '../config/config.php';

session_start();

// Check if 2FA verification is in progress
if (!isset($_SESSION['2fa_employee_id']) || !isset($_SESSION['2fa_method'])) {
    header('Location: login.php?error=2FA session expired');
    exit();
}

$employee_id = $_SESSION['2fa_employee_id'];
$method = $_SESSION['2fa_method'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    
    if (empty($code)) {
        $error = "Please enter the verification code";
    } else {
        try {
            // Check the code in database
            $stmt = $pdo->prepare("
                SELECT * FROM employee 
                WHERE employee_id = ? 
                AND two_fa_code = ?
                AND two_fa_code_expiry > NOW()
                AND two_fa_attempts < 3
            ");
            $stmt->execute([$employee_id, $code]);
            $employee = $stmt->fetch();
            
            if ($employee) {
                // Code is valid
                // Reset attempts
                $resetStmt = $pdo->prepare("
                    UPDATE employee 
                    SET two_fa_attempts = 0,
                        two_fa_code = NULL,
                        two_fa_code_expiry = NULL,
                        last_login = NOW()
                    WHERE employee_id = ?
                ");
                $resetStmt->execute([$employee_id]);
                
                // Set session for logged in user
                $_SESSION['employee_id'] = $employee_id;
                $_SESSION['role'] = $employee['role'];
                $_SESSION['first_name'] = $employee['first_name'];
                $_SESSION['last_name'] = $employee['last_name'];
                
                // Clear 2FA session
                unset($_SESSION['2fa_employee_id']);
                unset($_SESSION['2fa_method']);
                
                // Redirect based on role
                $redirects = [
                    'Admin' => '../accounts/admin/',
                    'HR Officer' => '../accounts/hrofficer/',
                    'HR Staff' => '../accounts/hrstaff/',
                    'Department Head' => '../accounts/depthead/',
                    'Regular Employee' => '../accounts/regular/'
                ];
                
                header('Location: ' . ($redirects[$employee['role']] ?? '../accounts/regular/'));
                exit();
            } else {
                // Invalid code, increment attempts
                $pdo->prepare("
                    UPDATE employee 
                    SET two_fa_attempts = two_fa_attempts + 1 
                    WHERE employee_id = ?
                ")->execute([$employee_id]);
                
                $error = "Invalid verification code";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Resend code if requested
if (isset($_GET['resend'])) {
    try {
        // Generate new code
        $new_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $stmt = $pdo->prepare("
            UPDATE employee 
            SET two_fa_code = ?,
                two_fa_code_expiry = ?,
                two_fa_attempts = 0
            WHERE employee_id = ?
        ");
        $stmt->execute([$new_code, $expiry, $employee_id]);
        
        // Get employee info for sending
        $empStmt = $pdo->prepare("SELECT email, contact_number FROM employee WHERE employee_id = ?");
        $empStmt->execute([$employee_id]);
        $employee = $empStmt->fetch();
        
        if ($method === 'email' && !empty($employee['email'])) {
            send2FAEmail($employee['email'], $new_code);
            $message = "New verification code sent to your email!";
        } elseif ($method === 'sms' && !empty($employee['contact_number'])) {
            send2FASMS($employee['contact_number'], $new_code);
            $message = "New verification code sent to your phone!";
        } else {
            // For localhost demonstration, show the code
            $message = "DEMO MODE: Your code is $new_code (Expires: " . date('H:i', strtotime($expiry)) . ")";
        }
    } catch (PDOException $e) {
        $error = "Failed to resend code: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Identity - HRMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        
        .verify-container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        
        .logo {
            max-width: 80px;
            margin-bottom: 20px;
        }
        
        h2 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .method-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .method-icon {
            font-size: 24px;
            margin-bottom: 10px;
            color: #007bff;
        }
        
        .code-inputs {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 25px 0;
        }
        
        .code-input {
            width: 50px;
            height: 60px;
            font-size: 24px;
            text-align: center;
            border: 2px solid #ddd;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .code-input:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }
        
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .resend-link {
            display: block;
            margin-top: 20px;
            color: #007bff;
            text-decoration: none;
        }
        
        .resend-link:hover {
            text-decoration: underline;
        }
        
        .demo-note {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 10px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 13px;
        }
        
        .error {
            color: #dc3545;
            background: #f8d7da;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .success {
            color: #28a745;
            background: #d4edda;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <img src="../assets/images/HRMS.png" alt="HRMS Logo" class="logo">
        <h2>Verify Your Identity</h2>
        <p>Enter the 6-digit code sent to your <?php echo $method; ?></p>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($message)): ?>
            <div class="success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="method-info">
            <div class="method-icon">
                <?php if ($method === 'email'): ?>
                    <i class="fas fa-envelope"></i>
                <?php else: ?>
                    <i class="fas fa-mobile-alt"></i>
                <?php endif; ?>
            </div>
            <p>Verification via <?php echo strtoupper($method); ?></p>
        </div>
        
        <form method="POST" action="">
            <div class="code-inputs">
                <input type="text" name="code" maxlength="6" pattern="\d{6}" 
                       title="Enter 6-digit code" required class="code-input"
                       placeholder="000000">
            </div>
            
            <button type="submit" class="btn">Verify & Continue</button>
        </form>
        
        <a href="?resend=true" class="resend-link">Resend Code</a>
        
        <div class="demo-note">
            <strong>Demo Mode:</strong> For educational purposes, codes are logged to server error log.
            Check your server logs or use "Resend Code" to see the generated code.
        </div>
    </div>
    
    <script>
        // Auto-focus on code input
        document.querySelector('.code-input').focus();
        
        // Auto-advance between inputs (if using multiple inputs)
        const inputs = document.querySelectorAll('.code-input');
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (e.target.value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });
            
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
                    inputs[index - 1].focus();
                }
            });
        });
    </script>
</body>
</html>
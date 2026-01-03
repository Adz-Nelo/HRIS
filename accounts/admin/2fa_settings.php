<?php
require_once '../../config/config.php';
require_once '../../includes/session_helper.php';

if (!isLoggedIn()) {
    header('Location: ../../PHP/login.php');
    exit();
}

$employee_id = $_SESSION['employee_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $method = $_POST['method'] ?? 'none';
    
    try {
        if ($action === 'enable') {
            // Generate a test code for demo purposes
            $demo_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Enable 2FA
            $stmt = $pdo->prepare("
                UPDATE employee 
                SET two_fa_enabled = 1,
                    two_fa_method = ?,
                    two_fa_code = ?,
                    two_fa_code_expiry = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
                WHERE employee_id = ?
            ");
            $stmt->execute([$method, $demo_code, $employee_id]);
            $success = "2FA has been enabled using " . strtoupper($method);
            $demo_message = "Demo Code: <strong>$demo_code</strong> (Valid for 10 minutes)";
            
        } elseif ($action === 'disable') {
            // Disable 2FA
            $stmt = $pdo->prepare("
                UPDATE employee 
                SET two_fa_enabled = 0,
                    two_fa_method = 'none',
                    two_fa_code = NULL,
                    two_fa_code_expiry = NULL,
                    two_fa_attempts = 0
                WHERE employee_id = ?
            ");
            $stmt->execute([$employee_id]);
            $success = "2FA has been disabled";
            
        } elseif ($action === 'test') {
            // Send test code
            $test_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            $stmt = $pdo->prepare("
                UPDATE employee 
                SET two_fa_code = ?,
                    two_fa_code_expiry = ?,
                    two_fa_attempts = 0
                WHERE employee_id = ?
            ");
            $stmt->execute([$test_code, $expiry, $employee_id]);
            
            $test_message = "Test code sent! Code: <strong>$test_code</strong> (Expires: " . date('H:i', strtotime($expiry)) . ")";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Get current 2FA status
$stmt = $pdo->prepare("SELECT two_fa_enabled, two_fa_method, email, contact_number, two_fa_code_expiry FROM employee WHERE employee_id = ?");
$stmt->execute([$employee_id]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - HRMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-light: #e9edff;
            --success-color: #2ec4b6;
            --warning-color: #ff9f1c;
            --danger-color: #e71d36;
            --dark-color: #2b2d42;
            --light-color: #f8f9fa;
            --border-radius: 12px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--dark-color);
        }

        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 2.5rem;
            color: var(--dark-color);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .header p {
            color: #6c757d;
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-left: 15px;
        }

        .badge-enabled {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 2px solid #28a745;
        }

        .badge-disabled {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 2px solid #dc3545;
        }

        .status-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 30px;
            margin-bottom: 30px;
            border-left: 6px solid var(--primary-color);
            transition: var(--transition);
        }

        .status-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .status-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .status-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            flex-shrink: 0;
        }

        .status-icon-enabled {
            background: linear-gradient(135deg, #2ec4b6, #20a198);
            color: white;
            animation: pulse 2s infinite;
        }

        .status-icon-disabled {
            background: linear-gradient(135deg, #adb5bd, #6c757d);
            color: white;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .status-content h2 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .method-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }

        .method-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            padding: 25px;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .method-card:hover:not(.disabled) {
            border-color: var(--primary-color);
            background: var(--primary-light);
            transform: translateY(-3px);
        }

        .method-card.selected {
            border-color: var(--primary-color);
            background: var(--primary-light);
            box-shadow: 0 8px 25px rgba(67, 97, 238, 0.15);
        }

        .method-card.disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .method-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-color);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .method-card.selected::before {
            transform: scaleX(1);
        }

        .method-icon {
            font-size: 40px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .method-card.disabled .method-icon {
            color: #adb5bd;
        }

        .method-info h3 {
            font-size: 1.3rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .method-info p {
            color: #6c757d;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .contact-detail {
            background: white;
            border-radius: 8px;
            padding: 12px 15px;
            margin-top: 15px;
            border: 1px solid #e9ecef;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            word-break: break-all;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 28px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-width: 200px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #3a56d4);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, #3a56d4, #2d46b8);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268, #495057);
            transform: translateY(-2px);
        }

        .btn-test {
            background: linear-gradient(135deg, var(--warning-color), #e68a00);
            color: white;
        }

        .btn-test:hover {
            background: linear-gradient(135deg, #e68a00, #cc7a00);
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        .info-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 30px;
            margin-top: 30px;
        }

        .info-card h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.4rem;
        }

        .steps {
            display: grid;
            gap: 20px;
            margin-bottom: 25px;
        }

        .step {
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }

        .step-number {
            width: 36px;
            height: 36px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
            font-size: 1.1rem;
        }

        .step-content h4 {
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .step-content p {
            color: #6c757d;
            font-size: 0.95rem;
        }

        .demo-note {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 2px solid #ffd43b;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-top: 25px;
        }

        .demo-note h4 {
            color: #856404;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .demo-note p {
            color: #856404;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .alert {
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            animation: fadeIn 0.5s ease;
            border-left: 6px solid;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border-left-color: #28a745;
            color: #155724;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            border-left-color: #dc3545;
            color: #721c24;
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            border-left-color: #17a2b8;
            color: #0c5460;
        }

        .back-link {
            text-align: center;
            margin-top: 30px;
        }

        .back-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .back-link a:hover {
            color: #3a56d4;
            transform: translateX(-5px);
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
                flex-direction: column;
                gap: 10px;
            }
            
            .badge {
                margin-left: 0;
            }
            
            .status-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                min-width: 100%;
            }
            
            .method-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-shield-alt"></i>
                Two-Factor Authentication
                <span class="badge <?php echo $user['two_fa_enabled'] ? 'badge-enabled' : 'badge-disabled'; ?>">
                    <i class="fas <?php echo $user['two_fa_enabled'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    <?php echo $user['two_fa_enabled'] ? 'ENABLED' : 'DISABLED'; ?>
                </span>
            </h1>
            <p>Add an extra layer of security to protect your HRMS account from unauthorized access</p>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success animate__animated animate__fadeInDown">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
                <?php if (isset($demo_message)): ?>
                    <div class="demo-note" style="margin-top: 15px;">
                        <?php echo $demo_message; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error animate__animated animate__shakeX">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($test_message)): ?>
            <div class="alert alert-info animate__animated animate__fadeInDown">
                <i class="fas fa-vial"></i>
                <?php echo $test_message; ?>
            </div>
        <?php endif; ?>

        <div class="status-card animate__animated animate__fadeInUp">
            <div class="status-header">
                <div class="status-icon <?php echo $user['two_fa_enabled'] ? 'status-icon-enabled' : 'status-icon-disabled'; ?>">
                    <i class="fas <?php echo $user['two_fa_enabled'] ? 'fa-shield-alt' : 'fa-shield'; ?>"></i>
                </div>
                <div class="status-content">
                    <h2>
                        Two-Factor Authentication is 
                        <?php if ($user['two_fa_enabled']): ?>
                            <span style="color: var(--success-color);">Active</span>
                        <?php else: ?>
                            <span style="color: var(--danger-color);">Inactive</span>
                        <?php endif; ?>
                    </h2>
                    <?php if ($user['two_fa_enabled']): ?>
                        <p>Your account is protected with <strong><?php echo strtoupper($user['two_fa_method']); ?></strong> verification</p>
                        <?php if ($user['two_fa_code_expiry']): ?>
                            <p style="font-size: 0.9rem; color: #6c757d; margin-top: 5px;">
                                <i class="fas fa-clock"></i> Next code expires at <?php echo date('H:i', strtotime($user['two_fa_code_expiry'])); ?>
                            </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>Enable 2FA to add an additional security layer to your account</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$user['two_fa_enabled']): ?>
                <form method="POST" action="">
                    <h3 style="margin-bottom: 20px; color: var(--dark-color);">Select Verification Method</h3>
                    
                    <div class="method-grid">
                        <!-- Email Option -->
                        <div class="method-card <?php echo empty($user['email']) ? 'disabled' : ''; ?>" 
                             onclick="<?php echo empty($user['email']) ? '' : 'selectMethod(\'email\')'; ?>" 
                             id="email-option">
                            <div class="method-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="method-info">
                                <h3>
                                    <input type="radio" name="method" value="email" id="email" 
                                           class="visually-hidden" 
                                           <?php echo empty($user['email']) ? 'disabled' : '' ?>>
                                    <label for="email" style="cursor: pointer;">
                                        Email Verification
                                        <?php if (empty($user['email'])): ?>
                                            <i class="fas fa-exclamation-triangle" style="color: var(--warning-color);"></i>
                                        <?php endif; ?>
                                    </label>
                                </h3>
                                <p>Receive a 6-digit code via email. Perfect if you regularly check your email.</p>
                                
                                <?php if (empty($user['email'])): ?>
                                    <div class="contact-detail" style="background: #fff3cd; border-color: #ffd43b;">
                                        <i class="fas fa-exclamation-circle"></i>
                                        Email address not configured in your profile
                                    </div>
                                <?php else: ?>
                                    <div class="contact-detail">
                                        <i class="fas fa-envelope"></i>
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- SMS Option -->
                        <div class="method-card <?php echo empty($user['contact_number']) ? 'disabled' : ''; ?>" 
                             onclick="<?php echo empty($user['contact_number']) ? '' : 'selectMethod(\'sms\')'; ?>" 
                             id="sms-option">
                            <div class="method-icon">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div class="method-info">
                                <h3>
                                    <input type="radio" name="method" value="sms" id="sms"
                                           class="visually-hidden"
                                           <?php echo empty($user['contact_number']) ? 'disabled' : '' ?>>
                                    <label for="sms" style="cursor: pointer;">
                                        SMS Verification
                                        <?php if (empty($user['contact_number'])): ?>
                                            <i class="fas fa-exclamation-triangle" style="color: var(--warning-color);"></i>
                                        <?php endif; ?>
                                    </label>
                                </h3>
                                <p>Receive a 6-digit code via text message. Ideal for quick access on your phone.</p>
                                
                                <?php if (empty($user['contact_number'])): ?>
                                    <div class="contact-detail" style="background: #fff3cd; border-color: #ffd43b;">
                                        <i class="fas fa-exclamation-circle"></i>
                                        Phone number not configured in your profile
                                    </div>
                                <?php else: ?>
                                    <div class="contact-detail">
                                        <i class="fas fa-phone"></i>
                                        <?php echo htmlspecialchars($user['contact_number']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="action" value="enable">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="enable-btn" disabled>
                            <i class="fas fa-shield-alt"></i>
                            Enable Two-Factor Authentication
                        </button>
                        
                        <?php if (!empty($user['email']) || !empty($user['contact_number'])): ?>
                            <button type="button" class="btn btn-test" onclick="testCurrentMethod()">
                                <i class="fas fa-vial"></i>
                                Send Test Code
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="alert" style="background: #fff3cd; border-left-color: #ffd43b;">
                        <div style="display: flex; align-items: flex-start; gap: 15px;">
                            <i class="fas fa-exclamation-triangle" style="color: #856404; font-size: 1.2rem; margin-top: 2px;"></i>
                            <div>
                                <h4 style="color: #856404; margin-bottom: 8px;">Security Notice</h4>
                                <p style="color: #856404; margin-bottom: 0;">
                                    Disabling 2FA will remove the extra security layer from your account. 
                                    Only proceed if you understand the risks or need to change your verification method.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <input type="hidden" name="action" value="disable">
                        <button type="submit" class="btn btn-secondary" 
                                onclick="return confirm('⚠️ SECURITY WARNING\n\nAre you sure you want to disable Two-Factor Authentication?\n\nThis will make your account less secure and easier to compromise.')">
                            <i class="fas fa-ban"></i>
                            Disable Two-Factor Authentication
                        </button>
                        
                        <button type="submit" name="action" value="test" class="btn btn-test">
                            <i class="fas fa-vial"></i>
                            Send Test Code
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="info-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
            <h3><i class="fas fa-info-circle"></i> How Two-Factor Authentication Works</h3>
            
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>Enter Your Credentials</h4>
                        <p>Log in with your employee ID and password as you normally would</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>Receive Verification Code</h4>
                        <p>A unique 6-digit code is sent to your selected method (email or SMS)</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4>Enter The Code</h4>
                        <p>Input the verification code on the next screen to confirm your identity</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h4>Access Granted</h4>
                        <p>Once verified, you'll be granted access to your HRMS account</p>
                    </div>
                </div>
            </div>
            
            <div class="demo-note">
                <h4><i class="fas fa-flask"></i> Educational Demo Information</h4>
                <p>
                    <strong>Note for Capstone Defense:</strong> This is a simulation for educational purposes. 
                    In a real implementation, codes would be sent via actual email/SMS services. 
                    For this demo, codes are generated and displayed on screen or logged to the server.
                    Use the "Send Test Code" button to see how the verification process works.
                </p>
            </div>
        </div>

        <div class="back-link">
            <a href="index.php">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>
    </div>

    <script>
        function selectMethod(method) {
            // Reset all cards
            document.querySelectorAll('.method-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Select clicked card
            const selectedCard = document.getElementById(method + '-option');
            if (!selectedCard.classList.contains('disabled')) {
                selectedCard.classList.add('selected');
                
                // Check the radio button
                document.getElementById(method).checked = true;
                
                // Enable the submit button
                const enableBtn = document.getElementById('enable-btn');
                if (enableBtn) {
                    enableBtn.disabled = false;
                    enableBtn.innerHTML = `<i class="fas fa-shield-alt"></i> Enable 2FA via ${method.toUpperCase()}`;
                }
            }
        }

        function testCurrentMethod() {
            const emailChecked = document.getElementById('email').checked;
            const smsChecked = document.getElementById('sms').checked;
            
            if (!emailChecked && !smsChecked) {
                alert('Please select a verification method first!');
                return;
            }
            
            const method = emailChecked ? 'email' : 'sms';
            const contactInfo = method === 'email' 
                ? document.querySelector('#email-option .contact-detail').textContent 
                : document.querySelector('#sms-option .contact-detail').textContent;
            
            if (confirm(`Send a test code to:\n\n${contactInfo}\n\nClick OK to send test verification code.`)) {
                // Create a hidden form for test
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = 'method';
                methodInput.value = method;
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'test';
                
                form.appendChild(methodInput);
                form.appendChild(actionInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Add some visual feedback on interaction
        document.addEventListener('DOMContentLoaded', function() {
            const methodCards = document.querySelectorAll('.method-card:not(.disabled)');
            methodCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    if (!this.classList.contains('selected')) {
                        this.style.transform = 'translateY(-3px)';
                    }
                });
                
                card.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('selected')) {
                        this.style.transform = 'translateY(0)';
                    }
                });
            });
            
            // Add keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (e.key === '1' || e.key === 'e') {
                    selectMethod('email');
                } else if (e.key === '2' || e.key === 's') {
                    selectMethod('sms');
                } else if (e.key === 'Enter' && document.getElementById('enable-btn') && 
                          !document.getElementById('enable-btn').disabled) {
                    document.getElementById('enable-btn').click();
                }
            });
            
            // Show keyboard shortcuts hint
            setTimeout(() => {
                const hint = document.createElement('div');
                hint.style.cssText = `
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    background: var(--dark-color);
                    color: white;
                    padding: 10px 15px;
                    border-radius: 8px;
                    font-size: 0.85rem;
                    z-index: 1000;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                    animation: fadeIn 0.5s ease;
                `;
                hint.innerHTML = `
                    <strong>Keyboard Shortcuts:</strong><br>
                    <kbd>E</kbd> or <kbd>1</kbd> - Select Email<br>
                    <kbd>S</kbd> or <kbd>2</kbd> - Select SMS<br>
                    <kbd>Enter</kbd> - Submit
                `;
                document.body.appendChild(hint);
                
                setTimeout(() => {
                    hint.style.opacity = '0';
                    hint.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => hint.remove(), 500);
                }, 5000);
            }, 1000);
        });
    </script>
</body>
</html>
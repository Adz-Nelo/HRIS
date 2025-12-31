<?php
// ==========================================================
// ✅ LOGIN VERIFICATION with Real-Time Status & Security Tracking
// ==========================================================

require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login.html");
    exit;
}

// Normalize employee ID (strip 'EMP-' if typed)
$employee_id = trim($_POST['employee_id'] ?? '');
$employee_id = preg_replace('/[^0-9]/', '', $employee_id); // only digits
$password    = $_POST['password'] ?? '';

if ($employee_id === '' || $password === '') {
    header("Location: ../login.html?error=" . urlencode("Please fill in all fields"));
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$maxAttempts = 5;

// Roles to track login attempts
$trackRoles = ['Regular Employee', 'HR Officer', 'HR Staff', 'Department Head', 'Admin'];

try {
    // 1. FETCH EMPLOYEE
    $stmt = $pdo->prepare("
        SELECT employee_id, first_name, last_name, password,
               department_id, role
        FROM employee
        WHERE employee_id = :employee_id
        LIMIT 1
    ");
    $stmt->execute([':employee_id' => $employee_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Initial check: if user doesn't exist at all
    if (!$user) {
        header("Location: ../login.html?error=" . urlencode("Invalid Employee ID"));
        exit;
    }

    $roleName = $user['role'];
    $failedAttempts = 0;

    // 2. CHECK SECURITY BLOCK (Failed Attempts)
    if (in_array($roleName, $trackRoles)) {
        $stmt = $pdo->prepare("
            SELECT attempts, access_status
            FROM login_attempts
            WHERE employee_id = :eid AND ip_address = :ip
            LIMIT 1
        ");
        $stmt->execute([':eid' => $employee_id, ':ip' => $ip]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($attempt) {
            $failedAttempts = (int)($attempt['attempts'] ?? 0);
            if ($attempt['access_status'] === 'Block' || $failedAttempts >= $maxAttempts) {
                header("Location: ../login.html?error=" . urlencode("Account blocked. Please contact HR."));
                exit;
            }
        }
    }

    // 3. PASSWORD VERIFICATION
    if (!password_verify($password, $user['password'])) {
        if (in_array($roleName, $trackRoles)) {
            $failedAttempts++;
            $access_status = ($failedAttempts >= $maxAttempts) ? 'Block' : 'N/A';
            
            // Log/Update Failed Attempt
            $stmt = $pdo->prepare("
                INSERT INTO login_attempts
                (employee_id, ip_address, attempts, last_login, status, access_status, user_agent)
                VALUES (:eid, :ip, :attempts, NOW(), 'Failed', :access, :ua)
                ON DUPLICATE KEY UPDATE
                    attempts = :attempts_upd,
                    last_login = NOW(),
                    status = 'Failed',
                    access_status = :access_upd,
                    user_agent = :ua_upd
            ");

            $stmt->execute([
                ':eid'          => $employee_id,
                ':ip'           => $ip,
                ':attempts'     => $failedAttempts,
                ':access'       => $access_status,
                ':ua'           => $userAgent,
                ':attempts_upd' => $failedAttempts,
                ':access_upd'   => $access_status,
                ':ua_upd'       => $userAgent
            ]);

            $remaining = $maxAttempts - $failedAttempts;
            $errorMsg = ($remaining > 0) 
                ? "Incorrect password. {$remaining} attempts remaining."
                : "Account blocked due to multiple failed attempts.";
        } else {
            $errorMsg = "Incorrect password.";
        }

        header("Location: ../login.html?error=" . urlencode($errorMsg));
        exit;
    }

    // 4. LOG SUCCESSFUL ATTEMPT
    if (in_array($roleName, $trackRoles)) {
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts
            (employee_id, ip_address, attempts, last_login, status, access_status, user_agent)
            VALUES (:eid, :ip, 0, NOW(), 'Success', 'Unblock', :ua)
            ON DUPLICATE KEY UPDATE
                attempts = 0,
                last_login = NOW(),
                status = 'Success',
                access_status = 'Unblock',
                user_agent = :ua_upd
        ");
        $stmt->execute([
            ':eid'    => $employee_id,
            ':ip'     => $ip,
            ':ua'     => $userAgent,
            ':ua_upd' => $userAgent
        ]);
    }

    // 5. TURN ON THE "REAL-TIME" STATUS LIGHT
    $stmtStatus = $pdo->prepare("
        UPDATE employee
        SET last_login = NOW(), 
            last_active = NOW()
        WHERE employee_id = :eid
    ");
    $stmtStatus->execute([':eid' => $employee_id]);

    // 6. START SESSION
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['employee_id']    = $employee_id;
    $_SESSION['first_name']     = $user['first_name'];
    $_SESSION['last_name']      = $user['last_name'];
    $_SESSION['department_id']  = $user['department_id'];
    $_SESSION['role_name']      = $roleName;

    // 7. ROLE-BASED REDIRECT
    $dashboards = [
        'Regular Employee' => 'regular/index.php',
        'Department Head'  => 'depthead/index.php',
        'HR Staff'         => 'hrstaff/index.php',
        'HR Officer'       => 'hrofficer/index.php',
        'Admin'            => 'admin/index.php'
    ];

    if (isset($dashboards[$roleName])) {
        header("Location: ../accounts/" . $dashboards[$roleName]);
    } else {
        header("Location: ../login.html?error=" . urlencode("No dashboard found for your role: $roleName"));
    }
    exit;

} catch (PDOException $e) {
    error_log("Login Database Error: " . $e->getMessage());
    header("Location: ../login.html?error=" . urlencode("System error. Try again later."));
    exit;
}
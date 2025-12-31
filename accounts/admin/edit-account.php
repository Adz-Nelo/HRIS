<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/config.php';

// ✅ FIX 1: Updated Access Control (role_name)
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'], ['Admin', 'HR Officer'])) {
    header("Location: ../../login.html");
    exit();
}

// ✅ FIX 2: REAL-TIME HEARTBEAT
try {
    $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?")
        ->execute([$_SESSION['employee_id']]);
} catch (PDOException $e) { /* silent fail */ }

$message = '';
$error = '';
$current_id = $_GET['id'] ?? '';

if (empty($current_id)) {
    header("Location: manage-accounts.php");
    exit();
}

$default_profile_image = '../../assets/images/default_user.png';

try {
    // 1. Fetch departments for the dropdown list
    $departments = $pdo->query("SELECT * FROM department ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch existing employee data
    $stmt = $pdo->prepare("SELECT e.*, d.department_name FROM employee e LEFT JOIN department d ON e.department_id = d.department_id WHERE e.employee_id = ?");
    $stmt->execute([$current_id]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$emp) {
        header("Location: manage-accounts.php");
        exit();
    }

    // 3. Handle Full Admin Update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_account'])) {
        $new_employee_id = trim($_POST['employee_id']); 
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $dept_id = $_POST['department_id'];
        $status = $_POST['status'];

        // ID Change Validation: Prevent duplicates if ID is being renamed
        if ($new_employee_id !== $current_id) {
            $checkID = $pdo->prepare("SELECT employee_id FROM employee WHERE employee_id = ?");
            $checkID->execute([$new_employee_id]);
            if ($checkID->rowCount() > 0) {
                $error = "The new Employee ID is already taken by another account.";
            }
        }

        if (empty($error)) {
            // Update logic (including the ID)
            $update_sql = "UPDATE employee SET 
                            employee_id = ?, 
                            first_name = ?, 
                            last_name = ?, 
                            email = ?, 
                            role = ?, 
                            department_id = ?, 
                            status = ? 
                           WHERE employee_id = ?";
            
            $update_stmt = $pdo->prepare($update_sql);
            if ($update_stmt->execute([$new_employee_id, $first_name, $last_name, $email, $role, $dept_id, $status, $current_id])) {
                
                // If ID changed, we must redirect to the new ID to prevent 404 on refresh
                if ($new_employee_id !== $current_id) {
                    header("Location: edit-account.php?id=" . urlencode($new_employee_id) . "&success=1");
                    exit();
                }
                
                $message = "Employee account updated successfully.";
                // Refresh local data to show new changes in the form
                $stmt->execute([$new_employee_id]);
                $emp = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = "Failed to update record. Please try again.";
            }
        }
    }
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}

$roles = ['Regular Employee', 'HR Officer', 'HR Staff', 'Department Head', 'Admin'];

// Handle URL success flag
if (isset($_GET['success'])) {
    $message = "Account updated successfully.";
}
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Edit Account - HRMS</title>

    <link rel="icon" type="image/x-icon" href="../../assets/images/HRMS.png">
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .dashboard-wrapper { gap: 15px !important; } 
        .content-card { padding: 0 !important; border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
        
        /* Circular Profile Styling */
        .profile-header-strip { display: flex; align-items: center; gap: 25px; background: #fff; padding: 30px; border-bottom: 1px solid #f1f5f9; }
        .profile-circle-container { position: relative; }
        .profile-img-circle { 
            width: 90px; height: 90px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 4px solid #fff; 
            box-shadow: 0 0 0 2px #3b82f6; /* Modern Blue Ring */
        }
        
        /* Form Layout */
        .form-body { padding: 30px; background: #fff; }
        .form-section-title { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .form-section-title::after { content: ""; flex: 1; height: 1px; background: #f1f5f9; }
        
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px 30px; margin-bottom: 40px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 12px; font-weight: 700; color: #334155; }
        .form-group input, .form-group select { padding: 11px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; color: #1e293b; transition: all 0.2s; }
        .form-group input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); outline: none; }

        .id-highlight { background: #f8fafc; font-family: 'Monaco', 'Consolas', monospace; font-weight: 600; color: #1d4ed8; }
        .full-width { grid-column: span 2; }

        .alert { margin: 20px 30px 0; padding: 14px 18px; border-radius: 8px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 12px; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .form-actions { padding: 25px 30px; background: #f8fafc; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 12px; }
        .btn-save { background: #1e293b; color: #fff; border: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-save:hover { background: #0f172a; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div id="sidebar-placeholder"></div>
        <main class="main-content">
            <div id="topbar-placeholder"></div>

            <div class="dashboard-wrapper">
                <div class="welcome-header">
                    <div class="welcome-text">
                        <h1 style="font-size: 24px; font-weight: 800; color: #0f172a;">Edit Account Profile</h1>
                        <p style="font-size: 14px; color: #64748b;">Full Administrative control over employee credentials and access level.</p>
                    </div>
                    <a href="manage-accounts.php" class="btn-secondary" style="padding: 10px 20px; font-size: 13px; background: #fff; color: #475569; border: 1px solid #e2e8f0; text-decoration: none; border-radius: 8px; display: flex; align-items: center; gap: 8px; font-weight: 600;">
                        <i class="fa-solid fa-chevron-left"></i> Cancel & Return
                    </a>
                </div>

                <div class="content-card">
                    <div class="profile-header-strip">
                        <div class="profile-circle-container">
                            <img src="<?= (!empty($emp['profile_pic'])) ? $emp['profile_pic'] : $default_profile_image ?>" class="profile-img-circle">
                        </div>
                        <div>
                            <h2 style="font-size: 22px; color: #0f172a; font-weight: 800; margin-bottom: 5px;"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></h2>
                            <div style="display: flex; gap: 15px; align-items: center;">
                                <span style="font-size: 13px; color: #64748b; font-weight: 500;"><i class="fa-solid fa-id-badge"></i> Current ID: <?= $emp['employee_id'] ?></span>
                                <span style="font-size: 13px; color: #64748b; font-weight: 500;"><i class="fa-solid fa-shield-halved"></i> Role: <?= $emp['role'] ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if($message || isset($_GET['success'])): ?>
                        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= $message ?: "Changes updated successfully." ?></div>
                    <?php endif; ?>
                    <?php if($error): ?>
                        <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= $error ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-body">
                            <span class="form-section-title">Identity & Authentication</span>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Employee ID (System ID)</label>
                                    <input type="text" name="employee_id" class="id-highlight" value="<?= htmlspecialchars($emp['employee_id']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>System Role</label>
                                    <select name="role" required>
                                        <?php foreach ($roles as $r): ?>
                                            <option value="<?= $r ?>" <?= ($emp['role'] == $r) ? 'selected' : '' ?>><?= $r ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" name="first_name" value="<?= htmlspecialchars($emp['first_name']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" value="<?= htmlspecialchars($emp['last_name']) ?>" required>
                                </div>
                            </div>

                            <span class="form-section-title">Organization & Status</span>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Work Email</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($emp['email']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Assigned Department</label>
                                    <select name="department_id" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $d): ?>
                                            <option value="<?= $d['department_id'] ?>" <?= ($emp['department_id'] == $d['department_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($d['department_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Account Status</label>
                                    <select name="status" required>
                                        <option value="Active" <?= ($emp['status'] == 'Active') ? 'selected' : '' ?>>Active</option>
                                        <option value="Inactive" <?= ($emp['status'] == 'Inactive') ? 'selected' : '' ?>>Inactive / Terminated</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-save">
                                <i class="fa-solid fa-cloud-arrow-up"></i> Update Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>

    <script src="../../assets/js/script.js"></script>
</body>
</html>
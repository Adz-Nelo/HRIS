<?php
session_start();
require_once '../../config/config.php';

// ✅ FIX 1: Access Control (Updated to role_name)
$allowed_roles = ['Admin', 'HR Officer', 'HR Staff'];
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'], $allowed_roles)) {
    header("Location: ../../login.html?error=unauthorized");
    exit();
}

// ✅ FIX 2: Heartbeat Update
try {
    $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?")
        ->execute([$_SESSION['employee_id']]);
} catch (PDOException $e) { 
    /* silent fail */ 
}

// Fetch departments for the dropdown
try {
    $dept_stmt = $pdo->query("SELECT * FROM department ORDER BY department_name ASC");
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Dept Fetch Error: " . $e->getMessage());
    die("Database Error: Could not load departments.");
}

$message = "";
$messageType = "";
$showModal = false;

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ✅ FIX 3: Robust Column Mapping
        // We ensure status is 'Active' by default and use role_name
        $sql = "INSERT INTO employee (
                    employee_id, first_name, middle_name, last_name, suffix, 
                    birth_date, gender, department_id, role_name, salary, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['employee_id'],
            $_POST['first_name'],
            $_POST['middle_name'] ?? null,
            $_POST['last_name'],
            $_POST['suffix'] ?? null,
            $_POST['birth_date'],
            $_POST['gender'],
            $_POST['department_id'],
            $_POST['role'] ?? 'Employee', // Maps to role_name
            $_POST['salary'] ?? 0
        ]);

        $showModal = true;
    } catch (PDOException $e) {
        error_log("Employee Creation Error: " . $e->getMessage());
        // Handle duplicate employee_id error specifically
        if ($e->getCode() == 23000) {
            $message = "Error: Employee ID already exists.";
        } else {
            $message = "Error: Could not save employee record.";
        }
        $messageType = "error";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee - HRMS</title>


    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .form-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 5px; }
        
        label { font-size: 13px; font-weight: 600; color: #475569; }
        input, select {
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            color: #1e293b;
            transition: all 0.2s;
        }
        input:focus, select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); outline: none; }
        
        .form-actions { margin-top: 30px; display: flex; gap: 12px; border-top: 1px solid #f1f5f9; padding-top: 25px; }
        .btn-save { background: #2563eb; color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .btn-save:hover { background: #1d4ed8; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        
        /* Modified Back Container to align RIGHT at the BOTTOM */
        .back-container { margin-top: 25px; display: flex; justify-content: flex-end; padding-bottom: 40px; }
        .btn-back { 
            text-decoration: none; color: #64748b; font-size: 13px; font-weight: 600; 
            display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;
            background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; transition: 0.2s;
        }
        .btn-back:hover { background: #f8fafc; color: #1e293b; border-color: #cbd5e1; }

        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }

        /* MODAL STYLES */
        .modal-blur-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(8px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .success-modal {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
            animation: slideUp 0.3s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-icon { font-size: 60px; color: #22c55e; margin-bottom: 20px; }
        .modal-title { font-size: 24px; font-weight: 800; color: #1e293b; margin-bottom: 10px; }
        .modal-desc { color: #64748b; margin-bottom: 30px; line-height: 1.5; }
        .btn-modal-close {
            background: #2563eb; color: white; border: none; padding: 12px 25px;
            border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; font-size: 15px;
        }
    </style>
</head>

<body>
    <div class="modal-blur-overlay" id="successModal" <?php if($showModal) echo 'style="display:flex;"'; ?>>
        <div class="success-modal">
            <div class="modal-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div class="modal-title">Success!</div>
            <div class="modal-desc">Employee record has been created successfully. The masterlist has been updated.</div>
            <button class="btn-modal-close" onclick="window.location.href='manage-employees.php'">Continue to Masterlist</button>
        </div>
    </div>

    <div class="wrapper">
        <div id="sidebar-placeholder"></div>

        <main class="main-content">
            <div id="topbar-placeholder"></div>

            <div class="dashboard-wrapper">
                <div class="welcome-header">
                    <div class="welcome-text">
                        <h1>Add New Employee</h1>
                        <p>Configure personal details, department assignment, and compensation.</p>
                    </div>
                </div>

                <div class="main-dashboard-grid" style="display: block;">
                    <?php if ($messageType === 'error'): ?>
                        <div class="alert alert-error">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <?= $message ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-card">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Employee ID <span style="color:red">*</span></label>
                                    <input type="number" name="employee_id" placeholder="Numeric ID" required>
                                </div>
                                <div class="form-group">
                                    <label>Birth Date</label>
                                    <input type="date" name="birth_date">
                                </div>

                                <div class="form-group">
                                    <label>First Name <span style="color:red">*</span></label>
                                    <input type="text" name="first_name" required>
                                </div>
                                <div class="form-group">
                                    <label>Middle Name</label>
                                    <input type="text" name="middle_name">
                                </div>
                                <div class="form-group">
                                    <label>Last Name <span style="color:red">*</span></label>
                                    <input type="text" name="last_name" required>
                                </div>
                                <div class="form-group">
                                    <label>Suffix</label>
                                    <input type="text" name="suffix" placeholder="e.g. Jr., Sr., III">
                                </div>

                                <div class="form-group">
                                    <label>Gender</label>
                                    <select name="gender">
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Department <span style="color:red">*</span></label>
                                    <select name="department_id" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= htmlspecialchars($dept['department_id']) ?>">
                                                <?= htmlspecialchars($dept['department_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>System Role</label>
                                    <select name="role">
                                        <option value="Regular Employee">Regular Employee</option>
                                        <option value="HR Officer">HR Officer</option>
                                        <option value="HR Staff">HR Staff</option>
                                        <option value="Department Head">Department Head</option>
                                        <option value="Admin">Admin</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Monthly Salary (Whole Number)</label>
                                    <input type="number" name="salary" placeholder="e.g. 25000">
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn-save">
                                    <i class="fa-solid fa-user-plus"></i> Save Employee Record
                                </button>
                                <button type="reset" style="background:none; border:none; color:#94a3b8; cursor:pointer; font-weight:600; font-size:14px; margin-left: 10px;">
                                    Clear Form
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="back-container">
                        <a href="manage-employees.php" class="btn-back">
                            <i class="fa-solid fa-arrow-left"></i> Back to Masterlist
                        </a>
                    </div>
                </div>
            </div>
        </main>

        <div id="rightbar-placeholder"></div>
    </div>

    <script src="/HRIS/assets/js/script.js"></script>
</body>
</html>
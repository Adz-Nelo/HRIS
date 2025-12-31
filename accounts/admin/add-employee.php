<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/config.php';

// Access Control
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'], ['Admin', 'HR Officer'])) {
    header("Location: ../../login.html");
    exit();
}

// REAL-TIME HEARTBEAT
try {
    $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?")
        ->execute([$_SESSION['employee_id']]);
} catch (PDOException $e) { /* silent fail */ }

$error = '';
$default_profile_image = '../../assets/images/default_user.png';

// --- BULK TEMPLATE DOWNLOADER ---
if (isset($_GET['download_format'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="employee_bulk_format.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['employee_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'department_id', 'role']);
    fputcsv($output, ['EMP-001', 'John', 'Quincy', 'Doe', '', 'ITD', 'Regular Employee']); 
    fclose($output);
    exit();
}

// 1. Fetch departments
try {
    $stmt = $pdo->query("SELECT department_id, department_name FROM department ORDER BY department_name ASC");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
    $error = "Failed to load departments: " . $e->getMessage();
}

$roles = ['Regular Employee', 'HR Officer', 'HR Staff', 'Department Head', 'Admin'];

// 2. Handle Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- SINGLE SAVE ---
    if (isset($_POST['save_employee'])) {
        $emp_id = trim($_POST['employee_id']);
        $fname  = trim($_POST['first_name']);
        $mname  = trim($_POST['middle_name']);
        $lname  = trim($_POST['last_name']);
        $suffix = trim($_POST['suffix']);
        $dept   = $_POST['department_id'];
        $role   = $_POST['role'];

        try {
            // Password column removed from query
            $sql = "INSERT INTO employee (employee_id, first_name, middle_name, last_name, suffix, department_id, role, status) 
                    VALUES (:id, :fname, :mname, :lname, :suffix, :dept, :role, 'Active')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $emp_id, 
                ':fname' => $fname, 
                ':mname' => $mname, 
                ':lname' => $lname, 
                ':suffix' => $suffix, 
                ':dept' => $dept, 
                ':role' => $role
            ]);
            header("Location: manage-accounts.php?success=1");
            exit();
        } catch (PDOException $e) {
            $error = ($e->getCode() == 23000) ? "Error: Employee ID already exists." : "Database Error: " . $e->getMessage();
        }
    }

    // --- BULK SAVE ---
    if (isset($_POST['save_bulk_employee']) && isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file']['tmp_name'];
        if ($_FILES['csv_file']['size'] > 0) {
            $handle = fopen($file, "r");
            fgetcsv($handle); // Skip header

            try {
                $pdo->beginTransaction();
                // Password column and placeholder removed
                $sql = "INSERT INTO employee (employee_id, first_name, middle_name, last_name, suffix, department_id, role, status) 
                        VALUES (?,?,?,?,?,?,?,'Active')";
                $stmt = $pdo->prepare($sql);
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if(!empty($data[0])) {
                        // Data mapping: ID, Fname, Mname, Lname, Suffix, Dept, Role
                        $stmt->execute([$data[0], $data[1], $data[2], $data[3], $data[4], $data[5], $data[6]]);
                    }
                }
                $pdo->commit();
                header("Location: manage-accounts.php?success=bulk");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Bulk Error: " . $e->getMessage();
            }
            fclose($handle);
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee - HRMS</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/HRMS.png">
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .dashboard-wrapper { gap: 15px !important; } 
        .content-card { padding: 0 !important; border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; background: #fff; }
        .profile-header-strip { display: flex; align-items: center; gap: 25px; background: #fff; padding: 30px; border-bottom: 1px solid #f1f5f9; }
        .profile-img-preview { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #fff; box-shadow: 0 0 0 2px #3b82f6; background: #f8fafc; }
        
        /* Tab Controls */
        .tab-navigation { display: flex; background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 0 30px; }
        .tab-btn { padding: 15px 25px; border: none; background: none; cursor: pointer; font-weight: 700; color: #64748b; font-size: 13px; border-bottom: 3px solid transparent; transition: 0.3s; }
        .tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        .form-body { padding: 30px; background: #fff; }
        .form-section-title { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .form-section-title::after { content: ""; flex: 1; height: 1px; background: #f1f5f9; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px 30px; margin-bottom: 40px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 12px; font-weight: 700; color: #334155; }
        .form-group input, .form-group select { padding: 11px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; color: #1e293b; transition: all 0.2s; }
        .id-highlight { background: #f8fafc; font-family: 'Monaco', 'Consolas', monospace; font-weight: 600; color: #1d4ed8; }
        .alert-error { margin: 20px 30px 0; padding: 14px 18px; border-radius: 8px; font-size: 14px; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; display: flex; align-items: center; gap: 12px; }
        .form-actions { padding: 25px 30px; background: #f8fafc; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 12px; }
        .btn-save { background: #1e293b; color: #fff; border: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        
        /* Bulk Instructions */
        .instruction-card { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 20px; margin-bottom: 30px; }
        .instruction-card h4 { color: #0369a1; font-size: 14px; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .instruction-card ul { padding-left: 20px; font-size: 13px; color: #0c4a6e; line-height: 1.6; }
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
                        <h1 style="font-size: 24px; font-weight: 800; color: #0f172a;">Employee Registration</h1>
                        <p style="font-size: 14px; color: #64748b;">Manage individual record entry or bulk CSV uploads.</p>
                    </div>
                    <a href="manage-accounts.php" class="btn-secondary" style="padding: 10px 20px; font-size: 13px; background: #fff; color: #475569; border: 1px solid #e2e8f0; text-decoration: none; border-radius: 8px; display: flex; align-items: center; gap: 8px; font-weight: 600;">
                        <i class="fa-solid fa-chevron-left"></i> Cancel
                    </a>
                </div>

                <div class="content-card">
                    <div class="profile-header-strip">
                        <img src="<?= $default_profile_image ?>" class="profile-img-preview">
                        <div>
                            <h2 style="font-size: 18px; color: #0f172a; font-weight: 700; margin-bottom: 2px;">Data Entry Mode</h2>
                            <p style="font-size: 13px; color: #64748b;">Choose between manual form or bulk file upload.</p>
                        </div>
                    </div>

                    <div class="tab-navigation">
                        <button class="tab-btn active" onclick="switchTab(event, 'single-panel')">Single Registration</button>
                        <button class="tab-btn" onclick="switchTab(event, 'bulk-panel')">Bulk Upload</button>
                    </div>

                    <?php if($error): ?>
                        <div class="alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= $error ?></div>
                    <?php endif; ?>

                    <div id="single-panel" class="tab-panel active">
                        <form method="POST">
                            <div class="form-body">
                                <span class="form-section-title">Identity Information</span>
                                <div class="form-grid">
                                    <div class="form-group" style="grid-column: span 2;">
                                        <label>Employee ID</label>
                                        <input type="text" name="employee_id" class="id-highlight" placeholder="Enter unique system ID" required autofocus>
                                    </div>
                                    <div class="form-group"><label>First Name</label><input type="text" name="first_name" required></div>
                                    <div class="form-group"><label>Middle Name</label><input type="text" name="middle_name" placeholder="Optional"></div>
                                    <div class="form-group"><label>Last Name</label><input type="text" name="last_name" required></div>
                                    <div class="form-group"><label>Suffix</label><input type="text" name="suffix" placeholder="e.g. Jr., III (Optional)"></div>
                                </div>

                                <span class="form-section-title">Organization Assignment</span>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Department</label>
                                        <select name="department_id" required>
                                            <option value="">Select Department</option>
                                            <?php foreach ($departments as $d): ?>
                                                <option value="<?= $d['department_id'] ?>"><?= htmlspecialchars($d['department_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>System Role</label>
                                        <select name="role" required>
                                            <?php foreach ($roles as $r): ?>
                                                <option value="<?= $r ?>"><?= $r ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="save_employee" class="btn-save">
                                    <i class="fa-solid fa-plus"></i> Register Employee
                                </button>
                            </div>
                        </form>
                    </div>

                    <div id="bulk-panel" class="tab-panel">
                        <div class="form-body">
                            <div class="instruction-card">
                                <h4><i class="fa-solid fa-circle-info"></i> Bulk Upload Instructions</h4>
                                <ul>
                                    <li>Download the <strong>CSV Format</strong> using the button below.</li>
                                    <li>Fill in the data: <strong>EmployeeID, FirstName, MiddleName, LastName, Suffix, DeptID, Role</strong>.</li>
                                    <li>Use numerical <strong>Department IDs</strong> (e.g., 1 for IT, 2 for HR).</li>
                                    <li>Roles must be: <em>Regular Employee, HR Officer, HR Staff, Department Head, or Admin</em>.</li>
                                </ul>
                                <a href="?download_format=1" class="btn-save" style="background:#fff; color:#1e293b; border:1px solid #cbd5e1; width:fit-content; margin-top:15px; padding:8px 20px;">
                                    <i class="fa-solid fa-download"></i> Download Format Template
                                </a>
                            </div>

                            <form method="POST" enctype="multipart/form-data">
                                <span class="form-section-title">Upload File</span>
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label>Select CSV File</label>
                                    <input type="file" name="csv_file" accept=".csv" required style="border: 2px dashed #e2e8f0; padding: 40px; text-align: center;">
                                </div>
                                <div class="form-actions" style="padding-left:0; padding-right:0; background:transparent; border:none;">
                                    <button type="submit" name="save_bulk_employee" class="btn-save">
                                        <i class="fa-solid fa-file-import"></i> Process Bulk Upload
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>  

    <script>
        // Simple Tab Switcher
        function switchTab(evt, panelId) {
            const panels = document.querySelectorAll('.tab-panel');
            const btns = document.querySelectorAll('.tab-btn');
            
            panels.forEach(p => p.classList.remove('active'));
            btns.forEach(b => b.classList.remove('active'));
            
            document.getElementById(panelId).classList.add('active');
            evt.currentTarget.classList.add('active');
        }
    </script>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
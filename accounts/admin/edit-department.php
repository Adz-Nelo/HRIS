<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/config.php';

// ✅ FIX 1: Access Control (Matching role_name)
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
$dept_id = $_GET['id'] ?? '';

if (empty($dept_id)) {
    header("Location: manage-departments.php"); 
    exit();
}

try {
    // 1. Fetch existing department data
    $stmt = $pdo->prepare("SELECT * FROM department WHERE department_id = ?");
    $stmt->execute([$dept_id]);
    $dept = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dept) {
        header("Location: manage-departments.php");
        exit();
    }

    // 2. Handle Update Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_department'])) {
        $new_dept_id = strtoupper(trim($_POST['department_id']));
        $dept_name = trim($_POST['department_name']);

        // Check for duplicates if ID is changing
        if ($new_dept_id !== $dept_id) {
            $check = $pdo->prepare("SELECT department_id FROM department WHERE department_id = ?");
            $check->execute([$new_dept_id]);
            if ($check->rowCount() > 0) {
                $error = "The new Department ID already exists.";
            }
        }

        if (empty($error)) {
            // Note: If employees are linked to this ID, ensure foreign key is set to ON UPDATE CASCADE
            $update_sql = "UPDATE department SET department_id = ?, department_name = ? WHERE department_id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            
            if ($update_stmt->execute([$new_dept_id, $dept_name, $dept_id])) {
                header("Location: edit-department.php?id=" . urlencode($new_dept_id) . "&success=1");
                exit();
            } else {
                $error = "Failed to update department details.";
            }
        }
    }
} catch (PDOException $e) {
    if ($e->getCode() == '23000') {
        $error = "Integrity Constraint Error: Ensure no employees are orphaned by this change.";
    } else {
        $error = "Database Error: " . $e->getMessage();
    }
}

// Handle Success Message
if (isset($_GET['success'])) {
    $message = "Department details updated successfully.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Department - <?= htmlspecialchars($dept['department_id']) ?></title>

    <link rel="icon" type="image/x-icon" href="../../assets/images/HRMS.png">
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .dashboard-wrapper { gap: 15px !important; } 
        .content-card { padding: 0 !important; border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; background: #fff; }
        
        /* Header Strip */
        .dept-header-strip { display: flex; align-items: center; gap: 25px; background: #fff; padding: 30px; border-bottom: 1px solid #f1f5f9; }
        .dept-icon-circle { 
            width: 70px; height: 70px; 
            border-radius: 50%; 
            background: #eff6ff;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid #3b82f6;
            color: #3b82f6; font-size: 28px;
        }
        
        /* Form Styling */
        .form-body { padding: 30px; }
        .form-section-title { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .form-section-title::after { content: ""; flex: 1; height: 1px; background: #f1f5f9; }
        
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px 30px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 12px; font-weight: 700; color: #334155; }
        .form-group input { padding: 11px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; color: #1e293b; }
        .form-group input:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }

        .id-highlight { background: #f8fafc; font-family: 'Monaco', 'Consolas', monospace; font-weight: 700; color: #1d4ed8; text-transform: uppercase; }

        .alert { margin: 20px 30px 0; padding: 14px 18px; border-radius: 8px; font-size: 14px; display: flex; align-items: center; gap: 12px; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .form-actions { padding: 25px 30px; background: #f8fafc; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 12px; }
        .btn-save { background: #1e293b; color: #fff; border: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; }
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
                        <h1 style="font-size: 24px; font-weight: 800; color: #0f172a;">Department Settings</h1>
                        <p style="font-size: 14px; color: #64748b;">Modify department identifiers and official naming conventions.</p>
                    </div>
                    <a href="department.php?dept_id=<?= urlencode($dept['department_id']) ?>" class="btn-secondary" style="padding: 10px 20px; font-size: 13px; background: #fff; color: #475569; border: 1px solid #e2e8f0; text-decoration: none; border-radius: 8px; display: flex; align-items: center; gap: 8px; font-weight: 600;">
                        <i class="fa-solid fa-chevron-left"></i> Cancel & Return
                    </a>
                </div>

                <div class="content-card">
                    <div class="dept-header-strip">
                        <div class="dept-icon-circle">
                            <i class="fa-solid fa-building-shield"></i>
                        </div>
                        <div>
                            <h2 style="font-size: 22px; color: #0f172a; font-weight: 800; margin-bottom: 5px;"><?= htmlspecialchars($dept['department_name']) ?></h2>
                            <span style="font-size: 13px; color: #64748b; font-weight: 500;"><i class="fa-solid fa-key"></i> System Code: <?= $dept['department_id'] ?></span>
                        </div>
                    </div>

                    <?php if(isset($_GET['success'])): ?>
                        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> Department updated successfully.</div>
                    <?php endif; ?>
                    <?php if($error): ?>
                        <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= $error ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-body">
                            <span class="form-section-title">Department Identity</span>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Department ID (Short Code)</label>
                                    <input type="text" name="department_id" class="id-highlight" value="<?= htmlspecialchars($dept['department_id']) ?>" required placeholder="e.g., HR, ACCT, IT">
                                    <small style="color: #94a3b8; font-size: 11px; margin-top: 4px;">This is the unique identifier used across the system.</small>
                                </div>
                                <div class="form-group">
                                    <label>Full Department Name</label>
                                    <input type="text" name="department_name" value="<?= htmlspecialchars($dept['department_name']) ?>" required placeholder="e.g., Human Resources Department">
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-save">
                                <i class="fa-solid fa-floppy-disk"></i> Save Changes
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
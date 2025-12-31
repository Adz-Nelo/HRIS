<?php
session_start();
require_once '../../config/config.php';

// ✅ FIX 1: Access Control (Standardized to role_name + HR Staff)
$allowed_roles = ['Admin', 'HR Officer', 'HR Staff'];
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'], $allowed_roles)) {
    header("Location: ../../login.html");
    exit();
}

// ✅ FIX 2: Heartbeat Update
try {
    $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?")
        ->execute([$_SESSION['employee_id']]);
} catch (PDOException $e) { 
    /* silent fail */ 
}

// Get employee ID from URL
$edit_id = $_GET['id'] ?? null;

if (!$edit_id) {
    header("Location: employee-list.php");
    exit();
}

$default_profile_image = '../../assets/images/default_user.png';
$show_success_modal = false;
$message = "";

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $sql = "UPDATE employee SET 
                first_name = ?, middle_name = ?, last_name = ?, extension = ?, suffix = ?, 
                birth_date = ?, gender = ?, department_id = ?, detailed_department_id = ?, 
                position = ?, role_name = ?, status = ?, salary = ?, email = ?, contact_number = ?
                WHERE employee_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['first_name'] ?? '', 
            $_POST['middle_name'] ?? '', 
            $_POST['last_name'] ?? '', 
            $_POST['extension'] ?? '', 
            $_POST['suffix'] ?? '',
            $_POST['birth_date'] ?? null, 
            $_POST['gender'] ?? '', 
            $_POST['department_id'] ?? null, 
            $_POST['detailed_department_id'] ?? null,
            $_POST['position'] ?? '', 
            $_POST['role_name'] ?? 'Employee', 
            $_POST['status'] ?? 'Active', 
            $_POST['salary'] ?? 0, 
            $_POST['email'] ?? '', 
            $_POST['contact_number'] ?? '',
            $edit_id
        ]);
        
        $show_success_modal = true;
        
        // Refresh data after update
        $stmt = $pdo->prepare("SELECT * FROM employee WHERE employee_id = ?");
        $stmt->execute([$edit_id]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Update Employee Error: " . $e->getMessage());
        $message = "<div class='alert alert-danger'>Update failed. Please check your data.</div>";
    }
} else {
    // --- INITIAL DATA FETCH ---
    try {
        $stmt = $pdo->prepare("SELECT * FROM employee WHERE employee_id = ?");
        $stmt->execute([$edit_id]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$emp) {
            die("Employee not found.");
        }
    } catch (PDOException $e) {
        error_log("Fetch Employee Error: " . $e->getMessage());
        die("Error: Unable to retrieve record.");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .profile-container { padding: 20px; }
        .profile-header-card { background: white; border-radius: 5px; padding: 30px; display: flex; align-items: center; gap: 35px; border: 1px solid #e2e8f0; box-shadow: 0 2px 10px rgba(0,0,0,0.02); margin-bottom: 25px; }
        .profile-main-img { width: 140px; height: 140px; border-radius: 5px; object-fit: cover; background: #f8fafc; }
        .header-info h1 { margin: 0; font-size: 26px; color: #1e293b; font-weight: 700; }
        .header-info p { margin: 4px 0; color: #64748b; font-size: 15px; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; }
        .info-card { background: white; border-radius: 5px; padding: 20px; border: 1px solid #e2e8f0; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .info-card h3 { font-size: 14px; font-weight: 700; color: var(--primary-color); margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; display: flex; align-items: center; gap: 10px; text-transform: uppercase; }
        .form-group { margin-bottom: 15px; display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; }
        .form-control { padding: 10px; border: 1px solid #e2e8f0; border-radius: 5px; font-size: 13px; color: #1e293b; outline: none; transition: border-color 0.2s; }
        .form-control:focus { border-color: var(--primary-color); }
        .action-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 30px; padding-bottom: 40px; }
        .btn-cancel, .btn-save { padding: 10px 20px; border-radius: 5px; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; cursor: pointer; transition: all 0.2s; }
        .btn-cancel { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .btn-save { background: var(--primary-color); color: white; border: none; }

        /* MODAL STYLES with FULL SCREEN BLUR */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.4); /* Dark translucent tint */
            backdrop-filter: blur(8px); /* Full screen blur */
            -webkit-backdrop-filter: blur(8px);
            display: none; /* Hidden by default */
            align-items: center;
            justify-content: center;
            z-index: 9999; /* Higher than sidebar and rightbar */
        }

        .modal-card {
            background: white;
            width: 90%;
            max-width: 400px;
            padding: 40px 30px;
            border-radius: 5px;
            text-align: center;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: modalPop 0.3s ease-out;
        }

        @keyframes modalPop {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-icon {
            width: 70px;
            height: 70px;
            background: #dcfce7;
            color: #16a34a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 20px;
        }

        .modal-card h2 { color: #1e293b; font-size: 22px; margin-bottom: 10px; }
        .modal-card p { color: #64748b; font-size: 14px; margin-bottom: 25px; line-height: 1.5; }

        .btn-modal-ok {
            background: var(--primary-color);
            color: white;
            padding: 12px 30px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: opacity 0.2s;
        }
        .btn-modal-ok:hover { opacity: 0.9; }

        @media (max-width: 850px) { .info-grid { grid-template-columns: 1fr; } }
    </style>
</head>

<body>
    <div class="modal-overlay" id="successModal">
        <div class="modal-card">
            <div class="modal-icon">
                <i class="fa-solid fa-check"></i>
            </div>
            <h2>Update Success!</h2>
            <p>Employee information has been successfully updated in the database.</p>
            <a href="view-employee.php?id=<?= $edit_id ?>" class="btn-modal-ok">Continue to Profile</a>
        </div>
    </div>

    <div class="wrapper">
        <div id="sidebar-placeholder"></div>

        <main class="main-content">
            <div id="topbar-placeholder"></div>

            <div class="profile-container">
                <form method="POST">
                    
                    <div class="profile-header-card">
                        <img src="<?= (!empty($emp['profile_pic']) && file_exists($emp['profile_pic'])) ? $emp['profile_pic'] : $default_profile_image ?>" class="profile-main-img">
                        <div class="header-info">
                            <p style="margin-bottom: 5px; font-weight: 700; color: var(--primary-color);">EDITING PROFILE</p>
                            <h1><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></h1>
                            <p><i class="fa-solid fa-id-badge"></i> Employee ID: #<?= htmlspecialchars($emp['employee_id']) ?></p>
                        </div>
                    </div>

                    <div class="info-grid">
                        <div class="info-card">
                            <h3><i class="fa-solid fa-user"></i> Personal Details</h3>
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($emp['first_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" class="form-control" value="<?= htmlspecialchars($emp['middle_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($emp['last_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Suffix / Extension</label>
                                <div style="display:flex; gap:10px;">
                                    <input type="text" name="suffix" class="form-control" placeholder="Suffix" value="<?= htmlspecialchars($emp['suffix']) ?>" style="flex:1;">
                                    <input type="text" name="extension" class="form-control" placeholder="Ext (Jr/Sr)" value="<?= htmlspecialchars($emp['extension']) ?>" style="flex:1;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Gender</label>
                                <select name="gender" class="form-control">
                                    <option value="Male" <?= $emp['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= $emp['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= $emp['gender'] == 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Birth Date</label>
                                <input type="date" name="birth_date" class="form-control" value="<?= $emp['birth_date'] ?>">
                            </div>
                        </div>

                        <div class="info-card">
                            <h3><i class="fa-solid fa-id-card"></i> Employment Info</h3>
                            <div class="form-group">
                                <label>Position</label>
                                <input type="text" name="position" class="form-control" value="<?= htmlspecialchars($emp['position']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Department ID</label>
                                <input type="text" name="department_id" class="form-control" value="<?= htmlspecialchars($emp['department_id']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Detailed Department ID</label>
                                <input type="text" name="detailed_department_id" class="form-control" value="<?= htmlspecialchars($emp['detailed_department_id']) ?>">
                            </div>
                            <div class="form-group">
                                <label>System Role</label>
                                <select name="role" class="form-control">
                                    <option value="Regular Employee" <?= $emp['role'] == 'Regular Employee' ? 'selected' : '' ?>>Regular Employee</option>
                                    <option value="HR Officer" <?= $emp['role'] == 'HR Officer' ? 'selected' : '' ?>>HR Officer</option>
                                    <option value="HR Staff" <?= $emp['role'] == 'HR Staff' ? 'selected' : '' ?>>HR Staff</option>
                                    <option value="Department Head" <?= $emp['role'] == 'Department Head' ? 'selected' : '' ?>>Department Head</option>
                                    <option value="Admin" <?= $emp['role'] == 'Admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Employment Status</label>
                                <select name="status" class="form-control">
                                    <option value="Active" <?= $emp['status'] == 'Active' ? 'selected' : '' ?>>Active</option>
                                    <option value="Inactive" <?= $emp['status'] == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="Retired" <?= $emp['status'] == 'Retired' ? 'selected' : '' ?>>Retired</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Salary (Monthly)</label>
                                <input type="number" name="salary" class="form-control" value="<?= $emp['salary'] ?>">
                            </div>
                        </div>

                        <div class="info-card">
                            <h3><i class="fa-solid fa-address-book"></i> Contact Details</h3>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($emp['email']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($emp['contact_number']) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="action-footer">
                        <a href="view-employee.php?id=<?= $edit_id ?>" class="btn-cancel"><i class="fa-solid fa-xmark"></i> Cancel</a>
                        <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>
    
    <script src="/HRIS/assets/js/script.js"></script>

    <script>
        // Trigger modal if PHP update was successful
        <?php if ($show_success_modal): ?>
            document.getElementById('successModal').style.display = 'flex';
        <?php endif; ?>
    </script>
</body>
</html>
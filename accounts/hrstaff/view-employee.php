<?php
session_start();
require_once '../../config/config.php';

// ✅ FIX 1: Access Control (Updated to role_name)
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

// --- DATA RETRIEVAL ---

// Get employee ID from URL
$view_id = $_GET['id'] ?? null;

if (!$view_id) {
    die("Employee ID not specified.");
}

$default_profile_image = '../../assets/images/default_user.png';

try {
    // Basic employee fetch (you might want to join with department later for the profile view)
    $stmt = $pdo->prepare("SELECT * FROM employee WHERE employee_id = ?");
    $stmt->execute([$view_id]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$emp) {
        die("Employee record not found.");
    }
} catch (PDOException $e) {
    error_log("Database Error in Employee View: " . $e->getMessage());
    die("Error: Unable to load employee details.");
}
?>






<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Profile - <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .profile-container { padding: 20px; }
        
        /* Profile Header Card */
        .profile-header-card {
            background: white;
            border-radius: 5px; 
            padding: 30px;
            display: flex;
            align-items: center;
            gap: 35px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            margin-bottom: 25px;
        }

        .profile-main-img {
            width: 140px;
            height: 140px;
            border-radius: 5px; 
            object-fit: cover;
            border: none; /* Border Removed */
            background: #f8fafc;
        }

        /* Status Badge placed above name */
        .status-badge-top {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 8px; /* Gap between badge and name */
        }

        .header-info h1 { 
            margin: 0; 
            font-size: 26px; 
            color: #1e293b; 
            font-weight: 700;
            line-height: 1.2;
        }
        
        .header-info p { 
            margin: 4px 0; 
            color: #64748b; 
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Information Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .info-card {
            background: white;
            border-radius: 5px; 
            padding: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }

        .info-card h3 {
            font-size: 14px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 20px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
        }

        .detail-row {
            display: flex;
            margin-bottom: 12px;
        }

        .detail-label {
            width: 140px;
            font-weight: 600;
            color: #64748b;
            font-size: 13px;
        }

        .detail-value {
            flex: 1;
            color: #1e293b;
            font-size: 13px;
            font-weight: 500;
        }

        /* Action Buttons */
        .action-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 30px;
            padding-bottom: 40px;
        }

        .btn-back, .btn-edit-profile {
            padding: 10px 20px;
            border-radius: 5px; 
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-back {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn-edit-profile {
            background: var(--primary-color);
            color: white;
            border: none;
        }

        .btn-back:hover { background: #e2e8f0; }
        .btn-edit-profile:hover { opacity: 0.9; }

        @media (max-width: 850px) {
            .info-grid { grid-template-columns: 1fr; }
            .profile-header-card { flex-direction: column; text-align: center; }
            .header-info p { justify-content: center; }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div id="sidebar-placeholder"></div>

        <main class="main-content">
            <div id="topbar-placeholder"></div>

            <div class="profile-container">
                
                <div class="profile-header-card">
                    <img src="<?= (!empty($emp['profile_pic']) && file_exists($emp['profile_pic'])) ? $emp['profile_pic'] : $default_profile_image ?>" class="profile-main-img">
                    <div class="header-info">
                        <span class="status-badge-top" style="background: <?= $emp['status'] == 'Active' ? 'rgba(49, 162, 76, 0.1)' : 'rgba(231, 76, 60, 0.1)' ?>; color: <?= $emp['status'] == 'Active' ? '#31a24c' : '#e74c3c' ?>;">
                            <?= htmlspecialchars($emp['status']) ?>
                        </span>
                        
                        <h1><?= htmlspecialchars($emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'][0].'. ' : '') . $emp['last_name'] . ' ' . $emp['suffix']) ?></h1>
                        
                        <p><i class="fa-solid fa-briefcase"></i> <?= htmlspecialchars($emp['position']) ?> • <?= htmlspecialchars($emp['department_id']) ?></p>
                        <p><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($emp['email']) ?></p>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-card">
                        <h3><i class="fa-solid fa-user"></i> Personal Details</h3>
                        <div class="detail-row"><div class="detail-label">Employee ID</div><div class="detail-value">#<?= htmlspecialchars($emp['employee_id']) ?></div></div>
                        <div class="detail-row"><div class="detail-label">Gender</div><div class="detail-value"><?= htmlspecialchars($emp['gender']) ?></div></div>
                        <div class="detail-row"><div class="detail-label">Birth Date</div><div class="detail-value"><?= $emp['birth_date'] ? date('F d, Y', strtotime($emp['birth_date'])) : 'Not Set' ?></div></div>
                        <div class="detail-row"><div class="detail-label">Contact Number</div><div class="detail-value"><?= htmlspecialchars($emp['contact_number'] ?: 'N/A') ?></div></div>
                    </div>

                    <div class="info-card">
                        <h3><i class="fa-solid fa-id-card"></i> Employment Info</h3>
                        <div class="detail-row"><div class="detail-label">Department ID</div><div class="detail-value"><?= htmlspecialchars($emp['department_id']) ?></div></div>
                        <div class="detail-row"><div class="detail-label">System Role</div><div class="detail-value"><strong><?= htmlspecialchars($emp['role']) ?></strong></div></div>
                        <div class="detail-row"><div class="detail-label">Salary Grade</div><div class="detail-value">₱ <?= number_format($emp['salary'], 2) ?></div></div>
                        <div class="detail-row"><div class="detail-label">Date Joined</div><div class="detail-value"><?= date('F d, Y', strtotime($emp['created_at'])) ?></div></div>
                    </div>

                    <div class="info-card">
                        <h3><i class="fa-solid fa-shield-halved"></i> Security & Access</h3>
                        <div class="detail-row"><div class="detail-label">2FA Status</div><div class="detail-value"><?= $emp['two_fa_enabled'] ? '<span class="text-green">Enabled</span>' : 'Disabled' ?></div></div>
                        <div class="detail-row"><div class="detail-label">Last Login</div><div class="detail-value"><?= $emp['last_login'] ? date('M d, Y | h:i A', strtotime($emp['last_login'])) : 'Never' ?></div></div>
                    </div>

                    <div class="info-card">
                        <h3><i class="fa-solid fa-signature"></i> Approval Info</h3>
                        <div class="detail-row"><div class="detail-label">Official Name</div><div class="detail-value"><?= htmlspecialchars($emp['authorized_official_name'] ?: 'N/A') ?></div></div>
                        <div class="detail-row"><div class="detail-label">Sign Date</div><div class="detail-value"><?= $emp['authorized_official_sign_date'] ?: 'N/A' ?></div></div>
                    </div>
                </div>

                <div class="action-footer">
                    <a href="manage-employees.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to List</a>
                    <a href="edit-employee.php?id=<?= $emp['employee_id'] ?>" class="btn-edit-profile"><i class="fa-solid fa-user-pen"></i> Edit Profile</a>
                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>
    <script src="/HRIS/assets/js/script.js"></script>
</body>
</html>
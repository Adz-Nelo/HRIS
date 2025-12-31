<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/config.php';

// ✅ FIX 1: Access Control (Updated to role_name and login.html)
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'], ['Admin', 'HR Officer'])) {
    header("Location: ../../login.html");
    exit();
}

// ✅ FIX 2: REAL-TIME HEARTBEAT
try {
    $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?")
        ->execute([$_SESSION['employee_id']]);
} catch (PDOException $e) { /* silent fail */ }

$emp_id = $_GET['id'] ?? '';

if (empty($emp_id)) {
    header("Location: manage-accounts.php");
    exit();
}

$default_profile_image = '../../assets/images/default_user.png';

try {
    // Fetch employee data with department name and ID for the back link
    $sql = "SELECT e.*, d.department_name, d.department_id 
            FROM employee e 
            LEFT JOIN department d ON e.department_id = d.department_id 
            WHERE e.employee_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$emp_id]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$emp) {
        header("Location: manage-accounts.php");
        exit();
    }

    // Dynamic Back Link: Returns to the specific department list the employee belongs to
    // If department_id is empty, defaults to the main manage-accounts page
    $back_link = !empty($emp['department_id']) 
                 ? "view-department-employees.php?dept_id=" . urlencode($emp['department_id']) 
                 : "manage-accounts.php";

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Profile - <?= htmlspecialchars($emp['first_name']) ?> - HRMS</title>

    <link rel="icon" type="image/x-icon" href="../../assets/images/HRMS.png">
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .dashboard-wrapper { gap: 15px !important; } 
        .content-card { padding: 0 !important; border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; background: #fff; }
        
        /* Header Strip Styling */
        .profile-header-strip { display: flex; align-items: center; justify-content: space-between; background: #fff; padding: 30px; border-bottom: 1px solid #f1f5f9; }
        .profile-info-left { display: flex; align-items: center; gap: 25px; }
        .profile-img-circle { 
            width: 90px; height: 90px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 4px solid #fff; 
            box-shadow: 0 0 0 2px #3b82f6; 
        }
        
        /* Data Display Layout */
        .profile-body { padding: 30px; }
        .info-section-title { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .info-section-title::after { content: ""; flex: 1; height: 1px; background: #f1f5f9; }
        
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px 40px; margin-bottom: 40px; }
        .info-item { display: flex; flex-direction: column; gap: 5px; }
        .info-label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; }
        .info-value { font-size: 15px; font-weight: 600; color: #1e293b; }

        /* Badges */
        .status-pill { padding: 4px 12px; border-radius: 6px; font-size: 11px; font-weight: 800; display: inline-block; }
        .status-active { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .status-inactive { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .id-highlight { font-family: 'Monaco', 'Consolas', monospace; color: #1d4ed8; background: #eff6ff; padding: 2px 8px; border-radius: 4px; font-weight: 700; }

        .btn-edit-profile { background: #1e293b; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; transition: 0.2s; display: flex; align-items: center; gap: 8px; }
        .btn-edit-profile:hover { background: #334155; }
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
                        <h1 style="font-size: 24px; font-weight: 800; color: #0f172a;">Employee Profile</h1>
                        <p style="font-size: 14px; color: #64748b;">Viewing full record for administrative and organizational review.</p>
                    </div>
                    <a href="<?= $back_link ?>" class="btn-secondary" style="padding: 10px 20px; font-size: 13px; background: #fff; color: #475569; border: 1px solid #e2e8f0; text-decoration: none; border-radius: 8px; display: flex; align-items: center; gap: 8px; font-weight: 600;">
                        <i class="fa-solid fa-chevron-left"></i> Back to Department
                    </a>
                </div>

                <div class="content-card">
                    <div class="profile-header-strip">
                        <div class="profile-info-left">
                            <img src="<?= (!empty($emp['profile_pic'])) ? $emp['profile_pic'] : $default_profile_image ?>" class="profile-img-circle">
                            <div>
                                <h2 style="font-size: 24px; color: #0f172a; font-weight: 800; margin-bottom: 5px;">
                                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                    <?php if(!empty($emp['suffix'])): ?> <span style="color: #64748b;"><?= $emp['suffix'] ?></span><?php endif; ?>
                                </h2>
                                <div style="display: flex; gap: 15px; align-items: center;">
                                    <span class="id-highlight"><i class="fa-solid fa-id-badge"></i> <?= $emp['employee_id'] ?></span>
                                    <span style="font-size: 13px; color: #64748b; font-weight: 500;"><i class="fa-solid fa-shield-halved"></i> Role: <?= $emp['role'] ?></span>
                                </div>
                            </div>
                        </div>
                        <a href="edit-account.php?id=<?= urlencode($emp['employee_id']) ?>" class="btn-edit-profile">
                            <i class="fa-solid fa-user-gear"></i> Edit Details
                        </a>
                    </div>

                    <div class="profile-body">
                        <span class="info-section-title">Organizational Placement</span>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Designated Department</span>
                                <span class="info-value"><?= htmlspecialchars($emp['department_name'] ?: 'Not Assigned') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Official Position</span>
                                <span class="info-value"><?= htmlspecialchars($emp['position'] ?: 'N/A') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Monthly Salary</span>
                                <span class="info-value" style="color: #166534;">₱ <?= number_format($emp['salary'], 2) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Account Status</span>
                                <div>
                                    <span class="status-pill <?= ($emp['status'] == 'Active') ? 'status-active' : 'status-inactive' ?>">
                                        <i class="fa-solid fa-circle" style="font-size: 8px; margin-right: 5px;"></i> <?= strtoupper($emp['status']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <span class="info-section-title">Personal Information & Contact</span>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Legal Name</span>
                                <span class="info-value"><?= htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name'] . ' ' . $emp['middle_name']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Gender</span>
                                <span class="info-value"><?= htmlspecialchars($emp['gender'] ?: 'N/A') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Date of Birth</span>
                                <span class="info-value"><?= !empty($emp['birth_date']) ? date('F d, Y', strtotime($emp['birth_date'])) : 'N/A' ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Work Email</span>
                                <span class="info-value" style="color: #2563eb;"><?= htmlspecialchars($emp['email']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Contact Number</span>
                                <span class="info-value"><?= htmlspecialchars($emp['contact_number'] ?: 'N/A') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Name Extension / Suffix</span>
                                <span class="info-value"><?= htmlspecialchars($emp['extension'] ?: 'None') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>

    <script src="../../assets/js/script.js"></script>
</body>
</html>
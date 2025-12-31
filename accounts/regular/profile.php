<?php
session_start();
require_once '../../config/config.php'; 

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.php");
    exit();
}

$session_emp_id = $_SESSION['employee_id'];
$update_success = false;

// --- HANDLE UPDATE REQUEST ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    try {
        // Fix for "Undefined array key" warnings using null coalescing ??
        $first_name     = $_POST['first_name'] ?? "";
        $middle_name    = $_POST['middle_name'] ?? "";
        $last_name      = $_POST['last_name'] ?? "";
        $suffix         = $_POST['suffix'] ?? "";
        $birth_date     = $_POST['birth_date'] ?? null;
        $gender         = $_POST['gender'] ?? "";
        $email          = $_POST['email'] ?? "";
        $contact_number = $_POST['contact_number'] ?? "";

        $stmtUpdate = $pdo->prepare("
            UPDATE employee 
            SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?, 
                birth_date = ?, gender = ?, email = ?, contact_number = ? 
            WHERE employee_id = ?
        ");
        $stmtUpdate->execute([
            $first_name, $middle_name, $last_name, $suffix, 
            $birth_date, $gender, $email, $contact_number, $session_emp_id
        ]);
        $update_success = true;
    } catch (PDOException $e) {
        error_log("Update error: " . $e->getMessage());
    }
}

// --- FETCH REFRESHED USER DATA ---
try {
    $stmtUser = $pdo->prepare("SELECT * FROM employee WHERE employee_id = ?");
    $stmtUser->execute([$session_emp_id]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $displayName = $user['first_name'] . " " . $user['last_name'];
        $displayRole = $user['role'];
        $profilePic = !empty($user['profile_pic']) ? $user['profile_pic'] : '/HRIS/assets/images/default_user.png';
    }
} catch (PDOException $e) {
    error_log("Error fetching user data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Profile - HRMS</title>


    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
    /* --- GRID & LAYOUT --- */
    .profile-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        padding: 5px;
    }

    .info-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: flex-start;
        gap: 15px;
        transition: all 0.2s ease;
    }

    .info-card:hover { border-color: #2563eb; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }

    .info-icon {
        width: 45px; height: 45px;
        background: #f1f5f9; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        color: #475569; font-size: 1.2rem; flex-shrink: 0;
    }

    .info-content { flex-grow: 1; }

    /* --- TEXT STYLING --- */
    .status-tag {
        font-size: 10px; font-weight: 700; text-transform: uppercase;
        color: #2563eb; background: #eff6ff;
        padding: 2px 8px; border-radius: 4px; margin-bottom: 8px; display: inline-block;
    }

    .field-label { font-size: 0.8rem; color: #64748b; display: block; margin-bottom: 4px; }
    .field-value { font-weight: 700; color: #1e293b; font-size: 0.95rem; display: block; }

    .edit-input {
        width: 100%; padding: 8px; border: 1px solid #2563eb;
        border-radius: 6px; font-size: 0.9rem; display: none;
        background: #fff; font-weight: 600;
    }

    /* --- BUTTON STYLING (The requested update) --- */
    
    /* Global Button Base */
    .btn-dl, .btn-view {
        padding: 10px 18px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: none;
    }

    /* Primary Actions (Edit Profile, Save Changes) */
    .btn-dl {
        background: #2563eb;
        color: white;
    }

    .btn-dl:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
    }

    /* Secondary/Cancel Actions */
    .btn-view {
        background: #f8fafc;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .btn-view:hover {
        background: #f1f5f9;
        color: #1e293b;
    }

    /* Specific Change Photo Button */
    .btn-photo-change {
        background: #ffffff;
        color: #2563eb;
        border: 1px solid #2563eb;
        width: 100%;
        justify-content: center;
    }

    .btn-photo-change:hover {
        background: #eff6ff;
    }

    /* Save Changes Button (Success Color) */
    button[name="update_profile"] {
        background: #059669; /* Emerald Green */
    }

    button[name="update_profile"]:hover {
        background: #047857;
    }

    /* --- SIDEBAR & PHOTO STYLING --- */
    .profile-photo-preview {
        text-align: center; padding: 15px; border-bottom: 1px solid #f1f5f9; margin-bottom: 15px;
    }
    .img-circle {
        width: 120px; height: 120px; border-radius: 50%;
        object-fit: cover; border: 4px solid #f8fafc; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .sidebar-section-title {
        font-size: 11px; font-weight: 800; color: #94a3b8;
        text-transform: uppercase; letter-spacing: 0.5px;
        margin: 15px 0 10px 0; display: block;
        border-bottom: 1px solid #f1f5f9; padding-bottom: 5px;
    }

    .guideline-item { display: flex; gap: 10px; margin-bottom: 12px; line-height: 1.4; }
    .security-note { margin-top: 15px; padding: 12px; background: #f8fafc; border-radius: 8px; border-left: 3px solid #cbd5e1; }
</style>
</head>

<body>
    <div class="wrapper">
        <div id="sidebar-placeholder"></div>

        <main class="main-content" id="main-content">
            <div id="topbar-placeholder"></div>

            <div class="dashboard-wrapper">
                <div class="welcome-header">
                    <div class="welcome-text">
                        <h1>My Profile</h1>
                        <p>View and update your personal information recorded in the HRMS.</p>
                    </div>
                    <div class="date-time-widget text-end">
                        <div class="time fw-bold" id="real-time">--:--:-- --</div>
                        <div class="date text-muted" id="real-date">Loading...</div>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    <div class="feed-container">
                        <form method="POST" action="">
                            <div class="content-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h2><i class="fa-solid fa-id-card me-2"></i>Employee Information</h2>
                                    <button type="button" class="btn-dl" id="edit-btn" onclick="toggleEdit()">
                                        <i class="fa-solid fa-pen-to-square"></i> Edit Profile
                                    </button>
                                </div>
                                
                                <div class="p-4">
                                    <?php if ($update_success): ?>
                                        <div class="status-tag" style="width:100%; padding: 10px; margin-bottom:20px; text-align:center; background: #dcfce7; color: #16a34a;">
                                            <i class="fa-solid fa-circle-check"></i> Profile Updated Successfully
                                        </div>
                                    <?php endif; ?>

                                    <div class="profile-grid">
                                        <div class="info-card">
                                            <div class="info-icon"><i class="fa-solid fa-user"></i></div>
                                            <div class="info-content">
                                                <span class="status-tag">Personal</span>
                                                <span class="field-label">First Name</span>
                                                <span class="field-value display-field"><?= htmlspecialchars($user['first_name'] ?? '') ?></span>
                                                <input type="text" name="first_name" class="edit-input" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                                            </div>
                                        </div>

                                        <div class="info-card">
                                            <div class="info-icon"><i class="fa-solid fa-user-tag"></i></div>
                                            <div class="info-content">
                                                <span class="status-tag">Personal</span>
                                                <span class="field-label">Middle Name</span>
                                                <span class="field-value display-field"><?= htmlspecialchars($user['middle_name'] ?: 'N/A') ?></span>
                                                <input type="text" name="middle_name" class="edit-input" value="<?= htmlspecialchars($user['middle_name'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="info-card">
                                            <div class="info-icon"><i class="fa-solid fa-signature"></i></div>
                                            <div class="info-content">
                                                <span class="status-tag">Personal</span>
                                                <span class="field-label">Last Name</span>
                                                <span class="field-value display-field"><?= htmlspecialchars($user['last_name'] ?? '') ?></span>
                                                <input type="text" name="last_name" class="edit-input" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                                            </div>
                                        </div>

                                        <div class="info-card">
                                            <div class="info-icon"><i class="fa-solid fa-id-badge"></i></div>
                                            <div class="info-content">
                                                <span class="status-tag">Personal</span>
                                                <span class="field-label">Suffix (Jr., III)</span>
                                                <span class="field-value display-field"><?= htmlspecialchars($user['suffix'] ?: 'None') ?></span>
                                                <input type="text" name="suffix" class="edit-input" value="<?= htmlspecialchars($user['suffix'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="info-card">
                                            <div class="info-icon"><i class="fa-solid fa-calendar"></i></div>
                                            <div class="info-content">
                                                <span class="status-tag">Personal</span>
                                                <span class="field-label">Birth Date</span>
                                                <span class="field-value display-field"><?= htmlspecialchars($user['birth_date'] ?: 'N/A') ?></span>
                                                <input type="date" name="birth_date" class="edit-input" value="<?= htmlspecialchars($user['birth_date'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="info-card">
                                            <div class="info-icon" style="color: #16a34a; background: #f0fdf4;"><i class="fa-solid fa-venus-mars"></i></div>
                                            <div class="info-content">
                                                <span class="status-tag" style="color: #16a34a; background: #f0fdf4;">Identity</span>
                                                <span class="field-label">Gender</span>
                                                <span class="field-value display-field"><?= htmlspecialchars($user['gender'] ?: 'N/A') ?></span>
                                                <select name="gender" class="edit-input">
                                                    <option value="Male" <?= ($user['gender'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                                                    <option value="Female" <?= ($user['gender'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="info-card">
                                            <div class="info-icon" style="color: #2563eb; background: #eff6ff;"><i class="fa-solid fa-phone"></i></div>
                                            <div class="info-content">
                                                <span class="status-tag" style="color: #2563eb; background: #eff6ff;">Contact</span>
                                                <span class="field-label">Mobile Number</span>
                                                <span class="field-value display-field"><?= htmlspecialchars($user['contact_number'] ?? '') ?></span>
                                                <input type="text" name="contact_number" class="edit-input" value="<?= htmlspecialchars($user['contact_number'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="info-card">
                                            <div class="info-icon" style="color: #ef4444; background: #fee2e2;"><i class="fa-solid fa-envelope"></i></div>
                                            <div class="info-content">
                                                <span class="status-tag" style="color: #ef4444; background: #fee2e2;">Digital</span>
                                                <span class="field-label">Email Address</span>
                                                <span class="field-value display-field"><?= htmlspecialchars($user['email'] ?? '') ?></span>
                                                <input type="email" name="email" class="edit-input" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4 text-end" id="save-actions" style="display: none;">
                                        <button type="button" class="btn-view me-2" onclick="toggleEdit()">Cancel</button>
                                        <button type="submit" name="update_profile" class="btn-dl">Save Changes</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="side-info-container">
                        <div class="content-card">
                            <div class="card-header">
                                <h2>Profile Management</h2>
                            </div>
                            <div class="p-3">
                                <div class="profile-photo-preview">
                                    <img src="<?= $profilePic ?>" alt="Profile" class="img-circle">
                                    <h3 class="mt-2 mb-0" style="font-size: 1rem;"><?= htmlspecialchars($displayName) ?></h3>
                                    <small class="text-muted"><?= htmlspecialchars($displayRole) ?></small>
                                    <button type="button" class="btn-dl w-100 mt-3" onclick="document.getElementById('photoInput').click()">
                                        <i class="fa-solid fa-camera"></i> Change Photo
                                    </button>
                                    <form action="upload_photo.php" method="POST" enctype="multipart/form-data" id="photoForm">
                                        <input type="file" id="photoInput" name="profile_pic" style="display:none" onchange="this.form.submit()">
                                    </form>
                                </div>

                                <span class="sidebar-section-title">Support Channels</span>
                                <div class="guideline-item">
                                    <i class="fa-solid fa-phone text-primary"></i>
                                    <div>
                                        <strong style="font-size: 12px;" class="d-block">HR Hotline</strong>
                                        <small class="text-muted">Dial 402 for local assistance.</small>
                                    </div>
                                </div>

                                <span class="sidebar-section-title">Data Privacy</span>
                                <div class="guideline-item">
                                    <i class="fa-solid fa-shield-check text-success"></i>
                                    <div>
                                        <strong style="font-size: 12px;" class="d-block">Verified Fields</strong>
                                        <small class="text-muted">Role and Department changes require HR verification.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>


        <div id="rightbar-placeholder"></div>
    </div>

    <script>
        function toggleEdit() {
            const isEditing = document.getElementById('save-actions').style.display === 'block';
            document.querySelectorAll('.display-field').forEach(d => d.style.display = isEditing ? 'block' : 'none');
            document.querySelectorAll('.edit-input').forEach(i => i.style.display = isEditing ? 'none' : 'block');
            document.getElementById('save-actions').style.display = isEditing ? 'none' : 'block';
            document.getElementById('edit-btn').innerHTML = isEditing ? 
                '<i class="fa-solid fa-pen-to-square"></i> Edit Profile' : 
                '<i class="fa-solid fa-xmark"></i> Cancel';
        }
    </script>
    <script src="/HRIS/assets/js/script.js"></script>
    </body>
</html>
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/config.php';

// ✅ FIX 1: Access Control (Updated to role_name and matching previous logic)
if (!isset($_SESSION['employee_id']) || $_SESSION['role_name'] !== 'Admin') {
    header("Location: ../../login.html");
    exit();
}

// ✅ FIX 2: REAL-TIME HEARTBEAT
try {
    $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?")
        ->execute([$_SESSION['employee_id']]);
} catch (PDOException $e) { /* silent fail */ }

$success_msg = "";
$error_msg = "";

// 1. Handle Save Request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    try {
        // Update Hero Content
        $hero_content = json_encode([
            'title' => trim($_POST['hero_title']),
            'subtitle' => trim($_POST['hero_subtitle'])
        ]);
        
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) 
                               VALUES ('hero_section', ?) 
                               ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$hero_content, $hero_content]);

        // Handle Logo Upload
        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] == 0) {
            $upload_dir = "../../assets/images/site/";
            
            // Ensure directory exists with correct permissions
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'svg', 'webp'];

            if (in_array($file_ext, $allowed_exts)) {
                $filename = "logo_" . time() . "." . $file_ext;
                $target_file = $upload_dir . $filename;

                if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $target_file)) {
                    $logo_path = "assets/images/site/" . $filename;
                    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) 
                                           VALUES ('company_logo', ?) 
                                           ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$logo_path, $logo_path]);
                } else {
                    $error_msg = "Failed to move uploaded file.";
                }
            } else {
                $error_msg = "Invalid file type. Please upload a PNG, JPG, or SVG.";
            }
        }
        
        if (empty($error_msg)) {
            $success_msg = "Site settings updated successfully!";
        }
        
    } catch (PDOException $e) {
        $error_msg = "Database Error: " . $e->getMessage();
    }
}

// 2. Fetch Current Settings
$settings = [];
try {
    $settings = $pdo->query("SELECT * FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    // If table doesn't exist yet, it will fail silently
}

// Set Defaults
$hero = isset($settings['hero_section']) ? json_decode($settings['hero_section'], true) : ['title' => 'Redefence Systems', 'subtitle' => 'Security Solutions'];
$current_logo = (isset($settings['company_logo']) && !empty($settings['company_logo'])) 
                ? "../../" . $settings['company_logo'] 
                : "../../assets/images/HRMS.png";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Builder - HRMS</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/HRMS.png">
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <link rel="stylesheet" href="../../assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .dashboard-wrapper { gap: 15px !important; } 
        .content-card.table-card { margin-top: 0 !important; padding: 0 !important; }
        .report-log-header { padding: 15px 25px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        
        .settings-container { padding: 25px; }
        .form-row { margin-bottom: 25px; border-bottom: 1px solid #f8fafc; padding-bottom: 20px; }
        .form-row:last-child { border-bottom: none; }
        .label-group { margin-bottom: 10px; }
        .label-group label { font-weight: 800; color: #475569; font-size: 11px; text-transform: uppercase; display: block; }
        
        .logo-upload-wrapper { display: flex; align-items: center; gap: 20px; background: #fbfcfd; padding: 15px; border-radius: 12px; border: 1px solid #edf2f7; }
        .preview-box { width: 140px; height: 70px; background: white; border: 1px solid #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .preview-box img { max-width: 90%; max-height: 90%; object-fit: contain; }
        
        input[type="text"], textarea { width: 100%; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px; font-weight: 600; }
        input:focus { border-color: #3b82f6; outline: none; }
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
                        <h1 style="font-size: 22px;">Website Content Builder</h1>
                        <p style="font-size: 13px;">Manage company branding and public landing page content.</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-image"></i></div>
                        <div class="stat-info"><h3>Branding</h3><p>Logo Config</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-pen-to-square"></i></div>
                        <div class="stat-info"><h3>Hero</h3><p>Main Headlines</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-shield-halved"></i></div>
                        <div class="stat-info"><h3>Security</h3><p>Admin Access</p></div>
                    </div>
                </div>

                <?php if($success_msg): ?>
                    <div style="margin:0 0 15px; background:#ecfdf5; color:#065f46; padding:12px; border-radius:8px; border:1px solid #d1fae5; font-size:13px;">
                        <i class="fa-solid fa-circle-check"></i> <?= $success_msg ?>
                    </div>
                <?php endif; ?>

                <div class="content-card table-card">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="report-log-header">
                            <h2 style="font-size: 16px;">General Website Settings</h2>
                            <button type="submit" name="save_settings" class="btn-primary">
                                <i class="fa-solid fa-floppy-disk"></i> Save Changes
                            </button>
                        </div>

                        <div class="settings-container">
                            <div class="form-row">
                                <div class="label-group"><label>Company Logo</label></div>
                                <div class="logo-upload-wrapper">
                                    <div class="preview-box">
                                        <img id="logo-preview-img" src="<?= $current_logo ?>">
                                    </div>
                                    <div style="flex: 1;">
                                        <input type="file" name="logo_file" accept="image/*" onchange="previewLogo(this)">
                                        <p style="font-size: 11px; color: #64748b; margin-top: 5px;">Upload your company logo (PNG/JPG).</p>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="label-group"><label>Hero Title</label></div>
                                <input type="text" name="hero_title" value="<?= htmlspecialchars($hero['title']) ?>">
                            </div>

                            <div class="form-row">
                                <div class="label-group"><label>Hero Subtitle</label></div>
                                <textarea name="hero_subtitle" rows="3"><?= htmlspecialchars($hero['subtitle']) ?></textarea>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>

    <script src="../../assets/js/script.js"></script>
    <script>
        function previewLogo(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('logo-preview-img').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
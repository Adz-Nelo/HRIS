<?php
session_start();
require_once '../../config/config.php';

// Access Control - Only Admin
if (!isset($_SESSION['employee_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// --- SAVE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_section'])) {
    $section_key = $_POST['section_key'];
    $content = json_encode($_POST['content']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) 
                               VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$section_key, $content, $content]);
        $success_msg = "Section updated successfully!";
    } catch (PDOException $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}

// Fetch current settings
$settings = $pdo->query("SELECT * FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$hero = isset($settings['hero_section']) ? json_decode($settings['hero_section'], true) : ['title' => '', 'subtitle' => ''];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Builder - HRMS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .builder-container { display: grid; grid-template-columns: 350px 1fr; gap: 20px; height: calc(100vh - 150px); }
        
        /* Left Panel: Editor */
        .editor-panel { background: white; border-radius: 15px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; overflow: hidden; }
        .editor-header { padding: 20px; border-bottom: 1px solid #f1f5f9; background: #f8fafc; }
        .editor-body { padding: 20px; overflow-y: auto; flex-grow: 1; }
        
        /* Right Panel: Preview */
        .preview-panel { background: #cbd5e1; border-radius: 15px; border: 4px solid #1e293b; overflow: hidden; position: relative; }
        .preview-label { position: absolute; top: 10px; left: 50%; transform: translateX(-50%); background: #1e293b; color: white; padding: 4px 12px; border-radius: 20px; font-size: 10px; font-weight: 700; z-index: 10; }
        .preview-iframe-mock { width: 100%; height: 100%; background: white; overflow-y: auto; }

        /* Form Styling */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 5px; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; }
        
        /* Mock Website Elements */
        .mock-hero { padding: 60px 20px; text-align: center; background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('../../assets/images/login-bg.jpg'); background-size: cover; color: white; }
        .mock-nav { height: 50px; background: white; border-bottom: 1px solid #eee; display: flex; align-items: center; padding: 0 20px; justify-content: space-between; }
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
                        <h1 style="font-size: 22px;">Portal Website Builder</h1>
                        <p style="font-size: 13px;">Customize your company's landing page and employee portal view.</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn-primary" onclick="document.getElementById('main-form').submit()">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Publish Changes
                        </button>
                    </div>
                </div>

                <div class="builder-container">
                    <div class="editor-panel">
                        <div class="editor-header">
                            <h3 style="font-size: 15px; color: #1e293b;"><i class="fa-solid fa-sliders"></i> Hero Section Settings</h3>
                        </div>
                        <div class="editor-body">
                            <form method="POST" id="main-form">
                                <input type="hidden" name="section_key" value="hero_section">
                                <input type="hidden" name="save_section" value="1">
                                
                                <div class="form-group">
                                    <label>Main Headline</label>
                                    <input type="text" name="content[title]" id="input-title" 
                                           value="<?= htmlspecialchars($hero['title']) ?>" 
                                           oninput="updatePreview()" placeholder="e.g. Welcome to Our Company">
                                </div>

                                <div class="form-group">
                                    <label>Sub-headline</label>
                                    <textarea name="content[subtitle]" id="input-subtitle" rows="4" 
                                              oninput="updatePreview()"><?= htmlspecialchars($hero['subtitle']) ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Primary Button Text</label>
                                    <input type="text" name="content[btn_text]" value="Get Started" disabled>
                                </div>

                                <div class="form-group">
                                    <label>Hero Overlay Opacity</label>
                                    <input type="range" min="0" max="100" value="50">
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="preview-panel">
                        <div class="preview-label">LIVE PREVIEW</div>
                        <div class="preview-iframe-mock">
                            <nav class="mock-nav">
                                <div style="font-weight: 800; color: #1e293b;">LOGO</div>
                                <div style="display: flex; gap: 15px; font-size: 12px; color: #64748b;">
                                    <span>Home</span><span>About</span><span>Careers</span>
                                </div>
                            </nav>
                            <section class="mock-hero" id="preview-hero">
                                <h1 id="preview-title" style="font-size: 32px; margin-bottom: 10px;">
                                    <?= $hero['title'] ?: 'Your Headline Here' ?>
                                </h1>
                                <p id="preview-subtitle" style="font-size: 16px; opacity: 0.9;">
                                    <?= $hero['subtitle'] ?: 'Your sub-headline will appear here.' ?>
                                </p>
                                <button style="margin-top: 20px; padding: 10px 25px; background: #3b82f6; color: white; border: none; border-radius: 5px;">Join Us</button>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>

    <script>
        // Layout Loader
        function loadLayout(id, path) {
            fetch(path).then(res => res.text()).then(data => document.getElementById(id).innerHTML = data);
        }
        loadLayout('sidebar-placeholder', '../../includes/sidebar.php');
        loadLayout('topbar-placeholder', '../../includes/topbar.php');
        loadLayout('rightbar-placeholder', '../../includes/rightbar.php');

        // Real-time Update Logic
        function updatePreview() {
            const titleInput = document.getElementById('input-title').value;
            const subtitleInput = document.getElementById('input-subtitle').value;
            
            document.getElementById('preview-title').innerText = titleInput || 'Your Headline Here';
            document.getElementById('preview-subtitle').innerText = subtitleInput || 'Your sub-headline will appear here.';
        }
    </script>
</body>
</html>
<?php
session_start();
require_once '../../config/config.php';

// Access Control - Only HR and Admin
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role'], ['Admin', 'HR Officer'])) {
    header("Location: ../../login.html");
    exit();
}

$error = '';
$success = '';

// --- BULK TEMPLATE DOWNLOADER ---
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="department_import_template.csv"');
    $output = fopen('php://output', 'w');
    // Headers: ID, Name
    fputcsv($output, ['department_id', 'department_name']);
    fputcsv($output, ['ITD', 'Information Technology Department']);
    fputcsv($output, ['HRMO', 'Human Resource Management Office']);
    fclose($output);
    exit();
}

// --- HANDLE SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. SINGLE SAVE
    if (isset($_POST['save_department'])) {
        $dept_id = strtoupper(trim($_POST['department_id']));
        $dept_name = trim($_POST['department_name']);

        if (!empty($dept_id) && !empty($dept_name)) {
            try {
                $sql = "INSERT INTO department (department_id, department_name) VALUES (:id, :name)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $dept_id, ':name' => $dept_name]);
                $success = "Department '$dept_name' created successfully!";
            } catch (PDOException $e) {
                $error = ($e->getCode() == 23000) ? "Error: Department ID or Name already exists." : "Database Error: " . $e->getMessage();
            }
        } else {
            $error = "All fields are required.";
        }
    }

    // 2. BULK SAVE (CSV)
    if (isset($_POST['upload_bulk']) && isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file']['tmp_name'];
        if ($_FILES['csv_file']['size'] > 0) {
            $handle = fopen($file, "r");
            fgetcsv($handle); // Skip header row

            try {
                $pdo->beginTransaction();
                $sql = "INSERT INTO department (department_id, department_name) VALUES (?, ?)";
                $stmt = $pdo->prepare($sql);
                
                $count = 0;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (!empty($data[0]) && !empty($data[1])) {
                        $stmt->execute([strtoupper($data[0]), $data[1]]);
                        $count++;
                    }
                }
                $pdo->commit();
                $success = "Successfully imported $count departments.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Bulk Error: Duplicate ID found in file. " . $e->getMessage();
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
    <title>Add Department - HRMS</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/HRMS.png">
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .dashboard-wrapper { gap: 15px !important; } 
        .content-card { padding: 0 !important; border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; background: #fff; }
        
        /* Tab Controls */
        .tab-navigation { display: flex; background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 0 30px; }
        .tab-btn { padding: 15px 25px; border: none; background: none; cursor: pointer; font-weight: 700; color: #64748b; font-size: 13px; border-bottom: 3px solid transparent; transition: 0.3s; }
        .tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        .form-body { padding: 30px; background: #fff; }
        .form-section-title { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .form-section-title::after { content: ""; flex: 1; height: 1px; background: #f1f5f9; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; }
        .form-group label { font-size: 12px; font-weight: 700; color: #334155; }
        .form-group input { padding: 11px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; }
        
        .alert-error { margin: 20px 30px 0; padding: 14px 18px; border-radius: 8px; font-size: 13px; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { margin: 20px 30px 0; padding: 14px 18px; border-radius: 8px; font-size: 13px; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        
        .form-actions { padding: 25px 30px; background: #f8fafc; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 12px; }
        
        /* Instruction Box for Bulk */
        .instruction-box { background: #eff6ff; border: 1px solid #dbeafe; border-radius: 10px; padding: 20px; margin-bottom: 25px; }
        .instruction-box h4 { font-size: 14px; color: #1e40af; margin-bottom: 10px; }
        .instruction-box ul { padding-left: 18px; font-size: 13px; color: #1e3a8a; line-height: 1.6; }
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
                        <h1 style="font-size: 24px; font-weight: 800; color: #0f172a;">Register New Department</h1>
                        <p style="font-size: 14px; color: #64748b;">Add individual departments or import a list via CSV file.</p>
                    </div>
                    <a href="department.php" class="btn-secondary" style="text-decoration:none;">
                        <i class="fa-solid fa-arrow-left"></i> Back to Directory
                    </a>
                </div>

                <div class="content-card">
                    <div class="tab-navigation">
                        <button class="tab-btn active" onclick="switchTab(event, 'single')">Single Entry</button>
                        <button class="tab-btn" onclick="switchTab(event, 'bulk')">Bulk Import</button>
                    </div>

                    <?php if($error): ?>
                        <div class="alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= $error ?></div>
                    <?php endif; ?>
                    <?php if($success): ?>
                        <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?= $success ?></div>
                    <?php endif; ?>

                    <div id="single" class="tab-panel active">
                        <form method="POST">
                            <div class="form-body">
                                <span class="form-section-title">Department Identity</span>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Department ID (Code)</label>
                                        <input type="text" name="department_id" placeholder="e.g. BBH" required maxlength="20">
                                    </div>
                                    <div class="form-group">
                                        <label>Full Department Name</label>
                                        <input type="text" name="department_name" placeholder="e.g. Bacolod Boys Home" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="reset" class="btn-secondary">Clear Form</button>
                                <button type="submit" name="save_department" class="btn-primary" style="padding: 12px 30px;">
                                    <i class="fa-solid fa-floppy-disk"></i> Save Department
                                </button>
                            </div>
                        </form>
                    </div>

                    <div id="bulk" class="tab-panel">
                        <div class="form-body">
                            <div class="instruction-box">
                                <h4><i class="fa-solid fa-circle-info"></i> How to import in bulk:</h4>
                                <ul>
                                    <li>Download the template file using the button below.</li>
                                    <li>Ensure the <strong>department_id</strong> is unique (e.g., PIO, GSD).</li>
                                    <li>Fill in the department names and save as a <strong>.CSV</strong> file.</li>
                                    <li>Upload the completed file using the field below.</li>
                                </ul>
                                <a href="?download_template=1" class="btn-secondary" style="display:inline-block; margin-top:10px; font-size:12px;">
                                    <i class="fa-solid fa-download"></i> Download CSV Template
                                </a>
                            </div>

                            <form method="POST" enctype="multipart/form-data">
                                <span class="form-section-title">Upload File</span>
                                <div class="form-group">
                                    <label>Select CSV File</label>
                                    <input type="file" name="csv_file" accept=".csv" required style="padding: 50px 20px; border: 2px dashed #e2e8f0; text-align: center;">
                                </div>
                                <div class="form-actions" style="background:transparent; padding: 20px 0 0;">
                                    <button type="submit" name="upload_bulk" class="btn-primary" style="width: 100%;">
                                        <i class="fa-solid fa-file-import"></i> Process Import
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

    <script src="../../assets/js/script.js"></script>
    <script>
        function switchTab(evt, tabName) {
            var i, tabpanel, tablinks;
            tabpanel = document.getElementsByClassName("tab-panel");
            for (i = 0; i < tabpanel.length; i++) {
                tabpanel[i].classList.remove("active");
            }
            tablinks = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
    </script>
</body>
</html>
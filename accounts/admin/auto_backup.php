<?php
// auto_backup.php - Admin Auto Backup Script
session_start();
require_once '../../config/config.php';
require_once '../../includes/session_helper.php'; // Add this

requireAdmin(); // This checks both login and admin role

// Check if admin is logged in - FIXED VERSION
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role_name'])) {
    header("Location: ../../login.html");
    exit();
}

// Check if user is admin (case-insensitive check)
$userRole = strtolower($_SESSION['role_name']);
if ($userRole !== 'admin') {
    header("Location: ../../login.html");
    exit();
}

// Set timezone
date_default_timezone_set('Asia/Manila');

/**
 * Auto backup function that runs every 24 hours
 */
function performAutoBackup($pdo) {
    $backupDir = '../../backups/';
    
    // Create backups directory if it doesn't exist
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0777, true);
    }
    
    // Check if 24 hours have passed since last backup
    $lastBackupFile = $backupDir . 'last_backup.txt';
    $shouldBackup = true;
    
    if (file_exists($lastBackupFile)) {
        $lastBackupTime = file_get_contents($lastBackupFile);
        $timeDiff = time() - (int)$lastBackupTime;
        
        // If less than 24 hours (86400 seconds), skip backup
        if ($timeDiff < 86400) {
            $shouldBackup = false;
            $nextBackup = 86400 - $timeDiff;
            return [
                'status' => 'skipped',
                'message' => 'Backup already performed within 24 hours. Next backup in ' . gmdate("H:i:s", $nextBackup),
                'last_backup' => date('Y-m-d H:i:s', $lastBackupTime)
            ];
        }
    }
    
    if ($shouldBackup) {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $backupFile = $backupDir . 'backup_' . $timestamp . '.sql';
            
            // Start backup content
            $backupContent = "-- HRMS Database Backup\n";
            $backupContent .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $backupContent .= "-- Tables: employee, leave_application\n\n";
            
            // Backup employee table
            $backupContent .= "-- ========== EMPLOYEE TABLE ==========\n";
            $stmt = $pdo->query("SELECT * FROM employee");
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($employees)) {
                $columns = implode(', ', array_keys($employees[0]));
                
                foreach ($employees as $employee) {
                    $values = [];
                    foreach ($employee as $value) {
                        $values[] = is_null($value) ? 'NULL' : $pdo->quote($value);
                    }
                    $backupContent .= "INSERT INTO employee ($columns) VALUES (" . implode(', ', $values) . ");\n";
                }
                $backupContent .= "-- Total employees: " . count($employees) . "\n\n";
            }
            
            // Backup leave_application table
            $backupContent .= "-- ========== LEAVE_APPLICATION TABLE ==========\n";
            $stmt = $pdo->query("SELECT * FROM leave_application");
            $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($leaves)) {
                $columns = implode(', ', array_keys($leaves[0]));
                
                foreach ($leaves as $leave) {
                    $values = [];
                    foreach ($leave as $value) {
                        $values[] = is_null($value) ? 'NULL' : $pdo->quote($value);
                    }
                    $backupContent .= "INSERT INTO leave_application ($columns) VALUES (" . implode(', ', $values) . ");\n";
                }
                $backupContent .= "-- Total leave applications: " . count($leaves) . "\n\n";
            }
            
            // Write to file
            file_put_contents($backupFile, $backupContent);
            
            // Update last backup time
            file_put_contents($lastBackupFile, time());
            
            // Log the backup
            $logMessage = date('Y-m-d H:i:s') . " - Auto backup created: $backupFile\n";
            file_put_contents($backupDir . 'backup_log.txt', $logMessage, FILE_APPEND);
            
            // Clean old backups (keep only last 7 days)
            cleanOldBackups($backupDir);
            
            return [
                'status' => 'success',
                'message' => 'Backup completed successfully!',
                'file' => $backupFile,
                'timestamp' => $timestamp,
                'employee_count' => count($employees),
                'leave_count' => count($leaves)
            ];
            
        } catch (PDOException $e) {
            return [
                'status' => 'error',
                'message' => 'Backup failed: ' . $e->getMessage()
            ];
        }
    }
}

/**
 * Clean backups older than 7 days
 */
function cleanOldBackups($backupDir) {
    $files = glob($backupDir . 'backup_*.sql');
    $now = time();
    $sevenDays = 7 * 24 * 60 * 60; // 7 days in seconds
    
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) > $sevenDays) {
                unlink($file);
            }
        }
    }
}

// Check if backup should run now
if (isset($_GET['force']) && $_GET['force'] === 'true') {
    $result = performAutoBackup($pdo);
} else {
    $result = performAutoBackup($pdo);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Backup System - Admin</title>
    <link rel="stylesheet" href="/HRIS/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.8rem;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            transition: background 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .nav-links a.active {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border: 1px solid #eaeaea;
        }
        
        .card h2 {
            color: #2d3748;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-box {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .status-success {
            background: linear-gradient(to right, #d4edda, #c3e6cb);
            border-left: 5px solid #28a745;
            color: #155724;
        }
        
        .status-error {
            background: linear-gradient(to right, #f8d7da, #f5c6cb);
            border-left: 5px solid #dc3545;
            color: #721c24;
        }
        
        .status-info {
            background: linear-gradient(to right, #d1ecf1, #bee5eb);
            border-left: 5px solid #17a2b8;
            color: #0c5460;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .stat-box {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .stat-box i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            display: block;
        }
        
        .stat-box .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .backup-list {
            margin-top: 20px;
        }
        
        .backup-item {
            background: #f8f9fa;
            padding: 18px;
            margin-bottom: 12px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #667eea;
            transition: transform 0.2s;
        }
        
        .backup-item:hover {
            transform: translateX(5px);
            background: #edf2f7;
        }
        
        .file-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .file-name {
            font-weight: 600;
            color: #2d3748;
        }
        
        .file-date {
            color: #718096;
            font-size: 0.9em;
        }
        
        .file-size {
            background: #e2e8f0;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            color: #4a5568;
        }
        
        .info-list {
            list-style: none;
            padding: 0;
        }
        
        .info-list li {
            padding: 12px 0;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-list li:last-child {
            border-bottom: none;
        }
        
        .info-list li i {
            color: #667eea;
            width: 20px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            margin-top: 40px;
            color: #718096;
            font-size: 0.9em;
            border-top: 1px solid #eaeaea;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Simple Header -->
    <div class="header">
        <h1><i class="fas fa-database"></i> Auto Backup System</h1>
        <div class="nav-links">
            <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="auto_backup.php" class="active"><i class="fas fa-database"></i> Auto Backup</a>
            <a href="gender_report.php"><i class="fas fa-chart-pie"></i> Reports</a>
            <a href="../../login.php?logout=true"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Welcome Section -->
        <div class="card">
            <h2><i class="fas fa-shield-alt"></i> Database Backup Status</h2>
            <p>Automatically backup employee and leave application data every 24 hours</p>
            
            <?php if (isset($result)): ?>
                <div class="status-box status-<?php echo $result['status']; ?>">
                    <h3 style="margin-bottom: 10px;">
                        <?php if ($result['status'] === 'success'): ?>
                            <i class="fas fa-check-circle"></i> Backup Successful
                        <?php elseif ($result['status'] === 'error'): ?>
                            <i class="fas fa-exclamation-circle"></i> Backup Error
                        <?php else: ?>
                            <i class="fas fa-info-circle"></i> Backup Information
                        <?php endif; ?>
                    </h3>
                    <p><?php echo $result['message']; ?></p>
                    
                    <?php if ($result['status'] === 'skipped'): ?>
                        <div class="alert alert-warning" style="margin-top: 15px;">
                            <i class="fas fa-clock"></i>
                            <div>
                                <strong>Last backup:</strong> <?php echo $result['last_backup']; ?><br>
                                <?php echo $result['message']; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($result['status'] === 'success'): ?>
                    <div class="stats-grid">
                        <div class="stat-box">
                            <i class="fas fa-file-export" style="color: #667eea;"></i>
                            <div class="stat-value"><?php echo basename($result['file']); ?></div>
                            <div>Backup File</div>
                        </div>
                        <div class="stat-box">
                            <i class="fas fa-users" style="color: #28a745;"></i>
                            <div class="stat-value"><?php echo $result['employee_count']; ?></div>
                            <div>Employee Records</div>
                        </div>
                        <div class="stat-box">
                            <i class="fas fa-calendar-alt" style="color: #fd7e14;"></i>
                            <div class="stat-value"><?php echo $result['leave_count']; ?></div>
                            <div>Leave Applications</div>
                        </div>
                        <div class="stat-box">
                            <i class="fas fa-clock" style="color: #17a2b8;"></i>
                            <div class="stat-value"><?php echo date('H:i:s'); ?></div>
                            <div>Backup Time</div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="action-buttons">
                <a href="?force=true" class="btn btn-primary">
                    <i class="fas fa-play-circle"></i> Force Backup Now
                </a>
                <a href="../../backups/" target="_blank" class="btn btn-secondary">
                    <i class="fas fa-folder-open"></i> View Backup Folder
                </a>
            </div>
        </div>

        <!-- Recent Backups -->
        <div class="card">
            <h2><i class="fas fa-history"></i> Recent Backups</h2>
            
            <div class="backup-list">
                <?php
                $backupDir = '../../backups/';
                if (file_exists($backupDir)) {
                    $files = glob($backupDir . 'backup_*.sql');
                    rsort($files);
                    
                    if (empty($files)) {
                        echo '<p style="text-align: center; color: #718096; padding: 20px;">No backup files found.</p>';
                    } else {
                        foreach (array_slice($files, 0, 5) as $file) {
                            $fileName = basename($file);
                            $fileSize = filesize($file);
                            $fileDate = date('Y-m-d H:i:s', filemtime($file));
                            $sizeFormatted = $fileSize > 1024 ? round($fileSize/1024, 2) . ' KB' : $fileSize . ' bytes';
                            
                            echo '<div class="backup-item">';
                            echo '<div class="file-info">';
                            echo '<div class="file-name"><i class="fas fa-file-alt"></i> ' . $fileName . '</div>';
                            echo '<div class="file-date">Created: ' . $fileDate . '</div>';
                            echo '</div>';
                            echo '<div class="file-size">' . $sizeFormatted . '</div>';
                            echo '</div>';
                        }
                        
                        if (count($files) > 5) {
                            echo '<p style="text-align: center; color: #718096; margin-top: 10px;">... and ' . (count($files) - 5) . ' more backup files</p>';
                        }
                    }
                } else {
                    echo '<p style="text-align: center; color: #718096; padding: 20px;">Backup directory not found.</p>';
                }
                ?>
            </div>
        </div>

        <!-- Backup Information -->
        <div class="card">
            <h2><i class="fas fa-info-circle"></i> Backup Information</h2>
            
            <ul class="info-list">
                <li><i class="fas fa-calendar-check"></i> <strong>Backup Schedule:</strong> Every 24 hours automatically</li>
                <li><i class="fas fa-table"></i> <strong>Tables Backed Up:</strong> employee, leave_application</li>
                <li><i class="fas fa-folder"></i> <strong>Backup Location:</strong> /HRIS/backups/ directory</li>
                <li><i class="fas fa-trash-alt"></i> <strong>Retention Period:</strong> 7 days (old backups auto-deleted)</li>
                <li><i class="fas fa-clock"></i> <strong>Next Automatic Backup:</strong> 24 hours from last successful backup</li>
                <li><i class="fas fa-hand-pointer"></i> <strong>Manual Backup:</strong> Use "Force Backup Now" button above</li>
            </ul>
        </div>
    </div>

    <!-- Simple Footer -->
    <div class="footer">
        <p>HRMS Auto Backup System &copy; <?php echo date('Y'); ?> | 
           Last Updated: <?php echo date('Y-m-d H:i:s'); ?> | 
           Server Time: <span id="currentTime"></span>
        </p>
    </div>

    <!-- Simple Scripts -->
    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour12: true, 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit' 
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        
        // Auto-refresh every 30 seconds
        setInterval(updateTime, 1000);
        updateTime();
        
        // Auto-refresh page every 2 minutes to check backup status
        setTimeout(function() {
            window.location.reload();
        }, 120000);
        
        // Confirm before forcing backup
        document.addEventListener('DOMContentLoaded', function() {
            const forceBtn = document.querySelector('a[href*="force=true"]');
            if (forceBtn) {
                forceBtn.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to force a backup now? This will create a new backup file immediately.')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>
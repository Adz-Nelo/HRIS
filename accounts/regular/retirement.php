<?php
// retirement.php - Optimized Retirement System with Age Detection
session_start();
require_once '../../config/config.php';

// ============================================
// 1. SECURITY & DEBUG MODE
// ============================================
$debug_mode = false; // Set to true for debugging
error_reporting($debug_mode ? E_ALL : 0);
ini_set('display_errors', $debug_mode ? 1 : 0);

// CSRF Token for form protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ============================================
// 2. FETCH LOGGED-IN USER DATA WITH VALIDATION
// ============================================
$employee_id = $_SESSION['employee_id'] ?? null;
$displayName = "User";
$displayRole = "Staff";
$birth_date = null;
$current_age = 0;
$is_eligible_for_retirement = false;
$user = null;
$user_department = "";
$user_position = "";

// Validate employee ID
if (!$employee_id || !is_numeric($employee_id)) {
    if (!$debug_mode) {
        header('Location: /HRIS/login.php');
        exit;
    }
}

if ($employee_id) {
    try {
        $stmtUser = $pdo->prepare("
            SELECT first_name, last_name, role, birth_date, status, department_id, position
            FROM employee 
            WHERE employee_id = ? AND status = 'Active'
        ");
        $stmtUser->execute([$employee_id]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $displayName = htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8');
            $displayRole = htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8');
            $user_department = htmlspecialchars($user['department_id'] ?? '', ENT_QUOTES, 'UTF-8');
            $user_position = htmlspecialchars($user['position'] ?? '', ENT_QUOTES, 'UTF-8');
            $birth_date = $user['birth_date'];
            
            // Validate and calculate age
            if ($birth_date && $birth_date != '0000-00-00') {
                $date_parts = explode('-', $birth_date);
                
                // Validate date format (YYYY-MM-DD)
                if (count($date_parts) == 3 && 
                    checkdate((int)$date_parts[1], (int)$date_parts[2], (int)$date_parts[0]) &&
                    $birth_date >= '1900-01-01') {
                    
                    $birthday = new DateTime($birth_date);
                    $today = new DateTime();
                    
                    // Check if birth date is in future
                    if ($birthday > $today) {
                        error_log("Future birth date for employee $employee_id: $birth_date");
                        $birth_date = null;
                        $current_age = 0;
                    } else {
                        $current_age = $birthday->diff($today)->y;
                        
                        // Check if 60 or older
                        $is_eligible_for_retirement = ($current_age >= 60);
                    }
                } else {
                    // Invalid date format
                    error_log("Invalid birth date for employee $employee_id: $birth_date");
                    $birth_date = null;
                }
            }
        } else {
            error_log("No active employee found for ID: $employee_id");
        }
    } catch (PDOException $e) {
        error_log("Error fetching user data: " . $e->getMessage());
        if ($debug_mode) {
            die("Database error: " . $e->getMessage());
        }
    }
}

// ============================================
// 3. CALCULATE RETIREMENT DATES WITH VALIDATION
// ============================================
$compulsory_year = "N/A";
$optional_year = "N/A";
$years_to_retirement = 0;
$countdown_text = "N/A";
$optional_retire_date = null;
$compulsory_retire_date = null;

if ($birth_date && $current_age > 0) {
    try {
        $birthday = new DateTime($birth_date);
        $today = new DateTime();
        
        // Optional retirement: age 60
        $optional_retire_date = (clone $birthday)->modify('+60 years');
        $optional_year = $optional_retire_date->format('Y');
        
        // Compulsory retirement: age 65
        $compulsory_retire_date = (clone $birthday)->modify('+65 years');
        $compulsory_year = $compulsory_retire_date->format('Y');
        
        // Years until optional retirement
        $years_to_retirement = max(0, 60 - $current_age);
        
        // Detailed countdown text (shows months when close)
        if ($years_to_retirement <= 5 && $years_to_retirement > 0) {
            $interval = $today->diff($optional_retire_date);
            
            if ($interval->y > 0) {
                $countdown_text = $interval->y . " year" . ($interval->y > 1 ? 's' : '');
                if ($interval->m > 0) {
                    $countdown_text .= " and " . $interval->m . " month" . ($interval->m > 1 ? 's' : '');
                }
            } elseif ($interval->m > 0) {
                $countdown_text = $interval->m . " month" . ($interval->m > 1 ? 's' : '');
                if ($interval->d > 0) {
                    $countdown_text .= " and " . $interval->d . " day" . ($interval->d > 1 ? 's' : '');
                }
            } else {
                $countdown_text = $interval->d . " day" . ($interval->d > 1 ? 's' : '');
            }
        } elseif ($years_to_retirement > 0) {
            $countdown_text = $years_to_retirement . " year" . ($years_to_retirement != 1 ? 's' : '');
        } else {
            $countdown_text = "Eligible Now";
        }
        
    } catch (Exception $e) {
        error_log("Date calculation error: " . $e->getMessage());
        if ($debug_mode) {
            die("Date calculation error: " . $e->getMessage());
        }
    }
}

// ============================================
// 4. SERVICE RECORD STATUS (Optional - can fetch from database)
// ============================================
try {
    $stmtSR = $pdo->prepare("
        SELECT document_path, updated_at 
        FROM employee_documents 
        WHERE employee_id = ? AND document_type = 'Service Record'
        ORDER BY updated_at DESC LIMIT 1
    ");
    $stmtSR->execute([$employee_id]);
    $service_record = $stmtSR->fetch(PDO::FETCH_ASSOC);
    $sr_status = $service_record ? 1 : 0;
    $sr_file_path = $service_record ? $service_record['document_path'] : '';
    $sr_updated_at = $service_record ? $service_record['updated_at'] : '';
} catch (PDOException $e) {
    // Table might not exist yet
    $sr_status = 0;
    $sr_file_path = '';
    $sr_updated_at = '';
}

// ============================================
// 5. CHECK IF RETIREMENT ALREADY APPLIED
// ============================================
try {
    $stmtRetire = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM retirement_applications 
        WHERE employee_id = ? AND status IN ('Pending', 'Approved')
    ");
    $stmtRetire->execute([$employee_id]);
    $has_pending_retirement = $stmtRetire->fetchColumn() > 0;
} catch (PDOException $e) {
    // Table might not exist yet
    $has_pending_retirement = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Retirement Portal - HRMS</title>
    
    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">
    <link rel="stylesheet" href="/HRIS/assets/css/style.css">
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* Retirement-specific styles */
        .retirement-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 20px;
            animation: pulse 2s infinite;
        }
        
        .retirement-banner.pending {
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
            animation: none;
        }
        
        .retirement-banner.hidden {
            display: none;
        }
        
        .retirement-application-form {
            background: #fff;
            border: 3px solid #667eea;
            border-radius: 12px;
            padding: 30px;
            margin: 25px 0;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
            display: none;
        }
        
        .retirement-countdown {
            background: linear-gradient(135deg, #667eea 0%, rgb(128, 147, 233) 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .age-indicator {
            display: inline-flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            margin-left: 10px;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .form-section.warning {
            border-left-color: #f59e0b;
            background: #fffbeb;
        }
        
        .retirement-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .retirement-stat {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .retirement-stat:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .retirement-stat h4 {
            margin: 0;
            font-size: 2em;
            color: #667eea;
        }
        
        .retirement-stat.compulsory h4 {
            color: #dc3545;
        }
        
        .retirement-stat.optional h4 {
            color: #10b981;
        }
        
        .retirement-stat.current h4 {
            color: #667eea;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(102, 126, 234, 0); }
            100% { box-shadow: 0 0 0 0 rgba(102, 126, 234, 0); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .eligibility-badge {
            background: <?php echo $is_eligible_for_retirement ? '#10b981' : '#f59e0b'; ?>;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
            animation: fadeIn 0.5s ease-out;
        }
        
        .btn-more {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-more:hover {
            background: #5a67d8;
        }
        
        .btn-more:disabled {
            background: #a0aec0;
            cursor: not-allowed;
        }
        
        .activity-list {
            list-style: none;
            padding: 0;
        }
        
        .activity-list li {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #eaeaea;
            animation: fadeIn 0.5s ease-out;
            animation-fill-mode: both;
        }
        
        .activity-list li:nth-child(1) { animation-delay: 0.1s; }
        .activity-list li:nth-child(2) { animation-delay: 0.2s; }
        .activity-list li:nth-child(3) { animation-delay: 0.3s; }
        .activity-list li:nth-child(4) { animation-delay: 0.4s; }
        
        .activity-list li:last-child {
            border-bottom: none;
        }
        
        .text-blue { color: #3b82f6; }
        .text-orange { color: #f97316; }
        .text-green { color: #10b981; }
        .text-purple { color: #8b5cf6; }
        
        .badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-gray {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .badge-success {
            background: #10b981;
            color: white;
        }
        
        .badge-warning {
            background: #f59e0b;
            color: white;
        }
        
        .announcement-item {
            animation: fadeIn 0.5s ease-out;
            animation-fill-mode: both;
        }
        
        .announcement-item:nth-child(1) { animation-delay: 0.1s; }
        .announcement-item:nth-child(2) { animation-delay: 0.2s; }
        .announcement-item:nth-child(3) { animation-delay: 0.3s; }
        
        .date-input-tip {
            font-size: 0.85em;
            color: #6b7280;
            margin-top: 5px;
        }
        
        .document-checklist {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }
        
        .document-checklist label {
            display: flex;
            align-items: flex-start;
            padding: 10px;
            margin-bottom: 8px;
            border-radius: 6px;
            transition: background 0.2s;
            cursor: pointer;
        }
        
        .document-checklist label:hover {
            background: #f9fafb;
        }
        
        .document-checklist input[type="checkbox"] {
            margin-top: 2px;
            margin-right: 10px;
        }
        
        /* Print styles */
        @media print {
            .no-print, 
            .retirement-banner,
            .retirement-countdown,
            .welcome-header,
            .retirement-application-form button,
            .retirement-stats,
            #sidebar-placeholder,
            #topbar-placeholder,
            #rightbar-placeholder,
            .main-dashboard-grid {
                display: none !important;
            }
            
            body {
                padding: 20px;
                background: white;
                color: black;
                font-size: 12pt;
            }
            
            .retirement-application-form {
                display: block !important;
                border: 1px solid #000;
                box-shadow: none;
                margin: 0;
                padding: 15px;
            }
        }
        
        @media (max-width: 768px) {
            .retirement-stats {
                grid-template-columns: 1fr 1fr;
            }
            
            .retirement-banner, .retirement-countdown {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div id="sidebar-placeholder"></div>
        
        <main class="main-content" id="main-content">
            <div id="topbar-placeholder"></div>
            
            <div class="dashboard-wrapper">
                <!-- ============================================
                     WELCOME HEADER WITH AGE DISPLAY
                ============================================ -->
                <div class="welcome-header">
                    <div class="welcome-text">
                        <h1>
                            Retirement Portal 
                            <span class="eligibility-badge">
                                <?php echo $is_eligible_for_retirement ? 'Eligible' : 'Not Yet Eligible'; ?>
                            </span>
                        </h1>
                        <p>
                            Welcome, <strong><?= $displayName ?></strong> 
                            (Age: <?= $current_age ?>) | 
                            Role: <?= $displayRole ?>
                        </p>
                    </div>
                    <div class="date-time-widget">
                        <div class="time" id="real-time">--:--:-- --</div>
                        <div class="date" id="real-date">Loading...</div>
                    </div>
                </div>
                
                <!-- ============================================
                     RETIREMENT ELIGIBILITY BANNER
                     (Only shows when age >= 60)
                ============================================ -->
                <?php if ($is_eligible_for_retirement): ?>
                    <?php if ($has_pending_retirement): ?>
                    <div class="retirement-banner pending">
                        <div style="font-size: 2.5em;">⏳</div>
                        <div style="flex: 1;">
                            <h3 style="margin: 0; color: white; font-size: 1.5em;">
                                Retirement Application Pending
                            </h3>
                            <p style="margin: 8px 0 0 0; opacity: 0.9; font-size: 1.1em;">
                                Your retirement application is being processed by HR.
                                <br>
                                <small>You will be notified once it's approved or if additional documents are needed.</small>
                            </p>
                        </div>
                        <button onclick="viewApplicationStatus()" 
                                style="background: white; color: #f59e0b; border: none; padding: 10px 25px; 
                                       border-radius: 8px; font-weight: bold; cursor: pointer; 
                                       transition: all 0.3s;">
                            <i class="fas fa-eye me-2"></i> View Status
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="retirement-banner" id="retirementBanner">
                        <div style="font-size: 2.5em;">🎉</div>
                        <div style="flex: 1;">
                            <h3 style="margin: 0; color: white; font-size: 1.5em;">
                                Retirement Eligibility Active!
                            </h3>
                            <p style="margin: 8px 0 0 0; opacity: 0.9; font-size: 1.1em;">
                                Congratulations! You have reached <strong><?= $current_age ?> years</strong> of age. 
                                You are now eligible to file for optional retirement under RA 8291.
                                <br>
                                <small>Compulsory retirement will be at age 65 (Year: <?= $compulsory_year ?>)</small>
                            </p>
                        </div>
                        <button onclick="showRetirementForm()" 
                                style="background: white; color: #667eea; border: none; padding: 10px 25px; 
                                       border-radius: 8px; font-weight: bold; cursor: pointer; 
                                       transition: all 0.3s;">
                            <i class="fas fa-file-alt me-2"></i> Start Application
                        </button>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                <!-- ============================================
                     RETIREMENT COUNTDOWN
                     (Shows when age < 60)
                ============================================ -->
                <div class="retirement-countdown">
                    <div style="font-size: 2em;">⏳</div>
                    <div style="flex: 1;">
                        <h3 style="margin: 0; color: white;">Retirement Countdown</h3>
                        <p style="margin: 5px 0 0 0; opacity: 0.9;">
                            You will be eligible for retirement in 
                            <strong><?= $countdown_text ?></strong>.
                            <br>
                            <small>Optional retirement: Age 60 (Year <?= $optional_year ?>) | 
                                   Compulsory retirement: Age 65 (Year <?= $compulsory_year ?>)</small>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- ============================================
                     RETIREMENT STATISTICS
                ============================================ -->
                <div class="retirement-stats">
                    <div class="retirement-stat current">
                        <h4><?= $current_age ?></h4>
                        <p>Current Age</p>
                    </div>
                    <div class="retirement-stat optional">
                        <h4>60</h4>
                        <p>Optional Retirement Age</p>
                    </div>
                    <div class="retirement-stat compulsory">
                        <h4>65</h4>
                        <p>Compulsory Retirement Age</p>
                    </div>
                    <div class="retirement-stat">
                        <h4><?= $is_eligible_for_retirement ? 'YES' : 'NO' ?></h4>
                        <p>Eligible to Apply</p>
                    </div>
                </div>
                
                <!-- ============================================
                     RETIREMENT APPLICATION FORM
                     (Only shows when age >= 60 and no pending application)
                ============================================ -->
                <?php if ($is_eligible_for_retirement && !$has_pending_retirement): ?>
                <div class="retirement-application-form" id="retirementForm">
                    <div class="card-header" style="border-bottom: 3px solid #667eea; padding-bottom: 20px; margin-bottom: 25px;">
                        <h2 style="color: #667eea; margin: 0; font-size: 1.8em;">
                            <i class="fas fa-file-contract me-2"></i> Retirement Application Form
                        </h2>
                        <p class="text-secondary" style="margin: 8px 0 0 0; font-size: 1.1em;">
                            Complete this form to apply for retirement under Republic Act 8291 (GSIS Act)
                        </p>
                    </div>
                    
                    <form id="retirementApplicationForm" onsubmit="submitRetirementApplication(event)">
                        <!-- CSRF Protection -->
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <!-- Section 1: Personal Information -->
                        <div class="form-section">
                            <h4 style="color: #555; margin-bottom: 15px; display: flex; align-items: center;">
                                <i class="fas fa-user-circle me-2"></i> Personal Information
                            </h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Full Name</label>
                                    <input type="text" 
                                           value="<?= $displayName . ' ' . htmlspecialchars($user['last_name'] ?? '') ?>" 
                                           readonly 
                                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; background: #f5f5f5;">
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Age</label>
                                    <input type="text" 
                                           value="<?= $current_age ?> years old" 
                                           readonly 
                                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; background: #f5f5f5;">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section 2: Retirement Type -->
                        <div class="form-section">
                            <h4 style="color: #555; margin-bottom: 15px; display: flex; align-items: center;">
                                <i class="fas fa-clipboard-check me-2"></i> Retirement Type
                            </h4>
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 10px; font-weight: 600;">
                                    Select Retirement Option:
                                </label>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                                    <label style="display: flex; align-items: center; padding: 15px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: border-color 0.3s;">
                                        <input type="radio" name="retirement_type" value="optional" style="margin-right: 10px;" required>
                                        <div>
                                            <strong>Optional Retirement</strong>
                                            <div style="font-size: 0.9em; color: #666;">Age 60 with at least 15 years of service</div>
                                        </div>
                                    </label>
                                    <label style="display: flex; align-items: center; padding: 15px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: border-color 0.3s;">
                                        <input type="radio" name="retirement_type" value="compulsory" style="margin-right: 10px;" required>
                                        <div>
                                            <strong>Compulsory Retirement</strong>
                                            <div style="font-size: 0.9em; color: #666;">Age 65 (mandatory)</div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section 3: Effective Date -->
                        <div class="form-section">
                            <h4 style="color: #555; margin-bottom: 15px; display: flex; align-items: center;">
                                <i class="fas fa-calendar-alt me-2"></i> Proposed Effective Date
                            </h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Last Day of Service *</label>
                                    <input type="date" name="last_day" required 
                                           min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                           max="<?= $optional_retire_date ? $optional_retire_date->format('Y-m-d') : '' ?>"
                                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                                    <div class="date-input-tip">
                                        Must be after today and before age 65
                                    </div>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Reason for Retirement *</label>
                                    <textarea name="reason" rows="3" 
                                              placeholder="Briefly state your reason for retirement (e.g., reaching retirement age, health reasons, personal choice)..."
                                              required
                                              style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section 4: Documents Checklist -->
                        <div class="form-section">
                            <h4 style="color: #555; margin-bottom: 15px; display: flex; align-items: center;">
                                <i class="fas fa-clipboard-list me-2"></i> Required Documents Checklist
                            </h4>
                            <div class="document-checklist">
                                <label>
                                    <input type="checkbox" name="doc_service_record" required>
                                    <div>
                                        <strong>Updated Service Record</strong>
                                        <div style="font-size: 0.9em; color: #666; margin-top: 2px;">
                                            Certified by HR. Required for GSIS and leave monetization claims.
                                        </div>
                                    </div>
                                </label>
                                <label>
                                    <input type="checkbox" name="doc_clearance" required>
                                    <div>
                                        <strong>Office Clearance</strong>
                                        <div style="font-size: 0.9em; color: #666; margin-top: 2px;">
                                            Signed by Property, Accounting, and Admin departments.
                                        </div>
                                    </div>
                                </label>
                                <label>
                                    <input type="checkbox" name="doc_gsis_form" required>
                                    <div>
                                        <strong>GSIS Retirement Application Form</strong>
                                        <div style="font-size: 0.9em; color: #666; margin-top: 2px;">
                                            Fill out in 3 copies. Available for download below.
                                        </div>
                                    </div>
                                </label>
                                <label>
                                    <input type="checkbox" name="doc_saln" required>
                                    <div>
                                        <strong>Statement of Assets, Liabilities and Net Worth (SALN)</strong>
                                        <div style="font-size: 0.9em; color: #666; margin-top: 2px;">
                                            Latest copy submitted to HR.
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Section 5: Declaration -->
                        <div class="form-section warning">
                            <h4 style="color: #555; margin-bottom: 15px; display: flex; align-items: center;">
                                <i class="fas fa-exclamation-triangle me-2" style="color: #f59e0b;"></i> Declaration
                            </h4>
                            <div style="display: flex; align-items: flex-start; gap: 10px;">
                                <input type="checkbox" id="declaration" name="declaration" required style="margin-top: 3px;">
                                <div>
                                    <label for="declaration" style="cursor: pointer;">
                                        <strong>I hereby declare that:</strong>
                                    </label>
                                    <ul style="margin: 8px 0 0 15px; font-size: 0.95em; color: #555;">
                                        <li>The information provided is true and correct</li>
                                        <li>I understand that retirement is irrevocable once approved</li>
                                        <li>I will submit all required documents within 30 days</li>
                                        <li>I authorize HR to process my retirement with GSIS</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div style="display: flex; justify-content: center; gap: 15px; margin-top: 30px;">
                            <button type="submit" id="submitBtn"
                                    style="background: #667eea; color: white; border: none; padding: 15px 40px; 
                                           border-radius: 8px; font-size: 1.1em; font-weight: bold; 
                                           cursor: pointer; transition: background 0.3s; display: flex; align-items: center;">
                                <i class="fas fa-paper-plane me-2"></i> Submit Retirement Application
                            </button>
                            <button type="button" onclick="hideRetirementForm()" 
                                    style="background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; padding: 15px 30px; 
                                           border-radius: 8px; font-size: 1.1em; cursor: pointer; transition: background 0.3s;">
                                Cancel
                            </button>
                            <button type="button" onclick="printApplicationForm()" 
                                    style="background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; padding: 15px 30px; 
                                        border-radius: 8px; font-size: 1.1em; cursor: pointer; transition: all 0.3s;
                                        display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-print me-2"></i> Print Application
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- ============================================
                     DOCUMENTARY REQUIREMENTS SECTION
                     (Always visible)
                ============================================ -->
                <div class="main-dashboard-grid">
                    <div class="feed-container">
                        <div class="content-card">
                            <div class="card-header">
                                <h2>Documentary Requirements</h2>
                                <span class="text-secondary small">Prepare these documents for retirement</span>
                            </div>
                            
                            <div class="announcement-item">
                                <div class="ann-date">
                                    <span class="month">CSC</span>
                                    <span class="day">SR</span>
                                </div>
                                <div class="ann-text">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <h4>Updated Service Record</h4>
                                        <span class="badge <?= $sr_status ? 'badge-success' : 'badge-gray' ?>">
                                            <?= $sr_status ? 'Available' : 'Not Requested' ?>
                                        </span>
                                    </div>
                                    <p>Certified by HR. Required for GSIS and leave monetization claims.</p>
                                    <?php if ($sr_status): ?>
                                        <p><small>Last updated: <?= $sr_updated_at ?></small></p>
                                        <button class="btn-more" onclick="downloadServiceRecord()" style="margin-top: 10px;">
                                            <i class="fa-solid fa-download me-1"></i> Download
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-more" onclick="requestServiceRecord()" style="margin-top: 10px;">
                                            <i class="fa-solid fa-paper-plane me-1"></i> Request from HR
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="announcement-item">
                                <div class="ann-date">
                                    <span class="month">SIGN</span>
                                    <span class="day">OFF</span>
                                </div>
                                <div class="ann-text">
                                    <h4>Office Clearance</h4>
                                    <p>Requires physical signatures from Property, Accounting, and Admin departments.</p>
                                    <button class="btn-more mt-2" onclick="printClearance()">
                                        <i class="fa-solid fa-file-pdf me-1"></i> Print Clearance Template
                                    </button>
                                </div>
                            </div>
                            
                            <div class="announcement-item">
                                <div class="ann-date">
                                    <span class="month">GSIS</span>
                                    <span class="day">01</span>
                                </div>
                                <div class="ann-text">
                                    <h4>GSIS Retirement Application</h4>
                                    <p>The primary form for RA 8291 claims. Fill out in 3 copies.</p>
                                    <button class="btn-more mt-2" onclick="downloadGSISForm()">
                                        <i class="fa-solid fa-download me-1"></i> Download Official Form
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="side-info-container">
                        <div class="content-card">
                            <div class="card-header">
                                <h2>Retirement Workflow</h2>
                            </div>
                            <ul class="activity-list">
                                <li>
                                    <i class="fa-solid fa-1 text-blue fa-lg"></i>
                                    <div>
                                        <strong>Check Eligibility</strong>
                                        <small>System verifies if you're age 60 or older</small>
                                    </div>
                                </li>
                                <li>
                                    <i class="fa-solid fa-2 text-orange fa-lg"></i>
                                    <div>
                                        <strong>Complete Application</strong>
                                        <small>Fill out the retirement application form</small>
                                    </div>
                                </li>
                                <li>
                                    <i class="fa-solid fa-3 text-green fa-lg"></i>
                                    <div>
                                        <strong>Submit Documents</strong>
                                        <small>Provide all required documents to HR</small>
                                    </div>
                                </li>
                                <li>
                                    <i class="fa-solid fa-4 text-purple fa-lg"></i>
                                    <div>
                                        <strong>GSIS Processing</strong>
                                        <small>HR submits application to GSIS</small>
                                    </div>
                                </li>
                            </ul>
                            
                            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea;">
                                <h6 style="font-weight: bold; margin-bottom: 5px; color: #4b5563;">
                                    <i class="fas fa-info-circle me-1"></i> Important Note:
                                </h6>
                                <p style="margin: 0; font-size: 0.9em; color: #666;">
                                    The retirement application form above will only appear when you reach <strong>60 years of age</strong>. 
                                    The system automatically detects your age from your birthdate.
                                </p>
                            </div>
                            
                            <?php if ($debug_mode): ?>
                            <div style="margin-top: 20px; padding: 15px; background: #fee2e2; border-radius: 8px; border-left: 4px solid #dc2626;">
                                <h6 style="font-weight: bold; margin-bottom: 5px; color: #dc2626;">
                                    <i class="fas fa-bug me-1"></i> Debug Info:
                                </h6>
                                <pre style="margin: 0; font-size: 0.8em; color: #7f1d1d;">
Employee ID: <?= $employee_id ?><br>
Birth Date: <?= $birth_date ?><br>
Current Age: <?= $current_age ?><br>
Eligible: <?= $is_eligible_for_retirement ? 'Yes' : 'No' ?><br>
Optional Year: <?= $optional_year ?><br>
Compulsory Year: <?= $compulsory_year ?>
                                </pre>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        
        <div id="rightbar-placeholder"></div>
    </div>
    
    <script src="/HRIS/assets/js/script.js"></script>
    <script>
    // ============================================
    // GLOBAL VARIABLES AND PHP DATA
    // ============================================
    let isSubmitting = false;
    
    // PHP data passed to JavaScript
    const phpData = {
        employeeName: "<?php echo htmlspecialchars($displayName . ' ' . ($user['last_name'] ?? ''), ENT_QUOTES); ?>",
        employeeId: "<?php echo $employee_id; ?>",
        currentAge: <?php echo $current_age; ?>,
        birthDate: "<?php echo $birth_date; ?>",
        department: "<?php echo $user_department; ?>",
        position: "<?php echo $user_position; ?>",
        isEligible: <?php echo $is_eligible_for_retirement ? 'true' : 'false'; ?>,
        optionalYear: "<?php echo $optional_year; ?>",
        compulsoryYear: "<?php echo $compulsory_year; ?>",
        csrfToken: "<?php echo $csrf_token; ?>"
    };
    
    // ============================================
    // ALERT FUNCTION (MUST BE DEFINED FIRST)
    // ============================================
    function showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            z-index: 10000;
            animation: slideIn 0.3s ease-out;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        
        if (type === 'success') {
            alertDiv.style.backgroundColor = '#10b981';
            alertDiv.innerHTML = `<i class="fas fa-check-circle me-2"></i> ${message}`;
        } else if (type === 'warning') {
            alertDiv.style.backgroundColor = '#f59e0b';
            alertDiv.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i> ${message}`;
        } else if (type === 'error') {
            alertDiv.style.backgroundColor = '#dc3545';
            alertDiv.innerHTML = `<i class="fas fa-times-circle me-2"></i> ${message}`;
        } else {
            alertDiv.style.backgroundColor = '#667eea';
            alertDiv.innerHTML = `<i class="fas fa-info-circle me-2"></i> ${message}`;
        }
        
        document.body.appendChild(alertDiv);
        
        // Remove after 5 seconds
        setTimeout(() => {
            alertDiv.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 300);
        }, 5000);
    }
    
    // ============================================
    // RETIREMENT FORM FUNCTIONS
    // ============================================
    
    function showRetirementForm() {
        const form = document.getElementById('retirementForm');
        if (form) {
            form.style.display = 'block';
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            // Set default date (30 days from now)
            const lastDayInput = document.querySelector('input[name="last_day"]');
            if (lastDayInput) {
                const today = new Date();
                const futureDate = new Date(today);
                futureDate.setDate(today.getDate() + 30);
                const formattedDate = futureDate.toISOString().split('T')[0];
                lastDayInput.value = formattedDate;
                lastDayInput.min = new Date(today.setDate(today.getDate() + 1)).toISOString().split('T')[0];
                
                // Set max date (65th birthday)
                if (phpData.birthDate) {
                    const birthDate = new Date(phpData.birthDate);
                    const maxDate = new Date(birthDate);
                    maxDate.setFullYear(birthDate.getFullYear() + 65);
                    lastDayInput.max = maxDate.toISOString().split('T')[0];
                }
            }
        }
    }
    
    function hideRetirementForm() {
        const form = document.getElementById('retirementForm');
        if (form) {
            form.style.display = 'none';
        }
    }
    
    function submitRetirementApplication(event) {
        event.preventDefault();
        
        // Prevent double submission
        if (isSubmitting) return;
        isSubmitting = true;
        
        // Get submit button
        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;
        
        // Disable button and show loading
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.7';
        
        // Collect form data
        const formData = new FormData(event.target);
        const retirementType = formData.get('retirement_type');
        const lastDay = formData.get('last_day');
        const reason = formData.get('reason');
        const csrfToken = formData.get('csrf_token');
        
        // CSRF validation
        if (csrfToken !== phpData.csrfToken) {
            showAlert('Security validation failed. Please refresh the page.', 'error');
            resetSubmitButton(submitBtn, originalText);
            isSubmitting = false;
            return;
        }
        
        // Validation
        if (!retirementType) {
            showAlert('Please select a retirement type.', 'warning');
            resetSubmitButton(submitBtn, originalText);
            isSubmitting = false;
            return;
        }
        
        if (!lastDay) {
            showAlert('Please select your last day of service.', 'warning');
            resetSubmitButton(submitBtn, originalText);
            isSubmitting = false;
            return;
        }
        
        // Check date is in future
        const selectedDate = new Date(lastDay);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (selectedDate <= today) {
            showAlert('Last day of service must be in the future.', 'warning');
            resetSubmitButton(submitBtn, originalText);
            isSubmitting = false;
            return;
        }
        
        // Check date is before 65th birthday
        if (phpData.birthDate) {
            const birthDate = new Date(phpData.birthDate);
            const maxDate = new Date(birthDate);
            maxDate.setFullYear(birthDate.getFullYear() + 65);
            
            if (selectedDate > maxDate) {
                showAlert('Last day cannot be after your 65th birthday.', 'warning');
                resetSubmitButton(submitBtn, originalText);
                isSubmitting = false;
                return;
            }
        }
        
        // Check all checkboxes are checked
        const checkboxes = event.target.querySelectorAll('input[type="checkbox"]');
        let allChecked = true;
        checkboxes.forEach(cb => {
            if (!cb.checked && cb.name !== 'csrf_token') {
                allChecked = false;
            }
        });
        
        if (!allChecked) {
            showAlert('Please check all required documents and the declaration.', 'warning');
            resetSubmitButton(submitBtn, originalText);
            isSubmitting = false;
            return;
        }
        
        // Show confirmation
        if (confirm('Are you sure you want to submit your retirement application?\n\nThis action cannot be undone. HR will contact you for next steps.')) {
            // In a real implementation, you would make an AJAX call here
            // For now, simulate API call
            setTimeout(() => {
                showAlert('Retirement application submitted successfully! HR will contact you within 3-5 working days.', 'success');
                hideRetirementForm();
                
                // Reload page after 2 seconds to show pending status
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
                
            }, 1500);
        } else {
            resetSubmitButton(submitBtn, originalText);
            isSubmitting = false;
        }
    }
    
    function resetSubmitButton(button, originalText) {
        button.innerHTML = originalText;
        button.disabled = false;
        button.style.opacity = '1';
    }
    
    // ============================================
    // DOCUMENT FUNCTIONS
    // ============================================
    
    function requestServiceRecord() {
        if (confirm('Send a formal request to HR Staff for an updated Service Record?')) {
            showAlert('Request sent successfully. HR staff will be notified.', 'success');
            // In real implementation, make an AJAX call here
        }
    }
    
    function downloadServiceRecord() {
        // In real implementation, this would download the actual file
        showAlert('Service Record download started.', 'success');
    }
    
    // ============================================
    // PRINT FUNCTIONS
    // ============================================
    
    function printClearance() {
        const today = new Date();
        const employeeName = phpData.employeeName || 'Employee Name';
        const employeeId = phpData.employeeId || 'N/A';
        const department = phpData.department || 'Not specified';
        const position = phpData.position || 'Not specified';
        const currentAge = phpData.currentAge || 'N/A';
        
        const printContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Office Clearance Form</title>
                <style>
                    body { 
                        font-family: Arial, sans-serif; 
                        padding: 40px;
                        line-height: 1.6;
                        color: #333;
                    }
                    .header {
                        text-align: center;
                        margin-bottom: 40px;
                    }
                    .header h1 {
                        color: #2c3e50;
                        margin-bottom: 10px;
                        font-size: 24px;
                    }
                    .header h2 {
                        color: #667eea;
                        font-size: 20px;
                        margin: 0;
                    }
                    .form-section {
                        margin: 25px 0;
                        padding: 20px;
                        border: 2px solid #ddd;
                        border-radius: 8px;
                    }
                    .form-section h3 {
                        color: #2c3e50;
                        border-bottom: 1px solid #eee;
                        padding-bottom: 10px;
                        margin-top: 0;
                    }
                    .info-grid {
                        display: grid;
                        grid-template-columns: 150px 1fr;
                        gap: 15px;
                        margin: 15px 0;
                    }
                    .info-label {
                        font-weight: bold;
                        color: #555;
                    }
                    .signature-area {
                        margin-top: 60px;
                        padding: 20px;
                        border-top: 2px solid #333;
                    }
                    .signature-line {
                        margin-top: 40px;
                        border-top: 1px solid #000;
                        width: 250px;
                        display: inline-block;
                    }
                    .signature-box {
                        display: inline-block;
                        margin: 20px 40px;
                        text-align: center;
                    }
                    .watermark {
                        position: fixed;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%) rotate(-45deg);
                        font-size: 80px;
                        color: rgba(0,0,0,0.1);
                        z-index: -1;
                        white-space: nowrap;
                    }
                    .footer-note {
                        text-align: center;
                        margin-top: 40px;
                        font-size: 12px;
                        color: #666;
                        border-top: 1px solid #eee;
                        padding-top: 10px;
                    }
                    @media print {
                        body { padding: 20px; }
                        .no-print { display: none !important; }
                        .form-section { break-inside: avoid; }
                    }
                </style>
            </head>
            <body>
                <div class="watermark">CLEARANCE FORM</div>
                
                <div class="header">
                    <h1>BACOLOD CITY GOVERNMENT</h1>
                    <h2>OFFICE CLEARANCE FORM</h2>
                    <p><em>For Retirement/Resignation/Transfer Purposes</em></p>
                </div>
                
                <div class="form-section">
                    <h3>EMPLOYEE INFORMATION</h3>
                    <div class="info-grid">
                        <div class="info-label">Employee Name:</div>
                        <div>${employeeName}</div>
                        
                        <div class="info-label">Employee ID:</div>
                        <div>${employeeId}</div>
                        
                        <div class="info-label">Department:</div>
                        <div>${department}</div>
                        
                        <div class="info-label">Position:</div>
                        <div>${position}</div>
                        
                        <div class="info-label">Age:</div>
                        <div>${currentAge} years old</div>
                        
                        <div class="info-label">Purpose:</div>
                        <div>Retirement from Service</div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>CLEARANCES REQUIRED</h3>
                    <p><strong>Note:</strong> This form must be presented to each department for clearance.</p>
                    
                    <div style="margin: 20px 0;">
                        <h4 style="color: #667eea; margin-bottom: 15px;">DEPARTMENT CLEARANCES:</h4>
                        
                        <div style="margin: 15px 0; padding: 15px; border: 1px solid #e0e0e0; border-radius: 5px;">
                            <strong>1. PROPERTY OFFICE</strong>
                            <div style="margin-top: 30px;">
                                <div class="signature-line"></div>
                                <div style="text-align: center; margin-top: 5px;">
                                    <em>Property Custodian</em>
                                </div>
                                <div style="text-align: center; margin-top: 5px;">
                                    Date: ___________________
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin: 15px 0; padding: 15px; border: 1px solid #e0e0e0; border-radius: 5px;">
                            <strong>2. ACCOUNTING OFFICE</strong>
                            <div style="margin-top: 30px;">
                                <div class="signature-line"></div>
                                <div style="text-align: center; margin-top: 5px;">
                                    <em>Accountant/Authorized Signatory</em>
                                </div>
                                <div style="text-align: center; margin-top: 5px;">
                                    Date: ___________________
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin: 15px 0; padding: 15px; border: 1px solid #e0e0e0; border-radius: 5px;">
                            <strong>3. ADMINISTRATIVE OFFICE</strong>
                            <div style="margin-top: 30px;">
                                <div class="signature-line"></div>
                                <div style="text-align: center; margin-top: 5px;">
                                    <em>Administrative Officer</em>
                                </div>
                                <div style="text-align: center; margin-top: 5px;">
                                    Date: ___________________
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin: 15px 0; padding: 15px; border: 1px solid #e0e0e0; border-radius: 5px;">
                            <strong>4. HUMAN RESOURCE MANAGEMENT SERVICES</strong>
                            <div style="margin-top: 30px;">
                                <div class="signature-line"></div>
                                <div style="text-align: center; margin-top: 5px;">
                                    <em>HRMS Head/Authorized Representative</em>
                                </div>
                                <div style="text-align: center; margin-top: 5px;">
                                    Date: ___________________
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>FINAL HRMS VERIFICATION</h3>
                    <p>This section to be completed by HRMS after all clearances are obtained.</p>
                    
                    <div style="margin-top: 40px;">
                        <div class="signature-box">
                            <div class="signature-line" style="width: 300px;"></div>
                            <div style="text-align: center; margin-top: 5px;">
                                <strong>HRMS VERIFICATION OFFICER</strong>
                            </div>
                            <div style="text-align: center; margin-top: 5px;">
                                Signature over Printed Name
                            </div>
                            <div style="text-align: center; margin-top: 5px;">
                                Date: ___________________
                            </div>
                        </div>
                        
                        <div class="signature-box">
                            <div style="width: 100px; height: 80px; border: 1px solid #000; display: inline-block; vertical-align: middle;">
                                <div style="text-align: center; line-height: 80px; font-size: 12px;">
                                    HRMS OFFICIAL STAMP
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="footer-note">
                    <p><strong>IMPORTANT:</strong> Submit completed form to HRMS for processing.</p>
                    <p>Form Reference: HRMS-CLR-001 | Revision: 1.0 | Effective Date: ${today.getFullYear()}</p>
                </div>
            </body>
            </html>
        `;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(printContent);
        printWindow.document.close();
        
        // Wait for content to load, then trigger print
        printWindow.onload = function() {
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
                printWindow.onafterprint = function() {
                    printWindow.close();
                };
            }, 500);
        };
    }
    
    function printApplicationForm() {
        // First, make sure the form is visible
        showRetirementForm();
        
        // Get form data
        const form = document.getElementById('retirementApplicationForm');
        if (!form) {
            showAlert('Please open the retirement form first.', 'warning');
            return;
        }

        const formData = new FormData(form);
        const today = new Date();
        
        const printContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Retirement Application Form</title>
                <style>
                    body { 
                        font-family: 'Times New Roman', serif; 
                        padding: 40px;
                        line-height: 1.6;
                        color: #000;
                        background: #fff;
                    }
                    .header {
                        text-align: center;
                        margin-bottom: 40px;
                        border-bottom: 3px double #000;
                        padding-bottom: 20px;
                    }
                    .header h1 {
                        font-size: 28px;
                        margin: 0;
                        font-weight: bold;
                        letter-spacing: 1px;
                    }
                    .header h2 {
                        font-size: 22px;
                        margin: 10px 0;
                        color: #333;
                    }
                    .form-section {
                        margin: 30px 0;
                        page-break-inside: avoid;
                    }
                    .form-section h3 {
                        font-size: 18px;
                        border-bottom: 1px solid #000;
                        padding-bottom: 5px;
                        margin-bottom: 20px;
                        font-weight: bold;
                    }
                    .info-grid {
                        margin: 15px 0;
                    }
                    .info-row {
                        margin-bottom: 12px;
                        display: flex;
                    }
                    .info-label {
                        width: 200px;
                        font-weight: bold;
                        flex-shrink: 0;
                    }
                    .info-value {
                        flex-grow: 1;
                        border-bottom: 1px dotted #000;
                        padding-bottom: 3px;
                    }
                    .checkbox-list {
                        margin: 20px 0 0 20px;
                    }
                    .checkbox-item {
                        margin-bottom: 15px;
                        display: flex;
                        align-items: flex-start;
                    }
                    .checkbox-box {
                        width: 20px;
                        height: 20px;
                        border: 1px solid #000;
                        margin-right: 10px;
                        margin-top: 2px;
                        flex-shrink: 0;
                    }
                    .declaration {
                        margin: 40px 0;
                        padding: 20px;
                        border: 1px solid #000;
                        background: #f9f9f9;
                    }
                    .signature-area {
                        margin-top: 80px;
                    }
                    .signature-line {
                        margin-top: 40px;
                        border-top: 1px solid #000;
                        width: 300px;
                        display: inline-block;
                    }
                    .signature-box {
                        display: inline-block;
                        margin: 0 50px;
                        text-align: center;
                    }
                    .watermark {
                        position: fixed;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%) rotate(-45deg);
                        font-size: 100px;
                        color: rgba(0,0,0,0.05);
                        z-index: -1;
                        white-space: nowrap;
                        font-weight: bold;
                    }
                    .footer {
                        position: fixed;
                        bottom: 20px;
                        left: 0;
                        right: 0;
                        text-align: center;
                        font-size: 12px;
                        color: #666;
                    }
                    @page {
                        size: A4;
                        margin: 2cm;
                    }
                    @media print {
                        body { padding: 0; }
                        .no-print { display: none !important; }
                        .form-section { page-break-inside: avoid; }
                        .footer { position: fixed; }
                    }
                </style>
            </head>
            <body>
                <div class="watermark">RETIREMENT FORM</div>
                
                <div class="header">
                    <h1>REPUBLIC OF THE PHILIPPINES</h1>
                    <h2>BACOLOD CITY GOVERNMENT</h2>
                    <h3>RETIREMENT APPLICATION FORM</h3>
                    <p><em>(Pursuant to Republic Act No. 8291)</em></p>
                </div>
                
                <div class="form-section">
                    <h3>I. PERSONAL INFORMATION</h3>
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-label">Name of Applicant:</div>
                            <div class="info-value">${phpData.employeeName}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Age:</div>
                            <div class="info-value">${phpData.currentAge} years old</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Type of Retirement:</div>
                            <div class="info-value">${formData.get('retirement_type') === 'optional' ? 'OPTIONAL RETIREMENT (Age 60)' : 'COMPULSORY RETIREMENT (Age 65)'}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Proposed Last Day:</div>
                            <div class="info-value">${formData.get('last_day') || ''}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Reason for Retirement:</div>
                            <div class="info-value">${formData.get('reason') || ''}</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>II. DOCUMENTARY REQUIREMENTS CHECKLIST</h3>
                    <p><strong>Check if document is ready/available:</strong></p>
                    
                    <div class="checkbox-list">
                        <div class="checkbox-item">
                            <div class="checkbox-box"></div>
                            <div>
                                <strong>Updated Service Record</strong>
                                <div style="font-size: 14px; color: #555; margin-top: 3px;">
                                    Certified by HR. Required for GSIS and leave monetization claims.
                                </div>
                            </div>
                        </div>
                        
                        <div class="checkbox-item">
                            <div class="checkbox-box"></div>
                            <div>
                                <strong>Office Clearance</strong>
                                <div style="font-size: 14px; color: #555; margin-top: 3px;">
                                    Signed by Property, Accounting, and Admin departments.
                                </div>
                            </div>
                        </div>
                        
                        <div class="checkbox-item">
                            <div class="checkbox-box"></div>
                            <div>
                                <strong>GSIS Retirement Application Form</strong>
                                <div style="font-size: 14px; color: #555; margin-top: 3px;">
                                    Fill out in 3 copies. Available for download from GSIS website.
                                </div>
                            </div>
                        </div>
                        
                        <div class="checkbox-item">
                            <div class="checkbox-box"></div>
                            <div>
                                <strong>Statement of Assets, Liabilities and Net Worth (SALN)</strong>
                                <div style="font-size: 14px; color: #555; margin-top: 3px;">
                                    Latest copy submitted to HR.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>III. DECLARATION</h3>
                    <div class="declaration">
                        <p>I, <strong>${phpData.employeeName}</strong>, hereby declare that:</p>
                        <ul style="margin: 15px 0 15px 20px;">
                            <li>The information provided in this application is true and correct to the best of my knowledge.</li>
                            <li>I understand that retirement is irrevocable once approved.</li>
                            <li>I will submit all required documents within 30 days from filing.</li>
                            <li>I authorize the Human Resource Management Services to process my retirement application with the Government Service Insurance System (GSIS).</li>
                            <li>I have cleared all my accountabilities with the Bacolod City Government.</li>
                        </ul>
                        
                        <div style="margin-top: 40px;">
                            <div class="signature-box">
                                <div class="signature-line"></div>
                                <div style="text-align: center; margin-top: 5px;">
                                    <strong>APPLICANT'S SIGNATURE</strong>
                                </div>
                                <div style="text-align: center; margin-top: 5px;">
                                    Date: ___________________
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>IV. FOR HRMS USE ONLY</h3>
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-label">Date Received:</div>
                            <div class="info-value">___________________</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Received By:</div>
                            <div class="info-value">___________________</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">HRMS Action:</div>
                            <div class="info-value">
                                [ ] Forwarded to GSIS<br>
                                [ ] Returned for completion<br>
                                [ ] Approved for processing<br>
                                [ ] Other: ___________________
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Remarks:</div>
                            <div class="info-value">_________________________________________</div>
                        </div>
                    </div>
                    
                    <div class="signature-area">
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <div style="text-align: center; margin-top: 5px;">
                                <strong>HRMS OFFICER</strong>
                            </div>
                            <div style="text-align: center; margin-top: 5px;">
                                Signature over Printed Name
                            </div>
                            <div style="text-align: center; margin-top: 5px;">
                                Date: ___________________
                            </div>
                        </div>
                        
                        <div class="signature-box">
                            <div style="width: 120px; height: 100px; border: 2px solid #000; display: inline-block; vertical-align: middle; position: relative;">
                                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; font-size: 12px; line-height: 1.2;">
                                    HRMS<br>OFFICIAL<br>STAMP
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="footer">
                    <p><strong>IMPORTANT:</strong> This form must be submitted with complete documents. Incomplete applications will not be processed.</p>
                    <p>Form Code: HRMS-RET-001 | Revision: 1.0 | Generated on: ${today.toLocaleDateString()} ${today.toLocaleTimeString()}</p>
                </div>
            </body>
            </html>
        `;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(printContent);
        printWindow.document.close();
        
        // Wait for content to load, then trigger print
        printWindow.onload = function() {
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
                printWindow.onafterprint = function() {
                    printWindow.close();
                };
            }, 1000);
        };
    }
    
    function downloadGSISForm() {
        window.open('https://www.gsis.gov.ph/downloads/forms/20180213-FORM-Retirement.pdf', '_blank');
        showAlert('GSIS form download started in new tab.', 'success');
    }
    
    function viewApplicationStatus() {
        showAlert('Application status feature coming soon.', 'info');
    }
    
    // ============================================
    // REAL-TIME CLOCK
    // ============================================
    function updateClock() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-PH', { 
            hour12: true, 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit' 
        });
        const dateStr = now.toLocaleDateString('en-PH', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        const timeEl = document.getElementById('real-time');
        const dateEl = document.getElementById('real-date');
        
        if (timeEl) timeEl.textContent = timeStr;
        if (dateEl) dateEl.textContent = dateStr;
    }
    
    // Update clock every second
    setInterval(updateClock, 1000);
    updateClock(); // Initial call
    
    // ============================================
    // ANIMATION STYLES
    // ============================================
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    `;
    document.head.appendChild(style);
    
    // ============================================
    // INITIALIZE DYNAMIC COMPONENTS
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        // Add animation to form when shown
        const form = document.getElementById('retirementForm');
        if (form) {
            form.style.animation = 'fadeIn 0.5s ease-out';
        }
        
        // Add hover effects to radio buttons
        const radioLabels = document.querySelectorAll('label input[type="radio"]');
        radioLabels.forEach(radio => {
            radio.addEventListener('change', function() {
                const parent = this.closest('label');
                if (this.checked) {
                    parent.style.borderColor = '#667eea';
                    parent.style.backgroundColor = '#f0f4ff';
                } else {
                    parent.style.borderColor = '#ddd';
                    parent.style.backgroundColor = 'transparent';
                }
            });
        });
        
        // Initialize tooltips
        const dateInput = document.querySelector('input[type="date"]');
        if (dateInput) {
            dateInput.addEventListener('focus', function() {
                this.title = 'Select a date after today and before your 65th birthday';
            });
        }
        
        // Initialize checkboxes
        const checkboxes = document.querySelectorAll('.document-checklist input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const parent = this.closest('label');
                if (this.checked) {
                    parent.style.backgroundColor = '#f0f9ff';
                    parent.style.borderLeft = '3px solid #3b82f6';
                } else {
                    parent.style.backgroundColor = 'transparent';
                    parent.style.borderLeft = 'none';
                }
            });
        });
    });
    </script>
</body>
</html>
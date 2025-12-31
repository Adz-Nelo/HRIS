<?php
session_start();
require_once '../../config/config.php'; 

// --- FETCH LOGGED-IN USER DATA ---
$displayName = "User"; 
$displayRole = "Staff"; 

if (isset($_SESSION['employee_id'])) {
    try {
        $stmtUser = $pdo->prepare("SELECT first_name, last_name, role FROM employee WHERE employee_id = ?");
        $stmtUser->execute([$_SESSION['employee_id']]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $displayName = $user['first_name'];
            $displayRole = $user['role'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching user data: " . $e->getMessage());
    }
}

try {
    /**
     * LOGIC: Reset activity logs every day at 8:00 AM
     */
    $threshold = (date('H') < 8) ? date('Y-m-d 08:00:00', strtotime('yesterday')) : date('Y-m-d 08:00:00');

    $stmtLogs = $pdo->prepare("
        SELECT e.first_name, e.last_name, l.updated_at 
        FROM login_attempts l
        JOIN employee e ON l.employee_id = e.employee_id
        WHERE l.updated_at >= ?
        ORDER BY l.updated_at DESC
    ");
    $stmtLogs->execute([$threshold]);
    $recentLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $recentLogs = [];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Documents - HRMS</title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* Modern Document Grid */
        .doc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 5px;
        }

        .doc-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            transition: all 0.2s ease;
        }

        .doc-card:hover {
            border-color: #2563eb;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .doc-icon {
            width: 45px;
            height: 45px;
            background: #f1f5f9;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #475569;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .doc-info {
            flex-grow: 1;
        }

        .doc-name {
            font-weight: 700;
            color: #1e293b;
            font-size: 0.95rem;
            margin-bottom: 4px;
            display: block;
        }

        .doc-date {
            font-size: 0.8rem;
            color: #64748b;
            display: block;
            margin-bottom: 12px;
        }

        .doc-btns {
            display: flex;
            gap: 8px;
        }

        .btn-dl {
            padding: 6px 12px;
            background: #2563eb;
            color: white !important;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-dl:hover { background: #1d4ed8; }

        .btn-view {
            padding: 6px 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #64748b;
            border-radius: 6px;
            text-decoration: none;
        }

        .status-tag {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            color: #16a34a;
            background: #dcfce7;
            padding: 2px 8px;
            border-radius: 4px;
            margin-bottom: 8px;
            display: inline-block;
        }

        /* Sidebar Sections */
        .sidebar-section-title {
            font-size: 11px;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 15px 0 10px 0;
            display: block;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 5px;
        }

        .guideline-item {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .guideline-item i {
            margin-top: 3px;
            font-size: 0.9rem;
        }

        .security-note {
            margin-top: 15px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid #cbd5e1;
        }
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
                        <h1>My Documents</h1>
                        <p>View and download the files uploaded for you by the HR office.</p>
                    </div>
                    <div class="date-time-widget text-end">
                        <div class="time fw-bold" id="real-time">--:--:-- --</div>
                        <div class="date text-muted" id="real-date">Loading...</div>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    <div class="feed-container">
                        <div class="content-card">
                            <div class="card-header">
                                <h2><i class="fa-solid fa-file-invoice me-2"></i>Received Files</h2>
                            </div>
                            
                            <div class="p-4">
                                <div class="doc-grid">
                                    <div class="doc-card">
                                        <div class="doc-icon" style="color: #ef4444; background: #fee2e2;">
                                            <i class="fa-solid fa-file-pdf"></i>
                                        </div>
                                        <div class="doc-info">
                                            <span class="status-tag">Ready to Download</span>
                                            <span class="doc-name">Loyalty Award Application Form</span>
                                            <span class="doc-date">Received: Dec 19, 2025</span>
                                            <div class="doc-btns">
                                                <a href="#" class="btn-dl"><i class="fa-solid fa-download"></i> Download</a>
                                                <a href="#" class="btn-view" title="View"><i class="fa-solid fa-eye"></i></a>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="doc-card">
                                        <div class="doc-icon" style="color: #2563eb; background: #eff6ff;">
                                            <i class="fa-solid fa-file-contract"></i>
                                        </div>
                                        <div class="doc-info">
                                            <span class="status-tag">Ready to Download</span>
                                            <span class="doc-name">Verified Service Record</span>
                                            <span class="doc-date">Received: Nov 15, 2025</span>
                                            <div class="doc-btns">
                                                <a href="#" class="btn-dl"><i class="fa-solid fa-download"></i> Download</a>
                                                <a href="#" class="btn-view" title="View"><i class="fa-solid fa-eye"></i></a>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="doc-card">
                                        <div class="doc-icon" style="color: #16a34a; background: #f0fdf4;">
                                            <i class="fa-solid fa-file-circle-check"></i>
                                        </div>
                                        <div class="doc-info">
                                            <span class="status-tag">Ready to Download</span>
                                            <span class="doc-name">Benefit Claim Summary</span>
                                            <span class="doc-date">Received: Oct 02, 2025</span>
                                            <div class="doc-btns">
                                                <a href="#" class="btn-dl"><i class="fa-solid fa-download"></i> Download</a>
                                                <a href="#" class="btn-view" title="View"><i class="fa-solid fa-eye"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="side-info-container">
                        <div class="content-card">
                            <div class="card-header">
                                <h2>HR Support & Guidelines</h2>
                            </div>
                            <div class="p-3">
                                <span class="sidebar-section-title">Contact Support</span>
                                <ul class="activity-list" style="margin-bottom: 20px;">
                                    <li class="mb-2">
                                        <i class="fa-solid fa-phone text-blue"></i>
                                        <div><small class="d-block fw-bold">Local Hotline</small> <small class="text-muted">Dial 402 or 405</small></div>
                                    </li>
                                    <li>
                                        <i class="fa-solid fa-envelope text-orange"></i>
                                        <div><small class="d-block fw-bold">Email</small> <small class="text-muted">hrms-support@company.com</small></div>
                                    </li>
                                </ul>

                                <span class="sidebar-section-title">Printing Guidelines</span>
                                <div class="guideline-item">
                                    <i class="fa-solid fa-ruler-combined text-primary"></i>
                                    <div>
                                        <strong style="font-size: 12px;" class="d-block">Legal Size Paper</strong>
                                        <small class="text-muted">Use 8.5" x 13" (Long Bond) for all CSC/GSIS forms.</small>
                                    </div>
                                </div>
                                <div class="guideline-item">
                                    <i class="fa-solid fa-pen-nib text-primary"></i>
                                    <div>
                                        <strong style="font-size: 12px;" class="d-block">Original Signatures</strong>
                                        <small class="text-muted">Visit HR for wet-ink signature and dry seal after printing.</small>
                                    </div>
                                </div>
                                <div class="guideline-item">
                                    <i class="fa-solid fa-calendar-check text-primary"></i>
                                    <div>
                                        <strong style="font-size: 12px;" class="d-block">Document Validity</strong>
                                        <small class="text-muted">Service Records are valid for 6 months from issue date.</small>
                                    </div>
                                </div>

                                <div class="security-note">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="fa-solid fa-shield-halved text-muted" style="font-size: 1rem;"></i>
                                        <strong style="font-size: 12px; color: #1e293b;">Secure Portal</strong>
                                    </div>
                                    <p style="font-size: 11px; color: #64748b; margin: 0;">
                                        Documents are encrypted. Log out if using a shared computer.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>

    <script src="/HRIS/assets/js/script.js"></script>
</body>
</html>
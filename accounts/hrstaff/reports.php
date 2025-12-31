<?php
session_start();
require_once '../../config/config.php';

// ✅ Access Control
$allowed_roles = ['Admin', 'HR Officer', 'HR Staff', 'Department Head'];
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'] ?? $_SESSION['role'] ?? '', $allowed_roles)) {
    header("Location: ../../login.html");
    exit();
}

// ✅ Heartbeat Update
try {
    $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?")
        ->execute([$_SESSION['employee_id']]);
} catch (PDOException $e) { 
    /* silent fail */ 
}

$default_profile_image = '../../assets/images/default_user.png';

// --- FILTERS & PAGINATION ---
$search_query = $_GET['search'] ?? '';
$dept_filter = $_GET['dept'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // 1. Fetch Departments for filter dropdown
    $departments = $pdo->query("SELECT * FROM department ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Build Base Query
    $base_sql = "FROM leave_application l
                 JOIN employee e ON l.employee_id = e.employee_id
                 LEFT JOIN department d ON e.department_id = d.department_id
                 WHERE 1=1";
    
    $params = [];
    if (!empty($search_query)) {
        $base_sql .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR l.reference_no LIKE ?)";
        $search_val = "%$search_query%";
        $params = array_merge($params, [$search_val, $search_val, $search_val]);
    }
    if (!empty($dept_filter)) {
        $base_sql .= " AND e.department_id = ?";
        $params[] = $dept_filter;
    }

    // 3. Get Total Count for Pagination
    $count_stmt = $pdo->prepare("SELECT COUNT(*) $base_sql");
    $count_stmt->execute($params);
    $total_rows = $count_stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // 4. Fetch Data
    $data_sql = "SELECT l.*, e.first_name, e.last_name, e.profile_pic, d.department_name 
                 $base_sql 
                 ORDER BY l.created_at DESC 
                 LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($data_sql);
    $stmt->execute($params);
    $report_log = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Report Log Error: " . $e->getMessage());
    die("Database Error: Could not load report data.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - HRMS</title>
    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* GAP & LAYOUT OVERRIDES */
        .dashboard-wrapper { gap: 15px !important; } 
        .welcome-header { margin-bottom: 0 !important; }
        .stats-grid { margin-bottom: 0 !important; }
        
        /* content-card overrides */
        .content-card.table-card { 
            margin-top: 0 !important; 
            padding: 0 !important;
        }

        .report-log-header {
            padding: 15px 25px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .report-log-header h2 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        /* Stats Card Styling */
        .stat-card { 
            cursor: pointer; 
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .download-label { 
            font-size: 10px; 
            font-weight: 800; 
            color: #1d4ed8; 
            margin-top: 5px; 
            display: block; 
            text-transform: uppercase;
        }
        
        .new-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ef4444;
            color: white;
            font-size: 9px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: bold;
        }

        /* Integrated Filter Area */
        .integrated-filter {
            padding: 12px 25px;
            background: #fbfcfd;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .filter-inputs input, .filter-inputs select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            font-size: 13px;
        }

        .ref-no { font-family: monospace; color: #1d4ed8; font-weight: 600; font-size: 12px; }
        .status { padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: 700; }
        .status.approved { background: #dcfce7; color: #166534; }
        .status.pending { background: #fef3c7; color: #92400e; }
        .status.rejected { background: #fee2e2; color: #991b1b; }

        .emp-info-cell { display: flex; align-items: center; gap: 10px; }
        .emp-profile-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
        
        /* Table styling */
        .table-container { padding: 0; margin: 0; }
        .ledger-table { width: 100%; border-collapse: collapse; margin: 0; }
        .ledger-table th { background: #f8fafc; padding: 12px 25px; }
        .ledger-table td { padding: 12px 25px; }
        
        /* Quick Export Buttons */
        .quick-export {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
            padding: 0 25px;
        }
        
        .export-btn {
            padding: 12px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: inherit;
        }
        
        .export-btn:hover {
            background: #4f46e5;
            color: white;
            transform: translateY(-3px);
            border-color: #4f46e5;
        }
        
        .export-btn:hover i {
            color: white;
        }
        
        .export-btn i {
            font-size: 1.5em;
            color: #4f46e5;
        }
        
        .export-btn .label {
            font-size: 12px;
            font-weight: 600;
        }
        
        .export-btn .format {
            font-size: 10px;
            opacity: 0.7;
        }
        
        /* Loading overlay - UPDATED */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        
        .loading-spinner {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            min-width: 200px;
        }
        
        .loading-spinner i {
            font-size: 2em;
            color: #4f46e5;
            margin-bottom: 15px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-progress {
            width: 100%;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            margin-top: 15px;
            overflow: hidden;
        }
        
        .loading-bar {
            height: 100%;
            background: #4f46e5;
            width: 0%;
            transition: width 0.3s;
            border-radius: 2px;
        }
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
                        <h1>Reports & Analytics</h1>
                        <p>Generate exports and track system activity.</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <!-- Using JavaScript functions instead of direct links -->
                    <div class="stat-card" onclick="downloadReport('employee_masterlist')">
                        <div class="stat-icon blue"><i class="fa-solid fa-file-pdf"></i></div>
                        <div class="stat-info">
                            <h3>Employee Masterlist</h3>
                            <p>Full database export</p>
                            <span class="download-label">DOWNLOAD CSV</span>
                        </div>
                    </div>
                    
                    <div class="stat-card" onclick="downloadReport('leave_analytics')">
                        <div class="stat-icon green"><i class="fa-solid fa-file-csv"></i></div>
                        <div class="stat-info">
                            <h3>Leave Analytics</h3>
                            <p>Summary of credits</p>
                            <span class="download-label">DOWNLOAD CSV</span>
                        </div>
                    </div>
                    
                    <div class="stat-card" onclick="downloadReport('service_records')">
                        <div class="stat-icon red"><i class="fa-solid fa-id-badge"></i></div>
                        <div class="stat-info">
                            <h3>Service Records</h3>
                            <p>Active & Retired Personnel</p>
                            <span class="download-label">DOWNLOAD CSV</span>
                        </div>
                    </div>
                    
                    <!-- Retirement Analytics Card -->
                    <div class="stat-card" onclick="window.location.href='retirement_reports.php'">
                        <span class="new-badge">NEW</span>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                            <i class="fa-solid fa-chart-line" style="color: white;"></i>
                        </div>
                        <div class="stat-info">
                            <h3 style="color: #7c3aed;">Retirement Analytics</h3>
                            <p>Growth & Benefits Reports</p>
                            <span class="download-label">VIEW ANALYTICS</span>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Export Buttons with JavaScript functions -->
                <div class="quick-export">
                    <a href="javascript:void(0)" class="export-btn" onclick="downloadReport('employee_masterlist')">
                        <i class="fa-solid fa-file-pdf"></i>
                        <div class="label">Employee Masterlist</div>
                        <div class="format">CSV Export</div>
                    </a>
                    
                    <a href="javascript:void(0)" class="export-btn" onclick="downloadReport('leave_analytics')">
                        <i class="fa-solid fa-file-csv"></i>
                        <div class="label">Leave Analytics</div>
                        <div class="format">CSV Export</div>
                    </a>
                    
                    <a href="javascript:void(0)" class="export-btn" onclick="downloadReport('service_records')">
                        <i class="fa-solid fa-id-badge"></i>
                        <div class="label">Service Records</div>
                        <div class="format">CSV Export</div>
                    </a>
                    
                    <a href="javascript:void(0)" class="export-btn" onclick="downloadReport('retirement_report')">
                        <i class="fa-solid fa-retirement"></i>
                        <div class="label">Retirement Report</div>
                        <div class="format">CSV Export</div>
                    </a>
                    
                    <a href="javascript:void(0)" class="export-btn" onclick="downloadReport('department_roster')">
                        <i class="fa-solid fa-building"></i>
                        <div class="label">Department Roster</div>
                        <div class="format">CSV Export</div>
                    </a>
                </div>

                <div class="content-card table-card">
                    <div class="report-log-header">
                        <h2>Report Activity Log</h2>
                    </div>

                    <form method="GET" class="integrated-filter">
                        <div class="filter-inputs">
                            <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search Name or Reference #">
                            <select name="dept" onchange="this.form.submit()">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['department_id'] ?>" <?= $dept_filter == $d['department_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 13px;">
                                <i class="fa-solid fa-magnifying-glass"></i> Search
                            </button>
                            <a href="reports.php" class="btn-secondary" style="padding: 8px 16px; font-size: 13px;">
                                <i class="fa-solid fa-rotate-left"></i> Reset
                            </a>
                        </div>
                    </form>

                    <div class="table-container">
                        <table class="ledger-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Ref No.</th>
                                    <th>Filing Date</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($report_log)): ?>
                                    <?php foreach ($report_log as $row): 
                                        $s = $row['status'];
                                        $class = strtolower(str_replace(' ', '', in_array($s, ['Approved', 'Officer Recommended']) ? 'approved' : $s));
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="emp-info-cell">
                                                <img src="<?= $row['profile_pic'] ?: $default_profile_image ?>" class="emp-profile-img">
                                                <span style="font-weight: 600;"><?= htmlspecialchars($row['last_name'] . ', ' . $row['first_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><span class="ref-no"><?= htmlspecialchars($row['reference_no']) ?></span></td>
                                        <td><?= date('M d, Y', strtotime($row['date_filing'])) ?></td>
                                        <td class="text-center">
                                            <span class="status <?= $class ?>"><?= strtoupper($s) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <a href="print-leave.php?id=<?= $row['application_id'] ?>" class="icon-btn" style="color: #059669;" title="Print"><i class="fa-solid fa-print"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align:center; padding: 50px; color: #94a3b8;">No records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <div class="pagination-container" style="padding: 15px 25px; border-top: 1px solid #edf2f7;">
                        <div class="pagination">
                            <button onclick="window.location.href='?page=<?= max(1, $page-1) ?>&dept=<?= $dept_filter ?>&search=<?= urlencode($search_query) ?>'" <?= $page <= 1 ? 'disabled' : '' ?>><i class="fa-solid fa-chevron-left"></i></button>
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <button onclick="window.location.href='?page=<?= $i ?>&dept=<?= $dept_filter ?>&search=<?= urlencode($search_query) ?>'" class="<?= $page == $i ? 'active' : '' ?>"><?= $i ?></button>
                            <?php endfor; ?>
                            <button onclick="window.location.href='?page=<?= min($total_pages, $page+1) ?>&dept=<?= $dept_filter ?>&search=<?= urlencode($search_query) ?>'" <?= $page >= $total_pages ? 'disabled' : '' ?>><i class="fa-solid fa-chevron-right"></i></button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <div id="rightbar-placeholder"></div>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <i class="fas fa-spinner"></i>
            <div id="loadingMessage">Generating report...</div>
            <div class="loading-progress">
                <div class="loading-bar" id="loadingBar"></div>
            </div>
            <small id="loadingDetail">This may take a few seconds</small>
            <button onclick="hideLoading()" style="margin-top: 15px; padding: 5px 15px; background: #ef4444; color: white; border: none; border-radius: 5px; cursor: pointer;">
                Cancel
            </button>
        </div>
    </div>

    <script src="/HRIS/assets/js/script.js"></script>
    <script>
    // ============================================
    // LOADING OVERLAY FUNCTIONS
    // ============================================
    function showLoading(message = 'Generating report...', detail = 'This may take a few seconds') {
        document.getElementById('loadingMessage').textContent = message;
        document.getElementById('loadingDetail').textContent = detail;
        document.getElementById('loadingOverlay').style.display = 'flex';
        
        // Animate progress bar
        let progress = 0;
        const loadingBar = document.getElementById('loadingBar');
        const interval = setInterval(() => {
            progress += Math.random() * 10;
            if (progress > 90) progress = 90; // Stop at 90%
            loadingBar.style.width = progress + '%';
        }, 300);
        
        // Store interval ID for cleanup
        window.loadingInterval = interval;
        
        // Auto-hide after 30 seconds (safety net)
        window.loadingTimeout = setTimeout(() => {
            hideLoading();
            alert('Report generation timed out. Please try again.');
        }, 30000);
    }
    
    function hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
        document.getElementById('loadingBar').style.width = '0%';
        
        // Clear intervals
        if (window.loadingInterval) {
            clearInterval(window.loadingInterval);
        }
        if (window.loadingTimeout) {
            clearTimeout(window.loadingTimeout);
        }
    }
    
    // ============================================
    // DOWNLOAD REPORT FUNCTION
    // ============================================
    function downloadReport(type) {
        if (!confirm(`Generate ${type.replace('_', ' ')} report?`)) {
            return;
        }
        
        // Map report types to file names
        const reportFiles = {
            'employee_masterlist': 'export_employee_masterlist.php',
            'leave_analytics': 'export_leave_analytics.php',
            'service_records': 'export_service_records.php',
            'retirement_report': 'export_retirement_report.php',
            'department_roster': 'export_department_roster.php'
        };
        
        const fileName = reportFiles[type];
        if (!fileName) {
            alert('Report type not found.');
            return;
        }
        
        // Show loading overlay
        showLoading(`Preparing ${type.replace('_', ' ')}...`, 'Download will start automatically');
        
        // Method 1: Create hidden iframe for download (Most reliable)
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = fileName;
        document.body.appendChild(iframe);
        
        // Hide loading after a short delay (when download should start)
        setTimeout(() => {
            hideLoading();
            document.body.removeChild(iframe);
            
            // Show success message
            showAlert(`${type.replace('_', ' ')} downloaded successfully!`, 'success');
        }, 2000);
    }
    
    // ============================================
    // ALERT FUNCTION
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
            alertDiv.style.backgroundColor = '#ef4444';
            alertDiv.innerHTML = `<i class="fas fa-times-circle me-2"></i> ${message}`;
        } else {
            alertDiv.style.backgroundColor = '#3b82f6';
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
    // INITIALIZATION
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        // Add CSS animations
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
        `;
        document.head.appendChild(style);
        
        // Add click effects to stat cards
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => {
            card.addEventListener('mousedown', function() {
                this.style.transform = 'translateY(-2px) scale(0.98)';
            });
            
            card.addEventListener('mouseup', function() {
                this.style.transform = 'translateY(-5px) scale(1)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
        
        // Add confirmation for export buttons
        const exportButtons = document.querySelectorAll('.export-btn');
        exportButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                // Prevent default since we handle with onclick
                e.preventDefault();
            });
        });
        
        // Hide loading on page load (just in case)
        hideLoading();
    });
    
    // Hide loading if user navigates away
    window.addEventListener('beforeunload', function() {
        hideLoading();
    });
    </script>
</body>
</html>
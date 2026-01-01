<?php
session_start();

// Add CSRF token for form protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

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
    <title>Employee Reports - HRMS</title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css">
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">





    <style>
        /* Report Selection Grid - Main Container */
        .report-selection-grid {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 15px;
        }

        /* Individual Report Item */
        .report-box {
            display: flex;
            align-items: center;
            padding: 18px 22px;
            background: #fdfdfd;
            border: 1px solid #eef2f6;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .report-box:hover {
            border-color: #3498db;
            background: #ffffff;
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
        }

        /* Modern Circular Icon */
        .report-icon {
            width: 52px;
            height: 52px;
            background: #ebf5ff;
            color: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 22px;
            margin-right: 20px;
            flex-shrink: 0;
        }

        /* Text Details */
        .report-details {
            flex-grow: 1;
        }

        .report-details h4 {
            margin: 0;
            font-size: 15px;
            color: #1e293b;
            font-weight: 700;
            letter-spacing: -0.3px;
        }

        .report-details p {
            margin: 3px 0 0;
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
        }

        /* Action Button */
        .btn-gen {
            background: #3498db;
            color: #ffffff !important;
            border: none;
            padding: 9px 20px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-gen:hover {
            background: #2980b9;
        }

        /* Filter Controls & Sidebar */
        .filter-section label {
            font-size: 11px;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            margin-bottom: 6px;
            display: block;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            color: #1e293b;
            background-color: #ffffff;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .mb-3 {
            margin-bottom: 20px;
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
                        <h1>Reports & Performance</h1>
                        <p>Generate certified summaries of your attendance, leave, and service records.</p>
                    </div>
                    <div class="date-time-widget">
                        <div class="time" id="real-time">--:--:-- --</div>
                        <div class="date" id="real-date">Loading...</div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $annual_data['total_late'] ?? '0' ?></h3>
                            <p>Total Tardiness (YTD)</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red">
                            <i class="fa-solid fa-user-slash"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $annual_data['total_absent'] ?? '0' ?></h3>
                            <p>Absences</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $annual_data['leave_balance'] ?? '0' ?></h3>
                            <p>Leave Credits</p>
                        </div>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    <div class="feed-container">
                        <div class="content-card">
                            <div class="card-header">
                                <h2>Generate New Report</h2>
                            </div>

                            <div class="p-4">
                                <div class="report-selection-grid">
                                    <div class="report-box">
                                        <div class="report-icon"><i class="fa-solid fa-file-invoice"></i></div>
                                        <div class="report-details">
                                            <h4>Service Record Summary</h4>
                                            <p>Detailed history of positions and salary adjustments.</p>
                                        </div>
                                        <button class="btn-gen" onclick="generateReport('service_record')">Generate</button>
                                    </div>

                                    <div class="report-box">
                                        <div class="report-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                                        <div class="report-details">
                                            <h4>Leave Ledger (Card)</h4>
                                            <p>Monthly breakdown of earned and used leave credits.</p>
                                        </div>
                                        <button class="btn-gen" onclick="generateReport('leave_ledger')">Generate</button>
                                    </div>

                                    <div class="report-box">
                                        <div class="report-icon"><i class="fa-solid fa-fingerprint"></i></div>
                                        <div class="report-details">
                                            <h4>DTR Log Summary</h4>
                                            <p>Consolidated time-in and time-out for a specific month.</p>
                                        </div>
                                        <button class="btn-gen" onclick="generateReport('dtr_summary')">Generate</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="side-info-container">
                        <div class="content-card">
                            <div class="card-header">
                                <h2>Report Filters</h2>
                            </div>
                            <div class="p-4">
                                <div class="filter-section">
                                    <label>Select Year</label>
                                    <select id="reportYear" class="form-control mb-3">
                                        <?php
                                        $currentYear = date('Y');
                                        for ($i = $currentYear; $i >= 2020; $i--) {
                                            echo '<option value="' . $i . '" ' . ($i == $currentYear ? 'selected' : '') . '>' . $i . '</option>';
                                        }
                                        ?>
                                    </select>

                                    <label>Select Month</label>
                                    <select id="reportMonth" class="form-control mb-3">
                                        <?php
                                        $months = [
                                            'January',
                                            'February',
                                            'March',
                                            'April',
                                            'May',
                                            'June',
                                            'July',
                                            'August',
                                            'September',
                                            'October',
                                            'November',
                                            'December'
                                        ];
                                        $currentMonth = date('F');
                                        foreach ($months as $month) {
                                            echo '<option value="' . $month . '" ' . ($month == $currentMonth ? 'selected' : '') . '>' . $month . '</option>';
                                        }
                                        ?>
                                    </select>

                                    <div style="margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">
                                        <p class="small text-secondary mb-0">
                                            <i class="fa-solid fa-circle-info me-1"></i>
                                            Reports are generated in PDF format and can be printed on <strong>A4 or Legal paper</strong>.
                                        </p>
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
        function generateReport(type) {
            // Show loading state
            const originalText = event.target.innerHTML;
            event.target.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Generating...';
            event.target.disabled = true;

            // Get selected year and month from filters
            const year = document.getElementById('reportYear').value;
            const month = document.getElementById('reportMonth').value;

            // Create form data
            const formData = new FormData();
            formData.append('report_type', type);
            formData.append('year', year);
            formData.append('month', month);
            formData.append('employee_id', '<?php echo $_SESSION["employee_id"] ?? 0; ?>');
            formData.append('csrf_token', '<?php echo $_SESSION["csrf_token"] ?? ""; ?>');

            // Send request to generate report
            fetch('/HRIS/includes/generate_report.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Show success message
                        showAlert(data.message || 'Report generated successfully!', 'success');

                        // Download the file
                        if (data.file_url) {
                            // Create a temporary link to download the file
                            const downloadLink = document.createElement('a');
                            downloadLink.href = data.file_url;
                            downloadLink.download = data.file_name || 'report.pdf';
                            document.body.appendChild(downloadLink);
                            downloadLink.click();
                            document.body.removeChild(downloadLink);

                            // Also show option to open in new tab
                            setTimeout(() => {
                                if (confirm('Report generated successfully! Would you like to open it in a new tab?')) {
                                    window.open(data.file_url, '_blank');
                                }
                            }, 500);
                        }
                    } else {
                        showAlert(data.message || 'Failed to generate report', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error generating report: ' + error.message, 'error');
                })
                .finally(() => {
                    // Reset button state
                    event.target.innerHTML = originalText;
                    event.target.disabled = false;
                });
        }

        // Alert function for better notifications
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
                alertDiv.style.backgroundColor = '#3498db';
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

        // Add animation styles
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
    </script>
    <script src="/HRIS/assets/js/script.js"></script>
</body>

</html>
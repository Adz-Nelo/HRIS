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
        /* COMPACT Document Grid */
        .doc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            padding: 10px;
        }

        .doc-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 18px;
            text-align: center;
            transition: all 0.2s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .doc-card:hover {
            border-color: #2563eb;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-3px);
        }

        .doc-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ef4444;
            font-size: 1.3rem;
            margin: 0 auto 12px;
        }

        .doc-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 1rem;
            margin-bottom: 8px;
            display: block;
            line-height: 1.3;
        }

        .doc-desc {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 15px;
            line-height: 1.4;
            flex-grow: 1;
        }

        .btn-dl {
            padding: 8px 16px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white !important;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            width: 100%;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-dl:hover { 
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            text-decoration: none;
            transform: scale(1.02);
        }

        .btn-dl i {
            margin-right: 6px;
            font-size: 0.9rem;
        }

        .status-tag {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: #16a34a;
            background: #dcfce7;
            padding: 3px 8px;
            border-radius: 12px;
            margin-bottom: 8px;
            display: inline-block;
            letter-spacing: 0.3px;
        }

        .category-badge {
            font-size: 0.65rem;
            background: #e0f2fe;
            color: #0369a1;
            padding: 2px 8px;
            border-radius: 10px;
            display: inline-block;
            margin-bottom: 8px;
            margin-left: 5px;
        }

        /* Even Smaller Grid Option */
        .doc-grid.small {
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 12px;
        }
        
        .doc-grid.small .doc-card {
            padding: 15px;
        }
        
        .doc-grid.small .doc-icon {
            width: 45px;
            height: 45px;
            font-size: 1.2rem;
            margin-bottom: 10px;
        }
        
        .doc-grid.small .doc-name {
            font-size: 0.95rem;
        }
        
        .doc-grid.small .doc-desc {
            font-size: 0.78rem;
            margin-bottom: 12px;
        }
        
        .doc-grid.small .btn-dl {
            padding: 7px 14px;
            font-size: 0.82rem;
        }

        /* Extra Small Grid Option */
        .doc-grid.xsmall {
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 10px;
        }
        
        .doc-grid.xsmall .doc-card {
            padding: 12px;
        }
        
        .doc-grid.xsmall .doc-icon {
            width: 40px;
            height: 40px;
            font-size: 1rem;
            margin-bottom: 8px;
        }
        
        .doc-grid.xsmall .doc-name {
            font-size: 0.9rem;
        }
        
        .doc-grid.xsmall .doc-desc {
            font-size: 0.75rem;
            margin-bottom: 10px;
        }
        
        .doc-grid.xsmall .btn-dl {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 2.5rem;
            color: #cbd5e1;
            margin-bottom: 12px;
        }

        .alert-message {
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: none;
        }

        .alert-success {
            background: #dcfce7;
            border: 1px solid #22c55e;
            color: #166534;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #ef4444;
            color: #991b1b;
        }

        /* Sidebar Sections - Compact */
        .sidebar-section-title {
            font-size: 10px;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 12px 0 8px 0;
            display: block;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 4px;
        }

        .guideline-item {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .guideline-item i {
            margin-top: 2px;
            font-size: 0.85rem;
            color: #64748b;
        }

        .guideline-item div {
            font-size: 0.8rem;
        }

        .guideline-item strong {
            font-size: 0.85rem;
            color: #1e293b;
        }

        .guideline-item small {
            font-size: 0.75rem;
        }

        .security-note {
            margin-top: 12px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 6px;
            border-left: 3px solid #cbd5e1;
        }
        
        .contact-item {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
            align-items: center;
        }
        
        .contact-icon {
            width: 35px;
            height: 35px;
            background: #eff6ff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2563eb;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        
        /* Grid Size Controls */
        .grid-controls {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
            justify-content: flex-end;
        }
        
        .grid-btn {
            padding: 5px 10px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.8rem;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .grid-btn:hover {
            background: #e2e8f0;
        }
        
        .grid-btn.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div id="sidebar-placeholder"></div>

        <main class="main-content" id="main-content">
            <div id="topbar-placeholder"></div>

            <div class="dashboard-wrapper">
                <?php if (isset($_SESSION['error'])): ?>
                <div class="alert-message alert-error" id="errorAlert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php 
                        echo htmlspecialchars($_SESSION['error']);
                        unset($_SESSION['error']);
                    ?>
                    <button onclick="this.parentElement.style.display='none'" style="float:right; background:none; border:none; color:#991b1b; cursor:pointer;">×</button>
                </div>
                <script>
                    document.getElementById('errorAlert').style.display = 'block';
                </script>
                <?php endif; ?>

                <div class="welcome-header">
                    <div class="welcome-text">
                        <h1 style="font-size: 1.8rem; margin-bottom: 5px;">My Documents</h1>
                        <p style="font-size: 0.95rem;">Download official HR forms and records</p>
                    </div>
                    <div class="date-time-widget text-end">
                        <div class="time fw-bold" id="real-time" style="font-size: 1rem;">--:--:-- --</div>
                        <div class="date text-muted" id="real-date" style="font-size: 0.85rem;">Loading...</div>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    <div class="feed-container">
                        <div class="content-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h2 style="font-size: 1.3rem;">
                                    <i class="fa-solid fa-file-invoice me-2"></i> Available Documents
                                </h2>
                                <div class="grid-controls">
                                    <!-- <button class="grid-btn active" onclick="setGridSize('normal')" title="Normal Size">
                                        <i class="fa-solid fa-grip"></i>
                                    </button>
                                    <button class="grid-btn" onclick="setGridSize('small')" title="Small Grid">
                                        <i class="fa-solid fa-grip-small"></i>
                                    </button>
                                    <button class="grid-btn" onclick="setGridSize('xsmall')" title="Extra Small">
                                        <i class="fa-solid fa-grip-vertical"></i>
                                    </button> -->
                                    <button onclick="refreshPage()" class="btn-dl" style="background: #64748b; padding: 6px 12px; font-size: 0.8rem; margin-left: 10px;">
                                        <i class="fa-solid fa-rotate"></i> Refresh
                                    </button>
                                </div>
                            </div>
                            
                            <div class="p-3">
                                <div class="doc-grid" id="docGrid">
                                    <!-- Document 1: Loyalty Award Form -->
                                    <div class="doc-card">
                                        <div>
                                            <span class="status-tag">Official</span>
                                            <span class="category-badge">Form</span>
                                        </div>
                                        <div class="doc-icon">
                                            <i class="fa-solid fa-file-pdf"></i>
                                        </div>
                                        <span class="doc-name">Loyalty Award Application</span>
                                        <p class="doc-desc">
                                            Apply for loyalty awards based on years of service. Required for all milestone awards.
                                        </p>
                                        <a href="../../uploads/documents/loyalty_award_form.pdf" 
                                           class="btn-dl" 
                                           download="Loyalty_Award_Form.pdf"
                                           onclick="return trackDownload('Loyalty Award Form')">
                                            <i class="fa-solid fa-download"></i> Download
                                        </a>
                                    </div>

                                    <!-- Document 2: Service Record -->
                                    <div class="doc-card">
                                        <div>
                                            <span class="status-tag">Verified</span>
                                            <span class="category-badge">Record</span>
                                        </div>
                                        <div class="doc-icon">
                                            <i class="fa-solid fa-file-contract"></i>
                                        </div>
                                        <span class="doc-name">Service Record</span>
                                        <p class="doc-desc">
                                            Official verified employment history. Valid for 6 months. Required for promotions and loans.
                                        </p>
                                        <a href="../../uploads/documents/service_record_verified.pdf" 
                                           class="btn-dl" 
                                           download="Service_Record.pdf"
                                           onclick="return trackDownload('Service Record')">
                                            <i class="fa-solid fa-download"></i> Download
                                        </a>
                                    </div>

                                    <!-- Document 3: Benefit Summary -->
                                    <div class="doc-card">
                                        <div>
                                            <span class="status-tag">Monthly</span>
                                            <span class="category-badge">Benefits</span>
                                        </div>
                                        <div class="doc-icon">
                                            <i class="fa-solid fa-file-invoice-dollar"></i>
                                        </div>
                                        <span class="doc-name">Benefit Summary</span>
                                        <p class="doc-desc">
                                            Summary of benefits, leave balances, and claims history. Updated monthly by HRD.
                                        </p>
                                        <a href="../../uploads/documents/benefit_claim_summary.pdf" 
                                           class="btn-dl" 
                                           download="Benefit_Summary.pdf"
                                           onclick="return trackDownload('Benefit Summary')">
                                            <i class="fa-solid fa-download"></i> Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="side-info-container">
                        <div class="content-card">
                            <div class="card-header">
                                <h2 style="font-size: 1.3rem;">HR Support</h2>
                            </div>
                            <div class="p-3">
                                <span class="sidebar-section-title">Quick Contact</span>
                                <div class="contact-item">
                                    <div class="contact-icon">
                                        <i class="fa-solid fa-phone"></i>
                                    </div>
                                    <div>
                                        <strong style="font-size: 0.85rem;">HR Hotline</strong><br>
                                        <small style="font-size: 0.75rem;">Local 402 or 405</small>
                                    </div>
                                </div>
                                
                                <div class="contact-item">
                                    <div class="contact-icon">
                                        <i class="fa-solid fa-envelope"></i>
                                    </div>
                                    <div>
                                        <strong style="font-size: 0.85rem;">Email</strong><br>
                                        <small style="font-size: 0.75rem;">hr-support@bacolod.gov.ph</small>
                                    </div>
                                </div>

                                <span class="sidebar-section-title">Printing Tips</span>
                                <div class="guideline-item">
                                    <i class="fa-solid fa-ruler"></i>
                                    <div>
                                        <strong>Legal Size</strong><br>
                                        <small>8.5" x 13" for CSC forms</small>
                                    </div>
                                </div>
                                <div class="guideline-item">
                                    <i class="fa-solid fa-pen"></i>
                                    <div>
                                        <strong>Wet Signatures</strong><br>
                                        <small>Visit HR for original signatures</small>
                                    </div>
                                </div>
                                <div class="guideline-item">
                                    <i class="fa-solid fa-calendar"></i>
                                    <div>
                                        <strong>6 Months Valid</strong><br>
                                        <small>Service records expiry</small>
                                    </div>
                                </div>

                                <div class="security-note">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="fa-solid fa-shield" style="font-size: 0.9rem;"></i>
                                        <strong style="font-size: 0.85rem;">Secure Portal</strong>
                                    </div>
                                    <p style="font-size: 0.75rem; color: #64748b; margin: 0;">
                                        Log out on shared computers.
                                    </p>
                                </div>
                                
                                <span class="sidebar-section-title">Quick Links</span>
                                <div style="font-size: 0.8rem; color: #64748b;">
                                    <p class="mb-2">
                                        <i class="fa-solid fa-file-signature me-2"></i>
                                        <a href="#" style="color: #2563eb; text-decoration: none;">Leave Form</a>
                                    </p>
                                    <p class="mb-2">
                                        <i class="fa-solid fa-id-card me-2"></i>
                                        <a href="#" style="color: #2563eb; text-decoration: none;">ID Request</a>
                                    </p>
                                    <p>
                                        <i class="fa-solid fa-hand-holding-dollar me-2"></i>
                                        <a href="#" style="color: #2563eb; text-decoration: none;">Loan Form</a>
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
    <script>
    // Grid size control
    function setGridSize(size) {
        const grid = document.getElementById('docGrid');
        const buttons = document.querySelectorAll('.grid-btn');
        
        // Remove all size classes
        grid.classList.remove('normal', 'small', 'xsmall');
        
        // Add selected size class
        grid.classList.add(size);
        
        // Update active button
        buttons.forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
        
        // Save preference
        localStorage.setItem('docGridSize', size);
    }
    
    // Load saved grid size
    document.addEventListener('DOMContentLoaded', function() {
        const savedSize = localStorage.getItem('docGridSize') || 'normal';
        setGridSize(savedSize);
        
        // Set active button
        const buttons = document.querySelectorAll('.grid-btn');
        buttons.forEach(btn => {
            const size = btn.getAttribute('onclick')?.match(/'([^']+)'/)?.[1];
            if (size === savedSize) {
                btn.classList.add('active');
            }
        });
        
        // Initialize any existing alerts
        const errorAlert = document.getElementById('errorAlert');
        if (errorAlert) {
            setTimeout(() => errorAlert.style.display = 'none', 5000);
        }
        
        // Set up real-time clock
        function updateTime() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', {hour12: true, hour: '2-digit', minute:'2-digit', second:'2-digit'});
            const dateStr = now.toLocaleDateString('en-US', {weekday: 'short', year: 'numeric', month: 'short', day: 'numeric'});
            
            document.getElementById('real-time').textContent = timeStr;
            document.getElementById('real-date').textContent = dateStr;
        }
        
        updateTime();
        setInterval(updateTime, 1000);
    });
    
    // Show messages
    function showMessage(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert-message alert-${type}`;
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
            ${message}
            <button onclick="this.parentElement.remove()" style="float:right; background:none; border:none; cursor:pointer;">×</button>
        `;
        document.querySelector('.welcome-header').after(alertDiv);
        setTimeout(() => alertDiv.remove(), 5000);
    }
    
    // Track download
    function trackDownload(docName) {
        console.log(`Downloading: ${docName}`);
        showMessage('success', `Downloading ${docName}...`);
        return true;
    }
    
    function refreshPage() {
        showMessage('info', 'Refreshing...');
        setTimeout(() => location.reload(), 300);
    }
    </script>
</body>
</html>
<?php
session_start();
require_once '../../config/config.php';

// ✅ FIX 1: Access Control (Standardized to role_name)
$allowed_roles = ['Admin', 'HR Officer', 'HR Staff'];
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'], $allowed_roles)) {
    header("Location: ../../login.html");
    exit();
}

// ✅ FIX 2: Heartbeat Update
try {
    $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?")
        ->execute([$_SESSION['employee_id']]);
} catch (PDOException $e) { 
    /* silent fail */ 
}

// --- DATA RETRIEVAL ---
try {
    // ✅ FIX 3: Robust Query for Active Balances
    // Fetches all active employees and their latest leave balance records
    $stmt = $pdo->prepare("
        SELECT 
            e.employee_id, e.first_name, e.last_name, e.profile_pic,
            lb.vacation_leave, lb.sick_leave, lb.month_year, lb.leave_balance_id,
            lb.is_latest
        FROM employee e
        LEFT JOIN leave_balance lb ON e.employee_id = lb.employee_id AND lb.is_latest = 1
        WHERE e.status = 'Active'
        ORDER BY e.last_name ASC
    ");
    $stmt->execute();
    $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Secure error logging
    error_log("Leave Balance List Error: " . $e->getMessage());
    die("Database Error: Unable to load leave balances.");
}

$default_profile_image = '../../assets/images/default_user.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Leave Balances - HR Staff</title>
    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* Essential Layout Fixes */
        .dashboard-wrapper { 
            display: flex;
            flex-direction: column;
            gap: 15px !important; 
        }

        .db-filter-bar {
            padding: 12px 20px;
            background: #fbfcfd;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .db-filter-bar input {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            font-size: 13px;
            width: 250px;
        }

        .credit-pill {
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 12px;
            display: inline-block;
            min-width: 50px;
            text-align: center;
        }
        .vl-pill { background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe; }
        .sl-pill { background: #f0fdf4; color: #166534; border: 1px solid #dcfce7; }

        /* UPDATED MODAL OVERLAY FOR FULL BLUR */
        .modal-overlay { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100vw; 
            height: 100vh; 
            background: rgba(15, 23, 42, 0.5); /* Semi-transparent dark blue/grey */
            backdrop-filter: blur(8px); /* The Magic: Blurs sidebar, topbar, and background */
            -webkit-backdrop-filter: blur(8px);
            z-index: 99999; /* Ensure it is above Sidebar and Rightbar */
        }

        .update-modal { 
            background: #fff; 
            width: 420px; 
            position: fixed; /* Keep it fixed relative to viewport */
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%); /* Perfectly centered */
            border-radius: 12px; 
            overflow: hidden; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); 
        }

        .modal-header { padding: 18px 24px; background: #fff; border-bottom: 1px solid #f1f5f9; font-weight: 700; font-size: 1.1rem; }
        .modal-body { padding: 24px; }
        .modal-body label { display: block; font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 6px; text-transform: uppercase; }
        .modal-body input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 18px; box-sizing: border-box; }
        .modal-body input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .modal-footer { padding: 16px 24px; text-align: right; background: #f8fafc; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 10px;}
        
        .btn-cancel { background:#e2e8f0; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight: 600; color: #475569; }
        .btn-save { background:#3b82f6; color:#fff; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight: 600; }
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
                        <h1>Leave Balance Management</h1>
                        <p>View and manually adjust employee leave credits for the current period.</p>
                    </div>
                </div>

                <div class="main-dashboard-grid">
                    <div class="feed-container">
                        <div class="content-card">
                            <div class="db-filter-bar">
                                <i class="fa-solid fa-magnifying-glass" style="color: #94a3b8;"></i>
                                <input type="text" id="empSearch" placeholder="Search employee name...">
                                <button onclick="location.reload()" style="margin-left:auto; background:none; border:none; color:#3b82f6; cursor:pointer;">
                                    <i class="fa-solid fa-rotate"></i> Refresh
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="ledger-table" id="balanceTable">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Vacation Leave</th>
                                            <th>Sick Leave</th>
                                            <th>As of Date</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($balances as $row): ?>
                                        <tr>
                                            <td>
                                                <div style="display:flex; align-items:center; gap:10px;">
                                                    <img src="<?= $row['profile_pic'] ?: '../../assets/images/default_user.png' ?>" 
                                                         style="width:30px; height:30px; border-radius:50%; object-fit:cover; border: 1px solid #e2e8f0;"
                                                         onerror="this.src='../../assets/images/default_user.png'">
                                                    <strong><?= htmlspecialchars($row['last_name'] . ', ' . $row['first_name']) ?></strong>
                                                </div>
                                            </td>
                                            <td><span class="credit-pill vl-pill"><?= number_format($row['vacation_leave'] ?? 0, 3) ?></span></td>
                                            <td><span class="credit-pill sl-pill"><?= number_format($row['sick_leave'] ?? 0, 3) ?></span></td>
                                            <td><small class="text-muted"><?= $row['month_year'] ? date('M d, Y', strtotime($row['month_year'])) : '---' ?></small></td>
                                            <td class="text-center">
                                                <button class="btn-adjust" onclick="openModal(<?= htmlspecialchars(json_encode($row)) ?>)" 
                                                        style="background:#3b82f6; color:#fff; border:none; padding:5px 12px; border-radius:4px; font-size:12px; cursor:pointer;">
                                                    <i class="fa-solid fa-pen-to-square"></i> Adjust
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="side-info-container">
                        <div class="content-card">
                            <div class="card-header">
                                <h2>Quick Actions</h2>
                            </div>
                            <div style="padding: 20px;">
                                <button style="width:100%; padding:10px; background:#10b981; color:#fff; border:none; border-radius:5px; margin-bottom:10px; cursor:pointer;">
                                    <i class="fa-solid fa-file-export"></i> Export Credits
                                </button>
                                <div style="margin-top:20px; padding:15px; background:#fff7ed; border-radius:8px; border:1px solid #ffedd5;">
                                    <h4 style="color:#9a3412; font-size:13px;"><i class="fa-solid fa-circle-info"></i> Note:</h4>
                                    <p style="font-size:11px; color:#c2410c; margin-top:5px;">
                                        Credits usually earn 1.25 points per month. Adjustments will reflect immediately in the ledger.
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

    <div class="modal-overlay" id="modalOverlay">
        <div class="update-modal">
            <div class="modal-header">Update Leave Credits</div>
            <form action="update_balance_process.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="employee_id" id="modal_emp_id">
                    
                    <div style="padding: 12px; background: #f1f5f9; border-radius: 8px; margin-bottom: 20px;">
                        <small style="color: #64748b; display: block; font-weight: 600; margin-bottom: 2px;">EMPLOYEE</small>
                        <strong id="modal_emp_name" style="color:#0f172a; font-size: 15px;"></strong>
                    </div>
                    
                    <label>Vacation Leave Balance</label>
                    <input type="number" step="0.001" name="vacation_leave" id="modal_vl" required>
                    
                    <label>Sick Leave Balance</label>
                    <input type="number" step="0.001" name="sick_leave" id="modal_sl" required>
                    
                    <label>Adjustment Remarks</label>
                    <input type="text" name="remarks" placeholder="Reason for adjustment..." required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-save">Update Balance</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Search Logic
        document.getElementById('empSearch').addEventListener('keyup', function() {
            let val = this.value.toLowerCase();
            let rows = document.querySelectorAll('#balanceTable tbody tr');
            rows.forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(val) ? '' : 'none';
            });
        });

        // Modal Logic
        function openModal(data) {
            document.getElementById('modalOverlay').style.display = 'block';
            document.getElementById('modal_emp_id').value = data.employee_id;
            document.getElementById('modal_emp_name').textContent = data.last_name + ', ' + data.first_name;
            document.getElementById('modal_vl').value = data.vacation_leave || 0;
            document.getElementById('modal_sl').value = data.sick_leave || 0;
            
            // Prevent background scrolling
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('modalOverlay').style.display = 'none';
            // Restore scrolling
            document.body.style.overflow = 'auto';
        }

        // Close modal if user clicks outside the modal content
        window.onclick = function(event) {
            let overlay = document.getElementById('modalOverlay');
            if (event.target == overlay) {
                closeModal();
            }
        }
    </script>
    <script src="/HRIS/assets/js/script.js"></script>
    </body>
</html>
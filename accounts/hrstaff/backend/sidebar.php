<?php
// Start session and include config
session_start();
include '../../../config/config.php'; // adjust path if needed

// Default values
$full_name = "Guest User";
$role = "Guest";
$profile_image = '../../assets/images/default_user.png';

// Only render sidebar if employee is logged in
if (!isset($_SESSION['employee_id'])) {
    header('Location: ../../login.html');
    exit;
}

$employee_id = $_SESSION['employee_id'];

try {
    $stmt = $pdo->prepare("SELECT first_name, last_name, role, profile_pic FROM employee WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch();

    if ($employee) {
        $full_name = $employee['first_name'] . ' ' . $employee['last_name'];
        $role = $employee['role'];
        if (!empty($employee['profile_pic'])) {
            $profile_image = '../../' . $employee['profile_pic'];
        }
    }
} catch (PDOException $e) {
    die("Error fetching employee: " . $e->getMessage());
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
    /* Benefits Management Styles */
.benefit-card {
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 25px;
    text-align: center;
    transition: all 0.3s ease;
    height: 100%;
    background: white;
}

.benefit-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    border-color: #007bff;
}

.benefit-icon {
    margin-bottom: 15px;
    color: #495057;
}

.benefit-list {
    list-style: none;
    padding: 0;
}

.benefit-item {
    padding: 12px 0;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    align-items: center;
}

.benefit-item:last-child {
    border-bottom: none;
}

/* Claims processing styles */
.claim-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-approved {
    background: #d4edda;
    color: #155724;
}

.status-rejected {
    background: #f8d7da;
    color: #721c24;
}

.status-processing {
    background: #cce5ff;
    color: #004085;
}

/* Enrollment wizard */
.enrollment-step {
    display: none;
}

.enrollment-step.active {
    display: block;
    animation: fadeIn 0.5s ease;
}

.step-indicator {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    position: relative;
}

.step-indicator::before {
    content: '';
    position: absolute;
    top: 15px;
    left: 0;
    right: 0;
    height: 2px;
    background: #e9ecef;
    z-index: 1;
}

.step {
    position: relative;
    z-index: 2;
    text-align: center;
    flex: 1;
}

.step-number {
    width: 32px;
    height: 32px;
    background: #e9ecef;
    color: #6c757d;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px;
    font-weight: bold;
}

.step.active .step-number {
    background: #007bff;
    color: white;
}

.step.completed .step-number {
    background: #28a745;
    color: white;
}
</style>

<div class="sidebar" id="sidebar">
    <div class="sidebar-user-role">
        <div class="role-icon">
            <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile Picture" class="profile-avatar">
        </div>
        <div class="role-info">
            <span class="user-name"><?php echo htmlspecialchars($full_name); ?></span>
            <span class="user-role-label"><?php echo htmlspecialchars($role); ?></span>
        </div>
    </div>

    <div class="menu-section">
        <div class="menu-label">Main</div>
        
        <a href="index.php" class="menu-item" id="dashboardLink">
            <i class="fa-solid fa-house-chimney-user"></i>
            <span class="menu-text">Dashboard</span>
        </a>
        
        <div class="has-flyout">
            <a href="#" class="menu-item">
                <i class="fa-solid fa-calendar-day"></i>
                <span class="menu-text">Leave Management</span>
                <i class="fas fa-chevron-right menu-arrow"></i>
            </a>
            <div class="flyout-submenu">
                <a href="pending-leave.php" class="submenu-item"><i class="fa-solid fa-hourglass-half"></i> Review Leave</a>
                <a href="leave-balance.php" class="submenu-item"><i class="fa-solid fa-file-circle-check"></i> Leave Balance</a>
                <a href="leave-calendar.php" class="submenu-item"><i class="fa-solid fa-calendar-check"></i> Leave Calendar</a>
                <a href="canceled-leave.php" class="submenu-item"><i class="fa-solid fa-ban"></i> Canceled Leave</a>
            </div>
        </div>

        <div class="has-flyout">
            <a href="#" class="menu-item">
                <i class="fa-solid fa-hand-holding-medical"></i>
                <span class="menu-text">Benefits Management</span>
                <i class="fas fa-chevron-right menu-arrow"></i>
            </a>
            <div class="flyout-submenu">
                <a href="online-enrollment.php" class="submenu-item">
                    <i class="fa-solid fa-user-check"></i>
                    <span>Online Benefits Enrollment</span>
                </a>
                <a href="benefits-claims.php" class="submenu-item">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <span>Benefits Claims Processing</span>
                </a>
                <a href="benefits-portal.php" class="submenu-item">
                    <i class="fa-solid fa-info-circle"></i>
                    <span>Benefits Information Portal</span>
                </a>
                <a href="manage-dependents.php" class="submenu-item">
                    <i class="fa-solid fa-users-between-lines"></i>
                    <span>Dependents Management</span>
                </a>
                <a href="validate-claims.php" class="submenu-item">
                    <i class="fa-solid fa-clipboard-check"></i>
                    <span>Validate Claims</span>
                </a>
            </div>
        </div>

        <!-- 2FA Settings - Placed outside Benefits Management -->
        <a href="2fa_settings.php" class="menu-item">
            <i class="fas fa-shield-alt"></i>
            <span class="menu-text">2FA Settings</span>
        </a>

        <div class="has-flyout">
            <a href="#" class="menu-item">
                <i class="fa-solid fa-piggy-bank"></i>
                <span class="menu-text">Retirement Management</span>
                <i class="fas fa-chevron-right menu-arrow"></i>
            </a>
            <div class="flyout-submenu">
                <a href="incoming-retirees.php" class="submenu-item"><i class="fa-solid fa-user-clock"></i> Incoming Retirees</a>
                <a href="view-appointment.php" class="submenu-item"><i class="fa-solid fa-calendar-check"></i> View Appointment</a>
                <a href="retirement-database.php" class="submenu-item"><i class="fa-solid fa-database"></i> Retirement Database</a>
                <!-- NEW: Retirement Analytics Link -->
                <a href="retirement_reports.php" class="submenu-item">
                    <i class="fa-solid fa-chart-line"></i> 
                    <span>Retirement Analytics</span>
                </a>
            </div>
        </div>

        <a href="reports.php" class="menu-item">
            <i class="fa-solid fa-chart-pie"></i>
            <span class="menu-text">Reports</span>
        </a>

        <a href="/HRIS/login.html" class="menu-item">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            <span class="menu-text">Logout</span>
        </a>
    </div>

    <div class="sidebar-footer">
        <small>© 2025 All Rights Reserved</small>
        <small>Version 1.0.0</small>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const dashboardLink = document.getElementById("dashboardLink");
        const flyoutLinks = document.querySelectorAll('.has-flyout > a');

        // Toggle Submenu
        flyoutLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                this.parentElement.classList.toggle('active');
            });
        });

        dashboardLink.addEventListener("click", function(e) {
            // e.preventDefault(); 
            console.log("Dashboard navigated");
        });
    });
</script>
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
            <i class="fas fa-house-user"></i>
            <span class="menu-text">Dashboard</span>
        </a>
        
        <div class="has-flyout">
            <a href="#" class="menu-item">
                <i class="fas fa-calendar-alt"></i>
                <span class="menu-text">Leave</span>
                <i class="fas fa-chevron-right menu-arrow"></i>
            </a>
            <a href="2fa_settings.php" class="menu-item">
                <i class="fas fa-shield-alt"></i>
                <span>2FA Settings</span>
            </a>
            <div class="flyout-submenu">
                <a href="apply-leave.php" class="submenu-item"><i class="fas fa-paper-plane"></i> Apply Leave</a>
                <a href="track-leave.php" class="submenu-item"><i class="fas fa-route"></i> Track Leave</a>
                <a href="leave-balance.php" class="submenu-item"><i class="fas fa-scale-balanced"></i> Leave Balance</a>
                <a href="leave-calendar.php" class="submenu-item"><i class="fas fa-calendar-days"></i> Leave Calendar</a>
            </div>
        </div>

        <a href="benefits.php" class="menu-item"><i class="fas fa-hand-holding-medical"></i><span class="menu-text">Benefits</span></a>
        <a href="retirement.php" class="menu-item"><i class="fas fa-piggy-bank"></i><span class="menu-text">Retirement</span></a>
        <a href="forms.php" class="menu-item"><i class="fas fa-file-signature"></i><span class="menu-text">Forms</span></a>
        <a href="reports.php" class="menu-item"><i class="fas fa-chart-pie"></i><span class="menu-text">Reports</span></a>
        <a href="/HRIS/assets/modal/logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i><span class="menu-text">Logout</span></a>

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
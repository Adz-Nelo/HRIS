<?php
// Start session and include config
session_start();
include '../../../config/config.php'; // adjust path if needed
include '../../../includes/session_helper.php'; // Add this

requireLogin(); // Just check if logged in

// Default values
$full_name = "Guest User";
$role = "Guest";
$profile_image = '../../assets/images/default_user.png';

// Only render sidebar if employee is logged in
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role_name'])) {
    header('Location: ../../login.html');
    exit;
}

$employee_id = $_SESSION['employee_id'];

try {
    $stmt = $pdo->prepare("SELECT first_name, last_name, profile_pic FROM employee WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch();

    if ($employee) {
        $full_name = $employee['first_name'] . ' ' . $employee['last_name'];
        $role = isset($_SESSION['role_name']) ? $_SESSION['role_name'] : 'Guest';
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
  <!-- Main Menu -->
  <div class="menu-section">
        <div class="menu-label">Main</div>
        
        <a href="index.php" class="menu-item" id="dashboardLink">
            <i class="fas fa-th-large"></i>
            <span class="menu-text">Dashboard</span>
        </a>
        
        <div class="has-flyout">
            <a href="#" class="menu-item">
                <i class="fas fa-user-shield"></i>
                <span class="menu-text">User Management</span>
                <i class="fas fa-chevron-right menu-arrow"></i>
            </a>
            <div class="flyout-submenu">
                <a href="add-employee.php" class="submenu-item"><i class="fas fa-plus"></i> Create Account</a>
                <a href="manage-accounts.php" class="submenu-item"><i class="fas fa-user-cog"></i> Manage Accounts</a>
            </div>
        </div>

        <a href="auto_backup.php" class="menu-item"><i class="fas fa-database"></i><span class="menu-text">Back Up</span></a>
        <a href="gender_report.php" class="menu-item"><i class="fas fa-transgender-alt"></i><span class="menu-text">Gender report</span></a>

        <a href="security.php" class="menu-item"><i class="fas fa-shield-alt"></i><span class="menu-text">Security</span></a>
        <a href="builder.php" class="menu-item"><i class="fas fa-globe"></i><span class="menu-text">Website Builder</span></a>
        <a href="reports.php" class="menu-item"><i class="fas fa-chart-bar"></i><span class="menu-text">Reports</span></a>
        <a href="/HRIS/login.html" class="menu-item"><i class="fas fa-sign-out-alt"></i><span class="menu-text">Logout</span></a>

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
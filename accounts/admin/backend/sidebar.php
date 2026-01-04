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
<style>
    /* Simple Mobile View Styles */
    @media (max-width: 768px) {
        .sidebar {
            position: fixed;
            top: 0;
            left: -280px; /* Hidden by default */
            width: 280px;
            height: 100vh;
            background: white;
            z-index: 1000;
            transition: left 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }
        
        .sidebar.active {
            left: 0;
        }
        
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }
        
        .sidebar.active + .sidebar-overlay {
            display: block;
        }
        
        .sidebar-user-role {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .menu-section {
            padding: 10px 0;
        }
        
        .menu-item {
            padding: 12px 20px;
            border-bottom: 1px solid #f1f1f1;
        }
        
        .menu-item:hover {
            background: #f8f9fa;
        }
        
        /* Mobile Menu Toggle Button */
        .mobile-menu-toggle {
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 998;
            background: #4361ee;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 5px;
            font-size: 18px;
            cursor: pointer;
            display: none;
        }
        
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }
        
        /* Adjust flyout for mobile */
        .flyout-submenu {
            display: none;
            background: #f8f9fa;
        }
        
        .has-flyout.active .flyout-submenu {
            display: block;
        }
        
        .submenu-item {
            padding: 10px 20px 10px 40px;
            font-size: 14px;
        }
    }
    
    /* Desktop styles remain the same */
    .sidebar {
        width: 260px;
        height: 100vh;
        background: white;
        border-right: 1px solid #dee2e6;
    }
</style>

<!-- Mobile Menu Toggle Button -->
<button class="mobile-menu-toggle" id="mobileMenuToggle">
    <i class="fas fa-bars"></i>
</button>

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

        <a href="2fa_settings.php" class="menu-item">
            <i class="fas fa-shield-alt"></i>
            <span class="menu-text">2FA Settings</span>
        </a>

        <a href="auto_backup.php" class="menu-item">
            <i class="fas fa-database"></i>
            <span class="menu-text">Back Up</span>
        </a>
        
        <a href="gender_report.php" class="menu-item">
            <i class="fas fa-transgender-alt"></i>
            <span class="menu-text">Gender Report</span>
        </a>

        <a href="security.php" class="menu-item">
            <i class="fas fa-shield-alt"></i>
            <span class="menu-text">Security</span>
        </a>
        
        <a href="builder.php" class="menu-item">
            <i class="fas fa-globe"></i>
            <span class="menu-text">Website Builder</span>
        </a>
        
        <a href="reports.php" class="menu-item">
            <i class="fas fa-chart-bar"></i>
            <span class="menu-text">Reports</span>
        </a>
        
        <a href="/HRIS/login.html" class="menu-item">
            <i class="fas fa-sign-out-alt"></i>
            <span class="menu-text">Logout</span>
        </a>
    </div>

    <div class="sidebar-footer">
        <small>© 2025 All Rights Reserved</small>
        <small>Version 1.0.0</small>
    </div>
</div>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const sidebar = document.getElementById("sidebar");
        const mobileMenuToggle = document.getElementById("mobileMenuToggle");
        const sidebarOverlay = document.getElementById("sidebarOverlay");
        const flyoutLinks = document.querySelectorAll('.has-flyout > a');
        
        // Mobile menu toggle
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        }
        
        // Close sidebar when clicking overlay
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
            });
        }
        
        // Close sidebar when clicking menu items (on mobile)
        if (window.innerWidth <= 768) {
            const menuItems = document.querySelectorAll('.menu-item:not(.has-flyout > a), .submenu-item');
            menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                });
            });
        }
        
        // Toggle Submenu
        flyoutLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    this.parentElement.classList.toggle('active');
                }
            });
        });
        
        // Close sidebar on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });
    });
</script>
<?php
// Start session and include config
session_start();
include '../../../config/config.php'; // adjust path if needed

// Default profile image
$profile_image = '../../assets/images/default_user.png';

// Only show profile image if logged in
if (isset($_SESSION['employee_id'])) {
    $employee_id = $_SESSION['employee_id'];

    try {
        $stmt = $pdo->prepare("SELECT profile_pic FROM employee WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch();

        if ($employee && !empty($employee['profile_pic'])) {
            $profile_image = '../../' . $employee['profile_pic'];
        }
    } catch (PDOException $e) {
        // Optional: log error and continue with default image
    }
}
?>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<div class="topbar">
    <div class="topbar-left">
        <i class="fas fa-bars menu-toggle" id="menuToggle"></i>
        <div class="logo-section">
            <img src="../../assets/images/HRMS.png" alt="Logo" class="logo">
            <span class="logo-text" id="logoText">HRMS</span>
        </div>
    </div>

    <div class="topbar-right">
        <!-- Email clickable -->
        <a href="messages.php" class="topbar-icon-link">
            <i class="far fa-envelope email-icon-top"></i>
        </a>

        <!-- Profile clickable -->
        <a href="profile.php" class="topbar-icon-link">
            <div class="topbar-profile">
                <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Profile" class="profile-avatar-top">
            </div>
        </a>
    </div>
</div>



<script>
// Optional sidebar toggle
document.getElementById('menuToggle').addEventListener('click', function() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.classList.toggle('collapsed');
    }
});
</script>

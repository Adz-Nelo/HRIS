<?php
require_once __DIR__ . '/../../../config/config.php';

// ===========================
// CONFIGURATION
// ===========================
$default_profile_image = 'assets/images/default_user.png';
$today = date('m-d');
$logged_in_id = $_SESSION['employee_id'] ?? null;

// Role colors for the badges
$role_colors = [
    'Regular Employee' => '#6B7280', // Gray
    'HR Officer'       => '#10B981', // Green
    'HR Staff'         => '#3B82F6', // Blue
    'Department Head'  => '#F59E0B', // Amber
    'Admin'            => '#EF4444', // Red
];

// 1. Fetch Birthdays
$birthdays = [];
try {
    $stmt = $pdo->prepare("
        SELECT first_name, last_name, profile_pic 
        FROM employee 
        WHERE status='Active' 
        AND DATE_FORMAT(birth_date, '%m-%d') = ?
    ");
    $stmt->execute([$today]);
    $birthdays = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching birthdays: " . $e->getMessage());
}

// 2. Fetch Active Employees
$activeEmployees = [];
try {
    $stmt = $pdo->prepare("
        SELECT employee_id, first_name, last_name, role, profile_pic, last_active 
        FROM employee 
        WHERE status='Active' AND employee_id != ?
        ORDER BY last_active DESC 
        LIMIT 20
    ");
    $stmt->execute([$logged_in_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $is_online = false;
        $status_text = "Offline";

        if (!empty($row['last_active']) && $row['last_active'] !== '0000-00-00 00:00:00') {
            $last_active = strtotime($row['last_active']);
            $diff = time() - $last_active;

            if ($diff < 120) { // Active within 2 minutes
                $is_online = true;
                $status_text = "Online";
            } elseif ($diff < 3600) {
                $status_text = floor($diff / 60) . "m ago";
            } elseif ($diff < 86400) {
                $status_text = floor($diff / 3600) . "h ago";
            } else {
                $status_text = floor($diff / 86400) . "d ago";
            }
        }

        $activeEmployees[] = [
            'full_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'profile_image' => !empty($row['profile_pic']) ? $row['profile_pic'] : $default_profile_image,
            'role' => $row['role'] ?? 'Regular Employee',
            'is_online' => $is_online,
            'status_text' => $status_text,
            'role_color' => $role_colors[$row['role']] ?? '#6B7280'
        ];
    }
} catch (PDOException $e) {
    error_log("Error fetching active employees: " . $e->getMessage());
}
?>

<style>
    .right-sidebar { width: 280px; padding: 15px; background: transparent; font-family: 'Inter', Arial, sans-serif; }
    
    /* Layout */
    .section-label { font-size: 13px; color: #65676b; font-weight: 600; margin-bottom: 10px; display: block; }
    .sidebar-divider { border: 0; border-top: 1px solid #e4e6eb; margin: 15px 0; }
    
    /* Employee Items */
    .employee-item { display: flex; align-items: center; justify-content: space-between; padding: 8px; border-radius: 8px; transition: 0.2s; text-decoration: none; color: inherit; }
    .employee-item:hover { background: #f2f2f2; }
    .emp-left { display: flex; align-items: center; gap: 12px; }
    
    /* Avatar & Status */
    .avatar-container { position: relative; width: 40px; height: 40px; }
    .profile-pic { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
    .status-indicator { position: absolute; bottom: 1px; right: 1px; width: 11px; height: 11px; border-radius: 50%; border: 2px solid #fff; background: #9ca3af; }
    .status-indicator.online { background: #31a24c; }

    /* Text info */
    .emp-info-text { display: flex; flex-direction: column; line-height: 1.2; }
    .emp-name { font-size: 13px; font-weight: 600; color: #050505; }
    .status-time { font-size: 11px; color: #65676b; font-weight: 400; }
    
    /* Role Badge (Curved above name) */
    .role-badge {
        font-size: 9px;
        color: white;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 10px;
        text-transform: uppercase;
        width: fit-content;
        margin-bottom: 2px;
    }
</style>

<div class="right-sidebar" id="right-sidebar">

    <div class="sponsored-section">
        <span class="section-label">Sponsored</span>
        <a href="https://web.facebook.com/hrmsbacolod" target="_blank" class="sponsor-item" style="text-decoration:none; display:flex; align-items:center; gap:12px;">
            <img src="../../assets/images/sponsors.png" alt="HRMS Bacolod" style="width:100px; border-radius:8px;">
            <div class="sponsor-details">
                <span style="display:block; font-size:12px; font-weight:600; color:#050505;">HRMS Bacolod</span>
                <span style="font-size:11px; color:#65676b;">web.facebook.com</span>
            </div>
        </a>
    </div>

    <hr class="sidebar-divider">

    <div class="birthday-section">
        <span class="section-label">Birthdays</span>
        <?php if(!empty($birthdays)): ?>
            <?php foreach($birthdays as $b): ?>
                <div class="birthday-item" style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                    <i class="fas fa-gift" style="color: #f35a7a;"></i>
                    <span class="birthday-text" style="font-size:13px;">
                        <strong><?= htmlspecialchars($b['first_name']); ?></strong> has a birthday today!
                    </span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <span style="font-size:12px; color:#888;">No birthdays today.</span>
        <?php endif; ?>
    </div>

    <hr class="sidebar-divider">

    <div class="rightbar-header" style="display:flex; justify-content:space-between; align-items:center;">
        <span class="section-label">Contacts</span>
        <div class="header-actions" style="display:flex; gap:12px; color:#65676b; font-size:14px; cursor:pointer;">
            <i class="fas fa-video"></i>
            <i class="fas fa-search"></i>
            <i class="fas fa-ellipsis-h"></i>
        </div>
    </div>

    <div class="employee-list">
        <?php foreach($activeEmployees as $emp): ?>
        <div class="employee-item">
            <div class="emp-left">
                <div class="avatar-container">
                    <img src="<?= htmlspecialchars($emp['profile_image']); ?>" class="profile-pic">
                    <div class="status-indicator <?= $emp['is_online'] ? 'online' : '' ?>"></div>
                </div>
                <div class="emp-info-text">
                    <span class="role-badge" style="background-color: <?= $emp['role_color'] ?>;">
                        <?= htmlspecialchars($emp['role']) ?>
                    </span>
                    <span class="emp-name"><?= htmlspecialchars($emp['full_name']); ?></span>
                </div>
            </div>
            
            <?php if(!$emp['is_online']): ?>
                <span class="status-time"><?= $emp['status_text'] ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
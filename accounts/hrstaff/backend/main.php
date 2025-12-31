<?php
session_start();
require_once '../../../config/config.php'; 

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

// --- FETCH RETIREMENT STATISTICS ---
$retirement_stats = [
    'eligible_retirees' => 0,
    'approaching_retirees' => 0,
    'retire_this_year' => 0,
    'avg_retirement_age' => 0
];

try {
    // Get retirement statistics
    $stmtRetire = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) >= 60 THEN 1 ELSE 0 END) as eligible,
            SUM(CASE WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 55 AND 59 THEN 1 ELSE 0 END) as approaching,
            SUM(CASE WHEN YEAR(DATE_ADD(birth_date, INTERVAL 60 YEAR)) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as this_year,
            ROUND(AVG(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())), 1) as avg_age
        FROM employee 
        WHERE status = 'Active' 
            AND birth_date IS NOT NULL 
            AND birth_date != '0000-00-00'
    ");
    $stmtRetire->execute();
    $retirement_data = $stmtRetire->fetch(PDO::FETCH_ASSOC);
    
    if ($retirement_data) {
        $retirement_stats['eligible_retirees'] = $retirement_data['eligible'] ?? 0;
        $retirement_stats['approaching_retirees'] = $retirement_data['approaching'] ?? 0;
        $retirement_stats['retire_this_year'] = $retirement_data['this_year'] ?? 0;
        $retirement_stats['avg_retirement_age'] = $retirement_data['avg_age'] ?? 0;
    }
    
    // Get upcoming retirements (next 3 months)
    $stmtUpcoming = $pdo->prepare("
        SELECT 
            e.first_name,
            e.last_name,
            e.position,
            d.department_name,
            TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) as age,
            DATE_FORMAT(DATE_ADD(e.birth_date, INTERVAL 60 YEAR), '%M %Y') as retirement_month,
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) >= 60 THEN 'eligible'
                ELSE 'approaching'
            END as status
        FROM employee e
        LEFT JOIN department d ON e.department_id = d.department_id
        WHERE e.status = 'Active' 
            AND e.birth_date IS NOT NULL 
            AND e.birth_date != '0000-00-00'
            AND TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) >= 55
            AND DATE_ADD(e.birth_date, INTERVAL 60 YEAR) <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
        ORDER BY DATE_ADD(e.birth_date, INTERVAL 60 YEAR) ASC
        LIMIT 5
    ");
    $stmtUpcoming->execute();
    $upcoming_retirements = $stmtUpcoming->fetchAll(PDO::FETCH_ASSOC);
    
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
    $upcoming_retirements = [];
}
?>

<style>
    /* ========== SCROLLABLE LOGS CSS ========== */
    .activity-list {
        max-height: 240px; 
        overflow-y: auto;
        padding: 0;
        margin: 0;
        list-style: none;
        scrollbar-width: none;  
        -ms-overflow-style: none;  
    }

    .activity-list::-webkit-scrollbar {
        display: none; 
    }

    .activity-list li {
        display: flex;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        height: 80px; 
        box-sizing: border-box;
    }

    .activity-list li i {
        font-size: 1.2rem;
        margin-right: 15px;
        color: #3498db;
    }

    .act-details strong {
        display: block;
        font-size: 0.95rem;
    }

    .act-details small {
        color: #888;
        font-size: 0.8rem;
    }

    /* ========== STATS GRID ADJUSTMENT ========== */
    .stat-info h3 {
        font-size: 1.1rem;
        margin-bottom: 2px;
    }
    .stat-info p {
        font-size: 0.8rem;
        color: #666;
        margin: 0;
    }
    
    /* ========== RETIREMENT CARDS ========== */
    .retirement-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin: 15px 0;
    }
    
    .retirement-card {
        background: white;
        padding: 15px;
        border-radius: 10px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        text-align: center;
        transition: transform 0.3s;
        border-top: 4px solid;
    }
    
    .retirement-card:hover {
        transform: translateY(-3px);
    }
    
    .retirement-card.eligible {
        border-color: #10b981;
    }
    
    .retirement-card.approaching {
        border-color: #f59e0b;
    }
    
    .retirement-card.this-year {
        border-color: #3b82f6;
    }
    
    .retirement-card.avg-age {
        border-color: #8b5cf6;
    }
    
    .retirement-value {
        font-size: 1.8em;
        font-weight: bold;
        color: #1f2937;
        margin: 5px 0;
    }
    
    .retirement-label {
        font-size: 0.8em;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* ========== UPCOMING RETIREMENTS ========== */
    .upcoming-list {
        max-height: 300px;
        overflow-y: auto;
        margin-top: 10px;
    }
    
    .upcoming-item {
        display: flex;
        align-items: center;
        padding: 12px;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .upcoming-item:last-child {
        border-bottom: none;
    }
    
    .upcoming-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 14px;
        margin-right: 12px;
    }
    
    .upcoming-details {
        flex: 1;
    }
    
    .upcoming-details strong {
        display: block;
        font-size: 13px;
        color: #1f2937;
    }
    
    .upcoming-details small {
        display: block;
        font-size: 11px;
        color: #6b7280;
        margin-top: 2px;
    }
    
    .upcoming-status {
        font-size: 10px;
        padding: 2px 8px;
        border-radius: 10px;
        font-weight: 600;
        margin-top: 4px;
        display: inline-block;
    }
    
    .status-eligible {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-approaching {
        background: #fef3c7;
        color: #92400e;
    }
    
    /* ========== ANALYTICS BUTTON ========== */
    .analytics-btn {
        display: block;
        width: 100%;
        padding: 12px;
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        color: white;
        text-align: center;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        margin-top: 15px;
        transition: all 0.3s;
    }
    
    .analytics-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(79, 70, 229, 0.3);
    }
</style>

<div class="dashboard-wrapper">
    <div class="welcome-header">
        <div class="welcome-text">
            <h1>Welcome back, <?= htmlspecialchars($displayName) ?></h1>
            <p>You are logged in as <strong><?= htmlspecialchars($displayRole) ?></strong>. Activity reset at 8:00 AM.</p>
        </div>
        <div class="date-time-widget">
            <div class="time" id="real-time">--:--:-- --</div>
            <div class="date" id="real-date">Loading...</div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card clickable" onclick="window.location.href='pending-leave.php'">
            <div class="stat-icon blue"><i class="fa-solid fa-calendar-check"></i></div>
            <div class="stat-info">
                <h3>Pending Leave</h3>
                <p>Review leave requests</p>
            </div>
        </div>

        <div class="stat-card clickable" onclick="window.location.href='manage-employees.php'">
            <div class="stat-icon green"><i class="fa-solid fa-user-gear"></i></div>
            <div class="stat-info">
                <h3>Manage Employees</h3>
                <p>Edit profiles and staff records</p>
            </div>
        </div>

        <div class="stat-card clickable" onclick="window.location.href='retirement_reports.php'" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-left: 4px solid #4f46e5;">
            <div class="stat-icon" style="background: #4f46e5;"><i class="fa-solid fa-chart-line" style="color: white;"></i></div>
            <div class="stat-info">
                <h3>Retirement Analytics</h3>
                <p style="color: #6b7280;">Growth & Benefits Reports</p>
                <small style="display: block; background: #4f46e5; color: white; padding: 2px 8px; border-radius: 10px; margin-top: 5px; font-size: 10px; width: fit-content;">
                    NEW
                </small>
            </div>
        </div>

        <div class="stat-card clickable" onclick="window.location.href='leave-history.php'">
            <div class="stat-icon red"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div class="stat-info">
                <h3>Leave History</h3>
                <p>Records of past leaves</p>
            </div>
        </div>
    </div>

    <div class="main-dashboard-grid">
        <div class="feed-container">
            <div class="content-card">
                <div class="card-header">
                    <h2>HRMS Announcements</h2>
                    <button class="btn-more">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>
                <div class="announcement-item">
                    <div class="ann-date">
                        <span class="month"><?= date('M') ?></span>
                        <span class="day"><?= date('d') ?></span>
                    </div>
                    <div class="ann-text">
                        <h4>System Synchronized</h4>
                        <p>Pulling live data from the <strong>redefence</strong> database. Reset cycle: 8:00 AM daily.</p>
                    </div>
                </div>
                
                <!-- Retirement Analytics Section -->
                <div class="announcement-item" style="background: #f0f9ff; border-left: 4px solid #4f46e5;">
                    <div class="ann-date" style="background: #4f46e5;">
                        <span class="month" style="color: white;">RET</span>
                        <span class="day" style="color: white;">ANA</span>
                    </div>
                    <div class="ann-text">
                        <h4 style="color: #4f46e5;">New: Retirement Analytics</h4>
                        <p>Access comprehensive retirement reports including growth projections, benefits utilization, and workforce demographics.</p>
                        <a href="retirement_reports.php" class="analytics-btn" style="margin-top: 10px; display: inline-block; width: auto; padding: 8px 20px;">
                            <i class="fas fa-chart-line me-2"></i> Launch Analytics
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="side-info-container">
            <!-- Retirement Statistics -->
            <div class="content-card">
                <div class="card-header">
                    <h2><i class="fas fa-retirement" style="color: #4f46e5;"></i> Retirement Overview</h2>
                </div>
                
                <div class="retirement-grid">
                    <div class="retirement-card eligible">
                        <div class="retirement-value"><?= $retirement_stats['eligible_retirees'] ?></div>
                        <div class="retirement-label">Eligible (60+)</div>
                    </div>
                    <div class="retirement-card approaching">
                        <div class="retirement-value"><?= $retirement_stats['approaching_retirees'] ?></div>
                        <div class="retirement-label">Approaching (55-59)</div>
                    </div>
                    <div class="retirement-card this-year">
                        <div class="retirement-value"><?= $retirement_stats['retire_this_year'] ?></div>
                        <div class="retirement-label">Retire This Year</div>
                    </div>
                    <div class="retirement-card avg-age">
                        <div class="retirement-value"><?= $retirement_stats['avg_retirement_age'] ?> yrs</div>
                        <div class="retirement-label">Avg. Age</div>
                    </div>
                </div>
                
                <a href="retirement_reports.php" class="analytics-btn">
                    <i class="fas fa-chart-line me-2"></i> View Detailed Analytics
                </a>
            </div>
            
            <!-- Upcoming Retirements -->
            <?php if (!empty($upcoming_retirements)): ?>
            <div class="content-card">
                <div class="card-header">
                    <h2><i class="fas fa-birthday-cake" style="color: #ef4444;"></i> Upcoming Retirements</h2>
                </div>
                
                <div class="upcoming-list">
                    <?php foreach($upcoming_retirements as $retiree): 
                        $initials = substr($retiree['first_name'], 0, 1) . substr($retiree['last_name'], 0, 1);
                    ?>
                    <div class="upcoming-item">
                        <div class="upcoming-avatar">
                            <?= strtoupper($initials) ?>
                        </div>
                        <div class="upcoming-details">
                            <strong><?= htmlspecialchars($retiree['first_name'] . ' ' . $retiree['last_name']) ?></strong>
                            <small><?= htmlspecialchars($retiree['position']) ?> • <?= htmlspecialchars($retiree['department_name']) ?></small>
                            <small>Retires: <?= htmlspecialchars($retiree['retirement_month']) ?></small>
                            <span class="upcoming-status <?= $retiree['status'] == 'eligible' ? 'status-eligible' : 'status-approaching' ?>">
                                <?= $retiree['status'] == 'eligible' ? 'Eligible Now' : 'Approaching' ?> • Age: <?= $retiree['age'] ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <a href="incoming-retirees.php" style="display: block; text-align: center; padding: 8px; color: #4f46e5; text-decoration: none; font-size: 12px; margin-top: 10px;">
                    View all upcoming retirements <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Recent Logs -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Recent Logs</h2>
                </div>

                <ul class="activity-list">
                    <?php if (!empty($recentLogs)): ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <li>
                                <i class="fas fa-history text-blue"></i>
                                <div class="act-details">
                                    <strong><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></strong> 
                                    active in system
                                    <small><?= date('h:i A', strtotime($log['updated_at'])) ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li style="height: auto; justify-content: center; padding: 30px;">
                            <div class="act-details" style="text-align: center;">No activity since 8:00 AM.</div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    // Real-time clock
    function updateClock() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-US', { 
            hour12: true, 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit' 
        });
        const dateStr = now.toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        document.getElementById('real-time').textContent = timeStr;
        document.getElementById('real-date').textContent = dateStr;
    }
    
    setInterval(updateClock, 1000);
    updateClock();
    
    // Animate retirement cards on hover
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.retirement-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 8px 20px rgba(0,0,0,0.12)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 3px 10px rgba(0,0,0,0.08)';
            });
        });
    });
</script>
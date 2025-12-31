<?php
// print-roster.php
session_start();
require_once '../../config/config.php';

// Check if department ID is provided
if (!isset($_GET['dept_id']) || !isset($_GET['dept_name'])) {
    die("Department information missing.");
}

$dept_id = $_GET['dept_id'];
$dept_name = $_GET['dept_name'];

try {
    // Fetch employees for this department - FIXED: removed e.hire_date
    $stmt = $pdo->prepare("
        SELECT 
            e.employee_id,
            e.first_name,
            e.last_name,
            e.middle_name,
            e.gender,
            e.position,
            e.email,
            e.contact_number,
            e.status,
            e.created_at,
            e.birth_date
        FROM employee e
        WHERE e.department_id = ? AND e.status = 'Active'
        ORDER BY e.last_name, e.first_name
    ");
    $stmt->execute([$dept_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count genders
    $male_count = 0;
    $female_count = 0;
    $other_count = 0;
    foreach ($employees as $emp) {
        if ($emp['gender'] == 'Male') $male_count++;
        if ($emp['gender'] == 'Female') $female_count++;
        if ($emp['gender'] == 'Other') $other_count++;
    }
    
} catch (PDOException $e) {
    die("Error fetching roster: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roster - <?= htmlspecialchars($dept_name) ?></title>
    <style>
        @media print {
            @page {
                size: letter;
                margin: 0.5in;
            }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                color: #000;
                background: white;
                margin: 0;
                padding: 20px;
            }
            .no-print { display: none !important; }
            table { break-inside: avoid; }
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        
        .print-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #3b82f6;
            padding-bottom: 15px;
        }
        
        .print-header h1 {
            color: #1e40af;
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        
        .print-header .subtitle {
            color: #64748b;
            font-size: 14px;
        }
        
        .metadata {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            flex-wrap: wrap;
        }
        
        .meta-item {
            text-align: center;
            margin: 5px 15px;
        }
        
        .meta-label {
            font-size: 12px;
            color: #64748b;
            display: block;
        }
        
        .meta-value {
            font-size: 18px;
            font-weight: bold;
            color: #1e293b;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 12px;
        }
        
        th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 600;
            padding: 12px 8px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 10px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        
        tr:hover {
            background-color: #f8fafc;
        }
        
        .gender-male {
            color: #1d4ed8;
            font-weight: 600;
        }
        
        .gender-female {
            color: #be185d;
            font-weight: 600;
        }
        
        .gender-other {
            color: #8b5cf6;
            font-weight: 600;
        }
        
        .print-footer {
            margin-top: 40px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
        }
        
        .print-date {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 11px;
            color: #64748b;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
            font-style: italic;
        }
        
        .controls {
            margin-bottom: 20px;
            text-align: center;
            padding: 15px;
            background: #f1f5f9;
            border-radius: 8px;
        }
        
        .btn-print {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-print:hover {
            background: #2563eb;
        }
        
        .status-active {
            background: #d1fae5;
            color: #065f46;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .employee-id {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            color: #64748b;
        }
        
        .age {
            font-size: 11px;
            color: #475569;
        }
    </style>
</head>
<body>
    <div class="print-date">
        Printed: <?= date('Y-m-d H:i') ?>
    </div>
    
    <div class="print-header">
        <h1>Department Roster</h1>
        <div class="subtitle"><?= htmlspecialchars($dept_name) ?> • HRMS Bacolod</div>
    </div>
    
    <div class="metadata">
        <div class="meta-item">
            <span class="meta-label">Total Employees</span>
            <span class="meta-value"><?= count($employees) ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Male</span>
            <span class="meta-value"><?= $male_count ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Female</span>
            <span class="meta-value"><?= $female_count ?></span>
        </div>
        <?php if ($other_count > 0): ?>
        <div class="meta-item">
            <span class="meta-label">Other</span>
            <span class="meta-value"><?= $other_count ?></span>
        </div>
        <?php endif; ?>
        <div class="meta-item">
            <span class="meta-label">Date Generated</span>
            <span class="meta-value"><?= date('F d, Y') ?></span>
        </div>
    </div>
    
    <?php if (empty($employees)): ?>
        <div class="no-data">
            <p>No active employees found in this department.</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th width="5%">ID</th>
                    <th width="20%">Employee Name</th>
                    <th width="15%">Position</th>
                    <th width="10%">Gender</th>
                    <th width="10%">Age/Birthdate</th>
                    <th width="15%">Status</th>
                    <th width="15%">Email</th>
                    <th width="10%">Contact</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                foreach ($employees as $index => $employee): 
                    // Calculate age if birthdate exists
                    $age_info = '';
                    if (!empty($employee['birth_date'])) {
                        $birth_date = new DateTime($employee['birth_date']);
                        $today = new DateTime();
                        $age = $today->diff($birth_date)->y;
                        $age_info = $age . ' yrs<br><small>' . date('m/d/Y', strtotime($employee['birth_date'])) . '</small>';
                    } else {
                        $age_info = '<em style="color:#94a3b8;">N/A</em>';
                    }
                ?>
                <tr>
                    <td class="employee-id"><?= $employee['employee_id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($employee['last_name'] . ', ' . $employee['first_name']) ?></strong>
                        <?php if (!empty($employee['middle_name'])): ?>
                            <br><small><?= htmlspecialchars($employee['middle_name']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= !empty($employee['position']) ? htmlspecialchars($employee['position']) : '<em style="color:#94a3b8;">Not specified</em>' ?></td>
                    <td>
                        <span class="gender-<?= strtolower($employee['gender']) ?>">
                            <?= $employee['gender'] ?>
                        </span>
                    </td>
                    <td class="age"><?= $age_info ?></td>
                    <td>
                        <span class="status-<?= strtolower($employee['status'] ?? 'active') ?>">
                            <?= $employee['status'] ?? 'Active' ?>
                        </span>
                    </td>
                    <td><?= !empty($employee['email']) ? htmlspecialchars($employee['email']) : '<em style="color:#94a3b8;">No email</em>' ?></td>
                    <td><?= !empty($employee['contact_number']) ? htmlspecialchars($employee['contact_number']) : '<em style="color:#94a3b8;">No contact</em>' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="metadata" style="margin-top: 30px; background: #f0f9ff;">
            <div class="meta-item">
                <span class="meta-label">Gender Summary</span>
                <div style="margin-top: 5px; font-size: 13px;">
                    <span style="color: #1d4ed8;">■ Male: <?= $male_count ?> (<?= count($employees) > 0 ? round(($male_count/count($employees))*100, 1) : 0 ?>%)</span> |
                    <span style="color: #be185d;">■ Female: <?= $female_count ?> (<?= count($employees) > 0 ? round(($female_count/count($employees))*100, 1) : 0 ?>%)</span>
                    <?php if ($other_count > 0): ?>
                        | <span style="color: #8b5cf6;">■ Other: <?= $other_count ?> (<?= count($employees) > 0 ? round(($other_count/count($employees))*100, 1) : 0 ?>%)</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="meta-item">
                <span class="meta-label">System Record</span>
                <div style="margin-top: 5px; font-size: 11px;">
                    First record: <?= !empty($employees) ? date('Y-m-d', strtotime(min(array_column($employees, 'created_at')))) : 'N/A' ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="print-footer">
        <p>Confidential • HR Management System Bacolod • <?= date('Y') ?> • Page 1 of 1</p>
        <p>Generated from HRMS • Department ID: <?= htmlspecialchars($dept_id) ?> • Total Records: <?= count($employees) ?></p>
    </div>
    
    <div class="controls no-print">
        <button class="btn-print" onclick="window.print()">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M5 1a2 2 0 0 0-2 2v1h10V3a2 2 0 0 0-2-2H5zm6 8H5a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1z"/>
                <path d="M0 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-1v-2a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v2H2a2 2 0 0 1-2-2V7zm2.5 1a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
            </svg>
            Print Roster
        </button>
        <button onclick="window.close()" style="background: #64748b; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; margin-left: 10px;">
            Close Window
        </button>
    </div>
    
    <script>
        // Auto-print when page loads
        window.onload = function() {
            // Optional: Uncomment to auto-print
            // setTimeout(() => { window.print(); }, 1000);
        };
    </script>
</body>
</html>
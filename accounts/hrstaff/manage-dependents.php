<?php
session_start();
require_once '../../config/config.php';

// ✅ Standardized Access Control
$allowed_roles = ['Admin', 'HR Officer', 'HR Staff'];
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role_name'], $allowed_roles)) {
    header("Location: ../../login.html");
    exit();
}

// ✅ Heartbeat Update
try {
    $pdo->prepare("UPDATE employee SET last_active = NOW() WHERE employee_id = ?")
        ->execute([$_SESSION['employee_id']]);
} catch (PDOException $e) { /* silent fail */ }

$default_profile_image = '../../assets/images/default_user.png';
$notification = $_SESSION['notification'] ?? null;
unset($_SESSION['notification']);

// --- POST HANDLERS (CRUD) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_dependent'])) {
            $stmt = $pdo->prepare("INSERT INTO employee_dependents (employee_id, dependent_name, relationship, birth_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['employee_id'], $_POST['dependent_name'], $_POST['relationship'], $_POST['birth_date']]);
            $_SESSION['notification'] = "Record added successfully!";
        }
        else if (isset($_POST['edit_dependent'])) {
            $stmt = $pdo->prepare("UPDATE employee_dependents SET dependent_name = ?, relationship = ?, birth_date = ? WHERE dependent_id = ?");
            $stmt->execute([$_POST['dependent_name'], $_POST['relationship'], $_POST['birth_date'], $_POST['dependent_id']]);
            $_SESSION['notification'] = "Record updated successfully!";
        }
        else if (isset($_POST['delete_dependent'])) {
            $stmt = $pdo->prepare("DELETE FROM employee_dependents WHERE dependent_id = ?");
            $stmt->execute([$_POST['dependent_id']]);
            $_SESSION['notification'] = "Record deleted successfully!";
        }
        header("Location: manage-dependents.php"); 
        exit();
    } catch (PDOException $e) {
        error_log("CRUD Error: " . $e->getMessage());
        $_SESSION['notification'] = "Error: Could not process request.";
        header("Location: manage-dependents.php"); 
        exit();
    }
}

// --- FILTERS & PAGINATION ---
$dept_filter = $_GET['dept'] ?? '';
$search_query = $_GET['search'] ?? '';
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

try {
    $employees_list = $pdo->query("SELECT employee_id, first_name, last_name FROM employee ORDER BY last_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $departments_list = $pdo->query("SELECT * FROM department ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // ✅ FIX 3: Added LEFT JOIN for department to get department_name
    $base_sql = "FROM employee_dependents d 
                 JOIN employee e ON d.employee_id = e.employee_id 
                 LEFT JOIN department dept ON e.department_id = dept.department_id
                 WHERE 1=1";
    $params = [];

    if (!empty($dept_filter)) { 
        $base_sql .= " AND e.department_id = ?"; 
        $params[] = $dept_filter; 
    }
    
    if (!empty($search_query)) {
        $base_sql .= " AND (d.dependent_name LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)";
        $search_val = "%$search_query%";
        $params = array_merge($params, [$search_val, $search_val, $search_val]);
    }

    $count_stmt = $pdo->prepare("SELECT COUNT(*) $base_sql");
    $count_stmt->execute($params);
    $total_rows = $count_stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // ✅ FIX 4: Selected dept.department_name as 'dept_name' to satisfy line 199
    $sql = "SELECT d.*, e.first_name, e.last_name, e.profile_pic, dept.department_name as dept_name 
            $base_sql 
            ORDER BY d.created_at DESC LIMIT $limit OFFSET $offset";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dependents = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Fetch Error: " . $e->getMessage());
    $dependents = [];
    $total_pages = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Dependents - HRMS</title>

    <link rel="icon" type="image/x-icon" href="/HRIS/assets/images/HRMS.png">

    <link rel="stylesheet" href="/HRIS/assets/css/style.css"> 
    <link rel="stylesheet" href="/HRIS/assets/css/ledger-table.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .dashboard-wrapper { gap: 15px !important; }
        .content-card { margin-top: 0 !important; }
        .emp-info-cell { display: flex; align-items: center; gap: 10px; }

        .emp-profile-img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }
        .rel-tag { padding: 4px 10px; background: #f1f5f9; color: #475569; border-radius: 4px; font-size: 11px; font-weight: 700; }

        /* --- FULLSCREEN BLUR MODAL SYSTEM --- */
        .modal { 
            display: none; position: fixed; z-index: 9999; 
            left: 0; top: 0; width: 100%; height: 100%; 
            background-color: rgba(15, 23, 42, 0.5); 
            backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
        }
        .modal-content { 
            background: #fff; margin: 8% auto; padding: 25px; 
            border-radius: 16px; width: 450px; position: relative;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: modalPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes modalPop { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; }
        .modal-footer { display: flex; gap: 10px; margin-top: 20px; }

        /* Success Modal Specific */
        .success-icon { width: 60px; height: 60px; background: #dcfce7; color: #22c55e; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 30px; margin: 0 auto 15px; }
    </style>
</head>

<body>
    <div class="wrapper">
        <div id="sidebar-placeholder"></div>
        <main class="main-content">
            <div id="topbar-placeholder"></div>
            <div class="dashboard-wrapper">
                <div class="welcome-header">
                    <div class="welcome-text">
                        <h1 style="font-size: 22px;">Employee Dependents</h1>
                        <p style="color: #64748b; font-size: 14px;">Manage family members and beneficiaries records.</p>
                    </div>
                    <button class="btn-primary" onclick="openModal('addModal')" style="background: #1d4ed8; color: white; border:none; padding: 10px 18px; border-radius: 8px; cursor:pointer;">
                        <i class="fa-solid fa-plus"></i> Add Dependent
                    </button>
                </div>

                <div class="content-card">
                    <div class="table-container">
                        <table class="accounts-table">
                            <thead>
                                <tr>
                                    <th>Dependent Name</th>
                                    <th>Relationship</th>
                                    <th>Date of Birth</th>
                                    <th>Employee (Provider)</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dependents as $dep): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($dep['dependent_name']) ?></strong></td>
                                    <td><span class="rel-tag"><?= strtoupper($dep['relationship']) ?></span></td>
                                    <td><?= date('M d, Y', strtotime($dep['birth_date'])) ?></td>
                                    <td>
                                        <div class="emp-info-cell">
                                            <img src="<?= $dep['profile_pic'] ?: $default_profile_image ?>" class="emp-profile-img">
                                            <div>
                                                <span style="font-weight:600;"><?= $dep['last_name'].', '.$dep['first_name'] ?></span><br>
                                                <small><?= $dep['dept_name'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <button class="icon-btn" onclick="openEditModal(<?= htmlspecialchars(json_encode($dep)) ?>)"><i class="fa-solid fa-pen-to-square"></i></button>
                                        <button class="icon-btn" style="color:#ef4444;" onclick="openDeleteModal(<?= $dep['dependent_id'] ?>, '<?= htmlspecialchars($dep['dependent_name']) ?>')"><i class="fa-solid fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
        <div id="rightbar-placeholder"></div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Add New Dependent</h2><i class="fa-solid fa-xmark" onclick="closeModal('addModal')" style="cursor:pointer"></i></div>
            <form method="POST">
                <div class="form-group"><label>Linked Employee</label>
                    <select name="employee_id" required>
                        <?php foreach ($employees_list as $emp): ?><option value="<?= $emp['employee_id'] ?>"><?= $emp['last_name'].', '.$emp['first_name'] ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Dependent Full Name</label><input type="text" name="dependent_name" required></div>
                <div class="form-group"><label>Relationship</label>
                    <select name="relationship"><option>Spouse</option><option>Son</option><option>Daughter</option><option>Parent</option><option>Sibling</option></select>
                </div>
                <div class="form-group"><label>Birth Date</label><input type="date" name="birth_date" required></div>
                <div class="modal-footer">
                    <button type="submit" name="add_dependent" class="btn-primary" style="flex:1; background:#1d4ed8; color:white; border:none; padding:12px; border-radius:8px;">Save Record</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Edit Dependent</h2><i class="fa-solid fa-xmark" onclick="closeModal('editModal')" style="cursor:pointer"></i></div>
            <form method="POST">
                <input type="hidden" name="dependent_id" id="edit_id">
                <div class="form-group"><label>Dependent Full Name</label><input type="text" name="dependent_name" id="edit_name" required></div>
                <div class="form-group"><label>Relationship</label>
                    <select name="relationship" id="edit_rel"><option>Spouse</option><option>Son</option><option>Daughter</option><option>Parent</option><option>Sibling</option></select>
                </div>
                <div class="form-group"><label>Birth Date</label><input type="date" name="birth_date" id="edit_dob" required></div>
                <div class="modal-footer">
                    <button type="submit" name="edit_dependent" class="btn-primary" style="flex:1; background:#1d4ed8; color:white; border:none; padding:12px; border-radius:8px;">Update Record</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="modal">
        <div class="modal-content" style="text-align:center;">
            <div style="color:#ef4444; font-size:50px; margin-bottom:15px;"><i class="fa-solid fa-circle-exclamation"></i></div>
            <h2>Are you sure?</h2>
            <p style="color:#64748b; margin-bottom:20px;">You are about to delete <b id="del_name"></b>. This action cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="dependent_id" id="del_id">
                <div class="modal-footer">
                    <button type="submit" name="delete_dependent" style="flex:1; background:#ef4444; color:white; border:none; padding:12px; border-radius:8px; cursor:pointer;">Delete Now</button>
                    <button type="button" onclick="closeModal('deleteModal')" style="flex:1; background:#f1f5f9; border:none; border-radius:8px; cursor:pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div id="successModal" class="modal" style="<?= $notification ? 'display:block' : '' ?>">
        <div class="modal-content" style="text-align:center; width: 350px;">
            <div class="success-icon"><i class="fa-solid fa-check"></i></div>
            <h2 style="margin-bottom:10px;">Success!</h2>
            <p style="color:#64748b; margin-bottom:20px;"><?= $notification ?></p>
            <button onclick="closeModal('successModal')" style="width:100%; background:#1d4ed8; color:white; border:none; padding:12px; border-radius:8px; cursor:pointer;">Continue</button>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        function openEditModal(data) {
            document.getElementById('edit_id').value = data.dependent_id;
            document.getElementById('edit_name').value = data.dependent_name;
            document.getElementById('edit_rel').value = data.relationship;
            document.getElementById('edit_dob').value = data.birth_date;
            openModal('editModal');
        }

        function openDeleteModal(id, name) {
            document.getElementById('del_id').value = id;
            document.getElementById('del_name').innerText = name;
            openModal('deleteModal');
        }

        window.onclick = function(e) { if(e.target.classList.contains('modal')) e.target.style.display = 'none'; }
    </script>
    <script src="/HRIS/assets/js/script.js"></script>
    </body>
</html>
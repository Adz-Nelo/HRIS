<?php
require_once __DIR__ . '/../config/config.php';

// Fetch departments from database
try {
    $stmt = $pdo->query("SELECT department_id, department_name FROM department ORDER BY department_name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $departments = [];
}

// Fetch detailed departments from database
try {
    $stmt = $pdo->query("SELECT detailed_department_id, detailed_department_name FROM detailed_department ORDER BY detailed_department_name");
    $detailed_departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $detailed_departments = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $employee_id = $_POST['employee_id'];
        $first_name = $_POST['first_name'];
        $middle_name = $_POST['middle_name'] ?? null;
        $last_name = $_POST['last_name'];
        $extension = $_POST['extension'] ?? null;
        $birth_date = $_POST['birth_date'] ?? null;
        $gender = $_POST['gender'];
        $department_id = $_POST['department_id'];
        $detailed_department_id = $_POST['detailed_department_id'] ?? null;
        $position = $_POST['position'] ?? null;
        $role = $_POST['role'];
        $salary = $_POST['salary'] ?? null;
        $email = $_POST['email'] ?? null;
        $contact_number = $_POST['contact_number'] ?? null;
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Insert into database
        $sql = "INSERT INTO employee (
            employee_id, first_name, middle_name, last_name, extension, 
            birth_date, gender, department_id, detailed_department_id, 
            position, role, salary, email, contact_number, password
        ) VALUES (
            :employee_id, :first_name, :middle_name, :last_name, :extension,
            :birth_date, :gender, :department_id, :detailed_department_id,
            :position, :role, :salary, :email, :contact_number, :password
        )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employee_id,
            ':first_name' => $first_name,
            ':middle_name' => $middle_name,
            ':last_name' => $last_name,
            ':extension' => $extension,
            ':birth_date' => $birth_date,
            ':gender' => $gender,
            ':department_id' => $department_id,
            ':detailed_department_id' => $detailed_department_id,
            ':position' => $position,
            ':role' => $role,
            ':salary' => $salary,
            ':email' => $email,
            ':contact_number' => $contact_number,
            ':password' => $password
        ]);
        
        $success_message = "Employee registered successfully!";
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Registration</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
        }
        
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        .required {
            color: red;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="number"],
        input[type="date"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Employee Registration</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-grid">
                <div class="form-group">
                    <label>Employee ID <span class="required">*</span></label>
                    <input type="number" name="employee_id" required>
                </div>
                
                <div class="form-group">
                    <label>Gender <span class="required">*</span></label>
                    <select name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" name="first_name" required>
                </div>
                
                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name">
                </div>
                
                <div class="form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" required>
                </div>
                
                <div class="form-group">
                    <label>Extension (Jr., Sr., etc.)</label>
                    <input type="text" name="extension">
                </div>
                
                <div class="form-group">
                    <label>Birth Date</label>
                    <input type="date" name="birth_date">
                </div>
                
                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact_number">
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email">
                </div>
                
                <div class="form-group">
                    <label>Salary</label>
                    <input type="number" name="salary">
                </div>
                
                <!-- Independent Role Selection -->
                <div class="form-group">
                    <label>Role <span class="required">*</span></label>
                    <select name="role" required>
                        <option value="">Select Role</option>
                        <option value="Regular Employee">Regular Employee</option>
                        <option value="HR Staff">HR Staff</option>
                        <option value="HR Officer">HR Officer</option>
                        <option value="Department Head">Department Head</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                
                <!-- Independent Department Selection -->
                <div class="form-group">
                    <label>Department <span class="required">*</span></label>
                    <select name="department_id" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept['department_id']); ?>">
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Independent Detailed Department Selection -->
                <div class="form-group">
                    <label>Detailed Department (Optional)</label>
                    <select name="detailed_department_id">
                        <option value="">Select Detailed Department</option>
                        <?php foreach ($detailed_departments as $detailed): ?>
                            <option value="<?php echo htmlspecialchars($detailed['detailed_department_id']); ?>">
                                <?php echo htmlspecialchars($detailed['detailed_department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" name="position" placeholder="e.g., Software Engineer">
                </div>
                
                <div class="form-group full-width">
                    <label>Password <span class="required">*</span></label>
                    <input type="password" name="password" required>
                </div>
            </div>
            
            <button type="submit" class="btn">Register Employee</button>
        </form>
    </div>
</body>
</html>
<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$user = $_SESSION;
$message = '';
$error = '';

// Check for export error
if (isset($_SESSION['export_error'])) {
    $error = $_SESSION['export_error'];
    unset($_SESSION['export_error']);
}

// Handle export functionality FIRST (before any HTML output)
if (isset($_GET['export'])) {
    try {
        require_once '../includes/config.php';
        $pdo = getDBConnection();
        
        $stmt = $pdo->query("
            SELECT s.student_number, u.username, u.full_name, u.email, 
                   s.specialization, s.year_of_study, u.created_at
            FROM users u 
            LEFT JOIN students s ON u.id = s.user_id 
            WHERE u.role = 'student' 
            ORDER BY u.created_at DESC
        ");
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if there are students to export
        if (empty($students)) {
            $_SESSION['export_error'] = 'No students found to export.';
            header("Location: student_management.php");
            exit();
        }
        
        if ($_GET['export'] == 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="students_' . date('Y-m-d') . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Student Number', 'Username', 'Full Name', 'Email', 'Specialization', 'Year', 'Created']);
            
            foreach ($students as $student) {
                fputcsv($output, [
                    $student['student_number'] ?? 'N/A',
                    $student['username'],
                    $student['full_name'],
                    $student['email'],
                    $student['specialization'] ?? 'N/A',
                    $student['year_of_study'] ? 'L' . $student['year_of_study'] : 'N/A',
                    $student['created_at']
                ]);
            }
            
            fclose($output);
            exit();
        }
        
        if ($_GET['export'] == 'excel') {
            // For now, export as CSV with .xls extension for Excel compatibility
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="students_' . date('Y-m-d') . '.xls"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            echo "<table border='1'>\n";
            echo "<tr><th>Student Number</th><th>Username</th><th>Full Name</th><th>Email</th><th>Specialization</th><th>Year</th><th>Created</th></tr>\n";
            
            foreach ($students as $student) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($student['student_number'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($student['username']) . "</td>";
                echo "<td>" . htmlspecialchars($student['full_name']) . "</td>";
                echo "<td>" . htmlspecialchars($student['email']) . "</td>";
                echo "<td>" . htmlspecialchars($student['specialization'] ?? 'N/A') . "</td>";
                echo "<td>" . ($student['year_of_study'] ? 'L' . $student['year_of_study'] : 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($student['created_at']) . "</td>";
                echo "</tr>\n";
            }
            
            echo "</table>";
            exit();
        }
        
    } catch (Exception $e) {
        error_log("Export error: " . $e->getMessage());
        $_SESSION['export_error'] = 'Export failed: ' . $e->getMessage();
        header("Location: student_management.php");
        exit();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once '../includes/config.php';
    $pdo = getDBConnection();
    
    // Handle student addition
    if (isset($_POST['action']) && $_POST['action'] == 'add_student') {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert into users table
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email, full_name) VALUES (?, ?, 'student', ?, ?)");
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt->execute([
                $_POST['username'],
                $password_hash,
                $_POST['email'],
                $_POST['full_name']
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            // Insert into students table
            $stmt = $pdo->prepare("INSERT INTO students (user_id, student_number, specialization, year_of_study) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $user_id,
                $_POST['student_number'],
                $_POST['specialization'],
                $_POST['year_of_study']
            ]);
            
            $pdo->commit();
            $message = 'Student added successfully!';
            
        } catch (PDOException $e) {
            $pdo->rollback();
            $error = 'Error adding student: ' . $e->getMessage();
        }
    }
    
    // Handle student editing
    if (isset($_POST['action']) && $_POST['action'] == 'edit_student') {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update users table
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ? WHERE id = ? AND role = 'student'");
            $stmt->execute([
                $_POST['edit_username'],
                $_POST['edit_email'], 
                $_POST['edit_full_name'],
                $_POST['edit_student_id']
            ]);
            
            // Update password if provided
            if (!empty($_POST['edit_password'])) {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'student'");
                $password_hash = password_hash($_POST['edit_password'], PASSWORD_DEFAULT);
                $stmt->execute([$password_hash, $_POST['edit_student_id']]);
            }
            
            // Update students table
            $stmt = $pdo->prepare("UPDATE students SET student_number = ?, specialization = ?, year_of_study = ? WHERE user_id = ?");
            $stmt->execute([
                $_POST['edit_student_number'],
                $_POST['edit_specialization'],
                $_POST['edit_year_of_study'],
                $_POST['edit_student_id']
            ]);
            
            $pdo->commit();
            $message = 'Student updated successfully!';
            
        } catch (PDOException $e) {
            $pdo->rollback();
            $error = 'Error updating student: ' . $e->getMessage();
        }
    }

    // Handle student deletion
    if (isset($_POST['action']) && $_POST['action'] == 'delete_student') {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
            $stmt->execute([$_POST['student_id']]);
            $message = 'Student deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting student: ' . $e->getMessage();
        }
    }
    
    // Handle Excel import
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_path = $upload_dir . basename($_FILES['excel_file']['name']);
        
        if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $file_path)) {
            // Process CSV file (simplified - would need proper Excel library for .xlsx)
            if (pathinfo($file_path, PATHINFO_EXTENSION) == 'csv') {
                try {
                    $pdo->beginTransaction();
                    $imported = 0;
                    
                    if (($handle = fopen($file_path, "r")) !== FALSE) {
                        // Skip header row
                        fgetcsv($handle, 1000, ",");
                        
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            if (count($data) >= 4) {
                                // Insert user
                                $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, role, email, full_name) VALUES (?, ?, 'student', ?, ?)");
                                $password_hash = password_hash('student123', PASSWORD_DEFAULT);
                                $stmt->execute([
                                    $data[1], // username
                                    $password_hash,
                                    $data[3], // email
                                    $data[2] // full_name
                                ]);
                                
                                $user_id = $pdo->lastInsertId();
                                if ($user_id) {
                                    // Insert student
                                    $stmt = $pdo->prepare("INSERT INTO students (user_id, student_number, specialization, year_of_study) VALUES (?, ?, ?, ?)");
                                    $stmt->execute([
                                        $user_id,
                                        $data[0], // student_number
                                        $data[4] ?? 'Computer Science', // specialization
                                        3 // year_of_study
                                    ]);
                                    $imported++;
                                }
                            }
                        }
                        fclose($handle);
                    }
                    
                    $pdo->commit();
                    $message = "Successfully imported $imported students from CSV file.";
                    
                } catch (PDOException $e) {
                    $pdo->rollback();
                    $error = 'Error importing CSV: ' . $e->getMessage();
                }
            } else {
                $error = 'Please upload a CSV file. Excel (.xlsx) support requires additional libraries.';
            }
            
            unlink($file_path); // Clean up uploaded file
        } else {
            $error = 'Error uploading file.';
        }
    }
}

// Get student list
try {
    require_once '../includes/config.php';
    $pdo = getDBConnection();
    
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.email, u.full_name, 
               s.student_number, s.specialization, s.year_of_study,
               u.created_at
        FROM users u 
        LEFT JOIN students s ON u.id = s.user_id 
        WHERE u.role = 'student' 
        ORDER BY u.created_at DESC
    ");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
    $total_students = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT specialization) as count FROM students WHERE specialization IS NOT NULL");
    $total_specializations = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Student management error: " . $e->getMessage());
    $students = [];
    $total_students = 0;
    $total_specializations = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management - Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            transition: background 0.3s;
        }
        
        .nav-link:hover,
        .nav-link.active {
            background: rgba(255,255,255,0.2);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .dropdown {
            position: relative;
        }
        
        .dropdown-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            cursor: pointer;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            min-width: 200px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-radius: 8px;
            overflow: hidden;
            z-index: 1000;
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        .dropdown-item {
            display: block;
            padding: 12px 16px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .dropdown-item:hover {
            background: #f8f9fa;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 0.5rem;
            font-weight: 300;
        }
        
        .breadcrumb {
            color: #666;
            font-size: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 5px solid #3498db;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #3498db;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-weight: 500;
        }
        
        .management-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }
        
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }
        
        .card-title {
            font-size: 1.25rem;
            color: #333;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .btn-success {
            background: #2ecc71;
            color: white;
        }
        
        .btn-success:hover {
            background: #27ae60;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #333;
        }
        
        .search-box {
            width: 300px;
            padding: 0.5rem;
            border: 1px solid #e9ecef;
            border-radius: 6px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .file-upload {
            border: 2px dashed #e9ecef;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .file-upload:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .file-upload-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 1rem;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        @media (max-width: 768px) {
            .management-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 0 1rem;
            }
            
            .nav-links {
                display: none;
            }
            
            .search-box {
                width: 200px;
            }
        }
        
        /* Modal Styles */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: none;
            border-radius: 10px;
            width: 80%;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        
        .close:hover,
        .close:focus {
            color: #000;
            text-decoration: none;
        }
        
        .modal h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo">
                🎓 AUAS Admin
            </div>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link">🏠 Home</a>
                <a href="statistics.php" class="nav-link">📊 Statistics</a>
                <a href="student_management.php" class="nav-link active">👥 Students</a>
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($user['username']); ?></span>
                <div class="dropdown">
                    <button class="dropdown-btn">
                        ⚙️ Admin Menu ▼
                    </button>
                    <div class="dropdown-content">
                        <a href="#" class="dropdown-item">👤 Profile</a>
                        <a href="#" class="dropdown-item">🔧 System Settings</a>
                        <a href="../wamp_status.php" class="dropdown-item">📊 System Status</a>
                        <a href="../auth/logout.php" class="dropdown-item">🚪 Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>👥 Student Management</h1>
            <div class="breadcrumb">
                Home / Admin / Student Management
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['export_error'])): ?>
            <div class="alert alert-warning">
                ⚠️ <strong>Export Error:</strong> <?php echo htmlspecialchars($_SESSION['export_error']); ?>
            </div>
            <?php unset($_SESSION['export_error']); ?>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_students; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_specializations; ?></div>
                <div class="stat-label">Specializations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo date('Y'); ?></div>
                <div class="stat-label">Academic Year</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($students); ?></div>
                <div class="stat-label">Records Loaded</div>
            </div>
        </div>

        <div class="management-grid">
            <div class="sidebar">
                <!-- Add Student Form -->
                <div class="card">
                    <div class="card-title">➕ Add New Student</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_student">
                        
                        <div class="form-group">
                            <label for="student_number">Student Number</label>
                            <input type="text" id="student_number" name="student_number" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required value="student123">
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="specialization">Specialization</label>
                            <select id="specialization" name="specialization" class="form-control" required>
                                <option value="">Select Specialization</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Information Systems">Information Systems</option>
                                <option value="Software Engineering">Software Engineering</option>
                                <option value="Data Science">Data Science</option>
                                <option value="Cybersecurity">Cybersecurity</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="year_of_study">Year of Study</label>
                            <select id="year_of_study" name="year_of_study" class="form-control" required>
                                <option value="">Select Year</option>
                                <option value="1">1st Year (L1)</option>
                                <option value="2">2nd Year (L2)</option>
                                <option value="3">3rd Year (L3)</option>
                                <option value="4">4th Year (M1)</option>
                                <option value="5">5th Year (M2)</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            ➕ Add Student
                        </button>
                    </form>
                </div>

                <!-- Edit Student Modal -->
                <div id="editStudentModal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <span class="close" onclick="closeEditModal()">&times;</span>
                        <h3>Edit Student</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="edit_student">
                            <input type="hidden" name="edit_student_id" id="edit_student_id">
                            
                            <div class="form-group">
                                <label>Student Number</label>
                                <input type="text" name="edit_student_number" id="edit_student_number" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" name="edit_username" id="edit_username" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="edit_full_name" id="edit_full_name" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="edit_email" id="edit_email" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label>New Password (leave blank to keep current)</label>
                                <input type="password" name="edit_password" id="edit_password" class="form-control" placeholder="Enter new password or leave blank">
                            </div>
                            
                            <div class="form-group">
                                <label>Specialization</label>
                                <select name="edit_specialization" id="edit_specialization" class="form-control">
                                    <option value="">Select specialization</option>
                                    <option value="Computer Science">Computer Science</option>
                                    <option value="Information Systems">Information Systems</option>
                                    <option value="Software Engineering">Software Engineering</option>
                                    <option value="Data Science">Data Science</option>
                                    <option value="Cybersecurity">Cybersecurity</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Year of Study</label>
                                <select name="edit_year_of_study" id="edit_year_of_study" class="form-control">
                                    <option value="">Select year</option>
                                    <option value="1">1st Year (L1)</option>
                                    <option value="2">2nd Year (L2)</option>
                                    <option value="3">3rd Year (L3)</option>
                                    <option value="4">4th Year (M1)</option>
                                    <option value="5">5th Year (M2)</option>
                                </select>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                                <button type="submit" class="btn btn-primary">Update Student</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Import/Export Section -->
                <div class="card">
                    <div class="card-title">📊 Import/Export</div>
                    
                    <!-- Import Section -->
                    <form method="POST" enctype="multipart/form-data" style="margin-bottom: 1rem;">
                        <div class="file-upload" onclick="document.getElementById('excel_file').click()">
                            <div class="file-upload-icon">📁</div>
                            <div>Click to select CSV file</div>
                            <small style="color: #666;">Progres Excel format (CSV)</small>
                        </div>
                        <input type="file" id="excel_file" name="excel_file" accept=".csv,.xlsx,.xls" style="display: none;" onchange="this.form.submit()">
                    </form>
                    
                    <!-- Export Buttons -->
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="?export=csv" class="btn btn-success btn-sm" style="flex: 1;" onclick="showExportFeedback('CSV')">
                            📥 Export CSV
                        </a>
                        <a href="?export=excel" class="btn btn-secondary btn-sm" style="flex: 1;" onclick="showExportFeedback('Excel')">
                            📊 Export Excel
                        </a>
                    </div>
                    
                    <div style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 6px; font-size: 0.875rem;">
                        <strong>CSV Format:</strong><br>
                        student_number, username, full_name, email, specialization
                    </div>
                </div>
            </div>

            <!-- Student List -->
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">👥 Student List (<?php echo count($students); ?>)</div>
                    <input type="text" class="search-box" placeholder="🔍 Search students..." id="searchBox">
                </div>
                
                <div style="overflow-x: auto;">
                    <table class="table" id="studentsTable">
                        <thead>
                            <tr>
                                <th>Student #</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Specialization</th>
                                <th>Year</th>
                                <th>Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['student_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['username']); ?></td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($student['specialization'] ?? 'N/A'); ?></td>
                                <td><?php echo $student['year_of_study'] ? 'L' . $student['year_of_study'] : 'N/A'; ?></td>
                                <td><?php echo date('M j, Y', strtotime($student['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <button class="btn btn-primary btn-sm" onclick="editStudent(<?php echo $student['id']; ?>)">
                                            ✏️ Edit
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this student?')">
                                            <input type="hidden" name="action" value="delete_student">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                🗑️ Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (empty($students)): ?>
                <div style="text-align: center; padding: 3rem; color: #666;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">👥</div>
                    <div style="font-size: 1.2rem; margin-bottom: 0.5rem;">No students found</div>
                    <div>Add students using the form or import from CSV</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchBox').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('studentsTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                let row = rows[i];
                let text = row.textContent.toLowerCase();
                
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
        
        // Edit student function
        function editStudent(studentId) {
            // Fetch student data via AJAX
            fetch('get_student_data.php?id=' + studentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate the edit form with existing data
                        document.getElementById('edit_student_id').value = data.student.id;
                        document.getElementById('edit_student_number').value = data.student.student_number || '';
                        document.getElementById('edit_username').value = data.student.username;
                        document.getElementById('edit_full_name').value = data.student.full_name;
                        document.getElementById('edit_email').value = data.student.email;
                        document.getElementById('edit_specialization').value = data.student.specialization || '';
                        document.getElementById('edit_year_of_study').value = data.student.year_of_study || '';
                        
                        // Clear password field
                        document.getElementById('edit_password').value = '';
                        
                        // Show the modal
                        document.getElementById('editStudentModal').style.display = 'block';
                    } else {
                        alert('Error loading student data: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading student data');
                });
        }
        
        function closeEditModal() {
            document.getElementById('editStudentModal').style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            var modal = document.getElementById('editStudentModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        // File upload feedback
        document.getElementById('excel_file').addEventListener('change', function() {
            if (this.files.length > 0) {
                document.querySelector('.file-upload').innerHTML = 
                    '<div class="file-upload-icon">📁</div>' +
                    '<div>Selected: ' + this.files[0].name + '</div>' +
                    '<small style="color: #666;">File will be uploaded...</small>';
            }
        });
        
        // Export feedback
        function showExportFeedback(format) {
            // Create a temporary notification
            const notification = document.createElement('div');
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.background = '#2ecc71';
            notification.style.color = 'white';
            notification.style.padding = '1rem';
            notification.style.borderRadius = '8px';
            notification.style.zIndex = '9999';
            notification.style.boxShadow = '0 4px 20px rgba(0,0,0,0.2)';
            notification.innerHTML = `📥 Preparing ${format} export...`;
            
            document.body.appendChild(notification);
            
            // Remove notification after 3 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 3000);
        }
    </script>
</body>
</html>
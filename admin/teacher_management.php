<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/unauthorized.php");
    exit();
}

$message = '';
$error = '';

// Handle export functionality FIRST (before any HTML output)
if (isset($_GET['export'])) {
    try {
        require_once '../includes/config.php';
        $pdo = getDBConnection();
        
        $stmt = $pdo->query("
            SELECT t.teacher_id, u.username, u.full_name, u.email, 
                   t.department, t.position, t.specialization, u.created_at
            FROM users u 
            LEFT JOIN teachers t ON u.id = t.user_id 
            WHERE u.role = 'teacher' 
            ORDER BY u.created_at DESC
        ");
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if there are teachers to export
        if (empty($teachers)) {
            $_SESSION['export_error'] = 'No teachers found to export.';
            header("Location: teacher_management.php");
            exit();
        }
        
        if ($_GET['export'] == 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="teachers_' . date('Y-m-d') . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Teacher ID', 'Username', 'Full Name', 'Email', 'Department', 'Position', 'Specialization', 'Created']);
            
            foreach ($teachers as $teacher) {
                fputcsv($output, [
                    $teacher['teacher_id'] ?? 'N/A',
                    $teacher['username'],
                    $teacher['full_name'],
                    $teacher['email'],
                    $teacher['department'] ?? 'N/A',
                    $teacher['position'] ?? 'N/A',
                    $teacher['specialization'] ?? 'N/A',
                    $teacher['created_at']
                ]);
            }
            
            fclose($output);
            exit();
        }
        
        if ($_GET['export'] == 'excel') {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="teachers_' . date('Y-m-d') . '.xls"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            echo "<table border='1'>\n";
            echo "<tr><th>Teacher ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Department</th><th>Position</th><th>Specialization</th><th>Created</th></tr>\n";
            
            foreach ($teachers as $teacher) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($teacher['teacher_id'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($teacher['username']) . "</td>";
                echo "<td>" . htmlspecialchars($teacher['full_name']) . "</td>";
                echo "<td>" . htmlspecialchars($teacher['email']) . "</td>";
                echo "<td>" . htmlspecialchars($teacher['department'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($teacher['position'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($teacher['specialization'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($teacher['created_at']) . "</td>";
                echo "</tr>\n";
            }
            
            echo "</table>";
            exit();
        }
        
    } catch (Exception $e) {
        error_log("Export error: " . $e->getMessage());
        $_SESSION['export_error'] = 'Export failed: ' . $e->getMessage();
        header("Location: teacher_management.php");
        exit();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once '../includes/config.php';
    $pdo = getDBConnection();
    
    // Handle teacher addition
    if (isset($_POST['action']) && $_POST['action'] == 'add_teacher') {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert into users table
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email, full_name) VALUES (?, ?, 'teacher', ?, ?)");
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt->execute([
                $_POST['username'],
                $password_hash,
                $_POST['email'],
                $_POST['full_name']
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            // Insert into teachers table
            $stmt = $pdo->prepare("INSERT INTO teachers (user_id, teacher_id, department, position, specialization) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id,
                $_POST['teacher_id'],
                $_POST['department'],
                $_POST['position'],
                $_POST['specialization']
            ]);
            
            $pdo->commit();
            $message = 'Teacher added successfully!';
            
        } catch (PDOException $e) {
            $pdo->rollback();
            $error = 'Error adding teacher: ' . $e->getMessage();
        }
    }
    
    // Handle teacher editing
    if (isset($_POST['action']) && $_POST['action'] == 'edit_teacher') {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update users table
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ? WHERE id = ? AND role = 'teacher'");
            $stmt->execute([
                $_POST['edit_username'],
                $_POST['edit_email'], 
                $_POST['edit_full_name'],
                $_POST['edit_teacher_id']
            ]);
            
            // Update password if provided
            if (!empty($_POST['edit_password'])) {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'teacher'");
                $password_hash = password_hash($_POST['edit_password'], PASSWORD_DEFAULT);
                $stmt->execute([$password_hash, $_POST['edit_teacher_id']]);
            }
            
            // Update teachers table
            $stmt = $pdo->prepare("UPDATE teachers SET teacher_id = ?, department = ?, position = ?, specialization = ? WHERE user_id = ?");
            $stmt->execute([
                $_POST['edit_teacher_number'],
                $_POST['edit_department'],
                $_POST['edit_position'],
                $_POST['edit_specialization'],
                $_POST['edit_teacher_id']
            ]);
            
            $pdo->commit();
            $message = 'Teacher updated successfully!';
            
        } catch (PDOException $e) {
            $pdo->rollback();
            $error = 'Error updating teacher: ' . $e->getMessage();
        }
    }

    // Handle teacher deletion
    if (isset($_POST['action']) && $_POST['action'] == 'delete_teacher') {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
            $stmt->execute([$_POST['teacher_id']]);
            $message = 'Teacher deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting teacher: ' . $e->getMessage();
        }
    }
    
    // Handle CSV import
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_path = $upload_dir . basename($_FILES['csv_file']['name']);
        
        if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $file_path)) {
            // Process CSV file
            if (pathinfo($file_path, PATHINFO_EXTENSION) == 'csv') {
                try {
                    $pdo->beginTransaction();
                    $imported_count = 0;
                    
                    if (($handle = fopen($file_path, "r")) !== FALSE) {
                        // Skip header row
                        fgetcsv($handle, 1000, ",");
                        
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            // Trim whitespace from all fields
                            $data = array_map('trim', $data);
                            
                            if (count($data) >= 6) {
                                // Check if user already exists
                                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                                $stmt->execute([$data[1]]);
                                $existing_user = $stmt->fetch();
                                
                                if ($existing_user) {
                                    $user_id = $existing_user['id'];
                                } else {
                                    // Insert new user
                                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email, full_name) VALUES (?, ?, 'teacher', ?, ?)");
                                    $default_password = password_hash('password123', PASSWORD_DEFAULT);
                                    $stmt->execute([
                                        $data[1], // username
                                        $default_password,
                                        $data[3], // email
                                        $data[2]  // full_name
                                    ]);
                                    $user_id = $pdo->lastInsertId();
                                }
                                
                                if ($user_id) {
                                    // Check if teacher record already exists
                                    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? OR teacher_id = ?");
                                    $stmt->execute([$user_id, $data[0]]);
                                    $existing_teacher = $stmt->fetch();
                                    
                                    if (!$existing_teacher) {
                                        // Insert teacher record
                                        $stmt = $pdo->prepare("INSERT INTO teachers (user_id, teacher_id, department, position, specialization) VALUES (?, ?, ?, ?, ?)");
                                        $stmt->execute([
                                            $user_id,
                                            $data[0], // teacher_id
                                            $data[4], // department
                                            $data[5], // position
                                            $data[6] ?? '' // specialization
                                        ]);
                                        $imported_count++;
                                    }
                                }
                            }
                        }
                        fclose($handle);
                    }
                    
                    $pdo->commit();
                    $message = "Successfully imported $imported_count teachers from CSV file.";
                    
                } catch (PDOException $e) {
                    $pdo->rollback();
                    $error = 'Error importing CSV: ' . $e->getMessage();
                }
            } else {
                $error = 'Please upload a CSV file.';
            }
            
            // Clean up uploaded file
            unlink($file_path);
        } else {
            $error = 'File upload failed.';
        }
    }
}

// Fetch teachers for display
try {
    require_once '../includes/config.php';
    $pdo = getDBConnection();
    
    // Get search term
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    // Build query with search
    $query = "
        SELECT u.id, u.username, u.full_name, u.email, u.created_at,
               t.teacher_id, t.department, t.position, t.specialization
        FROM users u 
        LEFT JOIN teachers t ON u.id = t.user_id 
        WHERE u.role = 'teacher'
    ";
    
    $params = [];
    if (!empty($search)) {
        $query .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR t.teacher_id LIKE ? OR t.department LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
    }
    
    $query .= " ORDER BY u.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'");
    $total_teachers = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $error = 'Error fetching teachers: ' . $e->getMessage();
    $teachers = [];
    $total_teachers = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Management - Admin Dashboard</title>
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
        
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: bold;
            text-decoration: none;
            color: white;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .nav-links a:hover, .nav-links a.active {
            background-color: rgba(255,255,255,0.2);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }
        
        .breadcrumb {
            color: #666;
            font-size: 0.9rem;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border-color: #007bff;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
        }
        
        .card-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: #2c3e50;
            border-bottom: 2px solid #007bff;
            padding-bottom: 0.5rem;
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
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        .btn {
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 1rem;
            transition: all 0.3s;
            text-align: center;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #1e7e34;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .search-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            gap: 1rem;
        }
        
        .search-box {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 300px;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .table tr:hover {
            background-color: #f8f9fa;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .file-upload {
            border: 2px dashed #007bff;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 1rem;
        }
        
        .file-upload:hover {
            background: #f8f9ff;
            border-color: #0056b3;
        }
        
        .file-upload-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .export-buttons {
            display: flex;
            gap: 0.5rem;
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
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .navbar-content {
                padding: 0 1rem;
            }
            
            .nav-links {
                display: none;
            }
            
            .search-box {
                width: 200px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <a href="dashboard.php" class="navbar-brand">🎓 Admin Dashboard</a>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="statistics.php">Statistics</a>
                <a href="student_management.php">Students</a>
                <a href="teacher_management.php" class="active">Teachers</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">👨‍🏫 Teacher Management</h1>
            <div class="breadcrumb">
                Home / Admin / Teacher Management
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
                <div class="stat-number"><?php echo $total_teachers; ?></div>
                <div class="stat-label">Total Teachers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($teachers); ?></div>
                <div class="stat-label">Displayed</div>
            </div>
        </div>

        <div class="content-grid">
            <div>
                <!-- Add Teacher Form -->
                <div class="card">
                    <div class="card-title">➕ Add New Teacher</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_teacher">
                        
                        <div class="form-group">
                            <label for="teacher_id">Teacher ID</label>
                            <input type="text" id="teacher_id" name="teacher_id" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required>
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
                            <label for="department">Department</label>
                            <select id="department" name="department" class="form-control" required>
                                <option value="">Select department</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Information Systems">Information Systems</option>
                                <option value="Software Engineering">Software Engineering</option>
                                <option value="Mathematics">Mathematics</option>
                                <option value="Physics">Physics</option>
                                <option value="Electronics">Electronics</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="position">Position</label>
                            <select id="position" name="position" class="form-control" required>
                                <option value="">Select position</option>
                                <option value="Assistant Professor">Assistant Professor</option>
                                <option value="Associate Professor">Associate Professor</option>
                                <option value="Full Professor">Full Professor</option>
                                <option value="Lecturer">Lecturer</option>
                                <option value="Teaching Assistant">Teaching Assistant</option>
                                <option value="Research Fellow">Research Fellow</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="specialization">Specialization</label>
                            <input type="text" id="specialization" name="specialization" class="form-control" placeholder="e.g., Machine Learning, Database Systems">
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            ➕ Add Teacher
                        </button>
                    </form>
                </div>

                <!-- Edit Teacher Modal -->
                <div id="editTeacherModal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <span class="close" onclick="closeEditModal()">&times;</span>
                        <h3>Edit Teacher</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="edit_teacher">
                            <input type="hidden" name="edit_teacher_id" id="edit_teacher_id">
                            
                            <div class="form-group">
                                <label>Teacher ID</label>
                                <input type="text" name="edit_teacher_number" id="edit_teacher_number" class="form-control" required>
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
                                <label>Department</label>
                                <select name="edit_department" id="edit_department" class="form-control" required>
                                    <option value="">Select department</option>
                                    <option value="Computer Science">Computer Science</option>
                                    <option value="Information Systems">Information Systems</option>
                                    <option value="Software Engineering">Software Engineering</option>
                                    <option value="Mathematics">Mathematics</option>
                                    <option value="Physics">Physics</option>
                                    <option value="Electronics">Electronics</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Position</label>
                                <select name="edit_position" id="edit_position" class="form-control" required>
                                    <option value="">Select position</option>
                                    <option value="Assistant Professor">Assistant Professor</option>
                                    <option value="Associate Professor">Associate Professor</option>
                                    <option value="Full Professor">Full Professor</option>
                                    <option value="Lecturer">Lecturer</option>
                                    <option value="Teaching Assistant">Teaching Assistant</option>
                                    <option value="Research Fellow">Research Fellow</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Specialization</label>
                                <input type="text" name="edit_specialization" id="edit_specialization" class="form-control" placeholder="e.g., Machine Learning, Database Systems">
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                                <button type="submit" class="btn btn-primary">Update Teacher</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Import/Export Section -->
                <div class="card">
                    <div class="card-title">📊 Import/Export</div>
                    
                <!-- Import Section -->
                <form method="POST" enctype="multipart/form-data" style="margin-bottom: 1rem;">
                    <div class="file-upload" onclick="document.getElementById('csv_file').click()">
                        <div class="file-upload-icon">📁</div>
                        <div>Click to select CSV file</div>
                        <small style="color: #666;">Teacher list format (CSV) - <a href="../docs/samples/sample_teachers.csv" download>Download Sample</a></small>
                    </div>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" style="display: none;" onchange="this.form.submit()">
                </form>                    <!-- Export Buttons -->
                    <div class="export-buttons">
                        <a href="?export=csv" class="btn btn-warning" style="flex: 1; text-align: center;">
                            📊 Export CSV
                        </a>
                        <a href="?export=excel" class="btn btn-warning" style="flex: 1; text-align: center;">
                            📈 Export Excel
                        </a>
                    </div>
                </div>
            </div>

            <div>
                <!-- Search and Teachers List -->
                <div class="card">
                    <div class="search-controls">
                        <input type="text" id="searchBox" class="search-box" placeholder="🔍 Search teachers..." value="<?php echo htmlspecialchars($search); ?>">
                        <div class="export-buttons">
                            <button type="button" class="btn btn-primary btn-sm" onclick="window.location.reload()">
                                🔄 Refresh
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Teacher ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Position</th>
                                <th>Specialization</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($teacher['teacher_id'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($teacher['username']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['department'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($teacher['position'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($teacher['specialization'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M j, Y', strtotime($teacher['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <button class="btn btn-primary btn-sm" onclick="editTeacher(<?php echo $teacher['id']; ?>)">
                                            ✏️ Edit
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this teacher?')">
                                            <input type="hidden" name="action" value="delete_teacher">
                                            <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
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
                
                <?php if (empty($teachers)): ?>
                <div style="text-align: center; padding: 3rem; color: #666;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">👨‍🏫</div>
                    <div style="font-size: 1.2rem; margin-bottom: 0.5rem;">No teachers found</div>
                    <div>Add teachers using the form or import from CSV</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchBox').addEventListener('keyup', function() {
            var searchTerm = this.value.toLowerCase();
            var rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Edit teacher function
        function editTeacher(teacherId) {
            // Fetch teacher data via AJAX
            fetch('get_teacher_data.php?id=' + teacherId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate the edit form with existing data
                        document.getElementById('edit_teacher_id').value = data.teacher.id;
                        document.getElementById('edit_teacher_number').value = data.teacher.teacher_id || '';
                        document.getElementById('edit_username').value = data.teacher.username;
                        document.getElementById('edit_full_name').value = data.teacher.full_name;
                        document.getElementById('edit_email').value = data.teacher.email;
                        document.getElementById('edit_department').value = data.teacher.department || '';
                        document.getElementById('edit_position').value = data.teacher.position || '';
                        document.getElementById('edit_specialization').value = data.teacher.specialization || '';
                        
                        // Clear password field
                        document.getElementById('edit_password').value = '';
                        
                        // Show the modal
                        document.getElementById('editTeacherModal').style.display = 'block';
                    } else {
                        alert('Error loading teacher data: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading teacher data');
                });
        }
        
        function closeEditModal() {
            document.getElementById('editTeacherModal').style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            var modal = document.getElementById('editTeacherModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        document.getElementById('csv_file').addEventListener('change', function() {
            var fileName = this.files[0] ? this.files[0].name : 'No file selected';
            var fileUpload = document.querySelector('.file-upload div');
            fileUpload.textContent = fileName;
        });
    </script>
</body>
</html>
<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/unauthorized.php");
    exit();
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once '../includes/config.php';
    $pdo = getDBConnection();
    
    // Handle module addition
    if (isset($_POST['action']) && $_POST['action'] == 'add_module') {
        try {
            $stmt = $pdo->prepare("INSERT INTO modules (module_code, module_name, description, credits, department, year_level, semester) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['module_code'],
                $_POST['module_name'],
                $_POST['description'],
                $_POST['credits'],
                $_POST['department'],
                $_POST['year_level'],
                $_POST['semester']
            ]);
            $message = 'Module added successfully!';
        } catch (PDOException $e) {
            $error = 'Error adding module: ' . $e->getMessage();
        }
    }
    
    // Handle module editing
    if (isset($_POST['action']) && $_POST['action'] == 'edit_module') {
        try {
            $stmt = $pdo->prepare("UPDATE modules SET module_code = ?, module_name = ?, description = ?, credits = ?, department = ?, year_level = ?, semester = ?, is_active = ? WHERE id = ?");
            $stmt->execute([
                $_POST['edit_module_code'],
                $_POST['edit_module_name'],
                $_POST['edit_description'],
                $_POST['edit_credits'],
                $_POST['edit_department'],
                $_POST['edit_year_level'],
                $_POST['edit_semester'],
                $_POST['edit_is_active'] ?? 0,
                $_POST['edit_module_id']
            ]);
            $message = 'Module updated successfully!';
        } catch (PDOException $e) {
            $error = 'Error updating module: ' . $e->getMessage();
        }
    }

    // Handle module deletion
    if (isset($_POST['action']) && $_POST['action'] == 'delete_module') {
        try {
            // First remove all teacher assignments
            $stmt = $pdo->prepare("DELETE FROM teacher_modules WHERE module_id = ?");
            $stmt->execute([$_POST['module_id']]);
            
            // Then delete the module
            $stmt = $pdo->prepare("DELETE FROM modules WHERE id = ?");
            $stmt->execute([$_POST['module_id']]);
            $message = 'Module deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting module: ' . $e->getMessage();
        }
    }
    
    // Handle teacher-module assignment
    if (isset($_POST['action']) && $_POST['action'] == 'assign_teacher') {
        try {
            // Check if assignment already exists
            $stmt = $pdo->prepare("SELECT id FROM teacher_modules WHERE teacher_id = ? AND module_id = ?");
            $stmt->execute([$_POST['teacher_id'], $_POST['module_id']]);
            
            if ($stmt->fetch()) {
                $error = 'Teacher is already assigned to this module!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO teacher_modules (teacher_id, module_id, role) VALUES (?, ?, ?)");
                $stmt->execute([
                    $_POST['teacher_id'],
                    $_POST['module_id'],
                    $_POST['role']
                ]);
                $message = 'Teacher assigned to module successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Error assigning teacher: ' . $e->getMessage();
        }
    }
    
    // Handle teacher-module unassignment
    if (isset($_POST['action']) && $_POST['action'] == 'unassign_teacher') {
        try {
            $stmt = $pdo->prepare("DELETE FROM teacher_modules WHERE id = ?");
            $stmt->execute([$_POST['assignment_id']]);
            $message = 'Teacher unassigned from module successfully!';
        } catch (PDOException $e) {
            $error = 'Error unassigning teacher: ' . $e->getMessage();
        }
    }
}

// Fetch modules for display
try {
    require_once '../includes/config.php';
    $pdo = getDBConnection();
    
    // Get search term
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    // Build query with search
    $query = "
        SELECT m.*, COUNT(tm.id) as teacher_count
        FROM modules m 
        LEFT JOIN teacher_modules tm ON m.id = tm.module_id
        WHERE 1=1
    ";
    
    $params = [];
    if (!empty($search)) {
        $query .= " AND (m.module_code LIKE ? OR m.module_name LIKE ? OR m.department LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    $query .= " GROUP BY m.id ORDER BY m.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total counts
    $stmt = $pdo->query("SELECT COUNT(*) FROM modules");
    $total_modules = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM modules WHERE is_active = 1");
    $active_modules = $stmt->fetchColumn();
    
    // Get teachers for assignment dropdown
    $stmt = $pdo->query("
        SELECT t.id, u.full_name, t.teacher_id, t.department 
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE u.role = 'teacher' 
        ORDER BY u.full_name
    ");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Error fetching modules: ' . $e->getMessage();
    $modules = [];
    $teachers = [];
    $total_modules = 0;
    $active_modules = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Module Management - Admin Dashboard</title>
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
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
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
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .module-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .teacher-list {
            list-style: none;
            padding: 0;
        }
        
        .teacher-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .teacher-info {
            flex: 1;
        }
        
        .teacher-role {
            font-size: 0.8rem;
            color: #666;
            font-style: italic;
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
            max-width: 800px;
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
        
        .close:hover {
            color: #000;
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
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
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
                <a href="teacher_management.php">Teachers</a>
                <a href="module_management.php" class="active">Modules</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">📚 Module Management</h1>
            <div class="breadcrumb">
                Home / Admin / Module Management
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

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_modules; ?></div>
                <div class="stat-label">Total Modules</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $active_modules; ?></div>
                <div class="stat-label">Active Modules</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($teachers); ?></div>
                <div class="stat-label">Available Teachers</div>
            </div>
        </div>
        
        <!-- Import/Export Actions -->
        <div style="background: white; padding: 1rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 2rem;">
            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <h3 style="margin: 0; color: #333;">📁 Import/Export</h3>
                <button class="btn btn-success" onclick="openImportModal()" style="background: #28a745;">
                    📥 Import Modules
                </button>
                <button class="btn btn-secondary" onclick="exportModules('csv')" style="background: #6c757d;">
                    📊 Export CSV
                </button>
                <a href="sample_modules.csv" download class="btn btn-secondary" style="background: #17a2b8; text-decoration: none;">
                    📋 Download Sample CSV
                </a>
            </div>
        </div>

        <div class="content-grid">
            <div>
                <!-- Add Module Form -->
                <div class="card">
                    <div class="card-title">➕ Add New Module</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_module">
                        
                        <div class="form-group">
                            <label for="module_code">Module Code</label>
                            <input type="text" id="module_code" name="module_code" class="form-control" required placeholder="e.g., CS101">
                        </div>
                        
                        <div class="form-group">
                            <label for="module_name">Module Name</label>
                            <input type="text" id="module_name" name="module_name" class="form-control" required placeholder="e.g., Introduction to Programming">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3" placeholder="Module description..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="credits">Credits</label>
                            <select id="credits" name="credits" class="form-control" required>
                                <option value="">Select credits</option>
                                <option value="1">1 Credit</option>
                                <option value="2">2 Credits</option>
                                <option value="3">3 Credits</option>
                                <option value="4">4 Credits</option>
                                <option value="5">5 Credits</option>
                                <option value="6">6 Credits</option>
                            </select>
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
                            <label for="year_level">Year Level</label>
                            <select id="year_level" name="year_level" class="form-control" required>
                                <option value="">Select year</option>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                                <option value="5">5th Year</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="semester">Semester</label>
                            <select id="semester" name="semester" class="form-control" required>
                                <option value="">Select semester</option>
                                <option value="Fall">Fall</option>
                                <option value="Spring">Spring</option>
                                <option value="Summer">Summer</option>
                                <option value="Both">Both Fall & Spring</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            ➕ Add Module
                        </button>
                    </form>
                </div>

                <!-- Teacher Assignment Form -->
                <div class="card">
                    <div class="card-title">👨‍🏫 Assign Teacher to Module</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="assign_teacher">
                        
                        <div class="form-group">
                            <label for="assign_teacher_id">Select Teacher</label>
                            <select id="assign_teacher_id" name="teacher_id" class="form-control" required>
                                <option value="">Choose teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['full_name'] . ' (' . $teacher['teacher_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="assign_module_id">Select Module</label>
                            <select id="assign_module_id" name="module_id" class="form-control" required>
                                <option value="">Choose module</option>
                                <?php foreach ($modules as $module): ?>
                                    <option value="<?php echo $module['id']; ?>">
                                        <?php echo htmlspecialchars($module['module_code'] . ' - ' . $module['module_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Teaching Role</label>
                            <select id="role" name="role" class="form-control" required>
                                <option value="">Select role</option>
                                <option value="Lecturer">Lecturer</option>
                                <option value="Assistant">Teaching Assistant</option>
                                <option value="Coordinator">Course Coordinator</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-success" style="width: 100%;">
                            👨‍🏫 Assign Teacher
                        </button>
                    </form>
                </div>
            </div>

            <div>
                <!-- Search and Modules List -->
                <div class="card">
                    <div class="search-controls">
                        <input type="text" id="searchBox" class="search-box" placeholder="🔍 Search modules..." value="<?php echo htmlspecialchars($search); ?>">
                        <div>
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
                                <th>Code</th>
                                <th>Module Name</th>
                                <th>Department</th>
                                <th>Credits</th>
                                <th>Year</th>
                                <th>Semester</th>
                                <th>Teachers</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($modules as $module): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($module['module_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($module['module_name']); ?></td>
                                <td><?php echo htmlspecialchars($module['department']); ?></td>
                                <td><?php echo $module['credits']; ?></td>
                                <td>Year <?php echo $module['year_level']; ?></td>
                                <td><?php echo htmlspecialchars($module['semester']); ?></td>
                                <td><?php echo $module['teacher_count']; ?> teacher(s)</td>
                                <td>
                                    <span class="status-badge <?php echo $module['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $module['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button class="btn btn-primary btn-sm" onclick="viewModule(<?php echo $module['id']; ?>)">
                                            👁️ View
                                        </button>
                                        <button class="btn btn-warning btn-sm" onclick="editModule(<?php echo $module['id']; ?>)">
                                            ✏️ Edit
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this module?')">
                                            <input type="hidden" name="action" value="delete_module">
                                            <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
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
                
                <?php if (empty($modules)): ?>
                <div style="text-align: center; padding: 3rem; color: #666;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📚</div>
                    <div style="font-size: 1.2rem; margin-bottom: 0.5rem;">No modules found</div>
                    <div>Add modules using the form on the left</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Module View Modal -->
    <div id="viewModuleModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('viewModuleModal')">&times;</span>
            <h3>Module Details</h3>
            <div id="moduleDetails"></div>
        </div>
    </div>

    <!-- Module Edit Modal -->
    <div id="editModuleModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModuleModal')">&times;</span>
            <h3>Edit Module</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit_module">
                <input type="hidden" name="edit_module_id" id="edit_module_id">
                
                <div class="form-group">
                    <label>Module Code</label>
                    <input type="text" name="edit_module_code" id="edit_module_code" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Module Name</label>
                    <input type="text" name="edit_module_name" id="edit_module_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="edit_description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Credits</label>
                    <select name="edit_credits" id="edit_credits" class="form-control" required>
                        <option value="1">1 Credit</option>
                        <option value="2">2 Credits</option>
                        <option value="3">3 Credits</option>
                        <option value="4">4 Credits</option>
                        <option value="5">5 Credits</option>
                        <option value="6">6 Credits</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Department</label>
                    <select name="edit_department" id="edit_department" class="form-control" required>
                        <option value="Computer Science">Computer Science</option>
                        <option value="Information Systems">Information Systems</option>
                        <option value="Software Engineering">Software Engineering</option>
                        <option value="Mathematics">Mathematics</option>
                        <option value="Physics">Physics</option>
                        <option value="Electronics">Electronics</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Year Level</label>
                    <select name="edit_year_level" id="edit_year_level" class="form-control" required>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                        <option value="5">5th Year</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Semester</label>
                    <select name="edit_semester" id="edit_semester" class="form-control" required>
                        <option value="Fall">Fall</option>
                        <option value="Spring">Spring</option>
                        <option value="Summer">Summer</option>
                        <option value="Both">Both Fall & Spring</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="edit_is_active" id="edit_is_active" value="1"> 
                        Module is active
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModuleModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Module</button>
                </div>
            </form>
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
        
        // View module function
        function viewModule(moduleId) {
            fetch('get_module_data.php?id=' + moduleId + '&view=full')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('moduleDetails').innerHTML = data.html;
                        document.getElementById('viewModuleModal').style.display = 'block';
                    } else {
                        alert('Error loading module data: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading module data');
                });
        }
        
        // Edit module function
        function editModule(moduleId) {
            fetch('get_module_data.php?id=' + moduleId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const module = data.module;
                        document.getElementById('edit_module_id').value = module.id;
                        document.getElementById('edit_module_code').value = module.module_code;
                        document.getElementById('edit_module_name').value = module.module_name;
                        document.getElementById('edit_description').value = module.description || '';
                        document.getElementById('edit_credits').value = module.credits;
                        document.getElementById('edit_department').value = module.department;
                        document.getElementById('edit_year_level').value = module.year_level;
                        document.getElementById('edit_semester').value = module.semester;
                        document.getElementById('edit_is_active').checked = module.is_active == 1;
                        
                        document.getElementById('editModuleModal').style.display = 'block';
                    } else {
                        alert('Error loading module data: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading module data');
                });
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function openImportModal() {
            document.getElementById('importModal').style.display = 'block';
        }
        
        function closeImportModal() {
            document.getElementById('importModal').style.display = 'none';
        }
        
        function exportModules(format) {
            window.location.href = 'export_modules.php?format=' + format;
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            var modals = ['viewModuleModal', 'editModuleModal', 'importModal'];
            modals.forEach(function(modalId) {
                var modal = document.getElementById(modalId);
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
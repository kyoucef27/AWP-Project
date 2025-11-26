<?php
session_start();
require_once '../../includes/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/unauthorized.php");
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_columns') {
    try {
        $pdo = getDBConnection();
        
        // Check if specialty column exists in modules table
        $stmt = $pdo->query("SHOW COLUMNS FROM modules LIKE 'specialty'");
        $modules_has_specialty = $stmt->rowCount() > 0;
        
        // Check if specialty column exists in students table
        $stmt = $pdo->query("SHOW COLUMNS FROM students LIKE 'specialty'");
        $students_has_specialty = $stmt->rowCount() > 0;
        
        $operations = [];
        
        // Add specialty column to modules table if it doesn't exist
        if (!$modules_has_specialty) {
            $pdo->exec("ALTER TABLE modules ADD COLUMN specialty VARCHAR(200) DEFAULT 'All' AFTER department");
            $operations[] = "Added 'specialty' column to modules table";
        } else {
            $operations[] = "modules.specialty column already exists";
        }
        
        // Add specialty column to students table if it doesn't exist
        if (!$students_has_specialty) {
            $pdo->exec("ALTER TABLE students ADD COLUMN specialty ENUM('Computer Science', 'Software Engineering', 'Information Systems', 'Data Science') DEFAULT 'Computer Science' AFTER specialization");
            $operations[] = "Added 'specialty' column to students table";
            
            // Update existing students based on their group assignments
            $stmt = $pdo->query("
                UPDATE students s
                JOIN student_group_assignments sga ON s.id = sga.student_id
                JOIN student_groups sg ON sga.group_id = sg.id
                SET s.specialty = CASE 
                    WHEN sg.specialization LIKE '%Computer Science%' THEN 'Computer Science'
                    WHEN sg.specialization LIKE '%Software Engineering%' THEN 'Software Engineering'
                    WHEN sg.specialization LIKE '%Information Systems%' THEN 'Information Systems'
                    ELSE 'Computer Science'
                END
            ");
            $updated_students = $stmt->rowCount();
            $operations[] = "Updated specialty for {$updated_students} existing students based on their groups";
        } else {
            $operations[] = "students.specialty column already exists";
        }
        
        $message = implode('<br>', $operations);
        
    } catch (PDOException $e) {
        $error = 'Error adding specialty columns: ' . $e->getMessage();
    }
}

// Check current column status
try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->query("SHOW COLUMNS FROM modules LIKE 'specialty'");
    $modules_has_specialty = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW COLUMNS FROM students LIKE 'specialty'");
    $students_has_specialty = $stmt->rowCount() > 0;
    
    // Get counts
    $stmt = $pdo->query("SELECT COUNT(*) FROM modules");
    $module_count = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM students");
    $student_count = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $error = 'Database connection error: ' . $e->getMessage();
    $modules_has_specialty = false;
    $students_has_specialty = false;
    $module_count = 0;
    $student_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Specialty Columns - Database Migration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
            padding: 3rem;
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .header i {
            font-size: 4rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        
        h1 {
            color: #2c3e50;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .subtitle {
            color: #6c757d;
            font-size: 1rem;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .status-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        
        .status-card h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .status-item:last-child {
            border-bottom: none;
        }
        
        .status-label {
            color: #495057;
            font-weight: 500;
        }
        
        .status-value {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 1.5rem;
            border-radius: 4px;
            margin-bottom: 2rem;
        }
        
        .info-box h3 {
            color: #0c5460;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        
        .info-box p {
            margin: 0.5rem 0;
            color: #0c5460;
            line-height: 1.6;
        }
        
        .info-box ul {
            margin: 1rem 0 0 1.5rem;
            color: #0c5460;
        }
        
        .info-box li {
            margin: 0.5rem 0;
        }
        
        .btn {
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
            min-width: 200px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn:disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <i class="fas fa-database"></i>
            <h1>Database Migration</h1>
            <p class="subtitle">Add Specialty Columns to Enable Specialty-Based Enrollment</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $message; ?></div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="status-grid">
            <div class="status-card">
                <h3><i class="fas fa-book"></i> Modules Table</h3>
                <div class="status-item">
                    <span class="status-label">Total Modules</span>
                    <span class="status-value"><?php echo $module_count; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label">Specialty Column</span>
                    <span class="status-value">
                        <?php if ($modules_has_specialty): ?>
                            <span class="badge badge-success"><i class="fas fa-check"></i> Exists</span>
                        <?php else: ?>
                            <span class="badge badge-danger"><i class="fas fa-times"></i> Missing</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            
            <div class="status-card">
                <h3><i class="fas fa-users"></i> Students Table</h3>
                <div class="status-item">
                    <span class="status-label">Total Students</span>
                    <span class="status-value"><?php echo $student_count; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label">Specialty Column</span>
                    <span class="status-value">
                        <?php if ($students_has_specialty): ?>
                            <span class="badge badge-success"><i class="fas fa-check"></i> Exists</span>
                        <?php else: ?>
                            <span class="badge badge-danger"><i class="fas fa-times"></i> Missing</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="info-box">
            <h3><i class="fas fa-info-circle"></i> What This Migration Does</h3>
            <p><strong>This migration will safely add the required specialty columns:</strong></p>
            <ul>
                <li><strong>modules.specialty:</strong> VARCHAR(200) DEFAULT 'All' - Defines which specialty each module targets</li>
                <li><strong>students.specialty:</strong> ENUM with options: Computer Science, Software Engineering, Information Systems, Data Science</li>
            </ul>
            <p><strong>Additional Actions:</strong></p>
            <ul>
                <li>Existing students will be automatically assigned specialties based on their current group assignments</li>
                <li>All existing modules will default to 'All' specialty (available to all students)</li>
                <li>No existing data will be lost or modified</li>
            </ul>
        </div>

        <div class="btn-group">
            <?php if (!$modules_has_specialty || !$students_has_specialty): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="add_columns">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Add Missing Specialty Columns
                    </button>
                </form>
            <?php else: ?>
                <button class="btn btn-success" disabled>
                    <i class="fas fa-check"></i>
                    All Columns Present
                </button>
            <?php endif; ?>
            
            <a href="../setup_sample_modules.php" class="btn btn-secondary">
                <i class="fas fa-magic"></i>
                Generate Sample Modules
            </a>
            
            <a href="../dashboard.php" class="btn btn-secondary">
                <i class="fas fa-home"></i>
                Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
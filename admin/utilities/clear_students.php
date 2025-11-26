<?php
/**
 * Clear Students Tool
 * This script removes all students from the database
 * USE WITH CAUTION - This will delete all student records permanently
 */

session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/unauthorized.php");
    exit();
}

require_once '../../includes/config.php';

$message = '';
$error = '';
$confirmation_required = true;

// Handle the deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete']) && $_POST['confirm_delete'] == 'YES_DELETE_ALL_STUDENTS') {
    try {
        $pdo = getDBConnection();
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Get count before deletion
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM students");
        $count = $stmt->fetch();
        $student_count = $count['count'];
        
        // Delete all student group assignments first
        $pdo->exec("DELETE FROM student_group_assignments");
        
        // Delete all students (this will cascade to related records)
        $pdo->exec("DELETE FROM students");
        
        // Delete student users
        $pdo->exec("DELETE FROM users WHERE role = 'student'");
        
        // Commit transaction
        $pdo->commit();
        
        $message = "Successfully deleted {$student_count} students and all related records.";
        $confirmation_required = false;
        
    } catch (PDOException $e) {
        $pdo->rollback();
        $error = 'Error deleting students: ' . $e->getMessage();
    }
}

// Get current student count
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM students");
    $count = $stmt->fetch();
    $total_students = $count['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM student_group_assignments");
    $count = $stmt->fetch();
    $assigned_students = $count['count'];
} catch (PDOException $e) {
    $error = 'Error fetching student count: ' . $e->getMessage();
    $total_students = 0;
    $assigned_students = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clear All Students - Admin Tool</title>
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
            padding: 2rem;
        }

        .container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 2rem;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header i {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }

        h1 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: #666;
            font-size: 0.95rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin: 2rem 0;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #dc3545;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 2rem 0;
        }

        .warning-box h3 {
            color: #856404;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .warning-box ul {
            margin-left: 1.5rem;
            color: #856404;
        }

        .warning-box li {
            margin: 0.5rem 0;
        }

        .form-group {
            margin: 1.5rem 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #dc3545;
        }

        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-danger:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-top: 1rem;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .confirmation-text {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.5rem;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: #667eea;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <i class="fas fa-exclamation-triangle"></i>
            <h1>Clear All Students</h1>
            <div class="subtitle">Database Cleanup Tool</div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($confirmation_required): ?>
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $assigned_students; ?></div>
                    <div class="stat-label">In Groups</div>
                </div>
            </div>

            <?php if ($total_students > 0): ?>
                <div class="warning-box">
                    <h3><i class="fas fa-exclamation-triangle"></i> Warning: Irreversible Action</h3>
                    <p style="margin-bottom: 1rem;">This action will permanently delete:</p>
                    <ul>
                        <li>All <?php echo $total_students; ?> student records</li>
                        <li>All student user accounts</li>
                        <li>All group assignments</li>
                        <li>All related enrollment data</li>
                    </ul>
                    <p style="margin-top: 1rem; font-weight: 600;">This action CANNOT be undone!</p>
                </div>

                <form method="POST" onsubmit="return confirm('Are you ABSOLUTELY SURE you want to delete ALL students? This cannot be undone!');">
                    <div class="form-group">
                        <label for="confirm_delete">Type <strong>YES_DELETE_ALL_STUDENTS</strong> to confirm:</label>
                        <input 
                            type="text" 
                            id="confirm_delete" 
                            name="confirm_delete" 
                            class="form-control" 
                            required 
                            placeholder="Type confirmation text here"
                            autocomplete="off"
                        >
                        <div class="confirmation-text">
                            This is a safety measure to prevent accidental deletion.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i> Delete All <?php echo $total_students; ?> Students
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i> No students found in the database.
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> The database has been cleared successfully.
            </div>
        <?php endif; ?>

        <a href="../dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <a href="../student_management/student_management.php" class="back-link">
            Go to Student Management
        </a>
    </div>
</body>
</html>

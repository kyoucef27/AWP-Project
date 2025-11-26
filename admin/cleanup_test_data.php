<?php
/**
 * Data Management Utility
 * Clean up test data and manage database records safely
 */
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/unauthorized.php");
    exit();
}

require_once '../includes/config.php';

$action = $_GET['action'] ?? '';
$message = '';
$error = '';

// Handle cleanup actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm'])) {
    try {
        $pdo = getDBConnection();
        
        switch ($_POST['action']) {
            case 'cleanup_test_users':
                $stmt = $pdo->prepare("DELETE FROM users WHERE username LIKE 'test.%' OR username LIKE '%test%'");
                $stmt->execute();
                $count = $stmt->rowCount();
                $message = "Removed $count test user accounts";
                break;
                
            case 'cleanup_orphaned_teachers':
                // Remove teachers without users
                $stmt = $pdo->prepare("DELETE FROM teachers WHERE user_id NOT IN (SELECT id FROM users)");
                $stmt->execute();
                $count = $stmt->rowCount();
                $message = "Removed $count orphaned teacher records";
                break;
                
            case 'cleanup_orphaned_students':
                // Remove students without users
                $stmt = $pdo->prepare("DELETE FROM students WHERE user_id NOT IN (SELECT id FROM users)");
                $stmt->execute();
                $count = $stmt->rowCount();
                $message = "Removed $count orphaned student records";
                break;
                
            case 'reset_module_assignments':
                $stmt = $pdo->prepare("DELETE FROM teacher_modules");
                $stmt->execute();
                $count = $stmt->rowCount();
                $message = "Removed $count teacher-module assignments";
                break;
        }
    } catch (PDOException $e) {
        $error = 'Error during cleanup: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Management - Admin Tools</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            text-align: center;
        }
        .section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .btn {
            display: inline-block;
            padding: 0.75rem 1rem;
            margin: 0.25rem;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            border-left: 4px solid #667eea;
            text-align: center;
        }
        .cleanup-action {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 1rem;
            margin: 0.5rem 0;
        }
        .cleanup-action h4 {
            margin-top: 0;
            color: #333;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 500px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧹 Data Management Utility</h1>
            <p>Clean up test data and manage database records</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php
        // Get current database statistics
        try {
            $pdo = getDBConnection();
            
            // Count records
            $stats = [];
            $tables = ['users', 'teachers', 'students', 'professors', 'modules', 'teacher_modules'];
            
            foreach ($tables as $table) {
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                    $stats[$table] = $stmt->fetchColumn();
                } catch (Exception $e) {
                    $stats[$table] = 'N/A';
                }
            }
            
            // Check for test data
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username LIKE 'test.%' OR username LIKE '%test%'");
            $test_users = $stmt->fetchColumn();
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM teachers WHERE user_id NOT IN (SELECT id FROM users)");
            $orphaned_teachers = $stmt->fetchColumn();
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE user_id NOT IN (SELECT id FROM users)");
            $orphaned_students = $stmt->fetchColumn();
            
        } catch (Exception $e) {
            $error = 'Could not retrieve database statistics: ' . $e->getMessage();
        }
        ?>

        <div class="section">
            <h3>📊 Current Database Status</h3>
            <div class="stats-grid">
                <?php foreach ($stats as $table => $count): ?>
                    <div class="stat-card">
                        <h4><?php echo ucfirst(str_replace('_', ' ', $table)); ?></h4>
                        <p><strong><?php echo $count; ?></strong> records</p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="section">
            <h3>🧹 Cleanup Operations</h3>
            
            <div class="cleanup-action">
                <h4>🧪 Test Data Cleanup</h4>
                <p>Remove test user accounts and related data</p>
                <p><strong>Found:</strong> <?php echo $test_users; ?> test users</p>
                <?php if ($test_users > 0): ?>
                    <button class="btn btn-warning" onclick="confirmAction('cleanup_test_users', 'Remove all test user accounts?')">
                        Clean Test Users
                    </button>
                <?php else: ?>
                    <span class="alert alert-success">✅ No test users found</span>
                <?php endif; ?>
            </div>

            <div class="cleanup-action">
                <h4>🔗 Orphaned Records Cleanup</h4>
                <p>Remove teacher/student records without corresponding user accounts</p>
                <p><strong>Found:</strong> <?php echo $orphaned_teachers; ?> orphaned teachers, <?php echo $orphaned_students; ?> orphaned students</p>
                <?php if ($orphaned_teachers > 0): ?>
                    <button class="btn btn-danger" onclick="confirmAction('cleanup_orphaned_teachers', 'Remove orphaned teacher records?')">
                        Clean Orphaned Teachers
                    </button>
                <?php endif; ?>
                <?php if ($orphaned_students > 0): ?>
                    <button class="btn btn-danger" onclick="confirmAction('cleanup_orphaned_students', 'Remove orphaned student records?')">
                        Clean Orphaned Students
                    </button>
                <?php endif; ?>
                <?php if ($orphaned_teachers == 0 && $orphaned_students == 0): ?>
                    <span class="alert alert-success">✅ No orphaned records found</span>
                <?php endif; ?>
            </div>

            <div class="cleanup-action">
                <h4>📚 Module Assignments Reset</h4>
                <p>Remove all teacher-module assignments</p>
                <p><strong>Current assignments:</strong> <?php echo $stats['teacher_modules'] ?? 0; ?></p>
                <?php if (($stats['teacher_modules'] ?? 0) > 0): ?>
                    <button class="btn btn-danger" onclick="confirmAction('reset_module_assignments', 'Remove ALL teacher-module assignments?')">
                        Reset Assignments
                    </button>
                <?php else: ?>
                    <span class="alert alert-success">✅ No assignments to remove</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="section">
            <h3>⚙️ Quick Actions</h3>
            <a href="dashboard.php" class="btn btn-primary">🏠 Admin Dashboard</a>
            <a href="utilities/system_diagnostics.php" class="btn btn-secondary">🔧 System Diagnostics</a>
            <a href="teacher_management/teacher_management.php" class="btn btn-secondary">👨‍🏫 Teacher Management</a>
            <a href="module_management/module_management.php" class="btn btn-secondary">📚 Module Management</a>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3>⚠️ Confirm Action</h3>
            <p id="confirmMessage">Are you sure you want to proceed?</p>
            <p><strong>This action cannot be undone!</strong></p>
            
            <form method="POST" style="text-align: right;">
                <input type="hidden" name="action" id="actionInput">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" name="confirm" class="btn btn-danger">Confirm</button>
            </form>
        </div>
    </div>

    <script>
        function confirmAction(action, message) {
            document.getElementById('confirmMessage').textContent = message;
            document.getElementById('actionInput').value = action;
            document.getElementById('confirmModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('confirmModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
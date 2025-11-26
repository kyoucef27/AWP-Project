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
$pdo = getDBConnection();

// Handle clear modules action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'clear_modules') {
        try {
            $pdo->beginTransaction();
            
            // Delete all module-related data in correct order
            $stmt = $pdo->query("DELETE FROM teacher_modules");
            $teacher_modules_deleted = $stmt->rowCount();
            
            $stmt = $pdo->query("DELETE FROM module_group_assignments");
            $group_assignments_deleted = $stmt->rowCount();
            
            $stmt = $pdo->query("DELETE FROM attendance");
            $attendance_deleted = $stmt->rowCount();
            
            $stmt = $pdo->query("DELETE FROM enrollments");
            $enrollments_deleted = $stmt->rowCount();
            
            $stmt = $pdo->query("DELETE FROM modules");
            $modules_deleted = $stmt->rowCount();
            
            $pdo->commit();
            
            $message = "Successfully cleared all modules and related data:<br>
                       - {$modules_deleted} modules deleted<br>
                       - {$teacher_modules_deleted} teacher assignments deleted<br>
                       - {$group_assignments_deleted} group assignments deleted<br>
                       - {$enrollments_deleted} enrollments deleted<br>
                       - {$attendance_deleted} attendance records deleted";
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error clearing modules: ' . $e->getMessage();
        }
    }
}

// Get statistics
$stats = [];
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM modules");
    $stats['total_modules'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM modules WHERE is_active = 1");
    $stats['active_modules'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM teacher_modules");
    $stats['teacher_assignments'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM module_group_assignments");
    $stats['group_assignments'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM enrollments");
    $stats['enrollments'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM attendance");
    $stats['attendance'] = $stmt->fetchColumn();
    
    // Get module breakdown by specialty
    $stmt = $pdo->query("
        SELECT specialty, COUNT(*) as count 
        FROM modules 
        GROUP BY specialty 
        ORDER BY count DESC
    ");
    $specialty_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get module breakdown by year level
    $stmt = $pdo->query("
        SELECT year_level, COUNT(*) as count 
        FROM modules 
        GROUP BY year_level 
        ORDER BY year_level
    ");
    $year_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Error fetching statistics: ' . $e->getMessage();
    $stats = ['total_modules' => 0, 'active_modules' => 0, 'teacher_assignments' => 0, 
              'group_assignments' => 0, 'enrollments' => 0, 'attendance' => 0];
    $specialty_breakdown = [];
    $year_breakdown = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clear All Modules - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .page-title {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .page-title i {
            color: #dc3545;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .alert i {
            margin-top: 0.25rem;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-icon.danger { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .stat-icon.warning { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #333; }
        .stat-icon.info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .stat-icon.success { background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%); color: white; }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .action-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .action-section h2 {
            color: #2c3e50;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1.5rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
        
        .warning-box h3 {
            color: #856404;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .warning-box p {
            margin: 0.5rem 0;
            color: #856404;
        }
        
        .warning-box ul {
            margin: 1rem 0 0 1.5rem;
            color: #856404;
        }
        
        .warning-box li {
            margin: 0.5rem 0;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .breakdown-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .breakdown-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
        }
        
        .breakdown-card h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .breakdown-item:last-child {
            border-bottom: none;
        }
        
        .breakdown-label {
            color: #495057;
        }
        
        .breakdown-value {
            font-weight: 600;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <?php include '../../includes/admin_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-trash-alt"></i>
                Clear All Modules
            </h1>
            <p>Remove all modules and related data from the database</p>
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

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['total_modules']; ?></div>
                    <div class="stat-label">Total Modules</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['teacher_assignments']; ?></div>
                    <div class="stat-label">Teacher Assignments</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['group_assignments']; ?></div>
                    <div class="stat-label">Group Assignments</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['enrollments']; ?></div>
                    <div class="stat-label">Student Enrollments</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['attendance']; ?></div>
                    <div class="stat-label">Attendance Records</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['active_modules']; ?></div>
                    <div class="stat-label">Active Modules</div>
                </div>
            </div>
        </div>

        <!-- Breakdown -->
        <?php if (!empty($specialty_breakdown) || !empty($year_breakdown)): ?>
        <div class="action-section">
            <h2>
                <i class="fas fa-chart-pie"></i>
                Module Breakdown
            </h2>
            
            <div class="breakdown-grid">
                <?php if (!empty($specialty_breakdown)): ?>
                <div class="breakdown-card">
                    <h3><i class="fas fa-graduation-cap"></i> By Specialty</h3>
                    <?php foreach ($specialty_breakdown as $item): ?>
                    <div class="breakdown-item">
                        <span class="breakdown-label"><?php echo htmlspecialchars($item['specialty'] ?: 'Unspecified'); ?></span>
                        <span class="breakdown-value"><?php echo $item['count']; ?> modules</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($year_breakdown)): ?>
                <div class="breakdown-card">
                    <h3><i class="fas fa-calendar"></i> By Year Level</h3>
                    <?php foreach ($year_breakdown as $item): ?>
                    <div class="breakdown-item">
                        <span class="breakdown-label">Year <?php echo $item['year_level']; ?></span>
                        <span class="breakdown-value"><?php echo $item['count']; ?> modules</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Warning and Action -->
        <div class="action-section">
            <h2>
                <i class="fas fa-exclamation-triangle"></i>
                Delete All Modules
            </h2>
            
            <div class="warning-box">
                <h3>
                    <i class="fas fa-exclamation-triangle"></i>
                    WARNING: Irreversible Action
                </h3>
                <p><strong>This action will permanently delete:</strong></p>
                <ul>
                    <li>All <?php echo $stats['total_modules']; ?> modules from the database</li>
                    <li>All <?php echo $stats['teacher_assignments']; ?> teacher-module assignments</li>
                    <li>All <?php echo $stats['group_assignments']; ?> module-group assignments</li>
                    <li>All <?php echo $stats['enrollments']; ?> student enrollments</li>
                    <li>All <?php echo $stats['attendance']; ?> attendance records</li>
                </ul>
                <p><strong>This action cannot be undone!</strong> Make sure you have a backup if needed.</p>
            </div>
            
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <?php if ($stats['total_modules'] > 0): ?>
                <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ WARNING ⚠️\n\nYou are about to DELETE ALL MODULES and related data!\n\nThis will remove:\n- <?php echo $stats['total_modules']; ?> modules\n- <?php echo $stats['teacher_assignments']; ?> teacher assignments\n- <?php echo $stats['group_assignments']; ?> group assignments\n- <?php echo $stats['enrollments']; ?> enrollments\n- <?php echo $stats['attendance']; ?> attendance records\n\nThis action CANNOT be undone!\n\nAre you absolutely sure?');">
                    <input type="hidden" name="action" value="clear_modules">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i>
                        Clear All Modules (<?php echo $stats['total_modules']; ?>)
                    </button>
                </form>
                <?php else: ?>
                <button class="btn btn-danger" disabled>
                    <i class="fas fa-trash-alt"></i>
                    No Modules to Clear
                </button>
                <?php endif; ?>
                
                <a href="../module_management/module_management.php" class="btn btn-secondary">
                    <i class="fas fa-book"></i>
                    Manage Modules
                </a>
                
                <a href="../dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>

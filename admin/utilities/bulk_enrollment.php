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

// Handle bulk enrollment action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'bulk_enroll') {
        try {
            $pdo->beginTransaction();
            
            // Get all students with their groups and specialty
            $stmt = $pdo->query("
                SELECT DISTINCT s.id as student_id, s.specialty as student_specialty, sg.id as group_id, sg.year_level
                FROM students s
                JOIN student_group_assignments sga ON s.id = sga.student_id
                JOIN student_groups sg ON sga.group_id = sg.id
            ");
            $student_groups = $stmt->fetchAll();
            
            $enrolled_count = 0;
            $skipped_count = 0;
            
            foreach ($student_groups as $sg) {
                // Get all modules assigned to this student's group with specialty info
                $stmt = $pdo->prepare("
                    SELECT DISTINCT m.id as module_id, m.specialty as module_specialty
                    FROM modules m
                    JOIN module_group_assignments mga ON m.id = mga.module_id
                    WHERE mga.group_id = ? AND m.is_active = 1
                ");
                $stmt->execute([$sg['group_id']]);
                $modules = $stmt->fetchAll();
                
                // Enroll student in each module if specialty matches
                foreach ($modules as $module) {
                    // Check specialty match: enroll if module is 'All' or matches student's specialty
                    $specialty_match = ($module['module_specialty'] === 'All' || 
                                       $module['module_specialty'] === $sg['student_specialty'] ||
                                       strpos($module['module_specialty'], $sg['student_specialty']) !== false);
                    
                    if (!$specialty_match) {
                        continue; // Skip this module, specialty doesn't match
                    }
                    
                    // Check if already enrolled
                    $stmt = $pdo->prepare("
                        SELECT id FROM enrollments 
                        WHERE student_id = ? AND module_id = ?
                    ");
                    $stmt->execute([$sg['student_id'], $module['module_id']]);
                    
                    if (!$stmt->fetch()) {
                        $stmt = $pdo->prepare("
                            INSERT INTO enrollments (student_id, module_id, status) 
                            VALUES (?, ?, 'active')
                        ");
                        $stmt->execute([$sg['student_id'], $module['module_id']]);
                        $enrolled_count++;
                    } else {
                        $skipped_count++;
                    }
                }
            }
            
            $pdo->commit();
            $message = "Successfully created {$enrolled_count} enrollments! Skipped {$skipped_count} existing enrollments.";
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error creating enrollments: ' . $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'clear_enrollments') {
        try {
            $stmt = $pdo->query("DELETE FROM enrollments");
            $deleted = $stmt->rowCount();
            $message = "Deleted {$deleted} enrollments.";
        } catch (PDOException $e) {
            $error = 'Error deleting enrollments: ' . $e->getMessage();
        }
    }
}

// Get statistics
$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) FROM students");
$stats['total_students'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM modules WHERE is_active = 1");
$stats['active_modules'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM enrollments");
$stats['total_enrollments'] = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(DISTINCT student_id) 
    FROM enrollments 
    WHERE status = 'active'
");
$stats['enrolled_students'] = $stmt->fetchColumn();

// Get detailed enrollment data
$stmt = $pdo->query("
    SELECT 
        s.student_number,
        u.full_name,
        sg.group_name,
        COUNT(e.id) as enrollment_count,
        GROUP_CONCAT(m.module_code SEPARATOR ', ') as enrolled_modules
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN student_group_assignments sga ON s.id = sga.student_id
    LEFT JOIN student_groups sg ON sga.group_id = sg.id
    LEFT JOIN enrollments e ON s.id = e.student_id AND e.status = 'active'
    LEFT JOIN modules m ON e.module_id = m.id
    GROUP BY s.id, s.student_number, u.full_name, sg.group_name
    ORDER BY u.full_name
    LIMIT 50
");
$enrollment_details = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Student Enrollment - Admin</title>
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
            max-width: 1400px;
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
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
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
        
        .stat-icon.primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stat-icon.success { background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%); color: white; }
        .stat-icon.warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .stat-icon.info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        
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
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
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
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
        
        .info-box p {
            margin: 0.5rem 0;
            color: #0c5460;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        thead {
            background: #f8f9fa;
        }
        
        th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e9ecef;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            color: #495057;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 0.35rem 0.65rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <?php include '../../includes/admin_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-user-graduate"></i>
                Bulk Student Enrollment
            </h1>
            <p>Automatically enroll students in modules assigned to their groups</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
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
                <div class="stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['active_modules']; ?></div>
                    <div class="stat-label">Active Modules</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['total_enrollments']; ?></div>
                    <div class="stat-label">Total Enrollments</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['enrolled_students']; ?></div>
                    <div class="stat-label">Enrolled Students</div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="action-section">
            <h2>
                <i class="fas fa-bolt"></i>
                Quick Actions
            </h2>
            
            <div class="info-box">
                <p><strong>How it works:</strong></p>
                <p>• The bulk enrollment tool will automatically enroll all students in the modules assigned to their groups</p>
                <p>• Students must be assigned to a group first</p>
                <p>• Only active modules will be enrolled</p>
                <p>• Existing enrollments will be skipped (no duplicates)</p>
            </div>
            
            <div class="action-buttons">
                <form method="POST" style="display: inline;" onsubmit="return confirm('Enroll all students in their group modules?');">
                    <input type="hidden" name="action" value="bulk_enroll">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-users-cog"></i>
                        Bulk Enroll Students
                    </button>
                </form>
                
                <form method="POST" style="display: inline;" onsubmit="return confirm('WARNING: This will delete ALL enrollments! Are you sure?');">
                    <input type="hidden" name="action" value="clear_enrollments">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i>
                        Clear All Enrollments
                    </button>
                </form>
                
                <a href="../student_management/student_management.php" class="btn btn-secondary">
                    <i class="fas fa-users"></i>
                    Manage Students
                </a>
                
                <a href="../module_management/module_management.php" class="btn btn-secondary">
                    <i class="fas fa-book"></i>
                    Manage Modules
                </a>
            </div>
        </div>

        <!-- Enrollment Details -->
        <div class="action-section">
            <h2>
                <i class="fas fa-list"></i>
                Enrollment Status (First 50 Students)
            </h2>
            
            <table>
                <thead>
                    <tr>
                        <th>Student #</th>
                        <th>Name</th>
                        <th>Group</th>
                        <th>Enrollments</th>
                        <th>Enrolled Modules</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollment_details as $detail): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($detail['student_number']); ?></td>
                            <td><?php echo htmlspecialchars($detail['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($detail['group_name'] ?? 'No Group'); ?></td>
                            <td>
                                <?php if ($detail['enrollment_count'] > 0): ?>
                                    <span class="badge badge-success"><?php echo $detail['enrollment_count']; ?> courses</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Not enrolled</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($detail['enrolled_modules'] ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

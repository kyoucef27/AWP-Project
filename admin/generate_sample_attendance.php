<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/unauthorized.php");
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pdo = getDBConnection();
    
    if ($_POST['action'] === 'generate_attendance') {
        try {
            $pdo->beginTransaction();
            
            // Get all active enrollments
            $stmt = $pdo->query("
                SELECT e.id as enrollment_id, e.student_id, e.module_id, 
                       s.student_number, u.full_name, m.module_code, m.module_name
                FROM enrollments e
                JOIN students s ON e.student_id = s.id
                JOIN users u ON s.user_id = u.id
                JOIN modules m ON e.module_id = m.id
                WHERE e.status = 'active'
            ");
            $enrollments = $stmt->fetchAll();
            
            if (empty($enrollments)) {
                throw new Exception("No active enrollments found. Please run bulk enrollment first.");
            }
            
            $attendance_count = 0;
            
            // Generate attendance for past 30 days (excluding weekends)
            for ($i = 30; $i >= 1; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $day_of_week = date('N', strtotime($date)); // 1 = Monday, 7 = Sunday
                
                // Skip weekends
                if ($day_of_week == 6 || $day_of_week == 7) {
                    continue;
                }
                
                // Random chance of classes (not every module every day)
                if (rand(1, 3) == 1) { // 33% chance of class on any given weekday
                    continue;
                }
                
                foreach ($enrollments as $enrollment) {
                    // Check if attendance already exists
                    $stmt = $pdo->prepare("
                        SELECT id FROM attendance 
                        WHERE enrollment_id = ? AND attendance_date = ?
                    ");
                    $stmt->execute([$enrollment['enrollment_id'], $date]);
                    
                    if ($stmt->fetch()) {
                        continue; // Skip if attendance already recorded
                    }
                    
                    // Generate realistic attendance patterns
                    $rand = rand(1, 100);
                    if ($rand <= 85) {
                        $status = 'present';
                        $remarks = null;
                    } elseif ($rand <= 92) {
                        $status = 'late';
                        $remarks = 'Arrived ' . rand(5, 20) . ' minutes late';
                    } else {
                        $status = 'absent';
                        $reasons = [
                            'Sick leave',
                            'Family emergency', 
                            'Medical appointment',
                            'Transportation issues',
                            'Personal reasons'
                        ];
                        $remarks = $reasons[array_rand($reasons)];
                    }
                    
                    // Insert attendance record
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance (enrollment_id, attendance_date, status, remarks, recorded_by)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $enrollment['enrollment_id'],
                        $date,
                        $status,
                        $remarks,
                        $_SESSION['user_id'] // recorded by current admin
                    ]);
                    
                    $attendance_count++;
                }
            }
            
            $pdo->commit();
            $message = "Successfully generated {$attendance_count} attendance records for " . count($enrollments) . " enrollments over the past 30 days.";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error generating attendance: ' . $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'clear_attendance') {
        try {
            $pdo->beginTransaction();
            
            // Clear justifications first (foreign key constraint)
            $stmt = $pdo->query("DELETE FROM absence_justifications");
            $justifications_deleted = $stmt->rowCount();
            
            // Clear attendance records
            $stmt = $pdo->query("DELETE FROM attendance");
            $attendance_deleted = $stmt->rowCount();
            
            $pdo->commit();
            $message = "Cleared {$attendance_deleted} attendance records and {$justifications_deleted} justifications.";
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error clearing attendance: ' . $e->getMessage();
        }
    }
}

// Get statistics
try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'active'");
    $total_enrollments = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM attendance");
    $total_attendance = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM absence_justifications");
    $total_justifications = $stmt->fetchColumn();
    
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM attendance 
        GROUP BY status 
        ORDER BY count DESC
    ");
    $status_breakdown = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $total_enrollments = 0;
    $total_attendance = 0;
    $total_justifications = 0;
    $status_breakdown = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Sample Attendance - Admin</title>
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
            max-width: 900px;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            border-left: 4px solid #667eea;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6c757d;
            font-weight: 500;
        }
        
        .breakdown {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .breakdown h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .breakdown-item:last-child {
            border-bottom: none;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.present { background: #d4edda; color: #155724; }
        .status-badge.absent { background: #f8d7da; color: #721c24; }
        .status-badge.late { background: #fff3cd; color: #856404; }
        .status-badge.excused { background: #d1ecf1; color: #0c5460; }
        
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
        
        .btn-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <i class="fas fa-calendar-check"></i>
            <h1>Generate Sample Attendance</h1>
            <p class="subtitle">Create realistic attendance records for testing</p>
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

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_enrollments; ?></div>
                <div class="stat-label">Active Enrollments</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_attendance; ?></div>
                <div class="stat-label">Attendance Records</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_justifications; ?></div>
                <div class="stat-label">Justifications</div>
            </div>
        </div>

        <?php if (!empty($status_breakdown)): ?>
        <div class="breakdown">
            <h3><i class="fas fa-chart-pie"></i> Attendance Status Breakdown</h3>
            <?php foreach ($status_breakdown as $status): ?>
            <div class="breakdown-item">
                <span class="status-badge <?php echo $status['status']; ?>">
                    <?php echo ucfirst($status['status']); ?>
                </span>
                <span><?php echo $status['count']; ?> records</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="info-box">
            <h3><i class="fas fa-info-circle"></i> About Sample Attendance Generation</h3>
            <p><strong>This tool generates realistic attendance data:</strong></p>
            <ul>
                <li>Creates records for the past 30 weekdays</li>
                <li>Realistic patterns: 85% present, 7% late, 8% absent</li>
                <li>Skips weekends and some random days (no classes every day)</li>
                <li>Adds realistic remarks for late arrivals and absences</li>
                <li>Only creates records for active enrollments</li>
                <li>Skips dates that already have attendance records</li>
            </ul>
            <p><strong>Note:</strong> You need active enrollments first. Run bulk enrollment if needed.</p>
        </div>

        <div class="btn-group">
            <?php if ($total_enrollments > 0): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="generate_attendance">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Generate sample attendance records?');">
                        <i class="fas fa-magic"></i>
                        Generate Sample Attendance
                    </button>
                </form>
            <?php else: ?>
                <button class="btn btn-primary" disabled>
                    <i class="fas fa-exclamation-triangle"></i>
                    No Enrollments Found
                </button>
            <?php endif; ?>
            
            <?php if ($total_attendance > 0): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="clear_attendance">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('WARNING: This will delete ALL attendance records and justifications! Are you sure?');">
                        <i class="fas fa-trash"></i>
                        Clear All Attendance
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="btn-group" style="margin-top: 1rem;">
            <a href="utilities/bulk_enrollment.php" class="btn btn-secondary">
                <i class="fas fa-user-plus"></i>
                Bulk Enrollment
            </a>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-home"></i>
                Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
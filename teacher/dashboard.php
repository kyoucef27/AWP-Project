<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../auth/login.php");
    exit();
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get teacher record
try {
    $stmt = $pdo->prepare("SELECT t.id, u.full_name FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.user_id = ?");
    $stmt->execute([$user_id]);
    $teacher_record = $stmt->fetch();
    
    if (!$teacher_record) {
        die("Teacher profile not found. Please contact an administrator.");
    }
    
    $teacher_id = $teacher_record['id'];
    $teacher_name = $teacher_record['full_name'];
} catch (PDOException $e) {
    error_log("Error fetching teacher: " . $e->getMessage());
    die("Error loading teacher profile");
}

// Check if teaching_sessions table exists, create if not
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'teaching_sessions'");
    if ($stmt->rowCount() == 0) {
        // Create teaching_sessions table
        $sql = "
        CREATE TABLE teaching_sessions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            module_id INT NOT NULL,
            session_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            session_type ENUM('Lecture', 'Lab', 'Tutorial', 'Exam', 'Workshop') DEFAULT 'Lecture',
            location VARCHAR(100) NULL,
            description TEXT NULL,
            attendance_taken BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
            FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
            INDEX idx_teacher_date (teacher_id, session_date),
            INDEX idx_module_date (module_id, session_date)
        )";
        $pdo->exec($sql);
    }
    
    // Update attendance table to include session_id if it doesn't exist
    $stmt = $pdo->query("DESCRIBE attendance");
    $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('session_id', $columns)) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN session_id INT NULL AFTER enrollment_id");
        $pdo->exec("ALTER TABLE attendance ADD FOREIGN KEY (session_id) REFERENCES teaching_sessions(id) ON DELETE SET NULL");
    }
} catch (PDOException $e) {
    error_log("Error setting up teaching tables: " . $e->getMessage());
}

// Get teacher's assigned modules with session counts
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.module_code,
            m.module_name,
            m.credits,
            tm.role as teaching_role,
            COUNT(DISTINCT e.student_id) as enrolled_students,
            COUNT(DISTINCT ts.id) as total_sessions,
            SUM(CASE WHEN ts.session_date = CURDATE() THEN 1 ELSE 0 END) as today_sessions,
            SUM(CASE WHEN ts.attendance_taken = 1 THEN 1 ELSE 0 END) as completed_sessions
        FROM teacher_modules tm
        JOIN modules m ON tm.module_id = m.id
        LEFT JOIN enrollments e ON m.id = e.module_id AND e.status = 'active'
        LEFT JOIN teaching_sessions ts ON m.id = ts.module_id AND ts.teacher_id = ?
        WHERE tm.teacher_id = ? AND m.is_active = 1
        GROUP BY m.id, m.module_code, m.module_name, m.credits, tm.role
        ORDER BY m.module_code
    ");
    $stmt->execute([$teacher_id, $teacher_id]);
    $modules = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching modules: " . $e->getMessage());
    $modules = [];
}

// Get recent sessions (last 7 days)
try {
    $stmt = $pdo->prepare("
        SELECT 
            ts.*,
            m.module_code,
            m.module_name,
            COUNT(DISTINCT e.student_id) as enrolled_count,
            COUNT(DISTINCT a.id) as attendance_count
        FROM teaching_sessions ts
        JOIN modules m ON ts.module_id = m.id
        LEFT JOIN enrollments e ON m.id = e.module_id AND e.status = 'active'
        LEFT JOIN attendance a ON ts.id = a.session_id
        WHERE ts.teacher_id = ? AND ts.session_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY ts.id
        ORDER BY ts.session_date DESC, ts.start_time DESC
        LIMIT 10
    ");
    $stmt->execute([$teacher_id]);
    $recent_sessions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching recent sessions: " . $e->getMessage());
    $recent_sessions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - PAW Project</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Teacher Dashboard Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #2c3e50;
            line-height: 1.6;
        }
        
        .teacher-navbar {
            background: linear-gradient(135deg, #2c5aa0 0%, #1e3a8a 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar-brand a {
            color: white;
            text-decoration: none;
            font-size: 1.5rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .navbar-menu {
            display: flex;
            gap: 2rem;
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.2);
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logout-btn {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border: 1px solid white;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: white;
            color: #2c5aa0;
        }
        
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-card:nth-child(1) .stat-icon { background: #3b82f6; }
        .stat-card:nth-child(2) .stat-icon { background: #10b981; }
        .stat-card:nth-child(3) .stat-icon { background: #f59e0b; }
        .stat-card:nth-child(4) .stat-icon { background: #8b5cf6; }
        
        .stat-content h3 {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .stat-content p {
            color: #6b7280;
            font-weight: 500;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .section-header h2 {
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-outline {
            background: transparent;
            color: #3b82f6;
            border: 2px solid #3b82f6;
        }
        
        .btn-outline:hover {
            background: #3b82f6;
            color: white;
        }
        
        .modules-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .module-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .module-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .module-header h3 {
            color: #2c3e50;
            font-size: 1.25rem;
        }
        
        .credits {
            background: #e5e7eb;
            color: #6b7280;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
        }
        
        .module-title {
            color: #6b7280;
            margin-bottom: 1rem;
        }
        
        .module-stats {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .module-actions {
            display: flex;
            gap: 1rem;
        }
        
        .sessions-list {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .session-item {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .session-item:last-child {
            border-bottom: none;
        }
        
        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.25rem;
        }
        
        .session-date {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .session-details {
            display: flex;
            gap: 1rem;
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .session-status {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.25rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .status-badge.completed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }
        
        .text-link {
            color: #3b82f6;
            text-decoration: none;
        }
        
        .text-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .navbar-menu {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .section-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .module-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <nav class="teacher-navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <a href="dashboard.php">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>PAW Teacher</span>
                </a>
            </div>
            
            <div class="navbar-menu">
                <a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <a href="sessions.php" class="nav-link">
                    <i class="fas fa-calendar-alt"></i>
                    Sessions
                </a>
                <a href="attendance_summary.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    Attendance Reports
                </a>
            </div>
            
            <div class="user-menu">
                <span class="user-name"><?php echo htmlspecialchars($teacher_name); ?></span>
                <a href="../auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="page-header">
            <h1><i class="fas fa-home"></i> Teacher Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($teacher_name); ?>!</p>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo count($modules); ?></h3>
                    <p>Assigned Modules</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo array_sum(array_column($modules, 'today_sessions')); ?></h3>
                    <p>Sessions Today</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo array_sum(array_column($modules, 'enrolled_students')); ?></h3>
                    <p>Total Students</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo array_sum(array_column($modules, 'completed_sessions')); ?></h3>
                    <p>Completed Sessions</p>
                </div>
            </div>
        </div>

        <!-- Modules and Sessions -->
        <div class="content-grid">
            <div class="modules-section">
                <div class="section-header">
                    <h2><i class="fas fa-book"></i> My Modules</h2>
                    <a href="sessions.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Create Session
                    </a>
                </div>
                
                <?php if (empty($modules)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>No Modules Assigned</h3>
                    <p>You haven't been assigned any modules yet. Contact the administrator.</p>
                </div>
                <?php else: ?>
                <div class="modules-grid">
                    <?php foreach ($modules as $module): ?>
                    <div class="module-card">
                        <div class="module-header">
                            <h3><?php echo htmlspecialchars($module['module_code']); ?></h3>
                            <span class="credits"><?php echo $module['credits']; ?> credits</span>
                        </div>
                        
                        <div class="module-title">
                            <?php echo htmlspecialchars($module['module_name']); ?>
                        </div>
                        
                        <div class="module-stats">
                            <div class="stat-item">
                                <i class="fas fa-users"></i>
                                <span><?php echo $module['enrolled_students']; ?> students</span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo $module['total_sessions']; ?> sessions</span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-check-circle"></i>
                                <span><?php echo $module['completed_sessions']; ?> completed</span>
                            </div>
                        </div>
                        
                        <div class="module-actions">
                            <a href="sessions.php?module_id=<?php echo $module['id']; ?>" class="btn btn-outline">
                                <i class="fas fa-calendar-alt"></i>
                                View Sessions
                            </a>
                            <a href="mark_attendance.php?module_id=<?php echo $module['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-check"></i>
                                Take Attendance
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="recent-section">
                <div class="section-header">
                    <h2><i class="fas fa-clock"></i> Recent Sessions</h2>
                    <a href="sessions.php" class="text-link">View All</a>
                </div>
                
                <?php if (empty($recent_sessions)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Recent Sessions</h3>
                    <p>No sessions in the last 7 days.</p>
                </div>
                <?php else: ?>
                <div class="sessions-list">
                    <?php foreach ($recent_sessions as $session): ?>
                    <div class="session-item">
                        <div class="session-info">
                            <div class="session-header">
                                <strong><?php echo htmlspecialchars($session['module_code']); ?></strong>
                                <span class="session-date"><?php echo date('M j', strtotime($session['session_date'])); ?></span>
                            </div>
                            <div class="session-details">
                                <span class="session-time">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('H:i', strtotime($session['start_time'])); ?> - <?php echo date('H:i', strtotime($session['end_time'])); ?>
                                </span>
                                <span class="session-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($session['location'] ?: 'TBA'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="session-status">
                            <?php if ($session['attendance_taken']): ?>
                                <span class="status-badge completed">
                                    <i class="fas fa-check"></i>
                                    Completed
                                </span>
                                <small><?php echo $session['attendance_count']; ?>/<?php echo $session['enrolled_count']; ?> attended</small>
                            <?php else: ?>
                                <a href="mark_attendance.php?session_id=<?php echo $session['id']; ?>" class="btn btn-sm">
                                    <i class="fas fa-check"></i>
                                    Take Attendance
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
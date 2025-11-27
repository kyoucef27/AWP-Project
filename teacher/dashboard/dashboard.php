<?php
session_start();
require_once '../../includes/config.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../auth/login.php");
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
    <link rel="stylesheet" href="dashboard.css">
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
                <a href="../sessions/sessions.php" class="nav-link">
                    <i class="fas fa-calendar-alt"></i>
                    Sessions
                </a>
                <a href="../attendance/attendance_summary.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    Attendance Reports
                </a>
            </div>
            
            <div class="user-menu">
                <span class="user-name"><?php echo htmlspecialchars($teacher_name); ?></span>
                <a href="../../auth/logout.php" class="logout-btn">
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
                    <a href="../sessions/sessions.php" class="btn btn-primary">
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
                            <a href="../sessions/sessions.php?module_id=<?php echo $module['id']; ?>" class="btn btn-outline">
                                <i class="fas fa-calendar-alt"></i>
                                View Sessions
                            </a>
                            <a href="../attendance/mark_attendance.php?module_id=<?php echo $module['id']; ?>" class="btn btn-primary">
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
                    <a href="../sessions/sessions.php" class="text-link">View All</a>
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
                                <a href="../attendance/mark_attendance.php?session_id=<?php echo $session['id']; ?>" class="btn btn-sm">
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
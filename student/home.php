<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$pdo = getDBConnection();

// Get user ID from session and fetch student record
$user_id = $_SESSION['user_id'];

// Fetch student info and get student ID
try {
    $stmt = $pdo->prepare("
        SELECT s.*, u.username, u.email, u.full_name 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        die("Student profile not found. Please contact an administrator.");
    }
    
    // Get the actual student ID from the students table
    $student_id = $student['id'];
} catch (PDOException $e) {
    error_log("Error fetching student: " . $e->getMessage());
    die("Error loading student profile");
}

// Fetch enrolled courses with attendance statistics
// Note: This now shows modules assigned to the student's group
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.id as module_id,
            m.module_name as module_name,
            m.module_code as module_code,
            m.description,
            m.credits,
            e.enrollment_date,
            e.status as enrollment_status,
            e.grade,
            u.full_name as teacher_name,
            COUNT(DISTINCT a.id) as total_sessions,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count
        FROM enrollments e
        JOIN modules m ON e.module_id = m.id
        LEFT JOIN teacher_modules tm ON m.id = tm.module_id AND tm.role = 'Lecturer'
        LEFT JOIN teachers t ON tm.teacher_id = t.id
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN attendance a ON a.enrollment_id = e.id
        WHERE e.student_id = ? AND e.status IN ('active', 'completed')
        GROUP BY m.id, m.module_name, m.module_code, m.description, m.credits, e.enrollment_date, e.status, e.grade, u.full_name
        ORDER BY e.enrollment_date DESC
    ");
    $stmt->execute([$student_id]);
    $enrolled_courses = $stmt->fetchAll();
    
    // Calculate attendance percentage for each course
    foreach ($enrolled_courses as &$course) {
        $total = $course['total_sessions'];
        if ($total > 0) {
            $course['attendance_percentage'] = round(($course['present_count'] / $total) * 100, 1);
        } else {
            $course['attendance_percentage'] = 0;
        }
    }
    
} catch (PDOException $e) {
    error_log("Error fetching enrollments: " . $e->getMessage());
    $enrolled_courses = [];
}

// Calculate overall statistics
$total_enrolled = count($enrolled_courses);
$total_sessions_all = array_sum(array_column($enrolled_courses, 'total_sessions'));
$total_present_all = array_sum(array_column($enrolled_courses, 'present_count'));
$overall_attendance = $total_sessions_all > 0 ? round(($total_present_all / $total_sessions_all) * 100, 1) : 0;

// Count pending justifications
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM absence_justifications aj
        JOIN attendance a ON aj.attendance_id = a.id
        WHERE aj.student_id = ? AND aj.status = 'pending'
    ");
    $stmt->execute([$student_id]);
    $pending_justifications = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error counting justifications: " . $e->getMessage());
    $pending_justifications = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - PAW Project</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="home.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
                <span>Student Portal</span>
            </div>
            <div class="nav-links">
                <a href="home.php" class="nav-link active">
                    <i class="fas fa-home"></i>
                    <span>My Courses</span>
                </a>
                <a href="attendance.php" class="nav-link">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>
                <a href="profile.php" class="nav-link">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
            </div>
            <div class="user-section">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($student['full_name']); ?></span>
                </div>
                <a href="../auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <h1 class="page-title">
                    <i class="fas fa-book-open"></i>
                    My Enrolled Courses
                </h1>
                <p class="page-subtitle">View your course enrollment and track your attendance progress</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $total_enrolled; ?></div>
                    <div class="stat-label">Enrolled Courses</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon <?php echo $overall_attendance >= 90 ? 'success' : ($overall_attendance >= 75 ? 'warning' : 'danger'); ?>">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $overall_attendance; ?>%</div>
                    <div class="stat-label">Overall Attendance</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $total_sessions_all; ?></div>
                    <div class="stat-label">Total Sessions</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $pending_justifications; ?></div>
                    <div class="stat-label">Pending Justifications</div>
                </div>
            </div>
        </div>

        <!-- Course Cards -->
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-list"></i>
                Course List
            </h2>
            <?php if ($total_enrolled > 0): ?>
                <div class="section-meta">
                    <?php echo $total_enrolled; ?> course<?php echo $total_enrolled !== 1 ? 's' : ''; ?> enrolled
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($enrolled_courses)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <h3>No Enrolled Courses</h3>
                <p>You are not currently enrolled in any courses. Please contact your academic advisor for course enrollment assistance.</p>
            </div>
        <?php else: ?>
            <div class="course-grid">
                <?php foreach ($enrolled_courses as $course): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <div class="course-badge">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="course-title-section">
                                <h3 class="course-title"><?php echo htmlspecialchars($course['module_name']); ?></h3>
                                <div class="course-code"><?php echo htmlspecialchars($course['module_code']); ?></div>
                            </div>
                            <div class="course-status <?php echo strtolower($course['enrollment_status']); ?>">
                                <?php echo ucfirst($course['enrollment_status']); ?>
                            </div>
                        </div>

                        <?php if ($course['description']): ?>
                            <p class="course-description"><?php echo htmlspecialchars($course['description']); ?></p>
                        <?php endif; ?>

                        <div class="course-meta-grid">
                            <div class="meta-item">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <span><?php echo htmlspecialchars($course['teacher_name'] ?? 'Not assigned'); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-award"></i>
                                <span><?php echo htmlspecialchars($course['credits']); ?> Credits</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span>Enrolled: <?php echo date('M j, Y', strtotime($course['enrollment_date'])); ?></span>
                            </div>
                            <?php if ($course['grade']): ?>
                                <div class="meta-item">
                                    <i class="fas fa-graduation-cap"></i>
                                    <span>Grade: <?php echo htmlspecialchars($course['grade']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="attendance-section">
                            <div class="attendance-header">
                                <span class="attendance-label">Attendance Progress</span>
                                <span class="attendance-percentage <?php 
                                    echo $course['attendance_percentage'] >= 90 ? 'excellent' : 
                                        ($course['attendance_percentage'] >= 75 ? 'good' : 'poor'); 
                                ?>">
                                    <?php echo $course['attendance_percentage']; ?>%
                                </span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill <?php 
                                    echo $course['attendance_percentage'] >= 90 ? 'excellent' : 
                                        ($course['attendance_percentage'] >= 75 ? 'good' : 'poor'); 
                                ?>" style="width: <?php echo $course['attendance_percentage']; ?>%"></div>
                            </div>
                            <div class="attendance-stats">
                                <div class="stat-item present">
                                    <i class="fas fa-check-circle"></i>
                                    <span><?php echo $course['present_count']; ?> Present</span>
                                </div>
                                <div class="stat-item absent">
                                    <i class="fas fa-times-circle"></i>
                                    <span><?php echo $course['absent_count']; ?> Absent</span>
                                </div>
                                <div class="stat-item late">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo $course['late_count']; ?> Late</span>
                                </div>
                                <div class="stat-item total">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span><?php echo $course['total_sessions']; ?> Total</span>
                                </div>
                            </div>
                        </div>

                        <div class="course-actions">
                            <a href="attendance.php?module_id=<?php echo $course['module_id']; ?>" class="btn btn-primary">
                                <i class="fas fa-chart-bar"></i>
                                View Attendance
                            </a>
                            <?php if ($course['absent_count'] > 0): ?>
                                <a href="attendance.php?module_id=<?php echo $course['module_id']; ?>#justifications" class="btn btn-secondary">
                                    <i class="fas fa-file-upload"></i>
                                    Submit Justification
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

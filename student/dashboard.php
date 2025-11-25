<?php
session_start();

// Check if user is logged in and is student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$user = $_SESSION;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - PAW Project</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 0;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .welcome-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .feature-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 2rem;
            text-align: center;
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 1rem;
            transition: background 0.3s ease;
        }
        
        .btn:hover {
            background: #764ba2;
        }
        
        .logout-btn {
            background: #e74c3c;
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo">🎓 Student Portal</div>
            <div>
                <span>Welcome, <?php echo htmlspecialchars($user['username']); ?></span>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="welcome-card">
            <h1>👨‍🎓 Student Dashboard</h1>
            <p>Welcome to your student portal, <?php echo htmlspecialchars($user['username']); ?>!</p>
            <p><strong>Role:</strong> <?php echo htmlspecialchars($user['role']); ?></p>
        </div>

        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>View Attendance</h3>
                <p>Check your attendance records and statistics</p>
                <a href="#" class="btn">Coming Soon</a>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📝</div>
                <h3>Submit Justifications</h3>
                <p>Submit justifications for absences</p>
                <a href="#" class="btn">Coming Soon</a>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📚</div>
                <h3>Course Information</h3>
                <p>View your enrolled courses and schedules</p>
                <a href="#" class="btn">Coming Soon</a>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">🔧</div>
                <h3>System Tools</h3>
                <p>Access system status and debug tools</p>
                <a href="../wamp_status.php" class="btn">System Status</a>
            </div>
        </div>
    </div>
</body>
</html>
        ORDER BY as_.session_date DESC, as_.start_time DESC
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get upcoming sessions (next 7 days)
    $stmt = $pdo->prepare("
        SELECT as_.*, c.name as course_name, c.code as course_code, g.name as group_name
        FROM attendance_sessions as_
        JOIN courses c ON as_.course_id = c.id
        JOIN groups g ON as_.group_id = g.id
        JOIN course_enrollments ce ON (c.id = ce.course_id AND ce.student_id = ?)
        WHERE as_.session_date >= CURDATE() 
        AND as_.session_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND as_.status IN ('scheduled', 'open', 'active')
        ORDER BY as_.session_date ASC, as_.start_time ASC
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $upcoming_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get student's justifications
    $stmt = $pdo->prepare("
        SELECT j.*, ar.status as attendance_status, as_.session_date, 
               c.name as course_name, c.code as course_code
        FROM justifications j
        JOIN attendance_records ar ON j.attendance_record_id = ar.id
        JOIN attendance_sessions as_ ON ar.session_id = as_.id
        JOIN courses c ON as_.course_id = c.id
        WHERE ar.student_id = ?
        ORDER BY j.submitted_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $justifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $stats = [];
    
    // Total courses enrolled
    $stats['total_courses'] = count($courses);
    
    // Overall attendance rate
    $total_sessions = array_sum(array_column($courses, 'total_sessions'));
    $total_present = array_sum(array_column($courses, 'present_count'));
    $stats['overall_attendance'] = $total_sessions > 0 ? round(($total_present / $total_sessions) * 100, 1) : 0;
    
    // Total sessions attended
    $stats['total_attended'] = $total_present;
    
    // Pending justifications
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM justifications j
        JOIN attendance_records ar ON j.attendance_record_id = ar.id
        WHERE ar.student_id = ? AND j.status = 'pending'
    ");
    $stmt->execute([$user['id']]);
    $stats['pending_justifications'] = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Student dashboard error: " . $e->getMessage());
    $courses = [];
    $recent_attendance = [];
    $upcoming_sessions = [];
    $justifications = [];
    $stats = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Algiers University Attendance System</title>
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
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
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
        
        .logo {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            transition: background 0.3s;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .dropdown {
            position: relative;
        }
        
        .dropdown-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            min-width: 200px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-radius: 8px;
            overflow: hidden;
            z-index: 1000;
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        .dropdown-item {
            display: block;
            padding: 12px 16px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .dropdown-item:hover {
            background: #f8f9fa;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 0.5rem;
            font-weight: 300;
        }
        
        .breadcrumb {
            color: #666;
            font-size: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-left: 5px solid;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.courses { border-left-color: #3498db; }
        .stat-card.attendance { border-left-color: #2ecc71; }
        .stat-card.sessions { border-left-color: #f39c12; }
        .stat-card.justifications { border-left-color: #e74c3c; }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .main-content, .sidebar {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .course-grid {
            display: grid;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .course-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            border-left: 4px solid #3498db;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .course-info h4 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .course-meta {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .attendance-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .attendance-progress {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .attendance-excellent { background: #28a745; }
        .attendance-good { background: #ffc107; }
        .attendance-poor { background: #dc3545; }
        
        .attendance-percentage {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .attendance-excellent-text { color: #28a745; }
        .attendance-good-text { color: #ffc107; }
        .attendance-poor-text { color: #dc3545; }
        
        .record-list {
            list-style: none;
        }
        
        .record-item {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .record-item:last-child {
            border-bottom: none;
        }
        
        .record-info {
            flex: 1;
        }
        
        .record-title {
            font-weight: 500;
            margin-bottom: 0.25rem;
            color: #333;
        }
        
        .record-meta {
            font-size: 0.875rem;
            color: #666;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-present { background: #d4edda; color: #155724; }
        .status-absent { background: #f8d7da; color: #721c24; }
        .status-late { background: #fff3cd; color: #856404; }
        .status-justified { background: #cce5ff; color: #004085; }
        
        .session-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #3498db;
        }
        
        .session-title {
            font-weight: 500;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .session-meta {
            color: #666;
            font-size: 0.9rem;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1.5rem;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .action-btn:hover {
            border-color: #3498db;
            background: #e3f2fd;
            transform: translateY(-2px);
        }
        
        .action-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #3498db;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .course-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .nav-links {
                display: none;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo">
                👨‍🎓 AUAS Student
            </div>
            <div class="nav-links">
                <a href="#" class="nav-link">📚 My Courses</a>
                <a href="#" class="nav-link">📊 Attendance</a>
                <a href="#" class="nav-link">📝 Justifications</a>
            </div>
            <div class="user-info">
                <span><?php echo htmlspecialchars($user['first_name']); ?></span>
                <div class="dropdown">
                    <button class="dropdown-btn">
                        ⚙️ Menu ▼
                    </button>
                    <div class="dropdown-content">
                        <a href="#" class="dropdown-item">👤 Profile</a>
                        <a href="#" class="dropdown-item">📋 Schedule</a>
                        <a href="#" class="dropdown-item">📈 My Progress</a>
                        <a href="../auth/logout.php" class="dropdown-item">🚪 Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>📊 Student Dashboard</h1>
            <div class="breadcrumb">
                Home / Dashboard / Student / <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card courses">
                <div class="stat-icon">📚</div>
                <div class="stat-number"><?php echo $stats['total_courses'] ?? 0; ?></div>
                <div class="stat-label">Enrolled Courses</div>
            </div>
            
            <div class="stat-card attendance">
                <div class="stat-icon">
                    <?php
                    $attendance_rate = $stats['overall_attendance'] ?? 0;
                    echo $attendance_rate >= 90 ? '🟢' : ($attendance_rate >= 75 ? '🟡' : '🔴');
                    ?>
                </div>
                <div class="stat-number"><?php echo $stats['overall_attendance'] ?? 0; ?>%</div>
                <div class="stat-label">Overall Attendance</div>
            </div>
            
            <div class="stat-card sessions">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo $stats['total_attended'] ?? 0; ?></div>
                <div class="stat-label">Sessions Attended</div>
            </div>
            
            <div class="stat-card justifications">
                <div class="stat-icon">📋</div>
                <div class="stat-number"><?php echo $stats['pending_justifications'] ?? 0; ?></div>
                <div class="stat-label">Pending Justifications</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="main-content">
                <h2 class="section-title">⚡ Quick Actions</h2>
                <div class="quick-actions">
                    <a href="view_attendance.php" class="action-btn">
                        <div class="action-icon">📊</div>
                        <div>View Attendance</div>
                    </a>
                    <a href="submit_justification.php" class="action-btn">
                        <div class="action-icon">📝</div>
                        <div>Submit Justification</div>
                    </a>
                    <a href="course_schedule.php" class="action-btn">
                        <div class="action-icon">📅</div>
                        <div>Course Schedule</div>
                    </a>
                    <a href="progress_report.php" class="action-btn">
                        <div class="action-icon">📈</div>
                        <div>Progress Report</div>
                    </a>
                </div>

                <h2 class="section-title">📚 My Courses</h2>
                <div class="course-grid">
                    <?php if (empty($courses)): ?>
                        <div class="course-card">
                            <div class="course-info">
                                <h4>No courses enrolled</h4>
                                <div class="course-meta">Contact your academic advisor for course enrollment</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($courses as $course): ?>
                            <div class="course-card">
                                <div class="course-info">
                                    <h4><?php echo htmlspecialchars($course['name']); ?></h4>
                                    <div class="course-meta">
                                        Code: <?php echo htmlspecialchars($course['code']); ?> |
                                        Sessions: <?php echo $course['total_sessions']; ?> |
                                        Attended: <?php echo $course['attended_sessions']; ?>
                                    </div>
                                    <div class="attendance-bar">
                                        <div class="attendance-progress <?php 
                                            echo $course['attendance_percentage'] >= 90 ? 'attendance-excellent' : 
                                                ($course['attendance_percentage'] >= 75 ? 'attendance-good' : 'attendance-poor');
                                        ?>" style="width: <?php echo $course['attendance_percentage']; ?>%"></div>
                                    </div>
                                </div>
                                <div class="attendance-percentage <?php 
                                    echo $course['attendance_percentage'] >= 90 ? 'attendance-excellent-text' : 
                                        ($course['attendance_percentage'] >= 75 ? 'attendance-good-text' : 'attendance-poor-text');
                                ?>">
                                    <?php echo $course['attendance_percentage']; ?>%
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sidebar">
                <h3 class="section-title">📅 Upcoming Sessions</h3>
                <?php if (empty($upcoming_sessions)): ?>
                    <p style="color: #666; text-align: center; padding: 2rem;">No upcoming sessions</p>
                <?php else: ?>
                    <?php foreach ($upcoming_sessions as $session): ?>
                        <div class="session-item">
                            <div class="session-title">
                                <?php echo htmlspecialchars($session['course_name']); ?>
                            </div>
                            <div class="session-meta">
                                <?php echo date('M j, Y', strtotime($session['session_date'])); ?> at 
                                <?php echo date('H:i', strtotime($session['start_time'])); ?><br>
                                Group: <?php echo htmlspecialchars($session['group_name']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <h3 class="section-title" style="margin-top: 2rem;">📝 Recent Attendance</h3>
                <?php if (empty($recent_attendance)): ?>
                    <p style="color: #666; text-align: center; padding: 1rem;">No attendance records</p>
                <?php else: ?>
                    <ul class="record-list">
                        <?php foreach ($recent_attendance as $record): ?>
                            <li class="record-item">
                                <div class="record-info">
                                    <div class="record-title">
                                        <?php echo htmlspecialchars($record['course_name']); ?>
                                    </div>
                                    <div class="record-meta">
                                        <?php echo date('M j, Y', strtotime($record['session_date'])); ?>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo $record['status']; ?>">
                                    <?php echo ucfirst($record['status']); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
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

// Get filter parameters
$filter_module = isset($_GET['module_id']) ? $_GET['module_id'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_specialty = isset($_GET['specialty']) ? $_GET['specialty'] : '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';

// Get teacher's assigned modules for dropdown - only modules they actually teach
try {
    $stmt = $pdo->prepare("
        SELECT m.id, m.module_code, m.module_name, tm.role
        FROM teacher_modules tm 
        JOIN modules m ON tm.module_id = m.id 
        WHERE tm.teacher_id = ? AND m.is_active = 1 
        ORDER BY m.module_code
    ");
    $stmt->execute([$teacher_id]);
    $teacher_modules = $stmt->fetchAll();
    
    // Additional security check - verify teacher is actually assigned to selected module
    if ($filter_module) {
        $stmt = $pdo->prepare("SELECT tm.id FROM teacher_modules tm WHERE tm.module_id = ? AND tm.teacher_id = ?");
        $stmt->execute([$filter_module, $teacher_id]);
        if (!$stmt->fetch()) {
            $filter_module = ''; // Reset if teacher doesn't have access
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching teacher modules: " . $e->getMessage());
    $teacher_modules = [];
}

// Get specialties for filter
try {
    $stmt = $pdo->query("SELECT DISTINCT specialty FROM students WHERE specialty IS NOT NULL AND specialty != '' ORDER BY specialty");
    $specialties = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching specialties: " . $e->getMessage());
    $specialties = [];
}

// Build attendance query based on report type
$reports = [];
$summary_stats = [];

if ($report_type === 'summary' && $filter_module) {
    // Student attendance summary
    $where_clauses = ["m.teacher_id = ?", "m.id = ?"];
    $params = [$teacher_id, $filter_module];
    
    if ($filter_date_from) {
        $where_clauses[] = "ts.session_date >= ?";
        $params[] = $filter_date_from;
    }
    
    if ($filter_date_to) {
        $where_clauses[] = "ts.session_date <= ?";
        $params[] = $filter_date_to;
    }
    
    if ($filter_specialty) {
        $where_clauses[] = "s.specialty = ?";
        $params[] = $filter_specialty;
    }
    
    $where_sql = implode(" AND ", $where_clauses);
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                s.id as student_id,
                s.student_id as student_number,
                u.full_name as student_name,
                s.specialty,
                COUNT(DISTINCT ts.id) as total_sessions,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(DISTINCT ts.id)), 1) as attendance_percentage
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN enrollments e ON s.id = e.student_id
            JOIN modules m ON e.module_id = m.id
            LEFT JOIN teaching_sessions ts ON m.id = ts.module_id AND ts.teacher_id = ? AND ts.attendance_taken = 1
            LEFT JOIN attendance a ON e.id = a.enrollment_id AND a.session_id = ts.id
            WHERE $where_sql AND e.status = 'active'
            GROUP BY s.id, s.student_id, u.full_name, s.specialty
            HAVING total_sessions > 0
            ORDER BY u.full_name
        ");
        
        array_unshift($params, $teacher_id);
        $stmt->execute($params);
        $reports = $stmt->fetchAll();
        
        // Calculate summary statistics
        if (!empty($reports)) {
            $summary_stats = [
                'total_students' => count($reports),
                'avg_attendance' => round(array_sum(array_column($reports, 'attendance_percentage')) / count($reports), 1),
                'total_sessions' => $reports[0]['total_sessions'] ?? 0,
                'high_attendance' => count(array_filter($reports, function($r) { return $r['attendance_percentage'] >= 90; })),
                'low_attendance' => count(array_filter($reports, function($r) { return $r['attendance_percentage'] < 70; }))
            ];
        }
        
    } catch (PDOException $e) {
        error_log("Error fetching attendance summary: " . $e->getMessage());
    }
    
} elseif ($report_type === 'sessions' && $filter_module) {
    // Session-wise attendance
    $where_clauses = ["ts.teacher_id = ?", "ts.module_id = ?"];
    $params = [$teacher_id, $filter_module];
    
    if ($filter_date_from) {
        $where_clauses[] = "ts.session_date >= ?";
        $params[] = $filter_date_from;
    }
    
    if ($filter_date_to) {
        $where_clauses[] = "ts.session_date <= ?";
        $params[] = $filter_date_to;
    }
    
    $where_sql = implode(" AND ", $where_clauses);
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                ts.*,
                m.module_code,
                m.module_name,
                COUNT(DISTINCT e.student_id) as enrolled_count,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(DISTINCT e.student_id)), 1) as attendance_percentage
            FROM teaching_sessions ts
            JOIN modules m ON ts.module_id = m.id
            LEFT JOIN enrollments e ON m.id = e.module_id AND e.status = 'active'
            LEFT JOIN attendance a ON ts.id = a.session_id AND a.enrollment_id = e.id
            WHERE $where_sql AND ts.attendance_taken = 1
            GROUP BY ts.id
            ORDER BY ts.session_date DESC, ts.start_time DESC
        ");
        $stmt->execute($params);
        $reports = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error fetching session reports: " . $e->getMessage());
    }
}

// Get module details for selected module
$selected_module = null;
if ($filter_module) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, tm.role 
            FROM modules m 
            JOIN teacher_modules tm ON m.id = tm.module_id 
            WHERE m.id = ? AND tm.teacher_id = ?
        ");
        $stmt->execute([$filter_module, $teacher_id]);
        $selected_module = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching module details: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - PAW Project</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Teacher Styles */
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .filters-title {
            margin-bottom: 1rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-group label {
            font-weight: 500;
            color: #374151;
        }
        
        .form-control {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
            font-size: 1rem;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
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
            margin: 0 auto 1rem;
        }
        
        .stat-card:nth-child(1) .stat-icon { background: #3b82f6; }
        .stat-card:nth-child(2) .stat-icon { background: #10b981; }
        .stat-card:nth-child(3) .stat-icon { background: #f59e0b; }
        .stat-card:nth-child(4) .stat-icon { background: #8b5cf6; }
        .stat-card:nth-child(5) .stat-icon { background: #ef4444; }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: #6b7280;
            font-weight: 500;
        }
        
        .reports-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header h2 {
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .report-tabs {
            display: flex;
            gap: 0.5rem;
        }
        
        .tab-btn {
            padding: 0.5rem 1rem;
            border: none;
            background: transparent;
            color: #6b7280;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .tab-btn.active {
            background: #3b82f6;
            color: white;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .report-table th,
        .report-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .report-table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        
        .report-table tbody tr:hover {
            background: #f9fafb;
        }
        
        .attendance-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .attendance-excellent {
            background: #d1fae5;
            color: #065f46;
        }
        
        .attendance-good {
            background: #fef3c7;
            color: #92400e;
        }
        
        .attendance-poor {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .progress-excellent { background: #10b981; }
        .progress-good { background: #f59e0b; }
        .progress-poor { background: #ef4444; }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }
        
        .module-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .module-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
        }
        
        .info-item strong {
            color: #2c3e50;
        }
        
        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            
            .report-tabs {
                flex-direction: column;
            }
            
            .section-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
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
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <a href="sessions.php" class="nav-link">
                    <i class="fas fa-calendar-alt"></i>
                    Sessions
                </a>
                <a href="attendance_summary.php" class="nav-link active">
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
            <h1><i class="fas fa-chart-bar"></i> Attendance Reports</h1>
            <p>View detailed attendance statistics and reports for your modules</p>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <h3 class="filters-title">
                <i class="fas fa-filter"></i>
                Report Filters
            </h3>
            <form method="GET" class="filter-grid">
                <div class="form-group">
                    <label for="module_id">Module *</label>
                    <select name="module_id" id="module_id" class="form-control" required>
                        <option value="">Select Module</option>
                        <?php foreach ($teacher_modules as $module): ?>
                        <option value="<?php echo $module['id']; ?>" 
                                <?php echo ($filter_module == $module['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($module['module_code'] . ' - ' . $module['module_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="report_type">Report Type</label>
                    <select name="report_type" id="report_type" class="form-control">
                        <option value="summary" <?php echo ($report_type === 'summary') ? 'selected' : ''; ?>>Student Summary</option>
                        <option value="sessions" <?php echo ($report_type === 'sessions') ? 'selected' : ''; ?>>Session Reports</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_from">Date From</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" 
                           value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>
                
                <div class="form-group">
                    <label for="date_to">Date To</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" 
                           value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>
                
                <?php if ($report_type === 'summary'): ?>
                <div class="form-group">
                    <label for="specialty">Specialty</label>
                    <select name="specialty" id="specialty" class="form-control">
                        <option value="">All Specialties</option>
                        <?php foreach ($specialties as $specialty): ?>
                        <option value="<?php echo htmlspecialchars($specialty); ?>" 
                                <?php echo ($filter_specialty === $specialty) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($specialty); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-chart-bar"></i>
                        Generate Report
                    </button>
                </div>
            </form>
        </div>

        <?php if ($selected_module): ?>
        <!-- Module Information -->
        <div class="reports-section">
            <div class="module-header">
                <h3><i class="fas fa-book"></i> <?php echo htmlspecialchars($selected_module['module_code']); ?></h3>
                <div class="module-info">
                    <div class="info-item">
                        <i class="fas fa-tag"></i>
                        <span><strong>Name:</strong> <?php echo htmlspecialchars($selected_module['module_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-star"></i>
                        <span><strong>Credits:</strong> <?php echo $selected_module['credits']; ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-calendar"></i>
                        <span><strong>Semester:</strong> <?php echo htmlspecialchars($selected_module['semester']); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-graduation-cap"></i>
                        <span><strong>Year:</strong> <?php echo $selected_module['year_level']; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($summary_stats)): ?>
        <!-- Summary Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $summary_stats['total_students']; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-number"><?php echo $summary_stats['avg_attendance']; ?>%</div>
                <div class="stat-label">Average Attendance</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-number"><?php echo $summary_stats['total_sessions']; ?></div>
                <div class="stat-label">Total Sessions</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-thumbs-up"></i>
                </div>
                <div class="stat-number"><?php echo $summary_stats['high_attendance']; ?></div>
                <div class="stat-label">High Attendance (≥90%)</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-number"><?php echo $summary_stats['low_attendance']; ?></div>
                <div class="stat-label">Low Attendance (<70%)</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Reports Table -->
        <?php if (!empty($reports)): ?>
        <div class="reports-section">
            <div class="section-header">
                <h2>
                    <i class="fas fa-table"></i>
                    <?php echo ($report_type === 'summary') ? 'Student Attendance Summary' : 'Session Attendance Reports'; ?>
                </h2>
            </div>
            
            <div class="table-container">
                <?php if ($report_type === 'summary'): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Specialty</th>
                            <th>Total Sessions</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Attendance Rate</th>
                            <th>Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($report['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($report['student_number']); ?></td>
                            <td><?php echo htmlspecialchars($report['specialty'] ?: 'General'); ?></td>
                            <td><?php echo $report['total_sessions']; ?></td>
                            <td><?php echo $report['present_count']; ?></td>
                            <td><?php echo $report['absent_count']; ?></td>
                            <td>
                                <?php
                                $percentage = $report['attendance_percentage'];
                                $class = '';
                                if ($percentage >= 90) {
                                    $class = 'attendance-excellent';
                                } elseif ($percentage >= 70) {
                                    $class = 'attendance-good';
                                } else {
                                    $class = 'attendance-poor';
                                }
                                ?>
                                <span class="attendance-badge <?php echo $class; ?>">
                                    <?php echo $percentage; ?>%
                                </span>
                            </td>
                            <td>
                                <div class="progress-bar">
                                    <?php
                                    $progressClass = '';
                                    if ($percentage >= 90) {
                                        $progressClass = 'progress-excellent';
                                    } elseif ($percentage >= 70) {
                                        $progressClass = 'progress-good';
                                    } else {
                                        $progressClass = 'progress-poor';
                                    }
                                    ?>
                                    <div class="progress-fill <?php echo $progressClass; ?>" 
                                         style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php else: ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Session Date</th>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Enrolled</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Attendance Rate</th>
                            <th>Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($report['session_date'])); ?></td>
                            <td>
                                <?php echo date('H:i', strtotime($report['start_time'])); ?> - 
                                <?php echo date('H:i', strtotime($report['end_time'])); ?>
                            </td>
                            <td><?php echo htmlspecialchars($report['session_type']); ?></td>
                            <td><?php echo htmlspecialchars($report['location'] ?: 'TBA'); ?></td>
                            <td><?php echo $report['enrolled_count']; ?></td>
                            <td><?php echo $report['present_count']; ?></td>
                            <td><?php echo $report['absent_count']; ?></td>
                            <td>
                                <?php
                                $percentage = $report['attendance_percentage'];
                                $class = '';
                                if ($percentage >= 90) {
                                    $class = 'attendance-excellent';
                                } elseif ($percentage >= 70) {
                                    $class = 'attendance-good';
                                } else {
                                    $class = 'attendance-poor';
                                }
                                ?>
                                <span class="attendance-badge <?php echo $class; ?>">
                                    <?php echo $percentage; ?>%
                                </span>
                            </td>
                            <td>
                                <div class="progress-bar">
                                    <?php
                                    $progressClass = '';
                                    if ($percentage >= 90) {
                                        $progressClass = 'progress-excellent';
                                    } elseif ($percentage >= 70) {
                                        $progressClass = 'progress-good';
                                    } else {
                                        $progressClass = 'progress-poor';
                                    }
                                    ?>
                                    <div class="progress-fill <?php echo $progressClass; ?>" 
                                         style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        
        <?php elseif ($filter_module): ?>
        <div class="empty-state">
            <i class="fas fa-chart-line"></i>
            <h3>No Data Available</h3>
            <p>No attendance data found for the selected filters. Make sure sessions have been conducted and attendance has been marked.</p>
        </div>
        
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-hand-pointer"></i>
            <h3>Select a Module</h3>
            <p>Please select a module from the dropdown above to view attendance reports.</p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-submit form when report type changes
        document.getElementById('report_type').addEventListener('change', function() {
            // Clear specialty filter when switching to sessions report
            if (this.value === 'sessions') {
                const specialtySelect = document.getElementById('specialty');
                if (specialtySelect) {
                    specialtySelect.value = '';
                }
            }
            this.form.submit();
        });
        
        // Auto-submit form when module changes
        document.getElementById('module_id').addEventListener('change', function() {
            if (this.value) {
                this.form.submit();
            }
        });
    </script>
</body>
</html>
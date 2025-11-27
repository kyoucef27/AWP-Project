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
    <link rel="stylesheet" href="attendance_summary.css">
</head>
<body>
    <nav class="teacher-navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <a href="../dashboard/dashboard.php">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>PAW Teacher</span>
                </a>
            </div>
            
            <div class="navbar-menu">
                <a href="../dashboard/dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <a href="../sessions/sessions.php" class="nav-link">
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
                <a href="../../auth/logout.php" class="logout-btn">
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

    <script src="attendance_summary.js"></script>
</body>
</html>
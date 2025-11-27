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

$message = '';
$error = '';
$session_id = isset($_GET['session_id']) ? $_GET['session_id'] : null;
$module_id = isset($_GET['module_id']) ? $_GET['module_id'] : null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'mark_attendance' && isset($_POST['session_id'])) {
            try {
                $pdo->beginTransaction();
                
                $session_id = $_POST['session_id'];
                
                // Verify session belongs to teacher
                $stmt = $pdo->prepare("SELECT id FROM teaching_sessions WHERE id = ? AND teacher_id = ?");
                $stmt->execute([$session_id, $teacher_id]);
                if (!$stmt->fetch()) {
                    throw new Exception("Session not found or access denied");
                }
                
                // Delete existing attendance for this session and date
                $stmt = $pdo->prepare("DELETE FROM attendance WHERE session_id = ? OR (attendance_date = ? AND enrollment_id IN (
                    SELECT e.id FROM enrollments e 
                    JOIN teaching_sessions ts ON e.module_id = ts.module_id 
                    WHERE ts.id = ? AND e.status = 'active'
                ))");
                $stmt->execute([$session_id, date('Y-m-d'), $session_id]);
                
                // Mark attendance for present students
                $present_count = 0;
                if (isset($_POST['present_students']) && is_array($_POST['present_students'])) {
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance (enrollment_id, attendance_date, status, recorded_by, session_id)
                        VALUES (?, ?, 'present', ?, ?)
                    ");
                    
                    foreach ($_POST['present_students'] as $enrollment_id) {
                        $stmt->execute([$enrollment_id, date('Y-m-d'), $user_id, $session_id]);
                        $present_count++;
                    }
                }
                
                // Mark attendance for absent students
                $stmt = $pdo->prepare("
                    SELECT e.id as enrollment_id 
                    FROM enrollments e 
                    JOIN teaching_sessions ts ON e.module_id = ts.module_id 
                    WHERE ts.id = ? AND e.status = 'active'
                ");
                $stmt->execute([$session_id]);
                $all_enrollments = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $present_enrollments = isset($_POST['present_students']) ? $_POST['present_students'] : [];
                $absent_enrollments = array_diff($all_enrollments, $present_enrollments);
                
                $absent_count = 0;
                if (!empty($absent_enrollments)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance (enrollment_id, attendance_date, status, recorded_by, session_id)
                        VALUES (?, ?, 'absent', ?, ?)
                    ");
                    
                    foreach ($absent_enrollments as $enrollment_id) {
                        $stmt->execute([$enrollment_id, date('Y-m-d'), $user_id, $session_id]);
                        $absent_count++;
                    }
                }
                
                // Mark session as attendance taken
                $stmt = $pdo->prepare("UPDATE teaching_sessions SET attendance_taken = TRUE WHERE id = ?");
                $stmt->execute([$session_id]);
                
                $pdo->commit();
                $message = "Attendance marked successfully for $present_count present and $absent_count absent students.";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Error marking attendance: " . $e->getMessage());
                error_log("SQL Error Details: " . print_r($e, true));
                $error = 'Error marking attendance: ' . $e->getMessage();
            }
        }
    }
}

// Get session details if session_id is provided
$session = null;
if ($session_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT ts.*, m.module_code, m.module_name 
            FROM teaching_sessions ts 
            JOIN modules m ON ts.module_id = m.id 
            WHERE ts.id = ? AND ts.teacher_id = ?
        ");
        $stmt->execute([$session_id, $teacher_id]);
        $session = $stmt->fetch();
        
        if ($session) {
            $module_id = $session['module_id'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching session: " . $e->getMessage());
    }
}

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
    if ($module_id) {
        $stmt = $pdo->prepare("SELECT tm.id FROM teacher_modules tm WHERE tm.module_id = ? AND tm.teacher_id = ?");
        $stmt->execute([$module_id, $teacher_id]);
        if (!$stmt->fetch()) {
            $module_id = null; // Reset if teacher doesn't have access
            $error = "Access denied: You can only mark attendance for modules you teach.";
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching teacher modules: " . $e->getMessage());
    $teacher_modules = [];
}

// Get sessions for selected module
$sessions = [];
if ($module_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT ts.*, 
                   COUNT(DISTINCT e.student_id) as enrolled_count,
                   COUNT(DISTINCT a.id) as attendance_count
            FROM teaching_sessions ts
            LEFT JOIN enrollments e ON ts.module_id = e.module_id AND e.status = 'active'
            LEFT JOIN attendance a ON ts.id = a.session_id
            WHERE ts.module_id = ? AND ts.teacher_id = ?
            GROUP BY ts.id
            ORDER BY ts.session_date DESC, ts.start_time DESC
        ");
        $stmt->execute([$module_id, $teacher_id]);
        $sessions = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching sessions: " . $e->getMessage());
    }
}

// Get students for attendance marking
$students = [];
if ($session_id && $session) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                e.id as enrollment_id,
                s.id as student_id,
                s.student_number,
                u.full_name as student_name,
                s.specialty,
                a.status as current_status
            FROM enrollments e
            JOIN students s ON e.student_id = s.id
            JOIN users u ON s.user_id = u.id
            LEFT JOIN attendance a ON e.id = a.enrollment_id AND a.session_id = ?
            WHERE e.module_id = ? AND e.status = 'active'
            ORDER BY u.full_name
        ");
        $stmt->execute([$session_id, $session['module_id']]);
        $students = $stmt->fetchAll();
        
        // Debug: Log the query results
        error_log("Session ID: $session_id, Module ID: {$session['module_id']}, Students found: " . count($students));
        
        // If no students found, let's check if there are any enrollments at all for this module
        if (empty($students)) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total_enrollments,
                       COUNT(CASE WHEN status = 'active' THEN 1 END) as active_enrollments
                FROM enrollments 
                WHERE module_id = ?
            ");
            $stmt->execute([$session['module_id']]);
            $enrollment_stats = $stmt->fetch();
            error_log("Module {$session['module_id']} - Total enrollments: {$enrollment_stats['total_enrollments']}, Active: {$enrollment_stats['active_enrollments']}");
        }
    } catch (PDOException $e) {
        error_log("Error fetching students: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance - PAW Project</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="mark_attendance.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
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
                <a href="attendance_summary.php" class="nav-link">
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
            <h1><i class="fas fa-check"></i> Mark Attendance</h1>
            <div class="breadcrumb">
                <a href="../dashboard/dashboard.php">Dashboard</a> / 
                <a href="sessions.php">Sessions</a> / 
                Mark Attendance
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Module and Session Selection -->
        <div class="selection-section">
            <h3><i class="fas fa-filter"></i> Select Module and Session</h3>
            
            <form method="GET" style="margin-top: 1rem;">
                <div class="form-group">
                    <label for="module_id">Module</label>
                    <select name="module_id" id="module_id" class="form-control" onchange="this.form.submit()">
                        <option value="">Select a Module</option>
                        <?php foreach ($teacher_modules as $module): ?>
                        <option value="<?php echo $module['id']; ?>" 
                                <?php echo ($module_id == $module['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($module['module_code'] . ' - ' . $module['module_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if (!empty($sessions)): ?>
                <div class="form-group">
                    <label>Available Sessions</label>
                    <div class="sessions-grid">
                        <?php foreach ($sessions as $sess): ?>
                        <div class="session-card <?php echo $sess['attendance_taken'] ? 'completed' : ''; ?>"
                             onclick="window.location.href='?session_id=<?php echo $sess['id']; ?>&module_id=<?php echo $module_id; ?>'">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <strong><?php echo date('M j, Y', strtotime($sess['session_date'])); ?></strong>
                                <?php if ($sess['attendance_taken']): ?>
                                <span class="status-present">
                                    <i class="fas fa-check"></i> Completed
                                </span>
                                <?php endif; ?>
                            </div>
                            <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-clock"></i>
                                <?php echo date('H:i', strtotime($sess['start_time'])); ?> - 
                                <?php echo date('H:i', strtotime($sess['end_time'])); ?>
                            </div>
                            <div style="color: #6b7280; font-size: 0.875rem;">
                                <?php echo htmlspecialchars($sess['session_type']); ?>
                                <?php if ($sess['location']): ?>
                                • <?php echo htmlspecialchars($sess['location']); ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($sess['attendance_taken']): ?>
                            <div style="margin-top: 0.5rem; font-size: 0.875rem;">
                                <?php echo $sess['attendance_count']; ?>/<?php echo $sess['enrolled_count']; ?> attended
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($session): ?>
        <!-- Session Information -->
        <div class="session-info">
            <h3><i class="fas fa-calendar"></i> Session Details</h3>
            <div class="session-details">
                <div class="detail-item">
                    <i class="fas fa-book"></i>
                    <span><strong>Module:</strong> <?php echo htmlspecialchars($session['module_code'] . ' - ' . $session['module_name']); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-calendar"></i>
                    <span><strong>Date:</strong> <?php echo date('M j, Y', strtotime($session['session_date'])); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-clock"></i>
                    <span><strong>Time:</strong> 
                        <?php echo date('H:i', strtotime($session['start_time'])); ?> - 
                        <?php echo date('H:i', strtotime($session['end_time'])); ?>
                    </span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><strong>Location:</strong> <?php echo htmlspecialchars($session['location'] ?: 'TBA'); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-tag"></i>
                    <span><strong>Type:</strong> <?php echo htmlspecialchars($session['session_type']); ?></span>
                </div>
                <?php if ($session['description']): ?>
                <div class="detail-item" style="grid-column: 1/-1;">
                    <i class="fas fa-info-circle"></i>
                    <span><strong>Description:</strong> <?php echo htmlspecialchars($session['description']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Attendance Marking -->
        <?php if (!empty($students)): ?>
        <form method="POST" class="attendance-section">
            <input type="hidden" name="action" value="mark_attendance">
            <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
            
            <div class="section-header">
                <h2><i class="fas fa-users"></i> Mark Attendance (<?php echo count($students); ?> students)</h2>
                <div class="bulk-actions">
                    <button type="button" class="btn btn-sm btn-outline" onclick="selectAll()">
                        <i class="fas fa-check-double"></i>
                        Select All
                    </button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="selectNone()">
                        <i class="fas fa-times"></i>
                        Select None
                    </button>
                </div>
            </div>
            
            <div class="students-list">
                <?php foreach ($students as $student): ?>
                <div class="student-item <?php echo ($student['current_status'] === 'present') ? 'present' : ''; ?>"
                     data-enrollment-id="<?php echo $student['enrollment_id']; ?>">
                    <input type="checkbox" 
                           name="present_students[]" 
                           value="<?php echo $student['enrollment_id']; ?>"
                           class="student-checkbox"
                           <?php echo ($student['current_status'] === 'present') ? 'checked' : ''; ?>
                           onchange="updateStudentStatus(this)">
                    
                    <div class="student-info">
                        <div>
                            <div class="student-name"><?php echo htmlspecialchars($student['student_name']); ?></div>
                            <div class="student-number"><?php echo htmlspecialchars($student['student_number']); ?></div>
                        </div>
                        <div class="student-specialty">
                            <?php echo htmlspecialchars($student['specialty'] ?: 'General'); ?>
                        </div>
                        <div class="attendance-status">
                            <?php if ($student['current_status'] === 'present'): ?>
                            <span class="status-present">
                                <i class="fas fa-check"></i>
                                Present
                            </span>
                            <?php elseif ($student['current_status'] === 'absent'): ?>
                            <span class="status-absent">
                                <i class="fas fa-times"></i>
                                Absent
                            </span>
                            <?php else: ?>
                            <span style="color: #6b7280;">
                                <i class="fas fa-question"></i>
                                Not Marked
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="submit-section">
                <div class="attendance-summary">
                    <span id="presentCount">0</span> present • 
                    <span id="absentCount">0</span> absent • 
                    <span id="totalCount"><?php echo count($students); ?></span> total
                </div>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i>
                    Save Attendance
                </button>
            </div>
        </form>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            <h3>No Students Found</h3>
            <p>No students are enrolled in this module or session.</p>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (!$module_id): ?>
        <div class="empty-state">
            <i class="fas fa-hand-pointer"></i>
            <h3>Select a Module</h3>
            <p>Please select a module from the dropdown above to view sessions and mark attendance.</p>
        </div>
        <?php elseif ($module_id && empty($sessions)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h3>No Sessions Found</h3>
            <p>No sessions have been created for this module. <a href="sessions.php?module_id=<?php echo $module_id; ?>">Create a session</a> to mark attendance.</p>
        </div>
        <?php endif; ?>
    </div>

    <script src="mark_attendance.js"></script>
</body>
</html>
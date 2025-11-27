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

// Handle form submissions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create_session') {
            try {
                // Verify the module belongs to this teacher before creating session
                $stmt = $pdo->prepare("SELECT tm.id FROM teacher_modules tm WHERE tm.module_id = ? AND tm.teacher_id = ?");
                $stmt->execute([$_POST['module_id'], $teacher_id]);
                if (!$stmt->fetch()) {
                    throw new Exception("Access denied: You can only create sessions for modules you teach.");
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO teaching_sessions (teacher_id, module_id, session_date, start_time, end_time, session_type, location, description)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $teacher_id,
                    $_POST['module_id'],
                    $_POST['session_date'],
                    $_POST['start_time'],
                    $_POST['end_time'],
                    $_POST['session_type'],
                    $_POST['location'],
                    $_POST['description']
                ]);
                $message = 'Session created successfully!';
            } catch (Exception $e) {
                error_log("Error creating session: " . $e->getMessage());
                $message = $e->getMessage();
            } catch (PDOException $e) {
                error_log("Error creating session: " . $e->getMessage());
                $message = 'Error creating session. Please try again.';
            }
        } elseif ($_POST['action'] === 'delete_session') {
            try {
                // Verify the session belongs to this teacher before deleting
                $stmt = $pdo->prepare("DELETE FROM teaching_sessions WHERE id = ? AND teacher_id = ?");
                $stmt->execute([$_POST['session_id'], $teacher_id]);
                if ($stmt->rowCount() > 0) {
                    $message = 'Session deleted successfully!';
                } else {
                    $message = 'Session not found or access denied.';
                }
            } catch (PDOException $e) {
                error_log("Error deleting session: " . $e->getMessage());
                $message = 'Error deleting session. Please try again.';
            }
        }
    }
}

// Get filter parameters
$filter_module = isset($_GET['module_id']) ? $_GET['module_id'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

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

// Build sessions query with filters
$where_clauses = ["ts.teacher_id = ?"];
$params = [$teacher_id];

if ($filter_module) {
    $where_clauses[] = "ts.module_id = ?";
    $params[] = $filter_module;
}

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
            COUNT(DISTINCT a.id) as attendance_count
        FROM teaching_sessions ts
        JOIN modules m ON ts.module_id = m.id
        LEFT JOIN enrollments e ON m.id = e.module_id AND e.status = 'active'
        LEFT JOIN attendance a ON ts.id = a.session_id
        WHERE $where_sql
        GROUP BY ts.id
        ORDER BY ts.session_date DESC, ts.start_time DESC
    ");
    $stmt->execute($params);
    $sessions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching sessions: " . $e->getMessage());
    $sessions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teaching Sessions - PAW Project</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="sessions.css">
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
                <a href="sessions.php" class="nav-link active">
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
            <h1><i class="fas fa-calendar-alt"></i> Teaching Sessions</h1>
            <button class="btn btn-primary" onclick="showCreateModal()">
                <i class="fas fa-plus"></i>
                Create New Session
            </button>
        </div>

        <?php if ($message): ?>
        <div class="alert">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters-section">
            <h3 class="filters-title">
                <i class="fas fa-filter"></i>
                Filter Sessions
            </h3>
            <form method="GET" class="filter-grid">
                <div class="form-group">
                    <label for="module_id">Module</label>
                    <select name="module_id" id="module_id" class="form-control">
                        <option value="">All Modules</option>
                        <?php foreach ($teacher_modules as $module): ?>
                        <option value="<?php echo $module['id']; ?>" 
                                <?php echo ($filter_module == $module['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($module['module_code'] . ' - ' . $module['module_name']); ?>
                        </option>
                        <?php endforeach; ?>
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
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Sessions List -->
        <div class="sessions-section">
            <div class="section-header">
                <h2><i class="fas fa-list"></i> Sessions (<?php echo count($sessions); ?>)</h2>
            </div>
            
            <?php if (empty($sessions)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Sessions Found</h3>
                <p>No sessions match your current filters. Try adjusting the filters or create a new session.</p>
            </div>
            <?php else: ?>
            <div class="sessions-list">
                <?php foreach ($sessions as $session): ?>
                <div class="session-item">
                    <div class="session-info">
                        <div class="session-badge <?php echo strtolower($session['session_type']); ?>">
                            <?php 
                            $icons = [
                                'Lecture' => 'L',
                                'Lab' => 'La',
                                'Tutorial' => 'T',
                                'Exam' => 'E',
                                'Workshop' => 'W'
                            ];
                            echo $icons[$session['session_type']] ?? 'S'; 
                            ?>
                        </div>
                        
                        <div class="session-details">
                            <h3><?php echo htmlspecialchars($session['module_code'] . ' - ' . $session['module_name']); ?></h3>
                            <div class="session-meta">
                                <span>
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M j, Y', strtotime($session['session_date'])); ?>
                                </span>
                                <span>
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('H:i', strtotime($session['start_time'])); ?> - 
                                    <?php echo date('H:i', strtotime($session['end_time'])); ?>
                                </span>
                                <span>
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($session['location'] ?: 'TBA'); ?>
                                </span>
                            </div>
                            <?php if ($session['description']): ?>
                            <div class="session-description">
                                <?php echo htmlspecialchars($session['description']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="session-status">
                            <?php if ($session['attendance_taken']): ?>
                                <div class="status-badge completed">
                                    <i class="fas fa-check"></i>
                                    Completed
                                </div>
                                <small><?php echo $session['attendance_count']; ?>/<?php echo $session['enrolled_count']; ?> attended</small>
                            <?php else: ?>
                                <div class="status-badge pending">
                                    <i class="fas fa-clock"></i>
                                    Pending
                                </div>
                                <small><?php echo $session['enrolled_count']; ?> enrolled</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="session-actions">
                        <?php if (!$session['attendance_taken']): ?>
                        <a href="../attendance/mark_attendance.php?session_id=<?php echo $session['id']; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-check"></i>
                            Take Attendance
                        </a>
                        <?php endif; ?>
                        
                        <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $session['id']; ?>)">
                            <i class="fas fa-trash"></i>
                            Delete
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Session Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Create New Session</h2>
                <button type="button" class="close-btn" onclick="hideCreateModal()">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_session">
                
                <div class="form-group">
                    <label for="create_module_id">Module *</label>
                    <select name="module_id" id="create_module_id" class="form-control" required>
                        <option value="">Select Module</option>
                        <?php foreach ($teacher_modules as $module): ?>
                        <option value="<?php echo $module['id']; ?>">
                            <?php echo htmlspecialchars($module['module_code'] . ' - ' . $module['module_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="session_date">Date *</label>
                    <input type="date" name="session_date" id="session_date" class="form-control" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_time">Start Time *</label>
                        <input type="time" name="start_time" id="start_time" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_time">End Time *</label>
                        <input type="time" name="end_time" id="end_time" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="session_type">Session Type *</label>
                        <select name="session_type" id="session_type" class="form-control" required>
                            <option value="Lecture">Lecture</option>
                            <option value="Lab">Lab</option>
                            <option value="Tutorial">Tutorial</option>
                            <option value="Exam">Exam</option>
                            <option value="Workshop">Workshop</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" name="location" id="location" class="form-control" placeholder="e.g. Room 101">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" class="form-control" rows="3" 
                              placeholder="Optional session description"></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" class="btn btn-outline" onclick="hideCreateModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Create Session
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_session">
        <input type="hidden" name="session_id" id="deleteSessionId">
    </form>

    <script src="sessions.js"></script>
</body>
</html>
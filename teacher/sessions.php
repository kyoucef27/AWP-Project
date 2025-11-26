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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
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
            font-size: 1rem;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
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
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
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
        
        .sessions-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .section-header h2 {
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .sessions-list {
            overflow: hidden;
        }
        
        .session-item {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            align-items: center;
        }
        
        .session-item:last-child {
            border-bottom: none;
        }
        
        .session-info {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 1rem;
            align-items: center;
        }
        
        .session-badge {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            font-weight: bold;
        }
        
        .session-badge.lecture { background: #3b82f6; }
        .session-badge.lab { background: #10b981; }
        .session-badge.tutorial { background: #f59e0b; }
        .session-badge.exam { background: #ef4444; }
        .session-badge.workshop { background: #8b5cf6; }
        
        .session-details h3 {
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .session-meta {
            display: flex;
            gap: 1rem;
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        
        .session-description {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .session-status {
            text-align: right;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            margin-bottom: 0.5rem;
        }
        
        .status-badge.completed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .session-actions {
            display: flex;
            gap: 0.5rem;
        }
        
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
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-title {
            color: #2c3e50;
            margin: 0;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .session-info {
                grid-template-columns: 1fr;
            }
            
            .session-item {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
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
                <a href="sessions.php" class="nav-link active">
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
                        <a href="mark_attendance.php?session_id=<?php echo $session['id']; ?>" class="btn btn-sm btn-primary">
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

    <script>
        function showCreateModal() {
            document.getElementById('createModal').classList.add('show');
            
            // Set default date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('session_date').value = today;
        }
        
        function hideCreateModal() {
            document.getElementById('createModal').classList.remove('show');
        }
        
        function confirmDelete(sessionId) {
            if (confirm('Are you sure you want to delete this session? This action cannot be undone.')) {
                document.getElementById('deleteSessionId').value = sessionId;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Close modal when clicking outside
        document.getElementById('createModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideCreateModal();
            }
        });
        
        // Form validation
        document.querySelector('#createModal form').addEventListener('submit', function(e) {
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            
            if (startTime >= endTime) {
                e.preventDefault();
                alert('End time must be after start time.');
            }
        });
    </script>
</body>
</html>
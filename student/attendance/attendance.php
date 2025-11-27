<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../../includes/config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../../auth/login.php");
    exit();
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Fetch student record to get student ID
try {
    $stmt = $pdo->prepare("SELECT s.id, u.full_name FROM students s JOIN users u ON s.user_id = u.id WHERE s.user_id = ?");
    $stmt->execute([$user_id]);
    $student_record = $stmt->fetch();
    
    if (!$student_record) {
        die("Student profile not found. Please contact an administrator.");
    }
    
    $student_id = $student_record['id'];
} catch (PDOException $e) {
    error_log("Error fetching student: " . $e->getMessage());
    die("Error loading student profile");
}

// Get module_id from query parameter (optional)
$selected_module_id = isset($_GET['module_id']) ? intval($_GET['module_id']) : null;

// Handle justification submission
$submission_success = false;
$submission_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_justification'])) {
    $attendance_id = intval($_POST['attendance_id']);
    $justification_text = trim($_POST['justification_text']);
    
    if (empty($justification_text)) {
        $submission_error = "Justification text is required.";
    } else {
        try {
            // Verify attendance record belongs to this student
            $stmt = $pdo->prepare("
                SELECT a.id, a.status 
                FROM attendance a 
                JOIN enrollments e ON a.enrollment_id = e.id 
                WHERE a.id = ? AND e.student_id = ?
            ");
            $stmt->execute([$attendance_id, $student_id]);
            $attendance = $stmt->fetch();
            
            if (!$attendance) {
                $submission_error = "Invalid attendance record.";
            } elseif ($attendance['status'] !== 'absent') {
                $submission_error = "You can only submit justifications for absences.";
            } else {
                // Check if justification already exists
                $stmt = $pdo->prepare("SELECT id FROM absence_justifications WHERE attendance_id = ? AND student_id = ?");
                $stmt->execute([$attendance_id, $student_id]);
                
                if ($stmt->fetch()) {
                    $submission_error = "A justification has already been submitted for this absence.";
                } else {
                    // Handle file upload
                    $document_path = null;
                    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/justifications/';
                        
                        // Create upload directory if it doesn't exist
                        if (!is_dir($upload_dir)) {
                            if (!mkdir($upload_dir, 0755, true)) {
                                $submission_error = "Failed to create upload directory.";
                            }
                        }
                        
                        if (!$submission_error) {
                            $file_extension = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
                            $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                            
                            // Validate file type
                            if (!in_array(strtolower($file_extension), $allowed_extensions)) {
                                $submission_error = "Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX";
                            }
                            // Validate file size (5MB max)
                            elseif ($_FILES['document']['size'] > 5 * 1024 * 1024) {
                                $submission_error = "File size too large. Maximum 5MB allowed.";
                            }
                            else {
                                // Generate unique filename
                                $file_name = 'just_' . $student_id . '_' . $attendance_id . '_' . time() . '.' . $file_extension;
                                $full_path = $upload_dir . $file_name;
                                
                                // Move uploaded file
                                if (move_uploaded_file($_FILES['document']['tmp_name'], $full_path)) {
                                    // Store relative path in database
                                    $document_path = 'uploads/justifications/' . $file_name;
                                } else {
                                    $submission_error = "Failed to upload document. Please try again.";
                                }
                            }
                        }
                    }
                    
                    if (!$submission_error) {
                        // Insert justification
                        $stmt = $pdo->prepare("
                            INSERT INTO absence_justifications 
                            (attendance_id, student_id, justification_text, supporting_document, status, submitted_at) 
                            VALUES (?, ?, ?, ?, 'pending', NOW())
                        ");
                        $stmt->execute([$attendance_id, $student_id, $justification_text, $document_path]);
                        $submission_success = true;
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Justification submission error: " . $e->getMessage());
            $submission_error = "An error occurred while submitting your justification.";
        }
    }
}

// Fetch full student info with username
try {
    $stmt = $pdo->prepare("SELECT s.*, u.username, u.full_name FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Error fetching student details: " . $e->getMessage());
    die("Error loading student profile");
}

// Fetch enrolled modules
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT m.id, m.module_name as name, m.module_code as code 
        FROM enrollments e 
        JOIN modules m ON e.module_id = m.id 
        WHERE e.student_id = ? AND e.status IN ('active', 'completed')
        ORDER BY m.module_name
    ");
    $stmt->execute([$student_id]);
    $enrolled_modules = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching modules: " . $e->getMessage());
    $enrolled_modules = [];
}

// Fetch attendance records with justification info
try {
    $query = "
        SELECT 
            a.id as attendance_id,
            a.attendance_date as date,
            a.status,
            a.remarks,
            m.id as module_id,
            m.module_name as module_name,
            m.module_code as module_code,
            aj.id as justification_id,
            aj.justification_text,
            aj.status as justification_status,
            aj.submitted_at,
            aj.reviewed_at,
            aj.review_notes as reviewer_comments
        FROM attendance a
        JOIN enrollments e ON a.enrollment_id = e.id
        JOIN modules m ON e.module_id = m.id
        LEFT JOIN absence_justifications aj ON a.id = aj.attendance_id
        WHERE e.student_id = ?
    ";
    
    $params = [$student_id];
    
    if ($selected_module_id) {
        $query .= " AND m.id = ?";
        $params[] = $selected_module_id;
    }
    
    $query .= " ORDER BY a.attendance_date DESC, m.module_name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $attendance_records = $stmt->fetchAll();
    
    // Calculate statistics
    $total_records = count($attendance_records);
    $present_count = count(array_filter($attendance_records, fn($r) => $r['status'] === 'present'));
    $absent_count = count(array_filter($attendance_records, fn($r) => $r['status'] === 'absent'));
    $late_count = count(array_filter($attendance_records, fn($r) => $r['status'] === 'late'));
    $attendance_rate = $total_records > 0 ? round(($present_count / $total_records) * 100, 1) : 0;
    
    $pending_justifications = count(array_filter($attendance_records, fn($r) => 
        $r['justification_id'] && $r['justification_status'] === 'pending'
    ));
    
} catch (PDOException $e) {
    error_log("Error fetching attendance records: " . $e->getMessage());
    echo "<div style='background: #ffebee; padding: 10px; margin: 10px 0; border-left: 4px solid #f44336;'>";
    echo "<strong>Database Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
    $attendance_records = [];
    $total_records = 0;
    $present_count = 0;
    $absent_count = 0;
    $late_count = 0;
    $attendance_rate = 0;
    $pending_justifications = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - PAW Project</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="attendance.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
                <span>Student Portal</span>
            </div>
            <div class="nav-links">
                <a href="../dashboard/dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>My Courses</span>
                </a>
                <a href="attendance.php" class="nav-link active">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>
                <a href="../profile/profile.php" class="nav-link">
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
                <a href="../../auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Alerts -->
        <?php if ($submission_success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span>Justification submitted successfully! Your request is pending review.</span>
            </div>
        <?php endif; ?>
        
        <?php if ($submission_error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($submission_error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <!-- Success/Error Messages -->
        <?php if ($submission_success): ?>
        <div style="background: #e8f5e9; padding: 15px; margin: 15px 0; border-left: 4px solid #4caf50; border-radius: 4px; color: #2e7d32;">
            <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
            <strong>Success!</strong> Your justification has been submitted successfully and is pending review.
        </div>
        <?php endif; ?>
        
        <?php if ($submission_error): ?>
        <div style="background: #ffebee; padding: 15px; margin: 15px 0; border-left: 4px solid #f44336; border-radius: 4px; color: #c62828;">
            <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
            <strong>Error!</strong> <?php echo htmlspecialchars($submission_error); ?>
        </div>
        <?php endif; ?>

        <div class="page-header">
            <div class="header-content">
                <h1 class="page-title">
                    <i class="fas fa-calendar-check"></i>
                    My Attendance Records
                </h1>
                <p class="page-subtitle">Track your attendance and manage absence justifications</p>
            </div>
        </div>
        
        <!-- Debug Info (remove in production) -->
        <?php if (count($enrolled_modules) == 0): ?>
        <div style="background: #fff3e0; padding: 15px; margin: 15px 0; border-left: 4px solid #ff9800; border-radius: 4px;">
            <strong>No Enrolled Modules Found</strong>
            <p>You don't appear to be enrolled in any modules. Contact your administrator or try the <a href="../admin/utilities/bulk_enrollment.php" style="color: #ff9800;">bulk enrollment tool</a>.</p>
        </div>
        <?php endif; ?>
        
        <?php if (count($attendance_records) == 0 && count($enrolled_modules) > 0): ?>
        <div style="background: #e3f2fd; padding: 15px; margin: 15px 0; border-left: 4px solid #2196f3; border-radius: 4px;">
            <strong>No Attendance Records Found</strong>
            <p>You're enrolled in modules but no attendance has been recorded yet. Your instructor may need to take attendance, or you can <a href="../admin/generate_sample_attendance.php" style="color: #2196f3;">generate sample data</a> for testing.</p>
        </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="attendance.php" class="filter-form">
                <div class="filter-group">
                    <label for="module_id">
                        <i class="fas fa-filter"></i>
                        Filter by Course
                    </label>
                    <select name="module_id" id="module_id" onchange="this.form.submit()">
                        <option value="">All Courses</option>
                        <?php foreach ($enrolled_modules as $module): ?>
                            <option value="<?php echo $module['id']; ?>" 
                                <?php echo $selected_module_id == $module['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($module['code'] . ' - ' . $module['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($selected_module_id): ?>
                    <a href="attendance.php" class="btn-clear-filter">
                        <i class="fas fa-times"></i>
                        Clear Filter
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $total_records; ?></div>
                    <div class="stat-label">Total Sessions</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $present_count; ?></div>
                    <div class="stat-label">Present</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $absent_count; ?></div>
                    <div class="stat-label">Absent</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $late_count; ?></div>
                    <div class="stat-label">Late</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon <?php echo $attendance_rate >= 90 ? 'success' : ($attendance_rate >= 75 ? 'warning' : 'danger'); ?>">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $attendance_rate; ?>%</div>
                    <div class="stat-label">Attendance Rate</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $pending_justifications; ?></div>
                    <div class="stat-label">Pending Reviews</div>
                </div>
            </div>
        </div>

        <!-- Attendance Records Table -->
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-list"></i>
                Attendance History
            </h2>
        </div>

        <?php if (empty($attendance_records)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <h3>No Attendance Records</h3>
                <p>No attendance records found<?php echo $selected_module_id ? ' for the selected course' : ''; ?>.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th>Justification</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): ?>
                            <tr data-label="Attendance Record">
                                <td data-label="Date">
                                    <div class="date-display">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('M j, Y', strtotime($record['date'])); ?>
                                    </div>
                                </td>
                                <td data-label="Course">
                                    <div class="course-display">
                                        <span class="course-code"><?php echo htmlspecialchars($record['module_code']); ?></span>
                                        <span class="course-name"><?php echo htmlspecialchars($record['module_name']); ?></span>
                                    </div>
                                </td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?php echo $record['status']; ?>">
                                        <?php 
                                        $status_icons = [
                                            'present' => 'check-circle',
                                            'absent' => 'times-circle',
                                            'late' => 'clock'
                                        ];
                                        ?>
                                        <i class="fas fa-<?php echo $status_icons[$record['status']] ?? 'question-circle'; ?>"></i>
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Remarks">
                                    <?php echo $record['remarks'] ? htmlspecialchars($record['remarks']) : '<span class="text-muted">—</span>'; ?>
                                </td>
                                <td data-label="Justification">
                                    <?php if ($record['justification_id']): ?>
                                        <div class="justification-info">
                                            <span class="justification-status status-<?php echo $record['justification_status']; ?>">
                                                <?php 
                                                $just_icons = [
                                                    'pending' => 'hourglass-half',
                                                    'approved' => 'check',
                                                    'rejected' => 'times'
                                                ];
                                                ?>
                                                <i class="fas fa-<?php echo $just_icons[$record['justification_status']] ?? 'question'; ?>"></i>
                                                <?php echo ucfirst($record['justification_status']); ?>
                                            </span>
                                            <button class="btn-view-justification" 
                                                    onclick="viewJustification(<?php echo $record['justification_id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                                View
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Actions">
                                    <?php if ($record['status'] === 'absent' && !$record['justification_id']): ?>
                                        <button class="btn-submit-justification" 
                                                onclick="openJustificationModal(<?php echo $record['attendance_id']; ?>, '<?php echo htmlspecialchars($record['module_name']); ?>', '<?php echo date('M j, Y', strtotime($record['date'])); ?>')">
                                            <i class="fas fa-file-upload"></i>
                                            Submit Justification
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Justification Submission Modal -->
    <div id="justificationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-file-upload"></i>
                    Submit Absence Justification
                </h3>
                <button class="modal-close" onclick="closeJustificationModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="attendance.php" enctype="multipart/form-data" class="justification-form">
                <input type="hidden" name="attendance_id" id="modal_attendance_id">
                <input type="hidden" name="submit_justification" value="1">
                
                <div class="form-group">
                    <label class="info-label">Course:</label>
                    <div id="modal_course_name" class="info-value"></div>
                </div>
                
                <div class="form-group">
                    <label class="info-label">Date:</label>
                    <div id="modal_date" class="info-value"></div>
                </div>
                
                <div class="form-group">
                    <label for="justification_text">
                        Justification <span class="required">*</span>
                    </label>
                    <textarea 
                        id="justification_text" 
                        name="justification_text" 
                        rows="5" 
                        placeholder="Please provide a detailed explanation for your absence..."
                        required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="document">
                        Supporting Document (Optional)
                    </label>
                    <input type="file" id="document" name="document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    <small class="help-text">Accepted formats: PDF, JPG, PNG, DOC, DOCX (Max 5MB)</small>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeJustificationModal()">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i>
                        Submit Justification
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="attendance.js"></script>
</body>
</html>

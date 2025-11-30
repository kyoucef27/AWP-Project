<?php
session_start();
require_once '../../includes/config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit();
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get justification ID from URL
$justification_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$justification_id) {
    die("Justification ID not provided.");
}

// Fetch student record
try {
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student_record = $stmt->fetch();
    
    if (!$student_record) {
        die("Student profile not found.");
    }
    
    $student_id = $student_record['id'];
} catch (PDOException $e) {
    error_log("Error fetching student: " . $e->getMessage());
    die("Error loading student profile");
}

// Fetch justification details with verification
try {
    $stmt = $pdo->prepare("
        SELECT 
            aj.*,
            a.attendance_date,
            a.status as attendance_status,
            m.module_name,
            m.module_code,
            reviewer.full_name as reviewer_name
        FROM absence_justifications aj
        JOIN attendance a ON aj.attendance_id = a.id
        JOIN enrollments e ON a.enrollment_id = e.id
        JOIN modules m ON e.module_id = m.id
        LEFT JOIN users reviewer ON aj.reviewed_by = reviewer.id
        WHERE aj.id = ? AND aj.student_id = ?
    ");
    $stmt->execute([$justification_id, $student_id]);
    $justification = $stmt->fetch();
    
    if (!$justification) {
        die("Justification not found or you don't have permission to view it.");
    }
    
} catch (PDOException $e) {
    error_log("Error fetching justification: " . $e->getMessage());
    die("Error loading justification");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Justification - PAW Project</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="view_justification.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1><i class="fas fa-file-alt"></i> Absence Justification</h1>
                <p><?php echo htmlspecialchars($justification['module_code'] . ' - ' . $justification['module_name']); ?></p>
            </div>
            
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Date of Absence</div>
                        <div class="info-value"><?php echo date('M j, Y', strtotime($justification['attendance_date'])); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Submitted</div>
                        <div class="info-value"><?php echo date('M j, Y - g:i A', strtotime($justification['submitted_at'])); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="status-badge status-<?php echo $justification['status']; ?>">
                                <?php 
                                $icons = [
                                    'pending' => 'hourglass-half',
                                    'approved' => 'check',
                                    'rejected' => 'times'
                                ];
                                ?>
                                <i class="fas fa-<?php echo $icons[$justification['status']] ?? 'question'; ?>"></i>
                                <?php echo ucfirst($justification['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if ($justification['reviewed_at']): ?>
                    <div class="info-item">
                        <div class="info-label">Reviewed</div>
                        <div class="info-value"><?php echo date('M j, Y - g:i A', strtotime($justification['reviewed_at'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="justification-section">
                    <h3 class="section-title">
                        <i class="fas fa-comment-alt"></i>
                        Your Justification
                    </h3>
                    <div class="justification-text">
                        <?php echo htmlspecialchars($justification['justification_text']); ?>
                    </div>
                </div>
                
                <?php if ($justification['supporting_document']): ?>
                <div class="document-section">
                    <h3 class="section-title">
                        <i class="fas fa-paperclip"></i>
                        Supporting Document
                    </h3>
                    <a href="download_document.php?file=<?php echo urlencode(basename($justification['supporting_document'])); ?>" 
                       target="_blank" class="document-link">
                        <i class="fas fa-download"></i>
                        Download Document
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if ($justification['reviewed_at'] && ($justification['review_notes'] || $justification['reviewer_name'])): ?>
                <div class="review-section">
                    <h3 class="section-title">
                        <i class="fas fa-user-check"></i>
                        Review Details
                    </h3>
                    
                    <?php if ($justification['reviewer_name']): ?>
                    <div style="margin-bottom: 1rem;">
                        <strong>Reviewed by:</strong> <?php echo htmlspecialchars($justification['reviewer_name']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($justification['review_notes']): ?>
                    <div>
                        <strong>Review Notes:</strong><br>
                        <div style="margin-top: 0.5rem; white-space: pre-wrap;">
                            <?php echo htmlspecialchars($justification['review_notes']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="actions">
                    <a href="attendance.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Attendance
                    </a>
                    
                    <button onclick="window.print()" class="btn btn-secondary">
                        <i class="fas fa-print"></i>
                        Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
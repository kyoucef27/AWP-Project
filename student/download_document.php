<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    die("Access denied");
}

// Get file parameter
$file = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($file)) {
    http_response_code(400);
    die("No file specified");
}

// Security: Only allow files in uploads/justifications directory
if (!preg_match('/^just_\d+_\d+_\d+\.(pdf|jpg|jpeg|png|doc|docx)$/i', basename($file))) {
    http_response_code(403);
    die("Invalid file name");
}

// Construct full path
$file_path = '../uploads/justifications/' . basename($file);

// Check if file exists
if (!file_exists($file_path)) {
    http_response_code(404);
    die("File not found: " . htmlspecialchars($file));
}

// Verify the user has permission to access this file
$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

try {
    // Get student ID
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student_record = $stmt->fetch();
    
    if (!$student_record) {
        http_response_code(403);
        die("Student profile not found");
    }
    
    $student_id = $student_record['id'];
    
    // Check if this file belongs to this student
    $filename = basename($file);
    $stmt = $pdo->prepare("
        SELECT id FROM absence_justifications 
        WHERE student_id = ? AND supporting_document LIKE ?
    ");
    $stmt->execute([$student_id, '%' . $filename]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        die("Access denied - file does not belong to you");
    }
    
} catch (PDOException $e) {
    error_log("Error checking file permissions: " . $e->getMessage());
    http_response_code(500);
    die("Database error");
}

// Get file info
$file_info = pathinfo($file_path);
$extension = strtolower($file_info['extension']);

// Set appropriate content type
$content_types = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

$content_type = $content_types[$extension] ?? 'application/octet-stream';

// Set headers
header('Content-Type: ' . $content_type);
header('Content-Length: ' . filesize($file_path));
header('Content-Disposition: inline; filename="' . basename($file) . '"');
header('Cache-Control: private, max-age=3600');

// Output file
readfile($file_path);
exit;
?>
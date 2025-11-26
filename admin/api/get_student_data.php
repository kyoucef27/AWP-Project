<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid student ID']);
    exit();
}

try {
    require_once '../../includes/config.php';
    $pdo = getDBConnection();
    
    // Get student data with user details
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.full_name, u.email, u.created_at,
               s.student_number, s.specialization, s.year_of_study
        FROM users u 
        LEFT JOIN students s ON u.id = s.user_id 
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmt->execute([$_GET['id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        echo json_encode([
            'success' => true,
            'student' => $student
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Student not found'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error fetching student data: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>

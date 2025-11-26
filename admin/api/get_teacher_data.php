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
    echo json_encode(['success' => false, 'error' => 'Invalid teacher ID']);
    exit();
}

try {
    require_once '../../includes/config.php';
    $pdo = getDBConnection();
    
    // Get teacher data with user details
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.full_name, u.email, u.created_at,
               t.teacher_id, t.department, t.position, t.specialization
        FROM users u 
        LEFT JOIN teachers t ON u.id = t.user_id 
        WHERE u.id = ? AND u.role = 'teacher'
    ");
    $stmt->execute([$_GET['id']]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($teacher) {
        echo json_encode([
            'success' => true,
            'teacher' => $teacher
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Teacher not found'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error fetching teacher data: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>

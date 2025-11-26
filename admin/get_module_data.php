<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

require_once '../includes/config.php';

if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Module ID is required']);
    exit();
}

try {
    $pdo = getDBConnection();
    $moduleId = $_GET['id'];
    
    // Get module details
    $stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ?");
    $stmt->execute([$moduleId]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$module) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Module not found']);
        exit();
    }
    
    // If requesting full view with teachers
    if (isset($_GET['view']) && $_GET['view'] === 'full') {
        // Get assigned teachers
        $stmt = $pdo->prepare("
            SELECT tm.*, u.full_name, t.teacher_id, t.department as teacher_department, tm.role as teaching_role
            FROM teacher_modules tm
            JOIN teachers t ON tm.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            WHERE tm.module_id = ?
            ORDER BY u.full_name
        ");
        $stmt->execute([$moduleId]);
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate HTML for full module view
        $html = '
            <div class="module-info">
                <h4>' . htmlspecialchars($module['module_code']) . ' - ' . htmlspecialchars($module['module_name']) . '</h4>
                <p><strong>Department:</strong> ' . htmlspecialchars($module['department']) . '</p>
                <p><strong>Credits:</strong> ' . $module['credits'] . '</p>
                <p><strong>Year Level:</strong> Year ' . $module['year_level'] . '</p>
                <p><strong>Semester:</strong> ' . htmlspecialchars($module['semester']) . '</p>
                <p><strong>Status:</strong> <span class="status-badge ' . ($module['is_active'] ? 'status-active' : 'status-inactive') . '">' . ($module['is_active'] ? 'Active' : 'Inactive') . '</span></p>';
        
        if ($module['description']) {
            $html .= '<p><strong>Description:</strong> ' . htmlspecialchars($module['description']) . '</p>';
        }
        
        $html .= '</div>';
        
        if (!empty($teachers)) {
            $html .= '<h4>Assigned Teachers</h4>';
            $html .= '<ul class="teacher-list">';
            
            foreach ($teachers as $teacher) {
                $html .= '
                    <li class="teacher-item">
                        <div class="teacher-info">
                            <div><strong>' . htmlspecialchars($teacher['full_name']) . '</strong> (' . htmlspecialchars($teacher['teacher_id']) . ')</div>
                            <div class="teacher-role">' . htmlspecialchars($teacher['teaching_role']) . ' - ' . htmlspecialchars($teacher['teacher_department']) . '</div>
                        </div>
                        <form method="POST" style="display: inline;" onsubmit="return confirm(\'Are you sure you want to unassign this teacher?\')">
                            <input type="hidden" name="action" value="unassign_teacher">
                            <input type="hidden" name="assignment_id" value="' . $teacher['id'] . '">
                            <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                        </form>
                    </li>';
            }
            
            $html .= '</ul>';
        } else {
            $html .= '<p><em>No teachers assigned to this module yet.</em></p>';
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'html' => $html]);
    } else {
        // Just return module data for editing
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'module' => $module]);
    }
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
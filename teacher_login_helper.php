<!DOCTYPE html>
<html>
<head>
    <title>Teacher Login Helper</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .section h3 { margin-top: 0; color: #333; }
        .teacher-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; }
        .teacher-card { border: 1px solid #ddd; padding: 10px; border-radius: 5px; background: #f9f9f9; }
        .success { color: green; }
        .error { color: red; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #005a87; }
    </style>
</head>
<body>
    <h1>Teacher Login Helper</h1>

<?php
require_once 'includes/config.php';
$pdo = getDBConnection();

// Handle password reset
if ($_POST && isset($_POST['reset_password'])) {
    try {
        $user_id = $_POST['user_id'];
        $new_password = password_hash('123456', PASSWORD_DEFAULT); // Default password: 123456
        
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$new_password, $user_id]);
        
        echo "<div class='section'><span class='success'>✓ Password reset to '123456' for user ID: $user_id</span></div>";
    } catch (Exception $e) {
        echo "<div class='section'><span class='error'>✗ Error: " . $e->getMessage() . "</span></div>";
    }
}

echo "<div class='section'>";
echo "<h3>Teachers and Their Module Assignments</h3>";

try {
    $stmt = $pdo->query('
        SELECT 
            u.id as user_id,
            u.username,
            u.full_name,
            t.id as teacher_id,
            COUNT(tm.module_id) as assigned_modules
        FROM users u 
        JOIN teachers t ON u.id = t.user_id 
        LEFT JOIN teacher_modules tm ON t.id = tm.teacher_id
        WHERE u.role = "teacher"
        GROUP BY u.id, u.username, u.full_name, t.id
        ORDER BY u.username
    ');
    $teachers = $stmt->fetchAll();
    
    echo "<div class='teacher-list'>";
    foreach ($teachers as $teacher) {
        echo "<div class='teacher-card'>";
        echo "<h4>{$teacher['full_name']}</h4>";
        echo "<strong>Username:</strong> {$teacher['username']}<br>";
        echo "<strong>User ID:</strong> {$teacher['user_id']}<br>";
        echo "<strong>Teacher ID:</strong> {$teacher['teacher_id']}<br>";
        echo "<strong>Assigned Modules:</strong> {$teacher['assigned_modules']}<br>";
        
        // Show modules for this teacher
        if ($teacher['assigned_modules'] > 0) {
            $stmt = $pdo->prepare('
                SELECT m.module_code, m.module_name, tm.role 
                FROM teacher_modules tm 
                JOIN modules m ON tm.module_id = m.id 
                WHERE tm.teacher_id = ?
            ');
            $stmt->execute([$teacher['teacher_id']]);
            $modules = $stmt->fetchAll();
            
            echo "<strong>Modules:</strong><br>";
            foreach ($modules as $module) {
                echo "- {$module['module_code']}: {$module['module_name']} ({$module['role']})<br>";
            }
        }
        
        // Reset password form
        echo "<form method='POST' style='margin-top: 10px;'>";
        echo "<input type='hidden' name='user_id' value='{$teacher['user_id']}'>";
        echo "<button type='submit' name='reset_password' class='btn' onclick='return confirm(\"Reset password to 123456?\")'>Reset Password to 123456</button>";
        echo "</form>";
        
        echo "</div>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<span class='error'>Error: " . $e->getMessage() . "</span>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h3>Quick Login Links</h3>";
echo "<p>After resetting a password above, you can use these links to log in:</p>";
foreach ($teachers as $teacher) {
    if ($teacher['assigned_modules'] > 0) {
        echo "<p><a href='auth/login.php' target='_blank'>Login as {$teacher['full_name']}</a> (Username: <strong>{$teacher['username']}</strong>, Password: <strong>123456</strong> after reset)</p>";
    }
}
echo "</div>";

echo "<div class='section'>";
echo "<h3>Module Assignment Statistics</h3>";

try {
    $stmt = $pdo->query('SELECT COUNT(*) FROM modules WHERE is_active = 1');
    $total_modules = $stmt->fetchColumn();
    
    $stmt = $pdo->query('SELECT COUNT(DISTINCT module_id) FROM teacher_modules');
    $assigned_modules = $stmt->fetchColumn();
    
    $stmt = $pdo->query('SELECT COUNT(*) FROM teacher_modules');
    $total_assignments = $stmt->fetchColumn();
    
    echo "Total active modules: $total_modules<br>";
    echo "Modules with teacher assignments: $assigned_modules<br>";
    echo "Total teacher-module assignments: $total_assignments<br>";
    
} catch (Exception $e) {
    echo "<span class='error'>Error: " . $e->getMessage() . "</span>";
}
echo "</div>";
?>

</body>
</html>
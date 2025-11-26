<!DOCTYPE html>
<html>
<head>
    <title>Teacher Module Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .section h3 { margin-top: 0; color: #333; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 3px; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h1>Teacher Module Relationship Debug</h1>

<?php
require_once 'includes/config.php';
$pdo = getDBConnection();

echo "<div class='section'>";
echo "<h3>CHECKING TEACHERS TABLE</h3>";
try {
    $stmt = $pdo->query('DESCRIBE teachers');
    $columns = $stmt->fetchAll();
    echo "<strong>Teachers table structure:</strong><br>";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})<br>";
    }
    echo "<span class='success'>✓ Teachers table exists</span>";
} catch (Exception $e) {
    echo "<span class='error'>✗ Teachers table: " . $e->getMessage() . "</span>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h3>CHECKING MODULES TABLE</h3>";
try {
    $stmt = $pdo->query('DESCRIBE modules');
    $columns = $stmt->fetchAll();
    echo "<strong>Modules table structure:</strong><br>";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})<br>";
    }
    echo "<span class='success'>✓ Modules table exists</span>";
} catch (Exception $e) {
    echo "<span class='error'>✗ Modules table: " . $e->getMessage() . "</span>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h3>CHECKING FOR TEACHER_MODULES TABLE</h3>";
try {
    $stmt = $pdo->query('DESCRIBE teacher_modules');
    $columns = $stmt->fetchAll();
    echo "<strong>Teacher_modules table structure:</strong><br>";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})<br>";
    }
    echo "<span class='success'>✓ Teacher_modules table exists</span>";
} catch (Exception $e) {
    echo "<span class='error'>✗ Teacher_modules table: " . $e->getMessage() . "</span>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h3>CHECKING CURRENT USER SESSION</h3>";
session_start();
if (isset($_SESSION['user_id'])) {
    echo "Logged in user ID: " . $_SESSION['user_id'] . "<br>";
    echo "Role: " . ($_SESSION['role'] ?? 'Not set') . "<br>";
    
    // Check if user exists in teachers table
    $stmt = $pdo->prepare('SELECT * FROM teachers WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $teacher = $stmt->fetch();
    if ($teacher) {
        echo "<span class='success'>✓ Teacher record found: ID " . $teacher['id'] . "</span><br>";
        
        echo "<h4>MODULE ASSIGNMENTS</h4>";
        
        // Method 1: Direct teacher_id in modules table
        try {
            $stmt = $pdo->prepare('SELECT * FROM modules WHERE teacher_id = ?');
            $stmt->execute([$teacher['id']]);
            $modules = $stmt->fetchAll();
            echo "<strong>Modules with teacher_id = {$teacher['id']}:</strong> " . count($modules) . "<br>";
            if (!empty($modules)) {
                foreach ($modules as $module) {
                    echo "- {$module['module_code']}: {$module['module_name']}<br>";
                }
            }
        } catch (Exception $e) {
            echo "<span class='error'>Error checking modules.teacher_id: " . $e->getMessage() . "</span><br>";
        }
        
        // Method 2: teacher_modules junction table
        try {
            $stmt = $pdo->prepare('
                SELECT m.*, tm.role, tm.assigned_at 
                FROM teacher_modules tm 
                JOIN modules m ON tm.module_id = m.id 
                WHERE tm.teacher_id = ?
            ');
            $stmt->execute([$teacher['id']]);
            $modules = $stmt->fetchAll();
            echo "<strong>Teacher_modules records for teacher_id = {$teacher['id']}:</strong> " . count($modules) . "<br>";
            if (!empty($modules)) {
                foreach ($modules as $module) {
                    echo "- {$module['module_code']}: {$module['module_name']} (Role: {$module['role']})<br>";
                }
            }
        } catch (Exception $e) {
            echo "<span class='error'>Error checking teacher_modules: " . $e->getMessage() . "</span><br>";
        }
        
    } else {
        echo "<span class='error'>✗ No teacher record found for this user</span><br>";
        
        // Check if there are any teachers at all
        $stmt = $pdo->query('SELECT COUNT(*) FROM teachers');
        $count = $stmt->fetchColumn();
        echo "Total teachers in database: $count<br>";
        
        if ($count > 0) {
            echo "<h4>All Teachers:</h4>";
            $stmt = $pdo->query('SELECT t.id, t.user_id, u.full_name, u.username FROM teachers t JOIN users u ON t.user_id = u.id');
            $teachers = $stmt->fetchAll();
            foreach ($teachers as $t) {
                echo "- Teacher ID: {$t['id']}, User ID: {$t['user_id']}, Name: {$t['full_name']} ({$t['username']})<br>";
            }
        }
    }
} else {
    echo "<span class='error'>✗ No user logged in</span><br>";
}
echo "</div>";

// Show all tables in the database
echo "<div class='section'>";
echo "<h3>ALL TABLES IN DATABASE</h3>";
$stmt = $pdo->query('SHOW TABLES');
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    echo "- $table<br>";
}
echo "</div>";

// Check if modules table has teacher_id column
echo "<div class='section'>";
echo "<h3>MODULES TABLE ANALYSIS</h3>";
try {
    $stmt = $pdo->query('SELECT COUNT(*) FROM modules WHERE teacher_id IS NOT NULL');
    $assigned_count = $stmt->fetchColumn();
    
    $stmt = $pdo->query('SELECT COUNT(*) FROM modules');
    $total_count = $stmt->fetchColumn();
    
    echo "Total modules: $total_count<br>";
    echo "Modules with teacher_id assigned: $assigned_count<br>";
    
    if ($assigned_count > 0) {
        echo "<h4>Modules with Teachers:</h4>";
        $stmt = $pdo->query('SELECT module_code, module_name, teacher_id FROM modules WHERE teacher_id IS NOT NULL LIMIT 10');
        $modules = $stmt->fetchAll();
        foreach ($modules as $module) {
            echo "- {$module['module_code']}: {$module['module_name']} (Teacher ID: {$module['teacher_id']})<br>";
        }
    }
} catch (Exception $e) {
    echo "<span class='error'>Error analyzing modules: " . $e->getMessage() . "</span><br>";
    
    // Check if teacher_id column exists
    $stmt = $pdo->query('DESCRIBE modules');
    $columns = $stmt->fetchAll();
    $has_teacher_id = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'teacher_id') {
            $has_teacher_id = true;
            break;
        }
    }
    
    if (!$has_teacher_id) {
        echo "<span class='error'>✗ modules table does not have teacher_id column!</span><br>";
        echo "<strong>SOLUTION: Add teacher_id column to modules table</strong><br>";
    }
}
echo "</div>";
?>

</body>
</html>
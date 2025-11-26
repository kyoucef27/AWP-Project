<?php
// Sample module data to populate the database for testing
require_once 'includes/config.php';

echo "<h2>Adding Sample Modules and Assignments</h2>";

try {
    $pdo = getDBConnection();
    
    // Sample modules data
    $modules = [
        ['CS101', 'Introduction to Programming', 'Basic programming concepts using Python', 3, 'Computer Science', 1, 'Both'],
        ['CS102', 'Data Structures and Algorithms', 'Fundamental data structures and algorithms', 4, 'Computer Science', 2, 'Fall'],
        ['CS201', 'Object-Oriented Programming', 'Advanced programming using Java', 3, 'Computer Science', 2, 'Spring'],
        ['CS301', 'Database Systems', 'Design and implementation of database systems', 4, 'Computer Science', 3, 'Both'],
        ['CS302', 'Web Development', 'Modern web development technologies', 3, 'Computer Science', 3, 'Spring'],
        ['IS101', 'Information Systems Fundamentals', 'Introduction to information systems', 3, 'Information Systems', 1, 'Fall'],
        ['IS201', 'Systems Analysis and Design', 'Analysis and design of information systems', 4, 'Information Systems', 2, 'Spring'],
        ['MATH101', 'Calculus I', 'Differential calculus', 4, 'Mathematics', 1, 'Both'],
        ['MATH102', 'Linear Algebra', 'Vectors, matrices, and linear transformations', 3, 'Mathematics', 2, 'Fall'],
        ['SE301', 'Software Engineering', 'Software development methodologies', 4, 'Software Engineering', 3, 'Both'],
    ];
    
    echo "<p>Inserting sample modules...</p>";
    
    foreach ($modules as $module) {
        try {
            $stmt = $pdo->prepare("INSERT INTO modules (module_code, module_name, description, credits, department, year_level, semester, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute($module);
            echo "<p>✅ Added module: {$module[0]} - {$module[1]}</p>";
        } catch (PDOException $e) {
            if ($e->errorInfo[1] === 1062) { // Duplicate entry
                echo "<p>⚠️ Module {$module[0]} already exists</p>";
            } else {
                echo "<p>❌ Error adding module {$module[0]}: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    // Get teachers and modules for sample assignments
    $stmt = $pdo->query("SELECT id FROM teachers LIMIT 5");
    $teachers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->query("SELECT id FROM modules LIMIT 10");
    $moduleIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($teachers) && !empty($moduleIds)) {
        echo "<p>Creating sample teacher-module assignments...</p>";
        
        $roles = ['Lecturer', 'Assistant', 'Coordinator'];
        $assignments_created = 0;
        
        // Create some random assignments
        for ($i = 0; $i < min(15, count($teachers) * 3); $i++) {
            $teacher_id = $teachers[array_rand($teachers)];
            $module_id = $moduleIds[array_rand($moduleIds)];
            $role = $roles[array_rand($roles)];
            
            try {
                $stmt = $pdo->prepare("INSERT IGNORE INTO teacher_modules (teacher_id, module_id, role) VALUES (?, ?, ?)");
                if ($stmt->execute([$teacher_id, $module_id, $role])) {
                    if ($stmt->rowCount() > 0) {
                        $assignments_created++;
                        echo "<p>✅ Assigned teacher ID {$teacher_id} to module ID {$module_id} as {$role}</p>";
                    }
                }
            } catch (PDOException $e) {
                echo "<p>⚠️ Assignment already exists or error: " . $e->getMessage() . "</p>";
            }
        }
        
        echo "<p><strong>Summary:</strong> Created {$assignments_created} new teacher-module assignments.</p>";
    } else {
        echo "<p>⚠️ No teachers or modules found for creating assignments. Please add teachers first.</p>";
    }
    
    echo "<hr>";
    echo "<h3>✅ Sample data setup completed!</h3>";
    echo "<p><a href='admin/dashboard.php' style='background: #007cba; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>Go to Admin Dashboard</a></p>";
    echo "<p><a href='admin/module_management.php' style='background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>Manage Modules</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>
<?php
require_once 'includes/config.php';

echo "<h1>Create Sample Enrollments</h1>";

try {
    $pdo = getDBConnection();
    
    // Check if we have students and modules
    $student_count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $module_count = $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
    $enrollment_count = $pdo->query("SELECT COUNT(*) FROM enrollments")->fetchColumn();
    
    echo "<div style='background: #f0f8ff; padding: 15px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px;'>";
    echo "<h3>Current Status:</h3>";
    echo "<p>📊 Students: $student_count</p>";
    echo "<p>📚 Modules: $module_count</p>";
    echo "<p>✅ Enrollments: $enrollment_count</p>";
    echo "</div>";
    
    if ($student_count == 0) {
        echo "<p style='color: red;'>❌ No students found! Please create students first using the admin panel.</p>";
        exit;
    }
    
    if ($module_count == 0) {
        echo "<p style='color: red;'>❌ No modules found! Please run setup_sample_modules.php first.</p>";
        exit;
    }
    
    // Get all students
    $stmt = $pdo->query("SELECT id, student_number FROM students ORDER BY id");
    $students = $stmt->fetchAll();
    
    // Get all modules
    $stmt = $pdo->query("SELECT id, module_code, module_name FROM modules ORDER BY id");
    $modules = $stmt->fetchAll();
    
    echo "<h2>Creating Sample Enrollments...</h2>";
    
    $enrollments_created = 0;
    $specialties = ['Computer Science', 'Information Systems', 'Software Engineering'];
    
    // Create enrollments for each student in multiple modules
    foreach ($students as $student) {
        // Each student gets enrolled in 3-5 random modules
        $num_modules = rand(3, 5);
        $selected_modules = array_rand($modules, min($num_modules, count($modules)));
        
        if (!is_array($selected_modules)) {
            $selected_modules = [$selected_modules];
        }
        
        foreach ($selected_modules as $module_index) {
            $module = $modules[$module_index];
            
            try {
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO enrollments (student_id, module_id, status, enrollment_date) 
                    VALUES (?, ?, 'active', NOW())
                ");
                
                if ($stmt->execute([$student['id'], $module['id']])) {
                    if ($stmt->rowCount() > 0) {
                        $enrollments_created++;
                        echo "<p>✅ Enrolled student {$student['student_number']} in {$module['module_code']} - {$module['module_name']}</p>";
                    }
                }
            } catch (PDOException $e) {
                echo "<p style='color: orange;'>⚠️ Student {$student['student_number']} already enrolled in {$module['module_code']}</p>";
            }
        }
    }
    
    echo "<div style='background: #d4edda; padding: 15px; margin: 20px 0; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<h3>✅ Enrollment Creation Complete!</h3>";
    echo "<p><strong>Total new enrollments created: $enrollments_created</strong></p>";
    echo "</div>";
    
    // Show final statistics
    $final_enrollment_count = $pdo->query("SELECT COUNT(*) FROM enrollments")->fetchColumn();
    
    echo "<h3>📊 Final Statistics</h3>";
    echo "<table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
    echo "<tr style='background: #f2f2f2;'><th style='border: 1px solid #ddd; padding: 8px;'>Module</th><th style='border: 1px solid #ddd; padding: 8px;'>Enrolled Students</th></tr>";
    
    foreach ($modules as $module) {
        $count = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE module_id = ? AND status = 'active'");
        $count->execute([$module['id']]);
        $enrolled_count = $count->fetchColumn();
        
        echo "<tr>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$module['module_code']} - {$module['module_name']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px; text-align: center;'>$enrolled_count</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div style='margin: 30px 0;'>";
    echo "<h3>🎯 Next Steps:</h3>";
    echo "<ol>";
    echo "<li><a href='debug_enrollments.php' style='color: #007cba;'>View enrollment debug report</a> to verify data</li>";
    echo "<li><a href='teacher/sessions.php' style='color: #28a745;'>Go to teacher sessions</a> to test attendance</li>";
    echo "<li><a href='teacher/mark_attendance.php' style='color: #dc3545;'>Test marking attendance</a> (make sure you're logged in as a teacher)</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    line-height: 1.6;
}
h1, h2, h3 {
    color: #333;
}
p {
    margin: 5px 0;
}
a {
    text-decoration: none;
    padding: 5px 10px;
    border-radius: 3px;
    display: inline-block;
    margin: 2px;
}
</style>
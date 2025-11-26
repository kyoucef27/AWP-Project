<?php
/**
 * Test Script: Student Enrollment Debugging
 * This script checks the database structure and creates test data if needed
 */

require_once 'includes/config.php';

echo "<h1>Student Enrollment Test & Debug</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .section { background: #f5f5f5; padding: 15px; margin: 15px 0; border-radius: 5px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #4CAF50; color: white; }
    .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; 
           border-radius: 5px; display: inline-block; margin: 5px; border: none; cursor: pointer; }
    .btn:hover { background: #0056b3; }
</style>";

try {
    $pdo = getDBConnection();
    echo "<div class='section success'>✅ Database connection successful</div>";
    
    // Step 1: Check if tables exist
    echo "<div class='section'>";
    echo "<h2>Step 1: Table Structure Check</h2>";
    
    $tables = ['users', 'students', 'modules', 'student_groups', 'student_group_assignments', 
               'module_group_assignments', 'enrollments', 'attendance', 'absence_justifications'];
    
    echo "<table><tr><th>Table Name</th><th>Exists</th><th>Row Count</th></tr>";
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "<tr><td>$table</td><td class='success'>✅ Yes</td><td>$count</td></tr>";
        } catch (PDOException $e) {
            echo "<tr><td>$table</td><td class='error'>❌ No</td><td>-</td></tr>";
        }
    }
    echo "</table></div>";
    
    // Step 2: Check students
    echo "<div class='section'>";
    echo "<h2>Step 2: Students Check</h2>";
    $stmt = $pdo->query("
        SELECT s.id, s.student_number, s.year_of_study, s.specialization, 
               u.username, u.full_name, u.role,
               (SELECT COUNT(*) FROM student_group_assignments WHERE student_id = s.id) as has_group,
               (SELECT COUNT(*) FROM enrollments WHERE student_id = s.id) as enrollment_count
        FROM students s
        JOIN users u ON s.user_id = u.id
        LIMIT 10
    ");
    $students = $stmt->fetchAll();
    
    if (empty($students)) {
        echo "<p class='warning'>⚠️ No students found in database</p>";
        echo "<form method='POST'>";
        echo "<input type='hidden' name='action' value='create_test_student'>";
        echo "<button type='submit' class='btn'>Create Test Student</button>";
        echo "</form>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Student #</th><th>Year</th><th>Specialization</th><th>Has Group</th><th>Enrollments</th></tr>";
        foreach ($students as $student) {
            $group_status = $student['has_group'] > 0 ? "✅ Yes" : "❌ No";
            $enroll_status = $student['enrollment_count'] > 0 ? $student['enrollment_count'] : "❌ 0";
            echo "<tr>";
            echo "<td>{$student['id']}</td>";
            echo "<td>{$student['username']}</td>";
            echo "<td>{$student['full_name']}</td>";
            echo "<td>{$student['student_number']}</td>";
            echo "<td>{$student['year_of_study']}</td>";
            echo "<td>{$student['specialization']}</td>";
            echo "<td>$group_status</td>";
            echo "<td>$enroll_status</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Step 3: Check modules
    echo "<div class='section'>";
    echo "<h2>Step 3: Modules Check</h2>";
    $stmt = $pdo->query("
        SELECT m.id, m.module_code, m.module_name, m.year_level, m.is_active,
               (SELECT COUNT(*) FROM module_group_assignments WHERE module_id = m.id) as assigned_groups,
               (SELECT COUNT(*) FROM enrollments WHERE module_id = m.id) as enrolled_students
        FROM modules m
        LIMIT 10
    ");
    $modules = $stmt->fetchAll();
    
    if (empty($modules)) {
        echo "<p class='warning'>⚠️ No modules found in database</p>";
        echo "<form method='POST'>";
        echo "<input type='hidden' name='action' value='create_test_module'>";
        echo "<button type='submit' class='btn'>Create Test Module</button>";
        echo "</form>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Code</th><th>Name</th><th>Year</th><th>Active</th><th>Assigned Groups</th><th>Enrolled Students</th></tr>";
        foreach ($modules as $module) {
            $active = $module['is_active'] ? "✅ Yes" : "❌ No";
            $groups = $module['assigned_groups'] > 0 ? "✅ {$module['assigned_groups']}" : "❌ 0";
            $enrolled = $module['enrolled_students'] > 0 ? "✅ {$module['enrolled_students']}" : "❌ 0";
            echo "<tr>";
            echo "<td>{$module['id']}</td>";
            echo "<td>{$module['module_code']}</td>";
            echo "<td>{$module['module_name']}</td>";
            echo "<td>{$module['year_level']}</td>";
            echo "<td>$active</td>";
            echo "<td>$groups</td>";
            echo "<td>$enrolled</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Step 4: Check groups
    echo "<div class='section'>";
    echo "<h2>Step 4: Student Groups Check</h2>";
    $stmt = $pdo->query("
        SELECT sg.id, sg.group_name, sg.year_level, sg.specialization, sg.current_count,
               (SELECT COUNT(*) FROM student_group_assignments WHERE group_id = sg.id) as actual_students,
               (SELECT COUNT(*) FROM module_group_assignments WHERE group_id = sg.id) as assigned_modules
        FROM student_groups sg
        LIMIT 10
    ");
    $groups = $stmt->fetchAll();
    
    if (empty($groups)) {
        echo "<p class='warning'>⚠️ No groups found in database</p>";
        echo "<form method='POST'>";
        echo "<input type='hidden' name='action' value='create_test_group'>";
        echo "<button type='submit' class='btn'>Create Test Group</button>";
        echo "</form>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Group Name</th><th>Year</th><th>Specialization</th><th>Students</th><th>Assigned Modules</th></tr>";
        foreach ($groups as $group) {
            $students_status = $group['actual_students'] > 0 ? "✅ {$group['actual_students']}" : "❌ 0";
            $modules_status = $group['assigned_modules'] > 0 ? "✅ {$group['assigned_modules']}" : "❌ 0";
            echo "<tr>";
            echo "<td>{$group['id']}</td>";
            echo "<td>{$group['group_name']}</td>";
            echo "<td>{$group['year_level']}</td>";
            echo "<td>{$group['specialization']}</td>";
            echo "<td>$students_status</td>";
            echo "<td>$modules_status</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Step 5: Check enrollments
    echo "<div class='section'>";
    echo "<h2>Step 5: Enrollments Check</h2>";
    $stmt = $pdo->query("
        SELECT e.id, s.student_number, u.full_name as student_name, 
               m.module_code, m.module_name, e.status, e.enrollment_date
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN modules m ON e.module_id = m.id
        ORDER BY e.enrollment_date DESC
        LIMIT 20
    ");
    $enrollments = $stmt->fetchAll();
    
    if (empty($enrollments)) {
        echo "<p class='warning'>⚠️ No enrollments found in database</p>";
        echo "<p>Students need to be enrolled in modules to see courses.</p>";
        echo "<form method='POST'>";
        echo "<input type='hidden' name='action' value='create_test_enrollment'>";
        echo "<button type='submit' class='btn'>Create Test Enrollment</button>";
        echo "</form>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Student #</th><th>Student Name</th><th>Module Code</th><th>Module Name</th><th>Status</th><th>Date</th></tr>";
        foreach ($enrollments as $enrollment) {
            echo "<tr>";
            echo "<td>{$enrollment['id']}</td>";
            echo "<td>{$enrollment['student_number']}</td>";
            echo "<td>{$enrollment['student_name']}</td>";
            echo "<td>{$enrollment['module_code']}</td>";
            echo "<td>{$enrollment['module_name']}</td>";
            echo "<td>{$enrollment['status']}</td>";
            echo "<td>{$enrollment['enrollment_date']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Handle POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        echo "<div class='section'>";
        echo "<h2>Action Result</h2>";
        
        switch ($_POST['action']) {
            case 'create_test_student':
                // Create test student
                $username = 'teststudent_' . time();
                $password = password_hash('test123', PASSWORD_DEFAULT);
                
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email, full_name) VALUES (?, ?, 'student', ?, ?)");
                $stmt->execute([$username, $password, $username.'@test.com', 'Test Student']);
                $user_id = $pdo->lastInsertId();
                
                $stmt = $pdo->prepare("INSERT INTO students (user_id, student_number, specialization, year_of_study) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, 'STU'.time(), 'Computer Science', 3]);
                $pdo->commit();
                
                echo "<p class='success'>✅ Created test student: $username (password: test123)</p>";
                echo "<p><a href='test_student_enrollment.php' class='btn'>Refresh Page</a></p>";
                break;
                
            case 'create_test_module':
                $code = 'TEST' . time();
                $stmt = $pdo->prepare("INSERT INTO modules (module_code, module_name, description, credits, year_level, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute([$code, 'Test Module', 'Test module for debugging', 3, 3]);
                
                echo "<p class='success'>✅ Created test module: $code</p>";
                echo "<p><a href='test_student_enrollment.php' class='btn'>Refresh Page</a></p>";
                break;
                
            case 'create_test_group':
                $group_name = 'Test Group ' . time();
                $stmt = $pdo->prepare("INSERT INTO student_groups (group_name, year_level, specialization, max_capacity) VALUES (?, ?, ?, ?)");
                $stmt->execute([$group_name, 3, 'Computer Science', 30]);
                
                echo "<p class='success'>✅ Created test group: $group_name</p>";
                echo "<p><a href='test_student_enrollment.php' class='btn'>Refresh Page</a></p>";
                break;
                
            case 'create_test_enrollment':
                // Get first student and first module
                $student = $pdo->query("SELECT id FROM students LIMIT 1")->fetch();
                $module = $pdo->query("SELECT id FROM modules LIMIT 1")->fetch();
                
                if ($student && $module) {
                    $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, module_id, status) VALUES (?, ?, 'active')");
                    $stmt->execute([$student['id'], $module['id']]);
                    
                    echo "<p class='success'>✅ Created test enrollment</p>";
                    echo "<p><a href='test_student_enrollment.php' class='btn'>Refresh Page</a></p>";
                } else {
                    echo "<p class='error'>❌ Need both a student and a module to create enrollment</p>";
                }
                break;
        }
        echo "</div>";
    }
    
    // Diagnosis
    echo "<div class='section'>";
    echo "<h2>📋 Diagnosis</h2>";
    
    $issues = [];
    
    // Check if we have students
    $student_count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    if ($student_count == 0) {
        $issues[] = "❌ No students in database - create students first";
    }
    
    // Check if we have modules
    $module_count = $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
    if ($module_count == 0) {
        $issues[] = "❌ No modules in database - create modules first";
    }
    
    // Check if we have enrollments
    $enrollment_count = $pdo->query("SELECT COUNT(*) FROM enrollments")->fetchColumn();
    if ($enrollment_count == 0) {
        $issues[] = "❌ No enrollments in database - students need to be enrolled in modules";
    }
    
    // Check if module_group_assignments table exists and has data
    try {
        $mga_count = $pdo->query("SELECT COUNT(*) FROM module_group_assignments")->fetchColumn();
        if ($mga_count == 0) {
            $issues[] = "⚠️ No module-group assignments - modules need to be linked to groups";
        }
    } catch (PDOException $e) {
        $issues[] = "❌ module_group_assignments table doesn't exist - run setup_database.php again";
    }
    
    if (empty($issues)) {
        echo "<p class='success'>✅ All checks passed! Students should be able to see their enrolled courses.</p>";
        echo "<p><strong>Test Login:</strong> Use any student username from the table above with their password.</p>";
    } else {
        echo "<ul>";
        foreach ($issues as $issue) {
            echo "<li>$issue</li>";
        }
        echo "</ul>";
    }
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>🔧 Quick Actions</h2>";
    echo "<p><a href='setup_database.php' class='btn'>Run Database Setup</a></p>";
    echo "<p><a href='admin/student_management/student_management.php' class='btn'>Manage Students</a></p>";
    echo "<p><a href='admin/module_management/module_management.php' class='btn'>Manage Modules</a></p>";
    echo "<p><a href='student/home.php' class='btn'>Go to Student Portal</a></p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='section error'>";
    echo "<h2>❌ Database Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>

<?php
// Simple test script to check what's wrong with attendance
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Attendance Debug Test</h2>";

try {
    require_once '../includes/config.php';
    echo "<p>✓ Config loaded successfully</p>";
    
    $pdo = getDBConnection();
    echo "<p>✓ Database connection established</p>";
    
    if (!isset($_SESSION['user_id'])) {
        echo "<p style='color: red;'>✗ No user session found. Please login first.</p>";
        echo "<p><a href='../auth/login.php'>Login</a></p>";
        exit;
    }
    
    echo "<p>✓ User session found: " . $_SESSION['user_id'] . " (" . $_SESSION['role'] . ")</p>";
    
    if ($_SESSION['role'] !== 'student') {
        echo "<p style='color: orange;'>Warning: User role is not 'student'. Current role: " . $_SESSION['role'] . "</p>";
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Test student lookup
    $stmt = $pdo->prepare("SELECT s.id, u.full_name FROM students s JOIN users u ON s.user_id = u.id WHERE s.user_id = ?");
    $stmt->execute([$user_id]);
    $student_record = $stmt->fetch();
    
    if ($student_record) {
        echo "<p>✓ Student record found: ID {$student_record['id']}, Name: {$student_record['full_name']}</p>";
        $student_id = $student_record['id'];
    } else {
        echo "<p style='color: red;'>✗ No student record found for user_id: $user_id</p>";
        echo "<p>Available students:</p>";
        
        $stmt = $pdo->query("SELECT s.id, u.full_name, s.user_id, u.username FROM students s LEFT JOIN users u ON s.user_id = u.id");
        $all_students = $stmt->fetchAll();
        
        echo "<ul>";
        foreach ($all_students as $student) {
            echo "<li>ID: {$student['id']}, Name: {$student['full_name']}, User ID: {$student['user_id']}, Username: {$student['username']}</li>";
        }
        echo "</ul>";
        exit;
    }
    
    // Test enrolled modules
    $stmt = $pdo->prepare("
        SELECT DISTINCT m.id, m.module_name, m.module_code 
        FROM enrollments e 
        JOIN modules m ON e.module_id = m.id 
        WHERE e.student_id = ? AND e.status IN ('active', 'completed')
        ORDER BY m.module_name
    ");
    $stmt->execute([$student_id]);
    $enrolled_modules = $stmt->fetchAll();
    
    echo "<p>✓ Found " . count($enrolled_modules) . " enrolled modules:</p>";
    echo "<ul>";
    foreach ($enrolled_modules as $module) {
        echo "<li>ID: {$module['id']}, Code: {$module['module_code']}, Name: {$module['module_name']}</li>";
    }
    echo "</ul>";
    
    if (count($enrolled_modules) == 0) {
        echo "<p style='color: orange;'>Warning: No enrolled modules found. Running bulk enrollment might be needed.</p>";
    }
    
    // Test attendance records query
    $query = "
        SELECT 
            a.id as attendance_id,
            a.attendance_date as date,
            a.status,
            a.remarks,
            m.id as module_id,
            m.module_name,
            m.module_code,
            aj.id as justification_id,
            aj.justification_text,
            aj.status as justification_status
        FROM attendance a
        JOIN enrollments e ON a.enrollment_id = e.id
        JOIN modules m ON e.module_id = m.id
        LEFT JOIN absence_justifications aj ON a.id = aj.attendance_id
        WHERE e.student_id = ?
        ORDER BY a.attendance_date DESC
        LIMIT 10
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$student_id]);
    $attendance_records = $stmt->fetchAll();
    
    echo "<p>✓ Found " . count($attendance_records) . " attendance records (showing first 10):</p>";
    
    if (count($attendance_records) > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Date</th><th>Module</th><th>Status</th><th>Remarks</th><th>Justification</th></tr>";
        
        foreach ($attendance_records as $record) {
            echo "<tr>";
            echo "<td>" . $record['date'] . "</td>";
            echo "<td>" . $record['module_code'] . " - " . $record['module_name'] . "</td>";
            echo "<td>" . $record['status'] . "</td>";
            echo "<td>" . ($record['remarks'] ?: '-') . "</td>";
            echo "<td>" . ($record['justification_id'] ? "ID: " . $record['justification_id'] . " (" . $record['justification_status'] . ")" : '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>No attendance records found. You may need to generate sample attendance data.</p>";
    }
    
    // Test absence_justifications table
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM absence_justifications");
        $just_count = $stmt->fetchColumn();
        echo "<p>✓ absence_justifications table exists with $just_count records</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ absence_justifications table error: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
    echo "<h3>Test Results Summary</h3>";
    
    if (count($enrolled_modules) == 0) {
        echo "<p><strong>Issue:</strong> No enrolled modules. <a href='../admin/utilities/bulk_enrollment.php'>Run Bulk Enrollment</a></p>";
    }
    
    if (count($attendance_records) == 0) {
        echo "<p><strong>Issue:</strong> No attendance records. <a href='../admin/generate_sample_attendance.php'>Generate Sample Attendance</a></p>";
    }
    
    if (count($enrolled_modules) > 0 && count($attendance_records) > 0) {
        echo "<p style='color: green;'><strong>All good!</strong> The attendance system should work. <a href='attendance.php'>Try Attendance Page</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; }
table { margin: 10px 0; }
th { background: #f0f0f0; }
</style>
<?php
require_once 'includes/config.php';
session_start();

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

echo "<h1>Teacher Session & Enrollment Debug</h1>";

// Check current user
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ Not logged in! Please <a href='auth/login.php'>login</a> as a teacher.</p>";
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'unknown';

echo "<div style='background: #e7f3ff; padding: 15px; margin: 10px 0; border: 1px solid #b3d9ff; border-radius: 5px;'>";
echo "<h3>Current Login Status:</h3>";
echo "<p>👤 User ID: $user_id</p>";
echo "<p>🎭 Role: $role</p>";
echo "</div>";

if ($role !== 'teacher') {
    echo "<p style='color: orange;'>⚠️ You're logged in as '$role', not 'teacher'. This might be why you can't see students.</p>";
    echo "<p>Use <a href='teacher_login_helper.php'>teacher login helper</a> to log in as a teacher.</p>";
}

// Get teacher info if logged in as teacher
$teacher_id = null;
if ($role === 'teacher') {
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $teacher_data = $stmt->fetch();
    $teacher_id = $teacher_data['id'] ?? null;
    echo "<p>🧑‍🏫 Teacher ID: $teacher_id</p>";
}

// Check today's sessions and their enrollments
echo "<h2>📅 Today's Sessions Analysis</h2>";

$stmt = $pdo->query("
    SELECT 
        ts.id as session_id,
        ts.teacher_id,
        ts.module_id,
        ts.session_type,
        ts.session_date,
        ts.start_time,
        ts.end_time,
        ts.location,
        m.module_code,
        m.module_name,
        COUNT(e.id) as enrolled_students,
        (ts.teacher_id = " . ($teacher_id ?? 0) . ") as is_your_session
    FROM teaching_sessions ts
    JOIN modules m ON ts.module_id = m.id
    LEFT JOIN enrollments e ON m.id = e.module_id AND e.status = 'active'
    WHERE ts.session_date = CURDATE()
    GROUP BY ts.id
    ORDER BY ts.start_time
");

$sessions = $stmt->fetchAll();

if (empty($sessions)) {
    echo "<p>❌ No sessions found for today.</p>";
} else {
    echo "<table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
    echo "<tr style='background: #f2f2f2;'>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Session ID</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Module</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Time</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Enrolled Students</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Your Session?</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Teacher ID</th>";
    echo "</tr>";
    
    foreach ($sessions as $session) {
        $bg_color = $session['is_your_session'] ? '#d4edda' : '#fff';
        echo "<tr style='background: $bg_color;'>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$session['session_id']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$session['module_code']} - {$session['module_name']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$session['start_time']} - {$session['end_time']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$session['enrolled_students']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . ($session['is_your_session'] ? '✅ YES' : '❌ NO') . "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$session['teacher_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test specific session attendance query
if ($teacher_id && !empty($sessions)) {
    echo "<h2>🧪 Attendance Test for Your Sessions</h2>";
    
    foreach ($sessions as $session) {
        if ($session['is_your_session']) {
            $session_id = $session['session_id'];
            $module_id = $session['module_id'];
            
            echo "<h3>Session {$session_id}: {$session['module_code']}</h3>";
            
            // Test the actual query used in mark_attendance.php
            $stmt = $pdo->prepare("
                SELECT 
                    e.id as enrollment_id,
                    s.id as student_id,
                    s.student_number,
                    u.full_name as student_name,
                    s.specialty,
                    a.status as current_status
                FROM enrollments e
                JOIN students s ON e.student_id = s.id
                JOIN users u ON s.user_id = u.id
                LEFT JOIN attendance a ON e.id = a.enrollment_id AND a.session_id = ?
                WHERE e.module_id = ? AND e.status = 'active'
                ORDER BY u.full_name
            ");
            $stmt->execute([$session_id, $module_id]);
            $students = $stmt->fetchAll();
            
            echo "<p><strong>Students found for this session: " . count($students) . "</strong></p>";
            
            if (count($students) > 0) {
                echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
                echo "<tr style='background: #f2f2f2;'>";
                echo "<th style='border: 1px solid #ddd; padding: 8px;'>Student #</th>";
                echo "<th style='border: 1px solid #ddd; padding: 8px;'>Name</th>";
                echo "<th style='border: 1px solid #ddd; padding: 8px;'>Specialty</th>";
                echo "<th style='border: 1px solid #ddd; padding: 8px;'>Current Status</th>";
                echo "</tr>";
                
                foreach ($students as $student) {
                    echo "<tr>";
                    echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$student['student_number']}</td>";
                    echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$student['student_name']}</td>";
                    echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$student['specialty']}</td>";
                    echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . ($student['current_status'] ?: 'Not marked') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
                echo "<p style='background: #d4edda; padding: 10px; border-radius: 5px;'>✅ This session should work for attendance! You should see these students in mark_attendance.php</p>";
            } else {
                echo "<p style='background: #f8d7da; padding: 10px; border-radius: 5px;'>❌ No students found for this session. Check if there are enrollments for module ID $module_id</p>";
            }
        }
    }
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
</style>
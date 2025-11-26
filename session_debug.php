<?php
session_start();
require_once 'includes/config.php';

$pdo = getDBConnection();

echo "<h1>🔍 Session Ownership Debug</h1>";

// Check current session
echo "<div style='background: #e7f3ff; padding: 15px; margin: 10px 0; border: 1px solid #b3d9ff; border-radius: 5px;'>";
echo "<h3>Current Login:</h3>";
if (isset($_SESSION['user_id'])) {
    echo "<p>👤 User ID: {$_SESSION['user_id']}</p>";
    echo "<p>🎭 Role: " . ($_SESSION['role'] ?? 'unknown') . "</p>";
    
    if ($_SESSION['role'] === 'teacher') {
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $teacher = $stmt->fetch();
        if ($teacher) {
            echo "<p>🧑‍🏫 Teacher ID: {$teacher['id']}</p>";
        } else {
            echo "<p style='color: red;'>❌ No teacher record found for this user!</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ You're not logged in as a teacher!</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Not logged in!</p>";
}
echo "</div>";

// Show all today's sessions and their owners
echo "<h2>📅 All Today's Sessions</h2>";

$stmt = $pdo->query("
    SELECT 
        ts.id,
        ts.teacher_id,
        ts.session_type,
        ts.session_date,
        ts.start_time,
        ts.end_time,
        ts.location,
        m.module_code,
        m.module_name,
        u.full_name as teacher_name,
        COUNT(e.id) as enrolled_students
    FROM teaching_sessions ts
    JOIN modules m ON ts.module_id = m.id
    JOIN teachers t ON ts.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
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
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Teacher</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Teacher ID</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Time</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Students</th>";
    echo "</tr>";
    
    foreach ($sessions as $session) {
        echo "<tr>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$session['id']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$session['module_code']} - {$session['module_name']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$session['teacher_name']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$session['teacher_id']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$session['start_time']} - {$session['end_time']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$session['enrolled_students']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Show solution
echo "<div style='background: #fff3cd; padding: 15px; margin: 20px 0; border: 1px solid #ffeaa7; border-radius: 5px;'>";
echo "<h3>🎯 Solutions:</h3>";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    echo "<p><strong>Option 1:</strong> <a href='teacher_login_helper.php' style='color: #007cba;'>Use Teacher Login Helper</a> to log in as a teacher</p>";
    echo "<p><strong>Option 2:</strong> <a href='auth/logout.php' style='color: #dc3545;'>Logout</a> and log in with a teacher account</p>";
} else {
    echo "<p><strong>Issue:</strong> You're logged in as a teacher, but the sessions belong to different teachers.</p>";
    echo "<p><strong>Option 1:</strong> Create new sessions for your teacher account</p>";
    echo "<p><strong>Option 2:</strong> Use the Teacher Login Helper to switch to the teacher who owns these sessions</p>";
}

echo "</div>";

// Quick teacher account switcher
echo "<h2>👥 Available Teachers</h2>";
$stmt = $pdo->query("
    SELECT t.id, u.full_name, u.username 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY u.full_name
");
$teachers = $stmt->fetchAll();

if (!empty($teachers)) {
    echo "<p>Available teacher accounts (use Teacher Login Helper to switch):</p>";
    echo "<ul>";
    foreach ($teachers as $teacher) {
        echo "<li>Teacher ID {$teacher['id']}: {$teacher['full_name']} (username: {$teacher['username']})</li>";
    }
    echo "</ul>";
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
a {
    text-decoration: none;
    padding: 5px 10px;
    border-radius: 3px;
    display: inline-block;
    margin: 2px;
}
</style>
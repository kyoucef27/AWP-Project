<?php
require_once 'includes/config.php';

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get all modules with their enrollment counts
$stmt = $pdo->query("
    SELECT 
        m.id,
        m.module_code,
        m.module_name,
        COUNT(e.id) as total_enrollments,
        COUNT(CASE WHEN e.status = 'active' THEN 1 END) as active_enrollments
    FROM modules m
    LEFT JOIN enrollments e ON m.id = e.module_id
    GROUP BY m.id, m.module_code, m.module_name
    ORDER BY m.module_code
");
$modules = $stmt->fetchAll();

// Get recent sessions with their modules
$stmt = $pdo->query("
    SELECT 
        ts.id as session_id,
        ts.session_type,
        ts.session_date,
        ts.start_time,
        ts.end_time,
        ts.location,
        m.module_code,
        m.module_name,
        COUNT(e.id) as enrolled_students
    FROM teaching_sessions ts
    JOIN modules m ON ts.module_id = m.id
    LEFT JOIN enrollments e ON m.id = e.module_id AND e.status = 'active'
    WHERE ts.session_date = CURDATE()
    GROUP BY ts.id
    ORDER BY ts.start_time
");
$today_sessions = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Enrollment Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .no-enrollments { color: red; }
        .has-enrollments { color: green; }
        h2 { margin-top: 30px; }
    </style>
</head>
<body>
    <h1>Enrollment Debug Report</h1>
    
    <h2>All Modules and Their Enrollments</h2>
    <table>
        <tr>
            <th>Module ID</th>
            <th>Code</th>
            <th>Name</th>
            <th>Total Enrollments</th>
            <th>Active Enrollments</th>
            <th>Status</th>
        </tr>
        <?php foreach ($modules as $module): ?>
        <tr>
            <td><?php echo $module['id']; ?></td>
            <td><?php echo htmlspecialchars($module['module_code']); ?></td>
            <td><?php echo htmlspecialchars($module['module_name']); ?></td>
            <td><?php echo $module['total_enrollments']; ?></td>
            <td><?php echo $module['active_enrollments']; ?></td>
            <td class="<?php echo $module['active_enrollments'] > 0 ? 'has-enrollments' : 'no-enrollments'; ?>">
                <?php echo $module['active_enrollments'] > 0 ? 'Has Students' : 'No Students'; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>Today's Sessions</h2>
    <table>
        <tr>
            <th>Session ID</th>
            <th>Module</th>
            <th>Type</th>
            <th>Time</th>
            <th>Location</th>
            <th>Enrolled Students</th>
        </tr>
        <?php foreach ($today_sessions as $session): ?>
        <tr>
            <td><?php echo $session['session_id']; ?></td>
            <td><?php echo htmlspecialchars($session['module_code'] . ' - ' . $session['module_name']); ?></td>
            <td><?php echo $session['session_type']; ?></td>
            <td><?php echo $session['start_time'] . ' - ' . $session['end_time']; ?></td>
            <td><?php echo htmlspecialchars($session['location']); ?></td>
            <td class="<?php echo $session['enrolled_students'] > 0 ? 'has-enrollments' : 'no-enrollments'; ?>">
                <?php echo $session['enrolled_students']; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>Sample Enrollment Data</h2>
    <?php
    $stmt = $pdo->query("
        SELECT 
            e.id,
            e.module_id,
            e.student_id,
            e.status,
            m.module_code,
            s.student_number,
            u.full_name
        FROM enrollments e
        JOIN modules m ON e.module_id = m.id
        JOIN students s ON e.student_id = s.id
        JOIN users u ON s.user_id = u.id
        ORDER BY m.module_code, u.full_name
        LIMIT 20
    ");
    $sample_enrollments = $stmt->fetchAll();
    ?>
    <table>
        <tr>
            <th>Enrollment ID</th>
            <th>Module</th>
            <th>Student Number</th>
            <th>Student Name</th>
            <th>Status</th>
        </tr>
        <?php foreach ($sample_enrollments as $enrollment): ?>
        <tr>
            <td><?php echo $enrollment['id']; ?></td>
            <td><?php echo htmlspecialchars($enrollment['module_code']); ?></td>
            <td><?php echo htmlspecialchars($enrollment['student_number']); ?></td>
            <td><?php echo htmlspecialchars($enrollment['full_name']); ?></td>
            <td><?php echo $enrollment['status']; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
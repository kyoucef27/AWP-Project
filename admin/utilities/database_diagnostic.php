<?php
require_once '../../includes/config.php';

try {
    $pdo = getDBConnection();
    
    echo "<h2>Database Diagnostic Report</h2>";
    echo "<p>Generated on: " . date('Y-m-d H:i:s') . "</p>";
    
    // Check all required tables
    $required_tables = ['users', 'students', 'modules', 'groups', 'enrollments', 'attendance', 'absence_justifications'];
    
    echo "<h3>Table Status</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Table</th><th>Status</th><th>Row Count</th><th>Columns</th></tr>";
    
    foreach ($required_tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                $status = "✓ Exists";
                
                // Get row count
                $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                $row_count = $stmt->fetchColumn();
                
                // Get column info
                $stmt = $pdo->query("DESCRIBE $table");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $column_list = implode(', ', array_column($columns, 'Field'));
                
                echo "<tr>";
                echo "<td>$table</td>";
                echo "<td style='color: green;'>$status</td>";
                echo "<td>$row_count</td>";
                echo "<td style='font-size: 0.8em;'>$column_list</td>";
                echo "</tr>";
            } else {
                echo "<tr>";
                echo "<td>$table</td>";
                echo "<td style='color: red;'>✗ Missing</td>";
                echo "<td>-</td>";
                echo "<td>-</td>";
                echo "</tr>";
            }
        } catch (PDOException $e) {
            echo "<tr>";
            echo "<td>$table</td>";
            echo "<td style='color: red;'>Error: " . $e->getMessage() . "</td>";
            echo "<td>-</td>";
            echo "<td>-</td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
    
    // Check for sample data
    echo "<h3>Sample Data Status</h3>";
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM students");
        $student_count = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM modules");
        $module_count = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM enrollments");
        $enrollment_count = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM attendance");
        $attendance_count = $stmt->fetchColumn();
        
        echo "<ul>";
        echo "<li><strong>Students:</strong> $student_count</li>";
        echo "<li><strong>Modules:</strong> $module_count</li>";
        echo "<li><strong>Enrollments:</strong> $enrollment_count</li>";
        echo "<li><strong>Attendance Records:</strong> $attendance_count</li>";
        echo "</ul>";
        
        if ($student_count == 0) {
            echo "<p style='color: orange;'><strong>Warning:</strong> No students found. You may need to run the setup scripts.</p>";
        }
        
        if ($module_count == 0) {
            echo "<p style='color: orange;'><strong>Warning:</strong> No modules found. You may need to generate sample modules.</p>";
        }
        
        if ($enrollment_count == 0 && $student_count > 0 && $module_count > 0) {
            echo "<p style='color: orange;'><strong>Warning:</strong> Students and modules exist but no enrollments found. You may need to run bulk enrollment.</p>";
        }
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Error checking sample data: " . $e->getMessage() . "</p>";
    }
    
    // Check specialty columns
    echo "<h3>Specialty System Status</h3>";
    
    try {
        $stmt = $pdo->query("DESCRIBE students");
        $student_columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
        
        $stmt = $pdo->query("DESCRIBE modules");
        $module_columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
        
        $student_has_specialty = in_array('specialty', $student_columns);
        $module_has_specialty = in_array('specialty', $module_columns);
        
        echo "<ul>";
        echo "<li><strong>Students specialty column:</strong> " . ($student_has_specialty ? "✓ Present" : "✗ Missing") . "</li>";
        echo "<li><strong>Modules specialty column:</strong> " . ($module_has_specialty ? "✓ Present" : "✗ Missing") . "</li>";
        echo "</ul>";
        
        if (!$student_has_specialty || !$module_has_specialty) {
            echo "<p style='color: orange;'><strong>Action needed:</strong> Run the specialty columns migration tool.</p>";
            echo "<p><a href='add_specialty_columns.php'>Add Specialty Columns</a></p>";
        }
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Error checking specialty columns: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
    echo "<h3>Quick Actions</h3>";
    echo "<p><a href='../../setup_database.php' style='background: #007bff; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>Setup Database</a></p>";
    echo "<p><a href='add_specialty_columns.php' style='background: #28a745; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>Add Specialty Columns</a></p>";
    echo "<p><a href='../setup_sample_modules.php' style='background: #ffc107; color: black; padding: 10px; text-decoration: none; border-radius: 5px;'>Generate Sample Modules</a></p>";
    echo "<p><a href='bulk_enrollment.php' style='background: #17a2b8; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>Run Bulk Enrollment</a></p>";
    echo "<p><a href='../generate_sample_attendance.php' style='background: #6f42c1; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>Generate Sample Attendance</a></p>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Database Connection Error</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database connection settings in includes/config.php</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1200px;
    margin: 20px auto;
    padding: 20px;
    line-height: 1.6;
}
h2, h3 {
    color: #333;
}
table {
    width: 100%;
    margin: 20px 0;
}
th {
    background: #f8f9fa;
    padding: 10px;
    text-align: left;
}
td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
}
a {
    color: #007bff;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
ul {
    margin: 10px 0;
}
li {
    margin: 5px 0;
}
</style>
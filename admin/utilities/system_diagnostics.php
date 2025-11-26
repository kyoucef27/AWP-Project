<?php
/**
 * System Diagnostic Tool
 * Comprehensive database and system status checker
 */
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/unauthorized.php");
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Diagnostics - Admin Tools</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        .section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .section h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
        }
        .status-good { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        .info-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 0.25rem;
        }
        .btn:hover {
            background: #5a67d8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 System Diagnostics</h1>
            <p>Comprehensive system and database health check</p>
        </div>

        <?php
        require_once '../../includes/config.php';

        // Database Connection Test
        echo "<div class='section'>";
        echo "<h3>Database Connection</h3>";
        try {
            $pdo = getDBConnection();
            echo "<p class='status-good'>✅ Database connection successful</p>";
            
            // Get database info
            $version = $pdo->query("SELECT VERSION() as version")->fetch();
            echo "<div class='info-grid'>";
            echo "<div class='info-card'><strong>MySQL Version:</strong> " . $version['version'] . "</div>";
            echo "<div class='info-card'><strong>Database:</strong> " . DB_NAME . "</div>";
            echo "<div class='info-card'><strong>Host:</strong> " . DB_HOST . "</div>";
            echo "<div class='info-card'><strong>Charset:</strong> " . DB_CHARSET . "</div>";
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<p class='status-error'>❌ Database connection failed: " . $e->getMessage() . "</p>";
        }
        echo "</div>";

        // Table Structure Check
        echo "<div class='section'>";
        echo "<h3>Database Tables</h3>";
        try {
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo "<p class='status-good'>✅ Found " . count($tables) . " tables</p>";
            
            $expected_tables = ['users', 'students', 'professors', 'teachers', 'modules', 'teacher_modules', 'login_attempts'];
            $missing_tables = array_diff($expected_tables, $tables);
            
            if (empty($missing_tables)) {
                echo "<p class='status-good'>✅ All required tables present</p>";
            } else {
                echo "<p class='status-warning'>⚠️ Missing tables: " . implode(', ', $missing_tables) . "</p>";
            }
            
            echo "<div class='info-grid'>";
            foreach ($tables as $table) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                $count = $stmt->fetchColumn();
                $status = in_array($table, $expected_tables) ? 'status-good' : 'status-warning';
                echo "<div class='info-card'><strong>$table:</strong> <span class='$status'>$count records</span></div>";
            }
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<p class='status-error'>❌ Error checking tables: " . $e->getMessage() . "</p>";
        }
        echo "</div>";

        // User Data Analysis
        echo "<div class='section'>";
        echo "<h3>User Data Analysis</h3>";
        try {
            // User counts by role
            $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
            $user_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<div class='info-grid'>";
            foreach ($user_counts as $role_data) {
                $icon = match($role_data['role']) {
                    'admin' => '👨‍💼',
                    'teacher' => '👨‍🏫',
                    'professor' => '👨‍🏫',
                    'student' => '👨‍🎓',
                    default => '👤'
                };
                echo "<div class='info-card'>";
                echo "<strong>$icon " . ucfirst($role_data['role']) . "s:</strong> " . $role_data['count'];
                echo "</div>";
            }
            echo "</div>";
            
            // Module statistics
            if (in_array('modules', $tables)) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM modules");
                $total_modules = $stmt->fetchColumn();
                
                $stmt = $pdo->query("SELECT COUNT(*) FROM modules WHERE is_active = 1");
                $active_modules = $stmt->fetchColumn();
                
                echo "<div class='info-grid'>";
                echo "<div class='info-card'><strong>📚 Total Modules:</strong> $total_modules</div>";
                echo "<div class='info-card'><strong>📚 Active Modules:</strong> $active_modules</div>";
                echo "</div>";
            }
            
            // Teacher assignments
            if (in_array('teacher_modules', $tables)) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM teacher_modules");
                $assignments = $stmt->fetchColumn();
                
                $stmt = $pdo->query("SELECT COUNT(DISTINCT teacher_id) FROM teacher_modules");
                $teachers_with_modules = $stmt->fetchColumn();
                
                echo "<div class='info-grid'>";
                echo "<div class='info-card'><strong>🔗 Teacher Assignments:</strong> $assignments</div>";
                echo "<div class='info-card'><strong>👨‍🏫 Teachers with Modules:</strong> $teachers_with_modules</div>";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<p class='status-error'>❌ Error analyzing user data: " . $e->getMessage() . "</p>";
        }
        echo "</div>";

        // System Information
        echo "<div class='section'>";
        echo "<h3>System Information</h3>";
        echo "<div class='info-grid'>";
        echo "<div class='info-card'><strong>PHP Version:</strong> " . PHP_VERSION . "</div>";
        echo "<div class='info-card'><strong>Server:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</div>";
        echo "<div class='info-card'><strong>Max Upload Size:</strong> " . ini_get('upload_max_filesize') . "</div>";
        echo "<div class='info-card'><strong>Memory Limit:</strong> " . ini_get('memory_limit') . "</div>";
        echo "<div class='info-card'><strong>Timezone:</strong> " . date_default_timezone_get() . "</div>";
        echo "<div class='info-card'><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</div>";
        echo "</div>";
        echo "</div>";

        // Quick Actions
        echo "<div class='section'>";
        echo "<h3>Quick Actions</h3>";
        echo "<a href='test_connection.php' class='btn'>🔌 Test Connection</a>";
        echo "<a href='../dashboard.php' class='btn'>🏠 Admin Dashboard</a>";
        echo "<a href='../../setup_database.php' class='btn'>🔧 Database Setup</a>";
        echo "<a href='../../wamp_status.php' class='btn'>📊 WAMP Status</a>";
        echo "</div>";
        ?>
    </div>
</body>
</html>
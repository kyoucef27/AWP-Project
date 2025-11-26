<?php
/**
 * Database Connection Test Utility
 * Simple tool to verify database connectivity
 */
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/unauthorized.php");
    exit();
}

require_once '../../includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Test</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #667eea;
        }
        .status-good { color: #28a745; }
        .status-error { color: #dc3545; }
        .status-info { color: #17a2b8; }
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 0.25rem;
        }
        .info-box {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            border-left: 4px solid #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔌 Database Connection Test</h1>
            <p>Testing connectivity to the PAW Project database</p>
        </div>

        <?php
        echo "<h3>Configuration Information:</h3>";
        echo "<div class='info-box'>";
        echo "<strong>Host:</strong> " . DB_HOST . "<br>";
        echo "<strong>Database:</strong> " . DB_NAME . "<br>";
        echo "<strong>User:</strong> " . DB_USER . "<br>";
        echo "<strong>Charset:</strong> " . DB_CHARSET . "<br>";
        echo "</div>";

        echo "<h3>Connection Test Results:</h3>";
        
        try {
            $pdo = getDBConnection();
            echo "<p class='status-good'>✅ <strong>Database connection successful!</strong></p>";
            
            // Test a simple query
            $stmt = $pdo->query("SELECT 1 as test");
            $result = $stmt->fetch();
            
            if ($result && $result['test'] == 1) {
                echo "<p class='status-good'>✅ <strong>Query execution successful!</strong></p>";
            } else {
                echo "<p class='status-error'>❌ Query test failed</p>";
            }
            
            // Show database information
            $version = $pdo->query("SELECT VERSION() as version")->fetch();
            echo "<div class='info-box'>";
            echo "<strong>MySQL Version:</strong> " . $version['version'] . "<br>";
            
            // Check if main tables exist
            $tables_to_check = ['users', 'students', 'professors', 'teachers', 'modules', 'teacher_modules'];
            $existing_tables = [];
            $missing_tables = [];
            
            foreach ($tables_to_check as $table) {
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                    $count = $stmt->fetchColumn();
                    $existing_tables[$table] = $count;
                } catch (Exception $e) {
                    $missing_tables[] = $table;
                }
            }
            
            if (!empty($existing_tables)) {
                echo "<strong>Existing Tables:</strong><br>";
                foreach ($existing_tables as $table => $count) {
                    echo "• $table ($count records)<br>";
                }
            }
            
            if (!empty($missing_tables)) {
                echo "<br><strong class='status-error'>Missing Tables:</strong><br>";
                foreach ($missing_tables as $table) {
                    echo "• $table<br>";
                }
            }
            
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<p class='status-error'>❌ <strong>Database connection failed:</strong></p>";
            echo "<div class='info-box'>";
            echo "<strong>Error:</strong> " . $e->getMessage() . "<br><br>";
            
            echo "<strong>Troubleshooting Steps:</strong><br>";
            echo "1. Ensure WAMP/XAMPP MySQL service is running<br>";
            echo "2. Verify database credentials in config.php<br>";
            echo "3. Check if database '" . DB_NAME . "' exists<br>";
            echo "4. Verify MySQL is running on port 3306<br>";
            echo "</div>";
        }
        ?>

        <h3>Quick Actions:</h3>
        <a href="../dashboard.php" class="btn">🏠 Admin Dashboard</a>
        <a href="system_diagnostics.php" class="btn">🔧 Full Diagnostics</a>
        <a href="../../setup_database.php" class="btn">🗄️ Database Setup</a>
        <a href="../../wamp_status.php" class="btn">📊 WAMP Status</a>
    </div>
</body>
</html>
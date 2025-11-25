<?php
// Test database connection script for MySQL
require_once 'includes/config.php';

echo "<h2>PAW Project - MySQL Database Connection Test</h2>";

// Check configuration
echo "<h3>Configuration Status:</h3>";
echo "<ul>";
echo "<li><strong>Database Type:</strong> MySQL</li>";
echo "<li><strong>Database Host:</strong> " . DB_HOST . "</li>";
echo "<li><strong>Database Name:</strong> " . DB_NAME . "</li>";
echo "<li><strong>Database User:</strong> " . DB_USER . "</li>";
echo "<li><strong>Database Charset:</strong> " . DB_CHARSET . "</li>";
echo "</ul>";

// Test connection
echo "<h3>Connection Test:</h3>";
try {
    $pdo = getDBConnection();
    echo "<p>✅ <strong>Database connection successful!</strong></p>";
    
    // Test a simple query
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    
    if ($result && $result['test'] == 1) {
        echo "<p>✅ <strong>Query test successful!</strong></p>";
    } else {
        echo "<p>❌ Query test failed</p>";
    }
    
    // Show MySQL version
    $version = $pdo->query("SELECT VERSION() as version")->fetch();
    echo "<p><strong>MySQL Version:</strong> " . $version['version'] . "</p>";
    
    // Check if users table exists
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $count = $stmt->fetchColumn();
        echo "<p>✅ <strong>Users table exists with {$count} records</strong></p>";
    } catch (Exception $e) {
        echo "<p>❌ Users table not found or not accessible: " . $e->getMessage() . "</p>";
        echo "<p>💡 You may need to run <a href='setup_database.php'>setup_database.php</a> first</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Database connection failed:</strong><br>" . $e->getMessage() . "</p>";
    
    echo "<h4>Troubleshooting MySQL Issues:</h4>";
    echo "<ul>";
    echo "<li>Check if MySQL service is running in WAMP</li>";
    echo "<li>Verify username/password in config.php</li>";
    echo "<li>Ensure database '" . DB_NAME . "' exists</li>";
    echo "<li>Check MySQL port (default: 3306)</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><a href='index.html' style='background: #007cba; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>Back to Login</a></p>";
echo "<p><a href='wamp_status.php' style='background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>Check WAMP Status</a></p>";
echo "<p><a href='setup_database.php' style='background: #dc3545; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>Run Database Setup</a></p>";
?>

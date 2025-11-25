<?php
// WAMP Status Check - Verify services and configuration
echo "<!DOCTYPE html><html><head><title>WAMP Status Check</title>";
echo "<style>body{font-family:Arial;max-width:800px;margin:20px auto;padding:20px;}";
echo ".success{color:#28a745;} .error{color:#dc3545;} .warning{color:#ffc107;}";
echo "h3{border-bottom:2px solid #007bff;padding-bottom:5px;}";
echo "table{width:100%;border-collapse:collapse;margin:10px 0;}";
echo "th,td{padding:10px;text-align:left;border:1px solid #ddd;}";
echo "th{background:#f8f9fa;}</style></head><body>";

echo "<h1>🚦 WAMP Status Check</h1>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";

// PHP Information
echo "<h3>🐘 PHP Information</h3>";
echo "<table>";
echo "<tr><th>Setting</th><th>Value</th></tr>";
echo "<tr><td>PHP Version</td><td class='success'>" . PHP_VERSION . "</td></tr>";
echo "<tr><td>Server Software</td><td>" . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</td></tr>";
echo "<tr><td>Document Root</td><td>" . $_SERVER['DOCUMENT_ROOT'] . "</td></tr>";
echo "<tr><td>Script Name</td><td>" . $_SERVER['SCRIPT_NAME'] . "</td></tr>";
echo "<tr><td>Current Working Directory</td><td>" . getcwd() . "</td></tr>";
echo "</table>";

// Extension Check
echo "<h3>🔧 Required Extensions</h3>";
$required_extensions = ['pdo', 'pdo_sqlite', 'session', 'json', 'mbstring'];
echo "<table>";
echo "<tr><th>Extension</th><th>Status</th></tr>";
foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? "<span class='success'>✅ Loaded</span>" : "<span class='error'>❌ Missing</span>";
    echo "<tr><td>$ext</td><td>$status</td></tr>";
}
echo "</table>";

// Database Connection Test
echo "<h3>🗄️ Database Connection Test</h3>";
$config_path = __DIR__ . '/includes/config.php';
if (file_exists($config_path)) {
    echo "<p class='success'>✅ Config file found: $config_path</p>";
    
    try {
        require_once $config_path;
        echo "<p class='success'>✅ Config file loaded successfully</p>";
        
        // Check if we're using SQLite or MySQL
        if (defined('DB_TYPE') && DB_TYPE === 'sqlite') {
            echo "<p class='success'>✅ Using SQLite database configuration</p>";
            
            $database_file = DB_FILE;
            echo "<p><strong>Database File:</strong> $database_file</p>";
            
            if (file_exists($database_file)) {
                echo "<p class='success'>✅ SQLite database file exists</p>";
                
                // Test connection
                $pdo = getDBConnection();
                echo "<p class='success'>✅ SQLite connection successful</p>";
                
                // Get SQLite version
                $version = $pdo->query('SELECT sqlite_version()')->fetchColumn();
                echo "<p><strong>SQLite Version:</strong> $version</p>";
                
                // Check if tables exist
                $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
                if ($tables) {
                    echo "<p class='success'>✅ Database tables found: " . implode(', ', $tables) . "</p>";
                } else {
                    echo "<p class='warning'>⚠️ No tables found. Run database setup.</p>";
                }
                
            } else {
                echo "<p class='warning'>⚠️ SQLite database file doesn't exist yet. Run setup to create it.</p>";
            }
            
        } else {
            // MySQL configuration
            echo "<p class='success'>✅ Using MySQL database configuration</p>";
            
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            
            echo "<p class='success'>✅ MySQL connection successful</p>";
            
            // Get MySQL version
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            echo "<p><strong>MySQL Version:</strong> $version</p>";
            
            // Test database creation
            try {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
                echo "<p class='success'>✅ Database '" . DB_NAME . "' is accessible</p>";
            } catch (PDOException $e) {
                echo "<p class='error'>❌ Cannot create/access database: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Configuration or connection error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p class='error'>❌ Config file not found at: $config_path</p>";
}

// File Permissions Check
echo "<h3>📁 File Permissions</h3>";
$paths_to_check = [
    'includes/config.php',
    'includes/db_connect.php', 
    'database',  // SQLite database directory
    'database/students.sqlite',  // SQLite database file
    'logs',
    'database_schema_complete.sql'
];

echo "<table>";
echo "<tr><th>Path</th><th>Exists</th><th>Readable</th><th>Writable</th></tr>";
foreach ($paths_to_check as $path) {
    $exists = file_exists($path) ? "✅" : "❌";
    $readable = is_readable($path) ? "✅" : "❌";
    $writable = is_writable($path) ? "✅" : "❌";
    echo "<tr><td>$path</td><td>$exists</td><td>$readable</td><td>$writable</td></tr>";
}
echo "</table>";

echo "<h3>🚀 Next Steps</h3>";
echo "<ul>";
echo "<li><a href='setup_debug.php'>🔧 Run Database Setup (Debug Mode)</a></li>";
echo "<li><a href='setup_database.php'>🗄️ Run Database Setup (Production)</a></li>";
echo "<li><a href='test_connection.php'>🔌 Test Database Connection</a></li>";
echo "<li><a href='index.html'>🏠 Go to Home Page</a></li>";
echo "</ul>";

echo "<hr><p><small>Generated on " . date('c') . "</small></p>";
echo "</body></html>";
?>
<?php
// Debug version of setup script to identify issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 Database Setup Debug</h2>";
echo "<div style='font-family: Arial; max-width: 800px; margin: 20px;'>";

// Step 1: Check if config file can be loaded
echo "<h3>Step 1: Loading Configuration</h3>";
try {
    require_once 'includes/config.php';
    echo "✅ Config file loaded successfully<br>";
    
    echo "📊 Database: " . DB_NAME . " on " . DB_HOST . "<br>";
    echo "🌍 Timezone: " . date_default_timezone_get() . "<br>";
} catch (Exception $e) {
    echo "❌ Error loading config: " . $e->getMessage() . "<br>";
    exit;
}

// Step 2: Test database connection
echo "<h3>Step 2: Testing Database Connection</h3>";
try {
    // MySQL connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    echo "✅ MySQL connection successful<br>";
    
    // Get MySQL version
    $stmt = $pdo->query('SELECT VERSION()');
    $version = $stmt->fetchColumn();
    echo "📊 MySQL Version: " . $version . "<br>";
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    echo "<p><strong>Common fixes:</strong></p>";
    echo "<ul>";
    echo "<li>Make sure WAMP/XAMPP MySQL service is running</li>";
    echo "<li>Check if port 3306 is available</li>";
    echo "<li>Verify MySQL username/password in config.php</li>";
    echo "</ul>";
    exit;
}

// Step 3: Create/Verify Database
echo "<h3>Step 3: Database Setup</h3>";
try {
    // MySQL database creation
    $collation = defined('DB_COLLATION') ? DB_COLLATION : 'utf8mb4_unicode_ci';
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " COLLATE " . $collation);
    echo "✅ Database '" . DB_NAME . "' created/exists<br>";
    
    $pdo->exec("USE " . DB_NAME);
    echo "✅ Database selected<br>";
    
    // Create MySQL tables
    echo "🔧 Creating MySQL tables...<br>";
    
    // Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'student',
            email VARCHAR(100),
            full_name VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Students table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            student_number VARCHAR(20) UNIQUE,
            specialization VARCHAR(100),
            year_of_study INT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Professors table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS professors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            department VARCHAR(100),
            office_location VARCHAR(50),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    echo "✅ MySQL tables created/verified<br>";
} catch (PDOException $e) {
    echo "❌ Error setting up database: " . $e->getMessage() . "<br>";
    exit;
}

// Step 4: Create admin user if needed
echo "<h3>Step 4: Creating Admin User</h3>";
try {
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo "✅ Admin user already exists<br>";
    } else {
        // Create admin user
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email, full_name) VALUES (?, ?, 'admin', ?, ?)");
        $stmt->execute(['admin', $admin_password, 'admin@pawproject.com', 'System Administrator']);
        echo "✅ Admin user created successfully<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Error creating admin user: " . $e->getMessage() . "<br>";
}

// Step 5: Verify tables
echo "<h3>Step 5: Verifying Tables</h3>";
try {
    // MySQL table listing
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "✅ Found " . count($tables) . " tables:<br>";
    echo "<div style='columns: 3; margin: 10px 0;'>";
    foreach ($tables as $table) {
        echo "• " . htmlspecialchars($table) . "<br>";
    }
    echo "</div>";
    
} catch (PDOException $e) {
    echo "❌ Error checking tables: " . $e->getMessage() . "<br>";
    exit;
}

// Step 6: Check sample data
echo "<h3>Step 6: Verifying Sample Data</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $user_count = $stmt->fetchColumn();
    
    echo "✅ Sample data loaded:<br>";
    echo "👥 Users: $user_count<br>";
    
    if ($user_count > 0) {
        echo "<h4>User Accounts:</h4>";
        $stmt = $pdo->query("
            SELECT username, email, role 
            FROM users 
            ORDER BY role, username
        ");
        $users = $stmt->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>Username</th><th>Email</th><th>Role</th><th>Default Password</th></tr>";
        foreach ($users as $user) {
            $default_password = match($user['role']) {
                'admin' => 'admin123',
                'professor' => 'prof123', 
                'student' => 'student123',
                default => 'password123'
            };
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td>" . htmlspecialchars($default_password) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "⚠️ Could not verify sample data: " . $e->getMessage() . "<br>";
    echo "This is normal if tables don't exist yet.<br>";
}

// Success message
echo "<h3>🎉 Setup Complete!</h3>";
echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h4 style='color: #155724; margin-top: 0;'>✅ Database setup completed successfully!</h4>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>🔗 <a href='test_connection.php' style='color: #155724;'>Test Database Connection</a></li>";
echo "<li>🔐 <a href='auth/login.php' style='color: #155724;'>Login to the System</a></li>";
echo "<li>🏠 <a href='index.html' style='color: #155724;'>Return to Home</a></li>";
echo "</ul>";
echo "</div>";

echo "</div>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - AUAS</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 900px; 
            margin: 0 auto; 
            padding: 20px; 
            background: #f8f9fa; 
            line-height: 1.6; 
        }
        h2, h3 { color: #333; }
        h3 { border-bottom: 2px solid #007bff; padding-bottom: 5px; }
        table { background: white; }
        th { background: #007bff; color: white; padding: 10px; }
        td { padding: 8px; }
        a { color: #007bff; }
    </style>
</head>
<body></body>
</html>
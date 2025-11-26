<?php
// Setup script to initialize the database with the required tables and sample data
require_once 'includes/config.php';

echo "<h2>PAW Project Database Setup</h2>";

// Ensure database exists
echo "<p>Creating database if needed...</p>";
if (ensureDatabaseExists()) {
    echo "<p>✅ Database '" . DB_NAME . "' is ready.</p>";
} else {
    die("<p>❌ Failed to create database. Please check your MySQL connection.</p>");
}

// Connect to the database
echo "<p>Connecting to database...</p>";
try {
    $pdo = getDBConnection();
    echo "<p>✅ Connected to database successfully.</p>";
} catch (Exception $e) {
    die("<p>❌ Database connection failed: " . $e->getMessage() . "</p>");
}

// Create tables with MySQL syntax
echo "<p>Creating tables...</p>";
$sql = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'professor', 'student', 'teacher') NOT NULL DEFAULT 'student',
    email VARCHAR(100),
    full_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    student_number VARCHAR(20) UNIQUE,
    specialization VARCHAR(100),
    year_of_study INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS professors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    department VARCHAR(100),
    office_location VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    teacher_id VARCHAR(20) UNIQUE,
    department VARCHAR(100),
    position VARCHAR(100),
    specialization VARCHAR(200),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_code VARCHAR(20) UNIQUE NOT NULL,
    module_name VARCHAR(200) NOT NULL,
    description TEXT,
    credits INT DEFAULT 3,
    department VARCHAR(100),
    year_level INT,
    semester ENUM('Fall', 'Spring', 'Summer', 'Both') DEFAULT 'Both',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS teacher_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT,
    module_id INT,
    role ENUM('Lecturer', 'Assistant', 'Coordinator') DEFAULT 'Lecturer',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_module (teacher_id, module_id)
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    successful BOOLEAN DEFAULT FALSE
);
";

try {
    $pdo->exec($sql);
    echo "<p>✅ Tables created successfully.</p>";
} catch (PDOException $e) {
    echo "<p>❌ Error creating tables: " . $e->getMessage() . "</p>";
    exit;
}

// Insert default admin user if not exists
echo "<p>Setting up default admin user...</p>";
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email, full_name) VALUES (?, ?, 'admin', ?, ?)");
        $stmt->execute(['admin', $admin_password, 'admin@pawproject.com', 'System Administrator']);
        echo "<p>✅ Default admin user created</p>";
        echo "<p><strong>Login credentials:</strong><br>Username: admin<br>Password: admin123</p>";
    } else {
        echo "<p>✅ Admin user already exists.</p>";
    }
} catch (PDOException $e) {
    echo "<p>❌ Error creating admin user: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>✅ Database setup completed successfully!</h3>";
echo "<p><a href='index.html' style='background: #007cba; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>Go to Login Page</a></p>";
echo "<p><a href='wamp_status.php' style='background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>Check System Status</a></p>";
?>
</html>
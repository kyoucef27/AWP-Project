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
    specialty ENUM('Computer Science', 'Software Engineering', 'Information Systems', 'Data Science') DEFAULT 'Computer Science',
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
    specialty VARCHAR(200) DEFAULT 'All',
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

CREATE TABLE IF NOT EXISTS student_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(100) NOT NULL,
    year_level INT NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    max_capacity INT DEFAULT 30,
    current_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_group (group_name, year_level, specialization)
);

CREATE TABLE IF NOT EXISTS student_group_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    group_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES student_groups(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_assignment (student_id)
);

CREATE TABLE IF NOT EXISTS module_group_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT NOT NULL,
    group_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES student_groups(id) ON DELETE CASCADE,
    UNIQUE KEY unique_module_group (module_id, group_id)
);

CREATE TABLE IF NOT EXISTS enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    module_id INT NOT NULL,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'dropped', 'completed') DEFAULT 'active',
    grade VARCHAR(5),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (student_id, module_id)
);

CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') DEFAULT 'absent',
    remarks TEXT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    recorded_by INT,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_attendance (enrollment_id, attendance_date)
);

CREATE TABLE IF NOT EXISTS absence_justifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attendance_id INT NOT NULL,
    student_id INT NOT NULL,
    justification_text TEXT NOT NULL,
    supporting_document VARCHAR(255),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    reviewed_by INT,
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT,
    FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
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
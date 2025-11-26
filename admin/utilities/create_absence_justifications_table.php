<?php
require_once '../../includes/config.php';

try {
    $pdo = getDBConnection();
    
    echo "<h2>Creating Absence Justifications Table</h2>";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'absence_justifications'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: orange;'>✓ Table 'absence_justifications' already exists.</p>";
    } else {
        // Create absence_justifications table
        $sql = "
        CREATE TABLE absence_justifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            attendance_id INT NOT NULL,
            student_id INT NOT NULL,
            justification_text TEXT NOT NULL,
            supporting_document VARCHAR(500) NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            submitted_at DATETIME NOT NULL,
            reviewed_at DATETIME NULL,
            reviewed_by INT NULL,
            review_notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        )";
        
        $pdo->exec($sql);
        echo "<p style='color: green;'>✓ Created 'absence_justifications' table successfully.</p>";
    }
    
    // Check if attendance table exists and has the right structure
    $stmt = $pdo->query("DESCRIBE attendance");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('id', $columns)) {
        echo "<p style='color: red;'>✗ 'attendance' table missing 'id' column. Running table update...</p>";
        
        // Add ID column if missing
        try {
            $pdo->exec("ALTER TABLE attendance ADD COLUMN id INT PRIMARY KEY AUTO_INCREMENT FIRST");
            echo "<p style='color: green;'>✓ Added 'id' column to attendance table.</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>Error adding ID column: " . $e->getMessage() . "</p>";
        }
    }
    
    // Check if enrollments table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'enrollments'");
    if ($stmt->rowCount() == 0) {
        echo "<p style='color: red;'>✗ 'enrollments' table is missing. Creating it...</p>";
        
        // Create enrollments table
        $sql = "
        CREATE TABLE enrollments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            module_id INT NOT NULL,
            group_id INT NULL,
            enrollment_date DATE NOT NULL,
            status ENUM('active', 'completed', 'dropped', 'withdrawn') DEFAULT 'active',
            grade VARCHAR(5) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_student_module (student_id, module_id),
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
            FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE SET NULL
        )";
        
        $pdo->exec($sql);
        echo "<p style='color: green;'>✓ Created 'enrollments' table successfully.</p>";
    }
    
    echo "<hr>";
    echo "<h3>Database Status Check Complete</h3>";
    echo "<p><a href='../../admin/dashboard.php'>Return to Admin Dashboard</a></p>";
    echo "<p><a href='../../student/attendance.php'>Test Student Attendance</a></p>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Database Error</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Code: " . $e->getCode() . "</p>";
    
    if (strpos($e->getMessage(), 'attendance') !== false) {
        echo "<p><strong>Suggestion:</strong> The attendance table might not exist. Try running the main database setup first.</p>";
        echo "<p><a href='../../setup_database.php'>Run Database Setup</a></p>";
    }
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    line-height: 1.6;
}
h2, h3 {
    color: #333;
}
p {
    margin: 10px 0;
}
a {
    color: #007bff;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
</style>
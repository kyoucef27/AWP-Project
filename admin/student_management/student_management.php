<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit();
}

$user = $_SESSION;
$message = '';
$error = '';

// Check for export error
if (isset($_SESSION['export_error'])) {
    $error = $_SESSION['export_error'];
    unset($_SESSION['export_error']);
}

// Handle export functionality FIRST (before any HTML output)
if (isset($_GET['export'])) {
    try {
        require_once '../../includes/config.php';
        $pdo = getDBConnection();
        
        $stmt = $pdo->query("
            SELECT s.student_number, u.username, u.full_name, u.email, 
                   s.specialization, s.year_of_study, u.created_at
            FROM users u 
            LEFT JOIN students s ON u.id = s.user_id 
            WHERE u.role = 'student' 
            ORDER BY u.created_at DESC
        ");
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if there are students to export
        if (empty($students)) {
            $_SESSION['export_error'] = 'No students found to export.';
            header("Location: student_management.php");
            exit();
        }
        
        if ($_GET['export'] == 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="students_' . date('Y-m-d') . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Student Number', 'Username', 'Full Name', 'Email', 'Specialization', 'Year', 'Created']);
            
            foreach ($students as $student) {
                fputcsv($output, [
                    $student['student_number'] ?? 'N/A',
                    $student['username'],
                    $student['full_name'],
                    $student['email'],
                    $student['specialization'] ?? 'N/A',
                    $student['year_of_study'] ? 'L' . $student['year_of_study'] : 'N/A',
                    $student['created_at']
                ]);
            }
            
            fclose($output);
            exit();
        }
        
        if ($_GET['export'] == 'excel') {
            // For now, export as CSV with .xls extension for Excel compatibility
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="students_' . date('Y-m-d') . '.xls"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            echo "<table border='1'>\n";
            echo "<tr><th>Student Number</th><th>Username</th><th>Full Name</th><th>Email</th><th>Specialization</th><th>Year</th><th>Created</th></tr>\n";
            
            foreach ($students as $student) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($student['student_number'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($student['username']) . "</td>";
                echo "<td>" . htmlspecialchars($student['full_name']) . "</td>";
                echo "<td>" . htmlspecialchars($student['email']) . "</td>";
                echo "<td>" . htmlspecialchars($student['specialization'] ?? 'N/A') . "</td>";
                echo "<td>" . ($student['year_of_study'] ? 'L' . $student['year_of_study'] : 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($student['created_at']) . "</td>";
                echo "</tr>\n";
            }
            
            echo "</table>";
            exit();
        }
        
    } catch (Exception $e) {
        error_log("Export error: " . $e->getMessage());
        $_SESSION['export_error'] = 'Export failed: ' . $e->getMessage();
        header("Location: student_management.php");
        exit();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once '../../includes/config.php';
    $pdo = getDBConnection();
    
    // Handle student addition
    if (isset($_POST['action']) && $_POST['action'] == 'add_student') {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert into users table
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email, full_name) VALUES (?, ?, 'student', ?, ?)");
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt->execute([
                $_POST['username'],
                $password_hash,
                $_POST['email'],
                $_POST['full_name']
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            // Insert into students table
            $stmt = $pdo->prepare("INSERT INTO students (user_id, student_number, specialization, year_of_study) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $user_id,
                $_POST['student_number'],
                $_POST['specialization'],
                $_POST['year_of_study']
            ]);
            
            $pdo->commit();
            $message = 'Student added successfully!';
            
        } catch (PDOException $e) {
            $pdo->rollback();
            $error = 'Error adding student: ' . $e->getMessage();
        }
    }
    
    // Handle student editing
    if (isset($_POST['action']) && $_POST['action'] == 'edit_student') {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update users table
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ? WHERE id = ? AND role = 'student'");
            $stmt->execute([
                $_POST['edit_username'],
                $_POST['edit_email'], 
                $_POST['edit_full_name'],
                $_POST['edit_student_id']
            ]);
            
            // Update password if provided
            if (!empty($_POST['edit_password'])) {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'student'");
                $password_hash = password_hash($_POST['edit_password'], PASSWORD_DEFAULT);
                $stmt->execute([$password_hash, $_POST['edit_student_id']]);
            }
            
            // Update students table
            $stmt = $pdo->prepare("UPDATE students SET student_number = ?, specialization = ?, year_of_study = ? WHERE user_id = ?");
            $stmt->execute([
                $_POST['edit_student_number'],
                $_POST['edit_specialization'],
                $_POST['edit_year_of_study'],
                $_POST['edit_student_id']
            ]);
            
            $pdo->commit();
            $message = 'Student updated successfully!';
            
        } catch (PDOException $e) {
            $pdo->rollback();
            $error = 'Error updating student: ' . $e->getMessage();
        }
    }

    // Handle student deletion
    if (isset($_POST['action']) && $_POST['action'] == 'delete_student') {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
            $stmt->execute([$_POST['student_id']]);
            $message = 'Student deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting student: ' . $e->getMessage();
        }
    }
    
    // Handle Excel import
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {
        $upload_dir = '../../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_path = $upload_dir . basename($_FILES['excel_file']['name']);
        
        if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $file_path)) {
            // Process CSV file (simplified - would need proper Excel library for .xlsx)
            if (pathinfo($file_path, PATHINFO_EXTENSION) == 'csv') {
                try {
                    $pdo->beginTransaction();
                    $imported = 0;
                    
                    if (($handle = fopen($file_path, "r")) !== FALSE) {
                        // Skip header row
                        fgetcsv($handle, 1000, ",");
                        
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            if (count($data) >= 4) {
                                // Insert user
                                $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, role, email, full_name) VALUES (?, ?, 'student', ?, ?)");
                                $password_hash = password_hash('student123', PASSWORD_DEFAULT);
                                $stmt->execute([
                                    $data[1], // username
                                    $password_hash,
                                    $data[3], // email
                                    $data[2] // full_name
                                ]);
                                
                                $user_id = $pdo->lastInsertId();
                                if ($user_id) {
                                    // Insert student
                                    $stmt = $pdo->prepare("INSERT INTO students (user_id, student_number, specialization, year_of_study) VALUES (?, ?, ?, ?)");
                                    $stmt->execute([
                                        $user_id,
                                        $data[0], // student_number
                                        $data[4] ?? 'Computer Science', // specialization
                                        3 // year_of_study
                                    ]);
                                    $imported++;
                                }
                            }
                        }
                        fclose($handle);
                    }
                    
                    $pdo->commit();
                    $message = "Successfully imported $imported students from CSV file.";
                    
                } catch (PDOException $e) {
                    $pdo->rollback();
                    $error = 'Error importing CSV: ' . $e->getMessage();
                }
            } else {
                $error = 'Please upload a CSV file. Excel (.xlsx) support requires additional libraries.';
            }
            
            unlink($file_path); // Clean up uploaded file
        } else {
            $error = 'Error uploading file.';
        }
    }
}

// Get student list
try {
    require_once '../../includes/config.php';
    $pdo = getDBConnection();
    
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.email, u.full_name, 
               s.student_number, s.specialization, s.year_of_study,
               u.created_at
        FROM users u 
        LEFT JOIN students s ON u.id = s.user_id 
        WHERE u.role = 'student' 
        ORDER BY u.created_at DESC
    ");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
    $total_students = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT specialization) as count FROM students WHERE specialization IS NOT NULL");
    $total_specializations = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Student management error: " . $e->getMessage());
    $students = [];
    $total_students = 0;
    $total_specializations = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="student_management.css">
</head>
<body>
    <?php include '../../includes/admin_navbar.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-user-graduate"></i> Student Management</h1>
                <div class="breadcrumb">
                    Home / Admin / User Management / Students
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_students; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_specializations; ?></div>
                <div class="stat-label">Specializations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo date('Y'); ?></div>
                <div class="stat-label">Academic Year</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($students); ?></div>
                <div class="stat-label">Records Loaded</div>
            </div>
        </div>

        <div class="mobile-quick-panel mobile-only">
            <div class="mobile-card">
                <div class="mobile-card-icon primary">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div>
                    <div class="mobile-card-title">Students</div>
                    <div class="mobile-card-value"><?php echo $total_students; ?></div>
                    <div class="mobile-card-meta"><?php echo $total_specializations; ?> specializations tracked</div>
                    <a href="#studentList" class="mobile-card-link">
                        <i class="fas fa-list"></i> View list
                    </a>
                </div>
            </div>
            <div class="mobile-card">
                <div class="mobile-card-icon success">
                    <i class="fas fa-file-upload"></i>
                </div>
                <div>
                    <div class="mobile-card-title">Import / Export</div>
                    <div class="mobile-card-value">CSV Ready</div>
                    <div class="mobile-card-meta">Upload new students or export data</div>
                    <a href="#" class="mobile-card-link" onclick="document.getElementById('excel_file').click(); return false;">
                        <i class="fas fa-upload"></i> Upload CSV
                    </a>
                </div>
            </div>
        </div>

        <div class="management-grid">
            <div class="sidebar">
                <!-- Add Student Form -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-user-plus"></i> Add New Student</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_student">
                        
                        <div class="form-group">
                            <label for="student_number">Student Number</label>
                            <input type="text" id="student_number" name="student_number" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required value="student123">
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="specialization">Specialization</label>
                            <select id="specialization" name="specialization" class="form-control" required>
                                <option value="">Select Specialization</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Information Systems">Information Systems</option>
                                <option value="Software Engineering">Software Engineering</option>
                                <option value="Data Science">Data Science</option>
                                <option value="Cybersecurity">Cybersecurity</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="year_of_study">Year of Study</label>
                            <select id="year_of_study" name="year_of_study" class="form-control" required>
                                <option value="">Select Year</option>
                                <option value="1">1st Year (L1)</option>
                                <option value="2">2nd Year (L2)</option>
                                <option value="3">3rd Year (L3)</option>
                                <option value="4">4th Year (M1)</option>
                                <option value="5">5th Year (M2)</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-plus"></i> Add Student
                        </button>
                    </form>
                </div>

                <!-- Edit Student Modal -->
                <div id="editStudentModal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <span class="close" onclick="closeEditModal()">&times;</span>
                        <h3>Edit Student</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="edit_student">
                            <input type="hidden" name="edit_student_id" id="edit_student_id">
                            
                            <div class="form-group">
                                <label>Student Number</label>
                                <input type="text" name="edit_student_number" id="edit_student_number" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" name="edit_username" id="edit_username" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="edit_full_name" id="edit_full_name" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="edit_email" id="edit_email" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label>New Password (leave blank to keep current)</label>
                                <input type="password" name="edit_password" id="edit_password" class="form-control" placeholder="Enter new password or leave blank">
                            </div>
                            
                            <div class="form-group">
                                <label>Specialization</label>
                                <select name="edit_specialization" id="edit_specialization" class="form-control">
                                    <option value="">Select specialization</option>
                                    <option value="Computer Science">Computer Science</option>
                                    <option value="Information Systems">Information Systems</option>
                                    <option value="Software Engineering">Software Engineering</option>
                                    <option value="Data Science">Data Science</option>
                                    <option value="Cybersecurity">Cybersecurity</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Year of Study</label>
                                <select name="edit_year_of_study" id="edit_year_of_study" class="form-control">
                                    <option value="">Select year</option>
                                    <option value="1">1st Year (L1)</option>
                                    <option value="2">2nd Year (L2)</option>
                                    <option value="3">3rd Year (L3)</option>
                                    <option value="4">4th Year (M1)</option>
                                    <option value="5">5th Year (M2)</option>
                                </select>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                                <button type="submit" class="btn btn-primary">Update Student</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Import/Export Section -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-exchange-alt"></i> Import/Export</div>
                    
                    <!-- Import Section -->
                    <form method="POST" enctype="multipart/form-data" style="margin-bottom: 1rem;">
                        <div class="file-upload" onclick="document.getElementById('excel_file').click()">
                            <div class="file-upload-icon"><i class="fas fa-file-upload"></i></div>
                            <div>Click to select CSV file</div>
                            <small style="color: #666;">Progres Excel format (CSV)</small>
                        </div>
                        <input type="file" id="excel_file" name="excel_file" accept=".csv,.xlsx,.xls" style="display: none;" onchange="this.form.submit()">
                    </form>
                    
                    <!-- Export Buttons -->
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="?export=csv" class="btn btn-success btn-sm" style="flex: 1;" onclick="showExportFeedback('CSV')">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </a>
                        <a href="?export=excel" class="btn btn-secondary btn-sm" style="flex: 1;" onclick="showExportFeedback('Excel')">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </a>
                    </div>
                    
                    <div style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 6px; font-size: 0.875rem;">
                        <strong>CSV Format:</strong><br>
                        student_number, username, full_name, email, specialization
                    </div>
                </div>
            </div>

            <!-- Student List -->
            <div class="table-container" id="studentList">
                <div class="table-header">
                    <div class="table-title"><i class="fas fa-list"></i> Student List (<?php echo count($students); ?>)</div>
                    <input type="text" class="search-box" placeholder="Search students..." id="searchBox">
                </div>
                
                <div style="overflow-x: auto;">
                    <table class="table" id="studentsTable">
                        <thead>
                            <tr>
                                <th>Student #</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Specialization</th>
                                <th>Year</th>
                                <th>Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['student_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['username']); ?></td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($student['specialization'] ?? 'N/A'); ?></td>
                                <td><?php echo $student['year_of_study'] ? 'L' . $student['year_of_study'] : 'N/A'; ?></td>
                                <td><?php echo date('M j, Y', strtotime($student['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <button class="btn btn-primary btn-sm" onclick="editStudent(<?php echo $student['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this student?')">
                                            <input type="hidden" name="action" value="delete_student">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (empty($students)): ?>
                <div style="text-align: center; padding: 3rem; color: #666;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;"><i class="fas fa-users"></i></div>
                    <div style="font-size: 1.2rem; margin-bottom: 0.5rem;">No students found</div>
                    <div>Add students using the form or import from CSV</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        </div>
    </div>

    <script src="student_management.js"></script>
</body>
</html>

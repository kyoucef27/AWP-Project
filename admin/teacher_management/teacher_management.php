<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/unauthorized.php");
    exit();
}

$message = '';
$error = '';

// Handle export functionality FIRST (before any HTML output)
if (isset($_GET['export'])) {
    try {
        require_once '../../includes/config.php';
        $pdo = getDBConnection();
        
        $stmt = $pdo->query("
            SELECT t.teacher_id, u.username, u.full_name, u.email, 
                   t.department, t.position, t.specialization, u.created_at
            FROM users u 
            LEFT JOIN teachers t ON u.id = t.user_id 
            WHERE u.role = 'teacher' 
            ORDER BY u.created_at DESC
        ");
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if there are teachers to export
        if (empty($teachers)) {
            $_SESSION['export_error'] = 'No teachers found to export.';
            header("Location: teacher_management.php");
            exit();
        }
        
        if ($_GET['export'] == 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="teachers_' . date('Y-m-d') . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Teacher ID', 'Username', 'Full Name', 'Email', 'Department', 'Position', 'Specialization', 'Created']);
            
            foreach ($teachers as $teacher) {
                fputcsv($output, [
                    $teacher['teacher_id'] ?? 'N/A',
                    $teacher['username'],
                    $teacher['full_name'],
                    $teacher['email'],
                    $teacher['department'] ?? 'N/A',
                    $teacher['position'] ?? 'N/A',
                    $teacher['specialization'] ?? 'N/A',
                    $teacher['created_at']
                ]);
            }
            
            fclose($output);
            exit();
        }
        
        if ($_GET['export'] == 'excel') {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="teachers_' . date('Y-m-d') . '.xls"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            echo "<table border='1'>\n";
            echo "<tr><th>Teacher ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Department</th><th>Position</th><th>Specialization</th><th>Created</th></tr>\n";
            
            foreach ($teachers as $teacher) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($teacher['teacher_id'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($teacher['username']) . "</td>";
                echo "<td>" . htmlspecialchars($teacher['full_name']) . "</td>";
                echo "<td>" . htmlspecialchars($teacher['email']) . "</td>";
                echo "<td>" . htmlspecialchars($teacher['department'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($teacher['position'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($teacher['specialization'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($teacher['created_at']) . "</td>";
                echo "</tr>\n";
            }
            
            echo "</table>";
            exit();
        }
        
    } catch (Exception $e) {
        error_log("Export error: " . $e->getMessage());
        $_SESSION['export_error'] = 'Export failed: ' . $e->getMessage();
        header("Location: teacher_management.php");
        exit();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once '../../includes/config.php';
    $pdo = getDBConnection();
    
    // Handle teacher addition
    if (isset($_POST['action']) && $_POST['action'] == 'add_teacher') {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert into users table
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email, full_name) VALUES (?, ?, 'teacher', ?, ?)");
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt->execute([
                $_POST['username'],
                $password_hash,
                $_POST['email'],
                $_POST['full_name']
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            // Insert into teachers table
            $stmt = $pdo->prepare("INSERT INTO teachers (user_id, teacher_id, department, position, specialization) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id,
                $_POST['teacher_id'],
                $_POST['department'],
                $_POST['position'],
                $_POST['specialization']
            ]);
            
            $pdo->commit();
            $message = 'Teacher added successfully!';
            
        } catch (PDOException $e) {
            $pdo->rollback();
            $error = 'Error adding teacher: ' . $e->getMessage();
        }
    }
    
    // Handle teacher editing
    if (isset($_POST['action']) && $_POST['action'] == 'edit_teacher') {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update users table
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ? WHERE id = ? AND role = 'teacher'");
            $stmt->execute([
                $_POST['edit_username'],
                $_POST['edit_email'], 
                $_POST['edit_full_name'],
                $_POST['edit_teacher_id']
            ]);
            
            // Update password if provided
            if (!empty($_POST['edit_password'])) {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'teacher'");
                $password_hash = password_hash($_POST['edit_password'], PASSWORD_DEFAULT);
                $stmt->execute([$password_hash, $_POST['edit_teacher_id']]);
            }
            
            // Update teachers table
            $stmt = $pdo->prepare("UPDATE teachers SET teacher_id = ?, department = ?, position = ?, specialization = ? WHERE user_id = ?");
            $stmt->execute([
                $_POST['edit_teacher_number'],
                $_POST['edit_department'],
                $_POST['edit_position'],
                $_POST['edit_specialization'],
                $_POST['edit_teacher_id']
            ]);
            
            $pdo->commit();
            $message = 'Teacher updated successfully!';
            
        } catch (PDOException $e) {
            $pdo->rollback();
            $error = 'Error updating teacher: ' . $e->getMessage();
        }
    }

    // Handle teacher deletion
    if (isset($_POST['action']) && $_POST['action'] == 'delete_teacher') {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
            $stmt->execute([$_POST['teacher_id']]);
            $message = 'Teacher deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting teacher: ' . $e->getMessage();
        }
    }
    
    // Handle CSV import
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $upload_dir = '../../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_path = $upload_dir . basename($_FILES['csv_file']['name']);
        
        if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $file_path)) {
            // Process CSV file
            if (pathinfo($file_path, PATHINFO_EXTENSION) == 'csv') {
                try {
                    $pdo->beginTransaction();
                    $imported_count = 0;
                    
                    if (($handle = fopen($file_path, "r")) !== FALSE) {
                        // Skip header row
                        fgetcsv($handle, 1000, ",");
                        
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            // Trim whitespace from all fields
                            $data = array_map('trim', $data);
                            
                            if (count($data) >= 6) {
                                // Check if user already exists
                                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                                $stmt->execute([$data[1]]);
                                $existing_user = $stmt->fetch();
                                
                                if ($existing_user) {
                                    $user_id = $existing_user['id'];
                                } else {
                                    // Insert new user
                                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email, full_name) VALUES (?, ?, 'teacher', ?, ?)");
                                    $default_password = password_hash('password123', PASSWORD_DEFAULT);
                                    $stmt->execute([
                                        $data[1], // username
                                        $default_password,
                                        $data[3], // email
                                        $data[2]  // full_name
                                    ]);
                                    $user_id = $pdo->lastInsertId();
                                }
                                
                                if ($user_id) {
                                    // Check if teacher record already exists
                                    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ? OR teacher_id = ?");
                                    $stmt->execute([$user_id, $data[0]]);
                                    $existing_teacher = $stmt->fetch();
                                    
                                    if (!$existing_teacher) {
                                        // Insert teacher record
                                        $stmt = $pdo->prepare("INSERT INTO teachers (user_id, teacher_id, department, position, specialization) VALUES (?, ?, ?, ?, ?)");
                                        $stmt->execute([
                                            $user_id,
                                            $data[0], // teacher_id
                                            $data[4], // department
                                            $data[5], // position
                                            $data[6] ?? '' // specialization
                                        ]);
                                        $imported_count++;
                                    }
                                }
                            }
                        }
                        fclose($handle);
                    }
                    
                    $pdo->commit();
                    $message = "Successfully imported $imported_count teachers from CSV file.";
                    
                } catch (PDOException $e) {
                    $pdo->rollback();
                    $error = 'Error importing CSV: ' . $e->getMessage();
                }
            } else {
                $error = 'Please upload a CSV file.';
            }
            
            // Clean up uploaded file
            unlink($file_path);
        } else {
            $error = 'File upload failed.';
        }
    }
}

// Fetch teachers for display
try {
    require_once '../../includes/config.php';
    $pdo = getDBConnection();
    
    // Get search term
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    // Build query with search
    $query = "
        SELECT u.id, u.username, u.full_name, u.email, u.created_at,
               t.teacher_id, t.department, t.position, t.specialization
        FROM users u 
        LEFT JOIN teachers t ON u.id = t.user_id 
        WHERE u.role = 'teacher'
    ";
    
    $params = [];
    if (!empty($search)) {
        $query .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR t.teacher_id LIKE ? OR t.department LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
    }
    
    $query .= " ORDER BY u.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'");
    $total_teachers = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $error = 'Error fetching teachers: ' . $e->getMessage();
    $teachers = [];
    $total_teachers = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Management - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="teacher_management.css">
</head>
<body>
    <?php include '../../includes/admin_navbar.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-chalkboard-teacher"></i> Teacher Management</h1>
                <div class="breadcrumb">
                    Home / Admin / User Management / Teachers
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
                    <div class="stat-number"><?php echo $total_teachers; ?></div>
                    <div class="stat-label">Total Teachers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($teachers); ?></div>
                    <div class="stat-label">Displayed</div>
                </div>
            </div>

            <div class="mobile-quick-panel mobile-only">
                <div class="mobile-card">
                    <div class="mobile-card-icon primary">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div>
                        <div class="mobile-card-title">Teaching Roster</div>
                        <div class="mobile-card-value"><?php echo $total_teachers; ?></div>
                        <div class="mobile-card-meta"><?php echo count($teachers); ?> loaded records</div>
                        <a href="#teacherList" class="mobile-card-link">
                            <i class="fas fa-list"></i> View teachers
                        </a>
                    </div>
                </div>
                <div class="mobile-card">
                    <div class="mobile-card-icon warning">
                        <i class="fas fa-upload"></i>
                    </div>
                    <div>
                        <div class="mobile-card-title">Import / Export</div>
                        <div class="mobile-card-value">CSV Ready</div>
                        <div class="mobile-card-meta">Use CSV templates to sync data</div>
                        <a href="#" class="mobile-card-link" onclick="document.getElementById('csv_file').click(); return false;">
                            <i class="fas fa-file-import"></i> Upload CSV
                        </a>
                    </div>
                </div>
            </div>

        <?php if (isset($_SESSION['export_error'])): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> <strong>Export Error:</strong> <?php echo htmlspecialchars($_SESSION['export_error']); ?>
            </div>
            <?php unset($_SESSION['export_error']); ?>
        <?php endif; ?>

        <div class="management-grid">
            <div class="sidebar">
                <!-- Add Teacher Form -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-user-plus"></i> Add New Teacher</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_teacher">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="teacher_id">Teacher ID</label>
                                <input type="text" id="teacher_id" name="teacher_id" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="department">Department</label>
                            <select id="department" name="department" class="form-control" required>
                                <option value="">Select department</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Information Systems">Information Systems</option>
                                <option value="Software Engineering">Software Engineering</option>
                                <option value="Mathematics">Mathematics</option>
                                <option value="Physics">Physics</option>
                                <option value="Electronics">Electronics</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="position">Position</label>
                            <select id="position" name="position" class="form-control" required>
                                <option value="">Select position</option>
                                <option value="Assistant Professor">Assistant Professor</option>
                                <option value="Associate Professor">Associate Professor</option>
                                <option value="Full Professor">Full Professor</option>
                                <option value="Lecturer">Lecturer</option>
                                <option value="Teaching Assistant">Teaching Assistant</option>
                                <option value="Research Fellow">Research Fellow</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="specialization">Specialization</label>
                            <input type="text" id="specialization" name="specialization" class="form-control" placeholder="e.g., Machine Learning, Database Systems">
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            ➕ Add Teacher
                        </button>
                    </form>
                </div>

                <!-- Edit Teacher Modal -->
                <div id="editTeacherModal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <span class="close" onclick="closeEditModal()">&times;</span>
                        <h3>Edit Teacher</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="edit_teacher">
                            <input type="hidden" name="edit_teacher_id" id="edit_teacher_id">
                            
                            <div class="form-group">
                                <label>Teacher ID</label>
                                <input type="text" name="edit_teacher_number" id="edit_teacher_number" class="form-control" required>
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
                                <label>Department</label>
                                <select name="edit_department" id="edit_department" class="form-control" required>
                                    <option value="">Select department</option>
                                    <option value="Computer Science">Computer Science</option>
                                    <option value="Information Systems">Information Systems</option>
                                    <option value="Software Engineering">Software Engineering</option>
                                    <option value="Mathematics">Mathematics</option>
                                    <option value="Physics">Physics</option>
                                    <option value="Electronics">Electronics</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Position</label>
                                <select name="edit_position" id="edit_position" class="form-control" required>
                                    <option value="">Select position</option>
                                    <option value="Assistant Professor">Assistant Professor</option>
                                    <option value="Associate Professor">Associate Professor</option>
                                    <option value="Full Professor">Full Professor</option>
                                    <option value="Lecturer">Lecturer</option>
                                    <option value="Teaching Assistant">Teaching Assistant</option>
                                    <option value="Research Fellow">Research Fellow</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Specialization</label>
                                <input type="text" name="edit_specialization" id="edit_specialization" class="form-control" placeholder="e.g., Machine Learning, Database Systems">
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                                <button type="submit" class="btn btn-primary">Update Teacher</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Import/Export Section -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-exchange-alt"></i> Import/Export</div>
                    
                <!-- Import Section -->
                <form method="POST" enctype="multipart/form-data" style="margin-bottom: 1rem;">
                    <div class="file-upload" onclick="document.getElementById('csv_file').click()">
                        <div class="file-upload-icon"><i class="fas fa-file-upload"></i></div>
                        <div>Click to select CSV file</div>
                        <small style="color: #666;">Teacher list format (CSV) - <a href="../../docs/samples/sample_teachers.csv" download>Download Sample</a></small>
                    </div>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" style="display: none;" onchange="this.form.submit()">
                </form>                    <!-- Export Buttons -->
                    <div class="export-buttons">
                        <a href="?export=csv" class="btn btn-warning" style="flex: 1; text-align: center;">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </a>
                        <a href="?export=excel" class="btn btn-warning" style="flex: 1; text-align: center;">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </a>
                    </div>
                </div>
            </div>

            <div>
                <!-- Teachers List -->
                <div class="table-container" id="teacherList">
                    <div class="table-header">
                        <div class="table-title"><i class="fas fa-list"></i> Teachers List</div>
                        <input type="text" id="searchBox" class="search-box" placeholder="Search teachers..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Teacher ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Position</th>
                                <th>Specialization</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td data-label="Teacher ID"><?php echo htmlspecialchars($teacher['teacher_id'] ?? 'N/A'); ?></td>
                                <td data-label="Username"><?php echo htmlspecialchars($teacher['username']); ?></td>
                                <td data-label="Full Name"><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                <td data-label="Email"><?php echo htmlspecialchars($teacher['email']); ?></td>
                                <td data-label="Department"><?php echo htmlspecialchars($teacher['department'] ?? 'N/A'); ?></td>
                                <td data-label="Position"><?php echo htmlspecialchars($teacher['position'] ?? 'N/A'); ?></td>
                                <td data-label="Specialization"><?php echo htmlspecialchars($teacher['specialization'] ?? 'N/A'); ?></td>
                                <td data-label="Created"><?php echo date('M j, Y', strtotime($teacher['created_at'])); ?></td>
                                <td data-label="Actions">
                                    <button class="btn btn-primary btn-sm" onclick="editTeacher(<?php echo $teacher['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this teacher?')">
                                        <input type="hidden" name="action" value="delete_teacher">
                                        <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (empty($teachers)): ?>
                <div style="text-align: center; padding: 3rem; color: #666;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;"><i class="fas fa-user-tie"></i></div>
                    <div style="font-size: 1.2rem; margin-bottom: 0.5rem;">No teachers found</div>
                    <div>Add teachers using the form or import from CSV</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Import Teachers Modal -->
    <div id="importModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-file-import"></i> Import Teachers</h3>
                <span class="close" onclick="closeImportModal()">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_teachers">
                
                <div class="form-group">
                    <label for="csv_file">Select CSV File</label>
                    <div class="file-upload" onclick="document.getElementById('csv_file').click()">
                        <div><i class="fas fa-folder-open"></i> Click to select CSV file</div>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" required style="display: none;">
                    </div>
                    <small class="text-muted">
                        File should contain columns: Teacher ID, Username, Full Name, Email, Department, Position, Specialization
                    </small>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeImportModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        📥 Import Teachers
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="teacher_management.js"></script>
</body>
</html>

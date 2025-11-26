<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/unauthorized.php");
    exit();
}

$message = '';
$error = '';

// Check for session message from redirect
if (isset($_SESSION['import_message'])) {
    $message = $_SESSION['import_message'];
    unset($_SESSION['import_message']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once '../../includes/config.php';
    $pdo = getDBConnection();
    
    // Handle module addition
    if (isset($_POST['action']) && $_POST['action'] == 'add_module') {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO modules (module_code, module_name, description, credits, department, specialty, year_level, semester) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['module_code'],
                $_POST['module_name'],
                $_POST['description'],
                $_POST['credits'],
                $_POST['department'],
                $_POST['specialty'] ?? 'All',
                $_POST['year_level'],
                $_POST['semester']
            ]);
            
            $module_id = $pdo->lastInsertId();
            $year_level = $_POST['year_level'];
            
            // Auto-assign module to all groups matching this year level
            $stmt = $pdo->prepare("
                SELECT id FROM student_groups 
                WHERE year_level = ?
            ");
            $stmt->execute([$year_level]);
            $matching_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $assigned_count = 0;
            foreach ($matching_groups as $group) {
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO module_group_assignments (module_id, group_id) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$module_id, $group['id']]);
                if ($stmt->rowCount() > 0) {
                    $assigned_count++;
                }
            }
            
            $pdo->commit();
            $message = "Module added successfully! Automatically assigned to {$assigned_count} matching group(s).";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error adding module: ' . $e->getMessage();
        }
    }
    
    // Handle module editing
    if (isset($_POST['action']) && $_POST['action'] == 'edit_module') {
        try {
            $stmt = $pdo->prepare("UPDATE modules SET module_code = ?, module_name = ?, description = ?, credits = ?, department = ?, specialty = ?, year_level = ?, semester = ?, is_active = ? WHERE id = ?");
            $stmt->execute([
                $_POST['edit_module_code'],
                $_POST['edit_module_name'],
                $_POST['edit_description'],
                $_POST['edit_credits'],
                $_POST['edit_department'],
                $_POST['edit_specialty'] ?? 'All',
                $_POST['edit_year_level'],
                $_POST['edit_semester'],
                $_POST['edit_is_active'] ?? 0,
                $_POST['edit_module_id']
            ]);
            $message = 'Module updated successfully!';
        } catch (PDOException $e) {
            $error = 'Error updating module: ' . $e->getMessage();
        }
    }

    // Handle module deletion
    if (isset($_POST['action']) && $_POST['action'] == 'delete_module') {
        try {
            // First remove all teacher assignments
            $stmt = $pdo->prepare("DELETE FROM teacher_modules WHERE module_id = ?");
            $stmt->execute([$_POST['module_id']]);
            
            // Then delete the module
            $stmt = $pdo->prepare("DELETE FROM modules WHERE id = ?");
            $stmt->execute([$_POST['module_id']]);
            $message = 'Module deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting module: ' . $e->getMessage();
        }
    }
    
    // Handle teacher-module assignment
    if (isset($_POST['action']) && $_POST['action'] == 'assign_teacher') {
        try {
            // Check if assignment already exists
            $stmt = $pdo->prepare("SELECT id FROM teacher_modules WHERE teacher_id = ? AND module_id = ?");
            $stmt->execute([$_POST['teacher_id'], $_POST['module_id']]);
            
            if ($stmt->fetch()) {
                $error = 'Teacher is already assigned to this module!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO teacher_modules (teacher_id, module_id, role) VALUES (?, ?, ?)");
                $stmt->execute([
                    $_POST['teacher_id'],
                    $_POST['module_id'],
                    $_POST['role']
                ]);
                $message = 'Teacher assigned to module successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Error assigning teacher: ' . $e->getMessage();
        }
    }
    
    // Handle teacher-module unassignment
    if (isset($_POST['action']) && $_POST['action'] == 'unassign_teacher') {
        try {
            $stmt = $pdo->prepare("DELETE FROM teacher_modules WHERE id = ?");
            $stmt->execute([$_POST['assignment_id']]);
            $message = 'Teacher unassigned from module successfully!';
        } catch (PDOException $e) {
            $error = 'Error unassigning teacher: ' . $e->getMessage();
        }
    }
    
    // Handle module import from CSV
    if (isset($_POST['action']) && $_POST['action'] == 'import_modules') {
        if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] == 0) {
            $file = $_FILES['import_file']['tmp_name'];
            
            try {
                $handle = fopen($file, 'r');
                $imported = 0;
                $skipped = 0;
                $errors = [];
                
                // Skip header row
                fgetcsv($handle);
                
                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    // Skip empty rows
                    if (empty($data[0]) || count($data) < 7) {
                        $skipped++;
                        continue;
                    }
                    
                    try {
                        // Check if module already exists
                        $stmt = $pdo->prepare("SELECT id FROM modules WHERE module_code = ?");
                        $stmt->execute([$data[0]]);
                        
                        if ($stmt->fetch()) {
                            $skipped++;
                            continue;
                        }
                        
                        // Insert new module
                        $stmt = $pdo->prepare("INSERT INTO modules (module_code, module_name, description, credits, department, year_level, semester) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            trim($data[0]), // module_code
                            trim($data[1]), // module_name
                            trim($data[2]), // description
                            trim($data[3]), // credits
                            trim($data[4]), // department
                            trim($data[5]), // year_level
                            trim($data[6])  // semester
                        ]);
                        $imported++;
                    } catch (PDOException $e) {
                        $errors[] = "Error importing module {$data[0]}: " . $e->getMessage();
                    }
                }
                
                fclose($handle);
                
                if ($imported > 0) {
                    $message = "Successfully imported $imported module(s)";
                    if ($skipped > 0) {
                        $message .= " ($skipped skipped - already exist or invalid data)";
                    }
                    // Redirect to refresh and show imported modules
                    $_SESSION['import_message'] = $message;
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $error = "No modules were imported";
                    if ($skipped > 0) {
                        $error .= " - $skipped rows skipped (already exist or invalid data)";
                    }
                }
                
                if (!empty($errors)) {
                    $error = implode('<br>', $errors);
                }
                
            } catch (Exception $e) {
                $error = 'Error reading CSV file: ' . $e->getMessage();
            }
        } else {
            $error = 'Please select a valid CSV file to import';
        }
    }
}

// Fetch modules for display
try {
    require_once '../../includes/config.php';
    $pdo = getDBConnection();
    
    // Get search term
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    // Build query with search
    $query = "
        SELECT m.*, COUNT(tm.id) as teacher_count
        FROM modules m 
        LEFT JOIN teacher_modules tm ON m.id = tm.module_id
        WHERE 1=1
    ";
    
    $params = [];
    if (!empty($search)) {
        $query .= " AND (m.module_code LIKE ? OR m.module_name LIKE ? OR m.department LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    $query .= " GROUP BY m.id ORDER BY m.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total counts
    $stmt = $pdo->query("SELECT COUNT(*) FROM modules");
    $total_modules = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM modules WHERE is_active = 1");
    $active_modules = $stmt->fetchColumn();
    
    // Get teachers for assignment dropdown
    $stmt = $pdo->query("
        SELECT t.id, u.full_name, t.teacher_id, t.department 
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE u.role = 'teacher' 
        ORDER BY u.full_name
    ");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Error fetching modules: ' . $e->getMessage();
    $modules = [];
    $teachers = [];
    $total_modules = 0;
    $active_modules = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Module Management - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="module_management.css">
</head>
<body>
    <?php include '../../includes/admin_navbar.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-book"></i> Module Management</h1>
                <div class="breadcrumb">
                    Home / Admin / Module Management
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

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_modules; ?></div>
                <div class="stat-label">Total Modules</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $active_modules; ?></div>
                <div class="stat-label">Active Modules</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($teachers); ?></div>
                <div class="stat-label">Available Teachers</div>
            </div>
        </div>

        <div class="mobile-quick-panel mobile-only">
            <div class="mobile-card">
                <div class="mobile-card-icon primary">
                    <i class="fas fa-book-open"></i>
                </div>
                <div>
                    <div class="mobile-card-title">Modules Overview</div>
                    <div class="mobile-card-value"><?php echo $total_modules; ?> Modules</div>
                    <div class="mobile-card-meta"><?php echo $active_modules; ?> active · <?php echo count($teachers); ?> teachers</div>
                    <a href="#moduleList" class="mobile-card-link">
                        <i class="fas fa-arrow-right"></i> View list
                    </a>
                </div>
            </div>
            <div class="mobile-card">
                <div class="mobile-card-icon success">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div>
                    <div class="mobile-card-title">Assignments</div>
                    <div class="mobile-card-value"><?php echo count($teachers); ?> Teachers</div>
                    <div class="mobile-card-meta">Ready for assignment workflows</div>
                    <a href="#importSection" class="mobile-card-link">
                        <i class="fas fa-upload"></i> Import data
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Import/Export Actions -->
        <div id="importSection" style="background: white; padding: 1rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 2rem;">
            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <h3 style="margin: 0; color: #333;"><i class="fas fa-folder-open"></i> Import/Export</h3>
                <button class="btn btn-success" onclick="openImportModal()" style="background: #28a745;">
                    <i class="fas fa-file-import"></i> Import Modules
                </button>
                <button class="btn btn-secondary" onclick="exportModules('csv')" style="background: #6c757d;">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button class="btn btn-secondary" onclick="exportModules('excel')" style="background: #28a745;">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
                <a href="../sample_modules.csv" download class="btn btn-secondary" style="background: #17a2b8; text-decoration: none;">
                    <i class="fas fa-download"></i> Download Sample CSV
                </a>
            </div>
        </div>

        <div class="content-grid">
            <div>
                <!-- Add Module Form -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-plus-circle"></i> Add New Module</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_module">
                        
                        <div class="form-group">
                            <label for="module_code">Module Code</label>
                            <input type="text" id="module_code" name="module_code" class="form-control" required placeholder="e.g., CS101">
                        </div>
                        
                        <div class="form-group">
                            <label for="module_name">Module Name</label>
                            <input type="text" id="module_name" name="module_name" class="form-control" required placeholder="e.g., Introduction to Programming">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3" placeholder="Module description..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="credits">Credits</label>
                            <select id="credits" name="credits" class="form-control" required>
                                <option value="">Select credits</option>
                                <option value="1">1 Credit</option>
                                <option value="2">2 Credits</option>
                                <option value="3">3 Credits</option>
                                <option value="4">4 Credits</option>
                                <option value="5">5 Credits</option>
                                <option value="6">6 Credits</option>
                            </select>
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
                            <label for="specialty">Specialty (Target Student Group)</label>
                            <select id="specialty" name="specialty" class="form-control" required>
                                <option value="All">All Specialties</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Software Engineering">Software Engineering</option>
                                <option value="Information Systems">Information Systems</option>
                                <option value="Data Science">Data Science</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="year_level">Year Level</label>
                            <select id="year_level" name="year_level" class="form-control" required>
                                <option value="">Select year</option>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                                <option value="5">5th Year</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="semester">Semester</label>
                            <select id="semester" name="semester" class="form-control" required>
                                <option value="">Select semester</option>
                                <option value="Fall">Fall</option>
                                <option value="Spring">Spring</option>
                                <option value="Summer">Summer</option>
                                <option value="Both">Both Fall & Spring</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-plus"></i> Add Module
                        </button>
                    </form>
                </div>

                <!-- Teacher Assignment Form -->
                <div class="card">
                    <div class="card-title"><i class="fas fa-chalkboard-teacher"></i> Assign Teacher to Module</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="assign_teacher">
                        
                        <div class="form-group">
                            <label for="assign_teacher_id">Select Teacher</label>
                            <select id="assign_teacher_id" name="teacher_id" class="form-control" required>
                                <option value="">Choose teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['full_name'] . ' (' . $teacher['teacher_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="assign_module_id">Select Module</label>
                            <select id="assign_module_id" name="module_id" class="form-control" required>
                                <option value="">Choose module</option>
                                <?php foreach ($modules as $module): ?>
                                    <option value="<?php echo $module['id']; ?>">
                                        <?php echo htmlspecialchars($module['module_code'] . ' - ' . $module['module_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Teaching Role</label>
                            <select id="role" name="role" class="form-control" required>
                                <option value="">Select role</option>
                                <option value="Lecturer">Lecturer</option>
                                <option value="Assistant">Teaching Assistant</option>
                                <option value="Coordinator">Course Coordinator</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-success" style="width: 100%;">
                            <i class="fas fa-user-plus"></i> Assign Teacher
                        </button>
                    </form>
                </div>
            </div>

            <div>
                <!-- Search and Modules List -->
                <div class="card">
                    <div class="search-controls">
                        <input type="text" id="searchBox" class="search-box" placeholder="Search modules..." value="<?php echo htmlspecialchars($search); ?>">
                        <div>
                            <button type="button" class="btn btn-primary btn-sm" onclick="window.location.reload()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Module Name</th>
                                <th>Department</th>
                                <th>Credits</th>
                                <th>Year</th>
                                <th>Semester</th>
                                <th>Teachers</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($modules as $module): ?>
                            <tr>
                                <td data-label="Code"><strong><?php echo htmlspecialchars($module['module_code']); ?></strong></td>
                                <td data-label="Module Name"><?php echo htmlspecialchars($module['module_name']); ?></td>
                                <td data-label="Department"><?php echo htmlspecialchars($module['department']); ?></td>
                                <td data-label="Credits"><?php echo $module['credits']; ?></td>
                                <td data-label="Year">Year <?php echo $module['year_level']; ?></td>
                                <td data-label="Semester"><?php echo htmlspecialchars($module['semester']); ?></td>
                                <td data-label="Teachers"><?php echo $module['teacher_count']; ?> teacher(s)</td>
                                <td data-label="Status">
                                    <span class="status-badge <?php echo $module['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $module['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <div class="actions">
                                        <button class="btn btn-primary btn-sm" onclick="viewModule(<?php echo $module['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn btn-warning btn-sm" onclick="editModule(<?php echo $module['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this module?')">
                                            <input type="hidden" name="action" value="delete_module">
                                            <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
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
                
                <?php if (empty($modules)): ?>
                <div style="text-align: center; padding: 3rem; color: #666;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;"><i class="fas fa-book-open"></i></div>
                    <div style="font-size: 1.2rem; margin-bottom: 0.5rem;">No modules found</div>
                    <div>Add modules using the form on the left</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Module View Modal -->
    <div id="viewModuleModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('viewModuleModal')">&times;</span>
            <h3>Module Details</h3>
            <div id="moduleDetails"></div>
        </div>
    </div>

    <!-- Module Edit Modal -->
    <div id="editModuleModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModuleModal')">&times;</span>
            <h3>Edit Module</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit_module">
                <input type="hidden" name="edit_module_id" id="edit_module_id">
                
                <div class="form-group">
                    <label>Module Code</label>
                    <input type="text" name="edit_module_code" id="edit_module_code" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Module Name</label>
                    <input type="text" name="edit_module_name" id="edit_module_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="edit_description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Credits</label>
                    <select name="edit_credits" id="edit_credits" class="form-control" required>
                        <option value="1">1 Credit</option>
                        <option value="2">2 Credits</option>
                        <option value="3">3 Credits</option>
                        <option value="4">4 Credits</option>
                        <option value="5">5 Credits</option>
                        <option value="6">6 Credits</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Department</label>
                    <select name="edit_department" id="edit_department" class="form-control" required>
                        <option value="Computer Science">Computer Science</option>
                        <option value="Information Systems">Information Systems</option>
                        <option value="Software Engineering">Software Engineering</option>
                        <option value="Mathematics">Mathematics</option>
                        <option value="Physics">Physics</option>
                        <option value="Electronics">Electronics</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Specialty</label>
                    <select name="edit_specialty" id="edit_specialty" class="form-control" required>
                        <option value="All">All Specialties</option>
                        <option value="Computer Science">Computer Science</option>
                        <option value="Software Engineering">Software Engineering</option>
                        <option value="Information Systems">Information Systems</option>
                        <option value="Data Science">Data Science</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Year Level</label>
                    <select name="edit_year_level" id="edit_year_level" class="form-control" required>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                        <option value="5">5th Year</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Semester</label>
                    <select name="edit_semester" id="edit_semester" class="form-control" required>
                        <option value="Fall">Fall</option>
                        <option value="Spring">Spring</option>
                        <option value="Summer">Summer</option>
                        <option value="Both">Both Fall & Spring</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="edit_is_active" id="edit_is_active" value="1"> 
                        Module is active
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModuleModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Module</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="importModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeImportModal()">&times;</span>
            <h3><i class="fas fa-file-import"></i> Import Modules</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_modules">
                
                <div class="form-group">
                    <label for="import_file">Select CSV File</label>
                    <input type="file" id="import_file" name="import_file" accept=".csv" class="form-control" required>
                    <small style="color: #666; font-size: 0.9rem; display: block; margin-top: 0.5rem;">
                        CSV format: module_code, module_name, description, credits, department, year_level, semester
                    </small>
                </div>
                
                <div style="margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary">Import Modules</button>
                    <button type="button" class="btn btn-secondary" onclick="closeImportModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="module_management.js"></script>
</body>
</html>

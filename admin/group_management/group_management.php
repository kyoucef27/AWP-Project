<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/unauthorized.php");
    exit();
}

$message = '';
$error = '';

require_once '../../includes/config.php';
$pdo = getDBConnection();

// Handle auto-grouping action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'auto_group') {
    try {
        // Get all students without group assignment
        $stmt = $pdo->query("
            SELECT s.id, s.year_of_study, s.specialization, u.full_name
            FROM students s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN student_group_assignments sga ON s.id = sga.student_id
            WHERE sga.id IS NULL
            ORDER BY s.year_of_study, s.specialization, u.full_name
        ");
        $ungrouped_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $grouped_count = 0;
        $groups_created = 0;
        
        // Group students by year and specialty
        $student_categories = [];
        foreach ($ungrouped_students as $student) {
            $key = $student['year_of_study'] . '_' . $student['specialization'];
            if (!isset($student_categories[$key])) {
                $student_categories[$key] = [
                    'year' => $student['year_of_study'],
                    'specialty' => $student['specialization'],
                    'students' => []
                ];
            }
            $student_categories[$key]['students'][] = $student;
        }
        
        // Create groups of 30 for each category
        foreach ($student_categories as $category) {
            $students = $category['students'];
            $year = $category['year'];
            $specialty = $category['specialty'];
            
            // Calculate how many groups needed
            $total_students = count($students);
            $num_groups = ceil($total_students / 30);
            
            for ($i = 0; $i < $num_groups; $i++) {
                // Get students for this group
                $group_students = array_slice($students, $i * 30, 30);
                
                // Generate group name
                $group_letter = chr(65 + $i); // A, B, C, etc.
                $group_name = "Year {$year} - {$specialty} - Group {$group_letter}";
                
                // Check if group already exists
                $stmt = $pdo->prepare("
                    SELECT id, current_count FROM student_groups 
                    WHERE year_level = ? AND specialization = ? AND group_name = ?
                ");
                $stmt->execute([$year, $specialty, $group_name]);
                $existing_group = $stmt->fetch();
                
                if ($existing_group) {
                    $group_id = $existing_group['id'];
                } else {
                    // Create new group
                    $stmt = $pdo->prepare("
                        INSERT INTO student_groups (group_name, year_level, specialization, max_capacity, current_count)
                        VALUES (?, ?, ?, 30, 0)
                    ");
                    $stmt->execute([$group_name, $year, $specialty]);
                    $group_id = $pdo->lastInsertId();
                    $groups_created++;
                    
                    // Auto-assign all modules matching this year level to the new group
                    $stmt = $pdo->prepare("
                        SELECT id FROM modules 
                        WHERE year_level = ? AND is_active = 1
                    ");
                    $stmt->execute([$year]);
                    $matching_modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($matching_modules as $module) {
                        $stmt = $pdo->prepare("
                            INSERT IGNORE INTO module_group_assignments (module_id, group_id) 
                            VALUES (?, ?)
                        ");
                        $stmt->execute([$module['id'], $group_id]);
                    }
                }
                
                // Assign students to group
                foreach ($group_students as $student) {
                    $stmt = $pdo->prepare("
                        INSERT INTO student_group_assignments (student_id, group_id)
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE group_id = ?
                    ");
                    $stmt->execute([$student['id'], $group_id, $group_id]);
                    $grouped_count++;
                }
                
                // Update group count
                $stmt = $pdo->prepare("
                    UPDATE student_groups 
                    SET current_count = (
                        SELECT COUNT(*) FROM student_group_assignments WHERE group_id = ?
                    )
                    WHERE id = ?
                ");
                $stmt->execute([$group_id, $group_id]);
            }
        }
        
        $message = "Successfully grouped {$grouped_count} students into groups. Created {$groups_created} new groups.";
        
    } catch (PDOException $e) {
        $error = 'Error creating groups: ' . $e->getMessage();
    }
}

// Handle manual group creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create_group') {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO student_groups (group_name, year_level, specialization, max_capacity)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['group_name'],
            $_POST['year_level'],
            $_POST['specialization'],
            $_POST['max_capacity']
        ]);
        
        $group_id = $pdo->lastInsertId();
        $year_level = $_POST['year_level'];
        
        // Auto-assign all modules matching this year level to the new group
        $stmt = $pdo->prepare("
            SELECT id FROM modules 
            WHERE year_level = ? AND is_active = 1
        ");
        $stmt->execute([$year_level]);
        $matching_modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $assigned_count = 0;
        foreach ($matching_modules as $module) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO module_group_assignments (module_id, group_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$module['id'], $group_id]);
            if ($stmt->rowCount() > 0) {
                $assigned_count++;
            }
        }
        
        $pdo->commit();
        $message = "Group created successfully! Automatically assigned {$assigned_count} matching module(s).";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Error creating group: ' . $e->getMessage();
    }
}

// Handle group deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_group') {
    try {
        $stmt = $pdo->prepare("DELETE FROM student_groups WHERE id = ?");
        $stmt->execute([$_POST['group_id']]);
        $message = 'Group deleted successfully!';
    } catch (PDOException $e) {
        $error = 'Error deleting group: ' . $e->getMessage();
    }
}

// Fetch all groups with student counts
$stmt = $pdo->query("
    SELECT sg.*, COUNT(sga.id) as actual_count
    FROM student_groups sg
    LEFT JOIN student_group_assignments sga ON sg.id = sga.group_id
    GROUP BY sg.id
    ORDER BY sg.year_level, sg.specialization, sg.group_name
");
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ungrouped students count
$stmt = $pdo->query("
    SELECT COUNT(*) as ungrouped_count
    FROM students s
    LEFT JOIN student_group_assignments sga ON s.id = sga.student_id
    WHERE sga.id IS NULL
");
$ungrouped = $stmt->fetch();
$ungrouped_count = $ungrouped['ungrouped_count'];

// Get total statistics
$stmt = $pdo->query("SELECT COUNT(*) FROM student_groups");
$total_groups = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM students");
$total_students = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM student_group_assignments");
$assigned_students = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Group Management - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="group_management.css">
</head>
<body>
    <?php include '../../includes/admin_navbar.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-users"></i> Student Group Management</h1>
                <div class="breadcrumb">
                    Home / Admin / Student Groups
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
                    <div class="stat-number"><?php echo $total_groups; ?></div>
                    <div class="stat-label">Total Groups</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $assigned_students; ?></div>
                    <div class="stat-label">Assigned Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $ungrouped_count; ?></div>
                    <div class="stat-label">Ungrouped Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>

            <!-- Auto-Group Section -->
            <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 2rem;">
                <div class="card-title" style="color: white; border-bottom-color: rgba(255,255,255,0.3);">
                    <i class="fas fa-magic"></i> Automatic Grouping
                </div>
                <p style="margin-bottom: 1rem;">Automatically organize students into groups of 30 based on their year level and specialization.</p>
                <form method="POST" onsubmit="return confirm('This will group all ungrouped students. Continue?')">
                    <input type="hidden" name="action" value="auto_group">
                    <button type="submit" class="btn" style="background: white; color: #667eea;">
                        <i class="fas fa-magic"></i> Auto-Group Students (<?php echo $ungrouped_count; ?> ungrouped)
                    </button>
                </form>
            </div>

            <div class="management-grid">
                <div class="sidebar">
                    <!-- Create Manual Group -->
                    <div class="card">
                        <div class="card-title"><i class="fas fa-plus-circle"></i> Create New Group</div>
                        <form method="POST">
                            <input type="hidden" name="action" value="create_group">
                            
                            <div class="form-group">
                                <label for="group_name">Group Name</label>
                                <input type="text" id="group_name" name="group_name" class="form-control" required placeholder="e.g., Year 1 - CS - Group A">
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
                                <label for="specialization">Specialization</label>
                                <input type="text" id="specialization" name="specialization" class="form-control" required placeholder="e.g., Computer Science">
                            </div>
                            
                            <div class="form-group">
                                <label for="max_capacity">Max Capacity</label>
                                <input type="number" id="max_capacity" name="max_capacity" class="form-control" value="30" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-plus"></i> Create Group
                            </button>
                        </form>
                    </div>
                </div>

                <div>
                    <!-- Groups List -->
                    <div class="table-container">
                        <div class="table-header">
                            <div class="table-title"><i class="fas fa-list"></i> Student Groups</div>
                        </div>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Group Name</th>
                                    <th>Year</th>
                                    <th>Specialization</th>
                                    <th>Students</th>
                                    <th>Capacity</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groups as $group): ?>
                                <tr>
                                    <td data-label="Group Name"><strong><?php echo htmlspecialchars($group['group_name']); ?></strong></td>
                                    <td data-label="Year">Year <?php echo $group['year_level']; ?></td>
                                    <td data-label="Specialization"><?php echo htmlspecialchars($group['specialization']); ?></td>
                                    <td data-label="Students"><?php echo $group['actual_count']; ?></td>
                                    <td data-label="Capacity"><?php echo $group['max_capacity']; ?></td>
                                    <td data-label="Status">
                                        <?php 
                                        $percentage = ($group['actual_count'] / $group['max_capacity']) * 100;
                                        if ($percentage >= 100): ?>
                                            <span class="status-badge status-full">Full</span>
                                        <?php elseif ($percentage >= 80): ?>
                                            <span class="status-badge status-high">High</span>
                                        <?php elseif ($percentage >= 50): ?>
                                            <span class="status-badge status-medium">Medium</span>
                                        <?php else: ?>
                                            <span class="status-badge status-low">Low</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions">
                                        <a href="view_group.php?id=<?php echo $group['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this group? Students will be unassigned.')">
                                            <input type="hidden" name="action" value="delete_group">
                                            <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
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
                    
                    <?php if (empty($groups)): ?>
                    <div style="text-align: center; padding: 3rem; color: #666;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;"><i class="fas fa-users"></i></div>
                        <div style="font-size: 1.2rem; margin-bottom: 0.5rem;">No groups found</div>
                        <div>Create groups manually or use auto-grouping</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

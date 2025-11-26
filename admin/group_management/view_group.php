<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/unauthorized.php");
    exit();
}

require_once '../../includes/config.php';
$pdo = getDBConnection();

$message = '';
$error = '';

$group_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$group_id) {
    header("Location: group_management.php");
    exit();
}

// Handle student assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'assign_student') {
    try {
        // Check current group capacity
        $stmt = $pdo->prepare("
            SELECT current_count, max_capacity 
            FROM student_groups 
            WHERE id = ?
        ");
        $stmt->execute([$group_id]);
        $group = $stmt->fetch();
        
        if ($group['current_count'] >= $group['max_capacity']) {
            $error = 'Group is at full capacity!';
        } else {
            // Assign student
            $stmt = $pdo->prepare("
                INSERT INTO student_group_assignments (student_id, group_id)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE group_id = ?
            ");
            $stmt->execute([$_POST['student_id'], $group_id, $group_id]);
            
            // Update count
            $stmt = $pdo->prepare("
                UPDATE student_groups 
                SET current_count = (
                    SELECT COUNT(*) FROM student_group_assignments WHERE group_id = ?
                )
                WHERE id = ?
            ");
            $stmt->execute([$group_id, $group_id]);
            
            $message = 'Student assigned successfully!';
        }
    } catch (PDOException $e) {
        $error = 'Error assigning student: ' . $e->getMessage();
    }
}

// Handle student removal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'remove_student') {
    try {
        $stmt = $pdo->prepare("DELETE FROM student_group_assignments WHERE student_id = ? AND group_id = ?");
        $stmt->execute([$_POST['student_id'], $group_id]);
        
        // Update count
        $stmt = $pdo->prepare("
            UPDATE student_groups 
            SET current_count = (
                SELECT COUNT(*) FROM student_group_assignments WHERE group_id = ?
            )
            WHERE id = ?
        ");
        $stmt->execute([$group_id, $group_id]);
        
        $message = 'Student removed successfully!';
    } catch (PDOException $e) {
        $error = 'Error removing student: ' . $e->getMessage();
    }
}

// Fetch group details
$stmt = $pdo->prepare("
    SELECT * FROM student_groups WHERE id = ?
");
$stmt->execute([$group_id]);
$group = $stmt->fetch();

if (!$group) {
    header("Location: group_management.php");
    exit();
}

// Fetch students in this group
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name, u.email, u.username
    FROM student_group_assignments sga
    JOIN students s ON sga.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE sga.group_id = ?
    ORDER BY u.full_name
");
$stmt->execute([$group_id]);
$group_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch available students (same year and specialty, not in any group)
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name, u.email
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN student_group_assignments sga ON s.id = sga.student_id
    WHERE s.year_of_study = ? 
    AND s.specialization = ? 
    AND sga.id IS NULL
    ORDER BY u.full_name
");
$stmt->execute([$group['year_level'], $group['specialization']]);
$available_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Group - <?php echo htmlspecialchars($group['group_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="group_management.css">
</head>
<body>
    <?php include '../../includes/admin_navbar.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-users"></i> <?php echo htmlspecialchars($group['group_name']); ?></h1>
                <div class="breadcrumb">
                    Home / Admin / <a href="group_management.php">Student Groups</a> / View Group
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

            <!-- Group Info Card -->
            <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h2 style="margin: 0 0 1rem 0; color: white;">Group Information</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <strong>Year Level:</strong> Year <?php echo $group['year_level']; ?>
                    </div>
                    <div>
                        <strong>Specialization:</strong> <?php echo htmlspecialchars($group['specialization']); ?>
                    </div>
                    <div>
                        <strong>Capacity:</strong> <?php echo count($group_students); ?> / <?php echo $group['max_capacity']; ?>
                    </div>
                    <div>
                        <strong>Status:</strong> 
                        <?php 
                        $percentage = (count($group_students) / $group['max_capacity']) * 100;
                        echo round($percentage, 1) . '% Full';
                        ?>
                    </div>
                </div>
            </div>

            <div class="management-grid">
                <div class="sidebar">
                    <!-- Assign Student -->
                    <div class="card">
                        <div class="card-title"><i class="fas fa-user-plus"></i> Assign Student</div>
                        <?php if (count($available_students) > 0): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="assign_student">
                            <div class="form-group">
                                <label>Select Student</label>
                                <select name="student_id" class="form-control" required>
                                    <option value="">Choose student</option>
                                    <?php foreach ($available_students as $student): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['student_number']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-user-plus"></i> Assign to Group
                            </button>
                        </form>
                        <?php else: ?>
                        <p style="color: #666;">No available students matching this group's year and specialty.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <!-- Students List -->
                    <div class="table-container">
                        <div class="table-header">
                            <div class="table-title"><i class="fas fa-users"></i> Group Members (<?php echo count($group_students); ?>)</div>
                        </div>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Student Number</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($group_students as $student): ?>
                                <tr>
                                    <td data-label="Student Number"><?php echo htmlspecialchars($student['student_number']); ?></td>
                                    <td data-label="Full Name"><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td data-label="Email"><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td data-label="Actions">
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this student from the group?')">
                                            <input type="hidden" name="action" value="remove_student">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fas fa-user-minus"></i> Remove
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (empty($group_students)): ?>
                    <div style="text-align: center; padding: 3rem; color: #666;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;"><i class="fas fa-user-friends"></i></div>
                        <div style="font-size: 1.2rem; margin-bottom: 0.5rem;">No students in this group yet</div>
                        <div>Assign students using the form on the left</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

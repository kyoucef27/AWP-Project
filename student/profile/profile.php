<?php
session_start();
require_once '../../includes/config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../../auth/login.php");
    exit();
}

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get student information
try {
    $stmt = $pdo->prepare("
        SELECT u.*, s.student_number, s.specialization, s.specialty, s.year_of_study
        FROM users u
        JOIN students s ON u.id = s.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        die("Student profile not found.");
    }
} catch (PDOException $e) {
    die("Error loading profile: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_info') {
            try {
                $pdo->beginTransaction();
                
                // Update users table
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, phone = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['full_name'],
                    $_POST['email'],
                    $_POST['phone'] ?? null,
                    $user_id
                ]);
                
                // Update students table
                $stmt = $pdo->prepare("
                    UPDATE students 
                    SET specialization = ?, specialty = ?, year_of_study = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $_POST['specialization'],
                    $_POST['specialty'],
                    $_POST['year_of_study'],
                    $user_id
                ]);
                
                $pdo->commit();
                $message = "Profile updated successfully!";
                
                // Refresh user data
                $stmt = $pdo->prepare("
                    SELECT u.*, s.student_number, s.specialization, s.specialty, s.year_of_study
                    FROM users u
                    JOIN students s ON u.id = s.user_id
                    WHERE u.id = ?
                ");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Error updating profile: " . $e->getMessage();
            }
        } 
        elseif ($_POST['action'] === 'change_password') {
            if (empty($_POST['current_password']) || empty($_POST['new_password']) || empty($_POST['confirm_password'])) {
                $error = "All password fields are required.";
            } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
                $error = "New passwords do not match.";
            } elseif (!password_verify($_POST['current_password'], $user['password'])) {
                $error = "Current password is incorrect.";
            } elseif (strlen($_POST['new_password']) < 6) {
                $error = "New password must be at least 6 characters long.";
            } else {
                try {
                    $new_password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$new_password_hash, $user_id]);
                    $message = "Password changed successfully!";
                } catch (PDOException $e) {
                    $error = "Error changing password: " . $e->getMessage();
                }
            }
        }
    }
}

// Get student's enrolled modules count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = (SELECT id FROM students WHERE user_id = ?) AND status = 'active'");
$stmt->execute([$user_id]);
$enrolled_modules = $stmt->fetchColumn();

// Get attendance statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
        COUNT(a.id) as total_sessions
    FROM attendance a 
    JOIN enrollments e ON a.enrollment_id = e.id 
    JOIN students s ON e.student_id = s.id 
    WHERE s.user_id = ?
");
$stmt->execute([$user_id]);
$attendance_stats = $stmt->fetch();
$attendance_rate = $attendance_stats['total_sessions'] > 0 ? 
    round(($attendance_stats['present_count'] / $attendance_stats['total_sessions']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - PAW Project</title>
    <link rel="stylesheet" href="../../css/admin-styles.css">
    <link rel="stylesheet" href="../../css/components.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="profile.css">
</head>
<body>
    <!-- Student Navigation -->
    <nav class="navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                <i class="fas fa-graduation-cap"></i>
                <span>Student Portal</span>
            </div>
            <div class="nav-links">
                <a href="../dashboard/dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>My Courses</span>
                </a>
                <a href="../attendance/attendance.php" class="nav-link">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>
                <a href="profile.php" class="nav-link active">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
            </div>
            <div class="user-section">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                        <div class="user-role"><?php echo htmlspecialchars($user['student_number'] ?? 'Student'); ?></div>
                    </div>
                </div>
                <a href="../../auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="profile-container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
            </div>
            <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
            <p><?php echo htmlspecialchars($user['specialty'] ?? 'Student'); ?></p>
            <p>Student ID: <?php echo htmlspecialchars($user['student_number'] ?? 'N/A'); ?></p>
        </div>

        <!-- Statistics -->
        <div class="profile-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $enrolled_modules; ?></div>
                <div>Enrolled Modules</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $attendance_stats['total_sessions']; ?></div>
                <div>Total Sessions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $attendance_rate; ?>%</div>
                <div>Attendance Rate</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $user['year_of_study'] ?? '1'; ?></div>
                <div>Year of Study</div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Personal Information -->
        <div class="form-section">
            <h3>🔷 Personal Information</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_info">
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" class="read-only-field" readonly>
                </div>

                <div class="form-group">
                    <label>Student Number</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['student_number'] ?? 'N/A'); ?>" class="read-only-field" readonly>
                </div>

                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Specialization</label>
                    <input type="text" name="specialization" value="<?php echo htmlspecialchars($user['specialization'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Specialty *</label>
                    <select name="specialty" required>
                        <option value="Computer Science" <?php echo $user['specialty'] === 'Computer Science' ? 'selected' : ''; ?>>Computer Science</option>
                        <option value="Software Engineering" <?php echo $user['specialty'] === 'Software Engineering' ? 'selected' : ''; ?>>Software Engineering</option>
                        <option value="Information Systems" <?php echo $user['specialty'] === 'Information Systems' ? 'selected' : ''; ?>>Information Systems</option>
                        <option value="Data Science" <?php echo $user['specialty'] === 'Data Science' ? 'selected' : ''; ?>>Data Science</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Year of Study *</label>
                    <select name="year_of_study" required>
                        <option value="1" <?php echo $user['year_of_study'] == 1 ? 'selected' : ''; ?>>1st Year</option>
                        <option value="2" <?php echo $user['year_of_study'] == 2 ? 'selected' : ''; ?>>2nd Year</option>
                        <option value="3" <?php echo $user['year_of_study'] == 3 ? 'selected' : ''; ?>>3rd Year</option>
                        <option value="4" <?php echo $user['year_of_study'] == 4 ? 'selected' : ''; ?>>4th Year</option>
                        <option value="5" <?php echo $user['year_of_study'] == 5 ? 'selected' : ''; ?>>5th Year</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary">Update Information</button>
                <a href="../dashboard/dashboard.php" class="btn-secondary">Back to Courses</a>
            </form>
        </div>

        <!-- Change Password -->
        <div class="form-section">
            <h3>🔒 Change Password</h3>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label>Current Password *</label>
                    <input type="password" name="current_password" required>
                </div>

                <div class="form-group">
                    <label>New Password * (minimum 6 characters)</label>
                    <input type="password" name="new_password" required minlength="6">
                </div>

                <div class="form-group">
                    <label>Confirm New Password *</label>
                    <input type="password" name="confirm_password" required minlength="6">
                </div>

                <button type="submit" class="btn-primary">Change Password</button>
            </form>
        </div>
    </div>

    <script src="profile.js"></script>
</body>
</html>
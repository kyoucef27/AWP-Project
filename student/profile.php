<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
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
    <link rel="stylesheet" href="../css/admin-styles.css">
    <link rel="stylesheet" href="../css/components.css">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Navbar Styles - matching student pages */
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            height: 70px;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.3rem;
            font-weight: 600;
            color: white;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            padding: 12px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateY(-1px);
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-role {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .logout-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
        }
        .profile-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 30px;
        }
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #28a745;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 10px;
        }
        .form-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        .form-section h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2);
        }
        .btn-primary, .btn-secondary {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        .btn-primary {
            background: #28a745;
            color: white;
        }
        .btn-primary:hover {
            background: #218838;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .read-only-field {
            background-color: #f8f9fa;
            color: #6c757d;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            margin: 0 auto 20px;
        }

        @media (max-width: 768px) {
            .navbar-content {
                padding: 0 15px;
                gap: 15px;
            }
            
            .nav-links {
                gap: 10px;
            }
            
            .nav-link span {
                display: none;
            }
            
            .profile-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
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
                <a href="home.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>My Courses</span>
                </a>
                <a href="attendance.php" class="nav-link">
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
                <a href="../auth/logout.php" class="logout-btn">
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
                <a href="home.php" class="btn-secondary">Back to Courses</a>
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

    <script>
        // Form validation for password change
        document.querySelector('form input[name="confirm_password"]')?.addEventListener('input', function() {
            const newPassword = document.querySelector('input[name="new_password"]').value;
            const confirmPassword = this.value;
            
            if (newPassword && confirmPassword && newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Auto-hide alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    </script>
</body>
</html>
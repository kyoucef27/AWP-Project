<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit();
}

$user = $_SESSION;

// Get statistics
try {
    require_once '../../includes/config.php';
    $pdo = getDBConnection();
    
    // Total counts
    $stats = [];
    
    // Users count by role
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $user_counts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $user_counts[$row['role']] = $row['count'];
    }
    
    // Tables count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
    $stats['tables'] = $stmt->fetch()['count'];
    
    // Module statistics
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM modules WHERE is_active = 1");
    $stats['modules'] = $stmt->fetch()['count'];
    
    // Teacher assignments
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM teacher_modules");
    $stats['assignments'] = $stmt->fetch()['count'];
    
} catch (PDOException $e) {
    $stats = ['tables' => 0, 'modules' => 0, 'assignments' => 0];
    $user_counts = ['admin' => 0, 'teacher' => 0, 'student' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PAW System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/admin-styles.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <?php include '../../includes/admin_navbar.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <div class="page-header">
                <div class="header-content">
                    <div>
                        <h1 class="page-title"><i class="fas fa-house"></i> Dashboard</h1>
                        <p class="page-subtitle">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>! Here's your system overview.</p>
                    </div>
                    <div class="header-badge">
                        <i class="fas fa-shield-alt"></i>
                        <span>Administrator</span>
                    </div>
                </div>
            </div>

            <!-- System Statistics -->
            <div class="stats-grid">
                <div class="stat-card stat-primary">
                    <div class="stat-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $user_counts['admin'] ?? 0; ?></div>
                        <div class="stat-label">Administrators</div>
                    </div>
                </div>
                <div class="stat-card stat-success">
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $user_counts['teacher'] ?? 0; ?></div>
                        <div class="stat-label">Teachers</div>
                    </div>
                </div>
                <div class="stat-card stat-info">
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $user_counts['student'] ?? 0; ?></div>
                        <div class="stat-label">Students</div>
                    </div>
                </div>
                <div class="stat-card stat-warning">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['modules']; ?></div>
                        <div class="stat-label">Active Modules</div>
                    </div>
                </div>
                <div class="stat-card stat-purple">
                    <div class="stat-icon">
                        <i class="fas fa-link"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['assignments']; ?></div>
                        <div class="stat-label">Assignments</div>
                    </div>
                </div>
                <div class="stat-card stat-secondary">
                    <div class="stat-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['tables']; ?></div>
                        <div class="stat-label">DB Tables</div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <h2 class="section-title"><i class="fas fa-bolt"></i> Quick Actions</h2>
            <div class="actions-grid">
                <div class="action-card action-success">
                    <div class="action-header">
                        <div class="action-icon">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <span class="action-badge badge-success">Active</span>
                    </div>
                    <h3 class="action-title">Teacher Management</h3>
                    <p class="action-description">Manage teacher accounts, import data, and view teaching assignments.</p>
                    <div class="action-meta">
                        <div class="action-stat">
                            <i class="fas fa-users"></i>
                            <span><?php echo $user_counts['teacher'] ?? 0; ?> Teachers</span>
                        </div>
                    </div>
                    <a href="../teacher_management/teacher_management.php" class="btn btn-success btn-sm action-button">
                        <i class="fas fa-arrow-right"></i> Manage
                    </a>
                </div>
                
                <div class="action-card action-info">
                    <div class="action-header">
                        <div class="action-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <span class="action-badge badge-info">Active</span>
                    </div>
                    <h3 class="action-title">Student Management</h3>
                    <p class="action-description">Oversee student accounts, enrollment, and academic progress tracking.</p>
                    <div class="action-meta">
                        <div class="action-stat">
                            <i class="fas fa-users"></i>
                            <span><?php echo $user_counts['student'] ?? 0; ?> Students</span>
                        </div>
                    </div>
                    <a href="../student_management.php" class="btn btn-info btn-sm action-button">
                        <i class="fas fa-arrow-right"></i> Manage
                    </a>
                </div>
                
                <div class="action-card action-primary">
                    <div class="action-header">
                        <div class="action-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <span class="action-badge badge-primary">Active</span>
                    </div>
                    <h3 class="action-title">Module Management</h3>
                    <p class="action-description">Add, edit, and organize academic modules. Import/export data and assign teachers.</p>
                    <div class="action-meta">
                        <div class="action-stat">
                            <i class="fas fa-book-open"></i>
                            <span><?php echo $stats['modules']; ?> Modules</span>
                        </div>
                    </div>
                    <a href="../module_management/module_management.php" class="btn btn-primary btn-sm action-button">
                        <i class="fas fa-arrow-right"></i> Manage
                    </a>
                </div>
                
                <div class="action-card action-warning">
                    <div class="action-header">
                        <div class="action-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <span class="action-badge badge-warning">Analytics</span>
                    </div>
                    <h3 class="action-title">Statistics & Reports</h3>
                    <p class="action-description">View detailed analytics, generate reports, and monitor system usage.</p>
                    <div class="action-meta">
                        <div class="action-stat">
                            <i class="fas fa-chart-bar"></i>
                            <span>Real-time Data</span>
                        </div>
                    </div>
                    <a href="../statistics.php" class="btn btn-warning btn-sm action-button">
                        <i class="fas fa-arrow-right"></i> View Stats
                    </a>
                </div>
                
                <div class="action-card action-danger">
                    <div class="action-header">
                        <div class="action-icon">
                            <i class="fas fa-database"></i>
                        </div>
                        <span class="action-badge badge-danger">Maintenance</span>
                    </div>
                    <h3 class="action-title">Data Management</h3>
                    <p class="action-description">Clean up test data, manage records, and perform maintenance tasks.</p>
                    <div class="action-meta">
                        <div class="action-stat">
                            <i class="fas fa-database"></i>
                            <span><?php echo $stats['tables']; ?> Tables</span>
                        </div>
                    </div>
                    <a href="../cleanup_test_data.php" class="btn btn-danger btn-sm action-button">
                        <i class="fas fa-arrow-right"></i> Manage Data
                    </a>
                </div>
                
                <div class="action-card action-secondary">
                    <div class="action-header">
                        <div class="action-icon">
                            <i class="fas fa-laptop-medical"></i>
                        </div>
                        <span class="action-badge badge-secondary">System</span>
                    </div>
                    <h3 class="action-title">System Diagnostics</h3>
                    <p class="action-description">Check system health, database connectivity, and troubleshoot issues.</p>
                    <div class="action-meta">
                        <div class="action-stat">
                            <i class="fas fa-check-circle"></i>
                            <span>All Systems OK</span>
                        </div>
                    </div>
                    <a href="../utilities/system_diagnostics.php" class="btn btn-secondary btn-sm action-button">
                        <i class="fas fa-arrow-right"></i> Run Check
                    </a>
                </div>
            </div>

            <!-- System Health -->
            <h2 class="section-title"><i class="fas fa-heartbeat"></i> System Health</h2>
            <div class="health-grid">
                <div class="health-card health-success">
                    <div class="health-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="health-content">
                        <div class="health-title">Database</div>
                        <div class="health-status">Connected</div>
                    </div>
                </div>
                <div class="health-card health-success">
                    <div class="health-icon">
                        <i class="fas fa-server"></i>
                    </div>
                    <div class="health-content">
                        <div class="health-title">Web Server</div>
                        <div class="health-status">Running</div>
                    </div>
                </div>
                <div class="health-card health-success">
                    <div class="health-icon">
                        <i class="fab fa-php"></i>
                    </div>
                    <div class="health-content">
                        <div class="health-title">PHP Version</div>
                        <div class="health-status"><?php echo PHP_VERSION; ?></div>
                    </div>
                </div>
                <div class="health-card health-info">
                    <div class="health-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="health-content">
                        <div class="health-title">Last Login</div>
                        <div class="health-status"><?php echo date('M j, H:i'); ?></div>
                    </div>
                </div>
            </div>


        </div>
    </div>

    <script src="dashboard.js"></script>
</body>
</html>

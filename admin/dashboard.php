<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$user = $_SESSION;

// Get statistics
try {
    require_once '../includes/config.php';
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
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
    $stats['tables'] = $stmt->fetch()['count'];
    
} catch (PDOException $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $user_counts = [];
    $stats = ['tables' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Algiers University Attendance System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .dropdown {
            position: relative;
        }
        
        .dropdown-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            min-width: 200px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-radius: 8px;
            overflow: hidden;
            z-index: 1000;
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        .dropdown-item {
            display: block;
            padding: 12px 16px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .dropdown-item:hover {
            background: #f8f9fa;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 0.5rem;
            font-weight: 300;
        }
        
        .breadcrumb {
            color: #666;
            font-size: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-left: 5px solid;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.students { border-left-color: #3498db; }
        .stat-card.professors { border-left-color: #2ecc71; }
        .stat-card.admins { border-left-color: #e74c3c; }
        .stat-card.courses { border-left-color: #f39c12; }
        .stat-card.groups { border-left-color: #9b59b6; }
        .stat-card.sessions { border-left-color: #1abc9c; }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .main-content, .sidebar {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1.5rem;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .action-btn:hover {
            border-color: #667eea;
            background: #f0f0ff;
            transform: translateY(-2px);
        }
        
        .action-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #667eea;
        }
        
        .activity-list {
            list-style: none;
        }
        
        .activity-item {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-description {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .activity-meta {
            font-size: 0.875rem;
            color: #666;
        }
        
        .activity-time {
            font-size: 0.75rem;
            color: #999;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .quick-actions {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo">
                🎓 AUAS Admin
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($user['username']); ?></span>
                <div class="dropdown">
                    <button class="dropdown-btn">
                        ⚙️ Admin Menu ▼
                    </button>
                    <div class="dropdown-content">
                        <a href="#" class="dropdown-item">👤 Profile</a>
                        <a href="#" class="dropdown-item">🔧 System Settings</a>
                        <a href="../wamp_status.php" class="dropdown-item">📊 System Status</a>
                        <a href="../auth/logout.php" class="dropdown-item">🚪 Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>📊 Admin Dashboard</h1>
            <div class="breadcrumb">
                Home / Dashboard / Administrator
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card students">
                <div class="stat-icon">👨‍🎓</div>
                <div class="stat-number"><?php echo $user_counts['student'] ?? 0; ?></div>
                <div class="stat-label">Students</div>
            </div>
            
            <div class="stat-card professors">
                <div class="stat-icon">👨‍🏫</div>
                <div class="stat-number"><?php echo $user_counts['professor'] ?? 0; ?></div>
                <div class="stat-label">Professors</div>
            </div>
            
            <div class="stat-card admins">
                <div class="stat-icon">👨‍💼</div>
                <div class="stat-number"><?php echo $user_counts['admin'] ?? 0; ?></div>
                <div class="stat-label">Administrators</div>
            </div>
            
            <div class="stat-card courses">
                <div class="stat-icon">📚</div>
                <div class="stat-number"><?php echo $stats['tables'] ?? 0; ?></div>
                <div class="stat-label">Database Tables</div>
            </div>
            
            <div class="stat-card groups">
                <div class="stat-icon">💾</div>
                <div class="stat-number">SQLite</div>
                <div class="stat-label">Database Type</div>
            </div>
            
            <div class="stat-card sessions">
                <div class="stat-icon">✅</div>
                <div class="stat-number">Active</div>
                <div class="stat-label">System Status</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="main-content">
                <h2 class="section-title">⚡ Quick Actions</h2>
                <div class="quick-actions">
                    <a href="../debug_users.php" class="action-btn">
                        <div class="action-icon">👤</div>
                        <div>View Users</div>
                    </a>
                    <a href="../setup_database.php" class="action-btn">
                        <div class="action-icon">🔧</div>
                        <div>Database Setup</div>
                    </a>
                    <a href="../wamp_status.php" class="action-btn">
                        <div class="action-icon">📊</div>
                        <div>System Status</div>
                    </a>
                    <a href="../test_connection.php" class="action-btn">
                        <div class="action-icon">🔌</div>
                        <div>Test Connection</div>
                    </a>
                    <a href="../login_test.php" class="action-btn">
                        <div class="action-icon">🧪</div>
                        <div>Login Test</div>
                    </a>
                    <a href="../setup_debug.php" class="action-btn">
                        <div class="action-icon">🐛</div>
                        <div>Debug Tools</div>
                    </a>
                </div>
            </div>

            <div class="sidebar">
                <h3 class="section-title">🎯 System Information</h3>
                <ul class="activity-list">
                    <li class="activity-item">
                        <div class="activity-icon">🗄️</div>
                        <div class="activity-content">
                            <div class="activity-description">Database Type</div>
                            <div class="activity-meta">SQLite - File-based database</div>
                        </div>
                    </li>
                    <li class="activity-item">
                        <div class="activity-icon">🌐</div>
                        <div class="activity-content">
                            <div class="activity-description">Web Server</div>
                            <div class="activity-meta">PHP Built-in Server (Port 8000)</div>
                        </div>
                    </li>
                    <li class="activity-item">
                        <div class="activity-icon">👤</div>
                        <div class="activity-content">
                            <div class="activity-description">Current User</div>
                            <div class="activity-meta"><?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['role']); ?>)</div>
                        </div>
                    </li>
                    <li class="activity-item">
                        <div class="activity-icon">⏰</div>
                        <div class="activity-content">
                            <div class="activity-description">Login Time</div>
                            <div class="activity-meta"><?php echo date('M j, Y g:i A'); ?></div>
                        </div>
                    </li>
                </ul>
                
                <div style="margin-top: 2rem;">
                    <a href="../auth/logout.php" style="color: #e74c3c; text-decoration: none; font-weight: 500;">
                        🚪 Logout →
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
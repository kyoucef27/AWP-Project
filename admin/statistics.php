<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$user = $_SESSION;

// Get comprehensive statistics
try {
    require_once '../includes/config.php';
    $pdo = getDBConnection();
    
    $stats = [];
    
    // Users count by role
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $user_counts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $user_counts[$row['role']] = $row['count'];
    }
    
    // Total users
    $stats['total_users'] = array_sum($user_counts);
    $stats['students'] = $user_counts['student'] ?? 0;
    $stats['professors'] = $user_counts['professor'] ?? 0;
    $stats['admins'] = $user_counts['admin'] ?? 0;
    
    // Database tables count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
    $stats['tables'] = $stmt->fetch()['count'];
    
    // Module statistics
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM modules");
    $stats['total_modules'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM modules WHERE is_active = 1");
    $stats['active_modules'] = $stmt->fetch()['count'];
    
    // Teacher assignments
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM teacher_modules");
    $stats['teacher_assignments'] = $stmt->fetch()['count'];
    
    // Teachers with modules
    $stmt = $pdo->query("SELECT COUNT(DISTINCT teacher_id) as count FROM teacher_modules");
    $stats['teachers_with_modules'] = $stmt->fetch()['count'];
    
    $stats['teachers_total'] = $user_counts['teacher'] ?? 0;
    
    // Recent activity (last 30 days) - simulated data for now
    $stats['recent_logins'] = rand(50, 200);
    $stats['active_sessions'] = rand(10, 50);
    $stats['system_uptime'] = '15 days, 8 hours';
    
    // Monthly data for charts (simulated)
    $monthly_users = [];
    $monthly_activity = [];
    for ($i = 11; $i >= 0; $i--) {
        $month = date('M Y', strtotime("-$i months"));
        $monthly_users[] = [
            'month' => $month,
            'students' => rand(50, 150),
            'professors' => rand(5, 25),
            'admins' => rand(1, 5)
        ];
        $monthly_activity[] = [
            'month' => $month,
            'logins' => rand(100, 500),
            'sessions' => rand(20, 80)
        ];
    }
    
} catch (PDOException $e) {
    error_log("Admin statistics error: " . $e->getMessage());
    $stats = [];
    $user_counts = [];
    $monthly_users = [];
    $monthly_activity = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics - Admin Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            transition: background 0.3s;
        }
        
        .nav-link:hover,
        .nav-link.active {
            background: rgba(255,255,255,0.2);
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
        
        .stat-card.users { border-left-color: #3498db; }
        .stat-card.students { border-left-color: #2ecc71; }
        .stat-card.professors { border-left-color: #e74c3c; }
        .stat-card.teachers { border-left-color: #8e44ad; }
        .stat-card.modules { border-left-color: #f39c12; }
        .stat-card.assignments { border-left-color: #16a085; }
        .stat-card.activity { border-left-color: #f39c12; }
        .stat-card.sessions { border-left-color: #9b59b6; }
        .stat-card.uptime { border-left-color: #1abc9c; }
        
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
        
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .chart-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .chart-title {
            font-size: 1.25rem;
            color: #333;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .insight-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .insight-title {
            font-size: 1.25rem;
            color: #333;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .insight-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .insight-item:last-child {
            border-bottom: none;
        }
        
        .insight-label {
            color: #666;
            font-weight: 500;
        }
        
        .insight-value {
            color: #333;
            font-weight: 600;
        }
        
        .trend-up {
            color: #2ecc71;
        }
        
        .trend-down {
            color: #e74c3c;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .nav-links {
                display: none;
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
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link">🏠 Home</a>
                <a href="statistics.php" class="nav-link active">📊 Statistics</a>
                <a href="student_management.php" class="nav-link">👥 Students</a>
                <a href="teacher_management.php" class="nav-link">👨‍🏫 Teachers</a>
                <a href="module_management.php" class="nav-link">📚 Modules</a>
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
            <h1>📊 Statistics & Analytics</h1>
            <div class="breadcrumb">
                Home / Admin / Statistics
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card users">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?php echo $stats['total_users'] ?? 0; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            
            <div class="stat-card students">
                <div class="stat-icon">👨‍🎓</div>
                <div class="stat-number"><?php echo $stats['students'] ?? 0; ?></div>
                <div class="stat-label">Students</div>
            </div>
            
            <div class="stat-card professors">
                <div class="stat-icon">👨‍🏫</div>
                <div class="stat-number"><?php echo $stats['professors'] ?? 0; ?></div>
                <div class="stat-label">Professors</div>
            </div>
            
            <div class="stat-card teachers">
                <div class="stat-icon">👨‍🏫</div>
                <div class="stat-number"><?php echo $stats['teachers_total'] ?? 0; ?></div>
                <div class="stat-label">Teachers</div>
            </div>
            
            <div class="stat-card modules">
                <div class="stat-icon">📚</div>
                <div class="stat-number"><?php echo $stats['total_modules'] ?? 0; ?></div>
                <div class="stat-label">Total Modules</div>
            </div>
            
            <div class="stat-card assignments">
                <div class="stat-icon">🔗</div>
                <div class="stat-number"><?php echo $stats['teacher_assignments'] ?? 0; ?></div>
                <div class="stat-label">Teacher Assignments</div>
            </div>
            
            <div class="stat-card activity">
                <div class="stat-icon">📈</div>
                <div class="stat-number"><?php echo $stats['recent_logins'] ?? 0; ?></div>
                <div class="stat-label">Recent Logins</div>
            </div>
            
            <div class="stat-card uptime">
                <div class="stat-icon">⏱️</div>
                <div class="stat-number"><?php echo $stats['system_uptime'] ?? 'N/A'; ?></div>
                <div class="stat-label">System Uptime</div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-title">👥 User Growth Over Time</div>
                <div class="chart-container">
                    <canvas id="userGrowthChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-title">📊 User Distribution</div>
                <div class="chart-container">
                    <canvas id="userDistributionChart"></canvas>
                </div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-title">🔄 System Activity</div>
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-title">📈 Login Trends</div>
                <div class="chart-container">
                    <canvas id="loginTrendsChart"></canvas>
                </div>
            </div>
        </div>

        <div class="insights-grid">
            <div class="insight-card">
                <div class="insight-title">📊 Key Metrics</div>
                <div class="insight-item">
                    <span class="insight-label">Average Daily Logins</span>
                    <span class="insight-value trend-up">25 (+12%)</span>
                </div>
                <div class="insight-item">
                    <span class="insight-label">System Performance</span>
                    <span class="insight-value trend-up">98.5%</span>
                </div>
                <div class="insight-item">
                    <span class="insight-label">Database Size</span>
                    <span class="insight-value">45.2 MB</span>
                </div>
                <div class="insight-item">
                    <span class="insight-label">Error Rate</span>
                    <span class="insight-value trend-down">0.1%</span>
                </div>
            </div>
            
            <div class="insight-card">
                <div class="insight-title">📚 Module Analytics</div>
                <div class="insight-item">
                    <span class="insight-label">Active Modules</span>
                    <span class="insight-value"><?php echo $stats['active_modules'] ?? 0; ?> / <?php echo $stats['total_modules'] ?? 0; ?></span>
                </div>
                <div class="insight-item">
                    <span class="insight-label">Teachers with Modules</span>
                    <span class="insight-value"><?php echo $stats['teachers_with_modules'] ?? 0; ?> / <?php echo $stats['teachers_total'] ?? 0; ?></span>
                </div>
                <div class="insight-item">
                    <span class="insight-label">Avg Modules per Teacher</span>
                    <span class="insight-value">
                        <?php 
                        if ($stats['teachers_with_modules'] > 0) {
                            echo round($stats['teacher_assignments'] / $stats['teachers_with_modules'], 1);
                        } else {
                            echo '0';
                        }
                        ?>
                    </span>
                </div>
                <div class="insight-item">
                    <span class="insight-label">Module Utilization</span>
                    <span class="insight-value trend-up">
                        <?php 
                        if ($stats['total_modules'] > 0) {
                            echo round(($stats['active_modules'] / $stats['total_modules']) * 100, 1) . '%';
                        } else {
                            echo '0%';
                        }
                        ?>
                    </span>
                </div>
            </div>
            
            <div class="insight-card">
                <div class="insight-title">🎯 Usage Insights</div>
                <div class="insight-item">
                    <span class="insight-label">Most Active Hour</span>
                    <span class="insight-value">2:00 PM - 3:00 PM</span>
                </div>
                <div class="insight-item">
                    <span class="insight-label">Peak Day</span>
                    <span class="insight-value">Tuesday</span>
                </div>
                <div class="insight-item">
                    <span class="insight-label">Avg Session Duration</span>
                    <span class="insight-value">45 minutes</span>
                </div>
                <div class="insight-item">
                    <span class="insight-label">Mobile Usage</span>
                    <span class="insight-value trend-up">32%</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart.js configurations
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        };

        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthly_users); ?>;
        
        new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(d => d.month),
                datasets: [{
                    label: 'Students',
                    data: monthlyData.map(d => d.students),
                    borderColor: '#2ecc71',
                    backgroundColor: 'rgba(46, 204, 113, 0.1)',
                    fill: true
                }, {
                    label: 'Professors',
                    data: monthlyData.map(d => d.professors),
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    fill: true
                }]
            },
            options: chartOptions
        });

        // User Distribution Chart
        const userDistCtx = document.getElementById('userDistributionChart').getContext('2d');
        new Chart(userDistCtx, {
            type: 'doughnut',
            data: {
                labels: ['Students', 'Professors', 'Admins'],
                datasets: [{
                    data: [<?php echo $stats['students']; ?>, <?php echo $stats['professors']; ?>, <?php echo $stats['admins']; ?>],
                    backgroundColor: ['#3498db', '#2ecc71', '#e74c3c']
                }]
            },
            options: chartOptions
        });

        // Activity Chart
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        const activityData = <?php echo json_encode($monthly_activity); ?>;
        
        new Chart(activityCtx, {
            type: 'bar',
            data: {
                labels: activityData.map(d => d.month),
                datasets: [{
                    label: 'Logins',
                    data: activityData.map(d => d.logins),
                    backgroundColor: '#3498db'
                }, {
                    label: 'Sessions',
                    data: activityData.map(d => d.sessions),
                    backgroundColor: '#9b59b6'
                }]
            },
            options: chartOptions
        });

        // Login Trends Chart
        const loginTrendsCtx = document.getElementById('loginTrendsChart').getContext('2d');
        new Chart(loginTrendsCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Daily Logins',
                    data: [45, 62, 38, 55, 48, 25, 18],
                    borderColor: '#f39c12',
                    backgroundColor: 'rgba(243, 156, 18, 0.1)',
                    fill: true
                }]
            },
            options: chartOptions
        });
    </script>
</body>
</html>
<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit();
}

$user = $_SESSION;

// Get comprehensive statistics
try {
    require_once '../../includes/config.php';
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="statistics.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../../includes/admin_navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-chart-bar"></i> Statistics & Analytics</h1>
            <div class="breadcrumb">
                Home / Admin / Statistics
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card users">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo $stats['total_users'] ?? 0; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            
            <div class="stat-card students">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-number"><?php echo $stats['students'] ?? 0; ?></div>
                <div class="stat-label">Students</div>
            </div>
            
            <div class="stat-card professors">
                <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo $stats['professors'] ?? 0; ?></div>
                <div class="stat-label">Professors</div>
            </div>
            
            <div class="stat-card teachers">
                <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="stat-number"><?php echo $stats['teachers_total'] ?? 0; ?></div>
                <div class="stat-label">Teachers</div>
            </div>
            
            <div class="stat-card modules">
                <div class="stat-icon"><i class="fas fa-book"></i></div>
                <div class="stat-number"><?php echo $stats['total_modules'] ?? 0; ?></div>
                <div class="stat-label">Total Modules</div>
            </div>
            
            <div class="stat-card assignments">
                <div class="stat-icon"><i class="fas fa-link"></i></div>
                <div class="stat-number"><?php echo $stats['teacher_assignments'] ?? 0; ?></div>
                <div class="stat-label">Teacher Assignments</div>
            </div>
            
            <div class="stat-card activity">
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                <div class="stat-number"><?php echo $stats['recent_logins'] ?? 0; ?></div>
                <div class="stat-label">Recent Logins</div>
            </div>
            
            <div class="stat-card uptime">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo $stats['system_uptime'] ?? 'N/A'; ?></div>
                <div class="stat-label">System Uptime</div>
            </div>
        </div>

        <div class="mobile-quick-panel mobile-only">
            <div class="mobile-card">
                <div class="mobile-card-icon primary">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div>
                    <div class="mobile-card-title">High-Level Stats</div>
                    <div class="mobile-card-value"><?php echo $stats['total_users'] ?? 0; ?> Users</div>
                    <div class="mobile-card-meta"><?php echo $stats['teachers_total'] ?? 0; ?> Teachers · <?php echo $stats['total_modules'] ?? 0; ?> Modules</div>
                    <a href="#chartsOverview" class="mobile-card-link">
                        <i class="fas fa-chart-pie"></i> View charts
                    </a>
                </div>
            </div>
            <div class="mobile-card">
                <div class="mobile-card-icon success">
                    <i class="fas fa-bolt"></i>
                </div>
                <div>
                    <div class="mobile-card-title">Activity Snapshot</div>
                    <div class="mobile-card-value"><?php echo $stats['recent_logins'] ?? 0; ?> Logins</div>
                    <div class="mobile-card-meta"><?php echo $stats['active_sessions'] ?? '?'; ?> active sessions</div>
                    <a href="#insights" class="mobile-card-link">
                        <i class="fas fa-info-circle"></i> Read insights
                    </a>
                </div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-users"></i> User Growth Over Time</div>
                <div class="chart-container">
                    <canvas id="userGrowthChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-chart-pie"></i> User Distribution</div>
                <div class="chart-container">
                    <canvas id="userDistributionChart"></canvas>
                </div>
            </div>
        </div>

        <div class="charts-grid" id="chartsOverview">
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-sync-alt"></i> System Activity</div>
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-title"><i class="fas fa-chart-line"></i> Login Trends</div>
                <div class="chart-container">
                    <canvas id="loginTrendsChart"></canvas>
                </div>
            </div>
        </div>

        <div class="insights-grid">
            <div class="insight-card" id="insights">
                <div class="insight-title"><i class="fas fa-tachometer-alt"></i> Key Metrics</div>
                <div class="insight-title"><i class="fas fa-tachometer-alt"></i> Key Metrics</div>
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
                <div class="insight-title"><i class="fas fa-book-open"></i> Module Analytics</div>
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
                <div class="insight-title"><i class="fas fa-bullseye"></i> Usage Insights</div>
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

    <script src="statistics.js"></script>
    <script>
        // Initialize charts with PHP data
        const monthlyData = <?php echo json_encode($monthly_users); ?>;
        const activityData = <?php echo json_encode($monthly_activity); ?>;
        const stats = <?php echo json_encode($stats); ?>;

        // Initialize all charts
        initUserGrowthChart(monthlyData);
        initUserDistributionChart(stats);
        initActivityChart(activityData);
        initLoginTrendsChart();
    </script>
</body>
</html>

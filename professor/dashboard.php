<?php
session_start();

// Check if user is logged in and is professor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../auth/login.php");
    exit();
}

$user = $_SESSION;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professor Dashboard - PAW Project</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 0;
        }
        
        .navbar {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .welcome-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .feature-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 2rem;
            text-align: center;
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #2ecc71;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 1rem;
            transition: background 0.3s ease;
        }
        
        .btn:hover {
            background: #27ae60;
        }
        
        .logout-btn {
            background: #e74c3c;
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo">👨‍🏫 Professor Portal</div>
            <div>
                <span>Welcome, <?php echo htmlspecialchars($user['username']); ?></span>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="welcome-card">
            <h1>👨‍🏫 Professor Dashboard</h1>
            <p>Welcome to your professor portal, <?php echo htmlspecialchars($user['username']); ?>!</p>
            <p><strong>Role:</strong> <?php echo htmlspecialchars($user['role']); ?></p>
        </div>

        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon">✅</div>
                <h3>Take Attendance</h3>
                <p>Mark student attendance for your classes</p>
                <a href="#" class="btn">Coming Soon</a>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>View Reports</h3>
                <p>Generate attendance reports and statistics</p>
                <a href="#" class="btn">Coming Soon</a>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>Manage Students</h3>
                <p>View and manage your class rosters</p>
                <a href="#" class="btn">Coming Soon</a>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📝</div>
                <h3>Review Justifications</h3>
                <p>Review student absence justifications</p>
                <a href="#" class="btn">Coming Soon</a>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">📚</div>
                <h3>Course Management</h3>
                <p>Manage your courses and schedules</p>
                <a href="#" class="btn">Coming Soon</a>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">🔧</div>
                <h3>System Tools</h3>
                <p>Access system status and admin tools</p>
                <a href="../wamp_status.php" class="btn">System Status</a>
            </div>
        </div>
    </div>
</body>
</html>
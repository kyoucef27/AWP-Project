<?php
session_start();

// Check if user is logged in and is teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../auth/login.php");
    exit();
}

$user = $_SESSION;

// Get teacher data and assigned modules
try {
    require_once '../includes/config.php';
    $pdo = getDBConnection();
    
    // Get teacher profile information
    $stmt = $pdo->prepare("
        SELECT t.*, u.full_name, u.email 
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $teacher_profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get assigned modules
    $stmt = $pdo->prepare("
        SELECT m.*, tm.role as teaching_role, tm.assigned_at
        FROM modules m
        JOIN teacher_modules tm ON m.id = tm.module_id
        WHERE tm.teacher_id = ? AND m.is_active = 1
        ORDER BY m.module_code
    ");
    $stmt->execute([$teacher_profile['id'] ?? 0]);
    $assigned_modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats = [];
    $stats['total_modules'] = count($assigned_modules);
    
    // Count by semester
    $stats['fall_modules'] = 0;
    $stats['spring_modules'] = 0;
    $stats['summer_modules'] = 0;
    $stats['both_modules'] = 0;
    
    foreach ($assigned_modules as $module) {
        switch ($module['semester']) {
            case 'Fall':
                $stats['fall_modules']++;
                break;
            case 'Spring':
                $stats['spring_modules']++;
                break;
            case 'Summer':
                $stats['summer_modules']++;
                break;
            case 'Both':
                $stats['both_modules']++;
                break;
        }
    }
    
    // Calculate total credits
    $stats['total_credits'] = array_sum(array_column($assigned_modules, 'credits'));
    
} catch (PDOException $e) {
    error_log("Teacher dashboard error: " . $e->getMessage());
    $teacher_profile = [];
    $assigned_modules = [];
    $stats = ['total_modules' => 0, 'total_credits' => 0, 'fall_modules' => 0, 'spring_modules' => 0, 'summer_modules' => 0, 'both_modules' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - PAW Project</title>
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
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
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
        
        .nav-link:hover {
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
        
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 2rem;
            align-items: center;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
        }
        
        .profile-info h2 {
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .profile-detail {
            color: #666;
            margin-bottom: 0.25rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            text-align: center;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.modules { border-left-color: #8e44ad; }
        .stat-card.credits { border-left-color: #3498db; }
        .stat-card.fall { border-left-color: #e67e22; }
        .stat-card.spring { border-left-color: #2ecc71; }
        .stat-card.summer { border-left-color: #f39c12; }
        .stat-card.both { border-left-color: #e74c3c; }
        
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
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        
        .modules-section {
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
            border-bottom: 2px solid #8e44ad;
            padding-bottom: 0.5rem;
        }
        
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .module-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            border-left: 5px solid #8e44ad;
            transition: transform 0.3s ease;
        }
        
        .module-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .module-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .module-code {
            font-size: 1.1rem;
            font-weight: bold;
            color: #8e44ad;
        }
        
        .module-role {
            background: #8e44ad;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        
        .module-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .module-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .module-detail {
            font-size: 0.9rem;
            color: #666;
        }
        
        .module-description {
            color: #555;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #ddd;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .profile-card {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .nav-links {
                display: none;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo">
                👨‍🏫 Teacher Portal
            </div>
            <div class="nav-links">
                <a href="#" class="nav-link">🏠 Home</a>
                <a href="#modules" class="nav-link">📚 My Modules</a>
                <a href="#" class="nav-link">📊 Reports</a>
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($user['username']); ?></span>
                <div class="dropdown">
                    <button class="dropdown-btn">
                        ⚙️ Menu ▼
                    </button>
                    <div class="dropdown-content">
                        <a href="#" class="dropdown-item">👤 Profile</a>
                        <a href="../wamp_status.php" class="dropdown-item">📊 System Status</a>
                        <a href="../auth/logout.php" class="dropdown-item">🚪 Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>👨‍🏫 Teacher Dashboard</h1>
            <div class="breadcrumb">
                Home / Teacher / Dashboard
            </div>
        </div>

        <?php if ($teacher_profile): ?>
        <div class="profile-card">
            <div class="profile-avatar">
                👨‍🏫
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($teacher_profile['full_name']); ?></h2>
                <div class="profile-detail"><strong>Teacher ID:</strong> <?php echo htmlspecialchars($teacher_profile['teacher_id'] ?? 'N/A'); ?></div>
                <div class="profile-detail"><strong>Department:</strong> <?php echo htmlspecialchars($teacher_profile['department'] ?? 'N/A'); ?></div>
                <div class="profile-detail"><strong>Position:</strong> <?php echo htmlspecialchars($teacher_profile['position'] ?? 'N/A'); ?></div>
                <div class="profile-detail"><strong>Email:</strong> <?php echo htmlspecialchars($teacher_profile['email']); ?></div>
                <?php if ($teacher_profile['specialization']): ?>
                <div class="profile-detail"><strong>Specialization:</strong> <?php echo htmlspecialchars($teacher_profile['specialization']); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card modules">
                <div class="stat-icon">📚</div>
                <div class="stat-number"><?php echo $stats['total_modules']; ?></div>
                <div class="stat-label">Total Modules</div>
            </div>
            
            <div class="stat-card credits">
                <div class="stat-icon">🎯</div>
                <div class="stat-number"><?php echo $stats['total_credits']; ?></div>
                <div class="stat-label">Total Credits</div>
            </div>
            
            <div class="stat-card fall">
                <div class="stat-icon">🍂</div>
                <div class="stat-number"><?php echo $stats['fall_modules']; ?></div>
                <div class="stat-label">Fall Semester</div>
            </div>
            
            <div class="stat-card spring">
                <div class="stat-icon">🌸</div>
                <div class="stat-number"><?php echo $stats['spring_modules']; ?></div>
                <div class="stat-label">Spring Semester</div>
            </div>
            
            <?php if ($stats['summer_modules'] > 0): ?>
            <div class="stat-card summer">
                <div class="stat-icon">☀️</div>
                <div class="stat-number"><?php echo $stats['summer_modules']; ?></div>
                <div class="stat-label">Summer Semester</div>
            </div>
            <?php endif; ?>
            
            <?php if ($stats['both_modules'] > 0): ?>
            <div class="stat-card both">
                <div class="stat-icon">📅</div>
                <div class="stat-number"><?php echo $stats['both_modules']; ?></div>
                <div class="stat-label">Both Semesters</div>
            </div>
            <?php endif; ?>
        </div>

        <div class="content-grid">
            <div class="modules-section" id="modules">
                <h2 class="section-title">📚 My Assigned Modules</h2>
                
                <?php if (!empty($assigned_modules)): ?>
                <div class="modules-grid">
                    <?php foreach ($assigned_modules as $module): ?>
                    <div class="module-card">
                        <div class="module-header">
                            <div class="module-code"><?php echo htmlspecialchars($module['module_code']); ?></div>
                            <div class="module-role"><?php echo htmlspecialchars($module['teaching_role']); ?></div>
                        </div>
                        
                        <div class="module-name"><?php echo htmlspecialchars($module['module_name']); ?></div>
                        
                        <div class="module-details">
                            <div class="module-detail">
                                <strong>Credits:</strong> <?php echo $module['credits']; ?>
                            </div>
                            <div class="module-detail">
                                <strong>Year:</strong> <?php echo $module['year_level']; ?>
                            </div>
                            <div class="module-detail">
                                <strong>Semester:</strong> <?php echo htmlspecialchars($module['semester']); ?>
                            </div>
                            <div class="module-detail">
                                <strong>Department:</strong> <?php echo htmlspecialchars($module['department']); ?>
                            </div>
                        </div>
                        
                        <?php if ($module['description']): ?>
                        <div class="module-description">
                            <?php echo htmlspecialchars($module['description']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📚</div>
                    <h3>No Modules Assigned</h3>
                    <p>You don't have any modules assigned yet. Please contact your administrator.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
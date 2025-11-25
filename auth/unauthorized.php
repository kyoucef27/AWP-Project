<?php
session_start();
$user = isset($_SESSION['user_id']) ? [
    'role' => $_SESSION['role'] ?? 'unknown',
    'username' => $_SESSION['username'] ?? 'Unknown'
] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - Algiers University Attendance System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 60px 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        
        .error-icon {
            font-size: 80px;
            color: #ff6b6b;
            margin-bottom: 30px;
            animation: shake 1s ease-in-out infinite alternate;
        }
        
        @keyframes shake {
            0% { transform: rotate(-5deg); }
            100% { transform: rotate(5deg); }
        }
        
        h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 20px;
            font-weight: 300;
        }
        
        .error-message {
            color: #666;
            font-size: 1.2em;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .user-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #ff6b6b;
        }
        
        .user-info h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .user-info p {
            color: #666;
            margin-bottom: 5px;
        }
        
        .actions {
            display: grid;
            gap: 15px;
            margin-top: 40px;
        }
        
        .btn {
            padding: 15px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #495057;
            border: 2px solid #dee2e6;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .help-section {
            background: #e7f3ff;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
            text-align: left;
        }
        
        .help-section h4 {
            color: #2196F3;
            margin-bottom: 15px;
        }
        
        .help-section ul {
            color: #333;
            padding-left: 20px;
        }
        
        .help-section li {
            margin-bottom: 8px;
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 40px 25px;
            }
            
            h1 {
                font-size: 2em;
            }
            
            .error-icon {
                font-size: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">🚫</div>
        
        <h1>Access Denied</h1>
        
        <div class="error-message">
            You don't have permission to access this page or perform this action.
        </div>
        
        <?php if ($user): ?>
        <div class="user-info">
            <h3>Current Session</h3>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
            <p><strong>Role:</strong> <?php echo ucfirst(htmlspecialchars($user['role'])); ?></p>
            <p><strong>Status:</strong> <span style="color: #ff6b6b;">Insufficient Privileges</span></p>
        </div>
        <?php endif; ?>
        
        <div class="actions">
            <?php if ($user): ?>
                <a href="<?php 
                    switch($user['role']) {
                        case 'admin': echo '../admin/dashboard.php'; break;
                        case 'professor': echo '../professor/dashboard.php'; break;
                        case 'student': echo '../student/dashboard.php'; break;
                        default: echo '../index.html'; break;
                    }
                ?>" class="btn btn-primary">
                    🏠 Go to My Dashboard
                </a>
                <a href="logout.php" class="btn btn-secondary">
                    🚪 Logout
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">
                    🔐 Login
                </a>
                <a href="../index.html" class="btn btn-secondary">
                    🏠 Go to Home
                </a>
            <?php endif; ?>
        </div>
        
        <div class="help-section">
            <h4>💡 What you can do:</h4>
            <ul>
                <li><strong>Students:</strong> View your attendance, submit justifications, and access course information</li>
                <li><strong>Professors:</strong> Manage attendance sessions, mark attendance, and generate reports</li>
                <li><strong>Administrators:</strong> Full system access including user management and analytics</li>
                <li><strong>Need help?</strong> Contact your system administrator for role changes or access requests</li>
            </ul>
        </div>
    </div>
</body>
</html>
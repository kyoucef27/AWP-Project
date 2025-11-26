<?php
session_start();
require_once '../includes/config.php';
// Note: getDBConnection() is now in config.php

// If user is already logged in, redirect to their dashboard
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'admin';
    switch($role) {
        case 'admin':
            header("Location: ../admin/dashboard.php");
            break;
        case 'professor':
            header("Location: ../professor/dashboard.php");
            break;
        case 'teacher':
            header("Location: ../teacher/dashboard.php");
            break;
        case 'student':
            header("Location: ../student/dashboard.php");
            break;
        default:
            header("Location: ../index.html");
            break;
    }
    exit();
}

$error_message = '';
$success_message = '';

// Check for logout success message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success_message = 'You have been successfully logged out.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        try {
            $pdo = getDBConnection();
            
            if ($pdo === null) {
                $error_message = 'Database connection failed. Please check if the database is set up.';
            } else {
                // Try to get user from users table
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($password, $user['password'])) {
                    // Successful login - create session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    
                    // Redirect based on role
                    switch($user['role']) {
                        case 'admin':
                            header("Location: ../admin/dashboard.php");
                            break;
                        case 'professor':
                            header("Location: ../professor/dashboard.php");
                            break;
                        case 'teacher':
                            header("Location: ../teacher/dashboard.php");
                            break;
                        case 'student':
                            header("Location: ../student/dashboard.php");
                            break;
                        default:
                            header("Location: ../index.html");
                            break;
                    }
                    exit();
                } else {
                    $error_message = 'Invalid username or password.';
                }
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error_message = 'A database error occurred. Please try setting up the database first.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Algiers University Attendance System</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 600px;
        }
        
        .login-form-section {
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-info-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-info-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s infinite linear;
        }
        
        @keyframes float {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(-50px, -50px) rotate(360deg); }
        }
        
        .logo {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            z-index: 2;
        }
        
        .logo h1 {
            font-size: 3em;
            margin-bottom: 10px;
            font-weight: 300;
        }
        
        .logo p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .welcome-text {
            position: relative;
            z-index: 2;
        }
        
        .welcome-text h2 {
            font-size: 2.2em;
            margin-bottom: 20px;
            font-weight: 300;
        }
        
        .features {
            list-style: none;
            padding: 0;
            margin: 30px 0;
        }
        
        .features li {
            padding: 10px 0;
            position: relative;
            padding-left: 25px;
        }
        
        .features li::before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #4CAF50;
            font-weight: bold;
            font-size: 1.2em;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .form-header h2 {
            color: #333;
            font-size: 2em;
            margin-bottom: 10px;
            font-weight: 300;
        }
        
        .form-header p {
            color: #666;
            font-size: 1em;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s ease;
            margin-top: 20px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .demo-accounts {
            background: #e7f3ff;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .demo-accounts h4 {
            color: #2196F3;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .demo-account {
            background: white;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            font-size: 14px;
            border-left: 3px solid #2196F3;
        }
        
        .demo-account strong {
            color: #333;
        }
        
        .back-link {
            text-align: center;
            margin-top: 30px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .login-container {
                grid-template-columns: 1fr;
                margin: 20px;
            }
            
            .login-info-section {
                display: none;
            }
            
            .login-form-section {
                padding: 40px 30px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-form-section">
            <div class="form-header">
                <h2>🔐 Login</h2>
                <p>Enter your credentials to access the system</p>
            </div>
            
            <?php if ($success_message): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                           placeholder="Enter your username">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter your password">
                </div>
                
                <button type="submit" class="btn-login">Sign In</button>
            </form>
            
            <div class="demo-accounts">
                <h4>🧪 Demo Accounts</h4>
                <div class="demo-account">
                    <strong>Admin:</strong> admin / admin123<br>
                    <em>Full system access</em>
                </div>
                <div class="demo-account">
                    <strong>Professor:</strong> prof.smith / prof123<br>
                    <em>Course & attendance management</em>
                </div>
                <div class="demo-account">
                    <strong>Student:</strong> student.alice / student123<br>
                    <em>View attendance & submit justifications</em>
                </div>
            </div>
            
            <div class="back-link">
                <a href="../index.html">← Back to Home</a>
            </div>
        </div>
        
        <div class="login-info-section">
            <div class="logo">
                <h1>🎓</h1>
                <p>Algiers University<br>Attendance System</p>
            </div>
            
            <div class="welcome-text">
                <h2>Welcome Back!</h2>
                <p>Access your personalized dashboard and manage attendance efficiently.</p>
                
                <ul class="features">
                    <li>Real-time attendance tracking</li>
                    <li>Comprehensive reporting</li>
                    <li>Mobile-friendly interface</li>
                    <li>Automated notifications</li>
                    <li>Secure data management</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
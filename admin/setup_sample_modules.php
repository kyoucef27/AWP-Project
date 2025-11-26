<?php
// Setup script to populate database with sample modules
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/unauthorized.php");
    exit();
}

require_once '../includes/config.php';

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'generate_modules') {
        try {
            $pdo = getDBConnection();
            $pdo->beginTransaction();
            
            // Sample modules for different specialties and year levels
            $sample_modules = [
                // Year 1 - Common to all specialties
                ['CS101', 'Introduction to Programming', 'Fundamentals of programming using Python', 4, 'Computer Science', 'All', 1, 'Fall'],
                ['CS102', 'Data Structures', 'Introduction to data structures and algorithms', 4, 'Computer Science', 'All', 1, 'Spring'],
                ['MATH101', 'Calculus I', 'Differential and integral calculus', 4, 'Mathematics', 'All', 1, 'Fall'],
                ['MATH102', 'Linear Algebra', 'Vectors, matrices, and linear transformations', 3, 'Mathematics', 'All', 1, 'Spring'],
                ['PHYS101', 'Physics I', 'Mechanics and thermodynamics', 3, 'Physics', 'All', 1, 'Fall'],
                ['ENG101', 'Technical Writing', 'Professional and technical communication', 2, 'Computer Science', 'All', 1, 'Both'],
                
                // Year 2 - Common courses
                ['CS201', 'Object-Oriented Programming', 'OOP principles using Java', 4, 'Computer Science', 'All', 2, 'Fall'],
                ['CS202', 'Database Systems', 'Relational databases and SQL', 4, 'Computer Science', 'All', 2, 'Spring'],
                ['CS203', 'Computer Architecture', 'Digital logic and computer organization', 3, 'Computer Science', 'All', 2, 'Fall'],
                ['CS204', 'Operating Systems', 'Process management, memory, and file systems', 4, 'Computer Science', 'All', 2, 'Spring'],
                ['MATH201', 'Discrete Mathematics', 'Logic, sets, and graph theory', 3, 'Mathematics', 'All', 2, 'Fall'],
                ['CS205', 'Web Development', 'HTML, CSS, JavaScript, and web frameworks', 3, 'Computer Science', 'All', 2, 'Spring'],
                
                // Year 3 - Specialty-specific courses
                // Computer Science specialty
                ['CS301', 'Algorithms Analysis', 'Advanced algorithms and complexity theory', 4, 'Computer Science', 'Computer Science', 3, 'Fall'],
                ['CS302', 'Artificial Intelligence', 'AI fundamentals and machine learning basics', 4, 'Computer Science', 'Computer Science', 3, 'Spring'],
                ['CS303', 'Computer Networks', 'Network protocols and architecture', 3, 'Computer Science', 'Computer Science', 3, 'Fall'],
                ['CS304', 'Computer Graphics', 'Graphics programming and rendering', 3, 'Computer Science', 'Computer Science', 3, 'Spring'],
                ['CS305', 'Compiler Design', 'Language processing and compiler construction', 4, 'Computer Science', 'Computer Science', 3, 'Fall'],
                
                // Software Engineering specialty
                ['SE301', 'Software Engineering Principles', 'SDLC, methodologies, and best practices', 4, 'Software Engineering', 'Software Engineering', 3, 'Fall'],
                ['SE302', 'Software Testing and QA', 'Testing strategies and quality assurance', 3, 'Software Engineering', 'Software Engineering', 3, 'Spring'],
                ['SE303', 'Software Architecture', 'Design patterns and architectural styles', 4, 'Software Engineering', 'Software Engineering', 3, 'Fall'],
                ['SE304', 'Agile Development', 'Scrum, Kanban, and agile methodologies', 3, 'Software Engineering', 'Software Engineering', 3, 'Spring'],
                ['SE305', 'DevOps and CI/CD', 'Continuous integration and deployment', 3, 'Software Engineering', 'Software Engineering', 3, 'Spring'],
                
                // Information Systems specialty
                ['IS301', 'Information Systems Analysis', 'Requirements analysis and system design', 4, 'Information Systems', 'Information Systems', 3, 'Fall'],
                ['IS302', 'Enterprise Systems', 'ERP and business process management', 3, 'Information Systems', 'Information Systems', 3, 'Spring'],
                ['IS303', 'IT Project Management', 'Project planning and management', 3, 'Information Systems', 'Information Systems', 3, 'Fall'],
                ['IS304', 'Business Intelligence', 'Data warehousing and analytics', 4, 'Information Systems', 'Information Systems', 3, 'Spring'],
                ['IS305', 'IT Security and Governance', 'Security policies and compliance', 3, 'Information Systems', 'Information Systems', 3, 'Fall'],
                
                // Data Science specialty (if added)
                ['DS301', 'Machine Learning', 'Supervised and unsupervised learning', 4, 'Computer Science', 'Data Science', 3, 'Fall'],
                ['DS302', 'Data Mining', 'Data extraction and pattern recognition', 4, 'Computer Science', 'Data Science', 3, 'Spring'],
                ['DS303', 'Big Data Technologies', 'Hadoop, Spark, and distributed computing', 3, 'Computer Science', 'Data Science', 3, 'Fall'],
                ['DS304', 'Statistical Analysis', 'Statistical methods for data analysis', 3, 'Mathematics', 'Data Science', 3, 'Spring'],
                ['DS305', 'Data Visualization', 'Visual analytics and dashboards', 3, 'Computer Science', 'Data Science', 3, 'Spring'],
                
                // Year 4 - Advanced specialty courses
                // Computer Science
                ['CS401', 'Advanced Algorithms', 'Approximation and randomized algorithms', 4, 'Computer Science', 'Computer Science', 4, 'Fall'],
                ['CS402', 'Deep Learning', 'Neural networks and deep learning', 4, 'Computer Science', 'Computer Science', 4, 'Spring'],
                ['CS403', 'Distributed Systems', 'Distributed computing and cloud platforms', 4, 'Computer Science', 'Computer Science', 4, 'Fall'],
                ['CS404', 'Cybersecurity', 'Security threats and countermeasures', 3, 'Computer Science', 'Computer Science', 4, 'Spring'],
                
                // Software Engineering
                ['SE401', 'Advanced Software Engineering', 'Software evolution and maintenance', 4, 'Software Engineering', 'Software Engineering', 4, 'Fall'],
                ['SE402', 'Mobile Application Development', 'iOS and Android development', 4, 'Software Engineering', 'Software Engineering', 4, 'Spring'],
                ['SE403', 'Cloud Computing', 'Cloud architecture and services', 3, 'Software Engineering', 'Software Engineering', 4, 'Fall'],
                ['SE404', 'Software Project Capstone', 'Real-world software project', 5, 'Software Engineering', 'Software Engineering', 4, 'Both'],
                
                // Information Systems
                ['IS401', 'Advanced Database Systems', 'NoSQL, distributed databases', 4, 'Information Systems', 'Information Systems', 4, 'Fall'],
                ['IS402', 'E-Commerce Systems', 'Online business platforms and digital marketing', 3, 'Information Systems', 'Information Systems', 4, 'Spring'],
                ['IS403', 'IT Strategy and Leadership', 'Strategic IT planning', 3, 'Information Systems', 'Information Systems', 4, 'Fall'],
                ['IS404', 'Information Systems Capstone', 'End-to-end IS project', 5, 'Information Systems', 'Information Systems', 4, 'Both'],
                
                // Year 5 - Electives and specialization (if applicable)
                ['CS501', 'Research Methods', 'Research methodology and thesis preparation', 3, 'Computer Science', 'All', 5, 'Fall'],
                ['CS502', 'Professional Ethics', 'Ethics in technology and computing', 2, 'Computer Science', 'All', 5, 'Fall'],
                ['CS503', 'Internship', 'Industry internship program', 6, 'Computer Science', 'All', 5, 'Summer'],
                ['CS504', 'Senior Project', 'Final year capstone project', 6, 'Computer Science', 'All', 5, 'Spring'],
            ];
            
            $inserted_count = 0;
            $skipped_count = 0;
            
            foreach ($sample_modules as $module) {
                // Check if module already exists
                $stmt = $pdo->prepare("SELECT id FROM modules WHERE module_code = ?");
                $stmt->execute([$module[0]]);
                
                if ($stmt->fetch()) {
                    $skipped_count++;
                    continue;
                }
                
                // Insert module
                $stmt = $pdo->prepare("
                    INSERT INTO modules 
                    (module_code, module_name, description, credits, department, specialty, year_level, semester, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $module[0], // module_code
                    $module[1], // module_name
                    $module[2], // description
                    $module[3], // credits
                    $module[4], // department
                    $module[5], // specialty
                    $module[6], // year_level
                    $module[7]  // semester
                ]);
                
                $module_id = $pdo->lastInsertId();
                $year_level = $module[6];
                $specialty = $module[5];
                
                // Auto-assign module to matching groups
                $stmt = $pdo->prepare("
                    SELECT id, specialization FROM student_groups 
                    WHERE year_level = ?
                ");
                $stmt->execute([$year_level]);
                $matching_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($matching_groups as $group) {
                    // Check if specialty matches (if module is 'All', assign to all groups)
                    if ($specialty === 'All' || 
                        strpos($group['specialization'], $specialty) !== false || 
                        $specialty === $group['specialization']) {
                        
                        $stmt = $pdo->prepare("
                            INSERT IGNORE INTO module_group_assignments (module_id, group_id) 
                            VALUES (?, ?)
                        ");
                        $stmt->execute([$module_id, $group['id']]);
                    }
                }
                
                $inserted_count++;
            }
            
            $pdo->commit();
            $message = "Successfully created {$inserted_count} modules! Skipped {$skipped_count} existing modules.";
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error creating sample modules: ' . $e->getMessage();
        }
    }
}

// Get current module count
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT COUNT(*) FROM modules");
    $module_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    $module_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Sample Modules - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 700px;
            width: 100%;
            padding: 3rem;
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .header i {
            font-size: 4rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        
        h1 {
            color: #2c3e50;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .subtitle {
            color: #6c757d;
            font-size: 1rem;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 1.5rem;
            border-radius: 4px;
            margin-bottom: 2rem;
        }
        
        .info-box h3 {
            color: #0c5460;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        
        .info-box p {
            margin: 0.5rem 0;
            color: #0c5460;
            line-height: 1.6;
        }
        
        .info-box ul {
            margin: 1rem 0 0 1.5rem;
            color: #0c5460;
        }
        
        .info-box li {
            margin: 0.5rem 0;
        }
        
        .stat {
            text-align: center;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 1rem;
            margin-top: 0.5rem;
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn {
            flex: 1;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
            min-width: 200px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <i class="fas fa-database"></i>
            <h1>Setup Sample Modules</h1>
            <p class="subtitle">Populate database with comprehensive module catalog</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="stat">
            <div class="stat-number"><?php echo $module_count; ?></div>
            <div class="stat-label">Modules Currently in Database</div>
        </div>

        <div class="info-box">
            <h3><i class="fas fa-info-circle"></i> What This Tool Does</h3>
            <p><strong>This tool will generate 52 comprehensive sample modules covering:</strong></p>
            <ul>
                <li><strong>Year 1-2:</strong> Common core courses (All specialties)</li>
                <li><strong>Year 3-4:</strong> Specialty-specific courses:
                    <ul>
                        <li>Computer Science (Algorithms, AI, Networks, Graphics)</li>
                        <li>Software Engineering (Testing, Architecture, DevOps)</li>
                        <li>Information Systems (ERP, Project Management, BI)</li>
                        <li>Data Science (ML, Data Mining, Big Data)</li>
                    </ul>
                </li>
                <li><strong>Year 5:</strong> Research, ethics, internship, and capstone</li>
            </ul>
            <p><strong>Features:</strong></p>
            <ul>
                <li>Modules are automatically assigned to matching student groups</li>
                <li>Specialty-based filtering ensures students only see relevant courses</li>
                <li>Existing modules will be skipped (no duplicates)</li>
            </ul>
        </div>

        <div class="btn-group">
            <form method="POST" style="flex: 1; display: flex;" onsubmit="return confirm('Generate all sample modules?');">
                <input type="hidden" name="action" value="generate_modules">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-magic"></i>
                    Generate Sample Modules
                </button>
            </form>
        </div>

        <div class="btn-group" style="margin-top: 1rem;">
            <a href="module_management/module_management.php" class="btn btn-success">
                <i class="fas fa-book"></i>
                Manage Modules
            </a>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-home"></i>
                Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>

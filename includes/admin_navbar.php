<?php
// Admin Navigation Bar Component with Dropdowns
// Include this file in all admin pages for consistent navigation

$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Detect if we're in a subfolder and adjust paths accordingly
$path_prefix = '';
if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
    // Check if we're in a subfolder within admin
    $parts = explode('/admin/', $_SERVER['PHP_SELF']);
    if (isset($parts[1]) && strpos($parts[1], '/') !== false) {
        $path_prefix = '../';
    }
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<nav class="admin-navbar">
    <div class="navbar-container">
        <div class="navbar-left">
            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            <div class="navbar-brand">
                <a href="<?php echo $path_prefix; ?>dashboard.php">
                    <i class="fas fa-graduation-cap"></i>
                    <span>PAW Admin</span>
                </a>
            </div>
        </div>
        
        <div class="navbar-center">
            <div class="navbar-menu" id="navbarMenu">
                <a href="<?php echo $path_prefix; ?>dashboard.php" class="nav-link <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                
                <div class="nav-dropdown">
                    <button class="nav-link dropdown-toggle <?php echo in_array($current_page, ['teacher_management', 'student_management', 'group_management']) ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="<?php echo $path_prefix; ?>teacher_management/teacher_management.php" class="dropdown-link <?php echo $current_page == 'teacher_management' ? 'active' : ''; ?>">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>Teachers</span>
                        </a>
                        <a href="<?php echo $path_prefix; ?>student_management/student_management.php" class="dropdown-link <?php echo $current_page == 'student_management' ? 'active' : ''; ?>">
                            <i class="fas fa-user-graduate"></i>
                            <span>Students</span>
                        </a>
                        <a href="<?php echo $path_prefix; ?>group_management/group_management.php" class="dropdown-link <?php echo $current_page == 'group_management' ? 'active' : ''; ?>">
                            <i class="fas fa-users-cog"></i>
                            <span>Student Groups</span>
                        </a>
                    </div>
                </div>
                
                <div class="nav-dropdown">
                    <button class="nav-link dropdown-toggle <?php echo in_array($current_page, ['module_management']) ? 'active' : ''; ?>">
                        <i class="fas fa-book"></i>
                        <span>Academic</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="<?php echo $path_prefix; ?>module_management/module_management.php" class="dropdown-link <?php echo $current_page == 'module_management' ? 'active' : ''; ?>">
                            <i class="fas fa-book-open"></i>
                            <span>Modules</span>
                        </a>
                        <a href="<?php echo $path_prefix; ?>course_scheduling.php" class="dropdown-link <?php echo $current_page == 'course_scheduling' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Scheduling</span>
                        </a>
                    </div>
                </div>
                
                <div class="nav-dropdown">
                    <button class="nav-link dropdown-toggle <?php echo in_array($current_page, ['statistics', 'reports']) ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="<?php echo $path_prefix; ?>statistics/statistics.php" class="dropdown-link <?php echo $current_page == 'statistics' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line"></i>
                            <span>Statistics</span>
                        </a>
                        <a href="<?php echo $path_prefix; ?>attendance_reports.php" class="dropdown-link <?php echo $current_page == 'attendance_reports' ? 'active' : ''; ?>">
                            <i class="fas fa-clipboard-list"></i>
                            <span>Attendance</span>
                        </a>
                    </div>
                </div>
                
                <div class="nav-dropdown">
                    <button class="nav-link dropdown-toggle <?php echo in_array($current_page, ['cleanup_test_data', 'system_diagnostics', 'bulk_enrollment', 'add_specialty_columns']) ? 'active' : ''; ?>">
                        <i class="fas fa-tools"></i>
                        <span>Tools</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="<?php echo $path_prefix; ?>utilities/add_specialty_columns.php" class="dropdown-link <?php echo $current_page == 'add_specialty_columns' ? 'active' : ''; ?>">
                            <i class="fas fa-database"></i>
                            <span>Add Specialty Columns</span>
                        </a>
                        <a href="<?php echo $path_prefix; ?>utilities/database_diagnostic.php" class="dropdown-link <?php echo $current_page == 'database_diagnostic' ? 'active' : ''; ?>">
                            <i class="fas fa-stethoscope"></i>
                            <span>Database Diagnostic</span>
                        </a>
                        <a href="<?php echo $path_prefix; ?>utilities/create_absence_justifications_table.php" class="dropdown-link <?php echo $current_page == 'create_absence_justifications_table' ? 'active' : ''; ?>">
                            <i class="fas fa-table"></i>
                            <span>Create Justifications Table</span>
                        </a>
                        <a href="<?php echo $path_prefix; ?>utilities/bulk_enrollment.php" class="dropdown-link <?php echo $current_page == 'bulk_enrollment' ? 'active' : ''; ?>">
                            <i class="fas fa-user-plus"></i>
                            <span>Bulk Enrollment</span>
                        </a>
                        <a href="<?php echo $path_prefix; ?>generate_sample_attendance.php" class="dropdown-link <?php echo $current_page == 'generate_sample_attendance' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-check"></i>
                            <span>Generate Sample Attendance</span>
                        </a>
                        <a href="<?php echo $path_prefix; ?>setup_sample_modules.php" class="dropdown-link <?php echo $current_page == 'setup_sample_modules' ? 'active' : ''; ?>">
                            <i class="fas fa-database"></i>
                            <span>Generate Sample Modules</span>
                        </a>
                        <a href="<?php echo $path_prefix; ?>utilities/clear_modules.php" class="dropdown-link <?php echo $current_page == 'clear_modules' ? 'active' : ''; ?>">
                            <i class="fas fa-trash-alt"></i>
                            <span>Clear All Modules</span>
                        </a>
                        <a href="<?php echo $path_prefix; ?>cleanup_test_data.php" class="dropdown-link <?php echo $current_page == 'cleanup_test_data' ? 'active' : ''; ?>">
                            <i class="fas fa-broom"></i>
                            <span>Data Management</span>
                        </a>
                        <a href="<?php echo $path_prefix; ?>utilities/system_diagnostics.php" class="dropdown-link <?php echo $current_page == 'system_diagnostics' ? 'active' : ''; ?>">
                            <i class="fas fa-stethoscope"></i>
                            <span>Diagnostics</span>
                        </a>
                        <a href="<?php echo $path_prefix; ?>backup_restore.php" class="dropdown-link <?php echo $current_page == 'backup_restore' ? 'active' : ''; ?>">
                            <i class="fas fa-database"></i>
                            <span>Backup & Restore</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="navbar-right">
            <div class="user-menu">
                <button class="user-toggle" id="userToggle">
                    <i class="fas fa-user-circle"></i>
                    <span class="user-name"><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'Admin')[0]); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="user-dropdown" id="userDropdown">
                    <div class="user-info">
                        <div class="user-full-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></div>
                        <div class="user-role"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Administrator'); ?></div>
                    </div>
                    <hr class="dropdown-divider">
                    <a href="<?php echo $path_prefix; ?>../auth/logout.php" class="dropdown-link logout-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<style>
/* Modern Admin Navbar */
.admin-navbar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.navbar-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1.5rem;
    height: 70px;
}

/* Left Section - Brand */
.navbar-left {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.navbar-brand a {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: white;
    text-decoration: none;
    font-size: 1.4rem;
    font-weight: 700;
    transition: transform 0.3s ease;
}

.navbar-brand a:hover {
    transform: scale(1.05);
}

.navbar-brand i {
    font-size: 1.8rem;
}

/* Center Section - Menu */
.navbar-center {
    flex: 1;
    display: flex;
    justify-content: center;
}

.navbar-menu {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: white;
    text-decoration: none;
    padding: 0.65rem 1rem;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 500;
    transition: all 0.3s ease;
    background: transparent;
    border: none;
    cursor: pointer;
    white-space: nowrap;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.15);
}

.nav-link.active {
    background: rgba(255, 255, 255, 0.25);
    font-weight: 600;
}

.nav-link i:first-child {
    font-size: 1.1rem;
}

/* Dropdown */
.nav-dropdown {
    position: relative;
}

.dropdown-toggle {
    position: relative;
}

.dropdown-icon {
    font-size: 0.75rem;
    margin-left: 0.25rem;
    transition: transform 0.3s ease;
}

.nav-dropdown:hover .dropdown-icon,
.nav-dropdown.mobile-open .dropdown-icon {
    transform: rotate(180deg);
}

.dropdown-menu {
    position: absolute;
    top: calc(100% + 0.5rem);
    left: 0;
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    min-width: 220px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    overflow: hidden;
}

.nav-dropdown:hover .dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1.25rem;
    color: #2c3e50;
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}

.dropdown-link:hover {
    background: #f8f9fa;
    color: #667eea;
    border-left-color: #667eea;
}

.dropdown-link.active {
    background: linear-gradient(90deg, #e3f2fd 0%, #f8f9fa 100%);
    color: #667eea;
    font-weight: 600;
    border-left-color: #667eea;
}

.dropdown-link i {
    font-size: 1rem;
    width: 20px;
    text-align: center;
}

/* Right Section - User Menu */
.navbar-right {
    flex-shrink: 0;
}

.user-menu {
    position: relative;
}

.user-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.2);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.user-toggle:hover {
    background: rgba(255, 255, 255, 0.2);
}

.user-toggle i:first-child {
    font-size: 1.5rem;
}

.user-toggle i:last-child {
    font-size: 0.75rem;
    transition: transform 0.3s ease;
}

.user-menu.active .user-toggle i:last-child {
    transform: rotate(180deg);
}

.user-name {
    font-weight: 600;
}

.user-dropdown {
    position: absolute;
    top: calc(100% + 0.75rem);
    right: 0;
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    min-width: 200px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    overflow: hidden;
}

.user-menu.active .user-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.user-info {
    padding: 1rem 1.25rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.user-full-name {
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
}

.user-role {
    color: #6c757d;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.dropdown-divider {
    margin: 0;
    border: none;
    border-top: 1px solid #e9ecef;
}

.logout-link {
    color: #dc3545 !important;
    font-weight: 600;
}

.logout-link:hover {
    background: #fff5f5 !important;
    border-left-color: #dc3545 !important;
}

/* Mobile Menu Toggle */
.mobile-menu-toggle {
    display: none;
    flex-direction: column;
    gap: 5px;
    background: white;
    border: none;
    padding: 0.65rem 0.75rem;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    margin-right: 0.5rem;
}

.mobile-menu-toggle span {
    display: block;
    width: 26px;
    height: 3px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 3px;
    transition: all 0.3s ease;
}

.mobile-menu-toggle:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.mobile-menu-toggle:active {
    transform: scale(0.95);
}

.mobile-menu-toggle.active span:nth-child(1) {
    transform: rotate(45deg) translateY(10px);
}

.mobile-menu-toggle.active span:nth-child(2) {
    opacity: 0;
}

.mobile-menu-toggle.active span:nth-child(3) {
    transform: rotate(-45deg) translateY(-10px);
}

/* Responsive Design */
@media (max-width: 1024px) {
    .mobile-menu-toggle {
        display: flex;
    }
    
    .navbar-center {
        position: absolute;
        top: 70px;
        left: 0;
        right: 0;
        justify-content: flex-start;
    }
    
    .navbar-menu {
        display: none;
        flex-direction: column;
        width: 100%;
        background: white;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        gap: 0;
        align-items: stretch;
        max-height: calc(100vh - 70px);
        overflow-y: auto;
    }
    
    .navbar-menu.active {
        display: flex;
    }
    
    .nav-link {
        color: #2c3e50;
        padding: 1rem 1.5rem;
        border-radius: 0;
        border-left: 3px solid transparent;
    }
    
    .nav-link:hover {
        background: #f8f9fa;
        color: #667eea;
        border-left-color: #667eea;
    }
    
    .nav-link.active {
        background: linear-gradient(90deg, #e3f2fd 0%, #f8f9fa 100%);
        color: #667eea;
        border-left-color: #667eea;
    }
    
    .nav-dropdown .dropdown-menu {
        position: static;
        opacity: 0;
        visibility: hidden;
        max-height: 0;
        transform: none;
        box-shadow: none;
        background: #f8f9fa;
        transition: max-height 0.3s ease, opacity 0.3s ease;
    }
    
    .nav-dropdown.mobile-open .dropdown-menu {
        opacity: 1;
        visibility: visible;
        max-height: 500px;
    }
    
    .dropdown-link {
        padding-left: 3rem;
    }
    
    .user-name {
        display: none;
    }
}

@media (max-width: 768px) {
    .navbar-container {
        padding: 0 1rem;
        height: 60px;
    }
    
    .navbar-brand a {
        font-size: 1.2rem;
    }
    
    .navbar-brand i {
        font-size: 1.5rem;
    }
    
    .navbar-center {
        top: 60px;
    }
}

@media (max-width: 480px) {
    .navbar-brand a span {
        display: none;
    }
    
    .user-toggle {
        padding: 0.5rem;
        min-width: auto;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const navbarMenu = document.getElementById('navbarMenu');
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    const userToggle = document.getElementById('userToggle');
    const userMenu = userToggle ? userToggle.closest('.user-menu') : null;
    
    // Toggle mobile menu
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('active');
            navbarMenu.classList.toggle('active');
            
            // Close user menu when opening mobile menu
            if (userMenu) {
                userMenu.classList.remove('active');
            }
        });
    }
    
    // Handle dropdown toggles
    dropdownToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const dropdown = this.closest('.nav-dropdown');
            
            // On mobile, toggle accordion style
            if (window.innerWidth <= 1024) {
                // Close other dropdowns
                document.querySelectorAll('.nav-dropdown').forEach(function(d) {
                    if (d !== dropdown) {
                        d.classList.remove('mobile-open');
                    }
                });
                
                dropdown.classList.toggle('mobile-open');
            }
        });
    });
    
    // User menu toggle
    if (userToggle) {
        userToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenu.classList.toggle('active');
            
            // Close mobile menu if open
            if (navbarMenu.classList.contains('active')) {
                navbarMenu.classList.remove('active');
                if (mobileMenuToggle) {
                    mobileMenuToggle.classList.remove('active');
                }
            }
        });
    }
    
    // Close menus when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.admin-navbar')) {
            // Close mobile menu
            if (navbarMenu && navbarMenu.classList.contains('active')) {
                navbarMenu.classList.remove('active');
                if (mobileMenuToggle) {
                    mobileMenuToggle.classList.remove('active');
                }
            }
            
            // Close user menu
            if (userMenu && userMenu.classList.contains('active')) {
                userMenu.classList.remove('active');
            }
            
            // Close mobile dropdowns
            document.querySelectorAll('.nav-dropdown').forEach(function(d) {
                d.classList.remove('mobile-open');
            });
        }
    });
    
    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 1024) {
                // Reset mobile menu
                if (navbarMenu) {
                    navbarMenu.classList.remove('active');
                }
                if (mobileMenuToggle) {
                    mobileMenuToggle.classList.remove('active');
                }
                
                // Reset mobile dropdowns
                document.querySelectorAll('.nav-dropdown').forEach(function(d) {
                    d.classList.remove('mobile-open');
                });
            }
        }, 250);
    });
    
    // Prevent dropdown menus from closing when clicking inside them
    document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
        menu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
});
</script>
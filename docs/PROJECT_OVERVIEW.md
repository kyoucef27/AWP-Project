# 🎓 Web-Based Student Attendance Management System
## Algiers University

### Project Overview
Complete web-based attendance management system with role-based access for students, professors, and administrators.

## 📋 Project Structure

```
pawproject/
├── config/
│   ├── config.php              # Database configuration
│   └── constants.php           # System constants
├── includes/
│   ├── db_connect.php          # Database connection
│   ├── auth.php                # Authentication functions
│   └── functions.php           # Common functions
├── assets/
│   ├── css/
│   │   ├── main.css           # Main styles
│   │   ├── professor.css      # Professor-specific styles
│   │   ├── student.css        # Student-specific styles
│   │   └── admin.css          # Admin-specific styles
│   ├── js/
│   │   ├── main.js            # Common JavaScript (jQuery)
│   │   ├── professor.js       # Professor functionality
│   │   ├── student.js         # Student functionality
│   │   └── admin.js           # Admin functionality
│   └── uploads/               # File uploads directory
├── professor/
│   ├── index.php              # Home page (sessions list)
│   ├── session.php            # Mark attendance page
│   ├── summary.php            # Attendance summary
│   └── course_management.php  # Course management
├── student/
│   ├── index.php              # Home page (enrolled courses)
│   ├── attendance.php         # View attendance per course
│   └── justification.php      # Submit justifications
├── admin/
│   ├── index.php              # Admin home page
│   ├── statistics.php         # Statistics and charts
│   ├── student_management.php # Student list management
│   └── import_export.php      # Progres Excel import/export
├── api/
│   ├── auth_api.php           # Authentication endpoints
│   ├── attendance_api.php     # Attendance operations
│   ├── student_api.php        # Student operations
│   └── statistics_api.php     # Statistics data
├── auth/
│   ├── login.php              # Login page
│   ├── logout.php             # Logout handler
│   └── register.php           # Registration (admin only)
├── database/
│   ├── schema.sql             # Complete database schema
│   └── sample_data.sql        # Sample data for testing
├── docs/
│   ├── database_design.md     # Database design documentation
│   ├── api_documentation.md   # API documentation
│   └── user_manual.md         # User manual
└── index.php                  # Landing/redirect page
```

## 🗄️ Database Tables

### Core Tables
- **users** - Students, professors, administrators
- **roles** - Role definitions
- **courses** - Course information
- **groups** - Student groups/classes
- **enrollments** - Student course enrollments
- **attendance_sessions** - Attendance sessions
- **attendance_records** - Individual attendance records
- **justifications** - Absence justifications
- **participation_records** - Student participation tracking
- **system_logs** - Activity logging

## 👥 User Roles & Access

### Professor Features
1. **Home Page**: List of sessions per course
2. **Session Page**: Mark attendance for students
3. **Summary Page**: Attendance summary (per group/course)

### Student Features
1. **Home Page**: List of enrolled courses
2. **Attendance Page**: View attendance status, submit justifications

### Administrator Features
1. **Home Page**: System overview
2. **Statistics Page**: Charts and analytics
3. **Student Management**: Import/export (Progres Excel), add/remove students

## 🚀 Implementation Progress

### ✅ Completed
- Database configuration and connection
- Basic CRUD operations for students
- Session management (create/close)
- Error handling and logging

### 🔄 Next Steps
1. Complete database schema with all tables
2. Implement authentication system
3. Create role-based access control
4. Build professor interface
5. Build student interface
6. Build admin interface
7. Implement import/export functionality
8. Add reporting and analytics

## 🛠️ Technologies
- **Frontend**: HTML5, CSS3, JavaScript, jQuery
- **Backend**: PHP 7.4+
- **Database**: MySQL/MariaDB
- **Server**: Apache (WAMP)

---

**Status**: Foundation Complete - Ready for Full Implementation
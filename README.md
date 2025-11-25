# 🎓 Algiers University Attendance System (AUAS)

A comprehensive web-based attendance management system designed for Algiers University, providing role-based access control for students, professors, and administrators.

## 📋 Table of Contents

- [Features](#features)
- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Directory Structure](#directory-structure)
- [Usage](#usage)
- [User Roles](#user-roles)
- [API Documentation](#api-documentation)
- [Security](#security)
- [Contributing](#contributing)
- [License](#license)

## ✨ Features

### 🔐 **Authentication & Security**
- Role-based access control (Admin, Professor, Student)
- Secure password hashing and session management
- Activity logging and audit trails
- CSRF protection and XSS prevention

### 👨‍🎓 **Student Features**
- View personal attendance records
- Submit absence justifications
- Track attendance percentages by course
- Access course schedules and upcoming sessions

### 👨‍🏫 **Professor Features**
- Create and manage attendance sessions
- Mark student attendance (Present/Absent/Late)
- Review and approve justifications
- Generate attendance reports and analytics

### 👨‍💼 **Administrator Features**
- Complete system oversight and management
- User account management (Students, Professors)
- Course and group management
- System statistics and analytics
- Progres Excel import/export functionality

### 📊 **Advanced Features**
- Real-time attendance tracking
- Responsive mobile-first design
- Automated email notifications
- Data export capabilities
- Comprehensive reporting system

## 💻 System Requirements

### Server Requirements
- **PHP**: 7.4+ (8.1+ recommended)
- **MySQL**: 5.7+ or MariaDB 10.3+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Memory**: Minimum 512MB RAM (1GB+ recommended)

### Development Environment
- **WAMP/XAMPP**: For local development
- **Modern Browser**: Chrome 90+, Firefox 88+, Safari 14+
- **Git**: For version control

## 🚀 Installation

### 1. Clone Repository
```bash
git clone https://github.com/kyoucef27/AWP-Project.git
cd AWP-Project
```

### 2. Server Setup
1. Start WAMP/XAMPP server
2. Ensure Apache and MySQL services are running
3. Place project in `htdocs/` or web root directory

### 3. Database Configuration
1. Open `includes/config.php`
2. Update database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'students');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 4. Database Initialization
1. Navigate to `http://localhost/pawproject/setup_database.php`
2. Follow the setup wizard to create tables and sample data
3. Verify successful installation

### 5. Test Installation
1. Visit `http://localhost/pawproject/`
2. Click "Test Database Connection" to verify setup
3. Login with demo credentials (see [User Roles](#user-roles))

## 📁 Directory Structure

```
pawproject/
├── admin/                  # Administrator interface
│   └── dashboard.php
├── auth/                   # Authentication system
│   ├── login.php
│   ├── logout.php
│   ├── session_check.php
│   └── unauthorized.php
├── docs/                   # Documentation
│   ├── PROJECT_OVERVIEW.md
│   ├── QUICK_START.md
│   └── EXERCISES_IMPLEMENTATION.md
├── includes/               # Core system files
│   ├── config.php
│   └── db_connect.php
├── legacy/                 # Legacy/unused files
│   ├── add_student.php
│   ├── script.js
│   └── styles.css
├── logs/                   # System logs
│   └── README.md
├── professor/              # Professor interface
│   └── dashboard.php
├── student/                # Student interface
│   └── dashboard.php
├── database_schema_complete.sql
├── index.html
├── setup_database.php
├── test_connection.php
└── README.md
```

## 🎯 Usage

### First Time Setup
1. **Setup Database**: Run `setup_database.php` to initialize the system
2. **Login**: Use demo credentials to explore different user roles
3. **Configure**: Customize system settings through admin panel

### Daily Operations

#### For Professors
1. Login to professor dashboard
2. Create attendance sessions for scheduled classes
3. Mark student attendance during sessions
4. Review and approve absence justifications
5. Generate attendance reports

#### For Students
1. Login to student dashboard
2. View attendance status for all courses
3. Submit justifications for absences
4. Monitor attendance percentages
5. Access upcoming session schedules

#### For Administrators
1. Access admin dashboard for system overview
2. Manage user accounts and permissions
3. Configure courses and groups
4. Monitor system activity and generate reports
5. Perform data backup and maintenance

## 👥 User Roles

### Demo Accounts (Password: see table below)

| Role | Username | Password | Access Level |
|------|----------|----------|--------------|
| **Admin** | `admin` | `admin123` | Full system control |
| **Professor** | `prof.smith` | `prof123` | Course management |
| **Student** | `student.alice` | `student123` | Attendance viewing |

### Role Permissions

#### 🔴 Administrator
- Complete system access and configuration
- User account management (create, modify, delete)
- Course and department management
- System-wide reports and analytics
- Database management and backups

#### 🟢 Professor
- Course and session management
- Student attendance marking
- Justification review and approval
- Course-specific reports
- Student progress tracking

#### 🔵 Student
- Personal attendance viewing
- Absence justification submission
- Course schedule access
- Progress monitoring
- Personal information updates

## 📡 API Documentation

### Authentication Endpoints
- `POST /auth/login.php` - User login
- `GET /auth/logout.php` - User logout

### Dashboard Endpoints
- `GET /admin/dashboard.php` - Admin dashboard
- `GET /professor/dashboard.php` - Professor dashboard  
- `GET /student/dashboard.php` - Student dashboard

### Utility Endpoints
- `GET /test_connection.php` - Database connection test
- `POST /setup_database.php` - Database initialization

## 🔒 Security

### Implemented Security Measures
- **Password Hashing**: bcrypt algorithm for secure password storage
- **Session Security**: Secure session management with regeneration
- **CSRF Protection**: Token-based form protection
- **SQL Injection Prevention**: Prepared statements throughout
- **XSS Protection**: Input sanitization and output encoding
- **Access Control**: Role-based permissions on all endpoints
- **Activity Logging**: Comprehensive audit trail

### Security Best Practices
1. Regular password updates for admin accounts
2. HTTPS implementation in production
3. Regular database backups
4. Log monitoring and review
5. Access control list maintenance

## 🤝 Contributing

### Development Guidelines
1. **Code Style**: Follow PSR-12 coding standards
2. **Documentation**: Comment all functions and complex logic
3. **Testing**: Test all functionality before committing
4. **Security**: Security review for all database operations

### Contribution Process
1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License. See `LICENSE` file for details.

## 📞 Support

### Technical Support
- **Email**: support@university-attendance.edu.dz
- **Documentation**: See `/docs/` directory
- **Issue Tracking**: GitHub Issues

### Development Team
- **Lead Developer**: Kefif Youcef
- **Course**: Programmation Avancée Web (PAW)
- **Institution**: Algiers University

---

## 📈 Version History

### v1.0.0 (Current)
- ✅ Complete authentication system
- ✅ Role-based dashboards
- ✅ Database schema and setup
- ✅ Security implementation
- ✅ Documentation and guides

### Planned Features (v1.1.0)
- 🔄 Real-time attendance marking
- 🔄 Email notification system
- 🔄 Advanced reporting tools
- 🔄 Mobile application support
- 🔄 Integration with university systems

---

*Built with ❤️ for Algiers University*
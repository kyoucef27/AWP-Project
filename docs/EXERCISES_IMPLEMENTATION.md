# 🎓 Student Attendance System - MySQL Implementation

Complete PHP-based attendance management system with MySQL database integration for WAMP server.

## 📋 Table of Contents
- [Requirements](#requirements)
- [Installation](#installation)
- [Database Setup](#database-setup)
- [Testing](#testing)
- [API Documentation](#api-documentation)
- [Exercise Completion](#exercise-completion)

---

## 🔧 Requirements

- **WAMP Server** (Windows, Apache, MySQL, PHP)
- **PHP 7.4+**
- **MySQL 5.7+**
- Web browser for testing

---

## 🚀 Installation

### Step 1: Copy Project to WAMP Directory

```bash
# Copy the project folder to:
C:\wamp64\www\pawproject\n```
```

### Step 2: Start WAMP Server

1. Launch WAMP from the Start Menu
2. Wait for the icon to turn **green** (services running)
3. Ensure Apache and MySQL services are started

### Step 3: Configure Database

Edit `config.php` if needed (default settings work for standard WAMP):

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'attendance_system');
define('DB_USER', 'root');
define('DB_PASS', '');  // Empty by default on WAMP
```

---

## 🗄️ Database Setup

### Method 1: Using phpMyAdmin (Recommended)

1. Open browser and go to: `http://localhost/phpmyadmin`
2. Click **"New"** to create a database
3. Name it: `attendance_system`
4. Click **"Import"** tab
5. Choose file: `schema.sql`
6. Click **"Go"**

### Method 2: Using MySQL Command Line

```bash
# Open WAMP MySQL Console or CMD
mysql -u root -p

# Run the schema file
source C:/wamp64/www/pawproject/schema.sql;
```

### Method 3: Direct SQL Execution

Copy and paste the entire `schema.sql` content into phpMyAdmin's SQL tab and execute.

---

## ✅ Testing

### 1️⃣ Test Database Connection

Open in browser:
```
http://localhost/pawproject/test_connection.php
```

**Expected Output:**
- ✓ Green success message
- Database name: `attendance_system`
- MySQL version information

---

## 📚 API Documentation

### Exercise 3: Database Connection ✅

#### Files Created:
- ✅ `config.php` - Database configuration (host, username, password, database name)
- ✅ `db_connect.php` - Connection handler with try/catch, error handling, and logging
- ✅ `test_connection.php` - Test script showing connection status

#### Testing Connection:
```bash
# Via browser
http://localhost/pawproject/test_connection.php

# Via PHP CLI
php test_connection.php
```

---

### Exercise 4: Student CRUD Operations ✅

#### 1. Add Student (`add_student.php`)

**Endpoint:** `POST /add_student.php`

**Parameters:**
```
studentId (matricule) - Student ID number
firstName - First name
lastName - Last name
email - Email address
groupId - Group ID (optional)
```

**Test with cURL:**
```bash
curl -X POST http://localhost/pawproject/add_student.php \
  -d "studentId=20210006" \
  -d "firstName=Ahmed" \
  -d "lastName=Cherif" \
  -d "email=ahmed.cherif@student.dz" \
  -d "groupId=1"
```

**Test with Browser (HTML Form):**
Create a simple HTML form or use the existing `index.html` and modify the action URL.

---

#### 2. List Students (`list_students.php`)

**Endpoint:** `GET /list_students.php`

**Test with Browser:**
```
http://localhost/pawproject/list_students.php
```

**Test with cURL:**
```bash
curl http://localhost/pawproject/list_students.php
```

**Response Example:**
```json
{
    "success": true,
    "message": "Students retrieved successfully",
    "count": 5,
    "data": [
        {
            "id": 1,
            "matricule": "20210001",
            "fullname": "Kefif Youcef",
            "email": "kefifyoucef2020@gmail.com",
            "group_id": 1,
            "group_name": "L3 INFO A",
            "level": "Licence 3"
        }
    ]
}
```

---

#### 3. Update Student (`update_student.php`)

**Endpoint:** `POST /update_student.php`

**Parameters:**
```
id - Student database ID (required)
matricule - Student ID number
fullname - Full name
email - Email address
groupId - Group ID (optional)
```

**Test with cURL:**
```bash
curl -X POST http://localhost/pawproject/update_student.php \
  -d "id=1" \
  -d "matricule=20210001" \
  -d "fullname=Kefif Youcef Ahmed" \
  -d "email=kefifyoucef2020@gmail.com" \
  -d "groupId=1"
```

---

#### 4. Delete Student (`delete_student.php`)

**Endpoint:** `POST /delete_student.php`

**Parameters:**
```
id - Student database ID (required)
```

**Test with cURL:**
```bash
curl -X POST http://localhost/pawproject/delete_student.php \
  -d "id=5"
```

**Test with Browser:**
```
http://localhost/pawproject/delete_student.php?id=5
```

---

### Exercise 5: Attendance Sessions ✅

#### Table Structure:
```sql
attendance_sessions (
    id, 
    course_id, 
    group_id, 
    date, 
    start_time,
    end_time,
    opened_by (professor_id), 
    status,
    created_at,
    closed_at
)
```

---

#### 1. Create Session (`create_session.php`)

**Endpoint:** `POST /create_session.php`

**Parameters:**
```
courseId - Course ID (required)
groupId - Group ID (required)
professorId - Professor ID (required)
date - Session date (YYYY-MM-DD, optional - defaults to today)
startTime - Start time (HH:MM:SS, optional - defaults to now)
```

**Test with cURL:**
```bash
# Example 1: Create session with defaults
curl -X POST http://localhost/pawproject/create_session.php \
  -d "courseId=1" \
  -d "groupId=1" \
  -d "professorId=1"

# Example 2: Create session with specific date/time
curl -X POST http://localhost/pawproject/create_session.php \
  -d "courseId=2" \
  -d "groupId=2" \
  -d "professorId=2" \
  -d "date=2025-11-24" \
  -d "startTime=14:00:00"
```

**Response Example:**
```json
{
    "success": true,
    "message": "Attendance session created successfully!",
    "data": {
        "sessionId": 4,
        "courseId": 1,
        "courseName": "Programmation Avancée Web",
        "groupId": 1,
        "groupName": "L3 INFO A",
        "professorId": 1,
        "professorName": "Dr. Ahmed Bennani",
        "date": "2025-11-23",
        "startTime": "10:30:00",
        "status": "open"
    }
}
```

---

#### 2. Close Session (`close_session.php`)

**Endpoint:** `POST /close_session.php`

**Parameters:**
```
sessionId - Attendance session ID (required)
```

**Test with cURL:**
```bash
curl -X POST http://localhost/pawproject/close_session.php \
  -d "sessionId=4"
```

**Test with Browser:**
```
http://localhost/pawproject/close_session.php?sessionId=4
```

**Response Example:**
```json
{
    "success": true,
    "message": "Attendance session closed successfully!",
    "data": {
        "sessionId": 4,
        "courseName": "Programmation Avancée Web",
        "groupName": "L3 INFO A",
        "professorName": "Dr. Ahmed Bennani",
        "date": "2025-11-23",
        "startTime": "10:30:00",
        "endTime": "12:15:00",
        "status": "closed"
    }
}
```

---

## 🧪 Testing Sessions Manually

### Insert Test Sessions via phpMyAdmin:

```sql
-- Session 1: Open session for PAW course
INSERT INTO attendance_sessions (course_id, group_id, date, start_time, opened_by, status) 
VALUES (1, 1, '2025-11-23', '08:00:00', 1, 'open');

-- Session 2: Closed session for Database course
INSERT INTO attendance_sessions (course_id, group_id, date, start_time, end_time, opened_by, status, closed_at) 
VALUES (2, 2, '2025-11-22', '10:00:00', '12:00:00', 2, 'closed', '2025-11-22 12:00:00');

-- Session 3: Open session for SE course
INSERT INTO attendance_sessions (course_id, group_id, date, start_time, opened_by, status) 
VALUES (3, 1, '2025-11-23', '14:00:00', 3, 'open');
```

### View All Sessions:

```sql
SELECT 
    s.id,
    c.course_name,
    g.group_name,
    p.professor_name,
    s.date,
    s.start_time,
    s.end_time,
    s.status
FROM attendance_sessions s
JOIN courses c ON s.course_id = c.id
JOIN groups g ON s.group_id = g.id
JOIN professors p ON s.opened_by = p.id
ORDER BY s.date DESC, s.start_time DESC;
```

---

## 📊 Database Tables Overview

### Tables Created:
1. **students** - Student information (id, matricule, fullname, email, group_id)
2. **groups** - Student groups/classes
3. **courses** - Course information
4. **professors** - Professor information
5. **attendance_sessions** - Attendance sessions (with status: open/closed)
6. **attendance_records** - Individual attendance records

---

## 📝 Logs

### View Database Logs:
```bash
# Windows PowerShell
Get-Content logs/database.log -Tail 20

# CMD
type logs\database.log
```

### View Activity Logs:
```bash
Get-Content logs/activity.log -Tail 20
```

---

## 🎯 Exercise Completion Checklist

### Exercise 3 ✅
- [x] Created `config.php` with database credentials
- [x] Created `db_connect.php` with try/catch and error handling
- [x] Implemented logging to `logs/database.log`
- [x] Created `test_connection.php` for testing

### Exercise 4 ✅
- [x] Created `students` table with (id, fullname, matricule, group_id)
- [x] Implemented `add_student.php` (INSERT)
- [x] Implemented `list_students.php` (SELECT)
- [x] Implemented `update_student.php` (UPDATE)
- [x] Implemented `delete_student.php` (DELETE)

### Exercise 5 ✅
- [x] Created `attendance_sessions` table (id, course_id, group_id, date, opened_by, status)
- [x] Implemented `create_session.php` (receives course + group + professor)
- [x] Returns session ID on creation
- [x] Implemented `close_session.php` (updates status to "closed")
- [x] Added sample data with 2-3 test sessions

---

## 🔒 Security Features

- ✅ Prepared statements (SQL injection prevention)
- ✅ Input validation and sanitization
- ✅ Error logging without exposing sensitive data
- ✅ CORS protection
- ✅ Password-less connections for local development

---

## 🐛 Troubleshooting

### WAMP Icon Not Green
- Restart WAMP services
- Check port 80 and 3306 are not in use
- Disable Skype or other applications using port 80

### Database Connection Failed
- Verify MySQL service is running in WAMP
- Check credentials in `config.php`
- Ensure database `attendance_system` exists

### 404 Not Found
- Verify project is in `C:\wamp64\www\pawproject\`
- Access via `http://localhost/pawproject/` not `http://localhost:8000/`

### Permission Errors
- Ensure WAMP has write permissions to `logs/` directory
- Run WAMP as Administrator if needed

---

## 📧 Contact

**Developer:** Kefif Youcef  
**Email:** kefifyoucef2020@gmail.com  
**Course:** Programmation Avancée Web (PAW)

---

## 📚 Additional Resources

- [PHP PDO Documentation](https://www.php.net/manual/en/book.pdo.php)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [WAMP Server Guide](https://www.wampserver.com/)

---

**🎉 All exercises completed successfully! Ready for testing and demonstration.**

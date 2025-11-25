# 🎯 QUICK START GUIDE

## Setup Steps (5 minutes)

### 1. Start WAMP
- Launch WAMP Server
- Wait for green icon

### 2. Create Database
Open browser: `http://localhost/phpmyadmin`
- Click "New"
- Database name: `attendance_system`
- Click "Create"

### 3. Import Schema
- Select `attendance_system` database
- Click "Import" tab
- Choose file: `schema.sql`
- Click "Go"

### 4. Test Connection
Open: `http://localhost/pawproject/`

Expected: ✓ Green success message

---

## Testing the APIs

### Open API Tester
```
http://localhost/pawproject/api_tester.html
```

### Or Test Individually:

**List Students:**
```
http://localhost/pawproject/list_students.php
```

**Test Connection:**
```
http://localhost/pawproject/test_connection.php
```

---

## Command Line Tests

### Test with cURL:

**Add Student:**
```bash
curl -X POST http://localhost/pawproject/add_student.php -d "studentId=20210099" -d "firstName=Test" -d "lastName=User" -d "email=test@student.dz"
```

**List Students:**
```bash
curl http://localhost/pawproject/list_students.php
```

**Create Session:**
```bash
curl -X POST http://localhost/pawproject/create_session.php -d "courseId=1" -d "groupId=1" -d "professorId=1"
```

**Close Session:**
```bash
curl -X POST http://localhost/pawproject/close_session.php -d "sessionId=1"
```

---

## Files Created

### Exercise 3 ✅
- `config.php` - Database configuration
- `db_connect.php` - Connection handler with try/catch and logging
- `test_connection.php` - Connection test script

### Exercise 4 ✅
- `add_student.php` - Add new student
- `list_students.php` - List all students
- `update_student.php` - Update student info
- `delete_student.php` - Delete student

### Exercise 5 ✅
- `create_session.php` - Create attendance session
- `close_session.php` - Close attendance session
- `schema.sql` - Complete database schema with sample data

### Bonus Files 🎁
- `api_tester.html` - Visual API testing interface
- `EXERCISES_IMPLEMENTATION.md` - Complete documentation

---

## Verification Checklist

- [ ] WAMP icon is green
- [ ] Database `attendance_system` exists
- [ ] Schema imported successfully
- [ ] `test_connection.php` shows success
- [ ] Sample students loaded (5 students)
- [ ] Sample sessions created (3 sessions)
- [ ] Can list students via browser
- [ ] Can create new session
- [ ] Can close session

---

## Sample Data Included

**Students:** 5 sample students
**Groups:** 3 groups (L3 INFO A, L3 INFO B, M1 INFO)
**Courses:** 3 courses (PAW, Database, OS)
**Professors:** 3 professors
**Sessions:** 3 sample attendance sessions

---

## Need Help?

**Database not found?**
- Run `schema.sql` in phpMyAdmin

**Connection failed?**
- Check WAMP is running (green icon)
- Verify MySQL service is started
- Check `config.php` credentials

**404 Error?**
- Project must be in: `C:\wamp64\www\pawproject\`
- Access via: `http://localhost/pawproject/`

---

## Next Steps

1. Open `api_tester.html` in browser
2. Click "Test Connection"
3. Try "List All Students"
4. Create a test session
5. View logs in `logs/database.log`

---

**✅ All exercises completed and ready for demonstration!**

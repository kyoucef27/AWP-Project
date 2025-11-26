# Project Cleanup and Organization Plan

## Files to Delete (Debug/Test/Unused Files)

### Admin Debug Files (No longer needed)
- `admin/cleanup_test_data.php` - Temporary cleanup script
- `admin/debug_teachers_db.php` - Debug script for teacher database
- `admin/debug_teacher_import.php` - Debug script for teacher import
- `admin/delete_all_teachers.php` - Dangerous utility, not needed
- `admin/fix_teacher_records.php` - One-time fix script
- `admin/fix_teacher_role.php` - One-time fix script

### Test Data Files
- `admin/sample_students.csv` - Move to docs/samples/
- `admin/sample_teachers.csv` - Move to docs/samples/

### Setup/Debug Files (Keep but organize)
- `setup_debug.php` - Keep but move to admin/utilities/
- `test_connection.php` - Keep but move to admin/utilities/

## Directory Structure Reorganization

```
pawproject/
├── admin/
│   ├── dashboard.php
│   ├── statistics.php
│   ├── student_management.php
│   ├── teacher_management.php
│   ├── module_management.php
│   ├── get_student_data.php
│   ├── get_teacher_data.php
│   ├── get_module_data.php
│   └── utilities/
│       ├── test_connection.php
│       └── system_debug.php
├── auth/
│   ├── login.php
│   ├── logout.php
│   ├── session_check.php
│   └── unauthorized.php
├── includes/
│   └── config.php
├── docs/
│   ├── PROJECT_OVERVIEW.md
│   ├── QUICK_START.md
│   ├── EXERCISES_IMPLEMENTATION.md
│   ├── MODULES_IMPLEMENTATION.md
│   └── samples/
│       ├── sample_students.csv
│       └── sample_teachers.csv
├── logs/
│   └── README.md
├── uploads/
│   └── README.md
├── professor/
│   └── dashboard.php
├── student/
│   └── dashboard.php
├── teacher/
│   └── dashboard.php
├── database/
│   └── README.md
├── setup_database.php
├── setup_sample_modules.php
├── wamp_status.php
├── index.html
└── README.md
```

## Code Cleanup Tasks

1. **Remove unused CSS classes and styles**
2. **Consolidate similar functions**
3. **Remove commented-out code**
4. **Standardize variable naming**
5. **Add proper error handling**
6. **Remove duplicate includes**

## Files to Optimize

1. **dashboard.php files** - Remove redundant styles, consolidate CSS
2. **management.php files** - Standardize form handling
3. **config.php** - Clean up and add documentation
4. **All PHP files** - Remove debug echo statements

## Security Improvements

1. **Add CSRF protection**
2. **Improve input validation**
3. **Add rate limiting for login**
4. **Sanitize all user inputs**
5. **Add proper session security**
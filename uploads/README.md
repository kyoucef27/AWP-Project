# Uploads Directory

This directory is used for temporary file uploads in the admin student management system.

## Security

- Files are automatically deleted after processing
- Direct access is restricted via .htaccess
- Only CSV and Excel files are accepted
- File size limits apply

## Usage

Used by:
- Student list import functionality
- Bulk student registration from Progres Excel format
- Temporary file storage during processing

## File Formats Supported

- CSV (.csv) - Recommended
- Excel (.xls, .xlsx) - Requires additional PHP libraries

## CSV Format

```
student_number,username,full_name,email,specialization
20210001,student.john,John Doe,john.doe@student.dz,Computer Science
20210002,student.jane,Jane Smith,jane.smith@student.dz,Information Systems
```

## Note

This directory should remain empty under normal circumstances as files are processed and deleted immediately.
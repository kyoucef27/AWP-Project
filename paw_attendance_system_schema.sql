-- =============================================
-- PAW Project Database Schema Export
-- Database: paw_attendance_system
-- Generated: 2025-11-27 00:26:24
-- =============================================

-- Drop existing tables (in reverse dependency order)
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `absence_justifications`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `enrollments`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `module_group_assignments`;
DROP TABLE IF EXISTS `modules`;
DROP TABLE IF EXISTS `professors`;
DROP TABLE IF EXISTS `student_group_assignments`;
DROP TABLE IF EXISTS `student_groups`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `teacher_modules`;
DROP TABLE IF EXISTS `teachers`;
DROP TABLE IF EXISTS `teaching_sessions`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- CREATE TABLES
-- =============================================

-- Table: absence_justifications
-- =============================================
CREATE TABLE `absence_justifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `attendance_id` int NOT NULL,
  `student_id` int NOT NULL,
  `justification_text` text COLLATE utf8mb3_unicode_ci NOT NULL,
  `supporting_document` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','approved','rejected') COLLATE utf8mb3_unicode_ci DEFAULT 'pending',
  `reviewed_by` int DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text COLLATE utf8mb3_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `attendance_id` (`attendance_id`),
  KEY `student_id` (`student_id`),
  KEY `reviewed_by` (`reviewed_by`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- Table: attendance
-- =============================================
CREATE TABLE `attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `enrollment_id` int NOT NULL,
  `session_id` int DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','absent','late','excused') COLLATE utf8mb3_unicode_ci DEFAULT 'absent',
  `remarks` text COLLATE utf8mb3_unicode_ci,
  `recorded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `recorded_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`enrollment_id`,`attendance_date`),
  KEY `recorded_by` (`recorded_by`),
  KEY `session_id` (`session_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3551 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- Table: enrollments
-- =============================================
CREATE TABLE `enrollments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `module_id` int NOT NULL,
  `enrollment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','dropped','completed') COLLATE utf8mb3_unicode_ci DEFAULT 'active',
  `grade` varchar(5) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enrollment` (`student_id`,`module_id`),
  KEY `module_id` (`module_id`)
) ENGINE=MyISAM AUTO_INCREMENT=671 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- Table: login_attempts
-- =============================================
CREATE TABLE `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb3_unicode_ci NOT NULL,
  `attempted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `successful` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- Table: module_group_assignments
-- =============================================
CREATE TABLE `module_group_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_id` int NOT NULL,
  `group_id` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_module_group` (`module_id`,`group_id`),
  KEY `group_id` (`group_id`)
) ENGINE=MyISAM AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- Table: modules
-- =============================================
CREATE TABLE `modules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_code` varchar(20) COLLATE utf8mb3_unicode_ci NOT NULL,
  `module_name` varchar(200) COLLATE utf8mb3_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb3_unicode_ci,
  `credits` int DEFAULT '3',
  `department` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `specialty` varchar(200) COLLATE utf8mb3_unicode_ci DEFAULT 'All',
  `year_level` int DEFAULT NULL,
  `semester` enum('Fall','Spring','Summer','Both') COLLATE utf8mb3_unicode_ci DEFAULT 'Both',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `module_code` (`module_code`)
) ENGINE=MyISAM AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- Table: professors
-- =============================================
CREATE TABLE `professors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `department` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `office_location` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- Table: student_group_assignments
-- =============================================
CREATE TABLE `student_group_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `group_id` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_assignment` (`student_id`),
  KEY `group_id` (`group_id`)
) ENGINE=MyISAM AUTO_INCREMENT=160 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- Table: student_groups
-- =============================================
CREATE TABLE `student_groups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `group_name` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `year_level` int NOT NULL,
  `specialization` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `max_capacity` int DEFAULT '30',
  `current_count` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_group` (`group_name`,`year_level`,`specialization`)
) ENGINE=MyISAM AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- Table: students
-- =============================================
CREATE TABLE `students` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `student_number` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `specialization` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `specialty` enum('Computer Science','Software Engineering','Information Systems','Data Science') COLLATE utf8mb3_unicode_ci DEFAULT 'Computer Science',
  `year_of_study` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_number` (`student_number`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=161 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- Table: teacher_modules
-- =============================================
CREATE TABLE `teacher_modules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int DEFAULT NULL,
  `module_id` int DEFAULT NULL,
  `role` enum('Lecturer','Assistant','Coordinator') COLLATE utf8mb3_unicode_ci DEFAULT 'Lecturer',
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_teacher_module` (`teacher_id`,`module_id`),
  KEY `module_id` (`module_id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- Table: teachers
-- =============================================
CREATE TABLE `teachers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `teacher_id` varchar(20) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `department` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `position` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `specialization` varchar(200) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teacher_id` (`teacher_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- Table: teaching_sessions
-- =============================================
CREATE TABLE `teaching_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `module_id` int NOT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `session_type` enum('Lecture','Lab','Tutorial','Exam','Workshop') COLLATE utf8mb3_unicode_ci DEFAULT 'Lecture',
  `location` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb3_unicode_ci,
  `attendance_taken` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_teacher_date` (`teacher_id`,`session_date`),
  KEY `idx_module_date` (`module_id`,`session_date`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- Table: users
-- =============================================
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `role` enum('admin','professor','student','teacher') COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'student',
  `email` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `full_name` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=224 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- =============================================
-- FOREIGN KEY RELATIONSHIPS SUMMARY
-- =============================================
<br />
<font size='1'><table class='xdebug-error xe-uncaught-exception' dir='ltr' border='1' cellspacing='0' cellpadding='1'>
<tr><th align='left' bgcolor='#f57900' colspan="5"><span style='background-color: #cc0000; color: #fce94f; font-size: x-large;'>( ! )</span> Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'UPDATE_RULE' in 'field list' in C:\wamp64\www\pawproject\export_database_schema.php on line <i>40</i></th></tr>
<tr><th align='left' bgcolor='#f57900' colspan="5"><span style='background-color: #cc0000; color: #fce94f; font-size: x-large;'>( ! )</span> PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'UPDATE_RULE' in 'field list' in C:\wamp64\www\pawproject\export_database_schema.php on line <i>40</i></th></tr>
<tr><th align='left' bgcolor='#e9b96e' colspan='5'>Call Stack</th></tr>
<tr><th align='center' bgcolor='#eeeeec'>#</th><th align='left' bgcolor='#eeeeec'>Time</th><th align='left' bgcolor='#eeeeec'>Memory</th><th align='left' bgcolor='#eeeeec'>Function</th><th align='left' bgcolor='#eeeeec'>Location</th></tr>
<tr><td bgcolor='#eeeeec' align='center'>1</td><td bgcolor='#eeeeec' align='center'>0.0004</td><td bgcolor='#eeeeec' align='right'>548672</td><td bgcolor='#eeeeec'>{main}(  )</td><td title='C:\wamp64\www\pawproject\export_database_schema.php' bgcolor='#eeeeec'>...\export_database_schema.php<b>:</b>0</td></tr>
<tr><td bgcolor='#eeeeec' align='center'>2</td><td bgcolor='#eeeeec' align='center'>0.0275</td><td bgcolor='#eeeeec' align='right'>599528</td><td bgcolor='#eeeeec'>getForeignKeys( <span>$pdo = </span><span>class PDO {  }</span>, <span>$tableName = </span><span>&#39;absence_justifications&#39;</span> )</td><td title='C:\wamp64\www\pawproject\export_database_schema.php' bgcolor='#eeeeec'>...\export_database_schema.php<b>:</b>88</td></tr>
<tr><td bgcolor='#eeeeec' align='center'>3</td><td bgcolor='#eeeeec' align='center'>0.0275</td><td bgcolor='#eeeeec' align='right'>599528</td><td bgcolor='#eeeeec'><a href='http://www.php.net/PDO.prepare' target='_new'>prepare</a>( <span>$query = </span><span>&#39;\r\n        SELECT \r\n            COLUMN_NAME,\r\n            REFERENCED_TABLE_NAME,\r\n            REFERENCED_COLUMN_NAME,\r\n            CONSTRAINT_NAME,\r\n            UPDATE_RULE,\r\n            DELETE_RULE\r\n        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE \r\n        WHERE TABLE_SCHEMA = DATABASE() \r\n        AND TABLE_NAME = ? \r\n        AND REFERENCED_TABLE_NAME IS NOT NULL\r\n    &#39;</span> )</td><td title='C:\wamp64\www\pawproject\export_database_schema.php' bgcolor='#eeeeec'>...\export_database_schema.php<b>:</b>40</td></tr>
</table></font>

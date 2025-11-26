<?php
// MySQL Configuration for WAMP
define('DB_HOST', 'localhost');
define('DB_PORT', '3308');
define('DB_NAME', 'paw_attendance_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_unicode_ci');

function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die('MySQL connection failed: ' . $e->getMessage());
    }
}

function ensureDatabaseExists() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " COLLATE " . DB_COLLATION);
        return true;
    } catch (PDOException $e) {
        error_log("Database creation error: " . $e->getMessage());
        return false;
    }
}
?>
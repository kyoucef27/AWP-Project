<?php
/**
 * Simple Session Management for PAW Project
 */

function checkAuthentication() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }
}

function checkRole($allowed_roles = []) {
    checkAuthentication();
    
    if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: ../auth/unauthorized.php");
        exit();
    }
}

function getCurrentUser() {
    checkAuthentication();
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'],
        'email' => $_SESSION['email'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? $_SESSION['username']
    ];
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isProfessor() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'professor';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}
?>
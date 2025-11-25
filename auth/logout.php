<?php
session_start();

// Clear all session data
$_SESSION = array();

// Delete the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page with a logout message
header("Location: login.php?logout=success");
exit();
?>
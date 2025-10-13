<?php
/**
 * Logout Page
 * Handles user session destruction and logout
 */

// Include required files
require_once 'includes/functions.php';

// Start session
startSecureSession();

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to signin page with message
header("Location: signin.php");
exit();
?>

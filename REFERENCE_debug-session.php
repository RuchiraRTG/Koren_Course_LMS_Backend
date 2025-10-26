<?php
/**
 * Session Debug Script
 * Place this in your PHP backend root (e.g., C:/xampp/htdocs/debug-session.php)
 * Access via: http://localhost/debug-session.php
 * 
 * This will show you what's currently in your PHP session
 */

require_once __DIR__ . '/includes/functions.php';

// Start session
startSecureSession();

header('Content-Type: application/json');

// CORS for testing
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['http://localhost:5173', 'http://localhost:5174', 'http://localhost:3000'];
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}

// Collect session information
$sessionData = [
    'session_id' => session_id(),
    'session_status' => session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive',
    'session_data' => $_SESSION,
    'has_user_id' => isset($_SESSION['user_id']),
    'has_user_type' => isset($_SESSION['user_type']),
    'has_user_role' => isset($_SESSION['user_role']),
    'user_id_value' => $_SESSION['user_id'] ?? null,
    'user_type_value' => $_SESSION['user_type'] ?? null,
    'user_role_value' => $_SESSION['user_role'] ?? null,
    'is_logged_in' => $_SESSION['is_logged_in'] ?? false,
    'cookies_sent' => $_COOKIE,
];

echo json_encode([
    'success' => true,
    'message' => 'Session debug info',
    'data' => $sessionData,
    'instructions' => [
        'If session is empty, you need to login first',
        'Access signin.php to create a session',
        'Make sure credentials: include is set in frontend',
        'Check that PHPSESSID cookie is present in browser'
    ]
], JSON_PRETTY_PRINT);
?>

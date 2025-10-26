<?php
/**
 * Sign In Endpoint - REFERENCE IMPLEMENTATION
 * This is how your signin.php should be structured to properly set sessions
 * Place this file in your PHP backend root (e.g., C:/xampp/htdocs/signin.php)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// CORS Configuration - MUST allow credentials
$allowedOrigins = [
    'http://localhost:3000',
    'http://localhost:5173',
    'http://localhost:5174',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:5174'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true"); // CRITICAL for cookies
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Accept, Authorization");
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start secure session
startSecureSession();
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendJsonResponse(false, 'Method not allowed');
    exit();
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

// Validate input
if (empty($email) || empty($password)) {
    http_response_code(400);
    sendJsonResponse(false, 'Email and password are required');
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    sendJsonResponse(false, 'Invalid email format');
    exit();
}

// Get database connection
$conn = getDBConnection();

// Query to get user by email
$sql = "SELECT id, first_name, last_name, email, password, role, is_active FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    $conn->close();
    http_response_code(500);
    sendJsonResponse(false, 'Server error');
    exit();
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

// Check if user exists
if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    http_response_code(401);
    sendJsonResponse(false, 'Invalid email or password');
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Check if account is active
if (!$user['is_active']) {
    $conn->close();
    http_response_code(403);
    sendJsonResponse(false, 'Account is inactive. Please contact administrator.');
    exit();
}

// Verify password
if (!verifyPassword($password, $user['password'])) {
    $conn->close();
    http_response_code(401);
    sendJsonResponse(false, 'Invalid email or password');
    exit();
}

// ========================================
// ✅ CRITICAL: Set session variables
// ========================================
$_SESSION['user_id'] = intval($user['id']);
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
$_SESSION['user_first_name'] = $user['first_name'];
$_SESSION['user_last_name'] = $user['last_name'];
$_SESSION['user_role'] = $user['role']; // 'admin' or 'student'

// IMPORTANT: Set user_type for exam submission logic
// The takeExam.php checks for user_type === 'student'
if ($user['role'] === 'admin') {
    $_SESSION['user_type'] = 'admin';
} else {
    $_SESSION['user_type'] = 'student';
}

$_SESSION['is_logged_in'] = true;
$_SESSION['login_time'] = time();

// Debug logging (remove in production)
error_log("=== User Logged In ===");
error_log("Session ID: " . session_id());
error_log("User ID: " . $_SESSION['user_id']);
error_log("User Type: " . $_SESSION['user_type']);
error_log("User Role: " . $_SESSION['user_role']);

// Update last_login timestamp in database
$updateSql = "UPDATE users SET last_login = NOW() WHERE id = ?";
$updateStmt = $conn->prepare($updateSql);
if ($updateStmt) {
    $userId = $user['id'];
    $updateStmt->bind_param('i', $userId);
    $updateStmt->execute();
    $updateStmt->close();
}

$conn->close();

// Return success response
http_response_code(200);
sendJsonResponse(true, 'Login successful', [
    'user_id' => intval($user['id']),
    'email' => $user['email'],
    'name' => $user['first_name'] . ' ' . $user['last_name'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'role' => $user['role'],
    'session_id' => session_id() // For debugging only
]);
?>

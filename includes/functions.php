<?php

// -- CORS start ---------------------------------------------------------
// Allow cross-origin requests for development/front-end integration.
// Important: tighten this to a specific origin in production (do NOT use '*').
if (!headers_sent()) {
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        // Reflect the originating host. Safer in development than a blanket '*'.
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    } else {
        header('Access-Control-Allow-Origin: *');
    }
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
}

// Handle preflight requests and return early
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// -- CORS end -----------------------------------------------------------

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Validate email format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate phone number (basic validation)
 */
function validatePhone($phone) {
   
    $phone = preg_replace('/[\s\-]/', '', $phone);
    
    return preg_match('/^[0-9]{10,15}$/', $phone);
}

/**
 * Validate NIC number (Sri Lankan NIC format)
 */
function validateNIC($nic) {
     
    return preg_match('/^([0-9]{9}[vVxX]|[0-9]{12})$/', $nic);
}

 
function validatePassword($password) {
    if (strlen($password) < 8) {
        return false;
    }
    // At least one letter and one number
    return preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password);
}

 
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

 
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate secure session token
 */
function generateSessionToken() {
    return bin2hex(random_bytes(32));
}

 
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configure session settings for security
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
        session_start();
    }
}

 
function isLoggedIn() {
    startSecureSession();
    return isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
}

/**
 * Check if current user is an admin
 */
function isAdmin() {
    startSecureSession();
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Require a logged-in admin; redirect otherwise
 */
function requireAdmin($redirectTo = '/signin.php') {
    startSecureSession();
    if (!isLoggedIn() || !isAdmin()) {
        header('Location: ' . $redirectTo);
        exit();
    }
}

 
function redirect($url) {
    header("Location: " . $url);
    exit();
}

 
function setFlashMessage($type, $message) {
    startSecureSession();
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

 
function getFlashMessage() {
    startSecureSession();
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Send JSON response
 */
function sendJsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

?>

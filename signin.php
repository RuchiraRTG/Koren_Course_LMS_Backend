<?php
/**
 * User Login (Signin) API
 * Handles user authentication - Backend Logic Only
 */

// Set headers for JSON response
header('Content-Type: application/json');

// Include required files
require_once 'config/database.php';
require_once 'includes/functions.php';

// Start session
startSecureSession();
// Ensure DB schema is initialized (including role column)
if (function_exists('initializeDatabase')) {
    initializeDatabase();
}

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'errors' => [],
    'data' => null
];

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method. Only POST is allowed.';
    echo json_encode($response);
    exit();
}

// Get JSON input if sent as JSON, otherwise use POST data
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
} else {
    $input = $_POST;
}

// Get and sanitize form data
$email = sanitizeInput($input['email'] ?? '');
$password = $input['password'] ?? '';

// Validate inputs
if (empty($email)) {
    $response['errors'][] = "Email is required";
} elseif (!validateEmail($email)) {
    $response['errors'][] = "Invalid email format";
}

if (empty($password)) {
    $response['errors'][] = "Password is required";
}

// If no validation errors, proceed with authentication
if (empty($response['errors'])) {
    try {
        $conn = getDBConnection();
        
        // First, try to get user from users table
        $stmt = $conn->prepare("SELECT id, first_name, last_name, email, nic_number, phone_number, password, is_active, role, created_at FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $userFound = false;
        $userType = null;
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $userFound = true;
            $userType = 'user';
        }
        $stmt->close();
        
        // If not found in users table, check students table
        if (!$userFound) {
            $stmt = $conn->prepare("SELECT id, first_name, last_name, email, nic_number, phone, password, is_active, batch_number, created_at FROM students WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $userFound = true;
                $userType = 'student';
                // Map 'phone' to 'phone_number' for consistency
                $user['phone_number'] = $user['phone'];
                $user['role'] = 'student';
            }
            $stmt->close();
        }
        
        if ($userFound) {
            // Check if account is active
            if ($user['is_active'] == 0) {
                $response['message'] = "Your account has been deactivated. Please contact support.";
                $response['errors'][] = "Account inactive";
            }
            // Verify password
            elseif (verifyPassword($password, $user['password'])) {
                // Password is correct, create session
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_role'] = $user['role'] ?? 'user';
                $_SESSION['user_type'] = $userType; // Track whether it's a user or student
                $_SESSION['session_token'] = generateSessionToken();
                
                // Add batch_number to session if student
                if ($userType === 'student' && isset($user['batch_number'])) {
                    $_SESSION['batch_number'] = $user['batch_number'];
                }
                
                // Update last login time based on user type
                if ($userType === 'user') {
                    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->bind_param("i", $user['id']);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
                // Note: students table doesn't have last_login column, can be added if needed
                
                // Set success response
                $response['success'] = true;
                $response['message'] = 'Login successful!';
                
                // Determine redirect based on role
                $redirectUrl = 'home.php'; // Default for students and regular users
                if ($userType === 'user' && ($user['role'] ?? 'user') === 'admin') {
                    $redirectUrl = 'admin/index.php';
                }

                $response['data'] = [
                    'user_id' => $user['id'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'full_name' => $user['first_name'] . ' ' . $user['last_name'],
                    'phone_number' => $user['phone_number'],
                    'nic_number' => $user['nic_number'],
                    'session_token' => $_SESSION['session_token'],
                    'role' => $_SESSION['user_role'],
                    'user_type' => $userType,
                    'redirect_url' => $redirectUrl
                ];
                
                // Add batch_number to response data if student
                if ($userType === 'student' && isset($user['batch_number'])) {
                    $response['data']['batch_number'] = $user['batch_number'];
                }
            } else {
                $response['message'] = "Invalid email or password";
                $response['errors'][] = "Invalid credentials";
            }
        } else {
            $response['message'] = "Invalid email or password";
            $response['errors'][] = "Invalid credentials";
        }
        
        $conn->close();
    } catch (Exception $e) {
        $response['message'] = "An error occurred during login";
        $response['errors'][] = "System error";
        error_log("Login exception: " . $e->getMessage());
    }
}

// Set response message if there are errors
if (!empty($response['errors']) && empty($response['message'])) {
    $response['message'] = count($response['errors']) === 1 ? $response['errors'][0] : 'Please fix the validation errors';
}

// Return JSON response
echo json_encode($response);
exit();

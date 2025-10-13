<?php
/**
 * User Registration (Signup) API
 * Handles new user account creation - Backend Logic Only
 */

// Set headers for JSON response
header('Content-Type: application/json');

// Include required files
require_once 'config/database.php';
require_once 'includes/functions.php';

// Start session
startSecureSession();

// Initialize database (creates table if not exists)
initializeDatabase();

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
$firstName = sanitizeInput($input['first_name'] ?? '');
$lastName = sanitizeInput($input['last_name'] ?? '');
$nicNumber = sanitizeInput($input['nic_number'] ?? '');
$phoneNumber = sanitizeInput($input['phone_number'] ?? '');
$email = sanitizeInput($input['email'] ?? '');
$password = $input['password'] ?? '';
$confirmPassword = $input['confirm_password'] ?? '';

// Validate inputs
if (empty($firstName)) {
    $response['errors'][] = "First name is required";
}

if (empty($lastName)) {
    $response['errors'][] = "Last name is required";
}

if (empty($nicNumber)) {
    $response['errors'][] = "NIC number is required";
} elseif (!validateNIC($nicNumber)) {
    $response['errors'][] = "Invalid NIC number format (Should be 9 digits + V or 12 digits)";
}

if (empty($phoneNumber)) {
    $response['errors'][] = "Phone number is required";
} elseif (!validatePhone($phoneNumber)) {
    $response['errors'][] = "Invalid phone number format";
}

if (empty($email)) {
    $response['errors'][] = "Email is required";
} elseif (!validateEmail($email)) {
    $response['errors'][] = "Invalid email format";
}

if (empty($password)) {
    $response['errors'][] = "Password is required";
} elseif (!validatePassword($password)) {
    $response['errors'][] = "Password must be at least 8 characters and contain both letters and numbers";
}

if ($password !== $confirmPassword) {
    $response['errors'][] = "Passwords do not match";
}

// If no validation errors, proceed with registration
if (empty($response['errors'])) {
    try {
        $conn = getDBConnection();
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $response['errors'][] = "Email address already registered";
        }
        $stmt->close();
        
        // Check if NIC already exists
        if (empty($response['errors'])) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE nic_number = ?");
            $stmt->bind_param("s", $nicNumber);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $response['errors'][] = "NIC number already registered";
            }
            $stmt->close();
        }
        
        // Insert new user if no errors
        if (empty($response['errors'])) {
            $hashedPassword = hashPassword($password);
            
            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, nic_number, phone_number, email, password) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $firstName, $lastName, $nicNumber, $phoneNumber, $email, $hashedPassword);
            
            if ($stmt->execute()) {
                $userId = $stmt->insert_id;
                
                $response['success'] = true;
                $response['message'] = 'Account created successfully!';
                $response['data'] = [
                    'user_id' => $userId,
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName
                ];
            } else {
                $response['message'] = "Registration failed. Please try again.";
                $response['errors'][] = "Database error occurred";
                error_log("Registration error: " . $stmt->error);
            }
            
            $stmt->close();
        }
        
        $conn->close();
    } catch (Exception $e) {
        $response['message'] = "An error occurred during registration";
        $response['errors'][] = "System error";
        error_log("Registration exception: " . $e->getMessage());
    }
}

// Set response message if there are errors
if (!empty($response['errors'])) {
    $response['message'] = count($response['errors']) === 1 ? $response['errors'][0] : 'Please fix the validation errors';
}

// Return JSON response
echo json_encode($response);
exit();

<?php
/**
 * Check Session API
 * Returns current user session information
 */

// Set headers for JSON response
header('Content-Type: application/json');

// Include required files
require_once 'config/database.php';
require_once 'includes/functions.php';

// Start session
startSecureSession();

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if user is logged in
if (isLoggedIn()) {
    $userId = $_SESSION['user_id'];
    
    try {
        $conn = getDBConnection();
        
        // Get user details from database
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email, phone_number, nic_number, role, created_at, last_login FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            $response['success'] = true;
            $response['message'] = 'User is logged in';
            $response['data'] = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'full_name' => $user['first_name'] . ' ' . $user['last_name'],
                'phone_number' => $user['phone_number'],
                'nic_number' => $user['nic_number'],
                'session_token' => $_SESSION['session_token'] ?? null,
                'role' => $_SESSION['user_role'] ?? $user['role'] ?? 'user',
                'created_at' => $user['created_at'],
                'last_login' => $user['last_login']
            ];
        } else {
            $response['message'] = 'User not found in database';
        }
        
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        $response['message'] = 'Error retrieving user information';
        error_log("Session check exception: " . $e->getMessage());
    }
} else {
    $response['message'] = 'User is not logged in';
}

// Return JSON response
echo json_encode($response);
exit();

<?php
// ✅ CORS Headers MUST come FIRST - before ANY output
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session AFTER headers
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Database connection
require_once 'config/database.php';

try {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    $userType = $_SESSION['user_type'] ?? 'user';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetProfile($conn, $userId, $userType);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        handlePutProfile($conn, $userId, $userType);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

// ============ GET PROFILE ============
function handleGetProfile($conn, $userId, $userType) {
    try {
        // Using snake_case columns (first_name, last_name, etc.)
        if ($userType === 'student') {
            $query = "SELECT 
                id, 
                first_name as firstName, 
                last_name as lastName, 
                email, 
                phone, 
                nic_number as nicNumber,
                batch_number as batchNumber,
                profile_photo as profilePhoto,
                created_at as createdAt,
                updated_at as updatedAt,
                is_active as isActive
            FROM students 
            WHERE id = ?";
        } else {
            $query = "SELECT 
                id, 
                first_name as firstName, 
                last_name as lastName, 
                email, 
                phone,
                nic_number as nicNumber,
                batch_number as batchNumber,
                profile_photo as profilePhoto,
                role,
                created_at as createdAt,
                updated_at as updatedAt,
                is_active as isActive
            FROM users 
            WHERE id = ?";
        }

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $profile = $result->fetch_assoc();
        $stmt->close();

        if ($profile) {
            echo json_encode(['success' => true, 'data' => $profile]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Profile not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

// ============ PUT PROFILE (UPDATE) ============
function handlePutProfile($conn, $userId, $userType) {
    try {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
            return;
        }

        // Validate required fields
        if (empty($input['firstName']) || empty($input['lastName'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'First name and last name are required']);
            return;
        }

        // Validate email format
        if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            return;
        }

        // Sanitize inputs
        $firstName = htmlspecialchars(trim($input['firstName']), ENT_QUOTES, 'UTF-8');
        $lastName = htmlspecialchars(trim($input['lastName']), ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars(trim($input['email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars(trim($input['phone'] ?? ''), ENT_QUOTES, 'UTF-8');

        // Update query - using snake_case columns
        if ($userType === 'student') {
            $updateQuery = "UPDATE students SET 
                first_name = ?, 
                last_name = ?, 
                email = ?, 
                phone = ?,
                updated_at = NOW()
            WHERE id = ?";
        } else {
            $updateQuery = "UPDATE users SET 
                first_name = ?, 
                last_name = ?, 
                email = ?, 
                phone = ?,
                updated_at = NOW()
            WHERE id = ?";
        }

        $stmt = $conn->prepare($updateQuery);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param('ssssi', $firstName, $lastName, $email, $phone, $userId);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        $stmt->close();

        // Fetch and return updated profile
        if ($userType === 'student') {
            $fetchQuery = "SELECT 
                id, 
                first_name as firstName, 
                last_name as lastName, 
                email, 
                phone,
                nic_number as nicNumber,
                batch_number as batchNumber,
                profile_photo as profilePhoto,
                created_at as createdAt,
                updated_at as updatedAt,
                is_active as isActive
            FROM students 
            WHERE id = ?";
        } else {
            $fetchQuery = "SELECT 
                id, 
                first_name as firstName, 
                last_name as lastName, 
                email, 
                phone,
                nic_number as nicNumber,
                batch_number as batchNumber,
                profile_photo as profilePhoto,
                role,
                created_at as createdAt,
                updated_at as updatedAt,
                is_active as isActive
            FROM users 
            WHERE id = ?";
        }

        $fetchStmt = $conn->prepare($fetchQuery);
        if (!$fetchStmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $fetchStmt->bind_param('i', $userId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $updatedProfile = $result->fetch_assoc();
        $fetchStmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $updatedProfile
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
?>

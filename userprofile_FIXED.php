<?php
// ✅ CORS Headers MUST come FIRST - before any output or session_start()
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

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
    exit();
}

// Include dependencies
require_once 'config/database.php';
require_once 'includes/functions.php';

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
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

// ============ GET Handler ============
function handleGetProfile($conn, $userId, $userType) {
    try {
        if ($userType === 'student') {
            $query = "SELECT 
                id, firstName, lastName, email, phone, 
                nicNumber, batchNumber, profilePhoto,
                createdAt, updatedAt, isActive
            FROM students WHERE id = ?";
        } else {
            $query = "SELECT 
                id, firstName, lastName, email, phone,
                nicNumber, batchNumber, profilePhoto, role,
                createdAt, updatedAt, isActive
            FROM users WHERE id = ?";
        }

        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $profile = $result->fetch_assoc();
        $stmt->close();

        if ($profile) {
            echo json_encode([
                'success' => true,
                'data' => $profile
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Profile not found'
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching profile: ' . $e->getMessage()
        ]);
    }
}

// ============ PUT Handler ============
function handlePutProfile($conn, $userId, $userType) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
            return;
        }

        // Validate required fields
        if (empty($input['firstName']) || empty($input['lastName'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'First and last name required']);
            return;
        }

        // Validate email if provided
        if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email']);
            return;
        }

        // Sanitize inputs
        $firstName = htmlspecialchars(trim($input['firstName']), ENT_QUOTES, 'UTF-8');
        $lastName = htmlspecialchars(trim($input['lastName']), ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars(trim($input['email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars(trim($input['phone'] ?? ''), ENT_QUOTES, 'UTF-8');

        // Update query
        if ($userType === 'student') {
            $query = "UPDATE students SET 
                firstName = ?, lastName = ?, email = ?, phone = ?,
                updatedAt = NOW()
            WHERE id = ?";
        } else {
            $query = "UPDATE users SET 
                firstName = ?, lastName = ?, email = ?, phone = ?,
                updatedAt = NOW()
            WHERE id = ?";
        }

        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssssi', $firstName, $lastName, $email, $phone, $userId);
        
        if ($stmt->execute()) {
            // Fetch updated profile
            if ($userType === 'student') {
                $getQuery = "SELECT 
                    id, firstName, lastName, email, phone,
                    nicNumber, batchNumber, profilePhoto,
                    createdAt, updatedAt, isActive
                FROM students WHERE id = ?";
            } else {
                $getQuery = "SELECT 
                    id, firstName, lastName, email, phone,
                    nicNumber, batchNumber, profilePhoto, role,
                    createdAt, updatedAt, isActive
                FROM users WHERE id = ?";
            }

            $getStmt = $conn->prepare($getQuery);
            $getStmt->bind_param('i', $userId);
            $getStmt->execute();
            $getResult = $getStmt->get_result();
            $updatedProfile = $getResult->fetch_assoc();
            $getStmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $updatedProfile
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update profile: ' . $conn->error
            ]);
        }

        $stmt->close();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}
?>

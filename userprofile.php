<?php
// ✅ CORS Headers FIRST - before ANYTHING
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Turn off HTML error display, catch errors as exceptions
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Custom error handler - convert errors to JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => "PHP Error: $errstr at line $errline in $errfile"
    ]);
    exit();
});

// Handle preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

try {
    // Database connection
    require_once 'config/database.php';
    
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

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

    if ($conn) {
        $conn->close();
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ============ GET PROFILE ============
function handleGetProfile($conn, $userId, $userType) {
    try {
        $table = $userType === 'student' ? 'students' : 'users';
        
        // Simple query - use all columns
        $query = "SELECT * FROM $table WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        if (!$result) {
            throw new Exception('Get result failed: ' . $conn->error);
        }

        $profile = $result->fetch_assoc();
        $stmt->close();

        if ($profile) {
            // Convert to camelCase
            $profile = arrayToCamelCase($profile);
            echo json_encode(['success' => true, 'data' => $profile]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Profile not found']);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'GET: ' . $e->getMessage()]);
    }
}

// ============ PUT PROFILE (UPDATE) ============
function handlePutProfile($conn, $userId, $userType) {
    try {
        // Get JSON input
        $jsonInput = file_get_contents('php://input');
        if (!$jsonInput) {
            throw new Exception('Empty request body');
        }

        $input = json_decode($jsonInput, true);
        if ($input === null) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }

        // Validate required fields
        if (empty($input['firstName']) || empty($input['lastName'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'First name and last name required']);
            return;
        }

        // Sanitize
        $firstName = trim($input['firstName']);
        $lastName = trim($input['lastName']);
        $email = isset($input['email']) ? trim($input['email']) : '';
        $phone = isset($input['phone']) ? trim($input['phone']) : '';

        // Validate email if provided
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email']);
            return;
        }

        $table = $userType === 'student' ? 'students' : 'users';

        // Try with snake_case columns first
        $updateQuery = "UPDATE $table SET 
            first_name = ?, 
            last_name = ?, 
            email = ?, 
            phone = ?
            WHERE id = ?";

        $stmt = $conn->prepare($updateQuery);
        
        // If that fails, the columns might not exist
        if (!$stmt) {
            // Try without specific column names (using underscore format)
            throw new Exception('Update query failed: ' . $conn->error);
        }

        $stmt->bind_param('ssssi', $firstName, $lastName, $email, $phone, $userId);
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            
            // If column not found, try camelCase
            if (strpos($error, 'Unknown column') !== false) {
                $updateQuery2 = "UPDATE $table SET 
                    firstName = ?, 
                    lastName = ?, 
                    email = ?, 
                    phone = ?
                    WHERE id = ?";
                
                $stmt2 = $conn->prepare($updateQuery2);
                if (!$stmt2) {
                    throw new Exception('Update failed with camelCase too: ' . $conn->error);
                }
                
                $stmt2->bind_param('ssssi', $firstName, $lastName, $email, $phone, $userId);
                if (!$stmt2->execute()) {
                    throw new Exception('Execute failed: ' . $stmt2->error);
                }
                $stmt2->close();
            } else {
                throw new Exception('Execute failed: ' . $error);
            }
        } else {
            $stmt->close();
        }

        // Fetch updated profile
        $fetchQuery = "SELECT * FROM $table WHERE id = ?";
        $fetchStmt = $conn->prepare($fetchQuery);
        if (!$fetchStmt) {
            throw new Exception('Fetch prepare failed: ' . $conn->error);
        }

        $fetchStmt->bind_param('i', $userId);
        if (!$fetchStmt->execute()) {
            throw new Exception('Fetch execute failed: ' . $fetchStmt->error);
        }

        $result = $fetchStmt->get_result();
        $updatedProfile = $result->fetch_assoc();
        $fetchStmt->close();

        // Convert to camelCase
        $updatedProfile = arrayToCamelCase($updatedProfile);

        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $updatedProfile
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'PUT: ' . $e->getMessage()]);
    }
}

// ============ HELPER: Convert array keys to camelCase ============
function arrayToCamelCase($array) {
    $result = [];
    foreach ($array as $key => $value) {
        // Convert snake_case to camelCase
        $camelKey = lcfirst(str_replace('_', '', ucwords($key, '_')));
        $result[$camelKey] = $value;
    }
    return $result;
}

?>

<?php
// ✅ CORS Headers FIRST
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

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
        // Get table info first
        $tableInfo = $userType === 'student' ? 'students' : 'users';
        
        // Get all columns from table
        $columnsQuery = "DESCRIBE {$tableInfo}";
        $columnsResult = $conn->query($columnsQuery);
        
        $columns = [];
        while ($row = $columnsResult->fetch_assoc()) {
            $columns[] = $row['Field'];
        }

        // Build dynamic SELECT - use actual column names
        $selectColumns = implode(', ', $columns);
        
        $query = "SELECT {$selectColumns} FROM {$tableInfo} WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Prepare error: ' . $conn->error);
        }

        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) {
            throw new Exception('Execute error: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $profile = $result->fetch_assoc();
        $stmt->close();

        if ($profile) {
            // Convert all keys to camelCase for frontend
            $camelCaseProfile = convertToCamelCase($profile);
            echo json_encode(['success' => true, 'data' => $camelCaseProfile]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Profile not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'GET Error: ' . $e->getMessage()]);
    }
}

// ============ PUT PROFILE (UPDATE) ============
function handlePutProfile($conn, $userId, $userType) {
    try {
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

        // Validate email
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

        $tableInfo = $userType === 'student' ? 'students' : 'users';

        // Check which columns exist and build dynamic UPDATE
        $columnsQuery = "DESCRIBE {$tableInfo}";
        $columnsResult = $conn->query($columnsQuery);
        
        $columns = [];
        while ($row = $columnsResult->fetch_assoc()) {
            $columns[] = strtolower($row['Field']);
        }

        // Build UPDATE statement with columns that exist
        $updateParts = [];
        $params = [];
        $types = '';

        // Map frontend fields to database columns
        if (in_array('first_name', $columns)) {
            $updateParts[] = "first_name = ?";
            $params[] = $firstName;
            $types .= 's';
        } elseif (in_array('firstname', $columns)) {
            $updateParts[] = "firstname = ?";
            $params[] = $firstName;
            $types .= 's';
        }

        if (in_array('last_name', $columns)) {
            $updateParts[] = "last_name = ?";
            $params[] = $lastName;
            $types .= 's';
        } elseif (in_array('lastname', $columns)) {
            $updateParts[] = "lastname = ?";
            $params[] = $lastName;
            $types .= 's';
        }

        if (in_array('email', $columns)) {
            $updateParts[] = "email = ?";
            $params[] = $email;
            $types .= 's';
        }

        if (in_array('phone', $columns)) {
            $updateParts[] = "phone = ?";
            $params[] = $phone;
            $types .= 's';
        }

        // Add updated_at if it exists
        if (in_array('updated_at', $columns)) {
            $updateParts[] = "updated_at = NOW()";
        } elseif (in_array('updatedat', $columns)) {
            $updateParts[] = "updatedat = NOW()";
        }

        $updateQuery = "UPDATE {$tableInfo} SET " . implode(', ', $updateParts) . " WHERE id = ?";
        $params[] = $userId;
        $types .= 'i';

        $stmt = $conn->prepare($updateQuery);
        if (!$stmt) {
            throw new Exception('Prepare error: ' . $conn->error);
        }

        call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $params));

        if (!$stmt->execute()) {
            throw new Exception('Execute error: ' . $stmt->error);
        }
        $stmt->close();

        // Fetch updated profile
        $columnsQuery = "DESCRIBE {$tableInfo}";
        $columnsResult = $conn->query($columnsQuery);
        
        $allColumns = [];
        while ($row = $columnsResult->fetch_assoc()) {
            $allColumns[] = $row['Field'];
        }

        $selectColumns = implode(', ', $allColumns);
        $fetchQuery = "SELECT {$selectColumns} FROM {$tableInfo} WHERE id = ?";

        $fetchStmt = $conn->prepare($fetchQuery);
        if (!$fetchStmt) {
            throw new Exception('Prepare error: ' . $conn->error);
        }

        $fetchStmt->bind_param('i', $userId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $updatedProfile = $result->fetch_assoc();
        $fetchStmt->close();

        $camelCaseProfile = convertToCamelCase($updatedProfile);

        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $camelCaseProfile
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'PUT Error: ' . $e->getMessage()]);
    }
}

// ============ HELPER FUNCTION: Convert snake_case to camelCase ============
function convertToCamelCase($array) {
    $result = [];
    foreach ($array as $key => $value) {
        $camelKey = lcfirst(str_replace('_', '', ucwords($key, '_')));
        $result[$camelKey] = $value;
    }
    return $result;
}

?>

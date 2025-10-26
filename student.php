<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config/database.php';

// Get database connection
$conn = getDBConnection();

// Initialize database tables
initializeStudentTable($conn);

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Route requests
switch ($method) {
    case 'GET':
        handleGet($conn, $action);
        break;
    case 'POST':
        // Support DELETE via POST with action=delete for clients that cannot send DELETE
        if (isset($_GET['action']) && strtolower($_GET['action']) === 'delete') {
            handleDelete($conn);
            break;
        }
        handlePost($conn);
        break;
    case 'PUT':
        handlePut($conn);
        break;
    case 'DELETE':
        handleDelete($conn);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}

// Initialize database table
function initializeStudentTable($conn) {
    // Create students table
    $sql = "CREATE TABLE IF NOT EXISTS students (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        batch_number VARCHAR(50) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        phone VARCHAR(20) NOT NULL,
        nic_number VARCHAR(20) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active TINYINT(1) DEFAULT 1,
        INDEX idx_batch (batch_number),
        INDEX idx_email (email),
        INDEX idx_nic (nic_number),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($sql);
    
    // Add password column if it doesn't exist (for existing tables)
    $checkColumn = $conn->query("SHOW COLUMNS FROM students LIKE 'password'");
    if ($checkColumn->num_rows == 0) {
        $conn->query("ALTER TABLE students ADD COLUMN password VARCHAR(255) NOT NULL DEFAULT ''");
    }
}

// Handle GET requests
function handleGet($conn, $action) {
    switch ($action) {
        case 'list':
            getAllStudents($conn);
            break;
        case 'view':
            getStudentById($conn);
            break;
        case 'stats':
            getStudentStats($conn);
            break;
        default:
            getAllStudents($conn);
            break;
    }
}

// Get all students
function getAllStudents($conn) {
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $batchNumber = isset($_GET['batch']) ? $_GET['batch'] : '';

    $sql = "SELECT * FROM students WHERE is_active = 1";
    
    if (!empty($search)) {
        $search = $conn->real_escape_string($search);
        $sql .= " AND (first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR email LIKE '%$search%' OR batch_number LIKE '%$search%' OR nic_number LIKE '%$search%')";
    }
    
    if (!empty($batchNumber)) {
        $batchNumber = $conn->real_escape_string($batchNumber);
        $sql .= " AND batch_number = '$batchNumber'";
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $students = [];
        while ($row = $result->fetch_assoc()) {
            $students[] = [
                'id' => $row['id'],
                'firstName' => $row['first_name'],
                'lastName' => $row['last_name'],
                'batchNumber' => $row['batch_number'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'nicNumber' => $row['nic_number'],
                'createdAt' => $row['created_at'],
                'updatedAt' => $row['updated_at']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $students,
            'total' => count($students)
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to retrieve students'
        ]);
    }
}

// Get single student by ID
function getStudentById($conn) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
        return;
    }
    
    $sql = "SELECT * FROM students WHERE id = ? AND is_active = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $student = [
            'id' => $row['id'],
            'firstName' => $row['first_name'],
            'lastName' => $row['last_name'],
            'batchNumber' => $row['batch_number'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'nicNumber' => $row['nic_number'],
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at']
        ];
        
        echo json_encode([
            'success' => true,
            'data' => $student
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Student not found'
        ]);
    }
    
    $stmt->close();
}

// Get student statistics
function getStudentStats($conn) {
    $stats = [];
    
    // Total students
    $result = $conn->query("SELECT COUNT(*) as total FROM students WHERE is_active = 1");
    $stats['total'] = $result->fetch_assoc()['total'];
    
    // By batch
    $result = $conn->query("SELECT batch_number, COUNT(*) as count FROM students WHERE is_active = 1 GROUP BY batch_number ORDER BY batch_number DESC");
    $stats['by_batch'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['by_batch'][$row['batch_number']] = $row['count'];
    }
    
    // Recent students (last 7 days)
    $result = $conn->query("SELECT COUNT(*) as count FROM students WHERE is_active = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['recent_students'] = $result->fetch_assoc()['count'];
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
}

// Handle POST requests (Create)
function handlePost($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validation
    $validation = validateStudentData($input);
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $validation['message']
        ]);
        return;
    }
    
    // Check for duplicate email
    $email = $conn->real_escape_string($input['email']);
    $checkEmail = $conn->query("SELECT id FROM students WHERE email = '$email' AND is_active = 1");
    if ($checkEmail->num_rows > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'A student with this email already exists'
        ]);
        return;
    }
    
    // Check for duplicate NIC
    $nicNumber = $conn->real_escape_string($input['nicNumber']);
    $checkNic = $conn->query("SELECT id FROM students WHERE nic_number = '$nicNumber' AND is_active = 1");
    if ($checkNic->num_rows > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'A student with this NIC number already exists'
        ]);
        return;
    }
    
    // Hash the password
    $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
    
    // Insert student
    $sql = "INSERT INTO students (
        first_name, last_name, batch_number, email, phone, nic_number, password
    ) VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'sssssss',
        $input['firstName'],
        $input['lastName'],
        $input['batchNumber'],
        $input['email'],
        $input['phone'],
        $input['nicNumber'],
        $hashedPassword
    );
    
    if ($stmt->execute()) {
        $studentId = $conn->insert_id;
        
        echo json_encode([
            'success' => true,
            'message' => 'Student created successfully',
            'student_id' => $studentId
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create student: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
}

// Handle PUT requests (Update)
function handlePut($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id']) || empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    $studentId = intval($input['id']);
    
    // Validation
    $validation = validateStudentData($input);
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $validation['message']
        ]);
        return;
    }
    
    // Check for duplicate email (excluding current student)
    $email = $conn->real_escape_string($input['email']);
    $checkEmail = $conn->query("SELECT id FROM students WHERE email = '$email' AND id != $studentId AND is_active = 1");
    if ($checkEmail->num_rows > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'A student with this email already exists'
        ]);
        return;
    }
    
    // Check for duplicate NIC (excluding current student)
    $nicNumber = $conn->real_escape_string($input['nicNumber']);
    $checkNic = $conn->query("SELECT id FROM students WHERE nic_number = '$nicNumber' AND id != $studentId AND is_active = 1");
    if ($checkNic->num_rows > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'A student with this NIC number already exists'
        ]);
        return;
    }
    
    // Update student
    $sql = "UPDATE students SET 
        first_name = ?, last_name = ?, batch_number = ?, 
        email = ?, phone = ?, nic_number = ?";
    
    $params = [
        $input['firstName'],
        $input['lastName'],
        $input['batchNumber'],
        $input['email'],
        $input['phone'],
        $input['nicNumber']
    ];
    $types = 'ssssss';
    
    // Update password only if provided
    if (isset($input['password']) && !empty($input['password'])) {
        $sql .= ", password = ?";
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
        $params[] = $hashedPassword;
        $types .= 's';
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $studentId;
    $types .= 'i';
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Student updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'No changes made to student'
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update student: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
}

// Handle DELETE requests (Soft delete)
function handleDelete($conn) {
    // Accept id from query string, JSON body, or form body
    $id = 0;
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
    }
    if ($id <= 0) {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $data = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['id'])) {
                $id = intval($data['id']);
            }
        }
    }
    if ($id <= 0 && isset($_POST['id'])) {
        $id = intval($_POST['id']);
    }

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing student ID']);
        return;
    }

    // Soft delete - just mark as inactive
    $sql = "UPDATE students SET is_active = 0 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        echo json_encode([
            'success' => true,
            'message' => $affected > 0 ? 'Student deleted successfully' : 'No change (already deleted or not found)',
            'affected' => $affected,
            'id' => $id
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete student'
        ]);
    }

    $stmt->close();
}

// Validate student data
function validateStudentData($data) {
    // Required fields
    if (empty($data['firstName'])) {
        return ['valid' => false, 'message' => 'First name is required'];
    }
    
    if (empty($data['lastName'])) {
        return ['valid' => false, 'message' => 'Last name is required'];
    }
    
    if (empty($data['batchNumber'])) {
        return ['valid' => false, 'message' => 'Batch number is required'];
    }
    
    if (empty($data['email'])) {
        return ['valid' => false, 'message' => 'Email is required'];
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'message' => 'Invalid email format'];
    }
    
    if (empty($data['phone'])) {
        return ['valid' => false, 'message' => 'Phone number is required'];
    }
    
    // Validate phone format (Sri Lankan format: 10 digits starting with 0)
    if (!preg_match('/^0\d{9}$/', $data['phone'])) {
        return ['valid' => false, 'message' => 'Invalid phone number format (should be 10 digits starting with 0)'];
    }
    
    if (empty($data['nicNumber'])) {
        return ['valid' => false, 'message' => 'NIC number is required'];
    }
    
    // Validate NIC format (Sri Lankan: old 9 digits + V/X or new 12 digits)
    if (!preg_match('/^(\d{9}[VvXx]|\d{12})$/', $data['nicNumber'])) {
        return ['valid' => false, 'message' => 'Invalid NIC number format'];
    }
    
    // Password is required only for new students (when id is not set)
    if (!isset($data['id']) || empty($data['id'])) {
        if (empty($data['password'])) {
            return ['valid' => false, 'message' => 'Password is required'];
        }
        
        // Validate password strength
        if (strlen($data['password']) < 6) {
            return ['valid' => false, 'message' => 'Password must be at least 6 characters long'];
        }
    }
    
    return ['valid' => true];
}

$conn->close();
?>

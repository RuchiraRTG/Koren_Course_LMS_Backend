<?php
// MUST BE FIRST - CORS Configuration
$allowedOrigins = [
    'http://localhost:5173',
    'http://localhost:3000',
    'http://localhost:5174',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:5174'
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
} else {
    // For testing, allow all origins but without credentials
    header("Access-Control-Allow-Origin: http://localhost:5173");
    header("Access-Control-Allow-Credentials: true");
}

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 86400");
header('Content-Type: application/json');

// Handle preflight OPTIONS requests immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Error handling - catch any errors and return JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to browser
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit();
});

// Try to include database config
if (file_exists(__DIR__ . '/config/database.php')) {
    require_once __DIR__ . '/config/database.php';
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Database configuration file not found',
        'path' => __DIR__ . '/config/database.php'
    ]);
    exit();
}

// Get database connection with error handling
try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Failed to connect to database');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection error: ' . $e->getMessage()
    ]);
    exit();
}

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Route requests
switch ($method) {
    case 'GET':
        handleGet($conn, $action);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}

// Handle GET requests
function handleGet($conn, $action) {
    switch ($action) {
        case 'list':
        case '':
            getStudentProgress($conn);
            break;
        case 'stats':
            getProgressStats($conn);
            break;
        case 'student':
            getStudentProgressById($conn);
            break;
        default:
            getStudentProgress($conn);
            break;
    }
}

/**
 * Get all student progress data with exam results
 * This function retrieves all exam results along with student information
 */
function getStudentProgress($conn) {
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $level = isset($_GET['level']) ? $_GET['level'] : '';
    
    // SQL query to get student information along with their exam results
    // Check both users table and students table for student information
    $sql = "SELECT 
                er.student_id,
                COALESCE(u.first_name, s.first_name, 'Unknown') as first_name,
                COALESCE(u.last_name, s.last_name, CONCAT('Student #', er.student_id)) as last_name,
                COALESCE(u.email, s.email, CONCAT('student', er.student_id, '@unknown.com')) as email,
                er.id as exam_result_id,
                er.exam_id,
                er.score,
                er.total_marks,
                er.percentage,
                er.time_taken,
                er.status,
                er.submitted_at,
                er.created_at as exam_date
            FROM exam_results er
            LEFT JOIN users u ON er.student_id = u.id
            LEFT JOIN students s ON er.student_id = s.id
            WHERE (er.status = 'completed' OR er.status = 'submitted')";
    
    // Add search filter
    if (!empty($search)) {
        $search = $conn->real_escape_string($search);
        $sql .= " AND (u.first_name LIKE '%$search%' 
                  OR u.last_name LIKE '%$search%' 
                  OR u.email LIKE '%$search%' 
                  OR CONCAT(u.first_name, ' ', u.last_name) LIKE '%$search%')";
    }
    
    $sql .= " ORDER BY er.submitted_at DESC";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $studentProgress = [];
        
        while ($row = $result->fetch_assoc()) {
            // Include ALL exam results, not just one per student
            $studentProgress[] = [
                'id' => intval($row['exam_result_id']), // Use exam_result_id as unique identifier
                'studentId' => intval($row['student_id']),
                'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'email' => $row['email'],
                'marks' => round(floatval($row['percentage']), 2),
                'examFaceDate' => $row['submitted_at'],
                'score' => floatval($row['score']),
                'totalMarks' => intval($row['total_marks']),
                'examId' => $row['exam_id'] ? intval($row['exam_id']) : null
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $studentProgress,
            'total' => count($studentProgress)
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to retrieve student progress',
            'error' => $conn->error
        ]);
    }
}

/**
 * Get progress data for a specific student
 */
function getStudentProgressById($conn) {
    $studentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($studentId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
        return;
    }
    
    $sql = "SELECT 
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                u.phone_number,
                er.id as exam_result_id,
                er.exam_id,
                er.score,
                er.total_marks,
                er.percentage,
                er.time_taken,
                er.status,
                er.submitted_at,
                er.created_at as exam_date
            FROM exam_results er
            INNER JOIN users u ON er.student_id = u.id
            WHERE u.id = ? AND u.is_active = 1 
            AND (er.status = 'completed' OR er.status = 'submitted')
            ORDER BY er.submitted_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $studentData = null;
        
        // Get the most recent exam result
        $row = $result->fetch_assoc();
        
        $studentData = [
            'id' => $row['id'],
            'name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'email' => $row['email'],
            'marks' => round(floatval($row['percentage']), 2),
            'examFaceDate' => $row['submitted_at'],
            'score' => floatval($row['score']),
            'totalMarks' => intval($row['total_marks'])
        ];
        
        echo json_encode([
            'success' => true,
            'data' => $studentData
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Student not found or no completed exams'
        ]);
    }
    
    $stmt->close();
}

/**
 * Get statistical overview of student progress
 */
function getProgressStats($conn) {
    $stats = [];
    
    // Total students with completed exam results
    $result = $conn->query("
        SELECT COUNT(DISTINCT er.student_id) as total 
        FROM exam_results er
        INNER JOIN users u ON er.student_id = u.id
        WHERE u.is_active = 1 
        AND (er.status = 'completed' OR er.status = 'submitted')
    ");
    $stats['totalStudents'] = $result->fetch_assoc()['total'];
    
    // Average completion rate (percentage)
    $result = $conn->query("
        SELECT AVG(er.percentage) as avg_completion 
        FROM exam_results er
        INNER JOIN users u ON er.student_id = u.id
        WHERE u.is_active = 1 
        AND (er.status = 'completed' OR er.status = 'submitted')
    ");
    $avgRow = $result->fetch_assoc();
    $stats['avgCompletion'] = $avgRow['avg_completion'] ? round($avgRow['avg_completion'], 2) : 0;
    
    // Active today (students with exam results submitted today)
    $result = $conn->query("
        SELECT COUNT(DISTINCT er.student_id) as active_today
        FROM exam_results er
        INNER JOIN users u ON er.student_id = u.id
        WHERE u.is_active = 1 
        AND DATE(er.submitted_at) = CURDATE()
        AND (er.status = 'completed' OR er.status = 'submitted')
    ");
    $stats['activeToday'] = $result->fetch_assoc()['active_today'];
    
    // Advanced level students (students with percentage >= 75)
    $result = $conn->query("
        SELECT COUNT(DISTINCT er.student_id) as advanced_students
        FROM exam_results er
        INNER JOIN users u ON er.student_id = u.id
        WHERE u.is_active = 1 
        AND er.percentage >= 75
        AND (er.status = 'completed' OR er.status = 'submitted')
    ");
    $stats['advancedLevel'] = $result->fetch_assoc()['advanced_students'];
    
    // Total completed exams
    $result = $conn->query("
        SELECT COUNT(*) as total_completed
        FROM exam_results er
        INNER JOIN users u ON er.student_id = u.id
        WHERE u.is_active = 1 
        AND (er.status = 'completed' OR er.status = 'submitted')
    ");
    $stats['totalCompletedExams'] = $result->fetch_assoc()['total_completed'];
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
}

// Remove unused functions
// determineLevelByBatch() is no longer needed

// Remove calculateLastActive() as it's not used in simplified version

$conn->close();
?>

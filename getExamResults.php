<?php
/**
 * Get Exam Results API
 * Endpoint to fetch exam results for logged-in student
 * 
 * Usage from React:
 * fetch('http://localhost/getExamResults.php', {
 *     credentials: 'include'
 * })
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// CORS Configuration
$allowedOrigins = [
    'http://localhost:3000',
    'http://localhost:5173',
    'http://localhost:5174',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:5174'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Accept, Authorization");
}

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

startSecureSession();
header('Content-Type: application/json');

// Helper functions
function ok($message, $data = null) {
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

function fail($message, $code = 400, $data = null) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    fail('Not logged in', 401);
}

$studentId = intval($_SESSION['user_id']);
$userType = $_SESSION['user_type'] ?? '';

// Only students can view their results
if ($userType !== 'student') {
    fail('Only students can access exam results', 403);
}

$conn = getDBConnection();

try {
    // Get action
    $action = $_GET['action'] ?? 'getResults';
    
    switch ($action) {
        case 'getResults': {
            // Get all exam results for this student
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
            $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
            
            $sql = "SELECT 
                        id,
                        exam_id,
                        student_id,
                        score,
                        total_marks,
                        percentage,
                        time_taken,
                        status,
                        started_at,
                        submitted_at,
                        created_at
                    FROM exam_results 
                    WHERE student_id = ? 
                    ORDER BY submitted_at DESC
                    LIMIT ? OFFSET ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iii', $studentId, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $results = [];
            while ($row = $result->fetch_assoc()) {
                // Convert to proper types
                $results[] = [
                    'id' => intval($row['id']),
                    'examId' => $row['exam_id'] ? intval($row['exam_id']) : null,
                    'studentId' => intval($row['student_id']),
                    'score' => floatval($row['score']),
                    'totalMarks' => intval($row['total_marks']),
                    'percentage' => floatval($row['percentage']),
                    'timeTaken' => intval($row['time_taken']),
                    'status' => $row['status'],
                    'startedAt' => $row['started_at'],
                    'submittedAt' => $row['submitted_at'],
                    'createdAt' => $row['created_at']
                ];
            }
            
            $stmt->close();
            
            ok('Exam results retrieved', [
                'results' => $results,
                'count' => count($results)
            ]);
            break;
        }
        
        case 'getStatistics': {
            // Get statistics for this student
            $sql = "SELECT 
                        COUNT(*) as total_exams,
                        AVG(percentage) as avg_percentage,
                        MAX(percentage) as best_score,
                        MIN(percentage) as worst_score,
                        AVG(time_taken) as avg_time_seconds,
                        SUM(CASE WHEN percentage >= 75 THEN 1 ELSE 0 END) as exams_above_75,
                        SUM(CASE WHEN percentage >= 50 AND percentage < 75 THEN 1 ELSE 0 END) as exams_50_to_75,
                        SUM(CASE WHEN percentage < 50 THEN 1 ELSE 0 END) as exams_below_50
                    FROM exam_results 
                    WHERE student_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $studentId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats = $result->fetch_assoc();
            $stmt->close();
            
            ok('Statistics retrieved', [
                'totalExams' => intval($stats['total_exams']),
                'averagePercentage' => round(floatval($stats['avg_percentage']), 2),
                'bestScore' => round(floatval($stats['best_score']), 2),
                'worstScore' => round(floatval($stats['worst_score']), 2),
                'averageTimeMinutes' => round(floatval($stats['avg_time_seconds']) / 60, 1),
                'distribution' => [
                    'excellent' => intval($stats['exams_above_75']), // >= 75%
                    'good' => intval($stats['exams_50_to_75']),      // 50-74%
                    'needsImprovement' => intval($stats['exams_below_50']) // < 50%
                ]
            ]);
            break;
        }
        
        case 'getResult': {
            // Get a specific exam result by ID
            $resultId = isset($_GET['id']) ? intval($_GET['id']) : null;
            if (!$resultId) {
                fail('Result ID is required');
            }
            
            $sql = "SELECT * FROM exam_results WHERE id = ? AND student_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $resultId, $studentId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $data = [
                    'id' => intval($row['id']),
                    'examId' => $row['exam_id'] ? intval($row['exam_id']) : null,
                    'studentId' => intval($row['student_id']),
                    'score' => floatval($row['score']),
                    'totalMarks' => intval($row['total_marks']),
                    'percentage' => floatval($row['percentage']),
                    'timeTaken' => intval($row['time_taken']),
                    'status' => $row['status'],
                    'startedAt' => $row['started_at'],
                    'submittedAt' => $row['submitted_at'],
                    'createdAt' => $row['created_at']
                ];
                ok('Result retrieved', $data);
            } else {
                fail('Result not found', 404);
            }
            
            $stmt->close();
            break;
        }
        
        case 'getRecentResults': {
            // Get recent results (last 5 by default)
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
            
            $sql = "SELECT * FROM exam_results 
                    WHERE student_id = ? 
                    ORDER BY submitted_at DESC 
                    LIMIT ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $studentId, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $results = [];
            while ($row = $result->fetch_assoc()) {
                $results[] = [
                    'id' => intval($row['id']),
                    'examId' => $row['exam_id'] ? intval($row['exam_id']) : null,
                    'score' => floatval($row['score']),
                    'totalMarks' => intval($row['total_marks']),
                    'percentage' => floatval($row['percentage']),
                    'timeTaken' => intval($row['time_taken']),
                    'status' => $row['status'],
                    'submittedAt' => $row['submitted_at']
                ];
            }
            
            $stmt->close();
            
            ok('Recent results retrieved', [
                'results' => $results,
                'count' => count($results)
            ]);
            break;
        }
        
        default:
            fail('Unknown action');
    }
    
} catch (Exception $e) {
    fail('Server error: ' . $e->getMessage(), 500);
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>

<?php
/**
 * Student Exam Results Viewer API
 * View exam results for students
 */

header('Content-Type: application/json');
require_once 'config/database.php';
require_once 'includes/functions.php';

startSecureSession();

$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $response['message'] = 'Invalid request method. Only GET is allowed.';
    echo json_encode($response);
    exit();
}

$conn = getDBConnection();
$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Get all results or filter by student_id
            $studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
            
            $sql = "SELECT 
                        er.*,
                        s.first_name,
                        s.last_name,
                        s.email,
                        s.batch_number
                    FROM exam_results er
                    LEFT JOIN students s ON er.student_id = s.id";
            
            if ($studentId) {
                $sql .= " WHERE er.student_id = ?";
            }
            
            $sql .= " ORDER BY er.created_at DESC LIMIT ?";
            
            $stmt = $conn->prepare($sql);
            if ($studentId) {
                $stmt->bind_param('ii', $studentId, $limit);
            } else {
                $stmt->bind_param('i', $limit);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $results = [];
            while ($row = $result->fetch_assoc()) {
                $results[] = [
                    'id' => $row['id'],
                    'exam_id' => $row['exam_id'],
                    'student' => [
                        'id' => $row['student_id'],
                        'name' => ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''),
                        'email' => $row['email'] ?? '',
                        'batch' => $row['batch_number'] ?? ''
                    ],
                    'score' => floatval($row['score']),
                    'total_marks' => intval($row['total_marks']),
                    'percentage' => floatval($row['percentage']),
                    'time_taken' => $row['time_taken'],
                    'status' => $row['status'],
                    'started_at' => $row['started_at'],
                    'submitted_at' => $row['submitted_at'],
                    'created_at' => $row['created_at']
                ];
            }
            
            $response['success'] = true;
            $response['message'] = 'Results retrieved successfully';
            $response['data'] = $results;
            $response['count'] = count($results);
            break;
            
        case 'stats':
            // Get overall statistics
            $sql = "SELECT 
                        COUNT(*) as total_attempts,
                        AVG(percentage) as avg_percentage,
                        MAX(percentage) as max_percentage,
                        MIN(percentage) as min_percentage,
                        AVG(time_taken) as avg_time_taken
                    FROM exam_results";
            
            $result = $conn->query($sql);
            $stats = $result->fetch_assoc();
            
            // Get stats by batch
            $batchSql = "SELECT 
                            s.batch_number,
                            COUNT(*) as attempts,
                            AVG(er.percentage) as avg_percentage
                        FROM exam_results er
                        JOIN students s ON er.student_id = s.id
                        WHERE s.batch_number IS NOT NULL
                        GROUP BY s.batch_number
                        ORDER BY avg_percentage DESC";
            
            $batchResult = $conn->query($batchSql);
            $batchStats = [];
            while ($row = $batchResult->fetch_assoc()) {
                $batchStats[] = [
                    'batch' => $row['batch_number'],
                    'attempts' => intval($row['attempts']),
                    'average_percentage' => round(floatval($row['avg_percentage']), 2)
                ];
            }
            
            $response['success'] = true;
            $response['message'] = 'Statistics retrieved successfully';
            $response['data'] = [
                'overall' => [
                    'total_attempts' => intval($stats['total_attempts']),
                    'average_percentage' => round(floatval($stats['avg_percentage'] ?? 0), 2),
                    'max_percentage' => round(floatval($stats['max_percentage'] ?? 0), 2),
                    'min_percentage' => round(floatval($stats['min_percentage'] ?? 0), 2),
                    'average_time_taken' => intval($stats['avg_time_taken'] ?? 0)
                ],
                'by_batch' => $batchStats
            ];
            break;
            
        case 'student':
            // Get specific student's performance
            $studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
            
            if (!$studentId) {
                throw new Exception('student_id is required');
            }
            
            // Get student info
            $studentSql = "SELECT id, first_name, last_name, email, batch_number FROM students WHERE id = ?";
            $stmt = $conn->prepare($studentSql);
            $stmt->bind_param('i', $studentId);
            $stmt->execute();
            $studentResult = $stmt->get_result();
            
            if ($studentResult->num_rows === 0) {
                throw new Exception('Student not found');
            }
            
            $student = $studentResult->fetch_assoc();
            
            // Get student's results
            $resultsSql = "SELECT * FROM exam_results WHERE student_id = ? ORDER BY submitted_at DESC";
            $stmt2 = $conn->prepare($resultsSql);
            $stmt2->bind_param('i', $studentId);
            $stmt2->execute();
            $resultsData = $stmt2->get_result();
            
            $attempts = [];
            $totalPercentage = 0;
            while ($row = $resultsData->fetch_assoc()) {
                $attempts[] = [
                    'id' => $row['id'],
                    'exam_id' => $row['exam_id'],
                    'score' => floatval($row['score']),
                    'total_marks' => intval($row['total_marks']),
                    'percentage' => floatval($row['percentage']),
                    'time_taken' => $row['time_taken'],
                    'submitted_at' => $row['submitted_at']
                ];
                $totalPercentage += floatval($row['percentage']);
            }
            
            $response['success'] = true;
            $response['message'] = 'Student performance retrieved successfully';
            $response['data'] = [
                'student' => [
                    'id' => $student['id'],
                    'name' => $student['first_name'] . ' ' . $student['last_name'],
                    'email' => $student['email'],
                    'batch' => $student['batch_number']
                ],
                'performance' => [
                    'total_attempts' => count($attempts),
                    'average_percentage' => count($attempts) > 0 ? round($totalPercentage / count($attempts), 2) : 0,
                    'best_score' => count($attempts) > 0 ? max(array_column($attempts, 'percentage')) : 0
                ],
                'attempts' => $attempts
            ];
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(400);
}

$conn->close();
echo json_encode($response);

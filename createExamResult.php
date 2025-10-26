<?php
/**
 * Create Exam Result API
 * POST endpoint to manually create/insert exam results for testing
 */

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config/database.php';

function sendResponse($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST method is allowed', null, 405);
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, 'Invalid JSON format', null, 400);
    }
    
    // Validate required fields
    $required = ['student_id', 'score', 'total_marks'];
    $missing = [];
    
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        sendResponse(false, 'Missing required fields: ' . implode(', ', $missing), null, 400);
    }
    
    // Extract and validate data
    $student_id = intval($data['student_id']);
    $exam_id = isset($data['exam_id']) && $data['exam_id'] !== '' ? intval($data['exam_id']) : null;
    $score = floatval($data['score']);
    $total_marks = intval($data['total_marks']);
    $time_taken = isset($data['time_taken']) ? intval($data['time_taken']) : 0;
    
    // Valid status values: pending, in_progress, completed, submitted
    $validStatuses = ['pending', 'in_progress', 'completed', 'submitted'];
    $requestedStatus = isset($data['status']) ? strtolower($data['status']) : 'submitted';
    $status = in_array($requestedStatus, $validStatuses) ? $requestedStatus : 'submitted';
    
    // Validate score
    if ($score < 0 || $score > $total_marks) {
        sendResponse(false, 'Score cannot be negative or greater than total marks', null, 400);
    }
    
    // Calculate percentage
    $percentage = $total_marks > 0 ? ($score / $total_marks) * 100 : 0;
    
    // Set timestamps
    $started_at = isset($data['started_at']) ? $data['started_at'] : date('Y-m-d H:i:s', strtotime('-' . ($time_taken + 60) . ' seconds'));
    $submitted_at = isset($data['submitted_at']) ? $data['submitted_at'] : date('Y-m-d H:i:s');
    
    // Validate student exists
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE id = ?");
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendResponse(false, "Student with ID $student_id not found", null, 404);
    }
    
    $student = $result->fetch_assoc();
    
    // Validate exam exists (if exam_id provided)
    if ($exam_id !== null) {
        $stmt = $conn->prepare("SELECT id, title FROM exams WHERE id = ?");
        $stmt->bind_param('i', $exam_id);
        $stmt->execute();
        $examResult = $stmt->get_result();
        
        if ($examResult->num_rows === 0) {
            sendResponse(false, "Exam with ID $exam_id not found", null, 404);
        }
        
        $exam = $examResult->fetch_assoc();
    }
    
    // Insert exam result
    $sql = "INSERT INTO exam_results 
            (exam_id, student_id, score, total_marks, percentage, time_taken, status, started_at, submitted_at, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'iididisss',
        $exam_id,
        $student_id,
        $score,
        $total_marks,
        $percentage,
        $time_taken,
        $status,
        $started_at,
        $submitted_at
    );
    
    if ($stmt->execute()) {
        $result_id = $conn->insert_id;
        
        // Fetch the created result
        $stmt = $conn->prepare("SELECT * FROM exam_results WHERE id = ?");
        $stmt->bind_param('i', $result_id);
        $stmt->execute();
        $createdResult = $stmt->get_result()->fetch_assoc();
        
        sendResponse(true, 'Exam result created successfully', [
            'result_id' => $result_id,
            'exam_id' => $exam_id ?? 'Mock Exam',
            'exam_title' => isset($exam) ? $exam['title'] : 'Mock/Practice Exam',
            'student' => [
                'id' => $student_id,
                'name' => trim($student['first_name'] . ' ' . $student['last_name'])
            ],
            'score' => $score . ' / ' . $total_marks,
            'percentage' => round($percentage, 2) . '%',
            'time_taken' => gmdate('H:i:s', $time_taken),
            'status' => $status,
            'started_at' => $createdResult['started_at'],
            'submitted_at' => $createdResult['submitted_at'],
            'created_at' => $createdResult['created_at']
        ], 201);
        
    } else {
        sendResponse(false, 'Failed to create exam result: ' . $stmt->error, null, 500);
    }
    
} catch (Exception $e) {
    sendResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>

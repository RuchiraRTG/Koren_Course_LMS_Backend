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
initializeExamTables($conn);

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

// Initialize database tables
function initializeExamTables($conn) {
    // Create exams table
    $sql = "CREATE TABLE IF NOT EXISTS exams (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        exam_name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        exam_type ENUM('mcq', 'voice', 'both') NOT NULL DEFAULT 'both',
        duration INT(11) NOT NULL COMMENT 'Duration in minutes',
        number_of_questions INT(11) NOT NULL,
        total_marks INT(11) NOT NULL,
        eligibility_type ENUM('batch', 'individual') NOT NULL DEFAULT 'batch',
        selected_batch VARCHAR(100) NULL COMMENT 'Batch name if eligibility_type is batch',
        mcq_count INT(11) DEFAULT 0,
        voice_count INT(11) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active TINYINT(1) DEFAULT 1,
        INDEX idx_exam_type (exam_type),
        INDEX idx_eligibility_type (eligibility_type),
        INDEX idx_batch (selected_batch),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($sql);

    // Create exam_questions junction table
    $sql = "CREATE TABLE IF NOT EXISTS exam_questions (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        exam_id INT(11) UNSIGNED NOT NULL,
        question_id INT(11) UNSIGNED NOT NULL,
        question_order INT(11) DEFAULT 0 COMMENT 'Order of question in exam',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_exam (exam_id),
        INDEX idx_question (question_id),
        UNIQUE KEY unique_exam_question (exam_id, question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($sql);

    // Create exam_student_assignments junction table
    $sql = "CREATE TABLE IF NOT EXISTS exam_student_assignments (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        exam_id INT(11) UNSIGNED NOT NULL,
        student_id INT(11) UNSIGNED NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_exam (exam_id),
        INDEX idx_student (student_id),
        UNIQUE KEY unique_exam_student (exam_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($sql);

    // Ensure users table has batch column
    ensureBatchColumn($conn);
}

// Ensure batch column exists in users table
function ensureBatchColumn($conn) {
    $dbName = DB_NAME;
    $checkSql = "SELECT COUNT(*) AS cnt FROM information_schema.columns 
                 WHERE table_schema = ? AND table_name = 'users' AND column_name = 'batch'";
    
    if ($stmt = $conn->prepare($checkSql)) {
        $stmt->bind_param('s', $dbName);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res && ($row = $res->fetch_assoc())) {
            if ((int)$row['cnt'] === 0) {
                $alter = "ALTER TABLE users ADD COLUMN batch VARCHAR(100) NULL AFTER role";
                $conn->query($alter);
            }
        }
        $stmt->close();
    }
}

// ========================================
// GET HANDLERS
// ========================================
function handleGet($conn, $action) {
    switch ($action) {
        case 'all':
            getAllExams($conn);
            break;
        case 'single':
            getSingleExam($conn);
            break;
        case 'students':
            getAllStudents($conn);
            break;
        case 'batches':
            getAllBatches($conn);
            break;
        case 'questions':
            getAllQuestions($conn);
            break;
        case 'exam-students':
            getExamStudents($conn);
            break;
        case 'exam-questions':
            getExamQuestions($conn);
            break;
        default:
            getAllExams($conn);
            break;
    }
}

// Get all exams with their details
function getAllExams($conn) {
    try {
        $sql = "SELECT 
                    e.*,
                    (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) as total_questions_assigned,
                    (SELECT COUNT(*) FROM exam_student_assignments esa WHERE esa.exam_id = e.id) as total_students_assigned
                FROM exams e
                WHERE e.is_active = 1
                ORDER BY e.created_at DESC";
        
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception($conn->error);
        }
        
        $exams = [];
        while ($row = $result->fetch_assoc()) {
            // Transform snake_case to camelCase for frontend compatibility
            $exam = [
                'id' => $row['id'],
                'examName' => $row['exam_name'],
                'description' => $row['description'],
                'examType' => $row['exam_type'],
                'duration' => $row['duration'],
                'numberOfQuestions' => $row['number_of_questions'],
                'totalMarks' => $row['total_marks'],
                'eligibilityType' => $row['eligibility_type'],
                'selectedBatch' => $row['selected_batch'],
                'mcqCount' => $row['mcq_count'],
                'voiceCount' => $row['voice_count'],
                'createdAt' => $row['created_at'],
                'updatedAt' => $row['updated_at'],
                'isActive' => $row['is_active'],
                'totalQuestionsAssigned' => $row['total_questions_assigned'],
                'totalStudentsAssigned' => $row['total_students_assigned']
            ];
            
            // Get assigned students for this exam
            if ($row['eligibility_type'] === 'individual') {
                $exam['assignedStudents'] = getAssignedStudentIds($conn, $row['id']);
            }
            
            // Get assigned questions for this exam
            $exam['assignedQuestions'] = getAssignedQuestionIds($conn, $row['id']);
            
            $exams[] = $exam;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $exams,
            'count' => count($exams)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching exams: ' . $e->getMessage()
        ]);
    }
}

// Get single exam by ID
function getSingleExam($conn) {
    try {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($id <= 0) {
            throw new Exception('Invalid exam ID');
        }
        
        $sql = "SELECT e.* FROM exams e WHERE e.id = ? AND e.is_active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Exam not found'
            ]);
            return;
        }
        
        $row = $result->fetch_assoc();
        
        // Transform snake_case to camelCase for frontend compatibility
        $exam = [
            'id' => $row['id'],
            'examName' => $row['exam_name'],
            'description' => $row['description'],
            'examType' => $row['exam_type'],
            'duration' => $row['duration'],
            'numberOfQuestions' => $row['number_of_questions'],
            'totalMarks' => $row['total_marks'],
            'eligibilityType' => $row['eligibility_type'],
            'selectedBatch' => $row['selected_batch'],
            'mcqCount' => $row['mcq_count'],
            'voiceCount' => $row['voice_count'],
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
            'isActive' => $row['is_active']
        ];
        
        // Get assigned students
        if ($row['eligibility_type'] === 'individual') {
            $exam['assignedStudents'] = getAssignedStudentIds($conn, $row['id']);
        }
        
        // Get assigned questions
        $exam['assignedQuestions'] = getAssignedQuestionIds($conn, $row['id']);
        
        echo json_encode([
            'success' => true,
            'data' => $exam
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching exam: ' . $e->getMessage()
        ]);
    }
}

// Get all students (users with role 'user' or 'student')
function getAllStudents($conn) {
    try {
        $sql = "SELECT 
                    id,
                    CONCAT(first_name, ' ', last_name) as name,
                    first_name,
                    last_name,
                    email,
                    batch,
                    role
                FROM users 
                WHERE is_active = 1 
                AND (role = 'user' OR role = 'student')
                ORDER BY first_name, last_name";
        
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception($conn->error);
        }
        
        $students = [];
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $students,
            'count' => count($students)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching students: ' . $e->getMessage()
        ]);
    }
}

// Get all unique batches
function getAllBatches($conn) {
    try {
        $sql = "SELECT DISTINCT batch 
                FROM users 
                WHERE batch IS NOT NULL 
                AND batch != '' 
                AND is_active = 1
                ORDER BY batch";
        
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception($conn->error);
        }
        
        $batches = [];
        while ($row = $result->fetch_assoc()) {
            $batches[] = $row['batch'];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $batches,
            'count' => count($batches)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching batches: ' . $e->getMessage()
        ]);
    }
}

// Get all questions from questions table
function getAllQuestions($conn) {
    try {
        // Support filtering by exam type
        $examType = isset($_GET['exam_type']) ? $_GET['exam_type'] : '';
        
        $sql = "SELECT 
                    id,
                    question_text,
                    question_type,
                    question_format,
                    question_image,
                    answer_type,
                    audio_link,
                    difficulty,
                    category,
                    time_limit,
                    created_at
                FROM questions 
                WHERE is_active = 1";
        
        // Filter by question type if exam_type is specified
        if ($examType === 'mcq') {
            $sql .= " AND question_type = 'mcq'";
        } elseif ($examType === 'voice') {
            $sql .= " AND question_type = 'voice'";
        }
        // If examType is 'both' or not specified, show all questions
        
        $sql .= " ORDER BY category, difficulty, created_at DESC";
        
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception($conn->error);
        }
        
        $questions = [];
        while ($row = $result->fetch_assoc()) {
            // Transform snake_case to camelCase for frontend compatibility
            $question = [
                'id' => $row['id'],
                'questionText' => $row['question_text'],
                'questionType' => $row['question_type'],
                'questionFormat' => $row['question_format'],
                'questionImage' => $row['question_image'],
                'answerType' => $row['answer_type'],
                'audioLink' => $row['audio_link'],
                'difficulty' => $row['difficulty'],
                'category' => $row['category'],
                'timeLimit' => $row['time_limit'],
                'createdAt' => $row['created_at']
            ];
            $questions[] = $question;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $questions,
            'count' => count($questions)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching questions: ' . $e->getMessage()
        ]);
    }
}

// Get students assigned to an exam
function getExamStudents($conn) {
    try {
        $examId = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
        
        if ($examId <= 0) {
            throw new Exception('Invalid exam ID');
        }
        
        $sql = "SELECT 
                    u.id,
                    CONCAT(u.first_name, ' ', u.last_name) as name,
                    u.email,
                    u.batch,
                    esa.assigned_at
                FROM exam_student_assignments esa
                JOIN users u ON esa.student_id = u.id
                WHERE esa.exam_id = ?
                ORDER BY u.first_name, u.last_name";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $examId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $students = [];
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $students,
            'count' => count($students)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching exam students: ' . $e->getMessage()
        ]);
    }
}

// Get questions assigned to an exam
function getExamQuestions($conn) {
    try {
        $examId = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
        
        if ($examId <= 0) {
            throw new Exception('Invalid exam ID');
        }
        
        $sql = "SELECT 
                    q.*,
                    eq.question_order
                FROM exam_questions eq
                JOIN questions q ON eq.question_id = q.id
                WHERE eq.exam_id = ?
                ORDER BY eq.question_order, eq.id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $examId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $questions = [];
        while ($row = $result->fetch_assoc()) {
            // Transform snake_case to camelCase for frontend compatibility
            $question = [
                'id' => $row['id'],
                'questionText' => $row['question_text'],
                'questionType' => $row['question_type'],
                'questionFormat' => $row['question_format'],
                'questionImage' => $row['question_image'],
                'answerType' => $row['answer_type'],
                'audioLink' => $row['audio_link'],
                'difficulty' => $row['difficulty'],
                'category' => $row['category'],
                'timeLimit' => $row['time_limit'],
                'createdAt' => $row['created_at'],
                'updatedAt' => $row['updated_at'],
                'isActive' => $row['is_active'],
                'questionOrder' => $row['question_order']
            ];
            $questions[] = $question;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $questions,
            'count' => count($questions)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching exam questions: ' . $e->getMessage()
        ]);
    }
}

// ========================================
// POST HANDLER - Create Exam
// ========================================
function handlePost($conn) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        // Validate required fields
        $required = ['examName', 'examType', 'duration', 'numberOfQuestions', 'totalMarks', 'eligibilityType'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || trim($input[$field]) === '') {
                throw new Exception("Field '$field' is required");
            }
        }
        
        // Validate eligibility-specific fields
        if ($input['eligibilityType'] === 'batch' && empty($input['selectedBatch'])) {
            throw new Exception('Batch selection is required when eligibility type is batch');
        }
        
        if ($input['eligibilityType'] === 'individual' && empty($input['selectedStudents'])) {
            throw new Exception('At least one student must be selected when eligibility type is individual');
        }
        
        // Validate questions
        if (empty($input['selectedQuestions'])) {
            throw new Exception('At least one question must be selected');
        }
        
        if (count($input['selectedQuestions']) > intval($input['numberOfQuestions'])) {
            throw new Exception('Number of selected questions exceeds the allowed limit');
        }
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Insert exam
            $sql = "INSERT INTO exams (
                        exam_name, description, exam_type, duration, 
                        number_of_questions, total_marks, eligibility_type, 
                        selected_batch, mcq_count, voice_count
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            $examName = $input['examName'];
            $description = $input['description'] ?? null;
            $examType = $input['examType'];
            $duration = intval($input['duration']);
            $numberOfQuestions = intval($input['numberOfQuestions']);
            $totalMarks = intval($input['totalMarks']);
            $eligibilityType = $input['eligibilityType'];
            $selectedBatch = $eligibilityType === 'batch' ? $input['selectedBatch'] : null;
            $mcqCount = intval($input['mcqCount'] ?? 0);
            $voiceCount = intval($input['voiceCount'] ?? 0);
            
            $stmt->bind_param(
                'sssiiissii',
                $examName,
                $description,
                $examType,
                $duration,
                $numberOfQuestions,
                $totalMarks,
                $eligibilityType,
                $selectedBatch,
                $mcqCount,
                $voiceCount
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to create exam: ' . $stmt->error);
            }
            
            $examId = $conn->insert_id;
            
            // Insert exam questions
            if (!empty($input['selectedQuestions'])) {
                insertExamQuestions($conn, $examId, $input['selectedQuestions']);
            }
            
            // Insert student assignments for individual eligibility
            if ($eligibilityType === 'individual' && !empty($input['selectedStudents'])) {
                insertStudentAssignments($conn, $examId, $input['selectedStudents']);
            }
            
            // Commit transaction
            $conn->commit();
            
            // Fetch the created exam
            $createdExam = getExamById($conn, $examId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Exam created successfully',
                'data' => $createdExam
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

// ========================================
// PUT HANDLER - Update Exam
// ========================================
function handlePut($conn) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        if (!isset($input['id']) || intval($input['id']) <= 0) {
            throw new Exception('Valid exam ID is required');
        }
        
        $examId = intval($input['id']);
        
        // Check if exam exists
        $checkSql = "SELECT id FROM exams WHERE id = ? AND is_active = 1";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('i', $examId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            http_response_code(404);
            throw new Exception('Exam not found');
        }
        
        // Validate required fields
        $required = ['examName', 'examType', 'duration', 'numberOfQuestions', 'totalMarks', 'eligibilityType'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || trim($input[$field]) === '') {
                throw new Exception("Field '$field' is required");
            }
        }
        
        // Validate eligibility-specific fields
        if ($input['eligibilityType'] === 'batch' && empty($input['selectedBatch'])) {
            throw new Exception('Batch selection is required when eligibility type is batch');
        }
        
        if ($input['eligibilityType'] === 'individual' && empty($input['selectedStudents'])) {
            throw new Exception('At least one student must be selected when eligibility type is individual');
        }
        
        // Validate questions
        if (empty($input['selectedQuestions'])) {
            throw new Exception('At least one question must be selected');
        }
        
        if (count($input['selectedQuestions']) > intval($input['numberOfQuestions'])) {
            throw new Exception('Number of selected questions exceeds the allowed limit');
        }
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update exam
            $sql = "UPDATE exams SET 
                        exam_name = ?,
                        description = ?,
                        exam_type = ?,
                        duration = ?,
                        number_of_questions = ?,
                        total_marks = ?,
                        eligibility_type = ?,
                        selected_batch = ?,
                        mcq_count = ?,
                        voice_count = ?
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            
            $examName = $input['examName'];
            $description = $input['description'] ?? null;
            $examType = $input['examType'];
            $duration = intval($input['duration']);
            $numberOfQuestions = intval($input['numberOfQuestions']);
            $totalMarks = intval($input['totalMarks']);
            $eligibilityType = $input['eligibilityType'];
            $selectedBatch = $eligibilityType === 'batch' ? $input['selectedBatch'] : null;
            $mcqCount = intval($input['mcqCount'] ?? 0);
            $voiceCount = intval($input['voiceCount'] ?? 0);
            
            $stmt->bind_param(
                'sssiiissioi',
                $examName,
                $description,
                $examType,
                $duration,
                $numberOfQuestions,
                $totalMarks,
                $eligibilityType,
                $selectedBatch,
                $mcqCount,
                $voiceCount,
                $examId
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update exam: ' . $stmt->error);
            }
            
            // Delete existing exam questions and student assignments
            $deleteSql = "DELETE FROM exam_questions WHERE exam_id = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->bind_param('i', $examId);
            $deleteStmt->execute();
            
            $deleteSql = "DELETE FROM exam_student_assignments WHERE exam_id = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->bind_param('i', $examId);
            $deleteStmt->execute();
            
            // Insert updated exam questions
            if (!empty($input['selectedQuestions'])) {
                insertExamQuestions($conn, $examId, $input['selectedQuestions']);
            }
            
            // Insert updated student assignments for individual eligibility
            if ($eligibilityType === 'individual' && !empty($input['selectedStudents'])) {
                insertStudentAssignments($conn, $examId, $input['selectedStudents']);
            }
            
            // Commit transaction
            $conn->commit();
            
            // Fetch the updated exam
            $updatedExam = getExamById($conn, $examId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Exam updated successfully',
                'data' => $updatedExam
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

// ========================================
// DELETE HANDLER - Delete Exam
// ========================================
function handleDelete($conn) {
    try {
        // Support both query parameter and JSON body
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($id <= 0) {
            $input = json_decode(file_get_contents('php://input'), true);
            $id = isset($input['id']) ? intval($input['id']) : 0;
        }
        
        if ($id <= 0) {
            throw new Exception('Valid exam ID is required');
        }
        
        // Check if exam exists
        $checkSql = "SELECT id FROM exams WHERE id = ? AND is_active = 1";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('i', $id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            http_response_code(404);
            throw new Exception('Exam not found');
        }
        
        // Soft delete (set is_active to 0)
        $sql = "UPDATE exams SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete exam: ' . $stmt->error);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Exam deleted successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

// ========================================
// HELPER FUNCTIONS
// ========================================

// Insert exam questions
function insertExamQuestions($conn, $examId, $questionIds) {
    $sql = "INSERT INTO exam_questions (exam_id, question_id, question_order) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    $order = 0;
    foreach ($questionIds as $questionId) {
        $stmt->bind_param('iii', $examId, $questionId, $order);
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert exam question: ' . $stmt->error);
        }
        $order++;
    }
}

// Insert student assignments
function insertStudentAssignments($conn, $examId, $studentIds) {
    $sql = "INSERT INTO exam_student_assignments (exam_id, student_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    
    foreach ($studentIds as $studentId) {
        $stmt->bind_param('ii', $examId, $studentId);
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert student assignment: ' . $stmt->error);
        }
    }
}

// Get exam by ID
function getExamById($conn, $examId) {
    $sql = "SELECT * FROM exams WHERE id = ? AND is_active = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $examId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $exam = $result->fetch_assoc();
    
    // Get assigned students
    if ($exam['eligibility_type'] === 'individual') {
        $exam['assigned_students'] = getAssignedStudentIds($conn, $exam['id']);
    }
    
    // Get assigned questions
    $exam['assigned_questions'] = getAssignedQuestionIds($conn, $exam['id']);
    
    return $exam;
}

// Get assigned student IDs for an exam
function getAssignedStudentIds($conn, $examId) {
    $sql = "SELECT student_id FROM exam_student_assignments WHERE exam_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $examId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $studentIds = [];
    while ($row = $result->fetch_assoc()) {
        $studentIds[] = intval($row['student_id']);
    }
    
    return $studentIds;
}

// Get assigned question IDs for an exam
function getAssignedQuestionIds($conn, $examId) {
    $sql = "SELECT question_id FROM exam_questions WHERE exam_id = ? ORDER BY question_order";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $examId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $questionIds = [];
    while ($row = $result->fetch_assoc()) {
        $questionIds[] = intval($row['question_id']);
    }
    
    return $questionIds;
}

$conn->close();
?>

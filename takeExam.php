<?php
// takeExam.php — Backend for Mock/Take Exam flows (single file API)
// Actions: startExam, fetchQuestions, submitAnswers

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// CORS Configuration for React Frontend
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

// Small helper: unified JSON out
function ok($message, $data = null) { sendJsonResponse(true, $message, $data); }
function fail($message, $code = 400, $data = null) {
    http_response_code($code);
    sendJsonResponse(false, $message, $data);
}

// Ensure persistence table for user answers
function ensureUserAnswersTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS useranswers (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        attempt_token VARCHAR(64) NOT NULL,
        user_id INT(11) UNSIGNED NULL,
        exam_type ENUM('mcq','voice','both') NOT NULL,
        question_id INT(11) UNSIGNED NOT NULL,
        selected_index INT(11) NULL,
        selected_text TEXT NULL,
        is_correct TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_attempt (attempt_token),
        INDEX idx_user (user_id),
        INDEX idx_question (question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
}

function getParam($name, $default = null) {
    // Read from JSON body first, then query string
    static $json;
    if ($json === null) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (!is_array($json)) { $json = []; }
    }
    if (array_key_exists($name, $json)) return $json[$name];
    if (isset($_POST[$name])) return $_POST[$name];
    if (isset($_GET[$name])) return $_GET[$name];
    return $default;
}

function randToken($len = 32) { return bin2hex(random_bytes($len/2)); }

// Helper function to convert image path to full URL
function getImageUrl($imagePath) {
    if (empty($imagePath) || $imagePath === null) {
        return null;
    }
    
    // If it's already a full URL, return it
    if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
        return $imagePath;
    }
    
    // Get the protocol and host
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Return full URL
    return $protocol . '://' . $host . '/' . $imagePath;
}

function mapQuestionRow($row) {
    return [
        'id' => intval($row['id']),
        'questionType' => $row['questionType'],
        'questionText' => $row['questionText'],
        'questionFormat' => $row['questionFormat'],
        'questionImage' => getImageUrl($row['questionImage']),
        'answerType' => $row['answerType'],
        'audioLink' => $row['audioLink'],
        'difficulty' => $row['difficulty'],
        'category' => $row['category'],
        'timeLimit' => isset($row['timeLimit']) ? (is_numeric($row['timeLimit']) ? intval($row['timeLimit']) : null) : null,
    ];
}

// Schema helpers
function columnExists($conn, $table, $column) {
    $db = DB_NAME;
    $sql = "SELECT COUNT(*) AS cnt FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $db, $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = false;
    if ($row = $res->fetch_assoc()) { $exists = intval($row['cnt']) > 0; }
    $stmt->close();
    return $exists;
}

function detectSchemaMap($conn) {
    // questions table
    $qType = columnExists($conn, 'questions', 'question_type') ? 'question_type' : (columnExists($conn, 'questions', 'questionType') ? 'questionType' : 'question_type');
    $qText = columnExists($conn, 'questions', 'question_text') ? 'question_text' : (columnExists($conn, 'questions', 'questionText') ? 'questionText' : 'question_text');
    $qFormat = columnExists($conn, 'questions', 'question_format') ? 'question_format' : (columnExists($conn, 'questions', 'questionFormat') ? 'questionFormat' : 'question_format');
    $qImage = columnExists($conn, 'questions', 'question_image') ? 'question_image' : (columnExists($conn, 'questions', 'questionImage') ? 'questionImage' : 'question_image');
    $qAnsType = columnExists($conn, 'questions', 'answer_type') ? 'answer_type' : (columnExists($conn, 'questions', 'answerType') ? 'answerType' : 'answer_type');
    $qAudio = columnExists($conn, 'questions', 'audio_link') ? 'audio_link' : (columnExists($conn, 'questions', 'audioLink') ? 'audioLink' : 'audio_link');
    $qDiff = 'difficulty';
    $qCat = 'category';
    $qTime = columnExists($conn, 'questions', 'time_limit') ? 'time_limit' : (columnExists($conn, 'questions', 'timeLimit') ? 'timeLimit' : 'time_limit');
    $qActive = columnExists($conn, 'questions', 'is_active') ? 'is_active' : (columnExists($conn, 'questions', 'isActive') ? 'isActive' : 'is_active');

    // question_options table
    $oText = columnExists($conn, 'question_options', 'option_text') ? 'option_text' : (columnExists($conn, 'question_options', 'text') ? 'text' : 'option_text');
    $oImage = columnExists($conn, 'question_options', 'option_image') ? 'option_image' : (columnExists($conn, 'question_options', 'image') ? 'image' : 'option_image');
    $oOrder = 'option_order';

    return [
        'questions' => [
            'type' => $qType,
            'text' => $qText,
            'format' => $qFormat,
            'image' => $qImage,
            'answerType' => $qAnsType,
            'audio' => $qAudio,
            'difficulty' => $qDiff,
            'category' => $qCat,
            'time' => $qTime,
            'active' => $qActive,
        ],
        'options' => [
            'text' => $oText,
            'image' => $oImage,
            'order' => $oOrder,
        ]
    ];
}

function getQuestionOptions($conn, $questionId, $schema = null) {
    if ($schema === null) { $schema = detectSchemaMap($conn); }
    $ot = $schema['options']['text'];
    $oi = $schema['options']['image'];
    $oo = $schema['options']['order'];
    $sql = "SELECT $ot AS option_text, $oi AS option_image, $oo AS option_order FROM question_options WHERE question_id = ? ORDER BY $oo ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $questionId);
    $stmt->execute();
    $res = $stmt->get_result();
    $options = [];
    while ($r = $res->fetch_assoc()) {
        $options[] = [ 
            'text' => $r['option_text'], 
            'image' => getImageUrl($r['option_image'])
        ];
    }
    $stmt->close();
    return $options;
}

function fetchRandomQuestions($conn, $type, $limit, $category = null) {
    $schema = detectSchemaMap($conn);
    $q = $schema['questions'];
    // Build base query with proper columns
    $sql = "SELECT id, {$q['type']} AS questionType, {$q['text']} AS questionText, {$q['format']} AS questionFormat, {$q['image']} AS questionImage, {$q['answerType']} AS answerType, {$q['audio']} AS audioLink, {$q['difficulty']} AS difficulty, {$q['category']} AS category, {$q['time']} AS timeLimit FROM questions WHERE {$q['active']} = 1 AND {$q['type']} = ?";
    $params = [$type];
    $types = 's';
    if (!empty($category)) {
        $sql .= " AND {$q['category']} = ?";
        $params[] = $category;
        $types .= 's';
    }
    $sql .= " ORDER BY RAND() LIMIT ?";
    $params[] = $limit;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $questions = [];
    while ($row = $res->fetch_assoc()) {
        $qMapped = mapQuestionRow($row);
        $qMapped['options'] = getQuestionOptions($conn, $row['id'], $schema);
        $questions[] = $qMapped;
    }
    $stmt->close();
    return $questions;
}

function fetchBothTypeQuestions($conn, $total, $category = null) {
    $half = intdiv($total, 2);
    $remainder = $total - $half;
    // Prefer balanced: half mcq, remainder voice
    $mcqNeeded = $half;
    $voiceNeeded = $remainder;

    $mcq = fetchRandomQuestions($conn, 'mcq', $mcqNeeded, $category);
    $voice = fetchRandomQuestions($conn, 'voice', $voiceNeeded, $category);

    // If one type is short, top-up from the other
    if (count($mcq) < $mcqNeeded) {
        $topup = fetchRandomQuestions($conn, 'voice', $mcqNeeded - count($mcq), $category);
        $voice = array_merge($voice, $topup);
    }
    if (count($voice) < $voiceNeeded) {
        $topup = fetchRandomQuestions($conn, 'mcq', $voiceNeeded - count($voice), $category);
        $mcq = array_merge($mcq, $topup);
    }

    $all = array_merge($mcq, $voice);
    // Shuffle to mix types
    shuffle($all);
    // Truncate to total just in case
    return array_slice($all, 0, $total);
}

function getCorrectIndicesForMcq($conn, $questionId) {
    $sql = "SELECT answer_indices FROM mcq_question_answers WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $questionId);
    $stmt->execute();
    $res = $stmt->get_result();
    $indices = [];
    if ($row = $res->fetch_assoc()) {
        $decoded = json_decode($row['answer_indices'], true);
        if (is_array($decoded)) { $indices = array_map('intval', $decoded); }
    }
    $stmt->close();
    return $indices;
}

function getCorrectIndexForVoice($conn, $questionId) {
    // Get answer text, then find matching option index
    $sql = "SELECT answer_text FROM voice_question_answers WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $questionId);
    $stmt->execute();
    $res = $stmt->get_result();
    $answerText = null;
    if ($row = $res->fetch_assoc()) { $answerText = $row['answer_text']; }
    $stmt->close();
    if ($answerText === null) return null;

    // Respect schema for options
    $schema = detectSchemaMap($conn);
    $ot = $schema['options']['text'];
    $oo = $schema['options']['order'];
    $sql2 = "SELECT $oo AS option_order, $ot AS option_text FROM question_options WHERE question_id = ? ORDER BY $oo ASC";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param('i', $questionId);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $correctIndex = null;
    while ($r = $res2->fetch_assoc()) {
        if ($r['option_text'] === $answerText) { $correctIndex = intval($r['option_order']); break; }
    }
    $stmt2->close();
    return $correctIndex;
}

function computeResultForQuestion($conn, $question) {
    $qid = intval($question['question_id']);
    $qtype = $question['question_type'];
    $selectedIndex = isset($question['selected_index']) ? intval($question['selected_index']) : null;

    $isCorrect = 0;
    $correctIndices = [];
    $correctIndex = null;

    if ($qtype === 'mcq') {
        $correctIndices = getCorrectIndicesForMcq($conn, $qid);
        if ($selectedIndex !== null && in_array($selectedIndex, $correctIndices, true)) {
            $isCorrect = 1;
        }
    } else if ($qtype === 'voice') {
        $correctIndex = getCorrectIndexForVoice($conn, $qid);
        if ($selectedIndex !== null && $correctIndex !== null && $selectedIndex === $correctIndex) {
            $isCorrect = 1;
        }
    }

    return [
        'question_id' => $qid,
        'question_type' => $qtype,
        'selected_index' => $selectedIndex,
        'is_correct' => $isCorrect,
        'correct_indices' => $correctIndices,
        'correct_index' => $correctIndex,
    ];
}

function getQuestionType($conn, $questionId) {
    $schema = detectSchemaMap($conn);
    $qtypeCol = $schema['questions']['type'];
    $sql = "SELECT $qtypeCol AS question_type FROM questions WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $questionId);
    $stmt->execute();
    $res = $stmt->get_result();
    $type = null;
    if ($row = $res->fetch_assoc()) { $type = $row['question_type']; }
    $stmt->close();
    return $type;
}

function getOptionText($conn, $questionId, $optionIndex) {
    $schema = detectSchemaMap($conn);
    $ot = $schema['options']['text'];
    $oo = $schema['options']['order'];
    $sql = "SELECT $ot AS option_text FROM question_options WHERE question_id = ? AND $oo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $questionId, $optionIndex);
    $stmt->execute();
    $res = $stmt->get_result();
    $text = null;
    if ($row = $res->fetch_assoc()) { $text = $row['option_text']; }
    $stmt->close();
    return $text;
}

// Route actions
$conn = getDBConnection();
ensureUserAnswersTable($conn);

$action = getParam('action', 'fetchQuestions');

try {
    switch ($action) {
        case 'startExam': {
            $examType = strtolower(trim((string)getParam('examType', 'both')));
            $numStr = getParam('numberOfQuestions', '20');
            $count = max(1, min(200, intval($numStr)));
            $category = getParam('category'); // optional

            if (!in_array($examType, ['mcq','voice','both'], true)) {
                fail('Invalid examType');
            }

            if ($examType === 'mcq') {
                $questions = fetchRandomQuestions($conn, 'mcq', $count, $category);
            } else if ($examType === 'voice') {
                $questions = fetchRandomQuestions($conn, 'voice', $count, $category);
            } else { // both
                $questions = fetchBothTypeQuestions($conn, $count, $category);
            }

            // Create attempt token and store in session the allowed question IDs
            $token = randToken(32);
            $_SESSION['exam_attempts'] = $_SESSION['exam_attempts'] ?? [];
            $_SESSION['exam_attempts'][$token] = [
                'examType' => $examType,
                'category' => $category,
                'questionIds' => array_map(function($q){ return $q['id']; }, $questions),
                'started_at' => time(),
            ];

            ok('Exam started', [
                'attemptToken' => $token,
                'examType' => $examType,
                'numberOfQuestions' => count($questions),
                'questions' => $questions,
            ]);
            break;
        }

        case 'fetchQuestions': {
            // Convenience action if frontend wants direct fetch without starting
            $examType = strtolower(trim((string)getParam('examType', 'both')));
            $numStr = getParam('numberOfQuestions', '20');
            $count = max(1, min(200, intval($numStr)));
            $category = getParam('category');

            if (!in_array($examType, ['mcq','voice','both'], true)) {
                fail('Invalid examType');
            }
            if ($examType === 'mcq') {
                $questions = fetchRandomQuestions($conn, 'mcq', $count, $category);
            } else if ($examType === 'voice') {
                $questions = fetchRandomQuestions($conn, 'voice', $count, $category);
            } else {
                $questions = fetchBothTypeQuestions($conn, $count, $category);
            }
            ok('Questions fetched', [ 'questions' => $questions ]);
            break;
        }

        case 'submitAnswers': {
            // Expect: attemptToken, answers: [{question_id, selected_index}]
            $token = getParam('attemptToken');
            $answers = getParam('answers');
            if (!$token || !is_array($answers)) {
                fail('attemptToken and answers are required');
            }

            $attempts = $_SESSION['exam_attempts'] ?? [];
            if (!isset($attempts[$token])) {
                fail('Invalid or expired attemptToken', 401);
            }
            $attempt = $attempts[$token];
            $allowedIds = $attempt['questionIds'];
            $examType = $attempt['examType'];

            // Optional: simulate loading time for UX
            usleep(500000); // 0.5s

            $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
            $userType = $_SESSION['user_type'] ?? null;

            // Validate and compute per-question results
            $resultsPerQ = [];
            $correct = 0; $incorrect = 0; $total = 0;

            // Prepare insert (NULLs are allowed for user_id, selected_index, selected_text)
            $insSql = "INSERT INTO useranswers (attempt_token, user_id, exam_type, question_id, selected_index, selected_text, is_correct) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $ins = $conn->prepare($insSql);

            foreach ($answers as $ans) {
                if (!isset($ans['question_id'])) { continue; }
                $qid = intval($ans['question_id']);
                if (!in_array($qid, $allowedIds, true)) { continue; }
                $selectedIndex = isset($ans['selected_index']) ? intval($ans['selected_index']) : null;
                $qtype = getQuestionType($conn, $qid);
                if (!$qtype) { continue; }

                $computed = computeResultForQuestion($conn, [
                    'question_id' => $qid,
                    'question_type' => $qtype,
                    'selected_index' => $selectedIndex,
                ]);

                $selText = $selectedIndex !== null ? getOptionText($conn, $qid, $selectedIndex) : null;

                $isCorr = intval($computed['is_correct']);
                $uid = $userId; // can be null
                // Bind and execute; mysqli supports NULL values bound as variables
                $ins->bind_param('sisiisi', $token, $uid, $examType, $qid, $selectedIndex, $selText, $isCorr);
                $ins->execute();

                $resultsPerQ[] = [
                    'question_id' => $qid,
                    'question_type' => $qtype,
                    'selected_index' => $selectedIndex,
                    'is_correct' => (bool)$isCorr,
                    'correct_indices' => $computed['correct_indices'],
                    'correct_index' => $computed['correct_index']
                ];

                $total++;
                if ($isCorr) { $correct++; } else { $incorrect++; }
            }

            if ($ins && method_exists($ins, 'close')) { $ins->close(); }

            // Calculate score and percentage
            $percentage = $total > 0 ? round(($correct/$total) * 100, 2) : 0;
            
            // Variable to store the exam result ID if saved
            $examResultId = null;
            
            // If user is a student, store result in exam_results table
            if ($userType === 'student' && $userId !== null) {
                try {
                    // Calculate time taken (in seconds) from exam start
                    $startTime = $attempt['started_at'] ?? time();
                    $timeTaken = time() - $startTime;
                    
                    // For mock exams, we don't have an exam_id, so we'll use NULL
                    // You can modify this logic if you want to link mock exams to specific exams
                    $examId = getParam('exam_id'); // Optional exam_id if taking a real exam
                    $examId = $examId ? intval($examId) : null;
                    
                    // Calculate score based on total marks (assume 1 mark per question by default)
                    $totalMarks = $total; // Can be customized based on exam settings
                    // score column is DECIMAL(5,2), so convert correct answers to decimal
                    $scoreDecimal = floatval($correct);
                    
                    // Debug logging
                    error_log("Saving exam result for student: userType=$userType, userId=$userId, score=$scoreDecimal, total=$totalMarks, percentage=$percentage");
                    
                    // Insert into exam_results table
                    // Note: exam_id can be NULL for mock exams, student_id is required
                    // score and percentage are DECIMAL(5,2), total_marks and time_taken are INT
                    // status can be: pending, in_progress, completed, submitted
                    $resultSql = "INSERT INTO exam_results 
                                  (exam_id, student_id, score, total_marks, percentage, time_taken, status, started_at, submitted_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, 'submitted', FROM_UNIXTIME(?), NOW())";
                    $resultStmt = $conn->prepare($resultSql);
                    
                    if (!$resultStmt) {
                        error_log("Failed to prepare exam_results insert: " . $conn->error);
                    } else {
                        // Bind parameters: i=integer, d=double/decimal, s=string
                        // exam_id (int or NULL), student_id (int), score (decimal), total_marks (int), 
                        // percentage (decimal), time_taken (int), startTime (int for FROM_UNIXTIME)
                        $resultStmt->bind_param('iididii', $examId, $userId, $scoreDecimal, $totalMarks, $percentage, $timeTaken, $startTime);
                        
                        if ($resultStmt->execute()) {
                            $examResultId = $conn->insert_id;
                            error_log("Successfully saved exam result with ID: $examResultId for student ID: $userId");
                        } else {
                            error_log("Failed to execute exam_results insert: " . $resultStmt->error);
                        }
                        
                        $resultStmt->close();
                    }
                } catch (Exception $e) {
                    error_log("Exception while saving exam result: " . $e->getMessage());
                }
            } else {
                // Log why result wasn't saved
                error_log("Exam result NOT saved: userType=$userType, userId=" . ($userId ?? 'NULL'));
            }

            ok('Results calculated', [
                'attemptToken' => $token,
                'examResultId' => $examResultId, // Include the saved result ID
                'summary' => [
                    'total' => $total,
                    'correct' => $correct,
                    'incorrect' => $incorrect,
                    'percentage' => $percentage,
                ],
                'details' => $resultsPerQ
            ]);
            break;
        }

        default:
            fail('Unknown action');
    }
} catch (Throwable $e) {
    fail('Server error: ' . $e->getMessage(), 500);
} finally {
    if ($conn) { $conn->close(); }
}


?>

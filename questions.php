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
initializeQuestionsTables($conn);

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
function initializeQuestionsTables($conn) {
    // Create questions table
    $sql = "CREATE TABLE IF NOT EXISTS questions (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        question_text TEXT NOT NULL,
        question_type ENUM('mcq', 'voice') NOT NULL DEFAULT 'mcq',
        question_format ENUM('normal', 'image') DEFAULT 'normal',
        question_image VARCHAR(255) NULL COMMENT 'File path to question image',
        answer_type ENUM('single', 'multiple') NOT NULL DEFAULT 'single',
        audio_link VARCHAR(500) NULL,
        difficulty ENUM('Beginner', 'Intermediate', 'Advanced') NOT NULL DEFAULT 'Beginner',
        category VARCHAR(100) NOT NULL,
        time_limit INT(11) NULL COMMENT 'Time limit in seconds (for voice questions only)',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active TINYINT(1) DEFAULT 1,
        INDEX idx_type (question_type),
        INDEX idx_category (category),
        INDEX idx_difficulty (difficulty),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($sql);

    // Create question_options table (stores 4 options for each question)
    $sql = "CREATE TABLE IF NOT EXISTS question_options (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        question_id INT(11) UNSIGNED NOT NULL,
        option_text TEXT NOT NULL,
        option_image VARCHAR(255) NULL COMMENT 'File path to option image',
        option_order TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=A, 1=B, 2=C, 3=D',
        is_correct TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_question (question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($sql);

    // Create voice_question_answers table (separate table for voice question answers)
    $sql = "CREATE TABLE IF NOT EXISTS voice_question_answers (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        question_id INT(11) UNSIGNED NOT NULL,
        answer_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_question (question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($sql);

    // Create mcq_question_answers table (separate table for MCQ correct answers)
    $sql = "CREATE TABLE IF NOT EXISTS mcq_question_answers (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        question_id INT(11) UNSIGNED NOT NULL,
        answer_indices TEXT NOT NULL COMMENT 'JSON array of correct option indices',
        answer_texts TEXT NULL COMMENT 'JSON array of correct option texts',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_question (question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($sql);
}

// Helper function to save base64 image to file
function saveBase64Image($base64String, $folder = 'questions') {
    // Check if it's a valid base64 image
    if (empty($base64String) || $base64String === 'null' || $base64String === null) {
        return null;
    }
    
    // Check if it's already a file path (not base64)
    if (!preg_match('/^data:image\/(\w+);base64,/', $base64String)) {
        // If it's already a file path, return it
        if (strpos($base64String, 'uploads/') === 0) {
            return $base64String;
        }
        return null;
    }
    
    // Extract the image data
    preg_match('/^data:image\/(\w+);base64,/', $base64String, $matches);
    $imageType = $matches[1] ?? 'png';
    $base64String = substr($base64String, strpos($base64String, ',') + 1);
    $imageData = base64_decode($base64String);
    
    if ($imageData === false) {
        return null;
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/uploads/images/' . $folder;
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $filename = uniqid('img_', true) . '_' . time() . '.' . $imageType;
    $filepath = $uploadDir . '/' . $filename;
    
    // Save the file
    if (file_put_contents($filepath, $imageData)) {
        // Return relative path
        return 'uploads/images/' . $folder . '/' . $filename;
    }
    
    return null;
}

// Helper function to delete image file
function deleteImageFile($imagePath) {
    if (empty($imagePath) || $imagePath === null) {
        return;
    }
    
    $filepath = __DIR__ . '/' . $imagePath;
    if (file_exists($filepath)) {
        unlink($filepath);
    }
}

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


// Handle GET requests
function handleGet($conn, $action) {
    switch ($action) {
        case 'list':
            getAllQuestions($conn);
            break;
        case 'view':
            getQuestionById($conn);
            break;
        case 'stats':
            getQuestionStats($conn);
            break;
        default:
            getAllQuestions($conn);
            break;
    }
}

// Get all questions with their options
function getAllQuestions($conn) {
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $type = isset($_GET['type']) ? $_GET['type'] : '';
    $difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
    $category = isset($_GET['category']) ? $_GET['category'] : '';

    $sql = "SELECT * FROM questions WHERE is_active = 1";
    
    if (!empty($search)) {
        $search = $conn->real_escape_string($search);
        $sql .= " AND (question_text LIKE '%$search%' OR category LIKE '%$search%')";
    }
    
    if (!empty($type)) {
        $type = $conn->real_escape_string($type);
        $sql .= " AND question_type = '$type'";
    }
    
    if (!empty($difficulty)) {
        $difficulty = $conn->real_escape_string($difficulty);
        $sql .= " AND difficulty = '$difficulty'";
    }
    
    if (!empty($category)) {
        $category = $conn->real_escape_string($category);
        $sql .= " AND category = '$category'";
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $questions = [];
        while ($row = $result->fetch_assoc()) {
            // Transform snake_case to camelCase for frontend compatibility
            $question = [
                'id' => $row['id'],
                'questionText' => $row['question_text'],
                'questionType' => $row['question_type'],
                'questionFormat' => $row['question_format'],
                'questionImage' => getImageUrl($row['question_image']),
                'answerType' => $row['answer_type'],
                'audioLink' => $row['audio_link'],
                'timeLimit' => $row['time_limit'],
                'difficulty' => $row['difficulty'],
                'category' => $row['category'],
                'isActive' => $row['is_active'],
                'createdBy' => $row['created_by'],
                'createdAt' => $row['created_at'],
                'updatedAt' => $row['updated_at']
            ];
            
            // Get options for this question
            $question['options'] = getQuestionOptions($conn, $row['id']);
            
            // Get correct answers array based on question type
            if ($row['question_type'] === 'mcq') {
                $question['correctAnswers'] = getMCQCorrectAnswers($conn, $row['id']);
            } else {
                $question['correctAnswers'] = [];
            }
            
            $questions[] = $question;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $questions,
            'total' => count($questions)
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to retrieve questions'
        ]);
    }
}

// Get single question by ID
function getQuestionById($conn) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid question ID']);
        return;
    }
    
    $sql = "SELECT * FROM questions WHERE id = ? AND is_active = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Transform snake_case to camelCase for frontend compatibility
        $question = [
            'id' => $row['id'],
            'questionText' => $row['question_text'],
            'questionType' => $row['question_type'],
            'questionFormat' => $row['question_format'],
            'questionImage' => getImageUrl($row['question_image']),
            'answerType' => $row['answer_type'],
            'audioLink' => $row['audio_link'],
            'timeLimit' => $row['time_limit'],
            'difficulty' => $row['difficulty'],
            'category' => $row['category'],
            'isActive' => $row['is_active'],
            'createdBy' => $row['created_by'],
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at']
        ];
        
        // Get options
        $question['options'] = getQuestionOptions($conn, $id);
        
        // Get correct answers based on question type
        if ($row['question_type'] === 'mcq') {
            $question['correctAnswers'] = getMCQCorrectAnswers($conn, $id);
        } else {
            $question['correctAnswers'] = [];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $question
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Question not found'
        ]);
    }
    
    $stmt->close();
}

// Get question statistics
function getQuestionStats($conn) {
    $stats = [];
    
    // Total questions
    $result = $conn->query("SELECT COUNT(*) as total FROM questions WHERE is_active = 1");
    $stats['total'] = $result->fetch_assoc()['total'];
    
    // By type
        $result = $conn->query("SELECT question_type, COUNT(*) as count FROM questions WHERE is_active = 1 GROUP BY question_type");
    $stats['by_type'] = [];
    while ($row = $result->fetch_assoc()) {
           $stats['by_type'][$row['question_type']] = $row['count'];
    }
    
    // By difficulty
    $result = $conn->query("SELECT difficulty, COUNT(*) as count FROM questions WHERE is_active = 1 GROUP BY difficulty");
    $stats['by_difficulty'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['by_difficulty'][$row['difficulty']] = $row['count'];
    }
    
    // By category
    $result = $conn->query("SELECT category, COUNT(*) as count FROM questions WHERE is_active = 1 GROUP BY category ORDER BY count DESC LIMIT 10");
    $stats['by_category'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['by_category'][$row['category']] = $row['count'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
}

// Handle POST requests (Create)
function handlePost($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validation
    $validation = validateQuestionData($input);
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $validation['message']
        ]);
        return;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Process question image if it's base64
        $questionImagePath = null;
        if (!empty($input['questionImage'])) {
            $questionImagePath = saveBase64Image($input['questionImage'], 'questions');
        }
        
        // Process option images if they're base64
        if (!empty($input['options']) && is_array($input['options'])) {
            foreach ($input['options'] as $index => $option) {
                if (!empty($option['image'])) {
                    $savedPath = saveBase64Image($option['image'], 'options');
                    $input['options'][$index]['image'] = $savedPath;
                }
            }
        }
        
        // timeLimit: voice uses provided (validated 5-300), MCQ uses 0
        $timeLimit = 0;
        if ($input['questionType'] === 'voice' && isset($input['timeLimit'])) {
            $timeLimit = intval($input['timeLimit']);
        }
        
        // Insert question
        $sql = "INSERT INTO questions (
              question_text, question_type, question_format, question_image, 
              answer_type, audio_link, difficulty, category, time_limit
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'ssssssssi',
            $input['questionText'],
            $input['questionType'],
            $input['questionFormat'],
            $questionImagePath,
            $input['answerType'],
            $input['audioLink'],
            $input['difficulty'],
            $input['category'],
            $timeLimit
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create question');
        }
        
        $questionId = $conn->insert_id;
        $stmt->close();
        
        // Insert options (no is_correct)
        if (!empty($input['options']) && is_array($input['options'])) {
            foreach ($input['options'] as $index => $option) {
                $sql = "INSERT INTO question_options (
                    question_id, option_text, option_image, option_order
                ) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    'issi',
                    $questionId,
                    $option['text'],
                    $option['image'],
                    $index
                );
                if (!$stmt->execute()) {
                    throw new Exception('Failed to create question options');
                }
                $stmt->close();
            }
        }

        // Store MCQ correct answers in mcq_question_answers
        if ($input['questionType'] === 'mcq' && !empty($input['correctAnswers'])) {
            $answerIndices = json_encode($input['correctAnswers']);
            $answerTexts = json_encode(array_map(function($i) use ($input) {
                return $input['options'][$i]['text'];
            }, $input['correctAnswers']));
            $sql = "INSERT INTO mcq_question_answers (question_id, answer_indices, answer_texts) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iss', $questionId, $answerIndices, $answerTexts);
            if (!$stmt->execute()) {
                throw new Exception('Failed to store MCQ question answer');
            }
            $stmt->close();
        }
        
        // For voice questions, store answer in separate table
        if ($input['questionType'] === 'voice' && !empty($input['correctAnswers'])) {
            // Get the correct answer text from options
            $correctIndex = $input['correctAnswers'][0];
            $answerText = $input['options'][$correctIndex]['text'];
            
            $sql = "INSERT INTO voice_question_answers (question_id, answer_text) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('is', $questionId, $answerText);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to store voice question answer');
            }
            $stmt->close();
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Question created successfully',
            'question_id' => $questionId
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create question: ' . $e->getMessage()
        ]);
    }
}

// Handle PUT requests (Update)
function handlePut($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id']) || empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Question ID is required']);
        return;
    }
    
    $questionId = intval($input['id']);
    
    // Validation
    $validation = validateQuestionData($input);
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $validation['message']
        ]);
        return;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get existing question image to delete if changed
        $existingImageSql = "SELECT question_image FROM questions WHERE id = ?";
        $existingStmt = $conn->prepare($existingImageSql);
        $existingStmt->bind_param('i', $questionId);
        $existingStmt->execute();
        $existingResult = $existingStmt->get_result();
        $existingQuestion = $existingResult->fetch_assoc();
        $existingStmt->close();
        
        // Process question image if it's base64
        $questionImagePath = $input['questionImage'];
        if (!empty($input['questionImage']) && preg_match('/^data:image\/(\w+);base64,/', $input['questionImage'])) {
            // Delete old image if exists
            if (!empty($existingQuestion['question_image'])) {
                deleteImageFile($existingQuestion['question_image']);
            }
            $questionImagePath = saveBase64Image($input['questionImage'], 'questions');
        }
        
        // Get existing option images to delete if changed
        $existingOptionsSql = "SELECT option_image FROM question_options WHERE question_id = ?";
        $existingOptionsStmt = $conn->prepare($existingOptionsSql);
        $existingOptionsStmt->bind_param('i', $questionId);
        $existingOptionsStmt->execute();
        $existingOptionsResult = $existingOptionsStmt->get_result();
        $existingOptionImages = [];
        while ($row = $existingOptionsResult->fetch_assoc()) {
            if (!empty($row['option_image'])) {
                $existingOptionImages[] = $row['option_image'];
            }
        }
        $existingOptionsStmt->close();
        
        // Process option images if they're base64
        if (!empty($input['options']) && is_array($input['options'])) {
            foreach ($input['options'] as $index => $option) {
                if (!empty($option['image']) && preg_match('/^data:image\/(\w+);base64,/', $option['image'])) {
                    $savedPath = saveBase64Image($option['image'], 'options');
                    $input['options'][$index]['image'] = $savedPath;
                }
            }
        }
        
        // Delete old option images
        foreach ($existingOptionImages as $oldImage) {
            deleteImageFile($oldImage);
        }
        
        // timeLimit: voice uses provided, MCQ uses 0
        $timeLimit = 0;
        if ($input['questionType'] === 'voice' && isset($input['timeLimit'])) {
            $timeLimit = intval($input['timeLimit']);
        }
        
        // Update question
        $sql = "UPDATE questions SET 
              question_text = ?, question_type = ?, question_format = ?, question_image = ?,
              answer_type = ?, audio_link = ?, difficulty = ?, category = ?, time_limit = ?
            WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'ssssssssii',
            $input['questionText'],
            $input['questionType'],
            $input['questionFormat'],
            $questionImagePath,
            $input['answerType'],
            $input['audioLink'],
            $input['difficulty'],
            $input['category'],
            $timeLimit,
            $questionId
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update question');
        }
        $stmt->close();
        
        // Delete existing options
        $conn->query("DELETE FROM question_options WHERE question_id = $questionId");
        // Delete existing MCQ answer
        $conn->query("DELETE FROM mcq_question_answers WHERE question_id = $questionId");
        // Delete existing voice answer
        $conn->query("DELETE FROM voice_question_answers WHERE question_id = $questionId");

        // Insert new options (no is_correct)
        if (!empty($input['options']) && is_array($input['options'])) {
            foreach ($input['options'] as $index => $option) {
                $sql = "INSERT INTO question_options (
                    question_id, option_text, option_image, option_order
                ) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    'issi',
                    $questionId,
                    $option['text'],
                    $option['image'],
                    $index
                );
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update question options');
                }
                $stmt->close();
            }
        }

        // Store MCQ correct answers in mcq_question_answers
        if ($input['questionType'] === 'mcq' && !empty($input['correctAnswers'])) {
            $answerIndices = json_encode($input['correctAnswers']);
            $answerTexts = json_encode(array_map(function($i) use ($input) {
                return $input['options'][$i]['text'];
            }, $input['correctAnswers']));
            $sql = "INSERT INTO mcq_question_answers (question_id, answer_indices, answer_texts) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iss', $questionId, $answerIndices, $answerTexts);
            if (!$stmt->execute()) {
                throw new Exception('Failed to store MCQ question answer');
            }
            $stmt->close();
        }
        
        // For voice questions, store answer in separate table
        if ($input['questionType'] === 'voice' && !empty($input['correctAnswers'])) {
            $correctIndex = $input['correctAnswers'][0];
            $answerText = $input['options'][$correctIndex]['text'];
            
            $sql = "INSERT INTO voice_question_answers (question_id, answer_text) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('is', $questionId, $answerText);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update voice question answer');
            }
            $stmt->close();
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Question updated successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update question: ' . $e->getMessage()
        ]);
    }
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
        echo json_encode(['success' => false, 'message' => 'Invalid or missing question ID']);
        return;
    }

    // Soft delete - just mark as inactive
    $sql = "UPDATE questions SET is_active = 0 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        echo json_encode([
            'success' => true,
            'message' => $affected > 0 ? 'Question deleted successfully' : 'No change (already deleted or not found)',
            'affected' => $affected,
            'id' => $id
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete question'
        ]);
    }

    $stmt->close();
}

// Helper function to get question options
function getQuestionOptions($conn, $questionId) {
    $sql = "SELECT * FROM question_options WHERE question_id = ? ORDER BY option_order ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $questionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $options = [];
    while ($row = $result->fetch_assoc()) {
        $options[] = [
            'text' => $row['option_text'],
            'image' => getImageUrl($row['option_image'])
        ];
    }
    
    $stmt->close();
    return $options;
}

// Helper function to get MCQ correct answer indices from mcq_question_answers table
function getMCQCorrectAnswers($conn, $questionId) {
    $sql = "SELECT answer_indices FROM mcq_question_answers WHERE question_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $questionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $correctAnswers = [];
    if ($row = $result->fetch_assoc()) {
        $correctAnswers = json_decode($row['answer_indices'], true);
    }
    
    $stmt->close();
    return $correctAnswers ? $correctAnswers : [];
}

// Helper function to get correct answer indices (kept for backward compatibility)
function getCorrectAnswers($conn, $questionId) {
    // This is now only used for voice questions
    // For MCQ, use getMCQCorrectAnswers()
    return [];
}

// Validate question data
function validateQuestionData($data) {
    // Required fields
    if (empty($data['questionText'])) {
        return ['valid' => false, 'message' => 'Question text is required'];
    }
    
    if (empty($data['questionType']) || !in_array($data['questionType'], ['mcq', 'voice'])) {
        return ['valid' => false, 'message' => 'Valid question type is required (mcq or voice)'];
    }
    
    if (empty($data['answerType']) || !in_array($data['answerType'], ['single', 'multiple'])) {
        return ['valid' => false, 'message' => 'Valid answer type is required (single or multiple)'];
    }
    
    if (empty($data['difficulty']) || !in_array($data['difficulty'], ['Beginner', 'Intermediate', 'Advanced'])) {
        return ['valid' => false, 'message' => 'Valid difficulty is required'];
    }
    
    if (empty($data['category'])) {
        return ['valid' => false, 'message' => 'Category is required'];
    }
    
    // Voice question specific validation
    if ($data['questionType'] === 'voice') {
        if (empty($data['audioLink'])) {
            return ['valid' => false, 'message' => 'Audio link is required for voice questions'];
        }
        
        if (empty($data['timeLimit']) || $data['timeLimit'] < 5 || $data['timeLimit'] > 300) {
            return ['valid' => false, 'message' => 'Time limit must be between 5 and 300 seconds for voice questions'];
        }
    }
    
    // Options validation
    if (empty($data['options']) || !is_array($data['options']) || count($data['options']) !== 4) {
        return ['valid' => false, 'message' => 'Exactly 4 options are required'];
    }
    
    foreach ($data['options'] as $option) {
        if (empty($option['text'])) {
            return ['valid' => false, 'message' => 'All options must have text'];
        }
    }
    
    // Correct answers validation
    if (empty($data['correctAnswers']) || !is_array($data['correctAnswers'])) {
        return ['valid' => false, 'message' => 'At least one correct answer must be selected'];
    }
    
    return ['valid' => true];
}

$conn->close();
?>

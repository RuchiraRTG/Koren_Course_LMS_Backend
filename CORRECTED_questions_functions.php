<?php
// CORRECTED FUNCTIONS FOR questions.php
// Replace the corresponding functions in your questions.php with these

// ====================================
// GET ALL QUESTIONS - CORRECTED
// ====================================
function getAllQuestions($conn) {
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $type = isset($_GET['type']) ? $_GET['type'] : '';
    $difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
    $category = isset($_GET['category']) ? $_GET['category'] : '';

    $sql = "SELECT * FROM questions WHERE is_active = 1";
    
    if (!empty($search)) {
        $search = $conn->real_escape_string($search);
        $sql .= " AND (questionText LIKE '%$search%' OR category LIKE '%$search%')";
    }
    
    if (!empty($type)) {
        $type = $conn->real_escape_string($type);
        $sql .= " AND questionType = '$type'";
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
            $question = $row;
            
            // Get options for this question
            $question['options'] = getQuestionOptions($conn, $row['id']);
            
            // Get correct answers array
            $question['correctAnswers'] = getCorrectAnswers($conn, $row['id']);
            
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

// ====================================
// GET CORRECT ANSWERS - CORRECTED
// ====================================
function getCorrectAnswers($conn, $questionId) {
    $correctAnswers = [];
    
    // First, get the question type
    $typeSql = "SELECT questionType FROM questions WHERE id = ?";
    $typeStmt = $conn->prepare($typeSql);
    $typeStmt->bind_param('i', $questionId);
    $typeStmt->execute();
    $typeResult = $typeStmt->get_result();
    
    if ($typeResult->num_rows === 0) {
        $typeStmt->close();
        return $correctAnswers;
    }
    
    $questionType = $typeResult->fetch_assoc()['questionType'];
    $typeStmt->close();
    
    if ($questionType === 'mcq') {
        // MCQ question - get from mcq_question_answers table
        $sql = "SELECT answer_indices FROM mcq_question_answers WHERE question_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $questionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $correctAnswers = json_decode($row['answer_indices'], true);
            if (!is_array($correctAnswers)) {
                $correctAnswers = [];
            }
        }
        $stmt->close();
        
    } else if ($questionType === 'voice') {
        // Voice question - get from voice_question_answers table
        $sql = "SELECT answer_text FROM voice_question_answers WHERE question_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $questionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $answerText = $row['answer_text'];
            
            // Find the matching option index
            $optionsSql = "SELECT option_order, text FROM question_options WHERE question_id = ? ORDER BY option_order ASC";
            $optionsStmt = $conn->prepare($optionsSql);
            $optionsStmt->bind_param('i', $questionId);
            $optionsStmt->execute();
            $optionsResult = $optionsStmt->get_result();
            
            while ($optionRow = $optionsResult->fetch_assoc()) {
                if ($optionRow['text'] === $answerText) {
                    $correctAnswers[] = intval($optionRow['option_order']);
                    break;
                }
            }
            $optionsStmt->close();
        }
        $stmt->close();
    }
    
    return $correctAnswers;
}

// ====================================
// HANDLE POST (CREATE) - CORRECTED
// ====================================
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
        // timeLimit: voice uses provided (validated 5-300), MCQ uses 0
        $timeLimit = 0;
        if ($input['questionType'] === 'voice' && isset($input['timeLimit'])) {
            $timeLimit = intval($input['timeLimit']);
        }
        
        // Insert question
        $sql = "INSERT INTO questions (
            questionText, questionType, questionFormat, questionImage, 
            answerType, audioLink, difficulty, category, timeLimit
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'ssssssssi',
            $input['questionText'],
            $input['questionType'],
            $input['questionFormat'],
            $input['questionImage'],
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
        
        // Insert options
        if (!empty($input['options']) && is_array($input['options'])) {
            foreach ($input['options'] as $index => $option) {
                $sql = "INSERT INTO question_options (
                    question_id, text, image, option_order
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

        // Store MCQ correct answers
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
        
        // For voice questions, store answer
        if ($input['questionType'] === 'voice' && !empty($input['correctAnswers'])) {
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

// ====================================
// HANDLE PUT (UPDATE) - CORRECTED
// ====================================
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
        // timeLimit: voice uses provided, MCQ uses 0
        $timeLimit = 0;
        if ($input['questionType'] === 'voice' && isset($input['timeLimit'])) {
            $timeLimit = intval($input['timeLimit']);
        }
        
        // Update question
        $sql = "UPDATE questions SET 
            questionText = ?, questionType = ?, questionFormat = ?, questionImage = ?,
            answerType = ?, audioLink = ?, difficulty = ?, category = ?, timeLimit = ?
            WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'ssssssssii',
            $input['questionText'],
            $input['questionType'],
            $input['questionFormat'],
            $input['questionImage'],
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
        
        // Delete existing options and answers
        $conn->query("DELETE FROM question_options WHERE question_id = $questionId");
        $conn->query("DELETE FROM mcq_question_answers WHERE question_id = $questionId");
        $conn->query("DELETE FROM voice_question_answers WHERE question_id = $questionId");

        // Insert new options
        if (!empty($input['options']) && is_array($input['options'])) {
            foreach ($input['options'] as $index => $option) {
                $sql = "INSERT INTO question_options (
                    question_id, text, image, option_order
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

        // Store MCQ correct answers
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
        
        // For voice questions, store answer
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

// ====================================
// GET QUESTION STATS - CORRECTED
// ====================================
function getQuestionStats($conn) {
    $stats = [];
    
    // Total questions
    $result = $conn->query("SELECT COUNT(*) as total FROM questions WHERE is_active = 1");
    $stats['total'] = $result->fetch_assoc()['total'];
    
    // By type
    $result = $conn->query("SELECT questionType, COUNT(*) as count FROM questions WHERE is_active = 1 GROUP BY questionType");
    $stats['by_type'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['by_type'][$row['questionType']] = $row['count'];
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
?>

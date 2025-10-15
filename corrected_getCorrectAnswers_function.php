<?php
// CORRECTED getCorrectAnswers() function
// Replace the existing function in your questions.php with this

// Helper function to get correct answer indices
function getCorrectAnswers($conn, $questionId) {
    $correctAnswers = [];
    
    // First, get the question type
    $typeSql = "SELECT question_type FROM questions WHERE id = ?";
    $typeStmt = $conn->prepare($typeSql);
    $typeStmt->bind_param('i', $questionId);
    $typeStmt->execute();
    $typeResult = $typeStmt->get_result();
    
    if ($typeResult->num_rows === 0) {
        $typeStmt->close();
        return $correctAnswers;
    }
    
    $questionType = $typeResult->fetch_assoc()['question_type'];
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
?>

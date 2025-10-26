<?php
/**
 * Simple direct test for exam result insertion
 * This test directly inserts an exam result without needing a specific student
 */

require_once 'config/database.php';

echo "=== Direct Exam Result Insertion Test ===\n\n";

$conn = getDBConnection();

// Use an existing user ID (we'll use ID 1 for testing)
$testUserId = 1;

echo "Step 1: Preparing test data...\n";
$examId = null; // Mock exam (no specific exam ID)
$score = 7.50; // 7.5 correct answers (DECIMAL)
$totalMarks = 10; // 10 total questions
$percentage = 75.00; // 75% (DECIMAL)
$timeTaken = 450; // 7.5 minutes in seconds
$startTime = time() - $timeTaken;

echo "  - User ID: $testUserId\n";
echo "  - Exam ID: " . ($examId ?? 'NULL (Mock Exam)') . "\n";
echo "  - Score: $score / $totalMarks\n";
echo "  - Percentage: $percentage%\n";
echo "  - Time Taken: $timeTaken seconds (" . round($timeTaken/60, 2) . " minutes)\n";
echo "  - Started At: " . date('Y-m-d H:i:s', $startTime) . "\n\n";

// Step 2: Insert the exam result
echo "Step 2: Inserting exam result into database...\n";

$resultSql = "INSERT INTO exam_results 
              (exam_id, student_id, score, total_marks, percentage, time_taken, status, started_at, submitted_at) 
              VALUES (?, ?, ?, ?, ?, ?, 'submitted', FROM_UNIXTIME(?), NOW())";

$resultStmt = $conn->prepare($resultSql);

if (!$resultStmt) {
    die("✗ Failed to prepare statement: " . $conn->error . "\n");
}

// Bind parameters: exam_id (int or NULL), student_id (int), score (decimal), 
// total_marks (int), percentage (decimal), time_taken (int), startTime (int)
$resultStmt->bind_param('iididii', $examId, $testUserId, $score, $totalMarks, $percentage, $timeTaken, $startTime);

if ($resultStmt->execute()) {
    $insertedId = $conn->insert_id;
    echo "✓ Successfully inserted exam result with ID: $insertedId\n\n";
} else {
    die("✗ Failed to insert exam result: " . $resultStmt->error . "\n");
}

$resultStmt->close();

// Step 3: Verify the inserted data
echo "Step 3: Retrieving and verifying the inserted data...\n";

$verifySql = "SELECT 
                er.*,
                u.first_name,
                u.last_name,
                u.email
              FROM exam_results er
              LEFT JOIN users u ON er.student_id = u.id
              WHERE er.id = ?";

$verifyStmt = $conn->prepare($verifySql);
$verifyStmt->bind_param('i', $insertedId);
$verifyStmt->execute();
$verifyResult = $verifyStmt->get_result();

if ($verifyResult->num_rows > 0) {
    $data = $verifyResult->fetch_assoc();
    
    echo "✓ Exam result retrieved successfully:\n";
    echo str_repeat("=", 70) . "\n";
    printf("  %-20s : %s\n", "Result ID", $data['id']);
    printf("  %-20s : %s\n", "Exam ID", $data['exam_id'] ?? 'NULL (Mock Exam)');
    printf("  %-20s : %s\n", "Student ID", $data['student_id']);
    printf("  %-20s : %s %s\n", "Student Name", $data['first_name'], $data['last_name']);
    printf("  %-20s : %s\n", "Student Email", $data['email']);
    printf("  %-20s : %s / %s\n", "Score", $data['score'], $data['total_marks']);
    printf("  %-20s : %s%%\n", "Percentage", $data['percentage']);
    printf("  %-20s : %s seconds\n", "Time Taken", $data['time_taken']);
    printf("  %-20s : %s\n", "Status", $data['status']);
    printf("  %-20s : %s\n", "Started At", $data['started_at']);
    printf("  %-20s : %s\n", "Submitted At", $data['submitted_at']);
    printf("  %-20s : %s\n", "Created At", $data['created_at']);
    echo str_repeat("=", 70) . "\n\n";
    
    // Verify key values
    echo "Step 4: Verifying data integrity...\n";
    $errors = [];
    
    if ($data['student_id'] != $testUserId) {
        $errors[] = "Student ID mismatch (expected: $testUserId, got: {$data['student_id']})";
    }
    
    if ($data['score'] != $score) {
        $errors[] = "Score mismatch (expected: $score, got: {$data['score']})";
    }
    
    if ($data['total_marks'] != $totalMarks) {
        $errors[] = "Total marks mismatch (expected: $totalMarks, got: {$data['total_marks']})";
    }
    
    if ($data['percentage'] != $percentage) {
        $errors[] = "Percentage mismatch (expected: $percentage, got: {$data['percentage']})";
    }
    
    if ($data['status'] !== 'submitted') {
        $errors[] = "Status mismatch (expected: submitted, got: {$data['status']})";
    }
    
    if (count($errors) > 0) {
        echo "✗ Data integrity check failed:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    } else {
        echo "✓ All data integrity checks passed!\n";
    }
} else {
    echo "✗ Could not retrieve the inserted exam result\n";
}

$verifyStmt->close();

// Step 5: Show all exam results
echo "\nStep 5: Showing all exam results in the database...\n";
echo str_repeat("=", 85) . "\n";
printf("%-6s %-10s %-12s %-15s %-12s %-12s %-20s\n", 
    "ID", "User ID", "Exam ID", "Score", "Percentage", "Status", "Submitted At");
echo str_repeat("=", 85) . "\n";

$allResults = $conn->query("SELECT id, student_id, exam_id, score, total_marks, percentage, status, submitted_at 
                            FROM exam_results 
                            ORDER BY submitted_at DESC 
                            LIMIT 10");

if ($allResults && $allResults->num_rows > 0) {
    while ($row = $allResults->fetch_assoc()) {
        printf("%-6s %-10s %-12s %-15s %-12s %-12s %-20s\n",
            $row['id'],
            $row['student_id'],
            $row['exam_id'] ?? 'Mock',
            $row['score'] . '/' . $row['total_marks'],
            $row['percentage'] . '%',
            $row['status'],
            $row['submitted_at']
        );
    }
} else {
    echo "No exam results found in database.\n";
}
echo str_repeat("=", 85) . "\n";

$conn->close();

echo "\n✓ Test completed successfully!\n";
echo "\nYou can now test the takeExam.php API by:\n";
echo "1. Starting an exam (action=startExam)\n";
echo "2. Submitting answers (action=submitAnswers)\n";
echo "3. The exam result will be automatically saved to the exam_results table\n";
?>

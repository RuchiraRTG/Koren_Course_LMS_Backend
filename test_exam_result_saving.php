<?php
/**
 * Test script for exam result saving functionality
 * This simulates a student taking an exam and verifies the result is saved
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "=== Testing Exam Result Saving Functionality ===\n\n";

// Step 1: Check if we have a test student
$conn = getDBConnection();

// Try to find a student user
$sql = "SELECT id, first_name, last_name, email FROM users WHERE role = 'student' LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows === 0) {
    echo "✗ No student user found in database.\n";
    echo "Please create a student user first before running this test.\n";
    echo "You can do this through the signup page or by inserting directly into the database.\n\n";
    
    // Show SQL for manual insertion
    echo "Manual SQL to create a test student:\n";
    echo str_repeat("-", 70) . "\n";
    echo "INSERT INTO users (first_name, last_name, email, password, nic_number, phone_number, role, is_active)\n";
    echo "VALUES ('Test', 'Student', 'teststudent@example.com', 'hashed_password', '123456789', '0771234567', 'student', 1);\n";
    echo str_repeat("-", 70) . "\n\n";
    
    $conn->close();
    exit(0);
} else {
    $student = $result->fetch_assoc();
    $testStudentId = $student['id'];
    echo "✓ Found student: {$student['first_name']} {$student['last_name']} (ID: $testStudentId)\n\n";
}

// Step 2: Simulate exam result data
echo "Step 2: Preparing test exam result data...\n";

$examId = null; // Mock exam (no specific exam ID)
$score = 8.00; // 8 correct answers (DECIMAL)
$totalMarks = 10; // 10 total questions
$percentage = 80.00; // 80% (DECIMAL)
$timeTaken = 300; // 5 minutes in seconds
$startTime = time() - $timeTaken;

echo "  - Student ID: $testStudentId\n";
echo "  - Exam ID: " . ($examId ?? 'NULL (Mock Exam)') . "\n";
echo "  - Score: $score / $totalMarks\n";
echo "  - Percentage: $percentage%\n";
echo "  - Time Taken: $timeTaken seconds\n\n";

// Step 3: Insert the exam result
echo "Step 3: Inserting exam result...\n";

$resultSql = "INSERT INTO exam_results 
              (exam_id, student_id, score, total_marks, percentage, time_taken, status, started_at, submitted_at) 
              VALUES (?, ?, ?, ?, ?, ?, 'submitted', FROM_UNIXTIME(?), NOW())";

$resultStmt = $conn->prepare($resultSql);

if (!$resultStmt) {
    die("✗ Failed to prepare statement: " . $conn->error . "\n");
}

$resultStmt->bind_param('iididii', $examId, $testStudentId, $score, $totalMarks, $percentage, $timeTaken, $startTime);

if ($resultStmt->execute()) {
    $insertedId = $conn->insert_id;
    echo "✓ Successfully inserted exam result with ID: $insertedId\n\n";
} else {
    die("✗ Failed to insert exam result: " . $resultStmt->error . "\n");
}

$resultStmt->close();

// Step 4: Verify the inserted data
echo "Step 4: Verifying inserted data...\n";

$verifySql = "SELECT 
                er.id,
                er.exam_id,
                er.student_id,
                er.score,
                er.total_marks,
                er.percentage,
                er.time_taken,
                er.status,
                er.started_at,
                er.submitted_at,
                er.created_at,
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
    
    echo "✓ Exam result verified:\n";
    echo str_repeat("-", 70) . "\n";
    echo "  Result ID        : " . $data['id'] . "\n";
    echo "  Exam ID          : " . ($data['exam_id'] ?? 'NULL (Mock Exam)') . "\n";
    echo "  Student ID       : " . $data['student_id'] . "\n";
    echo "  Student Name     : {$data['first_name']} {$data['last_name']}\n";
    echo "  Student Email    : {$data['email']}\n";
    echo "  Score            : " . $data['score'] . " / " . $data['total_marks'] . "\n";
    echo "  Percentage       : " . $data['percentage'] . "%\n";
    echo "  Time Taken       : " . $data['time_taken'] . " seconds\n";
    echo "  Status           : " . $data['status'] . "\n";
    echo "  Started At       : " . $data['started_at'] . "\n";
    echo "  Submitted At     : " . $data['submitted_at'] . "\n";
    echo "  Created At       : " . $data['created_at'] . "\n";
    echo str_repeat("-", 70) . "\n\n";
    
    // Verify data types and values
    $allGood = true;
    
    if ($data['exam_id'] !== null && $data['exam_id'] != $examId) {
        echo "✗ Exam ID mismatch\n";
        $allGood = false;
    }
    
    if ($data['student_id'] != $testStudentId) {
        echo "✗ Student ID mismatch\n";
        $allGood = false;
    }
    
    if ($data['score'] != $score) {
        echo "✗ Score mismatch\n";
        $allGood = false;
    }
    
    if ($data['total_marks'] != $totalMarks) {
        echo "✗ Total marks mismatch\n";
        $allGood = false;
    }
    
    if ($data['percentage'] != $percentage) {
        echo "✗ Percentage mismatch\n";
        $allGood = false;
    }
    
    if ($data['status'] !== 'submitted') {
        echo "✗ Status should be 'submitted'\n";
        $allGood = false;
    }
    
    if ($allGood) {
        echo "✓ All data fields verified successfully!\n\n";
    }
} else {
    echo "✗ Could not retrieve inserted exam result\n";
}

$verifyStmt->close();

// Step 5: Show recent exam results
echo "Step 5: Showing recent exam results for this student...\n";
echo str_repeat("-", 70) . "\n";

$recentSql = "SELECT 
                id,
                exam_id,
                score,
                total_marks,
                percentage,
                time_taken,
                status,
                submitted_at
              FROM exam_results
              WHERE student_id = ?
              ORDER BY submitted_at DESC
              LIMIT 5";

$recentStmt = $conn->prepare($recentSql);
$recentStmt->bind_param('i', $testStudentId);
$recentStmt->execute();
$recentResult = $recentStmt->get_result();

if ($recentResult->num_rows > 0) {
    printf("%-8s %-12s %-15s %-12s %-12s %-20s\n", 
        "ID", "Exam ID", "Score", "Percentage", "Status", "Submitted At");
    echo str_repeat("-", 70) . "\n";
    
    while ($row = $recentResult->fetch_assoc()) {
        printf("%-8s %-12s %-15s %-12s %-12s %-20s\n",
            $row['id'],
            $row['exam_id'] ?? 'Mock',
            $row['score'] . '/' . $row['total_marks'],
            $row['percentage'] . '%',
            $row['status'],
            $row['submitted_at']
        );
    }
    echo str_repeat("-", 70) . "\n";
} else {
    echo "No exam results found for this student.\n";
}

$recentStmt->close();
$conn->close();

echo "\n=== Test completed successfully! ===\n";
?>

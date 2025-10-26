<?php
/**
 * Test file to verify student exam results are being saved
 */

require_once 'config/database.php';

$conn = getDBConnection();

echo "=== Student Exam Results Test ===\n\n";

// Check if exam_results table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'exam_results'");
if ($tableCheck->num_rows > 0) {
    echo "✓ exam_results table exists\n\n";
    
    // Show recent results
    echo "Recent exam results (last 10):\n";
    echo str_repeat("-", 100) . "\n";
    
    $sql = "SELECT 
                er.*,
                s.first_name,
                s.last_name,
                s.email,
                s.batch_number
            FROM exam_results er
            LEFT JOIN students s ON er.student_id = s.id
            ORDER BY er.created_at DESC
            LIMIT 10";
    
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo sprintf(
                "ID: %d | Student: %s %s (%s) | Batch: %s\n" .
                "   Score: %d/%d (%.2f%%) | Time: %d sec | Status: %s\n" .
                "   Submitted: %s\n",
                $row['id'],
                $row['first_name'] ?? 'Unknown',
                $row['last_name'] ?? 'Unknown',
                $row['email'] ?? 'N/A',
                $row['batch_number'] ?? 'N/A',
                $row['score'],
                $row['total_marks'],
                $row['percentage'],
                $row['time_taken'] ?? 0,
                $row['status'],
                $row['submitted_at']
            );
            echo str_repeat("-", 100) . "\n";
        }
    } else {
        echo "No exam results found yet.\n";
        echo "Take a mock exam as a student to see results here.\n";
    }
    
    // Show statistics
    echo "\n=== Statistics ===\n";
    $stats = $conn->query("SELECT 
                            COUNT(*) as total_attempts,
                            AVG(percentage) as avg_percentage,
                            MAX(percentage) as max_percentage,
                            MIN(percentage) as min_percentage
                        FROM exam_results");
    
    if ($stats && $row = $stats->fetch_assoc()) {
        echo sprintf(
            "Total Attempts: %d\n" .
            "Average Score: %.2f%%\n" .
            "Highest Score: %.2f%%\n" .
            "Lowest Score: %.2f%%\n",
            $row['total_attempts'],
            $row['avg_percentage'] ?? 0,
            $row['max_percentage'] ?? 0,
            $row['min_percentage'] ?? 0
        );
    }
    
} else {
    echo "✗ exam_results table does not exist!\n";
    echo "Please create the table first.\n";
}

$conn->close();

echo "\n=== Test Complete ===\n";

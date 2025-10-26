<?php
// Check exam_results table structure
require_once 'config/database.php';

$conn = getDBConnection();

echo "Checking exam_results table structure...\n\n";

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'exam_results'");
if ($result->num_rows > 0) {
    echo "✓ exam_results table exists\n\n";
    
    // Describe table
    $descResult = $conn->query("DESCRIBE exam_results");
    echo "Table Structure:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-20s %-20s %-10s %-10s %-10s %-10s\n", "Field", "Type", "Null", "Key", "Default", "Extra");
    echo str_repeat("-", 80) . "\n";
    
    while ($row = $descResult->fetch_assoc()) {
        printf("%-20s %-20s %-10s %-10s %-10s %-10s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'], 
            $row['Key'], 
            $row['Default'] ?? 'NULL', 
            $row['Extra']
        );
    }
    echo str_repeat("-", 80) . "\n\n";
    
    // Show sample data
    $sampleResult = $conn->query("SELECT * FROM exam_results LIMIT 5");
    if ($sampleResult->num_rows > 0) {
        echo "Sample Data (first 5 rows):\n";
        while ($row = $sampleResult->fetch_assoc()) {
            print_r($row);
        }
    } else {
        echo "No data in exam_results table yet.\n";
    }
} else {
    echo "✗ exam_results table does NOT exist!\n";
    echo "You need to create it first.\n\n";
    
    echo "Suggested CREATE TABLE statement:\n";
    echo "-----------------------------------\n";
    $sql = "CREATE TABLE exam_results (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id INT(11) UNSIGNED NULL COMMENT 'NULL for mock exams',
    student_id INT(11) UNSIGNED NOT NULL,
    score INT(11) NOT NULL COMMENT 'Number of correct answers',
    total_marks INT(11) NOT NULL COMMENT 'Total possible marks',
    percentage DECIMAL(5,2) NOT NULL COMMENT 'Percentage score',
    time_taken INT(11) NOT NULL COMMENT 'Time taken in seconds',
    status ENUM('in_progress', 'submitted', 'graded') DEFAULT 'submitted',
    started_at TIMESTAMP NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_exam (exam_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    echo $sql . "\n";
}

$conn->close();
?>

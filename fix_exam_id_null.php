<?php
// Direct ALTER to allow NULL for exam_id
require_once 'config/database.php';

$conn = getDBConnection();

echo "Updating exam_id column to allow NULL values...\n";

$sql = "ALTER TABLE exam_results MODIFY COLUMN exam_id INT(11) UNSIGNED NULL COMMENT 'NULL for mock exams'";

if ($conn->query($sql)) {
    echo "✓ Successfully updated exam_id column to allow NULL\n\n";
} else {
    echo "✗ Error: " . $conn->error . "\n\n";
}

// Verify
echo "Verifying change:\n";
$result = $conn->query("DESCRIBE exam_results");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['Field'] === 'exam_id') {
            echo "Field: " . $row['Field'] . "\n";
            echo "Type: " . $row['Type'] . "\n";
            echo "Null: " . $row['Null'] . "\n";
            echo "Key: " . $row['Key'] . "\n";
            echo "Default: " . ($row['Default'] ?? 'NULL') . "\n";
            break;
        }
    }
}

$conn->close();
?>

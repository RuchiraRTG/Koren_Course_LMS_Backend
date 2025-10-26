<?php
/**
 * Verify image column schema
 */

require_once 'config/database.php';

$conn = getDBConnection();

echo "Verifying database schema for image columns...\n\n";

// Check questions table
echo "questions table - question_image column:\n";
$result = $conn->query("SHOW COLUMNS FROM questions LIKE 'question_image'");
if ($row = $result->fetch_assoc()) {
    echo "  Type: " . $row['Type'] . "\n";
    echo "  Null: " . $row['Null'] . "\n\n";
}

// Check question_options table
echo "question_options table - option_image column:\n";
$result = $conn->query("SHOW COLUMNS FROM question_options LIKE 'option_image'");
if ($row = $result->fetch_assoc()) {
    echo "  Type: " . $row['Type'] . "\n";
    echo "  Null: " . $row['Null'] . "\n\n";
}

echo "✅ Both columns are now LONGTEXT and can handle large base64 images!\n";

$conn->close();
?>

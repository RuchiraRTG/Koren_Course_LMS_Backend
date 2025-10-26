<?php
/**
 * Update image columns to store file paths instead of base64
 */

require_once 'config/database.php';

$conn = getDBConnection();

echo "Updating database schema for image columns to store file paths...\n\n";

try {
    // Update questions table - question_image column to store file path
    echo "1. Updating questions.question_image column...\n";
    $sql = "ALTER TABLE questions MODIFY COLUMN question_image VARCHAR(255) NULL";
    if ($conn->query($sql)) {
        echo "   ✓ Successfully updated questions.question_image to VARCHAR(255)\n";
    } else {
        echo "   ✗ Error: " . $conn->error . "\n";
    }

    // Update question_options table - option_image column to store file path
    echo "2. Updating question_options.option_image column...\n";
    $sql = "ALTER TABLE question_options MODIFY COLUMN option_image VARCHAR(255) NULL";
    if ($conn->query($sql)) {
        echo "   ✓ Successfully updated question_options.option_image to VARCHAR(255)\n";
    } else {
        echo "   ✗ Error: " . $conn->error . "\n";
    }

    echo "\n✅ Database schema update completed!\n";
    echo "Image columns now store file paths (e.g., 'uploads/images/questions/img_123.png')\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>

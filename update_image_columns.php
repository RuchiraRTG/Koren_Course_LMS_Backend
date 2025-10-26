<?php
/**
 * Update image columns to support large base64 images
 */

require_once 'config/database.php';

$conn = getDBConnection();

echo "Updating database schema for image columns...\n\n";

try {
    // Update questions table - question_image column
    echo "1. Updating questions.question_image column...\n";
    $sql = "ALTER TABLE questions MODIFY COLUMN question_image LONGTEXT NULL";
    if ($conn->query($sql)) {
        echo "   ✓ Successfully updated questions.question_image to LONGTEXT\n";
    } else {
        echo "   ✗ Error: " . $conn->error . "\n";
    }

    // Update question_options table - option_image column
    echo "2. Updating question_options.option_image column...\n";
    $sql = "ALTER TABLE question_options MODIFY COLUMN option_image LONGTEXT NULL";
    if ($conn->query($sql)) {
        echo "   ✓ Successfully updated question_options.option_image to LONGTEXT\n";
    } else {
        echo "   ✗ Error: " . $conn->error . "\n";
    }

    echo "\n✅ Database schema update completed!\n";
    echo "You can now upload large images (base64 encoded) for questions and options.\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>

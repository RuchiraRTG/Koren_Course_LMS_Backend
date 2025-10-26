<?php
require_once 'config/database.php';

$conn = getDBConnection();

echo "Checking how images are stored in the database...\n\n";

// Check questions with images
echo "=== QUESTIONS TABLE ===\n";
$result = $conn->query("SELECT id, question_text, question_image FROM questions WHERE question_image IS NOT NULL LIMIT 3");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "\nQuestion ID: " . $row['id'] . "\n";
        echo "Text: " . substr($row['question_text'], 0, 50) . "...\n";
        echo "Image Path: " . $row['question_image'] . "\n";
        echo "Storage Type: " . (strpos($row['question_image'], 'data:image') !== false ? 'BASE64 ❌' : 'FILE PATH ✅') . "\n";
    }
} else {
    echo "No questions with images found.\n";
}

// Check options with images
echo "\n\n=== QUESTION_OPTIONS TABLE ===\n";
$result = $conn->query("SELECT id, question_id, option_text, option_image FROM question_options WHERE option_image IS NOT NULL LIMIT 3");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "\nOption ID: " . $row['id'] . " (Question #" . $row['question_id'] . ")\n";
        echo "Text: " . substr($row['option_text'], 0, 30) . "\n";
        echo "Image Path: " . $row['option_image'] . "\n";
        echo "Storage Type: " . (strpos($row['option_image'], 'data:image') !== false ? 'BASE64 ❌' : 'FILE PATH ✅') . "\n";
    }
} else {
    echo "No options with images found.\n";
}

$conn->close();
?>

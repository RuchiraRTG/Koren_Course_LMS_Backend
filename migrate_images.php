<?php
/**
 * Migrate existing base64 images to file storage
 */

require_once 'config/database.php';

$conn = getDBConnection();

echo "Migrating existing base64 images to file storage...\n\n";

// Helper function to save base64 image to file
function saveBase64ToFile($base64String, $folder = 'questions') {
    if (empty($base64String) || $base64String === 'null' || $base64String === null) {
        return null;
    }
    
    // Check if it's already a file path
    if (!preg_match('/^data:image\/(\w+);base64,/', $base64String) && strpos($base64String, 'uploads/') === 0) {
        return $base64String;
    }
    
    // Extract the image data
    if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $matches)) {
        $imageType = $matches[1] ?? 'png';
        $base64String = substr($base64String, strpos($base64String, ',') + 1);
    } else {
        // Assume it's raw base64 without data:image prefix
        $imageType = 'png';
    }
    
    $imageData = base64_decode($base64String);
    
    if ($imageData === false || strlen($imageData) < 100) {
        return null;
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/uploads/images/' . $folder;
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $filename = uniqid('img_', true) . '_' . time() . '.' . $imageType;
    $filepath = $uploadDir . '/' . $filename;
    
    // Save the file
    if (file_put_contents($filepath, $imageData)) {
        return 'uploads/images/' . $folder . '/' . $filename;
    }
    
    return null;
}

try {
    $migratedCount = 0;
    $errorCount = 0;
    
    // Migrate question images
    echo "1. Migrating question images...\n";
    $result = $conn->query("SELECT id, question_image FROM questions WHERE question_image IS NOT NULL AND question_image != ''");
    
    while ($row = $result->fetch_assoc()) {
        if (strlen($row['question_image']) > 255) {
            // It's likely base64, migrate it
            $filePath = saveBase64ToFile($row['question_image'], 'questions');
            if ($filePath) {
                $updateSql = "UPDATE questions SET question_image = ? WHERE id = ?";
                $stmt = $conn->prepare($updateSql);
                $stmt->bind_param('si', $filePath, $row['id']);
                if ($stmt->execute()) {
                    $migratedCount++;
                    echo "   ✓ Migrated question #" . $row['id'] . "\n";
                } else {
                    $errorCount++;
                    echo "   ✗ Failed to update question #" . $row['id'] . "\n";
                }
                $stmt->close();
            } else {
                // Set to null if migration failed
                $conn->query("UPDATE questions SET question_image = NULL WHERE id = " . $row['id']);
                echo "   ⚠ Cleared invalid image for question #" . $row['id'] . "\n";
            }
        }
    }
    
    // Migrate option images
    echo "\n2. Migrating option images...\n";
    $result = $conn->query("SELECT id, question_id, option_image FROM question_options WHERE option_image IS NOT NULL AND option_image != ''");
    
    while ($row = $result->fetch_assoc()) {
        if (strlen($row['option_image']) > 255) {
            // It's likely base64, migrate it
            $filePath = saveBase64ToFile($row['option_image'], 'options');
            if ($filePath) {
                $updateSql = "UPDATE question_options SET option_image = ? WHERE id = ?";
                $stmt = $conn->prepare($updateSql);
                $stmt->bind_param('si', $filePath, $row['id']);
                if ($stmt->execute()) {
                    $migratedCount++;
                    echo "   ✓ Migrated option #" . $row['id'] . " for question #" . $row['question_id'] . "\n";
                } else {
                    $errorCount++;
                    echo "   ✗ Failed to update option #" . $row['id'] . "\n";
                }
                $stmt->close();
            } else {
                // Set to null if migration failed
                $conn->query("UPDATE question_options SET option_image = NULL WHERE id = " . $row['id']);
                echo "   ⚠ Cleared invalid image for option #" . $row['id'] . "\n";
            }
        }
    }
    
    echo "\n✅ Migration completed!\n";
    echo "   Total migrated: $migratedCount\n";
    echo "   Errors: $errorCount\n";
    
    // Now update the schema
    echo "\n3. Updating database schema...\n";
    
    $sql = "ALTER TABLE questions MODIFY COLUMN question_image VARCHAR(255) NULL";
    if ($conn->query($sql)) {
        echo "   ✓ Updated questions.question_image to VARCHAR(255)\n";
    } else {
        echo "   ✗ Error: " . $conn->error . "\n";
    }
    
    $sql = "ALTER TABLE question_options MODIFY COLUMN option_image VARCHAR(255) NULL";
    if ($conn->query($sql)) {
        echo "   ✓ Updated question_options.option_image to VARCHAR(255)\n";
    } else {
        echo "   ✗ Error: " . $conn->error . "\n";
    }
    
    echo "\n✅ All done! Images are now stored as files.\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>

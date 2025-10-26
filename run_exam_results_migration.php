<?php
// Run the migration for exam_results table
require_once 'config/database.php';

$conn = getDBConnection();

echo "Running migration for exam_results table...\n\n";

// Read the migration file
$sqlFile = __DIR__ . '/database/migrate_exam_results.sql';
if (!file_exists($sqlFile)) {
    die("Error: Migration file not found at $sqlFile\n");
}

$sql = file_get_contents($sqlFile);

// Split by semicolons but be careful with stored procedures
$statements = explode(';', $sql);

foreach ($statements as $statement) {
    $statement = trim($statement);
    
    // Skip empty statements and comments
    if (empty($statement) || strpos($statement, '--') === 0) {
        continue;
    }
    
    // Skip USE statements as we're already connected to the database
    if (stripos($statement, 'USE ') === 0) {
        continue;
    }
    
    try {
        if ($conn->query($statement)) {
            // Check if it's a SELECT statement to display results
            if (stripos($statement, 'SELECT') === 0) {
                $result = $conn->query($statement);
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        print_r($row);
                    }
                }
            }
        } else {
            if ($conn->error && strpos($conn->error, 'Duplicate') === false) {
                echo "Warning: " . $conn->error . "\n";
            }
        }
    } catch (Exception $e) {
        echo "Warning: " . $e->getMessage() . "\n";
    }
}

echo "\n✓ Migration completed!\n\n";

// Verify the changes
echo "Current exam_results table structure:\n";
echo str_repeat("-", 80) . "\n";

$result = $conn->query("DESCRIBE exam_results");
if ($result) {
    printf("%-20s %-25s %-10s %-10s %-15s %-15s\n", "Field", "Type", "Null", "Key", "Default", "Extra");
    echo str_repeat("-", 80) . "\n";
    
    while ($row = $result->fetch_assoc()) {
        printf("%-20s %-25s %-10s %-10s %-15s %-15s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'], 
            $row['Key'], 
            $row['Default'] ?? 'NULL', 
            $row['Extra']
        );
    }
}

$conn->close();
echo "\nDone!\n";
?>

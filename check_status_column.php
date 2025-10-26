<?php
require_once 'config/database.php';

$conn = getDBConnection();

$sql = "SHOW COLUMNS FROM exam_results WHERE Field = 'status'";
$result = $conn->query($sql);

if ($row = $result->fetch_assoc()) {
    echo "Column: status\n";
    echo "Type: " . $row['Type'] . "\n";
    echo "Null: " . $row['Null'] . "\n";
    echo "Default: " . $row['Default'] . "\n";
}

$conn->close();
?>

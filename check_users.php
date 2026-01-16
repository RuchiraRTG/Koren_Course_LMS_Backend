<?php
require_once 'config/database.php';

$conn = getDBConnection();

// Check all users
$sql = "SELECT id, first_name, last_name, email, is_active FROM users ORDER BY id";
$result = $conn->query($sql);

echo "All users in database:\n";
echo str_repeat("-", 80) . "\n";
printf("%-5s %-20s %-30s %-10s\n", "ID", "Name", "Email", "Active");
echo str_repeat("-", 80) . "\n";

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        printf("%-5s %-20s %-30s %-10s\n", 
            $row['id'], 
            $row['first_name'] . ' ' . $row['last_name'],
            $row['email'],
            $row['is_active'] ? 'Yes' : 'No'
        );
    }
} else {
    echo "No users found\n";
}

echo str_repeat("-", 80) . "\n";

// Check students who took exams but don't have user accounts
$sql2 = "SELECT DISTINCT er.student_id 
         FROM exam_results er 
         LEFT JOIN users u ON er.student_id = u.id 
         WHERE u.id IS NULL 
         ORDER BY er.student_id";
$result2 = $conn->query($sql2);

echo "\nStudents who took exams but don't have user accounts:\n";
echo str_repeat("-", 80) . "\n";

if ($result2->num_rows > 0) {
    while($row = $result2->fetch_assoc()) {
        echo "Student ID: " . $row['student_id'] . " (needs to be added to users table)\n";
    }
} else {
    echo "All students have user accounts\n";
}

$conn->close();
?>

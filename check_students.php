<?php
require_once 'config/database.php';

$conn = getDBConnection();

echo "Checking for existing students...\n\n";

$result = $conn->query("SELECT id, first_name, last_name, email, role FROM users WHERE role = 'student' LIMIT 5");

if ($result->num_rows > 0) {
    echo "Found " . $result->num_rows . " student(s):\n";
    echo str_repeat("-", 70) . "\n";
    printf("%-8s %-15s %-15s %-30s\n", "ID", "First Name", "Last Name", "Email");
    echo str_repeat("-", 70) . "\n";
    
    while ($row = $result->fetch_assoc()) {
        printf("%-8s %-15s %-15s %-30s\n", 
            $row['id'],
            $row['first_name'],
            $row['last_name'],
            $row['email']
        );
    }
    echo str_repeat("-", 70) . "\n";
} else {
    echo "No students found. Checking all users...\n\n";
    
    $allUsers = $conn->query("SELECT id, first_name, last_name, email, role FROM users LIMIT 5");
    
    if ($allUsers->num_rows > 0) {
        echo "Found " . $allUsers->num_rows . " user(s):\n";
        echo str_repeat("-", 70) . "\n";
        printf("%-8s %-15s %-15s %-30s %-10s\n", "ID", "First Name", "Last Name", "Email", "Role");
        echo str_repeat("-", 70) . "\n";
        
        while ($row = $allUsers->fetch_assoc()) {
            printf("%-8s %-15s %-15s %-30s %-10s\n", 
                $row['id'],
                $row['first_name'],
                $row['last_name'],
                $row['email'],
                $row['role']
            );
        }
        echo str_repeat("-", 70) . "\n";
    } else {
        echo "No users found in database.\n";
    }
}

$conn->close();
?>

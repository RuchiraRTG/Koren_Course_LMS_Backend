<?php
// Diagnostic Script for Questions API
// Place this file in your htdocs folder and access via: http://localhost/backend_diagnostic.php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$diagnostics = [
    'status' => 'running',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// Check 1: PHP Version
$diagnostics['checks']['php_version'] = [
    'status' => 'ok',
    'value' => PHP_VERSION
];

// Check 2: Database Connection
try {
    require_once 'config/database.php';
    $conn = getDBConnection();
    $diagnostics['checks']['database_connection'] = [
        'status' => 'ok',
        'message' => 'Connected successfully'
    ];
    
    // Check 3: Tables exist
    $tables = ['questions', 'question_options', 'mcq_question_answers', 'voice_question_answers'];
    $existing_tables = [];
    $missing_tables = [];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            $existing_tables[] = $table;
            
            // Get column names
            $columns_result = $conn->query("SHOW COLUMNS FROM $table");
            $columns = [];
            while ($col = $columns_result->fetch_assoc()) {
                $columns[] = $col['Field'];
            }
            $diagnostics['checks']["table_$table"] = [
                'status' => 'ok',
                'columns' => $columns
            ];
        } else {
            $missing_tables[] = $table;
            $diagnostics['checks']["table_$table"] = [
                'status' => 'error',
                'message' => 'Table does not exist'
            ];
        }
    }
    
    $diagnostics['checks']['tables_summary'] = [
        'existing' => $existing_tables,
        'missing' => $missing_tables
    ];
    
    // Check 4: Count records
    if (in_array('questions', $existing_tables)) {
        $result = $conn->query("SELECT COUNT(*) as count FROM questions");
        $row = $result->fetch_assoc();
        $diagnostics['checks']['question_count'] = [
            'status' => 'ok',
            'value' => $row['count']
        ];
    }
    
    $conn->close();
    
} catch (Exception $e) {
    $diagnostics['checks']['database_connection'] = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

// Check 5: Required files
$required_files = [
    'config/database.php',
    'questions.php'
];

foreach ($required_files as $file) {
    $diagnostics['checks']["file_$file"] = [
        'status' => file_exists($file) ? 'ok' : 'error',
        'exists' => file_exists($file)
    ];
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT);
?>

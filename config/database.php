<?php
/**
 * Database Configuration File
 * Configure your database connection settings here
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Root@1234');
define('DB_NAME', 'koren_lms');

// Create database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8mb4 for proper character encoding
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

// Create database and users table if they don't exist
function initializeDatabase() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Create database if not exists
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    $conn->query($sql);
    
    $conn->select_db(DB_NAME);
    
    // Create users table (includes role column for RBAC)
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        nic_number VARCHAR(20) NOT NULL UNIQUE,
        phone_number VARCHAR(20) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        is_active TINYINT(1) DEFAULT 1,
        role VARCHAR(20) NOT NULL DEFAULT 'user',
        INDEX idx_email (email),
        INDEX idx_nic (nic_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        // Ensure schema is up to date for existing databases
        ensureUsersTableSchema($conn);
        return true;
    } else {
        error_log("Error creating table: " . $conn->error);
        return false;
    }
}

/**
 * Ensure the users table has required columns for RBAC when upgrading
 */
function ensureUsersTableSchema($conn = null) {
    $shouldClose = false;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            error_log('Schema check connection failed: ' . $conn->connect_error);
            return;
        }
        $shouldClose = true;
    }

    // Check for 'role' column and add it if missing
    $dbName = DB_NAME;
    $checkSql = "SELECT COUNT(*) AS cnt FROM information_schema.columns WHERE table_schema = ? AND table_name = 'users' AND column_name = 'role'";
    if ($stmt = $conn->prepare($checkSql)) {
        $stmt->bind_param('s', $dbName);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            if ((int)$row['cnt'] === 0) {
                $alter = "ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user' AFTER is_active";
                if (!$conn->query($alter)) {
                    error_log('Failed to add role column: ' . $conn->error);
                }
            }
        }
        $stmt->close();
    }

    if ($shouldClose) {
        $conn->close();
    }
}

?>

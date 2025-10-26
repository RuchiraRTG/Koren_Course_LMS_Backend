<?php
require_once __DIR__ . '/config/database.php';

try {
	$conn = getDBConnection();
	echo "Database connection successful.";
	$conn->close();
} catch (Exception $e) {
	echo "Database connection failed: " . $e->getMessage();
}

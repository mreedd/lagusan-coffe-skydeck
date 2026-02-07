<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/utils.php';

if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// For DB escaping, use db_sanitize($data) from includes/utils.php
?>

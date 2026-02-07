<?php
require_once 'config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

echo 'Connected to database successfully<br>';

// Add new columns to sales table
$sql = 'ALTER TABLE sales
    ADD COLUMN IF NOT EXISTS subtotal DECIMAL(10, 2) DEFAULT 0 AFTER customer_name,
    ADD COLUMN IF NOT EXISTS discount_type VARCHAR(20) DEFAULT NULL AFTER subtotal,
    ADD COLUMN IF NOT EXISTS discount_percentage DECIMAL(5, 2) DEFAULT 0 AFTER discount_type,
    ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10, 2) DEFAULT 0 AFTER discount_percentage,
    ADD COLUMN IF NOT EXISTS discount_id_number VARCHAR(50) DEFAULT NULL AFTER discount_amount,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at';

if ($conn->query($sql) === TRUE) {
    echo 'Sales table updated successfully<br>';
} else {
    echo 'Error updating sales table: ' . $conn->error . '<br>';
}

// Update status enum to include 'pending'
$sql = 'ALTER TABLE sales MODIFY COLUMN status ENUM(\'pending\', \'completed\', \'cancelled\', \'refunded\') DEFAULT \'pending\'';
if ($conn->query($sql) === TRUE) {
    echo 'Status enum updated successfully<br>';
} else {
    echo 'Error updating status enum: ' . $conn->error . '<br>';
}

$conn->close();
echo 'Database update completed<br>';
?>

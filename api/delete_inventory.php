<?php
require_once '../session_check.php';
require_once '../config.php';
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if (!has_role('admin') && !has_role('staff')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$inventory_id = intval($input['inventory_id'] ?? 0);
$password = $input['password'] ?? null;

if ($inventory_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid inventory ID']);
    exit;
}

// For staff users, verify password
if (has_role('staff')) {
    if (!$password) {
        echo json_encode(['success' => false, 'message' => 'Password is required for staff users']);
        exit;
    }

    // Get current user's password hash
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid password']);
        exit;
    }
}

// Check if item exists
$stmt = $conn->prepare("SELECT id FROM inventory WHERE id = ?");
$stmt->bind_param("i", $inventory_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Inventory item not found']);
    exit;
}

// Delete related records first to allow deletion
$conn->begin_transaction();

try {
    // Delete from reorder_list
    $stmt = $conn->prepare("DELETE FROM reorder_list WHERE inventory_id = ?");
    $stmt->bind_param("i", $inventory_id);
    $stmt->execute();

    // Delete from wastage_log
    $stmt = $conn->prepare("DELETE FROM wastage_log WHERE inventory_id = ?");
    $stmt->bind_param("i", $inventory_id);
    $stmt->execute();

    // Delete from inventory_log
    $stmt = $conn->prepare("DELETE FROM inventory_log WHERE inventory_id = ?");
    $stmt->bind_param("i", $inventory_id);
    $stmt->execute();

    // Delete the inventory item
    $stmt = $conn->prepare("DELETE FROM inventory WHERE id = ?");
    $stmt->bind_param("i", $inventory_id);
    $stmt->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Inventory item and related records deleted successfully']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to delete inventory item: ' . $e->getMessage()]);
}

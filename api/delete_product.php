<?php
require_once '../config.php';
require_once '../includes/db_connect.php';
require_once '../session_check.php';

header('Content-Type: application/json');

if (!has_role('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'Product ID required']);
    exit;
}

$id = $data['id'];

try {
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM sale_items WHERE product_id = ?");
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        // Product has sales history, mark as unavailable instead of deleting
        $stmt = $conn->prepare("UPDATE products SET status = 'unavailable' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Product has sales history and has been marked as unavailable instead of deleted'
        ]);
    } else {
        // No sales history, safe to delete
        // First delete any product ingredients
        $delete_ingredients = $conn->prepare("DELETE FROM product_ingredients WHERE product_id = ?");
        $delete_ingredients->bind_param("i", $id);
        $delete_ingredients->execute();
        
        // Then delete the product
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting product: ' . $e->getMessage()
    ]);
}
?>

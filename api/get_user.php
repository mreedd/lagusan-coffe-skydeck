<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response

// Start output buffering to catch any errors
ob_start();

try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../includes/db_connect.php';
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Load functions
    require_once __DIR__ . '/../includes/functions.php';
    
    // Clear any output buffer
    ob_end_clean();
    
    header('Content-Type: application/json');
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    
    // Check if user is admin
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin access required']);
        exit;
    }
    
    $user_id = $_GET['id'] ?? 0;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT id, username, full_name, role, status FROM users WHERE id = ?");
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    echo json_encode(['success' => true, 'user' => $user]);
    
} catch (Exception $e) {
    // Clear output buffer if it exists
    if (ob_get_length()) ob_end_clean();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_start();

try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../includes/db_connect.php';
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    require_once __DIR__ . '/../includes/functions.php';
    
    ob_end_clean();
    
    header('Content-Type: application/json');
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    
    // Check if user is admin
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $user_id = $_POST['user_id'] ?? null;
    $username = $_POST['username'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? 'cashier';
    $password = $_POST['password'] ?? '';
    
    if (empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Username is required']);
        exit;
    }
    
    if ($user_id) {
        // Update existing user
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, password = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $username, $full_name, $email, $role, $hashed_password, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, role = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $username, $full_name, $email, $role, $user_id);
        }
    } else {
        // Create new user
        if (empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Password is required for new users']);
            exit;
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, role, password, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("sssss", $username, $full_name, $email, $role, $hashed_password);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
} catch (Exception $e) {
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Check authentication
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check admin role
if (!has_role('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';
    $category = $_POST['category'] ?? '';
    $price = $_POST['price'] ?? 0;
    $cost = $_POST['cost'] ?? null;
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? 'available';
    $existing_image = $_POST['existing_image'] ?? '';
    
    // Validate required fields
    if (empty($name) || empty($category) || empty($price)) {
        echo json_encode(['success' => false, 'message' => 'Name, category, and price are required']);
        exit;
    }
    
    // Handle image upload
    $image_path = $existing_image; // Keep existing image by default
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../images/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.']);
            exit;
        }
        
        // Generate unique filename
        $filename = uniqid('product_') . '.' . $file_extension;
        $upload_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            $image_path = 'images/' . $filename;
            
            // Delete old image if exists and is different
            if ($existing_image && $existing_image !== $image_path) {
                $old_image_path = __DIR__ . '/../' . $existing_image;
                if (file_exists($old_image_path)) {
                    unlink($old_image_path);
                }
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
            exit;
        }
    }
    
    if ($id) {
        // Update existing product
        $stmt = $conn->prepare("UPDATE products SET name = ?, category = ?, price = ?, cost = ?, description = ?, status = ?, image = ? WHERE id = ?");
        $stmt->bind_param("ssddsssi", $name, $category, $price, $cost, $description, $status, $image_path, $id);
    } else {
        // Insert new product
        $stmt = $conn->prepare("INSERT INTO products (name, category, price, cost, description, status, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssddsss", $name, $category, $price, $cost, $description, $status, $image_path);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Product saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

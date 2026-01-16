<?php
include 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

// Function to log activity
function logActivity($db, $user_id, $action, $details) {
    $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) 
                         VALUES (?, ?, ?, ?, ?)");
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $stmt->bind_param("issss", $user_id, $action, $details, $ip_address, $user_agent);
    $stmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $items = trim($_POST['items'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $status = trim($_POST['status'] ?? 'Available');
    $critical_stock = intval($_POST['critical_stock'] ?? 10);
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'Unknown User';
    
    // Validate required fields
    if (empty($name) || empty($items) || $price <= 0 || empty($category) || empty($date)) {
        echo json_encode(['success' => false, 'error' => 'All fields are required and price must be greater than 0']);
        exit();
    }
    
    // Validate date format
    if (!strtotime($date)) {
        echo json_encode(['success' => false, 'error' => 'Invalid date format']);
        exit();
    }
    
    // Check for duplicate product name
    $check_stmt = $db->prepare("SELECT id FROM products WHERE name = ?");
    $check_stmt->bind_param("s", $name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Product name already exists']);
        exit();
    }
    
    // Handle file upload
    $photo_path = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['photo']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            echo json_encode(['success' => false, 'error' => 'Only JPG, PNG, GIF, and WebP images are allowed']);
            exit();
        }
        
        // Validate file size (max 5MB)
        if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Image size must be less than 5MB']);
            exit();
        }
        
        $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name) . '.' . $file_extension;
        $photo_path = $upload_dir . $filename;
        
        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
            echo json_encode(['success' => false, 'error' => 'Failed to upload photo']);
            exit();
        }
    }
    
    // Insert product with critical_stock
    $stmt = $db->prepare("
        INSERT INTO products (name, items, price, category, date, status, photo, critical_stock) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssdssssi", $name, $items, $price, $category, $date, $status, $photo_path, $critical_stock);
    
    if ($stmt->execute()) {
        $product_id = $db->insert_id;
        
        // Log the activity
        $details = "Added new product: {$name} (ID: {$product_id}, Category: {$category}, Price: ₱{$price}, Status: {$status}, Critical Stock: {$critical_stock})";
        logActivity($db, $user_id, 'PRODUCT_ADDED', $details);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Product added successfully',
            'product_id' => $product_id
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
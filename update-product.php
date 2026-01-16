<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

// Required fields
if (!isset($_POST['id']) || !isset($_POST['name']) || !isset($_POST['items']) || !isset($_POST['price']) || !isset($_POST['category']) || !isset($_POST['date']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

$productId = intval($_POST['id']);
$name = trim($_POST['name']);
$items = trim($_POST['items']);
$price = floatval($_POST['price']);
$category = trim($_POST['category']);
$critical_stock = isset($_POST['critical_stock']) ? intval($_POST['critical_stock']) : 10;
$date = trim($_POST['date']);
$status = trim($_POST['status']);

// Validate inputs
if (empty($name) || empty($items) || empty($category) || empty($date) || empty($status)) {
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit();
}

if ($price <= 0) {
    echo json_encode(['success' => false, 'error' => 'Price must be greater than 0']);
    exit();
}

// Handle photo upload
$photoPath = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = mime_content_type($_FILES['photo']['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.']);
        exit();
    }
    
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileName = uniqid('product_', true) . '.' . pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $targetPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
        $photoPath = $targetPath;
        
        // Delete old photo if exists
        $oldPhotoStmt = $db->prepare("SELECT photo FROM products WHERE id = ?");
        $oldPhotoStmt->bind_param("i", $productId);
        $oldPhotoStmt->execute();
        $oldPhotoResult = $oldPhotoStmt->get_result();
        if ($oldPhotoRow = $oldPhotoResult->fetch_assoc()) {
            if (!empty($oldPhotoRow['photo']) && file_exists($oldPhotoRow['photo'])) {
                unlink($oldPhotoRow['photo']);
            }
        }
        $oldPhotoStmt->close();
    }
}

// Update product in database
if ($photoPath) {
    // Update with new photo
    $stmt = $db->prepare("UPDATE products SET name = ?, items = ?, price = ?, category = ?, critical_stock = ?, date = ?, status = ?, photo = ? WHERE id = ?");
    $stmt->bind_param("ssdsisssi", $name, $items, $price, $category, $critical_stock, $date, $status, $photoPath, $productId);
} else {
    // Update without changing photo
    $stmt = $db->prepare("UPDATE products SET name = ?, items = ?, price = ?, category = ?, critical_stock = ?, date = ?, status = ? WHERE id = ?");
    $stmt->bind_param("ssdsissi", $name, $items, $price, $category, $critical_stock, $date, $status, $productId);
}

if ($stmt->execute()) {
    // Log the activity
    $activityStmt = $db->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)");
    $action = 'Updated product: ' . $name;
    $details = json_encode([
        'product_id' => $productId,
        'name' => $name,
        'price' => $price,
        'category' => $category
    ]);
    $activityStmt->bind_param("iss", $_SESSION['user_id'], $action, $details);
    $activityStmt->execute();
    $activityStmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
?>
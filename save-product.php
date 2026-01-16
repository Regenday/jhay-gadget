<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $items = $_POST['items'] ?? '';
    $price = $_POST['price'] ?? 0;
    $category = $_POST['category'] ?? '';
    $date = $_POST['date'] ?? '';
    $status = $_POST['status'] ?? 'Available';
    
    // Handle file upload
    $photo = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $photo = $uploadDir . uniqid() . '_' . basename($_FILES['photo']['name']);
        move_uploaded_file($_FILES['photo']['tmp_name'], $photo);
    }
    
    $stmt = $db->prepare("INSERT INTO products (name, items, price, category, date, status, photo) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdssss", $name, $items, $price, $category, $date, $status, $photo);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Product saved successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save product: ' . $db->error]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
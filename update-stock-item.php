<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $serial_number = $_POST['serial_number'] ?? '';
    $color = $_POST['color'] ?? '';
    $status = $_POST['status'] ?? 'Available';
    
    $stmt = $db->prepare("UPDATE product_stock SET serial_number=?, color=?, status=? WHERE id=?");
    $stmt->bind_param("sssi", $serial_number, $color, $status, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Stock item updated successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update stock item: ' . $db->error]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
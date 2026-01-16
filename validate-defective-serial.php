<?php
include 'db.php';

if (isset($_GET['product_id']) && isset($_GET['serial_number'])) {
    $product_id = $_GET['product_id'];
    $serial_number = $_GET['serial_number'];
    
    $stmt = $db->prepare("
        SELECT id, status 
        FROM product_stock 
        WHERE product_id = ? AND serial_number = ? AND status = 'Available'
    ");
    $stmt->bind_param("is", $product_id, $serial_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo json_encode([
        'valid' => $result->num_rows > 0,
        'message' => $result->num_rows > 0 ? 'Serial is valid' : 'Serial not found or not available'
    ]);
} else {
    echo json_encode(['valid' => false, 'message' => 'Missing parameters']);
}
?>
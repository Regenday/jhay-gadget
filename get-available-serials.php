<?php
include 'db.php';

header('Content-Type: application/json');

if (isset($_GET['product_id'])) {
    $product_id = intval($_GET['product_id']);
    
    $stmt = $db->prepare("
        SELECT serial_number 
        FROM product_stock 
        WHERE product_id = ? AND status = 'Available'
        ORDER BY id ASC
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $serials = [];
    while ($row = $result->fetch_assoc()) {
        $serials[] = $row;
    }
    
    echo json_encode($serials);
} else {
    echo json_encode([]);
}
?>
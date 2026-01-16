<?php
include 'db.php';

$productId = $_GET['product_id'] ?? 0;
$search = $_GET['q'] ?? '';

if (!$productId) {
    echo json_encode([]);
    exit();
}

$query = "SELECT 
    id,
    serial_number,
    color,
    status
    FROM product_stock 
    WHERE product_id = ? 
    AND status = 'Available'
    AND serial_number LIKE ?
    ORDER BY serial_number
    LIMIT 10";

$stmt = $db->prepare($query);
$searchTerm = "%$search%";
$stmt->bind_param("is", $productId, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$serials = [];
while ($row = $result->fetch_assoc()) {
    $serials[] = $row;
}

header('Content-Type: application/json');
echo json_encode($serials);
?>
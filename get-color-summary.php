<?php
include 'db.php';
session_start();

if (!isset($_GET['product_id'])) {
    echo json_encode([]);
    exit;
}

$product_id = intval($_GET['product_id']);

// Get color summary
$query = "
    SELECT 
        color,
        COUNT(*) as total_count,
        COUNT(CASE WHEN status = 'Available' THEN 1 END) as available_count,
        COUNT(CASE WHEN status = 'Sold' THEN 1 END) as sold_count,
        COUNT(CASE WHEN status = 'Defective' THEN 1 END) as defective_count
    FROM product_stock 
    WHERE product_id = ?
    GROUP BY color
    ORDER BY total_count DESC
";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

$colorSummary = [];
while ($row = $result->fetch_assoc()) {
    $colorSummary[] = $row;
}

echo json_encode($colorSummary);
?>
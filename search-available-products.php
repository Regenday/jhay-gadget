<?php
include 'db.php';

$search = $_GET['q'] ?? '';

$query = "SELECT 
    p.id,
    p.name,
    p.category,
    p.price,
    COUNT(CASE WHEN ps.status = 'Available' THEN 1 END) as available_stock
    FROM products p
    LEFT JOIN product_stock ps ON p.id = ps.product_id AND ps.status = 'Available'
    WHERE (p.name LIKE ? OR p.category LIKE ?) AND p.status = 'Available'
    GROUP BY p.id, p.name, p.category, p.price
    HAVING available_stock > 0
    ORDER BY p.name
    LIMIT 10";

$stmt = $db->prepare($query);
$searchTerm = "%$search%";
$stmt->bind_param("ss", $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

header('Content-Type: application/json');
echo json_encode($products);
?>
<?php
include 'db.php';

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$searchQuery = "%$query%";
$sql = "SELECT p.*, 
        COUNT(CASE WHEN ps.status = 'Available' THEN 1 END) as available_stock
        FROM products p
        LEFT JOIN product_stock ps ON p.id = ps.product_id
        WHERE p.name LIKE ? OR p.category LIKE ?
        GROUP BY p.id
        ORDER BY p.name
        LIMIT 10";

$stmt = $db->prepare($sql);
$stmt->bind_param("ss", $searchQuery, $searchQuery);
$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);

header('Content-Type: application/json');
echo json_encode($products);
?>
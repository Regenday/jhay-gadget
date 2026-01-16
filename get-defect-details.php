<?php
include 'db.php';

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No ID provided']);
    exit();
}

$stockId = $_GET['id'];

$query = "SELECT 
    ps.*,
    p.name as product_name,
    p.category as product_category,
    p.price as product_price,
    sh.change_date as defect_date,
    sh.notes as defect_history_notes,
    u.username as reported_by_user
    FROM product_stock ps
    JOIN products p ON ps.product_id = p.id
    LEFT JOIN stock_history sh ON ps.id = sh.stock_id AND sh.change_type = 'defective'
    LEFT JOIN users u ON sh.changed_by = u.id
    WHERE ps.id = ?";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $stockId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    header('Content-Type: application/json');
    echo json_encode($row);
} else {
    echo json_encode(['error' => 'Defect item not found']);
}
?>
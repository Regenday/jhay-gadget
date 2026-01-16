<?php
include 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    exit(json_encode(['error' => 'Unauthorized']));
}

// Get filter parameters
$stockFilter = $_GET['stock'] ?? 'all';
$categoryFilter = $_GET['category'] ?? 'all';
$searchTerm = $_GET['search'] ?? '';

// Build query for sales data
$query = "
    SELECT 
        DATE(t.created_at) as date,
        SUM(ti.quantity) as qty,
        SUM(ti.quantity * ti.sale_price) as revenue,
        SUM(ti.quantity * (ti.sale_price - p.price)) as profit
    FROM transactions t
    JOIN transaction_items ti ON t.id = ti.transaction_id
    JOIN products p ON ti.product_id = p.id
    WHERE 1=1
";

$params = [];
$types = "";

// Apply category filter
if ($categoryFilter !== 'all') {
    $query .= " AND p.category = ?";
    $params[] = $categoryFilter;
    $types .= "s";
}

// Apply search filter (by month name)
if (!empty($searchTerm)) {
    $query .= " AND MONTHNAME(t.created_at) LIKE ?";
    $params[] = "%$searchTerm%";
    $types .= "s";
}

// Apply stock availability filter through product join
if ($stockFilter !== 'all') {
    switch($stockFilter) {
        case 'in':
            $query .= " AND p.available_stock > 10";
            break;
        case 'out':
            $query .= " AND p.available_stock = 0";
            break;
        case 'low':
            $query .= " AND p.available_stock BETWEEN 1 AND 10";
            break;
    }
}

$query .= " GROUP BY DATE(t.created_at) ORDER BY t.created_at DESC LIMIT 30";

// Prepare and execute query
if (!empty($params)) {
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $db->query($query);
}

$salesData = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $salesData[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($salesData);
?>
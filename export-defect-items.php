<?php
include 'db.php';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=defect_items_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Write CSV headers
fputcsv($output, [
    'ID',
    'Product Name',
    'Category',
    'Serial Number',
    'Color',
    'Price',
    'Defect Notes',
    'Defect Date',
    'Reported By',
    'Date Added',
    'Last Updated'
]);

// Get search parameter
$search = $_GET['search'] ?? '';

// Build query
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
    WHERE ps.status = 'Defective'";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (
        ps.serial_number LIKE ? OR 
        p.name LIKE ? OR 
        ps.defect_notes LIKE ? OR
        p.category LIKE ?
    )";
    $searchTerm = "%$search%";
    $params = array_fill(0, 4, $searchTerm);
    $types = str_repeat("s", 4);
}

$query .= " ORDER BY ps.updated_at DESC";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Write data rows
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['product_name'],
        $row['product_category'],
        $row['serial_number'],
        $row['color'] ?? '',
        $row['product_price'],
        $row['defect_notes'] ?? '',
        $row['defect_date'] ?? $row['updated_at'],
        $row['reported_by_user'] ?? '',
        $row['created_at'],
        $row['updated_at']
    ]);
}

fclose($output);
?>
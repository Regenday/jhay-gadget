<?php
include 'db.php';

$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';
$timeframe = $_GET['timeframe'] ?? 'all';

// Build query (same as get-all-stock-history.php)
$query = "
    SELECT 
        sh.*,
        ps.serial_number,
        ps.color,
        p.name as product_name,
        u.username as changed_by
    FROM stock_history sh
    LEFT JOIN product_stock ps ON sh.stock_id = ps.id
    LEFT JOIN products p ON sh.product_id = p.id
    LEFT JOIN users u ON sh.changed_by = u.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($filter !== 'all') {
    $query .= " AND sh.change_type = ?";
    $params[] = $filter;
    $types .= "s";
}

if ($timeframe !== 'all') {
    $dateCondition = "";
    switch($timeframe) {
        case 'today': $dateCondition = "DATE(sh.change_date) = CURDATE()"; break;
        case 'week': $dateCondition = "YEARWEEK(sh.change_date) = YEARWEEK(CURDATE())"; break;
        case 'month': $dateCondition = "YEAR(sh.change_date) = YEAR(CURDATE()) AND MONTH(sh.change_date) = MONTH(CURDATE())"; break;
    }
    $query .= " AND $dateCondition";
}

if (!empty($search)) {
    $query .= " AND (ps.serial_number LIKE ? OR ps.color LIKE ? OR u.username LIKE ? OR p.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ssss";
}

$query .= " ORDER BY sh.change_date DESC";

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="global_stock_history_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'Date',
    'Product',
    'Action',
    'Serial Number',
    'Color',
    'Previous Status',
    'New Status',
    'Quantity Change',
    'Changed By'
]);

try {
    // Prepare and execute
    if (!empty($params)) {
        $stmt = $db->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $db->query($query);
    }

    // Add data rows
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['change_date'],
            $row['product_name'],
            ucfirst($row['change_type']),
            $row['serial_number'] ?? '',
            $row['color'] ?? '',
            $row['previous_status'] ?? '',
            $row['new_status'] ?? '',
            $row['quantity_change'] ?? 0,
            $row['changed_by'] ?? 'System'
        ]);
    }

    fclose($output);
    
} catch (Exception $e) {
    // If there's an error, output a simple error message
    fputcsv($output, ['Error', 'Failed to generate CSV: ' . $e->getMessage()]);
    fclose($output);
}
exit;
?>
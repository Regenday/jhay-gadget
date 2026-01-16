<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

include 'db.php';

$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';
$timeframe = $_GET['timeframe'] ?? 'all';

// Build query
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

// Apply filters
if ($filter !== 'all') {
    $query .= " AND sh.change_type = ?";
    $params[] = $filter;
    $types .= "s";
}

// Apply timeframe
if ($timeframe !== 'all') {
    $dateCondition = "";
    switch($timeframe) {
        case 'today':
            $dateCondition = "DATE(sh.change_date) = CURDATE()";
            break;
        case 'week':
            $dateCondition = "YEARWEEK(sh.change_date) = YEARWEEK(CURDATE())";
            break;
        case 'month':
            $dateCondition = "YEAR(sh.change_date) = YEAR(CURDATE()) AND MONTH(sh.change_date) = MONTH(CURDATE())";
            break;
    }
    $query .= " AND $dateCondition";
}

// Apply search
if (!empty($search)) {
    $query .= " AND (ps.serial_number LIKE ? OR ps.color LIKE ? OR u.username LIKE ? OR p.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ssss";
}

$query .= " ORDER BY sh.change_date DESC LIMIT 1000";

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
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    echo json_encode($history);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
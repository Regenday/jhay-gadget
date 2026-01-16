<?php
include 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';
$timeframe = $_GET['timeframe'] ?? 'all';

// Build query conditions
$whereConditions = [];
$params = [];
$paramTypes = '';

if (!empty($search)) {
    $whereConditions[] = "(action LIKE ? OR details LIKE ? OR username LIKE ? OR ip_address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $paramTypes .= 'ssss';
}

if ($filter !== 'all') {
    $whereConditions[] = "action LIKE ?";
    $params[] = "%$filter%";
    $paramTypes .= 's';
}

// Timeframe filter
if ($timeframe !== 'all') {
    switch($timeframe) {
        case 'today':
            $whereConditions[] = "DATE(activity_date) = CURDATE()";
            break;
        case 'week':
            $whereConditions[] = "YEARWEEK(activity_date) = YEARWEEK(CURDATE())";
            break;
        case 'month':
            $whereConditions[] = "YEAR(activity_date) = YEAR(CURDATE()) AND MONTH(activity_date) = MONTH(CURDATE())";
            break;
        case 'year':
            $whereConditions[] = "YEAR(activity_date) = YEAR(CURDATE())";
            break;
    }
}

// Build the query
$query = "SELECT * FROM activity_log";
if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}
$query .= " ORDER BY activity_date DESC";

// Prepare and execute query
if (!empty($params)) {
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = false;
    }
} else {
    $result = $db->query($query);
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=activity_log_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, ['ID', 'User ID', 'Username', 'Action', 'Details', 'IP Address', 'User Agent', 'Date']);

// Add data rows
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['user_id'],
            $row['username'],
            $row['action'],
            $row['details'],
            $row['ip_address'],
            $row['user_agent'],
            $row['activity_date']
        ]);
    }
}

fclose($output);
exit();
?>
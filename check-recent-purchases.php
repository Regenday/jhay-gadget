<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['has_new_purchases' => false]);
    exit();
}

// Get the last check time from request or use default
$last_check = isset($_GET['last_check']) ? intval($_GET['last_check']) : (time() - 300); // Default to 5 minutes ago

// Check for purchases in the last 5 minutes
$check_query = "SELECT COUNT(*) as new_purchases FROM purchases WHERE created_at >= FROM_UNIXTIME(?)";
$check_stmt = $db->prepare($check_query);
$check_stmt->bind_param("i", $last_check);
$check_stmt->execute();
$result = $check_stmt->get_result();
$data = $result->fetch_assoc();

echo json_encode([
    'has_new_purchases' => $data['new_purchases'] > 0,
    'new_purchase_count' => $data['new_purchases']
]);
?>
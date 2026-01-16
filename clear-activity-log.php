<?php
include 'db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Clear the activity log
$result = $db->query("TRUNCATE TABLE activity_log");

if ($result) {
    // Log the clearing action
    $db->query("
        INSERT INTO activity_log (user_id, username, action, details, ip_address, user_agent) 
        VALUES (" . $_SESSION['user_id'] . ", '" . ($_SESSION['username'] ?? 'System') . "', 'System Maintenance', 'Activity log cleared by administrator', '" . $_SERVER['REMOTE_ADDR'] . "', '" . $_SERVER['HTTP_USER_AGENT'] . "')
    ");
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $db->error]);
}
?>
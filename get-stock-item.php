<?php
include 'db.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No stock item ID provided']);
    exit;
}

$stock_id = intval($_GET['id']);

try {
    // Get stock item with defect notes
    $stmt = $db->prepare("
        SELECT 
            ps.id,
            ps.product_id,
            ps.serial_number,
            ps.color,
            ps.status,
            ps.defect_notes,
            ps.predetermined_profit,
            ps.purchase_price,
            ps.created_at,
            ps.updated_at,
            p.name as product_name,
            p.price as product_price
        FROM product_stock ps
        LEFT JOIN products p ON ps.product_id = p.id
        WHERE ps.id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $db->error);
    }
    
    $stmt->bind_param("i", $stock_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stockItem = $result->fetch_assoc();
        
        // Ensure defect_notes is never null
        if ($stockItem['defect_notes'] === null) {
            $stockItem['defect_notes'] = '';
        }
        
        echo json_encode($stockItem);
    } else {
        echo json_encode(['error' => 'Stock item not found']);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Error in get-stock-item.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
<?php
include 'db.php';

// Set header first
header('Content-Type: application/json');

// Turn off error display
ini_set('display_errors', 0);
error_reporting(0);

if (isset($_GET['product_id']) && isset($_GET['serial_number'])) {
    $product_id = intval($_GET['product_id']);
    $serial_number = trim($_GET['serial_number']);
    
    try {
        $stmt = $db->prepare("SELECT id, status FROM product_stock WHERE product_id = ? AND serial_number = ?");
        if (!$stmt) {
            throw new Exception('Database error: ' . $db->error);
        }
        
        $stmt->bind_param("is", $product_id, $serial_number);
        if (!$stmt->execute()) {
            throw new Exception('Query failed: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ($row['status'] === 'Available') {
                echo json_encode(['valid' => true, 'status' => 'available']);
            } else {
                echo json_encode(['valid' => false, 'status' => $row['status'], 'message' => 'Serial number is not available']);
            }
        } else {
            echo json_encode(['valid' => false, 'status' => 'not_found', 'message' => 'Serial number not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['valid' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['valid' => false, 'error' => 'Missing parameters']);
}
?>
<?php
include 'db.php';
session_start();

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check if product_ids are provided
if (!isset($data['product_ids']) || !is_array($data['product_ids']) || empty($data['product_ids'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No products selected for deletion']);
    exit();
}

$product_ids = $data['product_ids'];
$deleted_count = 0;
$errors = [];

try {
    // Start transaction
    $db->begin_transaction();

    // Prepare statements
    $delete_stock_history_stmt = $db->prepare("DELETE FROM stock_history WHERE product_id = ?");
    $delete_stock_stmt = $db->prepare("DELETE FROM product_stock WHERE product_id = ?");
    $delete_product_stmt = $db->prepare("DELETE FROM products WHERE id = ?");

    foreach ($product_ids as $product_id) {
        $product_id = intval($product_id);
        
        if ($product_id <= 0) {
            $errors[] = "Invalid product ID: $product_id";
            continue;
        }

        // First delete associated stock history
        $delete_stock_history_stmt->bind_param("i", $product_id);
        if (!$delete_stock_history_stmt->execute()) {
            $errors[] = "Failed to delete stock history for product ID: $product_id";
            continue;
        }

        // Then delete associated stock items
        $delete_stock_stmt->bind_param("i", $product_id);
        if (!$delete_stock_stmt->execute()) {
            $errors[] = "Failed to delete stock for product ID: $product_id";
            continue;
        }

        // Finally delete the product
        $delete_product_stmt->bind_param("i", $product_id);
        if ($delete_product_stmt->execute()) {
            $deleted_count++;
        } else {
            $errors[] = "Failed to delete product ID: $product_id";
        }
    }

    // Commit transaction
    $db->commit();

    if ($deleted_count > 0) {
        echo json_encode([
            'success' => true,
            'message' => "Successfully deleted $deleted_count product(s)",
            'deleted_count' => $deleted_count,
            'errors' => $errors
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'No products were deleted',
            'errors' => $errors
        ]);
    }

} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

// Close statements and connection
if (isset($delete_stock_history_stmt)) $delete_stock_history_stmt->close();
if (isset($delete_stock_stmt)) $delete_stock_stmt->close();
if (isset($delete_product_stmt)) $delete_product_stmt->close();
$db->close();
?>
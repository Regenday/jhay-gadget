<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit(json_encode(['error' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $cart_items = $input['cart_items'] ?? [];
    $serial_numbers = $input['serial_numbers'] ?? [];
    $total_amount = $input['total_amount'] ?? 0;
    $cashier_id = $_SESSION['user_id'];
    
    if (empty($cart_items)) {
        echo json_encode(['success' => false, 'error' => 'No items in cart']);
        exit;
    }
    
    // Generate receipt number
    $receipt_number = 'RCP' . date('YmdHis') . rand(100, 999);
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        // Create transaction record
        $stmt = $db->prepare("INSERT INTO transactions (receipt_number, total_amount, cashier_id) VALUES (?, ?, ?)");
        $stmt->bind_param("sdi", $receipt_number, $total_amount, $cashier_id);
        $stmt->execute();
        $transaction_id = $db->insert_id;
        
        // Process each cart item
        foreach ($cart_items as $item) {
            $product_id = $item['id'];
            $quantity = $item['quantity'];
            $sale_price = $item['salePrice'];
            
            // Add to transaction_items
            $stmt = $db->prepare("INSERT INTO transaction_items (transaction_id, product_id, quantity, sale_price) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiid", $transaction_id, $product_id, $quantity, $sale_price);
            $stmt->execute();
            $transaction_item_id = $db->insert_id;
            
            // Update stock status for each serial number
            if (isset($serial_numbers[$product_id])) {
                foreach ($serial_numbers[$product_id] as $serial) {
                    $stmt = $db->prepare("UPDATE product_stock SET status = 'Sold', sold_date = NOW() WHERE product_id = ? AND serial_number = ? AND status = 'Available'");
                    $stmt->bind_param("is", $product_id, $serial);
                    $stmt->execute();
                    
                    if ($db->affected_rows > 0) {
                        // Record in stock history
                        $stmt = $db->prepare("INSERT INTO stock_history (product_id, stock_id, change_type, previous_status, new_status, quantity_change, changed_by) 
                                             SELECT ?, id, 'sold', 'Available', 'Sold', -1, ? FROM product_stock WHERE product_id = ? AND serial_number = ?");
                        $stmt->bind_param("iiis", $product_id, $cashier_id, $product_id, $serial);
                        $stmt->execute();
                    }
                }
            }
        }
        
        $db->commit();
        
        // Trigger sales update event
        $_SESSION['sales_updated'] = time();
        
        // Return success with purchase details
        echo json_encode([
            'success' => true,
            'purchase' => [
                'receipt_number' => $receipt_number,
                'total_amount' => $total_amount,
                'items' => $cart_items,
                'transaction_id' => $transaction_id
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['success' => false, 'error' => 'Transaction failed: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
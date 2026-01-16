<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['cart_items']) || !isset($input['serial_numbers']) || !isset($input['total_amount'])) {
            throw new Exception('Missing required fields');
        }

        $cart_items = $input['cart_items'];
        $serial_numbers = $input['serial_numbers'];
        $total_amount = floatval($input['total_amount']);
        $cashier_id = $_SESSION['user_id'];

        // Start transaction
        $db->begin_transaction();

        // Generate receipt number
        $receipt_number = 'RCP' . date('YmdHis') . rand(100, 999);

        // Calculate total profit
        $total_profit = 0;
        foreach ($cart_items as $item) {
            $original_price = floatval($item['price']);
            $sale_price = floatval($item['salePrice']);
            $quantity = intval($item['quantity']);
            $profit = ($sale_price - $original_price) * $quantity;
            $total_profit += $profit;
        }

        // Insert purchase record
        $purchase_sql = "INSERT INTO purchases (receipt_number, total_amount, items, purchase_date, cashier_id, total_profit) 
                         VALUES (?, ?, ?, NOW(), ?, ?)";
        $purchase_stmt = $db->prepare($purchase_sql);
        
        $items_json = json_encode([
            'cart_items' => $cart_items,
            'serial_numbers' => $serial_numbers
        ]);
        
        $purchase_stmt->bind_param("sdsid", $receipt_number, $total_amount, $items_json, $cashier_id, $total_profit);
        $purchase_stmt->execute();
        $purchase_id = $db->insert_id;

        // Process each item in cart
        foreach ($cart_items as $item) {
            $product_id = intval($item['id']);
            $product_name = $db->real_escape_string($item['name']);
            $quantity = intval($item['quantity']);
            $sale_price = floatval($item['salePrice']);
            $original_price = floatval($item['price']);
            $profit = ($sale_price - $original_price) * $quantity;
            
            $item_serials = $serial_numbers[$product_id] ?? [];

            // DEBUG: Log what we're processing
            error_log("Processing purchase for product $product_id ($product_name) with serials: " . json_encode($item_serials));

            // Validate serial numbers exist and are available FOR THIS PRODUCT
            foreach ($item_serials as $serial) {
                $serial = trim($serial);
                
                // Check if serial exists and is available FOR THIS SPECIFIC PRODUCT
                $check_sql = "SELECT id, product_id, status FROM product_stock 
                             WHERE serial_number = ? AND status = 'Available'";
                $check_stmt = $db->prepare($check_sql);
                $check_stmt->bind_param("s", $serial);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows === 0) {
                    $check_stmt->close();
                    
                    // Check if serial exists but wrong status
                    $status_check = $db->query("SELECT status, product_id FROM product_stock WHERE serial_number = '$serial'");
                    if ($status_check->num_rows > 0) {
                        $status_row = $status_check->fetch_assoc();
                        throw new Exception("Serial '$serial' exists but status is '{$status_row['status']}' (Product ID: {$status_row['product_id']})");
                    } else {
                        throw new Exception("Serial '$serial' does not exist in database");
                    }
                }
                
                $stock_row = $check_result->fetch_assoc();
                $stock_product_id = $stock_row['product_id'];
                
                // Verify serial belongs to the correct product
                if ($stock_product_id != $product_id) {
                    // Get product name for the serial's actual product
                    $product_query = $db->query("SELECT name FROM products WHERE id = $stock_product_id");
                    $product_name_actual = $product_query->fetch_assoc()['name'];
                    
                    throw new Exception("Serial '$serial' belongs to product: '$product_name_actual' (ID: $stock_product_id), not '$product_name' (ID: $product_id)");
                }
                
                $check_stmt->close();
            }

            // Insert purchase item
            $item_sql = "INSERT INTO purchase_items (purchase_id, product_id, product_name, quantity, sale_price, serial_numbers, cost_price, profit, base_price) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $item_stmt = $db->prepare($item_sql);
            
            $serials_json = json_encode($item_serials);
            $cost_price = $original_price * $quantity;
            $base_price = $original_price * $quantity;
            
            $item_stmt->bind_param("iisidssdd", 
                $purchase_id, 
                $product_id, 
                $product_name,
                $quantity,
                $sale_price,
                $serials_json,
                $cost_price,
                $profit,
                $base_price
            );
            $item_stmt->execute();
            $item_stmt->close();

            // Update stock status to 'Sold' for each serial number
            foreach ($item_serials as $serial) {
                $serial = trim($serial);
                
                // Update stock status
                $update_stock_sql = "UPDATE product_stock SET status = 'Sold' WHERE serial_number = ? AND status = 'Available'";
                $update_stmt = $db->prepare($update_stock_sql);
                $update_stmt->bind_param("s", $serial);
                $update_stmt->execute();
                
                if ($update_stmt->affected_rows === 0) {
                    // Check what happened
                    $check = $db->query("SELECT id, status FROM product_stock WHERE serial_number = '$serial'");
                    if ($check->num_rows > 0) {
                        $row = $check->fetch_assoc();
                        throw new Exception("Could not mark serial '$serial' as sold. Current status: '{$row['status']}'");
                    } else {
                        throw new Exception("Serial '$serial' disappeared from database");
                    }
                }
                
                // Get stock_id for history
                $get_id_sql = "SELECT id FROM product_stock WHERE serial_number = ?";
                $get_id_stmt = $db->prepare($get_id_sql);
                $get_id_stmt->bind_param("s", $serial);
                $get_id_stmt->execute();
                $id_result = $get_id_stmt->get_result();
                $stock_row = $id_result->fetch_assoc();
                $stock_id = $stock_row['id'];
                $get_id_stmt->close();
                
                // Record stock history
                $history_sql = "INSERT INTO stock_history (product_id, stock_id, change_type, previous_status, new_status, changed_by, change_date, quantity_change) 
                               VALUES (?, ?, 'sold', 'Available', 'Sold', ?, NOW(), -1)";
                $history_stmt = $db->prepare($history_sql);
                $history_stmt->bind_param("iii", $product_id, $stock_id, $cashier_id);
                $history_stmt->execute();
                $history_stmt->close();
                
                $update_stmt->close();
            }

            // Insert sales record
            $sales_sql = "INSERT INTO sales (product_id, quantity, total_amount, sale_price, cost_price, profit, receipt_number, serial_numbers, purchase_id, base_price, original_price) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $sales_stmt = $db->prepare($sales_sql);
            
            $item_total = $sale_price * $quantity;
            $sales_serials_json = json_encode($item_serials);
            
            $sales_stmt->bind_param("iiddddssidd", 
                $product_id,
                $quantity,
                $item_total,
                $sale_price,
                $cost_price,
                $profit,
                $receipt_number,
                $sales_serials_json,
                $purchase_id,
                $base_price,
                $original_price
            );
            $sales_stmt->execute();
            $sales_stmt->close();
        }

        // Log the activity
        $activity_sql = "INSERT INTO activity_log (user_id, username, action, details, ip_address, user_agent) 
                        VALUES (?, ?, 'Purchase Completed', ?, ?, ?)";
        $activity_stmt = $db->prepare($activity_sql);
        $username = $_SESSION['username'] ?? 'Unknown';
        $details = "Processed purchase with receipt $receipt_number. Sold " . count($cart_items) . " items. Total: ₱$total_amount";
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $activity_stmt->bind_param("issss", $cashier_id, $username, $details, $ip_address, $user_agent);
        $activity_stmt->execute();
        $activity_stmt->close();

        // Commit transaction
        $db->commit();

        // Return success
        echo json_encode([
            'success' => true,
            'message' => 'Purchase completed successfully',
            'purchase' => [
                'receipt_number' => $receipt_number,
                'total_amount' => $total_amount,
                'items' => array_map(function($item) use ($serial_numbers) {
                    return [
                        'name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'sale_price' => $item['salePrice'],
                        'original_price' => $item['price'],
                        'serial_numbers' => $serial_numbers[$item['id']] ?? []
                    ];
                }, $cart_items)
            ]
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($db)) {
            $db->rollback();
        }
        
        error_log("Purchase processing error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => 'Purchase failed: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
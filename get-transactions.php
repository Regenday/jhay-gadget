<?php
include 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

header('Content-Type: application/json');

$search = isset($_GET['search']) ? trim($db->real_escape_string($_GET['search'])) : '';

try {
    // Query to get purchase transactions
    $query = "SELECT 
                p.id,
                p.receipt_number,
                p.total_amount,
                p.cashier_id,
                p.purchase_date,
                p.created_at,
                u.username as cashier_name,
                pi.product_id,
                pi.product_name,
                pi.quantity,
                pi.sale_price,
                pi.serial_numbers,
                pi.cost_price,
                pi.profit,
                pi.base_price
              FROM purchases p
              LEFT JOIN users u ON p.cashier_id = u.id
              LEFT JOIN purchase_items pi ON p.id = pi.purchase_id
              WHERE 1=1";
    
    // Add search conditions
    if (!empty($search)) {
        $query .= " AND (
            p.receipt_number LIKE '%$search%' OR 
            u.username LIKE '%$search%' OR 
            pi.product_name LIKE '%$search%'
        )";
    }
    
    // Order by most recent first
    $query .= " ORDER BY p.created_at DESC, p.purchase_date DESC LIMIT 50";
    
    $result = $db->query($query);
    $transactions = [];
    $currentTransaction = null;
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $receiptNumber = $row['receipt_number'];
            
            // If this is a new transaction, start a new one
            if (!$currentTransaction || $currentTransaction['receipt_number'] !== $receiptNumber) {
                if ($currentTransaction) {
                    $transactions[] = $currentTransaction;
                }
                
                $currentTransaction = [
                    'id' => $row['id'],
                    'receipt_number' => $receiptNumber,
                    'total_amount' => $row['total_amount'],
                    'cashier_id' => $row['cashier_id'],
                    'cashier_name' => $row['cashier_name'] ?: 'Unknown',
                    'purchase_date' => $row['purchase_date'],
                    'created_at' => $row['created_at'],
                    'items' => []
                ];
            }
            
            // Add item to current transaction
            if ($row['product_name']) {
                $serialNumbers = [];
                if (!empty($row['serial_numbers'])) {
                    try {
                        $serials = json_decode($row['serial_numbers'], true);
                        if (is_array($serials)) {
                            $serialNumbers = $serials;
                        }
                    } catch (Exception $e) {
                        $serialNumbers = [$row['serial_numbers']];
                    }
                }
                
                $currentTransaction['items'][] = [
                    'product_id' => $row['product_id'],
                    'product_name' => $row['product_name'],
                    'quantity' => $row['quantity'],
                    'sale_price' => $row['sale_price'],
                    'cost_price' => $row['cost_price'],
                    'profit' => $row['profit'],
                    'base_price' => $row['base_price'],
                    'serial_numbers' => $serialNumbers
                ];
            }
        }
        
        // Don't forget to add the last transaction
        if ($currentTransaction) {
            $transactions[] = $currentTransaction;
        }
    }
    
    echo json_encode($transactions);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
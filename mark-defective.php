<?php
// mark-defective.php
session_start();
include 'db.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the raw POST data
    $input = file_get_contents('php://input');
    
    // Check if it's JSON or form data
    if (isset($_POST['product_id'])) {
        // Form data was sent
        $product_id = intval($_POST['product_id']);
        $serial_numbers = json_decode($_POST['serial_numbers'], true);
        $notes = trim($_POST['notes']);
    } else {
        // Try to parse as JSON
        $data = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $product_id = intval($data['product_id']);
            $serial_numbers = $data['serial_numbers'];
            $notes = trim($data['notes']);
        } else {
            // If neither works, return error
            echo json_encode(['success' => false, 'error' => 'Invalid data format']);
            exit;
        }
    }

    // Validate required fields
    if (empty($product_id) || empty($serial_numbers) || empty($notes)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Missing required fields: product_id, serial_numbers, or notes',
            'received_data' => [
                'product_id' => $product_id,
                'serial_numbers_count' => is_array($serial_numbers) ? count($serial_numbers) : 0,
                'notes' => $notes
            ]
        ]);
        exit;
    }

    if (empty($serial_numbers)) {
        echo json_encode(['success' => false, 'error' => 'No serial numbers provided']);
        exit;
    }

    if (empty($notes)) {
        echo json_encode(['success' => false, 'error' => 'Defect notes are required']);
        exit;
    }

    try {
        $db->begin_transaction();
        
        $marked_count = 0;
        $errors = [];

        foreach ($serial_numbers as $serial) {
            // Check if serial exists and is available
            $check_sql = "SELECT ps.id, ps.product_id, p.name as product_name 
                         FROM product_stock ps 
                         JOIN products p ON ps.product_id = p.id 
                         WHERE ps.serial_number = ? AND ps.status = 'Available'";
            $check_stmt = $db->prepare($check_sql);
            $check_stmt->bind_param("s", $serial);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $stock_item = $result->fetch_assoc();
                
                // Update stock status and store defect notes
                $update_sql = "UPDATE product_stock 
                              SET status = 'Defective', defect_notes = ?, updated_at = NOW() 
                              WHERE id = ?";
                $update_stmt = $db->prepare($update_sql);
                $update_stmt->bind_param("si", $notes, $stock_item['id']);
                
                if ($update_stmt->execute()) {
                    $marked_count++;
                    
                    // Record in stock history
                    $history_sql = "INSERT INTO stock_history 
                                   (product_id, stock_id, change_type, previous_status, new_status, changed_by, notes) 
                                   VALUES (?, ?, 'defective', 'Available', 'Defective', ?, ?)";
                    $history_stmt = $db->prepare($history_sql);
                    $history_stmt->bind_param("iiis", $stock_item['product_id'], $stock_item['id'], $_SESSION['user_id'], $notes);
                    $history_stmt->execute();
                    
                    // Log the activity
                    $activity_sql = "INSERT INTO activity_log (user_id, action, details) 
                                    VALUES (?, 'stock_defective', ?)";
                    $activity_stmt = $db->prepare($activity_sql);
                    $details = "Marked item as defective - Product: " . $stock_item['product_name'] . 
                              ", Serial: " . $serial . ", Notes: " . $notes;
                    $activity_stmt->bind_param("is", $_SESSION['user_id'], $details);
                    $activity_stmt->execute();
                    
                } else {
                    $errors[] = "Failed to mark serial $serial as defective";
                }
            } else {
                $errors[] = "Serial $serial not found or not available";
            }
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'marked_count' => $marked_count,
            'errors' => $errors,
            'message' => "Successfully marked $marked_count item(s) as defective"
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
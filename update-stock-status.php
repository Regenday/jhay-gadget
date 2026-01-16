<?php
// update-stock-status.php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stock_id = intval($input['stock_id']);
    $new_status = $input['status'];
    
    // Only require notes for defective status
    $defect_notes = '';
    if ($new_status === 'Defective') {
        $defect_notes = trim($input['defect_notes'] ?? '');
        if (empty($defect_notes)) {
            echo json_encode(['success' => false, 'error' => 'Defect notes are required when marking as defective']);
            exit;
        }
    }
    
    try {
        // Get current status
        $current_stmt = $db->prepare("SELECT status, product_id FROM product_stock WHERE id = ?");
        $current_stmt->bind_param("i", $stock_id);
        $current_stmt->execute();
        $current_result = $current_stmt->get_result();
        
        if ($current_result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Stock item not found']);
            exit;
        }
        
        $current_data = $current_result->fetch_assoc();
        $previous_status = $current_data['status'];
        $product_id = $current_data['product_id'];
        $current_stmt->close();
        
        // Update stock status (only update notes for defective status)
        if ($new_status === 'Defective') {
            $update_stmt = $db->prepare("UPDATE product_stock SET status = ?, defect_notes = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $new_status, $defect_notes, $stock_id);
        } else {
            $update_stmt = $db->prepare("UPDATE product_stock SET status = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_status, $stock_id);
        }
        
        if ($update_stmt->execute()) {
            // Log activity
            $user_id = $_SESSION['user_id'];
            $activity_query = "INSERT INTO activity_log (user_id, action, details) VALUES (?, 'stock_status_updated', ?)";
            $activity_stmt = $db->prepare($activity_query);
            $details = "Changed stock item #{$stock_id} from {$previous_status} to {$new_status}";
            if ($new_status === 'Defective' && !empty($defect_notes)) {
                $details .= ". Notes: " . substr($defect_notes, 0, 100);
            }
            $activity_stmt->bind_param("is", $user_id, $details);
            $activity_stmt->execute();
            $activity_stmt->close();
            
            // Add to stock history
            $history_query = "INSERT INTO stock_history (product_id, stock_id, change_type, previous_status, new_status, defect_notes, changed_by) VALUES (?, ?, 'updated', ?, ?, ?, ?)";
            $history_stmt = $db->prepare($history_query);
            $history_stmt->bind_param("iisssi", $product_id, $stock_id, $previous_status, $new_status, $defect_notes, $user_id);
            $history_stmt->execute();
            $history_stmt->close();
            
            echo json_encode(['success' => true, 'message' => 'Stock status updated successfully']);
        } else {
            throw new Exception("Failed to update stock status: " . $update_stmt->error);
        }
        
        $update_stmt->close();
        
    } catch (Exception $e) {
        error_log("Error updating stock status: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
<?php
// add-stock.php
header('Content-Type: application/json');

// Enable error reporting for debugging (remove in production)
error_reporting(0); // Turn off error display for JSON response
ini_set('display_errors', 0);

include 'db.php';
include 'functions.php'; // ADD THIS LINE
session_start(); // ADD THIS LINE

// Check if user is logged in - ADD THIS SECTION
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check if JSON decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit;
}

// Validate required fields
if (!isset($data['product_id']) || !isset($data['serial_data'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$product_id = intval($data['product_id']);
$serial_data = $data['serial_data'];
$user_id = $_SESSION['user_id']; // ADD THIS LINE
$username = $_SESSION['username'] ?? null; // ADD THIS LINE

try {
    // Start transaction
    $db->begin_transaction();
    
    $success_count = 0;
    $errors = [];
    
    // Get product name for logging - ADD THIS SECTION
    $product_stmt = $db->prepare("SELECT name FROM products WHERE id = ?");
    $product_stmt->bind_param("i", $product_id);
    $product_stmt->execute();
    $product_result = $product_stmt->get_result();
    $product = $product_result->fetch_assoc();
    $product_name = $product['name'] ?? 'Unknown Product';
    $product_stmt->close();
    
    foreach ($serial_data as $item) {
        $serial = trim($item['serial']);
        $color = trim($item['color']);
        
        // Validate serial and color
        if (empty($serial) || empty($color)) {
            $errors[] = "Missing serial or color for one of the items";
            continue;
        }
        
        // Check if serial already exists
        $check_stmt = $db->prepare("SELECT id FROM product_stock WHERE serial_number = ?");
        $check_stmt->bind_param("s", $serial);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "Serial number '$serial' already exists";
            $check_stmt->close();
            continue;
        }
        $check_stmt->close();
        
        // Insert new stock item
        $insert_stmt = $db->prepare("INSERT INTO product_stock (product_id, serial_number, color, status, created_at) VALUES (?, ?, ?, 'Available', NOW())");
        $insert_stmt->bind_param("iss", $product_id, $serial, $color);
        
        if ($insert_stmt->execute()) {
            $success_count++;
            
            // Record in stock history
            $stock_id = $insert_stmt->insert_id;
            $history_stmt = $db->prepare("INSERT INTO stock_history (product_id, stock_id, change_type, previous_status, new_status, quantity_change, changed_by, change_date) VALUES (?, ?, 'added', NULL, 'Available', 1, ?, NOW())");
            $history_stmt->bind_param("iii", $product_id, $stock_id, $user_id);
            $history_stmt->execute();
            $history_stmt->close();
            
        } else {
            $errors[] = "Failed to add serial '$serial': " . $insert_stmt->error;
        }
        $insert_stmt->close();
    }
    
    if ($success_count > 0) {
        $db->commit();
        
        // LOG THE ACTIVITY - ADD THIS SECTION
        $details = "Added {$success_count} stock items to {$product_name}. Serials: " . implode(', ', array_column($serial_data, 'serial'));
        logActivity($db, $user_id, 'Stock Added', $details, $username);
        
        $response = [
            'success' => true,
            'message' => "Successfully added $success_count stock items",
            'added_count' => $success_count
        ];
        
        if (!empty($errors)) {
            $response['warnings'] = $errors;
        }
    } else {
        $db->rollback();
        $response = [
            'success' => false,
            'error' => 'Failed to add any stock items: ' . implode(', ', $errors)
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $db->rollback();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$db->close();
?>
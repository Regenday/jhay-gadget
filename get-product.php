<?php
include 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

header('Content-Type: application/json');

// Get product ID from query string
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    echo json_encode(['error' => 'Invalid product ID']);
    exit();
}

try {
    // Get single product with stock counts
    $query = "SELECT 
                p.*,
                COUNT(ps.id) as total_stock,
                COUNT(CASE WHEN ps.status = 'Available' THEN 1 END) as available_stock,
                COUNT(CASE WHEN ps.status = 'Sold' THEN 1 END) as sold_count,
                COUNT(CASE WHEN ps.status = 'Defective' THEN 1 END) as defective_count
              FROM products p 
              LEFT JOIN product_stock ps ON p.id = ps.product_id 
              WHERE p.id = ?
              GROUP BY p.id";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        
        // Clean up the data
        $product['id'] = intval($product['id']);
        $product['name'] = $product['name'] ?? '';
        $product['items'] = $product['items'] ?? '';
        $product['price'] = floatval($product['price'] ?? 0);
        $product['cost_price'] = floatval($product['cost_price'] ?? 0);
        $product['category'] = $product['category'] ?? '';
        $product['critical_stock'] = intval($product['critical_stock'] ?? 10);
        $product['date'] = $product['date'] ?? '';
        $product['status'] = $product['status'] ?? 'Available';
        $product['photo'] = $product['photo'] ?? '';
        $product['stock'] = intval($product['stock'] ?? 0);
        $product['total_stock'] = intval($product['total_stock'] ?? 0);
        $product['available_stock'] = intval($product['available_stock'] ?? 0);
        $product['sold_count'] = intval($product['sold_count'] ?? 0);
        $product['defective_count'] = intval($product['defective_count'] ?? 0);
        
        echo json_encode($product);
    } else {
        echo json_encode(['error' => 'Product not found']);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
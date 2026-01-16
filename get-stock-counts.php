<?php
include 'db.php';
session_start();

// Calculate sidebar counts
$inStockCount = $db->query("SELECT COUNT(*) as count FROM products p 
    LEFT JOIN product_stock ps ON p.id = ps.product_id 
    WHERE ps.status = 'Available' 
    GROUP BY p.id 
    HAVING COUNT(ps.id) > 10")->num_rows;

$outStockCount = $db->query("SELECT COUNT(*) as count FROM products p 
    LEFT JOIN product_stock ps ON p.id = ps.product_id 
    WHERE ps.status = 'Available' 
    GROUP BY p.id 
    HAVING COUNT(ps.id) = 0")->num_rows;

$lowStockCount = $db->query("SELECT COUNT(*) as count FROM products p 
    LEFT JOIN product_stock ps ON p.id = ps.product_id 
    WHERE ps.status = 'Available' 
    GROUP BY p.id 
    HAVING COUNT(ps.id) BETWEEN 1 AND 10")->num_rows;

$soldCount = $db->query("SELECT COUNT(*) as count FROM product_stock WHERE status = 'Sold'")->fetch_assoc()['count'];
$defectiveCount = $db->query("SELECT COUNT(*) as count FROM product_stock WHERE status = 'Defective'")->fetch_assoc()['count'];

echo json_encode([
    'success' => true,
    'in_stock' => $inStockCount,
    'out_stock' => $outStockCount,
    'low_stock' => $lowStockCount,
    'sold' => $soldCount,
    'defective' => $defectiveCount
]);
?>
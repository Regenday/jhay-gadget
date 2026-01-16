<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    exit('Not authorized');
}

// Get current stock counts
$inStock = $db->query("SELECT COUNT(*) as count FROM products WHERE stock > 0")->fetch_assoc()['count'];
$outStock = $db->query("SELECT COUNT(*) as count FROM products WHERE stock = 0")->fetch_assoc()['count'];

// Get sales data from transactions table
$monthlyStats = $db->query("
    SELECT 
        COALESCE(SUM(qty), 0) as total_sales,
        COALESCE(SUM(unit_price * qty), 0) as total_revenue
    FROM transactions 
    WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch_assoc();

// Get weekly sales data
$weeklyStats = $db->query("
    SELECT 
        COALESCE(SUM(qty), 0) as weekly_sales,
        COALESCE(SUM(unit_price * qty), 0) as weekly_revenue
    FROM transactions 
    WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch_assoc();

// Get top product
$topMonthly = $db->query("
    SELECT product_name as name 
    FROM transactions 
    WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
    GROUP BY product_name 
    ORDER BY SUM(qty) DESC 
    LIMIT 1
")->fetch_assoc();

echo json_encode([
    'inStock' => intval($inStock),
    'outStock' => intval($outStock),
    'totalRevenue' => floatval($monthlyStats['total_revenue']),
    'weeklySales' => intval($weeklyStats['weekly_sales']),
    'weeklyRevenue' => floatval($weeklyStats['weekly_revenue']),
    'monthlySales' => intval($monthlyStats['total_sales']),
    'monthlyRevenue' => floatval($monthlyStats['total_revenue']),
    'topMonthly' => $topMonthly ? $topMonthly['name'] : 'No sales data'
]);
?>
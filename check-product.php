<?php
include 'db.php';

$serial = 'po456';

echo "<h2>Find which product $serial belongs to:</h2>";

$query = "SELECT 
            ps.*,
            p.name as product_name,
            p.price,
            p.category
          FROM product_stock ps
          JOIN products p ON ps.product_id = p.id
          WHERE ps.serial_number = '$serial'";

$result = $db->query($query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Serial Number</th><td>{$row['serial_number']}</td></tr>";
    echo "<tr><th>Status</th><td>{$row['status']}</td></tr>";
    echo "<tr><th>Belongs to Product</th><td>{$row['product_name']} (ID: {$row['product_id']})</td></tr>";
    echo "<tr><th>Product Price</th><td>₱" . number_format($row['price'], 2) . "</td></tr>";
    echo "<tr><th>Category</th><td>{$row['category']}</td></tr>";
    echo "<tr><th>Color</th><td>{$row['color']}</td></tr>";
    echo "</table>";
    
    echo "<h3>When purchasing, make sure to select this product:</h3>";
    echo "<p><strong>Product Name:</strong> {$row['product_name']}</p>";
    echo "<p><strong>Product ID:</strong> {$row['product_id']}</p>";
    
} else {
    echo "<p style='color:red'>Serial '$serial' not found in database</p>";
}

// Show all available products
echo "<h3>Available Products in System:</h3>";
$products = $db->query("SELECT id, name, price, category, stock FROM products ORDER BY name");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Name</th><th>Price</th><th>Category</th><th>Stock</th></tr>";
while ($prod = $products->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$prod['id']}</td>";
    echo "<td>{$prod['name']}</td>";
    echo "<td>₱" . number_format($prod['price'], 2) . "</td>";
    echo "<td>{$prod['category']}</td>";
    echo "<td>{$prod['stock']}</td>";
    echo "</tr>";
}
echo "</table>";
?>
<?php
header('Content-Type: application/json'); 
error_reporting(0);
include('db.php'); 
session_start();

try {
    if (!isset($_POST['id'])) {
        echo json_encode(['success' => false, 'error' => 'Missing product ID']);
        exit;
    }

    $id          = intval($_POST['id']);
    $product_code = $_POST['product_code'] ?? '';
    $name        = $_POST['name'] ?? '';
    $items       = $_POST['items'] ?? '';
    $supplier    = $_POST['supplier'] ?? '';
    $price       = (float)($_POST['price'] ?? 0);
    $stock       = intval($_POST['stock'] ?? 0);
    $category    = $_POST['category'] ?? '';
    $added_date  = $_POST['added_date'] ?? '';
    $status      = $_POST['status'] ?? '';

    // Get current stock before update
    $current_stmt = $db->prepare("SELECT stock, name FROM products WHERE id = ?");
    $current_stmt->bind_param("i", $id);
    $current_stmt->execute();
    $current_result = $current_stmt->get_result();
    $current_product = $current_result->fetch_assoc();
    $old_stock = $current_product['stock'];
    $product_name = $current_product['name'];

    // Handle photo upload (optional)
    $photo_sql = '';
    $params = [$product_code, $name, $items, $supplier, $price, $stock, $category, $added_date, $status, $id];
    $types  = "ssssdisssi";

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photo = uniqid() . "." . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], "uploads/" . $photo);

        $photo_sql = ", photo=?";
        $params = [$product_code, $name, $items, $supplier, $price, $stock, $category, $added_date, $status, $photo, $id];
        $types  = "ssssdissssi";
    }

    $sql = "UPDATE products 
            SET product_code=?, name=?, items=?, supplier=?, price=?, stock=?, category=?, date=?, status=? $photo_sql 
            WHERE id=?";

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        // ✅ Log stock history if stock changed
        if ($old_stock != $stock) {
            $change_type = ($stock > $old_stock) ? 'input' : 'edit';
            $changed_by = $_SESSION['username'] ?? 'System';
            $notes = "Stock updated from $old_stock to $stock";
            $change_amount = $stock - $old_stock;
            
            $history_stmt = $db->prepare("INSERT INTO stock_history 
                (product_id, product_name, old_stock, new_stock, change_amount, change_type, changed_by, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $history_stmt->bind_param("isiiisss", 
                $id, 
                $product_name, 
                $old_stock,
                $stock,
                $change_amount,
                $change_type, 
                $changed_by, 
                $notes
            );
            $history_stmt->execute();
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
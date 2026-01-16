<?php
include 'db.php'; // connection

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id']);
    $sale_date  = $_POST['sale_date'];

    // get product info
    $q = $db->prepare("SELECT selling_price, cost_price FROM products WHERE id=?");
    $q->bind_param("i", $product_id);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();

    if ($res) {
        $revenue = $res['selling_price'];
        $profit  = $res['selling_price'] - $res['cost_price'];

        $stmt = $db->prepare("INSERT INTO sales (product_id, sale_date, revenue, profit) VALUES (?,?,?,?)");
        $stmt->bind_param("isdd", $product_id, $sale_date, $revenue, $profit);

        if ($stmt->execute()) {
            echo json_encode(["success"=>true]);
        } else {
            echo json_encode(["success"=>false, "error"=>$stmt->error]);
        }
    } else {
        echo json_encode(["success"=>false, "error"=>"Product not found"]);
    }
}

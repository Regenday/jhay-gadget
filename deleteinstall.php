<?php
include 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id']);
    
    $sql = "DELETE FROM installments WHERE id = $id";
    
    if ($db->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error deleting installment: ' . $db->error]);
    }
    exit();
}
?>
<?php
include 'db.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? '';
    $value = $input['value'] ?? '';
    
    if (empty($type) || empty($value)) {
        echo json_encode(['exists' => false]);
        exit();
    }
    
    try {
        if ($type === 'customer_name') {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM installments WHERE customer_name = ?");
        } elseif ($type === 'contact_number') {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM installments WHERE contact_number = ?");
        } else {
            echo json_encode(['exists' => false]);
            exit();
        }
        
        $stmt->bind_param("s", $value);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        echo json_encode(['exists' => $row['count'] > 0]);
        
    } catch (Exception $e) {
        echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['exists' => false]);
}
?>
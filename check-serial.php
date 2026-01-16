<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $serial_number = $input['serial_number'] ?? '';
    
    // Check if editing existing product
    $edit_id = $input['edit_id'] ?? 0;
    
    if (!empty($serial_number)) {
        // Check if serial number already exists (excluding current product if editing)
        if ($edit_id > 0) {
            $stmt = $db->prepare("SELECT id FROM products WHERE serial_number = ? AND id != ?");
            $stmt->bind_param("si", $serial_number, $edit_id);
        } else {
            $stmt = $db->prepare("SELECT id FROM products WHERE serial_number = ?");
            $stmt->bind_param("s", $serial_number);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo json_encode(['exists' => $result->num_rows > 0]);
    } else {
        echo json_encode(['exists' => false]);
    }
}
?>
<?php
include 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $installment_id = intval($_POST['id']);
        $payment_amount = floatval($_POST['payment_amount']);
        
        // Get current installment details
        $stmt = $db->prepare("SELECT * FROM installments WHERE id = ?");
        $stmt->bind_param("i", $installment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $installment = $result->fetch_assoc();
        
        if (!$installment) {
            throw new Exception('Installment not found');
        }
        
        // Validate payment amount
        if ($payment_amount <= 0) {
            throw new Exception('Payment amount must be greater than 0');
        }
        
        if ($payment_amount > $installment['remaining_amount']) {
            throw new Exception('Payment amount cannot exceed remaining balance');
        }
        
        // Calculate new balances
        $new_paid = $installment['paid_amount'] + $payment_amount;
        $new_remaining = $installment['total_amount'] - $new_paid;
        $previous_balance = $installment['remaining_amount'];
        
        // Update installment
        $update_stmt = $db->prepare("UPDATE installments SET paid_amount = ?, remaining_amount = ?, status = ? WHERE id = ?");
        $new_status = $new_remaining <= 0 ? 'Completed' : $installment['status'];
        $update_stmt->bind_param("ddss", $new_paid, $new_remaining, $new_status, $installment_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception('Failed to update installment');
        }
        
        // Record transaction in install_transactions table
        $transaction_stmt = $db->prepare("INSERT INTO install_transactions (installment_id, customer_name, product_name, payment_amount, previous_balance, new_balance, payment_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $transaction_stmt->bind_param("issddd", $installment_id, $installment['customer_name'], $installment['product_name'], $payment_amount, $previous_balance, $new_remaining);
        
        if (!$transaction_stmt->execute()) {
            throw new Exception('Failed to record transaction');
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Payment added successfully',
            'new_balance' => $new_remaining
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'error' => 'Invalid request method'
    ]);
}
?>
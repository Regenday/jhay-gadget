<?php
include 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

header('Content-Type: application/json');

$search = isset($_GET['search']) ? trim($db->real_escape_string($_GET['search'])) : '';
$filter = isset($_GET['filter']) ? $db->real_escape_string($_GET['filter']) : 'all';
$timeframe = isset($_GET['timeframe']) ? $db->real_escape_string($_GET['timeframe']) : 'all';

try {
    // Query to get stock history with product info
    $query = "SELECT 
                sh.id,
                sh.product_id,
                sh.stock_id,
                sh.change_type as action_type,
                sh.previous_status as old_status,
                sh.new_status,
                sh.changed_by as user_id,
                sh.change_date,
                sh.created_at,
                sh.notes,
                sh.quantity_change as quantity,
                p.name as product_name,
                ps.serial_number,
                ps.color,
                u.username as user_name
              FROM stock_history sh
              LEFT JOIN products p ON sh.product_id = p.id
              LEFT JOIN product_stock ps ON sh.stock_id = ps.id
              LEFT JOIN users u ON sh.changed_by = u.id
              WHERE 1=1";
    
    // Add search conditions
    if (!empty($search)) {
        $query .= " AND (
            p.name LIKE '%$search%' OR 
            ps.serial_number LIKE '%$search%' OR 
            ps.color LIKE '%$search%' OR
            sh.change_type LIKE '%$search%' OR
            sh.notes LIKE '%$search%' OR
            u.username LIKE '%$search%'
        )";
    }
    
    // Add filter condition
    if ($filter != 'all') {
        $query .= " AND sh.change_type = '$filter'";
    }
    
    // Add timeframe condition
    if ($timeframe != 'all') {
        switch($timeframe) {
            case 'today':
                $query .= " AND DATE(sh.change_date) = CURDATE()";
                break;
            case 'week':
                $query .= " AND sh.change_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $query .= " AND sh.change_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
        }
    }
    
    // Order and limit
    $query .= " ORDER BY sh.change_date DESC, sh.created_at DESC LIMIT 100";
    
    $result = $db->query($query);
    $history = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $history[] = [
                'id' => $row['id'],
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'] ?: 'Unknown Product',
                'serial_number' => $row['serial_number'] ?: '',
                'color' => $row['color'] ?: '',
                'action_type' => $row['action_type'] ?: 'unknown',
                'old_status' => $row['old_status'] ?: '',
                'new_status' => $row['new_status'] ?: '',
                'quantity' => $row['quantity'] ?: 0,
                'notes' => $row['notes'] ?: '',
                'user_id' => $row['user_id'],
                'user_name' => $row['user_name'] ?: 'System',
                'created_at' => $row['change_date'] ?: $row['created_at']
            ];
        }
    }
    
    echo json_encode($history);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';
$timeframe = $_GET['timeframe'] ?? 'all';

function getActivityLog($db, $search = '', $filter = 'all', $timeframe = 'all') {
    $activityLog = [];
    
    // Build query for comprehensive activity log
    $query = "
        SELECT 
            'stock_change' as log_type,
            sh.change_date as timestamp,
            sh.change_type as action,
            p.name as product_name,
            ps.serial_number,
            ps.color,
            u.username as user_name,
            CONCAT(
                'Stock ', 
                sh.change_type,
                ' - ',
                p.name,
                CASE WHEN ps.serial_number IS NOT NULL THEN CONCAT(' (SN: ', ps.serial_number, ')') ELSE '' END,
                CASE WHEN ps.color IS NOT NULL THEN CONCAT(' - Color: ', ps.color) ELSE '' END,
                CASE 
                    WHEN sh.previous_status IS NOT NULL AND sh.new_status IS NOT NULL 
                    THEN CONCAT(' - Status: ', sh.previous_status, ' → ', sh.new_status)
                    ELSE ''
                END
            ) as description
        FROM stock_history sh
        LEFT JOIN product_stock ps ON sh.stock_id = ps.id
        LEFT JOIN products p ON sh.product_id = p.id
        LEFT JOIN users u ON sh.changed_by = u.id
        
        UNION ALL
        
        SELECT 
            'product_added' as log_type,
            p.created_at as timestamp,
            'product_added' as action,
            p.name as product_name,
            NULL as serial_number,
            NULL as color,
            'System' as user_name,
            CONCAT('Product Added: ', p.name, ' - ', p.items) as description
        FROM products p
        
        UNION ALL
        
        SELECT 
            'product_updated' as log_type,
            p.updated_at as timestamp,
            'product_updated' as action,
            p.name as product_name,
            NULL as serial_number,
            NULL as color,
            'System' as user_name,
            CONCAT('Product Updated: ', p.name) as description
        FROM products p
        WHERE p.updated_at IS NOT NULL
        
        UNION ALL
        
        SELECT 
            'purchase' as log_type,
            t.created_at as timestamp,
            'purchase' as action,
            'Multiple Products' as product_name,
            NULL as serial_number,
            NULL as color,
            u.username as user_name,
            CONCAT('Purchase Completed - Receipt: ', t.receipt_number, ' - Total: ₱', t.total_amount) as description
        FROM transactions t
        LEFT JOIN users u ON t.cashier_id = u.id
        WHERE t.created_at IS NOT NULL
        
        ORDER BY timestamp DESC
        LIMIT 1000
    ";

    $result = $db->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $activityLog[] = $row;
        }
    }
    
    // Apply filters in PHP for the complex UNION query
    $filteredLog = [];
    foreach ($activityLog as $log) {
        $include = true;
        
        // Apply search filter
        if (!empty($search)) {
            $searchLower = strtolower($search);
            $matchesSearch = 
                stripos($log['product_name'], $search) !== false ||
                stripos($log['description'], $search) !== false ||
                stripos($log['user_name'], $search) !== false ||
                stripos($log['action'], $search) !== false;
            if (!$matchesSearch) {
                $include = false;
            }
        }
        
        // Apply action filter
        if ($filter !== 'all') {
            if ($log['action'] !== $filter) {
                $include = false;
            }
        }
        
        // Apply timeframe filter
        if ($timeframe !== 'all') {
            $logDate = strtotime($log['timestamp']);
            $today = strtotime('today');
            
            switch($timeframe) {
                case 'today':
                    if (date('Y-m-d', $logDate) !== date('Y-m-d')) {
                        $include = false;
                    }
                    break;
                case 'week':
                    $weekStart = strtotime('monday this week');
                    $weekEnd = strtotime('sunday this week');
                    if ($logDate < $weekStart || $logDate > $weekEnd) {
                        $include = false;
                    }
                    break;
                case 'month':
                    if (date('Y-m', $logDate) !== date('Y-m')) {
                        $include = false;
                    }
                    break;
            }
        }
        
        if ($include) {
            $filteredLog[] = $log;
        }
    }
    
    return $filteredLog;
}

$activityLog = getActivityLog($db, $search, $filter, $timeframe);
header('Content-Type: application/json');
echo json_encode($activityLog);
?>
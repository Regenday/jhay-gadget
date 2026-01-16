<?php
// get-sales-data.php
include 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get filters from request
$stockFilter = $_GET['stock'] ?? 'all';
$categoryFilter = $_GET['category'] ?? 'all';
$searchFilter = $_GET['search'] ?? '';
$timeframe = $_GET['timeframe'] ?? 'day';
$startDate = $_GET['startDate'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['endDate'] ?? date('Y-m-d');

// Validate dates
if (!strtotime($startDate) || !strtotime($endDate)) {
    $startDate = date('Y-m-d', strtotime('-30 days'));
    $endDate = date('Y-m-d');
}

// Make sure end date is not before start date
if (strtotime($endDate) < strtotime($startDate)) {
    $temp = $startDate;
    $startDate = $endDate;
    $endDate = $temp;
}

// Debug: Log incoming parameters
error_log("Date Range: $startDate to $endDate");
error_log("Filters: timeframe=$timeframe, category=$categoryFilter, stock=$stockFilter");

// Check if sales table exists
$tableCheck = $db->query("SHOW TABLES LIKE 'sales'");
if ($tableCheck->num_rows == 0) {
    // Return empty data if sales table doesn't exist
    echo json_encode([]);
    exit();
}

try {
    // Build WHERE conditions
    $whereConditions = [];
    $params = [];
    $types = '';
    
    // 1. Date range condition
    $whereConditions[] = "DATE(s.sale_date) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= 'ss';
    
    // 2. Stock filter condition
    if ($stockFilter !== 'all') {
        if ($stockFilter === 'in') {
            // Products with available stock
            $whereConditions[] = "EXISTS (
                SELECT 1 FROM product_stock ps 
                WHERE ps.product_id = p.id AND ps.status = 'Available'
            )";
        } elseif ($stockFilter === 'out') {
            // Products with no available stock
            $whereConditions[] = "NOT EXISTS (
                SELECT 1 FROM product_stock ps 
                WHERE ps.product_id = p.id AND ps.status = 'Available'
            )";
        } elseif ($stockFilter === 'low') {
            // Products with 10 or less available stock
            $whereConditions[] = "EXISTS (
                SELECT 1 FROM product_stock ps 
                WHERE ps.product_id = p.id AND ps.status = 'Available'
                GROUP BY ps.product_id
                HAVING COUNT(ps.id) <= 10
            )";
        }
    }
    
    // 3. Category filter
    if ($categoryFilter !== 'all') {
        $whereConditions[] = "p.category = ?";
        $params[] = $categoryFilter;
        $types .= 's';
    }
    
    // 4. Search filter
    if (!empty($searchFilter)) {
        $whereConditions[] = "(p.name LIKE ? OR p.category LIKE ?)";
        $searchTerm = "%{$searchFilter}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }
    
    // Build final WHERE clause
    $whereClause = implode(" AND ", $whereConditions);
    
    // Build SELECT and GROUP BY based on timeframe
    $selectDate = "";
    $groupBy = "";
    $orderBy = "";
    
    switch($timeframe) {
        case 'day':
            // Daily view - show each day's total
            $selectDate = "DATE(s.sale_date) as date";
            $groupBy = "DATE(s.sale_date)";
            $orderBy = "s.sale_date ASC";
            break;
            
        case 'week':
            // Weekly view - group by ISO week (Monday to Sunday) and show weekly totals
            $selectDate = "DATE(DATE_SUB(s.sale_date, INTERVAL WEEKDAY(s.sale_date) DAY)) as date";
            $groupBy = "YEARWEEK(s.sale_date, 1)"; // Mode 1: Week starts Monday
            $orderBy = "date ASC";
            break;
            
        case 'month':
            // Monthly view - group by year and month, show monthly totals
            $selectDate = "DATE_FORMAT(s.sale_date, '%Y-%m-01') as date";
            $groupBy = "YEAR(s.sale_date), MONTH(s.sale_date)";
            $orderBy = "date ASC";
            break;
            
        default:
            $selectDate = "DATE(s.sale_date) as date";
            $groupBy = "DATE(s.sale_date)";
            $orderBy = "s.sale_date ASC";
    }
    
    // Prepare the query - IMPORTANT: This will sum ALL sales within each time period
    $query = "
        SELECT 
            $selectDate,
            COALESCE(SUM(s.quantity), 0) as qty,
            COALESCE(SUM(s.total_amount), 0) as revenue,
            COALESCE(SUM(s.profit), 0) as profit
        FROM sales s
        INNER JOIN products p ON s.product_id = p.id
        WHERE $whereClause
        GROUP BY $groupBy
        ORDER BY $orderBy
    ";
    
    // Debug: Log the final query
    error_log("Sales Query for $timeframe: " . $query);
    
    // Prepare and execute statement
    if (!empty($params)) {
        $stmt = $db->prepare($query);
        if (!$stmt) {
            throw new Exception("Query preparation failed: " . $db->error . "\nQuery: " . $query);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $result = $db->query($query);
    }
    
    $salesData = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $salesData[] = [
                'date' => $row['date'],
                'qty' => (int)$row['qty'],
                'revenue' => (float)$row['revenue'],
                'profit' => (float)$row['profit']
            ];
        }
        $result->free();
    }
    
    // If no data found with timeframe grouping, try to get daily data and group it manually
    if (empty($salesData) && ($timeframe === 'week' || $timeframe === 'month')) {
        error_log("No data found with $timeframe grouping, trying to get daily data...");
        
        // Get all daily sales first
        $dailyQuery = "
            SELECT 
                DATE(s.sale_date) as date,
                s.quantity as qty,
                s.total_amount as revenue,
                s.profit as profit
            FROM sales s
            INNER JOIN products p ON s.product_id = p.id
            WHERE DATE(s.sale_date) BETWEEN ? AND ?
            ORDER BY s.sale_date ASC
        ";
        
        $dailyStmt = $db->prepare($dailyQuery);
        $dailyStmt->bind_param('ss', $startDate, $endDate);
        $dailyStmt->execute();
        $dailyResult = $dailyStmt->get_result();
        
        $dailySales = [];
        while ($row = $dailyResult->fetch_assoc()) {
            $dailySales[] = $row;
        }
        $dailyStmt->close();
        
        // Group daily sales by week/month
        if (!empty($dailySales)) {
            $groupedData = [];
            
            foreach ($dailySales as $sale) {
                $date = new DateTime($sale['date']);
                
                if ($timeframe === 'week') {
                    // Get Monday of the week
                    $weekStart = clone $date;
                    $weekStart->modify('Monday this week');
                    $key = $weekStart->format('Y-m-d');
                } else {
                    // Get first day of the month
                    $key = $date->format('Y-m-01');
                }
                
                if (!isset($groupedData[$key])) {
                    $groupedData[$key] = [
                        'date' => $key,
                        'qty' => 0,
                        'revenue' => 0,
                        'profit' => 0
                    ];
                }
                
                $groupedData[$key]['qty'] += (int)$sale['qty'];
                $groupedData[$key]['revenue'] += (float)$sale['revenue'];
                $groupedData[$key]['profit'] += (float)$sale['profit'];
            }
            
            // Convert to array and sort by date
            $salesData = array_values($groupedData);
            usort($salesData, function($a, $b) {
                return strcmp($a['date'], $b['date']);
            });
        }
    }
    
    // Debug: Log number of records found
    error_log("$timeframe records found: " . count($salesData));
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($salesData);
    
} catch (Exception $e) {
    error_log("Error in get-sales-data.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch sales data: ' . $e->getMessage()]);
}
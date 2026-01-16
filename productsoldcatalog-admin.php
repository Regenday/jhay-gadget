<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Get filter parameters
$viewType = isset($_GET['view']) ? $_GET['view'] : 'matrix';
$selectedColor = isset($_GET['color']) ? $_GET['color'] : 'all';
$dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'all';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build date filter condition (use purchase date when available)
$dateCondition = "";
// Default date field uses item created date; for matrix view prefer purchase date when available
$dateField = "pi.created_at";
if ($viewType === 'matrix') {
    $dateField = "COALESCE(pur.purchase_date, pi.created_at)";
}
if ($dateFilter !== 'all') {
    $currentDate = date('Y-m-d');
    switch ($dateFilter) {
        case 'today':
            $dateCondition = " AND DATE(" . $dateField . ") = CURDATE()";
            break;
        case 'yesterday':
            $dateCondition = " AND DATE(" . $dateField . ") = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'week':
            $dateCondition = " AND " . $dateField . " >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $dateCondition = " AND " . $dateField . " >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
        case 'custom':
            if (!empty($startDate) && !empty($endDate)) {
                $sd = DateTime::createFromFormat('Y-m-d', $startDate);
                $ed = DateTime::createFromFormat('Y-m-d', $endDate);
                if ($sd && $sd->format('Y-m-d') === $startDate && $ed && $ed->format('Y-m-d') === $endDate) {
                    $dateCondition = " AND DATE(" . $dateField . ") BETWEEN '$startDate' AND '$endDate'";
                }
            }
            break;
    }
}

// Build color filter condition
$colorCondition = "";
if ($selectedColor !== 'all') {
    $colorCondition = " AND UPPER(TRIM(ps.color)) = '" . strtoupper(trim($selectedColor)) . "'";
}

// Get all colors for filter dropdown from product_stock
$allColorsQuery = "
    SELECT DISTINCT UPPER(TRIM(color)) as color 
    FROM product_stock 
    WHERE color IS NOT NULL 
    AND color != '' 
    AND status = 'Sold'
    ORDER BY color
";
$allColorsResult = $db->query($allColorsQuery);
$allColors = [];
if ($allColorsResult) {
    while ($row = $allColorsResult->fetch_assoc()) {
        $allColors[] = $row['color'];
    }
}

// MATRIX VIEW DATA (Product Sales Matrix) - USING purchase_items TABLE WITH ACTUAL CATEGORY
if ($viewType === 'matrix') {
    $productSalesQuery = "
        SELECT 
            pi.product_name,
            p.category,
            COUNT(*) as sales_count,
            SUM(pi.sale_price) as total_revenue,
            SUM(pi.profit) as total_profit,
            AVG(pi.sale_price) as avg_sale_price
        FROM purchase_items pi
        LEFT JOIN products p ON pi.product_id = p.id
        LEFT JOIN purchases pur ON pi.purchase_id = pur.id
        WHERE 1=1
        $dateCondition
        GROUP BY pi.product_name, p.category
        ORDER BY sales_count DESC, total_revenue DESC
    ";

    $result = $db->query($productSalesQuery);
    $productSales = [];
    $allProducts = [];
    $totalSales = 0;
    $totalRevenue = 0;
    $totalProfit = 0;

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $productName = $row['product_name'];
            
            $productSales[$productName] = [
                'sales_count' => $row['sales_count'],
                'total_revenue' => $row['total_revenue'],
                'total_profit' => $row['total_profit'],
                'avg_sale_price' => $row['avg_sale_price'],
                'category' => $row['category'] ?: 'Uncategorized'
            ];
            
            $allProducts[] = $productName;
            
            $totalSales += $row['sales_count'];
            $totalRevenue += $row['total_revenue'];
            $totalProfit += $row['total_profit'];
        }
    }
}

// CATALOG VIEW DATA (Detailed Sales) - USING purchase_items TABLE WITH ACTUAL CATEGORY
if ($viewType === 'catalog') {
    $catalogQuery = "
        SELECT 
            pi.id,
            pi.product_name,
            p.category,
            pi.created_at as sale_date,
            pi.quantity,
            pi.sale_price as total_amount,
            pi.profit,
            pi.cost_price,
            pi.base_price,
            pi.serial_numbers,
            pur.receipt_number,
            pur.purchase_date
        FROM purchase_items pi
        LEFT JOIN products p ON pi.product_id = p.id
        LEFT JOIN purchases pur ON pi.purchase_id = pur.id
        WHERE 1=1
        $dateCondition
        ORDER BY pi.created_at DESC
    ";

    $catalogResult = $db->query($catalogQuery);
    $soldProducts = [];
    $catalogTotalRevenue = 0;
    $catalogTotalProfit = 0;
    $catalogTotalItems = 0;

    if ($catalogResult) {
        while ($row = $catalogResult->fetch_assoc()) {
            $soldProducts[] = $row;
            $catalogTotalRevenue += $row['total_amount'];
            $catalogTotalProfit += $row['profit'];
            $catalogTotalItems += $row['quantity'];
        }
    }
}

// COLOR MATRIX VIEW DATA - Using product_stock for actual color data WITH ACTUAL CATEGORY
if ($viewType === 'color_matrix') {
    $colorMatrixQuery = "
        SELECT 
            p.name as product_name,
            p.category,
            UPPER(TRIM(ps.color)) as color,
            COUNT(*) as sales_count,
            SUM(pi.sale_price) as total_revenue
        FROM product_stock ps
        JOIN products p ON ps.product_id = p.id
        LEFT JOIN purchase_items pi ON ps.product_id = pi.product_id AND ps.serial_number = pi.serial_numbers
        WHERE ps.status = 'Sold'
        AND ps.color IS NOT NULL 
        AND ps.color != ''
        $dateCondition
        $colorCondition
        GROUP BY p.name, p.category, UPPER(TRIM(ps.color))
        ORDER BY p.name, sales_count DESC
    ";

    $colorMatrixResult = $db->query($colorMatrixQuery);
    $colorMatrixData = [];
    $colorMatrixProducts = [];
    $colorMatrixColors = [];
    $colorTotalSales = 0;
    $colorTotalRevenue = 0;

    if ($colorMatrixResult) {
        while ($row = $colorMatrixResult->fetch_assoc()) {
            $productName = $row['product_name'];
            $color = $row['color'];
            
            $colorMatrixData[$productName][$color] = [
                'sales_count' => $row['sales_count'],
                'total_revenue' => $row['total_revenue'],
                'category' => $row['category'] ?: 'Uncategorized'
            ];
            
            if (!in_array($productName, $colorMatrixProducts)) {
                $colorMatrixProducts[] = $productName;
            }
            if (!in_array($color, $colorMatrixColors)) {
                $colorMatrixColors[] = $color;
            }
            
            $colorTotalSales += $row['sales_count'];
            $colorTotalRevenue += $row['total_revenue'];
        }
    }
}

// Get product statistics for the dashboard from purchase_items WITH ACTUAL CATEGORY
$productStatsQuery = "
    SELECT 
        pi.product_name,
        p.category,
        COUNT(*) as total_sold,
        SUM(pi.sale_price) as total_revenue
    FROM purchase_items pi
    LEFT JOIN products p ON pi.product_id = p.id
    GROUP BY pi.product_name, p.category
    ORDER BY total_sold DESC
    LIMIT 10
";

$productStatsResult = $db->query($productStatsQuery);
$productStats = [];

if ($productStatsResult) {
    while ($row = $productStatsResult->fetch_assoc()) {
        $productStats[$row['product_name']] = [
            'count' => $row['total_sold'],
            'category' => $row['category'] ?: 'Uncategorized'
        ];
    }
}

// Get most sold product
$mostSoldProduct = !empty($productStats) ? array_key_first($productStats) : 'No data';
$mostSoldProductCount = !empty($productStats) ? $productStats[$mostSoldProduct]['count'] : 0;
$mostSoldProductCategory = !empty($productStats) ? $productStats[$mostSoldProduct]['category'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JHAY GADGET · Product Sales Analysis</title>
    <style>
        :root {
            --blue:#1e88e5;
            --blue-600:#1976d2;
            --ink:#0f172a;
            --ink-2:#111827;
            --bg:#000;
            --card:#111;
            --border:#333;
            --muted:#9ca3af;
            --accent:#22c55e;
            --sold:#8b5cf6;
        }
        * {box-sizing: border-box; margin: 0; padding: 0;}
        html, body {height: 100%;}
        body {
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial;
            color: #f9fafb;
            background: var(--bg);
            line-height: 1.5;
        }

        .app {display: grid; grid-template-rows: auto 1fr; min-height: 100vh;}
        header {
            background: var(--black);
            color: #fff;
            display: flex; align-items: center; gap: 16px;
            padding: 10px 16px;
            box-shadow: 0 2px 0 rgba(0,0,0,.5);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 220px;
        }
        .brand img {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            border: 3px solid var(--black);
            object-fit: contain;
        }
        .brand .title {font-weight: 700;}
        .crumbs {opacity: .9; font-size: 14px;}
        .crumbs a {color: #cbd5e1; text-decoration: none;}
        .crumbs a:hover {color: #fff;}

        .shell {
            display: grid;
            grid-template-columns: 260px 1fr;
            height: calc(100vh - 52px);
        }
        aside {
            background: var(--ink-2);
            color: #d1d5db;
            padding: 14px 12px;
            border-right: 1px solid #222;
            overflow-y: auto;
            height: calc(100vh - 52px);
            position: sticky;
            top: 52px;
        }

        main {
            padding: 18px;
            overflow: auto;
            background: var(--bg);
            height: calc(100vh - 52px);
        }

        .navGroup {margin: 10px 0 18px;}
        .navTitle {font-size: 12px; text-transform: uppercase; letter-spacing: .12em; color: var(--muted); padding: 6px 10px;}
        .chipRow {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            border-radius: 10px;
            transition: background .2s;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }
        .chipRow:hover {background: #1f2937;}
        .chipLabel {display: flex; align-items: center; gap: 10px;}
        .dot {width: 8px; height: 8px; border-radius: 999px; background: #6b7280;}
        .badge {
            background: #374151; 
            color: #e5e7eb; 
            font-size: 12px; 
            padding: 2px 8px; 
            border-radius: 999px;
            min-width: 20px;
            text-align: center;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .kpi {
            background: #1f2937;
            padding: 18px;
            border-radius: 10px;
            border: 1px solid var(--border);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .kpi:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px -3px rgba(0, 0, 0, 0.3);
        }
        .kpi h3 {
            margin: 0;
            font-size: 14px;
            color: var(--muted);
            font-weight: 500;
        }
        .kpi .value {
            font-size: 24px;
            font-weight: 600;
            margin-top: 6px;
            color: #fff;
        }

        h2 {
            margin: 0 0 20px 0;
            color: #fff;
            font-weight: 600;
            font-size: 1.5rem;
        }

        h3 {
            margin: 0 0 15px 0;
            color: #fff;
            font-weight: 500;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            color: #f1f5f9;
        }
        thead th {
            font-size: 12px;
            text-transform: uppercase;
            color: #9ca3af;
            background: #1e293b;
            text-align: left;
            padding: 12px;
            position: sticky;
            top: 0;
        }
        tbody td {
            padding: 14px 12px;
            border-top: 1px solid var(--border);
        }
        tbody tr:hover {
            background: #1e293b;
        }

        .btn {
            background: var(--blue);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            background: var(--blue-600);
            transform: translateY(-1px);
        }

        .btn.secondary {
            background: #374151;
        }

        .btn.secondary:hover {
            background: #4b5563;
        }

        .export-btn {
            background: var(--accent);
            margin-left: 10px;
        }

        .export-btn:hover {
            background: #16a34a;
        }

        /* View Toggle */
        .view-toggle {
            display: flex;
            background: #1f2937;
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }

        .view-option {
            flex: 1;
            padding: 10px 16px;
            text-align: center;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .view-option.active {
            background: var(--blue);
            color: white;
        }

        .view-option:not(.active):hover {
            background: #374151;
        }

        /* Filter Styles */
        .filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-label {
            font-size: 14px;
            color: var(--muted);
            font-weight: 500;
        }

        .color-select, .date-select {
            padding: 8px 12px;
            border-radius: 8px;
            background: var(--card);
            color: #fff;
            border: 1px solid var(--border);
            min-width: 150px;
            cursor: pointer;
        }

        .date-inputs {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .date-input {
            padding: 8px 12px;
            border-radius: 8px;
            background: var(--card);
            color: #fff;
            border: 1px solid var(--border);
            min-width: 140px;
        }

        .apply-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .apply-btn:hover {
            background: #16a34a;
        }

        .color-select:focus, .date-select:focus, .date-input:focus {
            outline: none;
            border-color: var(--blue);
        }

        .color-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            background: var(--sold);
            color: white;
            margin-left: 8px;
        }

        .product-stats {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .product-stat-item {
            background: #1f2937;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .product-stat-item:hover {
            background: #374151;
            transform: translateY(-1px);
        }

        /* Matrix Table Styles */
        .matrix-table {
            font-size: 14px;
        }
        
        .matrix-table th {
            position: sticky;
            top: 0;
            background: #1e293b;
            z-index: 10;
        }
        
        .matrix-table th:first-child {
            position: sticky;
            left: 0;
            background: #1e293b;
            z-index: 20;
        }
        
        .matrix-table td:first-child {
            position: sticky;
            left: 0;
            background: var(--card);
            z-index: 5;
            font-weight: 500;
        }
        
        .matrix-table tbody tr:hover td:first-child {
            background: #1e293b;
        }
        
        .sales-cell {
            text-align: center;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .sales-cell:hover {
            background: #374151 !important;
            transform: scale(1.05);
        }
        
        .sales-cell.zero {
            color: var(--muted);
            background: #1a1a1a;
        }
        
        .sales-cell.has-sales {
            background: #1f2937;
        }
        
        .product-category {
            color: var(--muted);
            font-size: 12px;
            font-weight: normal;
        }

        .product-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #1f2937;
            border-radius: 6px;
            font-size: 12px;
            margin-left: 8px;
        }

        .receipt-number {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: var(--muted);
            background: #1f2937;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid var(--border);
        }

        .cost-breakdown {
            font-size: 11px;
            color: var(--muted);
        }

        /* Profit highlighting */
        .profit-positive {
            color: var(--accent);
            font-weight: 600;
        }
        
        .profit-negative {
            color: #ef4444;
            font-weight: 600;
        }
        
        .profit-zero {
            color: var(--muted);
        }

        /* Color Matrix Styles */
        .color-cell {
            text-align: center;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 80px;
        }
        
        .color-cell:hover {
            background: #374151 !important;
            transform: scale(1.05);
        }
        
        .color-cell.zero {
            color: var(--muted);
            background: #1a1a1a;
        }
        
        .color-cell.has-sales {
            background: #1f2937;
        }

        /* Mobile Responsive */
        @media (max-width: 980px) {
            .shell {
                grid-template-columns: 80px 1fr;
            }
            
            aside {
                padding: 12px 8px;
            }
            
            .navTitle {
                display: none;
            }
            
            .chipLabel span {
                display: none;
            }
            
            .brand .title {
                display: none;
            }
            
            .filters {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .shell {
                grid-template-columns: 1fr;
            }
            
            aside {
                display: none;
            }
            
            .grid {
                grid-template-columns: 1fr;
            }
            
            .filter-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .matrix-container {
                overflow-x: auto;
            }
            
            .date-inputs {
                flex-direction: column;
            }
            
            .view-toggle {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            main {
                padding: 10px;
            }
            
            .card {
                padding: 15px;
            }
            
            .kpi {
                padding: 12px;
            }
            
            .kpi .value {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="brand">
            <img src="img/jhay-gadget-logo.png.jpg" alt="JHAY GADGET Logo">
            <div>
                <div class="title">JHAY GADGET</div>
                <div class="crumbs">
                    <a href="analytics-admin.php">Analytics</a> &nbsp;›&nbsp; <strong>Product Sales Analysis</strong>
                </div>
            </div>
        </div>
    </header>

    <div class="app">
        <div class="shell">
            <aside>
                <div class="navGroup">
                    <div class="navTitle">Navigation</div>
                    <a href="fronttae-admin.php" class="chipRow">
                        <div class="chipLabel"><span class="dot"></span><span>All Products</span></div>
                    </a>
                    <a href="analytics-admin.php" class="chipRow">
                        <div class="chipLabel"><span class="dot"></span><span>Data Analytics</span></div>
                    </a>
                    <a href="sales-graphs-admin.php" class="chipRow">
                        <div class="chipLabel"><span class="dot"></span><span>Sales Analytics</span></div>
                    </a>
                    <a href="feedback-admin.php" class="chipRow">
                        <div class="chipLabel"><span class="dot"></span><span>Feedback</span></div>
                    </a>
                </div>
            </aside>

            <main>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                    <h2>Product Sales Analysis</h2>
                    <div>
                        <a href="analytics-admin.php" class="btn secondary">Back to Analytics</a>
                        <button class="btn export-btn" onclick="exportToCSV()">Export to CSV</button>
                    </div>
                </div>

                <!-- View Toggle -->
                <div class="view-toggle">
                    <div class="view-option <?php echo $viewType === 'matrix' ? 'active' : ''; ?>" onclick="changeView('matrix')">
                        Product Sales Matrix
                    </div>
                    <div class="view-option <?php echo $viewType === 'color_matrix' ? 'active' : ''; ?>" onclick="changeView('color_matrix')">
                        Color Sales Matrix
                    </div>
                    <div class="view-option <?php echo $viewType === 'catalog' ? 'active' : ''; ?>" onclick="changeView('catalog')">
                        Sales History
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="card">
                    <h3>Filters & Analysis</h3>
                    <div class="filter-section">
                        <div class="filter-group">
                            <label class="filter-label">Date Range:</label>
                            <select class="date-select" id="dateFilter" onchange="toggleCustomDates()">
                                <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All Time</option>
                                <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="yesterday" <?php echo $dateFilter === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                                <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="custom" <?php echo $dateFilter === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                            </select>
                        </div>

                        <?php if ($viewType === 'color_matrix'): ?>
                        <div class="filter-group">
                            <label class="filter-label">Color Filter:</label>
                            <select class="color-select" id="colorFilter" onchange="applyColorFilter()">
                                <option value="all" <?php echo $selectedColor === 'all' ? 'selected' : ''; ?>>All Colors</option>
                                <?php foreach ($allColors as $color): ?>
                                    <option value="<?php echo $color; ?>" <?php echo $selectedColor === $color ? 'selected' : ''; ?>>
                                        <?php echo $color; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="filter-group" id="customDates" style="display: <?php echo $dateFilter === 'custom' ? 'flex' : 'none'; ?>; flex-direction: column; gap: 8px;">
                            <label class="filter-label">Custom Date Range:</label>
                            <div class="date-inputs">
                                <input type="date" class="date-input" id="startDate" value="<?php echo $startDate; ?>" placeholder="Start Date">
                                <span>to</span>
                                <input type="date" class="date-input" id="endDate" value="<?php echo $endDate; ?>" placeholder="End Date">
                                <button class="apply-btn" onclick="applyFilters()">Apply</button>
                            </div>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Most Sold Product:</label>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="color: #fff; font-weight: 500;">
                                    <?php echo htmlspecialchars($mostSoldProduct); ?>
                                </span>
                                <span class="color-badge"><?php echo $mostSoldProductCount; ?> sales</span>
                                <?php if ($mostSoldProductCategory): ?>
                                    <span style="color: var(--muted); font-size: 12px;">(<?php echo htmlspecialchars($mostSoldProductCategory); ?>)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Product Statistics -->
                    <?php if (!empty($productStats)): ?>
                    <div style="margin-top: 20px;">
                        <h4 style="color: var(--muted); margin-bottom: 10px;">Top Products:</h4>
                        <div class="product-stats">
                            <?php foreach ($productStats as $product => $data): ?>
                                <div class="product-stat-item" onclick="filterByProduct('<?php echo htmlspecialchars($product); ?>')">
                                    <span style="color: #fff; font-weight: 500;"><?php echo htmlspecialchars($product); ?></span>
                                    <span style="color: var(--accent); font-weight: 600;"><?php echo $data['count']; ?></span>
                                    <span style="color: var(--muted); font-size: 12px;"><?php echo htmlspecialchars($data['category']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- PRODUCT SALES MATRIX VIEW -->
                <?php if ($viewType === 'matrix'): ?>
                <!-- Overview Grid -->
                <div class="grid">
                    <div class="kpi"><h3>Total Products</h3><div class="value"><?php echo isset($allProducts) ? count($allProducts) : 0; ?></div></div>
                    <div class="kpi"><h3>Total Sales</h3><div class="value"><?php echo isset($totalSales) ? $totalSales : 0; ?></div></div>
                    <div class="kpi"><h3>Total Revenue</h3><div class="value">₱<?php echo isset($totalRevenue) ? number_format($totalRevenue, 2) : '0.00'; ?></div></div>
                    <div class="kpi"><h3>Total Profit</h3><div class="value">₱<?php echo isset($totalProfit) ? number_format($totalProfit, 2) : '0.00'; ?></div></div>
                    <div class="kpi"><h3>Avg Sale Price</h3><div class="value">₱<?php echo isset($totalSales) && $totalSales > 0 ? number_format($totalRevenue / $totalSales, 2) : '0.00'; ?></div></div>
                </div>

                <!-- Product Sales Matrix Table -->
                <h3>
                    Product Sales Matrix
                    <?php if ($dateFilter !== 'all'): ?>
                        <span style="color: var(--muted); font-size: 14px;">
                            - Date: <strong><?php echo ucfirst($dateFilter); ?></strong>
                        </span>
                    <?php endif; ?>
                </h3>
                <div class="card">
                    <div class="matrix-container" style="max-height: 600px; overflow: auto;">
                        <table class="matrix-table">
                            <thead>
                                <tr>
                                    <th style="min-width: 200px;">Product Name</th>
                                    <th style="min-width: 100px; text-align: center;">Sales Count</th>
                                    <th style="min-width: 120px; text-align: center;">Total Revenue</th>
                                    <th style="min-width: 120px; text-align: center;">Total Profit</th>
                                    <th style="min-width: 120px; text-align: center;">Avg Sale Price</th>
                                    <th style="min-width: 100px; text-align: center;">Profit Margin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($allProducts) || empty($productSales)): ?>
                                    <tr>
                                        <td colspan="6" style="padding: 20px; text-align: center; color: var(--muted);">
                                            No product sales data found for the selected filters.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($allProducts as $productName): ?>
                                        <?php if (isset($productSales[$productName])): ?>
                                            <?php 
                                                $product = $productSales[$productName];
                                                $profitMargin = $product['total_revenue'] > 0 ? ($product['total_profit'] / $product['total_revenue']) * 100 : 0;
                                            ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <?php echo htmlspecialchars($productName); ?>
                                                        <div class="product-category"><?php echo htmlspecialchars($product['category']); ?></div>
                                                    </div>
                                                </td>
                                                <td class="sales-cell has-sales" style="text-align: center;">
                                                    <?php echo $product['sales_count']; ?>
                                                </td>
                                                <td style="text-align: center;">
                                                    ₱<?php echo number_format($product['total_revenue'], 2); ?>
                                                </td>
                                                <td style="text-align: center;" class="<?php 
                                                    $profit = $product['total_profit'];
                                                    if ($profit > 0) echo 'profit-positive';
                                                    elseif ($profit < 0) echo 'profit-negative';
                                                    else echo 'profit-zero';
                                                ?>">
                                                    ₱<?php echo number_format($profit, 2); ?>
                                                </td>
                                                <td style="text-align: center;">
                                                    ₱<?php echo number_format($product['avg_sale_price'], 2); ?>
                                                </td>
                                                <td style="text-align: center;" class="<?php 
                                                    if ($profitMargin > 20) echo 'profit-positive';
                                                    elseif ($profitMargin > 0) echo 'profit-zero';
                                                    else echo 'profit-negative';
                                                ?>">
                                                    <?php echo number_format($profitMargin, 1); ?>%
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- COLOR MATRIX VIEW -->
                <?php elseif ($viewType === 'color_matrix'): ?>
                <!-- Overview Grid for Color Matrix -->
                <div class="grid">
                    <div class="kpi"><h3>Total Products</h3><div class="value"><?php echo isset($colorMatrixProducts) ? count($colorMatrixProducts) : 0; ?></div></div>
                    <div class="kpi"><h3>Total Colors</h3><div class="value"><?php echo isset($colorMatrixColors) ? count($colorMatrixColors) : 0; ?></div></div>
                    <div class="kpi"><h3>Total Sales</h3><div class="value"><?php echo isset($colorTotalSales) ? $colorTotalSales : 0; ?></div></div>
                    <div class="kpi"><h3>Total Revenue</h3><div class="value">₱<?php echo isset($colorTotalRevenue) ? number_format($colorTotalRevenue, 2) : '0.00'; ?></div></div>
                </div>

                <!-- Color Sales Matrix Table -->
                <h3>
                    Color Sales Matrix
                    <?php if ($dateFilter !== 'all'): ?>
                        <span style="color: var(--muted); font-size: 14px;">
                            - Date: <strong><?php echo ucfirst($dateFilter); ?></strong>
                        </span>
                    <?php endif; ?>
                    <?php if ($selectedColor !== 'all'): ?>
                        <span style="color: var(--muted); font-size: 14px;">
                            - Color: <strong><?php echo $selectedColor; ?></strong>
                        </span>
                    <?php endif; ?>
                </h3>
                <div class="card">
                    <div class="matrix-container" style="max-height: 600px; overflow: auto;">
                        <table class="matrix-table">
                            <thead>
                                <tr>
                                    <th style="min-width: 200px;">Product Name</th>
                                    <?php if (isset($colorMatrixColors)): ?>
                                        <?php foreach ($colorMatrixColors as $color): ?>
                                            <th style="min-width: 80px; text-align: center;">
                                                <?php echo htmlspecialchars($color); ?>
                                            </th>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($colorMatrixProducts) || empty($colorMatrixData)): ?>
                                    <tr>
                                        <td colspan="<?php echo isset($colorMatrixColors) ? count($colorMatrixColors) + 1 : 1; ?>" style="padding: 20px; text-align: center; color: var(--muted);">
                                            No color sales data found for the selected filters.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($colorMatrixProducts as $productName): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <?php echo htmlspecialchars($productName); ?>
                                                    <?php if (isset($colorMatrixData[$productName]) && !empty($colorMatrixData[$productName])): ?>
                                                        <?php 
                                                            $firstColor = array_key_first($colorMatrixData[$productName]);
                                                            $category = $colorMatrixData[$productName][$firstColor]['category'];
                                                        ?>
                                                        <div class="product-category"><?php echo htmlspecialchars($category); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <?php foreach ($colorMatrixColors as $color): ?>
                                                <td class="color-cell <?php echo (isset($colorMatrixData[$productName][$color]) && $colorMatrixData[$productName][$color]['sales_count'] > 0) ? 'has-sales' : 'zero'; ?>"
                                                    onclick="showColorDetails('<?php echo htmlspecialchars($productName); ?>', '<?php echo htmlspecialchars($color); ?>')"
                                                    title="Click for details">
                                                    <?php if (isset($colorMatrixData[$productName][$color])): ?>
                                                        <?php echo $colorMatrixData[$productName][$color]['sales_count']; ?>
                                                    <?php else: ?>
                                                        0
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- CATALOG VIEW -->
                <?php elseif ($viewType === 'catalog'): ?>
                <!-- Overview Grid for Catalog -->
                <div class="grid">
                    <div class="kpi"><h3>Total Items Sold</h3><div class="value"><?php echo isset($catalogTotalItems) ? $catalogTotalItems : 0; ?></div></div>
                    <div class="kpi"><h3>Total Transactions</h3><div class="value"><?php echo isset($soldProducts) ? count($soldProducts) : 0; ?></div></div>
                    <div class="kpi"><h3>Total Revenue</h3><div class="value">₱<?php echo isset($catalogTotalRevenue) ? number_format($catalogTotalRevenue, 2) : '0.00'; ?></div></div>
                    <div class="kpi"><h3>Total Profit</h3><div class="value">₱<?php echo isset($catalogTotalProfit) ? number_format($catalogTotalProfit, 2) : '0.00'; ?></div></div>
                </div>

                <!-- Sales History Table -->
                <h3>
                    Sales History
                    <?php if ($dateFilter !== 'all'): ?>
                        <span style="color: var(--muted); font-size: 14px;">
                            - Date: <strong><?php echo ucfirst($dateFilter); ?></strong>
                        </span>
                    <?php endif; ?>
                </h3>
                <div class="card">
                    <div style="max-height: 600px; overflow-y: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Sale Date</th>
                                    <th>Qty</th>
                                    <th>Cost Price</th>
                                    <th>Sale Price</th>
                                    <th>Profit</th>
                                    <th>Serial Numbers</th>
                                    <th>Receipt #</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($soldProducts)): ?>
                                    <tr>
                                        <td colspan="9" style="padding: 20px; text-align: center; color: var(--muted);">
                                            No sales history found for the selected filters.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($soldProducts as $product): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['category'] ?: 'Uncategorized'); ?></td>
                                            <td>
                                                <?php 
                                                    $saleDate = !empty($product['purchase_date']) ? $product['purchase_date'] : $product['sale_date'];
                                                    echo date('M j, Y g:i A', strtotime($saleDate)); 
                                                ?>
                                            </td>
                                            <td><?php echo $product['quantity']; ?></td>
                                            <td>
                                                ₱<?php echo number_format($product['cost_price'], 2); ?>
                                                <?php if (isset($product['base_price']) && $product['base_price'] > 0): ?>
                                                    <div class="cost-breakdown">Base: ₱<?php echo number_format($product['base_price'], 2); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>₱<?php echo number_format($product['total_amount'], 2); ?></td>
                                            <td class="<?php 
                                                $profit = $product['profit'];
                                                if ($profit > 0) echo 'profit-positive';
                                                elseif ($profit < 0) echo 'profit-negative';
                                                else echo 'profit-zero';
                                            ?>">
                                                ₱<?php echo number_format($profit, 2); ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($product['serial_numbers'])): ?>
                                                    <span class="receipt-number"><?php echo htmlspecialchars($product['serial_numbers']); ?></span>
                                                <?php else: ?>
                                                    <span style="color: var(--muted); font-size: 12px;">No serial</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($product['receipt_number'])): ?>
                                                    <span class="receipt-number"><?php echo htmlspecialchars($product['receipt_number']); ?></span>
                                                <?php else: ?>
                                                    <span style="color: var(--muted); font-size: 12px;">No receipt</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        function changeView(viewType) {
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            
            // Update the view parameter
            urlParams.set('view', viewType);
            
            // Keep existing filter parameters
            const dateFilter = document.getElementById('dateFilter');
            const colorFilter = document.getElementById('colorFilter');
            const startDate = document.getElementById('startDate');
            const endDate = document.getElementById('endDate');
            
            if (dateFilter) {
                urlParams.set('date_filter', dateFilter.value);
            }
            
            if (colorFilter) {
                if (colorFilter.value !== 'all') {
                    urlParams.set('color', colorFilter.value);
                } else {
                    urlParams.delete('color');
                }
            }
            
            if (startDate && startDate.value) {
                urlParams.set('start_date', startDate.value);
            }
            
            if (endDate && endDate.value) {
                urlParams.set('end_date', endDate.value);
            }
            
            // Build new URL
            const newUrl = window.location.pathname + '?' + urlParams.toString();
            
            // Navigate to new URL
            window.location.href = newUrl;
        }

        function filterByProduct(productName) {
            alert('Filtering by product: ' + productName + '\nThis feature can be implemented to filter the sales data by specific product.');
        }

        function toggleCustomDates() {
            const dateFilter = document.getElementById('dateFilter').value;
            const customDates = document.getElementById('customDates');
            customDates.style.display = dateFilter === 'custom' ? 'flex' : 'none';
        }

        function applyColorFilter() {
            const colorFilter = document.getElementById('colorFilter').value;
            const url = new URL(window.location.href);
            
            if (colorFilter === 'all') {
                url.searchParams.delete('color');
            } else {
                url.searchParams.set('color', colorFilter);
            }
            
            window.location.href = url.toString();
        }

        function applyFilters() {
            const url = new URL(window.location.href);
            const dateFilter = document.getElementById('dateFilter').value;
            
            url.searchParams.set('date_filter', dateFilter);
            
            if (dateFilter === 'custom') {
                const startDate = document.getElementById('startDate').value;
                const endDate = document.getElementById('endDate').value;
                if (startDate && endDate) {
                    url.searchParams.set('start_date', startDate);
                    url.searchParams.set('end_date', endDate);
                }
            } else {
                url.searchParams.delete('start_date');
                url.searchParams.delete('end_date');
            }
            
            window.location.href = url.toString();
        }

        function showColorDetails(productName, color) {
            alert('Product: ' + productName + '\nColor: ' + color + '\n\nSwitch to Sales History view for detailed information.');
        }

        function exportToCSV() {
            <?php if ($viewType === 'matrix'): ?>
                // Matrix view CSV export
                let csvContent = "Product Name,Category,Sales Count,Total Revenue,Total Profit,Average Sale Price,Profit Margin\n";
                <?php if (isset($productSales)): ?>
                    <?php foreach ($productSales as $productName => $data): ?>
                        <?php 
                            $profitMargin = $data['total_revenue'] > 0 ? ($data['total_profit'] / $data['total_revenue']) * 100 : 0;
                        ?>
                        csvContent += `"<?php echo addslashes($productName); ?>","<?php echo addslashes($data['category']); ?>",<?php echo $data['sales_count']; ?>,<?php echo $data['total_revenue']; ?>,<?php echo $data['total_profit']; ?>,<?php echo $data['avg_sale_price']; ?>,<?php echo $profitMargin; ?>\n`;
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php elseif ($viewType === 'color_matrix'): ?>
                // Color Matrix view CSV export
                let csvContent = "Product Name,Category,Color,Sales Count,Total Revenue\n";
                <?php if (isset($colorMatrixData)): ?>
                    <?php foreach ($colorMatrixData as $productName => $colors): ?>
                        <?php foreach ($colors as $color => $data): ?>
                            csvContent += `"<?php echo addslashes($productName); ?>","<?php echo addslashes($data['category']); ?>","<?php echo addslashes($color); ?>",<?php echo $data['sales_count']; ?>,<?php echo $data['total_revenue']; ?>\n`;
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php else: ?>
                // Catalog view CSV export
                let csvContent = "Product Name,Category,Sale Date,Quantity,Cost Price,Base Price,Sale Price,Profit,Serial Numbers,Receipt Number\n";
                <?php if (isset($soldProducts)): ?>
                    <?php foreach ($soldProducts as $product): ?>
                        <?php 
                            $saleDate = !empty($product['purchase_date']) ? $product['purchase_date'] : $product['sale_date'];
                        ?>
                        csvContent += `"<?php echo addslashes($product['product_name']); ?>","<?php echo addslashes($product['category']); ?>","<?php echo date('M j, Y g:i A', strtotime($saleDate)); ?>",<?php echo $product['quantity']; ?>,<?php echo $product['cost_price']; ?>,<?php echo isset($product['base_price']) ? $product['base_price'] : '0'; ?>,<?php echo $product['total_amount']; ?>,<?php echo $product['profit']; ?>,"<?php echo addslashes($product['serial_numbers']); ?>","<?php echo addslashes($product['receipt_number']); ?>"\n`;
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>

            // Create and download file
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `product-sales-<?php echo $viewType; ?>-${new Date().toISOString().split('T')[0]}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
        }

        // Initialize custom dates visibility
        document.addEventListener('DOMContentLoaded', function() {
            toggleCustomDates();
        });
    </script>
</body>
</html>
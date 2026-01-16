<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Include database connection
if (file_exists('db.php')) {
    include 'db.php';
} else {
    die("Database connection file not found");
}

// Get timeframe from request or default to monthly
$timeframe = isset($_GET['timeframe']) ? $_GET['timeframe'] : 'monthly';

// Get category filter from request
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : 'all';

// Validate timeframe
$allowedTimeframes = ['daily', 'weekly', 'monthly', 'all'];
$timeframe = in_array($timeframe, $allowedTimeframes) ? $timeframe : 'monthly';

// Set date ranges based on timeframe
switch($timeframe) {
    case 'daily':
        $dateRange = "DATE(s.sale_date) = CURDATE()";
        $intervalText = "Today";
        break;
    case 'weekly':
        $dateRange = "s.sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $intervalText = "Last 7 Days";
        break;
    case 'monthly':
    default:
        $dateRange = "s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $intervalText = "Last 30 Days";
        break;
    case 'all':
        $dateRange = "1=1";
        $intervalText = "All Time";
        break;
}

try {
    // Build category filter condition
    $categoryCondition = "";
    if ($categoryFilter !== 'all') {
        $escapedCategory = $db->real_escape_string($categoryFilter);
        $categoryCondition = " AND p.category = '$escapedCategory'";
    }

    // Fetch products for sidebar with accurate counts
    $products = [];
    $res = $db->query("
        SELECT 
            p.*,
            COALESCE(SUM(CASE WHEN ps.status = 'Available' THEN 1 ELSE 0 END), 0) as available_stock,
            COALESCE(SUM(CASE WHEN ps.status = 'Sold' THEN 1 ELSE 0 END), 0) as sold_count,
            COALESCE(SUM(CASE WHEN ps.status = 'Defective' THEN 1 ELSE 0 END), 0) as defective_count,
            COUNT(ps.id) as total_stock
        FROM products p 
        LEFT JOIN product_stock ps ON p.id = ps.product_id 
        WHERE 1=1 $categoryCondition
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $products[] = $row;
        }
    }

    // Get purchase data for comparison with category filter
    $purchaseStatsRes = $db->query("
        SELECT 
            COALESCE(SUM(pi.quantity), 0) as total_purchased,
            COALESCE(SUM(pi.quantity * pi.sale_price), 0) as total_purchase_value
        FROM purchase_items pi
        INNER JOIN products p ON pi.product_id = p.id
        WHERE 1=1 $categoryCondition
    ");
    
    $purchaseStats = $purchaseStatsRes && $purchaseStatsRes->num_rows > 0 
        ? $purchaseStatsRes->fetch_assoc() 
        : ['total_purchased' => 0, 'total_purchase_value' => 0];

    // Fetch sales data for selected timeframe with category filter
    $salesData = [];
    $salesRes = $db->query("
        SELECT 
            p.id,
            p.name,
            p.category,
            p.price,
            COALESCE(SUM(s.quantity), 0) as total_quantity,
            COALESCE(SUM(s.total_amount), 0) as total_revenue,
            COALESCE(SUM(s.profit), 0) as total_profit
        FROM products p
        LEFT JOIN sales s ON p.id = s.product_id AND $dateRange
        WHERE 1=1 $categoryCondition
        GROUP BY p.id, p.name, p.category, p.price
        HAVING total_quantity > 0
    ");

    if ($salesRes) {
        while ($row = $salesRes->fetch_assoc()) {
            $salesData[] = $row;
        }
    }

    // Get stats for selected timeframe with category filter
    $statsRes = $db->query("
        SELECT 
            COALESCE(SUM(s.quantity), 0) as total_sales,
            COALESCE(SUM(s.total_amount), 0) as total_revenue,
            COALESCE(SUM(s.profit), 0) as total_profit
        FROM sales s
        INNER JOIN products p ON s.product_id = p.id
        WHERE $dateRange $categoryCondition
    ");
    
    $stats = $statsRes && $statsRes->num_rows > 0
        ? $statsRes->fetch_assoc()
        : ['total_sales' => 0, 'total_revenue' => 0, 'total_profit' => 0];

    // Get comparison data for different timeframes with category filter
    $dailyStatsRes = $db->query("
        SELECT 
            COALESCE(SUM(s.quantity), 0) as sales,
            COALESCE(SUM(s.total_amount), 0) as revenue,
            COALESCE(SUM(s.profit), 0) as profit
        FROM sales s
        INNER JOIN products p ON s.product_id = p.id
        WHERE DATE(s.sale_date) = CURDATE() $categoryCondition
    ");
    
    $dailyStats = $dailyStatsRes && $dailyStatsRes->num_rows > 0
        ? $dailyStatsRes->fetch_assoc()
        : ['sales' => 0, 'revenue' => 0, 'profit' => 0];

    $weeklyStatsRes = $db->query("
        SELECT 
            COALESCE(SUM(s.quantity), 0) as sales,
            COALESCE(SUM(s.total_amount), 0) as revenue,
            COALESCE(SUM(s.profit), 0) as profit
        FROM sales s
        INNER JOIN products p ON s.product_id = p.id
        WHERE s.sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) $categoryCondition
    ");
    
    $weeklyStats = $weeklyStatsRes && $weeklyStatsRes->num_rows > 0
        ? $weeklyStatsRes->fetch_assoc()
        : ['sales' => 0, 'revenue' => 0, 'profit' => 0];

    $monthlyStatsRes = $db->query("
        SELECT 
            COALESCE(SUM(s.quantity), 0) as sales,
            COALESCE(SUM(s.total_amount), 0) as revenue,
            COALESCE(SUM(s.profit), 0) as profit
        FROM sales s
        INNER JOIN products p ON s.product_id = p.id
        WHERE s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) $categoryCondition
    ");
    
    $monthlyStats = $monthlyStatsRes && $monthlyStatsRes->num_rows > 0
        ? $monthlyStatsRes->fetch_assoc()
        : ['sales' => 0, 'revenue' => 0, 'profit' => 0];

    // Get all unique categories from products
    $categories = [];
    $categoryRes = $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
    if ($categoryRes) {
        while ($row = $categoryRes->fetch_assoc()) {
            $categories[] = $row['category'];
        }
    }

    // Get top products for selected timeframe with category filter
    $topProductRes = $db->query("
        SELECT p.name, SUM(s.quantity) as total_sold
        FROM sales s
        JOIN products p ON s.product_id = p.id
        WHERE $dateRange $categoryCondition
        GROUP BY p.id, p.name
        ORDER BY total_sold DESC
        LIMIT 1
    ");
    
    $topProduct = $topProductRes && $topProductRes->num_rows > 0 
        ? $topProductRes->fetch_assoc() 
        : ['name' => 'No sales data', 'total_sold' => 0];

    // Get chart data for top products for selected timeframe with category filter
    $topProductsChartRes = $db->query("
        SELECT p.name, COALESCE(SUM(s.quantity), 0) as total_sold
        FROM products p
        LEFT JOIN sales s ON p.id = s.product_id AND $dateRange
        WHERE 1=1 $categoryCondition
        GROUP BY p.id, p.name
        HAVING total_sold > 0
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    
    $topProductsChart = [];
    if ($topProductsChartRes) {
        while ($row = $topProductsChartRes->fetch_assoc()) {
            $topProductsChart[] = $row;
        }
    }

    // Get profit analysis data for selected timeframe with category filter
    $profitAnalysisRes = $db->query("
        SELECT 
            p.name,
            COALESCE(SUM(s.total_amount), 0) as revenue,
            COALESCE(SUM(s.profit), 0) as profit
        FROM products p
        LEFT JOIN sales s ON p.id = s.product_id AND $dateRange
        WHERE 1=1 $categoryCondition
        GROUP BY p.id, p.name
        HAVING revenue > 0
        ORDER BY revenue DESC
        LIMIT 8
    ");
    
    $profitAnalysis = [];
    if ($profitAnalysisRes) {
        while ($row = $profitAnalysisRes->fetch_assoc()) {
            $profitAnalysis[] = $row;
        }
    }

    // Get sales trend data for the chart with category filter
    $salesTrendRes = $db->query("
        SELECT 
            p.name,
            COALESCE(SUM(CASE WHEN DATE(s.sale_date) = CURDATE() THEN s.quantity ELSE 0 END), 0) as daily_sales,
            COALESCE(SUM(CASE WHEN s.sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN s.quantity ELSE 0 END), 0) as weekly_sales,
            COALESCE(SUM(CASE WHEN s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN s.quantity ELSE 0 END), 0) as monthly_sales
        FROM products p
        LEFT JOIN sales s ON p.id = s.product_id
        WHERE 1=1 $categoryCondition
        GROUP BY p.id, p.name
        HAVING monthly_sales > 0 OR weekly_sales > 0 OR daily_sales > 0
        ORDER BY monthly_sales DESC
        LIMIT 10
    ");
    
    $salesTrend = [];
    if ($salesTrendRes) {
        while ($row = $salesTrendRes->fetch_assoc()) {
            $salesTrend[] = $row;
        }
    }

    // Calculate stock counts for sidebar with category filter
    $inStockQuery = $db->query("
        SELECT COUNT(DISTINCT p.id) as count 
        FROM products p 
        INNER JOIN product_stock ps ON p.id = ps.product_id 
        WHERE ps.status = 'Available' $categoryCondition
    ");
    
    $inStockCount = $inStockQuery && $inStockQuery->num_rows > 0 
        ? $inStockQuery->fetch_assoc()['count'] 
        : 0;

    $outStockQuery = $db->query("
        SELECT COUNT(DISTINCT p.id) as count 
        FROM products p 
        LEFT JOIN product_stock ps ON p.id = ps.product_id AND ps.status = 'Available'
        WHERE ps.id IS NULL $categoryCondition
    ");
    
    $outStockCount = $outStockQuery && $outStockQuery->num_rows > 0 
        ? $outStockQuery->fetch_assoc()['count'] 
        : 0;

    $lowStockQuery = $db->query("
        SELECT COUNT(DISTINCT p.id) as count 
        FROM products p 
        WHERE EXISTS (
            SELECT 1 FROM product_stock ps 
            WHERE ps.product_id = p.id AND ps.status = 'Available'
            GROUP BY ps.product_id
            HAVING COUNT(ps.id) <= 3
        ) $categoryCondition
    ");
    
    $lowStockCount = $lowStockQuery && $lowStockQuery->num_rows > 0 
        ? $lowStockQuery->fetch_assoc()['count'] 
        : 0;

    // Calculate total products with category filter
    $totalProductsQuery = $db->query("
        SELECT COUNT(*) as count 
        FROM products p 
        WHERE 1=1 $categoryCondition
    ");
    
    $totalProducts = $totalProductsQuery && $totalProductsQuery->num_rows > 0 
        ? $totalProductsQuery->fetch_assoc()['count'] 
        : 0;

    // Calculate total sold items for inventory turnover
    $totalSoldQuery = $db->query("
        SELECT COALESCE(SUM(s.quantity), 0) as total_sold
        FROM sales s
        INNER JOIN products p ON s.product_id = p.id
        WHERE 1=1 $categoryCondition
    ");
    
    $totalSold = $totalSoldQuery && $totalSoldQuery->num_rows > 0 
        ? $totalSoldQuery->fetch_assoc()['total_sold'] 
        : 0;

    // Calculate total available stock for inventory turnover
    $totalAvailableStockQuery = $db->query("
        SELECT COALESCE(COUNT(ps.id), 0) as total_available
        FROM product_stock ps
        INNER JOIN products p ON ps.product_id = p.id
        WHERE ps.status = 'Available' $categoryCondition
    ");
    
    $totalAvailableStock = $totalAvailableStockQuery && $totalAvailableStockQuery->num_rows > 0 
        ? $totalAvailableStockQuery->fetch_assoc()['total_available'] 
        : 0;

    // Calculate inventory turnover rate
    $turnoverRate = 0;
    if ($totalAvailableStock > 0) {
        $turnoverRate = ($totalSold / $totalAvailableStock) * 100;
    }

    // Calculate profit margin
    $profitMargin = 0;
    if ($stats['total_revenue'] > 0) {
        $profitMargin = ($stats['total_profit'] / $stats['total_revenue']) * 100;
    }

    // Calculate purchase vs sales ratio
    $purchaseVsSales = 0;
    if ($purchaseStats['total_purchased'] > 0) {
        $purchaseVsSales = ($stats['total_sales'] / $purchaseStats['total_purchased']) * 100;
    }

    // Calculate average sale value
    $averageSale = 0;
    if ($stats['total_sales'] > 0) {
        $averageSale = $stats['total_revenue'] / $stats['total_sales'];
    }

} catch (Exception $e) {
    die("An error occurred while loading analytics data: " . $e->getMessage());
}

// Set currency symbol
$currency = '₱';
$appName = 'JHAY GADGET';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo $appName; ?> · Data Analytics</title>
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
            --stock-low:#dc2626;
            --stock-ok:#22c55e;
            --sold:#8b5cf6;
            --defective:#f97316;
        }
        * {box-sizing: border-box; margin: 0; padding: 0;}
        html, body {height: 100%;}
        body {
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial;
            color: #f9fafb;
            background: var(--bg);
            line-height: 1.5;
        }

        .app {display: grid; grid-template-rows: auto 1fr; height: 100%;}
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

        .shell {display: grid; grid-template-columns: 260px 1fr; height: calc(100vh - 52px);}
        aside {
            background: var(--ink-2);
            color: #d1d5db;
            padding: 14px 12px;
            border-right: 1px solid #222;
            overflow-y: auto;
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

        main {
            padding: 18px;
            overflow: auto;
            background: var(--bg);
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
        .kpi .trend {
            font-size: 12px;
            margin-top: 4px;
        }
        .trend.positive { color: var(--accent); }
        .trend.negative { color: var(--stock-low); }

        .timeframe-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .timeframe-btn {
            background: #374151;
            color: #e5e7eb;
            border: 1px solid #4b5563;
            border-radius: 8px;
            padding: 10px 20px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .timeframe-btn:hover {
            background: #4b5563;
            border-color: #6b7280;
            transform: translateY(-1px);
        }

        .timeframe-btn.active {
            background: var(--blue);
            border-color: var(--blue-600);
            color: white;
            box-shadow: 0 2px 4px rgba(30, 136, 229, 0.3);
        }

        /* Action Buttons Styles */
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .sold-products-btn {
            background: var(--sold);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .sold-products-btn:hover {
            background: #7c3aed;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }

        .export-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .export-btn:hover {
            background: #16a34a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }

        .dropdown-wrapper {
            margin-bottom: 20px;
        }

        select {
            width: 220px;
            padding: 8px 12px;
            font-size: 14px;
            border-radius: 8px;
            background: var(--card);
            color: #fff;
            border: 1px solid var(--border);
            appearance: none;
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%239ca3af' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 8px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 32px;
        }

        select:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(30, 136, 229, 0.1);
        }

        .comparison-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .comparison-item {
            background: #1f2937;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border);
            text-align: center;
            transition: transform 0.2s ease;
        }
        .comparison-item:hover {
            transform: translateY(-2px);
        }
        .comparison-item h4 {
            margin: 0 0 8px 0;
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            font-weight: 600;
        }
        .comparison-value {
            font-size: 18px;
            font-weight: 600;
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

        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #374151;
            border-radius: 50%;
            border-top-color: var(--blue);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Toast notification */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--accent);
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            display: none;
            z-index: 2000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        /* Chart fallback message */
        .chart-fallback {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            color: var(--muted);
            padding: 20px;
        }

        .chart-fallback button {
            margin-top: 15px;
            padding: 8px 16px;
            background: var(--blue);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .chart-fallback button:hover {
            background: var(--blue-600);
        }

        /* Mobile Responsive */
        @media (max-width: 980px) {
            .shell {grid-template-columns: 80px 1fr;}
            aside {padding: 12px 8px;}
            .navTitle {display: none;}
            .chipLabel span {display: none;}
            .brand .title {display: none;}
        }

        @media (max-width: 768px) {
            .shell {grid-template-columns: 1fr;}
            aside {display: none;}
            .grid {grid-template-columns: 1fr;}
            .timeframe-filter {justify-content: center;}
            .comparison-grid {grid-template-columns: 1fr;}
            select {width: 100%;}
            .action-buttons {flex-direction: column;}
            .sold-products-btn, .export-btn {justify-content: center;}
        }

        @media (max-width: 480px) {
            main {padding: 10px;}
            .card {padding: 15px;}
            .kpi {padding: 12px;}
            .kpi .value {font-size: 20px;}
            .sold-products-btn, .export-btn {padding: 10px 16px; font-size: 13px;}
        }
    </style>
    <!-- Load Chart.js locally first, with CDN fallback -->
    <script src="js/chart.min.js"></script>
    <script>
        // Check if Chart.js loaded successfully
        if (typeof Chart === 'undefined') {
            console.warn('Local Chart.js not found, trying CDN...');
            document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js"><\/script>');
        }
    </script>
</head>
<body>
    <header>
        <div class="brand">
            <img src="img/jhay-gadget-logo.png.jpg" alt="<?php echo $appName; ?> Logo">
            <div>
                <div class="title"><?php echo $appName; ?></div>
                <div class="crumbs">
                    <a href="fronttae.php">All Products</a> &nbsp;›&nbsp; <strong>Data Analytics</strong>
                    <?php if ($categoryFilter !== 'all'): ?>
                        &nbsp;›&nbsp; <strong><?php echo htmlspecialchars($categoryFilter); ?></strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="app">
        <div class="shell">
            <aside>
                <div class="navGroup">
                    <div class="navTitle">Navigation</div>
                    <a href="fronttae.php" class="chipRow">
                        <div class="chipLabel"><span class="dot"></span><span>All Products</span></div>
                    </a>
                    <a href="analytics.php" class="chipRow" style="background: #1f2937;">
                        <div class="chipLabel"><span class="dot"></span><span>Data Analytics</span></div>
                    </a>
                    <a href="sales-graphs.php" class="chipRow">
                        <div class="chipLabel"><span class="dot"></span><span>Sales Analytics</span></div>
                    </a>
                    <a href="feedback.php" class="chipRow">
                        <div class="chipLabel"><span class="dot"></span><span>Feedback</span></div>
                    </a>
                </div>
                <div class="navGroup">
                    <div class="navTitle">Stock Status</div>
                    <div class="chipRow">
                        <div class="chipLabel"><span>In Stock</span></div>
                        <span class="badge" id="inStockBadge"><?php echo $inStockCount; ?></span>
                    </div>
                    <div class="chipRow">
                        <div class="chipLabel"><span>Out of Stock</span></div>
                        <span class="badge" id="outStockBadge"><?php echo $outStockCount; ?></span>
                    </div>
                    <div class="chipRow">
                        <div class="chipLabel"><span>Low Stock</span></div>
                        <span class="badge" id="lowStockBadge"><?php echo $lowStockCount; ?></span>
                    </div>
                    <div class="chipRow">
                        <div class="chipLabel"><span>Total Products</span></div>
                        <span class="badge" id="totalProducts"><?php echo $totalProducts; ?></span>
                    </div>
                </div>
            </aside>

            <main>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                    <h2>Sales Analytics - <?php echo $intervalText; ?><?php echo $categoryFilter !== 'all' ? ' - ' . htmlspecialchars($categoryFilter) : ''; ?></h2>
                    <div class="timeframe-filter">
                        <button class="timeframe-btn <?php echo $timeframe == 'daily' ? 'active' : ''; ?>" onclick="changeTimeframe('daily')">Daily</button>
                        <button class="timeframe-btn <?php echo $timeframe == 'weekly' ? 'active' : ''; ?>" onclick="changeTimeframe('weekly')">Weekly</button>
                        <button class="timeframe-btn <?php echo $timeframe == 'monthly' ? 'active' : ''; ?>" onclick="changeTimeframe('monthly')">Monthly</button>
                        <button class="timeframe-btn <?php echo $timeframe == 'all' ? 'active' : ''; ?>" onclick="changeTimeframe('all')">All Time</button>
                    </div>
                </div>

                <!-- Action Buttons Section -->
                <div class="action-buttons">
                    <a href="productsoldcatalog-admin.php" class="sold-products-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14,2 14,8 20,8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10,9 9,9 8,9"></polyline>
                        </svg>
                        View Sold Products Catalog
                    </a>
                    <button class="export-btn" onclick="exportAnalyticsData()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7,10 12,15 17,10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        Export Analytics Data
                    </button>
                </div>

                <!-- Category Filter Section -->
                <div class="dropdown-wrapper">
                    <label for="categorySelect" style="font-size: 14px; color: var(--muted); display: block; margin-bottom: 6px;">Filter by Category:</label>
                    <select id="categorySelect" onchange="applyCategoryFilter()">
                        <option value="all" <?php echo $categoryFilter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $categoryFilter === $category ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Timeframe Comparison -->
                <div class="comparison-grid">
                    <div class="comparison-item">
                        <h4>Today</h4>
                        <div class="comparison-value"><?php echo $currency . number_format($dailyStats['revenue'], 2); ?></div>
                        <div style="font-size: 12px; color: #9ca3af;"><?php echo $dailyStats['sales']; ?> sales</div>
                    </div>
                    <div class="comparison-item">
                        <h4>This Week</h4>
                        <div class="comparison-value"><?php echo $currency . number_format($weeklyStats['revenue'], 2); ?></div>
                        <div style="font-size: 12px; color: #9ca3af;"><?php echo $weeklyStats['sales']; ?> sales</div>
                    </div>
                    <div class="comparison-item">
                        <h4>This Month</h4>
                        <div class="comparison-value"><?php echo $currency . number_format($monthlyStats['revenue'], 2); ?></div>
                        <div style="font-size: 12px; color: #9ca3af;"><?php echo $monthlyStats['sales']; ?> sales</div>
                    </div>
                </div>

                <!-- Purchase Comparison -->
                <div class="comparison-grid" style="margin-top: 20px;">
                    <div class="comparison-item">
                        <h4>Items Purchased</h4>
                        <div class="comparison-value"><?php echo $purchaseStats['total_purchased']; ?> units</div>
                        <div style="font-size: 12px; color: #9ca3af;"><?php echo $currency . number_format($purchaseStats['total_purchase_value'], 2); ?> value</div>
                    </div>
                    <div class="comparison-item">
                        <h4>Purchase vs Sales</h4>
                        <div class="comparison-value">
                            <?php 
                            if ($purchaseStats['total_purchased'] > 0) {
                                echo number_format($purchaseVsSales, 1) . '%';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </div>
                        <div style="font-size: 12px; color: #9ca3af;">Sales ratio</div>
                    </div>
                </div>

                <!-- Overview Grid -->
                <h3>Overview</h3>
                <div class="grid" id="overviewGrid">
                    <div class="kpi"><h3>Total Products</h3><div class="value"><?php echo $totalProducts; ?></div></div>
                    <div class="kpi"><h3>In Stock</h3><div class="value" id="dynamicInStock"><?php echo $inStockCount; ?></div></div>
                    <div class="kpi"><h3>Out of Stock</h3><div class="value" id="dynamicOutStock"><?php echo $outStockCount; ?></div></div>
                    <div class="kpi"><h3>Items Purchased</h3><div class="value"><?php echo $purchaseStats['total_purchased']; ?> units</div></div>
                    <div class="kpi"><h3>Total Revenue</h3><div class="value"><?php echo $currency . number_format($stats['total_revenue'], 2); ?></div></div>
                </div>

                <h3>Sales Summary - <?php echo $intervalText; ?></h3>
                <div class="grid">
                    <div class="kpi"><h3>Total Sales</h3><div class="value" id="totalSales"><?php echo $stats['total_sales']; ?> units</div></div>
                    <div class="kpi"><h3>Total Revenue</h3><div class="value" id="totalRevenue"><?php echo $currency . number_format($stats['total_revenue'], 2); ?></div></div>
                    <div class="kpi"><h3>Total Profit</h3><div class="value" id="totalProfit"><?php echo $currency . number_format($stats['total_profit'], 2); ?></div></div>
                    <div class="kpi"><h3>Avg. Sale Value</h3><div class="value" id="averageSale">
                        <?php echo $currency . number_format($averageSale, 2); ?>
                    </div></div>
                </div>

                <h3>Sales Insights</h3>
                <div class="grid">
                    <div class="kpi"><h3>Top Product</h3><div class="value" id="topProduct"><?php echo htmlspecialchars($topProduct['name']); ?></div></div>
                    <div class="kpi"><h3>Products Sold</h3><div class="value"><?php echo count($salesData); ?></div></div>
                    <div class="kpi"><h3>Profit Margin</h3><div class="value" id="profitMargin">
                        <?php echo number_format($profitMargin, 1) . '%'; ?>
                    </div></div>
                    <div class="kpi"><h3>Inventory Turnover</h3><div class="value" id="turnoverRate">
                        <?php echo number_format($turnoverRate, 1) . '%'; ?>
                    </div></div>
                </div>

                <h3>Sales Trend</h3>
                <div class="card">
                    <div class="chart-container">
                        <canvas id="salesTrendChart"></canvas>
                        <div id="salesTrendFallback" class="chart-fallback" style="display: none;">
                            <p>Chart cannot be displayed. Chart.js library is not available.</p>
                            <button onclick="location.reload()">Retry Loading Chart</button>
                        </div>
                    </div>
                </div>

                <h3>Top Products - <?php echo $intervalText; ?></h3>
                <div class="card">
                    <div class="chart-container">
                        <canvas id="topProductsChart"></canvas>
                        <div id="topProductsFallback" class="chart-fallback" style="display: none;">
                            <p>Chart cannot be displayed. Chart.js library is not available.</p>
                            <button onclick="location.reload()">Retry Loading Chart</button>
                        </div>
                    </div>
                </div>

                <h3>Profit Analysis - <?php echo $intervalText; ?></h3>
                <div class="card">
                    <div class="chart-container">
                        <canvas id="profitChart"></canvas>
                        <div id="profitChartFallback" class="chart-fallback" style="display: none;">
                            <p>Chart cannot be displayed. Chart.js library is not available.</p>
                            <button onclick="location.reload()">Retry Loading Chart</button>
                        </div>
                    </div>
                </div>
                
            </main>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>

    <script>
        // Convert PHP data to JavaScript
        const salesTrend = <?php echo json_encode($salesTrend); ?>;
        const topProductsChart = <?php echo json_encode($topProductsChart); ?>;
        const profitAnalysis = <?php echo json_encode($profitAnalysis); ?>;
        const currentTimeframe = '<?php echo $timeframe; ?>';
        const currentCategory = '<?php echo $categoryFilter; ?>';
        const currencySymbol = '<?php echo $currency; ?>';

        // Toast notification function
        function showToast(msg, color = 'var(--accent)') {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.style.background = color;
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }

        // Function to apply category filter
        function applyCategoryFilter(category = null) {
            if (category === null) {
                category = document.getElementById('categorySelect').value;
            }
            
            // Build URL with current timeframe and selected category
            let url = 'analytics-admin.php?';
            url += 'timeframe=' + currentTimeframe;
            url += '&category=' + encodeURIComponent(category);
            
            window.location.href = url;
        }

        // Function to change timeframe (updated to preserve category filter)
        function changeTimeframe(timeframe) {
            let url = 'analytics-admin.php?';
            url += 'timeframe=' + timeframe;
            
            // Keep the current category filter
            if (currentCategory !== 'all') {
                url += '&category=' + encodeURIComponent(currentCategory);
            }
            
            window.location.href = url;
        }

        // Function to export analytics data
        function exportAnalyticsData() {
            // Create CSV content for analytics data
            let csvContent = "Metric,Value,Timeframe\n";
            
            // Add overview data
            csvContent += `Total Products,${<?php echo $totalProducts; ?>},"<?php echo $intervalText; ?>"\n`;
            csvContent += `In Stock,${<?php echo $inStockCount; ?>},"<?php echo $intervalText; ?>"\n`;
            csvContent += `Out of Stock,${<?php echo $outStockCount; ?>},"<?php echo $intervalText; ?>"\n`;
            csvContent += `Items Purchased,${<?php echo $purchaseStats['total_purchased']; ?>},"<?php echo $intervalText; ?>"\n`;
            csvContent += `Total Sales,${<?php echo $stats['total_sales']; ?>},"<?php echo $intervalText; ?>"\n`;
            csvContent += `Total Revenue,${currencySymbol}${<?php echo $stats['total_revenue']; ?>},"<?php echo $intervalText; ?>"\n`;
            csvContent += `Total Profit,${currencySymbol}${<?php echo $stats['total_profit']; ?>},"<?php echo $intervalText; ?>"\n`;
            
            // Add top products
            csvContent += "\nTop Products,Quantity Sold,Timeframe\n";
            <?php foreach ($topProductsChart as $product): ?>
                csvContent += `"<?php echo addslashes($product['name']); ?>",${<?php echo $product['total_sold']; ?>},"<?php echo $intervalText; ?>"\n`;
            <?php endforeach; ?>

            // Create and download file
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `analytics-data-${new Date().toISOString().split('T')[0]}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showToast('Data exported successfully!', '#22c55e');
        }

        // Show chart fallback message
        function showChartFallback(chartId) {
            const fallback = document.getElementById(chartId + 'Fallback');
            const canvas = document.getElementById(chartId);
            if (fallback && canvas) {
                fallback.style.display = 'flex';
                canvas.style.display = 'none';
            }
        }

        // Hide chart fallback message
        function hideChartFallback(chartId) {
            const fallback = document.getElementById(chartId + 'Fallback');
            const canvas = document.getElementById(chartId);
            if (fallback && canvas) {
                fallback.style.display = 'none';
                canvas.style.display = 'block';
            }
        }

        // Initialize charts
        function initializeCharts() {
            // Check if Chart.js is available
            if (typeof Chart === 'undefined') {
                console.error('Chart.js is not loaded. Cannot initialize charts.');
                showChartFallback('salesTrendChart');
                showChartFallback('topProductsChart');
                showChartFallback('profitChart');
                return;
            }

            // Hide all fallback messages
            hideChartFallback('salesTrendChart');
            hideChartFallback('topProductsChart');
            hideChartFallback('profitChart');

            try {
                // Sales Trend Chart
                const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
                const salesTrendLabels = salesTrend.map(item => item.name);
                const dailyData = salesTrend.map(item => parseInt(item.daily_sales) || 0);
                const weeklyData = salesTrend.map(item => parseInt(item.weekly_sales) || 0);
                const monthlyData = salesTrend.map(item => parseInt(item.monthly_sales) || 0);

                new Chart(salesTrendCtx, {
                    type: 'bar',
                    data: {
                        labels: salesTrendLabels,
                        datasets: [
                            {
                                label: 'Today',
                                data: dailyData,
                                backgroundColor: '#1e88e5',
                                borderColor: '#1565c0',
                                borderWidth: 1
                            },
                            {
                                label: 'This Week',
                                data: weeklyData,
                                backgroundColor: '#43a047',
                                borderColor: '#2e7d32',
                                borderWidth: 1
                            },
                            {
                                label: 'This Month',
                                data: monthlyData,
                                backgroundColor: '#ff9800',
                                borderColor: '#f57c00',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Sales Trend Comparison',
                                color: '#f9fafb',
                                font: {
                                    size: 16
                                }
                            },
                            legend: {
                                labels: {
                                    color: '#f9fafb'
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Quantity Sold',
                                    color: '#f9fafb'
                                },
                                ticks: {
                                    color: '#f9fafb'
                                },
                                grid: {
                                    color: '#374151'
                                }
                            },
                            x: {
                                ticks: {
                                    color: '#f9fafb',
                                    maxRotation: 45
                                },
                                grid: {
                                    color: '#374151'
                                }
                            }
                        }
                    }
                });

                // Top Products Chart
                const topProductsCtx = document.getElementById('topProductsChart').getContext('2d');
                const topProductsLabels = topProductsChart.map(item => item.name);
                const topProductsData = topProductsChart.map(item => parseInt(item.total_sold) || 0);

                new Chart(topProductsCtx, {
                    type: 'doughnut',
                    data: {
                        labels: topProductsLabels,
                        datasets: [{
                            data: topProductsData,
                            backgroundColor: [
                                '#1e88e5', '#43a047', '#ff9800', '#e53935', '#8e24aa'
                            ],
                            borderWidth: 1,
                            borderColor: '#111'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: `Top 5 Products - <?php echo $intervalText; ?>`,
                                color: '#f9fafb',
                                font: {
                                    size: 16
                                }
                            },
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: '#f9fafb'
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += context.parsed + ' units';
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });

                // Profit Chart
                const profitCtx = document.getElementById('profitChart').getContext('2d');
                const profitLabels = profitAnalysis.map(item => item.name);
                const revenueData = profitAnalysis.map(item => parseFloat(item.revenue) || 0);
                const profitData = profitAnalysis.map(item => parseFloat(item.profit) || 0);

                new Chart(profitCtx, {
                    type: 'bar',
                    data: {
                        labels: profitLabels,
                        datasets: [
                            {
                                label: 'Revenue',
                                data: revenueData,
                                backgroundColor: '#1e88e5',
                                borderColor: '#1565c0',
                                borderWidth: 1
                            },
                            {
                                label: 'Profit',
                                data: profitData,
                                backgroundColor: '#43a047',
                                borderColor: '#2e7d32',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: `Revenue vs Profit - <?php echo $intervalText; ?>`,
                                color: '#f9fafb',
                                font: {
                                    size: 16
                                }
                            },
                            legend: {
                                labels: {
                                    color: '#f9fafb'
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Amount (' + currencySymbol + ')',
                                    color: '#f9fafb'
                                },
                                ticks: {
                                    color: '#f9fafb',
                                    callback: function(value) {
                                        return currencySymbol + value.toLocaleString();
                                    }
                                },
                                grid: {
                                    color: '#374151'
                                }
                            },
                            x: {
                                ticks: {
                                    color: '#f9fafb',
                                    maxRotation: 45
                                },
                                grid: {
                                    color: '#374151'
                                }
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error initializing charts:', error);
                showToast('Failed to initialize charts. Please refresh the page.', '#dc2626');
            }
        }

        // REAL-TIME PURCHASE DETECTION
        function checkForRecentPurchases() {
            fetch('check-recent-purchases.php')
                .then(response => response.json())
                .then(data => {
                    if (data.has_new_purchases) {
                        showToast('New purchase detected! Updating analytics...', '#22c55e');
                        // Refresh immediately when purchase is detected
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    }
                })
                .catch(error => console.error('Error checking for purchases:', error));
        }

        // Check for localStorage updates (from products page)
        function checkForLocalStorageUpdates() {
            const lastUpdate = localStorage.getItem('analytics_last_update');
            const currentTime = Date.now();
            
            if (lastUpdate && (currentTime - parseInt(lastUpdate)) < 5000) {
                // Update detected in last 5 seconds
                showToast('Data updated! Refreshing analytics...', '#22c55e');
                localStorage.removeItem('analytics_last_update');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            }
        }

        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Wait a moment to ensure Chart.js is loaded
            setTimeout(initializeCharts, 100);
            
            // Start real-time monitoring
            setInterval(checkForRecentPurchases, 5000); // Check every 5 seconds
            setInterval(checkForLocalStorageUpdates, 2000); // Check localStorage every 2 seconds
        });

        // Fallback: if charts fail to initialize after 2 seconds
        setTimeout(function() {
            if (typeof Chart === 'undefined') {
                showToast('Chart library not loaded. Some features may be limited.', '#f97316');
            }
        }, 2000);
    </script>
</body>
</html>
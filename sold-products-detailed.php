<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Get filter parameters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$product_name = $_GET['product_name'] ?? '';
$category = $_GET['category'] ?? '';

// Build WHERE clause for filters
$whereConditions = [];
$params = [];
$types = '';

if (!empty($start_date)) {
    $whereConditions[] = "DATE(s.sale_date) >= ?";
    $params[] = $start_date;
    $types .= 's';
}

if (!empty($end_date)) {
    $whereConditions[] = "DATE(s.sale_date) <= ?";
    $params[] = $end_date;
    $types .= 's';
}

if (!empty($product_name)) {
    $whereConditions[] = "p.name LIKE ?";
    $params[] = "%$product_name%";
    $types .= 's';
}

if (!empty($category)) {
    $whereConditions[] = "p.category = ?";
    $params[] = $category;
    $types .= 's';
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// Fetch products for sidebar counts
$products = [];
$res = $db->query("
    SELECT 
        p.*,
        COUNT(ps.id) as total_stock,
        COUNT(CASE WHEN ps.status = 'Available' THEN 1 END) as available_stock,
        COUNT(CASE WHEN ps.status = 'Sold' THEN 1 END) as sold_count,
        COUNT(CASE WHEN ps.status = 'Defective' THEN 1 END) as defective_count
    FROM products p 
    LEFT JOIN product_stock ps ON p.id = ps.product_id 
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $products[] = $row;
    }
}

// Calculate sidebar counts - use per-product critical_stock
// In Stock: any product with available stock > 0 (includes low stock)
$inStockCount = count(array_filter($products, function($p) {
    return ($p['available_stock'] ?? 0) > 0;
}));
$outStockCount = count(array_filter($products, function($p) {
    return ($p['available_stock'] ?? 0) == 0;
}));
$lowStockCount = count(array_filter($products, function($p) {
    $critical = isset($p['critical_stock']) ? intval($p['critical_stock']) : 10;
    return ($p['available_stock'] ?? 0) > 0 && ($p['available_stock'] ?? 0) <= $critical;
}));
$soldCount = array_sum(array_column($products, 'sold_count'));
$defectiveCount = array_sum(array_column($products, 'defective_count'));

// Fetch sold products with filters
$soldProducts = [];
$query = "
    SELECT 
        p.name,
        p.category,
        p.price,
        ps.serial_number,
        ps.color,
        s.sale_date,
        s.quantity,
        s.total_amount,
        s.profit,
        s.receipt_number
    FROM sales s
    JOIN products p ON s.product_id = p.id
    JOIN product_stock ps ON s.stock_id = ps.id
    $whereClause
    ORDER BY s.sale_date DESC
";

if (!empty($params)) {
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $db->query($query);
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $soldProducts[] = $row;
    }
}

// Get unique categories for filter dropdown
$categories = [];
$categoryRes = $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != ''");
if ($categoryRes) {
    while ($row = $categoryRes->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

// Calculate totals
$totalRevenue = array_sum(array_column($soldProducts, 'total_amount'));
$totalProfit = array_sum(array_column($soldProducts, 'profit'));
$totalItems = count($soldProducts);

// Get most sold products
$mostSoldProducts = [];
$mostSoldRes = $db->query("
    SELECT 
        p.name,
        p.category,
        SUM(s.quantity) as total_sold,
        SUM(s.total_amount) as total_revenue
    FROM sales s
    JOIN products p ON s.product_id = p.id
    GROUP BY p.id, p.name, p.category
    ORDER BY total_sold DESC
    LIMIT 10
");

if ($mostSoldRes) {
    while ($row = $mostSoldRes->fetch_assoc()) {
        $mostSoldProducts[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JHAY GADGET · Detailed Sold Products</title>
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
        }
        tbody td {
            padding: 14px 12px;
            border-top: 1px solid var(--border);
        }
        tbody tr:hover {
            background: #1e293b;
        }

        .filter-form {
            background: #1f2937;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid var(--border);
            margin-bottom: 20px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 5px;
            font-weight: 500;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--card);
            color: #fff;
            font-size: 14px;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--blue);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
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
            .filter-row {grid-template-columns: 1fr;}
        }

        @media (max-width: 480px) {
            main {padding: 10px;}
            .card {padding: 15px;}
            .kpi {padding: 12px;}
            .kpi .value {font-size: 20px;}
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
                    <a href="sold-catalog.php">Sold Products</a> &nbsp;›&nbsp; <strong>Detailed Catalog</strong>
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
                    <a href="analytics.php" class="chipRow">
                        <div class="chipLabel"><span class="dot"></span><span>Data Analytics</span></div>
                    </a>
                    <a href="sold-catalog.php" class="chipRow">
                        <div class="chipLabel"><span class="dot"></span><span>Sold Products</span></div>
                    </a>
                    <a href="sold-products-detailed.php" class="chipRow" style="background: #1f2937;">
                        <div class="chipLabel"><span class="dot"></span><span>Detailed Catalog</span></div>
                    </a>
                    <a href="sales-graphs.php" class="chipRow">
                        <div class="chipLabel"><span class="dot"></span><span>Sales Analytics</span></div>
                    </a>
                    <a href="installments.php" class="chipRow">
                        <div class="chipLabel"><span class="dot"></span><span>Installments</span></div>
                    </a>
                    <a href="feedback.php" class="chipRow">
                        <div class="chipLabel"><span class="dot"></span><span>Feedback</span></div>
                    </a>
                </div>
                <div class="navGroup">
                    <div class="navTitle">Stock Status</div>
                    <div class="chipRow">
                        <div class="chipLabel"><span>In Stock</span></div><span class="badge" id="inStockBadge"><?php echo $inStockCount; ?></span>
                    </div>
                    <div class="chipRow">
                        <div class="chipLabel"><span>Out of Stock</span></div><span class="badge" id="outStockBadge"><?php echo $outStockCount; ?></span>
                    </div>
                    <div class="chipRow">
                        <div class="chipLabel"><span>Low Stock</span></div><span class="badge" id="lowStockBadge"><?php echo $lowStockCount; ?></span>
                    </div>
                    <div class="chipRow">
                        <div class="chipLabel"><span>Sold</span></div><span class="badge" id="soldBadge"><?php echo $soldCount; ?></span>
                    </div>
                    <div class="chipRow">
                        <div class="chipLabel"><span>Defective</span></div><span class="badge" id="defectiveBadge"><?php echo $defectiveCount; ?></span>
                    </div>
                </div>
                <div class="navGroup">
                    <div class="navTitle">Quick Actions</div>
                    <a href="add-product.php" class="chipRow">
                        <div class="chipLabel"><span>Add Product</span></div>
                    </a>
                    <a href="add-stock.php" class="chipRow">
                        <div class="chipLabel"><span>Add Stock</span></div>
                    </a>
                    <a href="reports.php" class="chipRow">
                        <div class="chipLabel"><span>Generate Report</span></div>
                    </a>
                </div>
            </aside>

            <main>
                <h2>Detailed Sold Products Catalog</h2>

                <!-- Filter Form -->
                <div class="card">
                    <h3>Filter Results</h3>
                    <form method="GET" class="filter-form">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="filter-group">
                                <label for="end_date">End Date</label>
                                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                            <div class="filter-group">
                                <label for="product_name">Product Name</label>
                                <input type="text" id="product_name" name="product_name" value="<?php echo htmlspecialchars($product_name); ?>" placeholder="Search product name...">
                            </div>
                            <div class="filter-group">
                                <label for="category">Category</label>
                                <select id="category" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category == $cat ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn">Apply Filters</button>
                            <a href="sold-products-detailed.php" class="btn secondary">Clear Filters</a>
                        </div>
                    </form>
                </div>

                <!-- Overview Grid -->
                <h3>Filtered Results Overview</h3>
                <div class="grid">
                    <div class="kpi"><h3>Total Items Sold</h3><div class="value"><?php echo $totalItems; ?></div></div>
                    <div class="kpi"><h3>Total Revenue</h3><div class="value">₱<?php echo number_format($totalRevenue, 2); ?></div></div>
                    <div class="kpi"><h3>Total Profit</h3><div class="value">₱<?php echo number_format($totalProfit, 2); ?></div></div>
                    <div class="kpi"><h3>Average Sale</h3><div class="value">
                        <?php 
                        if ($totalItems > 0) {
                            echo '₱' . number_format($totalRevenue / $totalItems, 2);
                        } else {
                            echo '₱0.00';
                        }
                        ?>
                    </div></div>
                </div>

                <!-- Most Sold Products -->
                <h3>Most Sold Products</h3>
                <div class="card">
                    <div style="max-height: 300px; overflow-y: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Total Sold</th>
                                    <th>Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mostSoldProducts)): ?>
                                    <tr>
                                        <td colspan="4" style="padding: 20px; text-align: center; color: var(--muted);">
                                            No sales data available.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($mostSoldProducts as $product): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                                            <td><?php echo $product['total_sold']; ?> units</td>
                                            <td style="color: var(--accent);">₱<?php echo number_format($product['total_revenue'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sold Products Table -->
                <h3>All Sold Products</h3>
                <div class="card">
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Serial Number</th>
                                    <th>Color</th>
                                    <th>Sale Date</th>
                                    <th>Price</th>
                                    <th>Profit</th>
                                    <th>Receipt #</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($soldProducts)): ?>
                                    <tr>
                                        <td colspan="8" style="padding: 20px; text-align: center; color: var(--muted);">
                                            No sold products found with the current filters.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($soldProducts as $product): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                                            <td><?php echo htmlspecialchars($product['serial_number']); ?></td>
                                            <td><?php echo htmlspecialchars($product['color']); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($product['sale_date'])); ?></td>
                                            <td>₱<?php echo number_format($product['total_amount'], 2); ?></td>
                                            <td style="color: var(--accent);">₱<?php echo number_format($product['profit'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($product['receipt_number']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
<?php
include 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Get accurate stock counts from both products and product_stock tables
$inStockCount = 0;
$outStockCount = 0;
$lowStockCount = 0;

// Check if product_stock table exists
$tableCheck = $db->query("SHOW TABLES LIKE 'product_stock'");
$hasProductStock = $tableCheck->num_rows > 0;

if ($hasProductStock) {
    // Get stock counts from product_stock table (most accurate)
    $query = "
        SELECT 
            p.id,
            COUNT(CASE WHEN ps.status = 'Available' THEN 1 END) as available_stock
        FROM products p 
        LEFT JOIN product_stock ps ON p.id = ps.product_id 
        GROUP BY p.id
    ";
    
    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $available_stock = $row['available_stock'] ?? 0;
            
            if ($available_stock > 0) {
                $inStockCount++; // Has at least 1 available item
                if ($available_stock <= 3) {
                    $lowStockCount++;
                }
            } else {
                $outStockCount++;
            }
        }
    }
} else {
    // Fallback to products table stock field
    $query = "SELECT id, stock FROM products";
    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stock = $row['stock'] ?? 0;
            
            if ($stock > 0) {
                $inStockCount++;
                if ($stock <= 3) {
                    $lowStockCount++;
                }
            } else {
                $outStockCount++;
            }
        }
    }
}

// Fetch product list for other purposes
$products = [];
$res = $db->query("SELECT id, name, stock FROM products ORDER BY name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $products[] = $row;
    }
}

// Get all unique categories for the filter dropdown
$categories = [];
$categoryRes = $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
if ($categoryRes) {
    while ($row = $categoryRes->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sales Graphs · JHAY Gadget</title>
<style>
:root {
  --blue:#1e88e5; --blue-600:#1976d2;
  --green:#43a047; --red:#e53935;
  --ink:#0f172a; --ink-2:#111827;
  --bg:#000; --card:#111; --border:#333;
  --muted:#9ca3af;
}
* {box-sizing:border-box;}
html,body{height:100%;margin:0;}
body {
  font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial;
  color:#f9fafb; background:var(--bg);
}
.app{display:grid; grid-template-rows:auto 1fr; height:100%;}
header{
  background:var(--black); color:#fff; display:flex; align-items:center; gap:16px;
  padding:10px 16px; box-shadow:0 2px 0 rgba(0,0,0,.5);
}
.brand{display:flex;align-items:center;gap:12px;min-width:220px;}
.brand img {width:48px;height:48px;border-radius:8px;border:3px solid var(--black);object-fit:contain;}
.brand .title{font-weight:700;}
.shell{display:grid; grid-template-columns:260px 1fr; height:calc(100vh - 52px);}
aside{
  background:var(--ink-2); color:#d1d5db; padding:14px 12px; overflow:auto;
  border-right:1px solid #222;
}
.navGroup{margin:10px 0 18px;}
.navTitle{font-size:12px;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);padding:6px 10px;}
.chipRow{display:flex;align-items:center;justify-content:space-between;padding:10px;border-radius:10px;transition:background .2s;cursor:pointer;text-decoration:none;color:inherit;}
.chipRow:hover{background:#1f2937;}
.chipLabel{display:flex;align-items:center;gap:10px;}
.dot{width:8px;height=8px;border-radius:999px;background:#6b7280;}
.badge{background:#374151;color:#e5e7eb;font-size:12px;padding:2px 8px;border-radius:999px;} 
.filters{padding:8px 10px;}
.filter{border-top:1px dashed #2d3748;padding:12px 0;}
.filter label{display:block;font-size:12px;color:#9ca3af;margin-bottom:6px;}
.yn{display:flex;gap:8px;flex-wrap:wrap;}
.yn button{
  background:#1f2937;color:#e5e7eb;border:1px solid #374151;border-radius:999px;
  padding:6px 10px;cursor:pointer;font-size:12px;transition:all 0.2s;
}
.yn button.active{
  background:var(--blue);border-color:var(--blue-600);color:white;
}
main{padding:18px;overflow:auto;}
.toolbar{display:flex;gap:10px;align-items:center;margin-bottom:12px;}
.search{
  flex:1; display:flex; align-items:center; gap:8px; background:var(--card);
  border:1px solid var(--border); border-radius:12px; padding:8px 10px;
}
.search input{border:0; outline:0; width:100%; font-size:14px; background:transparent; color:#fff;}
.btn{background:#111;border:1px solid var(--border);border-radius:12px;padding:8px 14px;cursor:pointer;color:#fff;transition:all 0.2s;}
.btn.primary{background:var(--blue);color:#fff;border-color:var(--blue-600);}
.btn:active{transform:translateY(1px);}
.card{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:auto;}
table{width:100%;border-collapse:separate;border-spacing:0;color:#f1f5f9;}
thead th{font-size:12px;text-transform:uppercase;color:#9ca3af;background:#1e293b;text-align:left;padding:12px;}
tbody td{padding:14px 12px;border-top:1px solid var(--border);}
tbody tr:hover{background:#1e293b;}
.status{display:inline-flex;align-items:center;gap:8px;background:#065f46;color:#fff;padding:4px 10px;border-radius:999px;font-size:12px;}
.storeLogo{width:40px;height:40px;border-radius:8px;background:#2563eb;display:grid;place-items:center;color:#fff;font-weight:700;object-fit:cover;}
.stockLevel {font-weight:600;font-size:13px;padding:4px 8px;border-radius:6px;display:inline-block;}
.stock-low {background: var(--red);color: white;}
.stock-ok {background: var(--green);color: white;}
@media (max-width: 980px){
  .shell{grid-template-columns:80px 1fr;}
  aside{padding:12px 8px;}
  .navTitle{display:none;}
  .chipLabel span{display:none;}
  .filters{display:none;}
}
@media (max-width: 720px){
  .toolbar{flex-wrap:wrap;}
  thead{display:none;}
  tbody td{display:block;border-top:0;}
  tbody tr{display:block;border-top:1px solid var(--border);padding:12px;}
  tbody td[data-th]::before{content:attr(data-th) ": ";font-weight:600;color:#9ca3af;margin-right:6px;}
}
select {width:100%;padding:6px 10px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:#f9fafb;font-size:14px;appearance:none;cursor:pointer;}
select:focus {outline:2px solid var(--blue);}

/* Sales Graphs Specific Styles */
.container {padding:20px;}
.filters {display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;}
select, button, input {
  padding:6px 12px; border-radius:6px; border:none;
  background:#1f2937; color:#fff; cursor:pointer;
}
#chartContainer {background:var(--card); padding:20px; border-radius:12px;}
form {display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;}
form input, form select {flex:1; min-width:150px;}
@media(max-width:720px){.filters, form{flex-direction:column;}}
.btn.print {background:var(--blue); color:#fff;}
.btn.refresh {background:var(--green); color:#fff;}

/* Chart container styles */
.chart-container {
  position: relative;
  height: 400px;
  width: 100%;
  margin-bottom: 20px;
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

/* Print Styles */
@media print {
  body { 
    background:#fff !important; 
    color:#000 !important; 
    font-family: Arial, sans-serif !important;
  }
  
  /* Hide elements that shouldn't print */
  .app > header,
  .shell > aside,
  .toolbar,
  .filters,
  .btn,
  .search,
  .print-hide {
    display: none !important;
  }
  
  /* Show only main content */
  .shell {
    grid-template-columns: 1fr !important;
    height: auto !important;
  }
  
  main {
    padding: 0 !important;
    margin: 0 !important;
    width: 100% !important;
  }
  
  .card {
    background: #fff !important;
    border: 2px solid #000 !important;
    border-radius: 8px !important;
    box-shadow: none !important;
    margin: 0 !important;
    padding: 20px !important;
  }
  
  /* Print header */
  .print-header { 
    display: block !important; 
    text-align: center; 
    margin-bottom: 30px;
    border-bottom: 2px solid #000;
    padding-bottom: 15px;
  }
  
  .print-header h2 {
    margin: 10px 0 5px 0;
    font-size: 24px;
    color: #000;
  }
  
  .print-header p {
    margin: 5px 0;
    color: #666;
    font-size: 14px;
  }
  
  /* Ensure chart is visible and properly sized */
  .chart-container {
    width: 100% !important;
    height: 400px !important;
    background: #fff !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  
  /* Summary cards in print */
  .summary-cards {
    display: grid !important;
    grid-template-columns: repeat(3, 1fr) !important;
    gap: 15px !important;
    margin-top: 25px !important;
    page-break-inside: avoid;
  }
  
  .summary-card {
    background: #f8f9fa !important;
    border: 1px solid #ddd !important;
    padding: 15px !important;
    border-radius: 6px !important;
    text-align: center !important;
    color: #000 !important;
  }
  
  .summary-card h3 {
    color: #666 !important;
    margin: 0 0 10px 0 !important;
    font-size: 14px !important;
  }
  
  .summary-card div {
    color: #000 !important;
    font-size: 20px !important;
    font-weight: bold !important;
  }
  
  /* Page breaks */
  .page-break {
    page-break-before: always;
  }
  
  /* Ensure no background colors interfere */
  * {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
}

.print-header { 
  display: none; 
}

.loading {color: var(--muted); text-align: center; padding: 20px;}
.error {color: #dc2626; text-align: center; padding: 20px;}

/* Summary cards styling */
.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin-top: 20px;
}

.summary-card {
  background: var(--card);
  padding: 20px;
  border-radius: 10px;
  text-align: center;
  border: 1px solid var(--border);
}

.summary-card h3 {
  margin: 0 0 12px 0;
  color: var(--muted);
  font-size: 14px;
  font-weight: 500;
}

.summary-card div {
  font-size: 28px;
  font-weight: bold;
  color: #fff;
}

/* Toast Notification */
.toast {
  position: fixed;
  top: 20px;
  right: 20px;
  background: var(--green);
  color: white;
  padding: 12px 20px;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.3);
  z-index: 1000;
  transform: translateX(400px);
  transition: transform 0.3s ease;
  font-size: 14px;
  max-width: 300px;
}

.toast.show {
  transform: translateX(0);
}

.toast.error {
  background: var(--red);
}

/* Filter active states */
.filter-active {
  background: var(--blue) !important;
  color: white !important;
  border-color: var(--blue-600) !important;
}

.chipRow.active {
  background: #1e293b;
  border: 1px solid var(--blue);
}

/* Debug styles (remove in production) */
.debug-info {
  background: #1f2937;
  padding: 10px;
  border-radius: 8px;
  margin-bottom: 20px;
  font-size: 12px;
  color: #9ca3af;
  display: none; /* Set to block for debugging */
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
<div class="app">
  <header>
    <div class="brand">
      <img src="img/jhay-gadget-logo.png.jpg" alt="JHAY Gadget">
      <div><div class="title">JHAY GADGET</div></div>
    </div>
  </header>

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
        <a href="sales-graphs-admin.php" class="chipRow active">
          <div class="chipLabel"><span class="dot"></span><span>Sales Analytics</span></div>
        </a>
        <a href="feedback-admin.php" class="chipRow">
          <div class="chipLabel"><span class="dot"></span><span>Feedback</span></div>
        </a>
      </div>
      <div class="navGroup">
        <div class="navTitle">Stock Status</div>
        <div class="chipRow" id="inStockFilter">
          <div class="chipLabel"><span>In Stock</span></div><span class="badge" id="inStockBadge"><?php echo $inStockCount; ?></span>
        </div>
        <div class="chipRow" id="outStockFilter">
          <div class="chipLabel"><span>Out of Stock</span></div><span class="badge" id="outStockBadge"><?php echo $outStockCount; ?></span>
        </div>
        <div class="chipRow" id="lowStockFilter">
          <div class="chipLabel"><span>Low Stock</span></div><span class="badge" id="lowStockBadge"><?php echo $lowStockCount; ?></span>
        </div>
      </div>
      <div class="filters">
        <div class="filter">
          <label>Category</label>
          <select id="categoryFilter">
            <option value="all">All Categories</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?php echo htmlspecialchars($category); ?>">
                <?php echo htmlspecialchars($category); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter">
          <label>Stock Status</label>
          <div class="yn">
            <button id="inStockBtn">In Stock</button>
            <button id="outStockBtn">Out of Stock</button>
            <button id="lowStockBtn">Low Stock</button>
            <button id="allStockBtn" class="active">Show All</button>
          </div>
        </div>
      </div>
    </aside>

    <main>
      <!-- Debug info (can be enabled for troubleshooting) -->
      <div class="debug-info" id="debugInfo"></div>

      <div class="print-header">
        <img src="img/jhay-gadget-logo.png.jpg" alt="JHAY Gadget" style="width:120px;height:auto;margin-bottom:10px;">
        <h2>JHAY GADGET - SALES REPORT</h2>
        <p>Sales Analytics Dashboard</p>
        <p>Generated on: <span id="printDate"></span></p>
        <hr>
      </div>

      <div class="toolbar print-hide">
        <div class="search">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/>
            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input id="searchInput" placeholder="Search by product name or category..." />
        </div>
        <button class="btn primary" id="refreshBtn">Refresh Data</button>
        <button class="btn print" id="printBtn">Print Report</button>
      </div>

      <div class="filters print-hide">
        <div>
          <label for="timeframe">View By:</label>
          <select id="timeframe">
            <option value="day">Daily</option>
            <option value="week">Weekly</option>
            <option value="month">Monthly</option>
          </select>
        </div>
        <div>
          <label for="periodRange">Date Range:</label>
          <select id="periodRange">
            <option value="7">Last 7 Days</option>
            <option value="30" selected>Last 30 Days</option>
            <option value="90">Last 90 Days</option>
            <option value="365">Last Year</option>
          </select>
        </div>
      </div>

      <div class="card">
        <div class="chart-container">
          <canvas id="salesChart"></canvas>
          <div id="chartFallback" class="chart-fallback" style="display: none;">
            <p>Chart cannot be displayed. Chart.js library is not available.</p>
            <button onclick="location.reload()">Retry Loading Chart</button>
          </div>
        </div>
      </div>
      
      <!-- Summary Cards -->
      <div class="summary-cards">
        <div class="summary-card">
          <h3>Total Sales</h3>
          <div id="totalSales">0</div>
        </div>
        <div class="summary-card">
          <h3>Total Revenue</h3>
          <div id="totalRevenue">₱0</div>
        </div>
        <div class="summary-card">
          <h3>Total Profit</h3>
          <div id="totalProfit">₱0</div>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Toast Notification -->
<div id="toast" class="toast"></div>

<script>
// Main application code
document.addEventListener('DOMContentLoaded', function() {
  const timeframe = document.getElementById('timeframe');
  const periodRange = document.getElementById('periodRange');
  const printBtn = document.getElementById('printBtn');
  const refreshBtn = document.getElementById('refreshBtn');
  const searchInput = document.getElementById('searchInput');
  const printDate = document.getElementById('printDate');
  const toast = document.getElementById('toast');
  const canvas = document.getElementById('salesChart');
  const chartFallback = document.getElementById('chartFallback');
  const debugInfo = document.getElementById('debugInfo');

  // Stock filter elements
  const categoryFilter = document.getElementById('categoryFilter');
  const inStockBtn = document.getElementById('inStockBtn');
  const outStockBtn = document.getElementById('outStockBtn');
  const lowStockBtn = document.getElementById('lowStockBtn');
  const allStockBtn = document.getElementById('allStockBtn');
  const inStockFilter = document.getElementById('inStockFilter');
  const outStockFilter = document.getElementById('outStockFilter');
  const lowStockFilter = document.getElementById('lowStockFilter');

  // Set current date for print header
  printDate.textContent = new Date().toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });

  // Initialize Chart.js
  let salesChart = null;
  
  function initChart() {
    // Check if Chart.js is available
    if (typeof Chart === 'undefined') {
      console.error('Chart.js is not loaded. Cannot initialize chart.');
      chartFallback.style.display = 'flex';
      canvas.style.display = 'none';
      return;
    }

    // Hide fallback and show canvas
    chartFallback.style.display = 'none';
    canvas.style.display = 'block';

    const ctx = canvas.getContext('2d');
    
    // Create initial chart with loading state
    salesChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['Loading...'],
        datasets: [
          {
            label: 'Quantity Sold',
            data: [0],
            backgroundColor: '#e53935',
            borderColor: '#c62828',
            borderWidth: 1
          },
          {
            label: 'Revenue (₱)',
            data: [0],
            backgroundColor: '#43a047',
            borderColor: '#2e7d32',
            borderWidth: 1
          },
          {
            label: 'Profit (₱)',
            data: [0],
            backgroundColor: '#1e88e5',
            borderColor: '#1565c0',
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
            text: 'Sales Analytics',
            color: '#f9fafb',
            font: {
              size: 16
            }
          },
          legend: {
            labels: {
              color: '#f9fafb'
            }
          },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.85)',
            titleColor: '#fff',
            bodyColor: '#fff',
            borderColor: '#374151',
            borderWidth: 1,
            callbacks: {
              label: function(context) {
                let label = context.dataset.label || '';
                if (label) {
                  label += ': ';
                }
                if (label.includes('₱')) {
                  label += '₱' + context.parsed.y.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                  });
                } else {
                  label += context.parsed.y.toLocaleString();
                }
                return label;
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              color: '#374151'
            },
            ticks: {
              color: '#9ca3af',
              callback: function(value) {
                if (value >= 1000000) {
                  return (value / 1000000).toFixed(1) + 'M';
                } else if (value >= 1000) {
                  return (value / 1000).toFixed(1) + 'K';
                }
                return value;
              }
            }
          },
          x: {
            grid: {
              color: '#374151'
            },
            ticks: {
              color: '#9ca3af',
              maxRotation: 45
            }
          }
        }
      }
    });
  }

  // Filter state
  let currentFilters = {
    stock: 'all',
    category: 'all',
    search: '',
    timeframe: 'day',
    periodRange: '30'
  };

  // Toast notification function
  function showToast(message, isError = false) {
    toast.textContent = message;
    toast.className = 'toast';
    if (isError) {
      toast.classList.add('error');
    }
    toast.classList.add('show');
    
    setTimeout(() => {
      toast.classList.remove('show');
    }, 3000);
  }

  // Build filter summary for toast messages
  function getFilterSummary() {
    const summaries = [];
    
    if (currentFilters.stock !== 'all') {
      const stockText = {
        'in': 'In Stock',
        'out': 'Out of Stock', 
        'low': 'Low Stock'
      }[currentFilters.stock];
      summaries.push(stockText);
    }
    
    if (currentFilters.category !== 'all') {
      summaries.push(currentFilters.category);
    }
    
    if (currentFilters.search) {
      summaries.push(`Search: "${currentFilters.search}"`);
    }
    
    return summaries.length > 0 ? `Filters: ${summaries.join(', ')}` : 'All products';
  }

  function getTimeframeText(timeframe) {
    const texts = {
      'day': 'Daily',
      'week': 'Weekly', 
      'month': 'Monthly'
    };
    return texts[timeframe] || timeframe;
  }

  function getPeriodRangeText(period) {
    const texts = {
      '7': 'Last 7 Days',
      '30': 'Last 30 Days',
      '90': 'Last 90 Days',
      '365': 'Last Year'
    };
    return texts[period] || period;
  }

  // Search functionality
  function initSearch() {
    let searchTimeout;
    
    searchInput.addEventListener('input', (e) => {
      clearTimeout(searchTimeout);
      currentFilters.search = e.target.value.trim();
      
      searchTimeout = setTimeout(() => {
        fetchSales();
      }, 500);
    });
    
    // Clear search when clicking refresh
    refreshBtn.addEventListener('click', () => {
      searchInput.value = '';
      currentFilters.search = '';
      fetchSales();
    });
  }

  // Initialize stock filter buttons
  function initStockFilters() {
    // Set active state for stock filter buttons
    function setActiveButton(activeBtn, stockType) {
      [inStockBtn, outStockBtn, lowStockBtn, allStockBtn].forEach(btn => {
        btn.classList.remove('active');
      });
      [inStockFilter, outStockFilter, lowStockFilter].forEach(chip => {
        chip.classList.remove('active');
      });
      
      if (activeBtn) {
        activeBtn.classList.add('active');
      }
      
      // Also highlight the corresponding chip in sidebar
      if (stockType === 'in') {
        inStockFilter.classList.add('active');
      } else if (stockType === 'out') {
        outStockFilter.classList.add('active');
      } else if (stockType === 'low') {
        lowStockFilter.classList.add('active');
      }
    }
    
    // Button click handlers
    inStockBtn.addEventListener('click', () => {
      currentFilters.stock = 'in';
      setActiveButton(inStockBtn, 'in');
      fetchSales();
    });
    
    outStockBtn.addEventListener('click', () => {
      currentFilters.stock = 'out';
      setActiveButton(outStockBtn, 'out');
      fetchSales();
    });
    
    lowStockBtn.addEventListener('click', () => {
      currentFilters.stock = 'low';
      setActiveButton(lowStockBtn, 'low');
      fetchSales();
    });
    
    allStockBtn.addEventListener('click', () => {
      currentFilters.stock = 'all';
      setActiveButton(allStockBtn, 'all');
      fetchSales();
    });
    
    // Chip row click handlers
    inStockFilter.addEventListener('click', () => {
      currentFilters.stock = 'in';
      setActiveButton(inStockBtn, 'in');
      fetchSales();
    });
    
    outStockFilter.addEventListener('click', () => {
      currentFilters.stock = 'out';
      setActiveButton(outStockBtn, 'out');
      fetchSales();
    });
    
    lowStockFilter.addEventListener('click', () => {
      currentFilters.stock = 'low';
      setActiveButton(lowStockBtn, 'low');
      fetchSales();
    });
    
    // Category filter change handler
    categoryFilter.addEventListener('change', () => {
      currentFilters.category = categoryFilter.value;
      fetchSales();
    });
  }

  // Initialize timeframe and period range filters
  function initTimeframeFilters() {
    timeframe.addEventListener('change', (e) => {
      currentFilters.timeframe = e.target.value;
      fetchSales();
    });
    
    periodRange.addEventListener('change', (e) => {
      currentFilters.periodRange = e.target.value;
      fetchSales();
    });
  }

  async function fetchSales() {
    try {
      console.log('Fetching sales data with filters:', currentFilters);
      
      // Show loading state
      if (salesChart) {
        salesChart.data.labels = ['Loading...'];
        salesChart.data.datasets[0].data = [0];
        salesChart.data.datasets[1].data = [0];
        salesChart.data.datasets[2].data = [0];
        salesChart.update();
      }
      
      // Build query parameters for filters
      const params = new URLSearchParams();
      if (currentFilters.stock !== 'all') {
        params.append('stock', currentFilters.stock);
      }
      if (currentFilters.category !== 'all') {
        params.append('category', currentFilters.category);
      }
      if (currentFilters.search) {
        params.append('search', currentFilters.search);
      }
      params.append('timeframe', currentFilters.timeframe);
      params.append('periodRange', currentFilters.periodRange);
      
      const url = 'get-sales-data.php?' + params.toString();
      console.log('Fetching from:', url);
      
      const res = await fetch(url);
      
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      
      const salesData = await res.json();
      
      // Check if response is an error object
      if (salesData.error) {
        throw new Error(salesData.error);
      }
      
      console.log('Sales data received:', salesData);

      if (!salesData || salesData.length === 0) {
        // Show appropriate message based on filters
        if (salesChart) {
          salesChart.data.labels = ['No Data Available'];
          salesChart.data.datasets[0].data = [0];
          salesChart.data.datasets[1].data = [0];
          salesChart.data.datasets[2].data = [0];
          salesChart.options.plugins.title.text = 'Sales Analytics - No Data';
          salesChart.update();
        }
        updateSummaryCards(0, 0, 0);
        
        const timeframeText = getTimeframeText(currentFilters.timeframe);
        const periodText = getPeriodRangeText(currentFilters.periodRange);
        showToast(`No sales data found for ${timeframeText} view in ${periodText}`, true);
      } else {
        // Process data for chart
        processChartData(salesData);
        
        const timeframeText = getTimeframeText(currentFilters.timeframe);
        const periodText = getPeriodRangeText(currentFilters.periodRange);
        showToast(`Showing ${salesData.length} ${timeframeText} periods in ${periodText}`);
      }
      
    } catch (err) {
      console.error("Error loading sales:", err);
      // Show error state
      if (salesChart) {
        salesChart.data.labels = ['Error Loading Data'];
        salesChart.data.datasets[0].data = [0];
        salesChart.data.datasets[1].data = [0];
        salesChart.data.datasets[2].data = [0];
        salesChart.update();
      }
      
      updateSummaryCards(0, 0, 0);
      showToast('Error loading sales data: ' + err.message, true);
    }
  }

  function processChartData(salesData) {
    const labels = [];
    const quantities = [];
    const revenues = [];
    const profits = [];
    
    let totalSales = 0;
    let totalRevenue = 0;
    let totalProfit = 0;
    
    // Sort data by date to ensure chronological order
    salesData.sort((a, b) => new Date(a.date) - new Date(b.date));
    
    // Process each data point
    salesData.forEach(row => {
      let label = '';
      const date = new Date(row.date);
      
      switch(currentFilters.timeframe) {
        case 'day':
          label = date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric' 
          });
          break;
        case 'week':
          // Format as "Week of [start date] - [end date]"
          const weekStart = new Date(date);
          const weekEnd = new Date(weekStart);
          weekEnd.setDate(weekStart.getDate() + 6);
          label = `${weekStart.getDate()}-${weekEnd.getDate()} ${weekStart.toLocaleDateString('en-US', { month: 'short' })}`;
          break;
        case 'month':
          label = date.toLocaleDateString('en-US', { 
            year: 'numeric',
            month: 'short'
          });
          break;
        default:
          label = date.toLocaleDateString('en-US');
      }
      
      labels.push(label);
      quantities.push(parseInt(row.qty) || 0);
      revenues.push(parseFloat(row.revenue) || 0);
      profits.push(parseFloat(row.profit) || 0);
      
      totalSales += parseInt(row.qty) || 0;
      totalRevenue += parseFloat(row.revenue) || 0;
      totalProfit += parseFloat(row.profit) || 0;
    });
    
    // Update chart data
    if (salesChart) {
      salesChart.data.labels = labels;
      salesChart.data.datasets[0].data = quantities;
      salesChart.data.datasets[1].data = revenues;
      salesChart.data.datasets[2].data = profits;
      
      // Update chart title based on filters
      updateChartTitle();
      
      salesChart.update();
    }
    
    // Update summary cards
    updateSummaryCards(totalSales, totalRevenue, totalProfit);
  }

  function updateChartTitle() {
    let title = 'Sales Analytics';
    
    const timeframeText = getTimeframeText(currentFilters.timeframe);
    const periodText = getPeriodRangeText(currentFilters.periodRange);
    
    title += ` - ${timeframeText} View (${periodText})`;
    
    if (currentFilters.search) {
      title += ` - Search: "${currentFilters.search}"`;
    }
    
    if (currentFilters.stock !== 'all') {
      const stockText = {
        'in': 'In Stock',
        'out': 'Out of Stock', 
        'low': 'Low Stock'
      }[currentFilters.stock];
      title += ` - ${stockText}`;
    }
    
    if (currentFilters.category !== 'all') {
      title += ` - ${currentFilters.category}`;
    }
    
    if (salesChart) {
      salesChart.options.plugins.title.text = title;
    }
  }

  function updateSummaryCards(sales, revenue, profit) {
    document.getElementById('totalSales').textContent = sales.toLocaleString();
    document.getElementById('totalRevenue').textContent = '₱' + revenue.toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
    document.getElementById('totalProfit').textContent = '₱' + profit.toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  // Enhanced print function
  function prepareForPrint() {
    // Update print date
    printDate.textContent = new Date().toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
    
    // Ensure chart is ready for printing
    if (salesChart) {
      salesChart.resize();
    }
  }

  // Event listeners
  timeframe.addEventListener('change', fetchSales);
  periodRange.addEventListener('change', fetchSales);
  printBtn.addEventListener('click', e => { 
    e.preventDefault();
    prepareForPrint();
    setTimeout(() => {
      window.print();
    }, 500);
  });
  refreshBtn.addEventListener('click', fetchSales);

  // 🔔 Custom event fired after process-sales.php succeeds
  window.addEventListener("salesUpdated", () => {
    console.log("🔄 Sales updated event received. Refreshing graphs...");
    fetchSales();
  });

  // Cross-tab communication
  window.addEventListener("storage", (e) => {
    if (e.key === 'salesUpdateTime') {
      console.log("📊 Sales update detected from another tab");
      fetchSales();
    }
  });

  // Auto-refresh every 30 seconds
  setInterval(() => {
    fetchSales();
  }, 30000);

  // Handle window resize
  window.addEventListener('resize', () => {
    if (salesChart) {
      setTimeout(() => {
        salesChart.resize();
      }, 100);
    }
  });

  // Initialize
  initChart();
  initSearch();
  initStockFilters();
  initTimeframeFilters();
  fetchSales();
});
</script>
</body>
</html>
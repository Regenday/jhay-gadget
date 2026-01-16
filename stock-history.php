<?php
include 'db.php';

// Fetch stock history
$history = [];
$res = $db->query("
    SELECT 
        sh.*,
        p.name as product_name,
        p.photo as product_photo
    FROM stock_history sh
    LEFT JOIN products p ON sh.product_id = p.id
    ORDER BY sh.created_at DESC
    LIMIT 100
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $history[] = $row;
    }
}

// Get statistics
$stats = [
    'total_added' => 0,
    'total_removed' => 0,
    'today_added' => 0,
    'today_removed' => 0
];

$statsRes = $db->query("
    SELECT 
        action_type,
        COUNT(*) as count,
        SUM(quantity) as total_quantity,
        DATE(created_at) as date
    FROM stock_history 
    GROUP BY action_type, DATE(created_at)
    ORDER BY date DESC
");

while ($row = $statsRes->fetch_assoc()) {
    if ($row['action_type'] === 'add') {
        $stats['total_added'] += $row['total_quantity'];
        if ($row['date'] === date('Y-m-d')) {
            $stats['today_added'] += $row['total_quantity'];
        }
    } else if ($row['action_type'] === 'remove') {
        $stats['total_removed'] += $row['total_quantity'];
        if ($row['date'] === date('Y-m-d')) {
            $stats['today_removed'] += $row['total_quantity'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>JHAY Gadget · Stock History</title>
<style>
:root {
  --blue:#1e88e5; --blue-600:#1976d2;
  --ink:#0f172a; --ink-2:#111827;
  --bg:#000; --card:#111; --border:#333;
  --muted:#9ca3af; --accent:#22c55e;
  --stock-low:#dc2626; --stock-ok:#22c55e; --stock-warning:#f59e0b;
  --sold:#8b5cf6; --defective:#f97316;
}
* {box-sizing:border-box;}
html,body{height:100%;margin:0;}
body {
  font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial;
  color:#f9fafb; background:var(--bg);
}
.app{display:grid; grid-template-rows:auto 1fr; height:100%;}
header{
  background:var(--black); color:#fff; display:flex; align-items:center; justify-content:space-between;
  padding:10px 16px; box-shadow:0 2px 0 rgba(0,0,0,.5);
}
.header-left{display:flex; align-items:center; gap:16px;}
.brand{display:flex;align-items:center;gap:12px;min-width:220px;}
.brand img {width:48px;height:48px;border-radius:8px;border:3px solid var(--black);object-fit:contain;}
.brand .title{font-weight:700;}
.logout-btn {
  background: #dc2626; color: white; border: none; padding: 8px 16px;
  border-radius: 6px; cursor: pointer; font-size: 14px; transition: background 0.2s;
}
.logout-btn:hover {background: #b91c1c;}
.stock-history-btn {
  background: var(--blue); color: white; border: none; padding: 8px 16px;
  border-radius: 6px; cursor: pointer; font-size: 14px; transition: background 0.2s;
  margin-right: 10px;
}
.stock-history-btn:hover {background: var(--blue-600);}
.shell{display:grid; grid-template-columns:260px 1fr; height:calc(100vh - 52px);}
aside{
  background:var(--ink-2); color:#d1d5db; padding:14px 12px; overflow:auto;
  border-right:1px solid #222;
}
.navGroup{Margin:10px 0 18px;}
.navTitle{font-size:12px;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);padding:6px 10px;}
.chipRow{display:flex;align-items:center;justify-content:space-between;padding:10px;border-radius:10px;text-decoration:none;color:inherit;}
.chipLabel{display:flex;align-items:center;gap:10px;}
.dot{width:8px;height:8px;border-radius:999px;background:#6b7280;}
.badge{background:#374151;color:#e5e7eb;font-size:12px;padding:2px 8px;border-radius:999px;} 
main{padding:18px;overflow:auto;}
.toolbar{display:flex;gap:10px;align-items:center;margin-bottom:12px;}
.search{
  flex:1; display:flex; align-items:center; gap:8px; background:var(--card);
  border:1px solid var(--border); border-radius:12px; padding:8px 10px;
}
.search input{border:0; outline:0; width:100%; font-size:14px; background:transparent; color:#fff;}
.btn{background:#111;border:1px solid var(--border);border-radius:12px;padding:8px 14px;cursor:pointer;color:#fff;}
.btn.primary{background:var(--blue);color:#fff;border-color:var(--blue-600);}
.btn.success{background:var(--accent);color:#fff;border-color:#16a34a;}
.btn.danger{background:#dc2626;color:#fff;border-color:#b91c1c;}
.btn:active{transform:translateY(1px);}
.card{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:auto;}
table{width:100%;border-collapse:separate;border-spacing:0;color:#f1f5f9;}
thead th{font-size:12px;text-transform:uppercase;color:#9ca3af;background:#1e293b;text-align:left;padding:12px;}
tbody td{padding:14px 12px;border-top:1px solid var(--border);}
tbody tr:hover{background:#1e293b;}
.status{display:inline-flex;align-items:center;gap:8px;padding:4px 10px;border-radius:999px;font-size:12px;}
.status-add {background: #065f46; color: white;}
.status-remove {background: #dc2626; color: white;}
.status-update {background: #f59e0b; color: white;}
.storeLogo{width:40px;height:40px;border-radius:8px;background:#2563eb;display:grid;place-items:center;color:#fff;font-weight:700;object-fit:cover;}
.stats-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;}
.stat-card {background: var(--card); padding: 20px; border-radius: 10px; border: 1px solid var(--border);}
.stat-value {font-size: 24px; font-weight: bold; margin-bottom: 5px;}
.stat-label {font-size: 14px; color: var(--muted);}
.stat-positive {color: #22c55e;}
.stat-negative {color: #dc2626;}
.stat-neutral {color: #f59e0b;}
.history-item {display: flex; align-items: center; gap: 12px; padding: 12px; border-bottom: 1px solid var(--border);}
.history-item:last-child {border-bottom: none;}
.history-icon {width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px;}
.history-icon.add {background: #065f46; color: white;}
.history-icon.remove {background: #dc2626; color: white;}
.history-icon.update {background: #f59e0b; color: white;}
.history-content {flex: 1;}
.history-product {font-weight: 600; margin-bottom: 4px;}
.history-details {font-size: 14px; color: var(--muted); margin-bottom: 4px;}
.history-time {font-size: 12px; color: #6b7280;}
.history-quantity {font-weight: 600; padding: 4px 8px; border-radius: 6px; font-size: 14px;}
.quantity-positive {background: #065f46; color: white;}
.quantity-negative {background: #dc2626; color: white;}
@media (max-width: 980px){
  .shell{grid-template-columns:80px 1fr;}
  aside{padding:12px 8px;}
  .navTitle{display:none;}
  .chipLabel span{display:none;}
}
@media (max-width: 720px){
  .toolbar{flex-wrap:wrap;}
  thead{display:none;}
  tbody td{display:block;border-top:0;}
  tbody tr{display:block;border-top:1px solid var(--border);padding:12px;}
  tbody td[data-th]::before{content:attr(data-th) ": ";font-weight:600;color:#9ca3af;margin-right:6px;}
  .stats-grid {grid-template-columns: 1fr;}
}
</style>
</head>
<body>
<header>
<div class="header-left">
  <div class="brand">
    <img id="brandLogo" alt="JHAY Gadget" src="img/jhay-gadget-logo.png.jpg" />
    <div><div class="title">JHAY GADGET</div></div>
  </div>
</div>
<div>
  <button class="stock-history-btn" onclick="location.href='fronttae.php'">Back to Products</button>
  <button class="logout-btn" id="logoutBtn">Logout</button>
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
    <a href="stock-history.php" class="chipRow" style="background: #1e293b;">
      <div class="chipLabel"><span class="dot"></span><span>Stock History</span></div>
    </a>
    <a href="analytics.php" class="chipRow">
      <div class="chipLabel"><span class="dot"></span><span>Data Analytics</span></div>
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
</aside>
<main>
<div class="toolbar">
  <div class="search">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input id="searchInput" placeholder="Search stock history…" />
  </div>
  <button class="btn primary" onclick="exportHistory()">Export History</button>
  <button class="btn danger" onclick="clearHistory()">Clear History</button>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-value stat-positive">+<?php echo $stats['total_added']; ?></div>
    <div class="stat-label">Total Stock Added</div>
  </div>
  <div class="stat-card">
    <div class="stat-value stat-negative">-<?php echo $stats['total_removed']; ?></div>
    <div class="stat-label">Total Stock Removed</div>
  </div>
  <div class="stat-card">
    <div class="stat-value stat-positive">+<?php echo $stats['today_added']; ?></div>
    <div class="stat-label">Today's Added</div>
  </div>
  <div class="stat-card">
    <div class="stat-value stat-negative">-<?php echo $stats['today_removed']; ?></div>
    <div class="stat-label">Today's Removed</div>
  </div>
</div>

<div class="card">
  <table>
    <thead>
      <tr>
        <th>Product</th>
        <th>Action</th>
        <th>Quantity</th>
        <th>Color</th>
        <th>Details</th>
        <th>Timestamp</th>
      </tr>
    </thead>
    <tbody id="historyTable">
      <?php foreach ($history as $item): ?>
      <tr>
        <td data-th="Product">
          <div style="display:flex;align-items:center;gap:10px;">
            <?php if (!empty($item['product_photo'])): ?>
              <img src="<?php echo htmlspecialchars($item['product_photo']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" style="width:32px;height:32px;border-radius:6px;object-fit:cover;">
            <?php else: ?>
              <div class="storeLogo" style="width:32px;height:32px;font-size:12px;"><?php echo strtoupper(substr($item['product_name'], 0, 1)); ?></div>
            <?php endif; ?>
            <span><?php echo htmlspecialchars($item['product_name']); ?></span>
          </div>
        </td>
        <td data-th="Action">
          <span class="status status-<?php echo $item['action_type']; ?>">
            <?php echo ucfirst($item['action_type']); ?>
          </span>
        </td>
        <td data-th="Quantity">
          <span class="history-quantity <?php echo $item['action_type'] === 'add' ? 'quantity-positive' : 'quantity-negative'; ?>">
            <?php echo $item['action_type'] === 'add' ? '+' : '-'; ?><?php echo $item['quantity']; ?>
          </span>
        </td>
        <td data-th="Color"><?php echo htmlspecialchars($item['color'] ?? 'N/A'); ?></td>
        <td data-th="Details"><?php echo htmlspecialchars($item['details'] ?? 'Stock update'); ?></td>
        <td data-th="Timestamp"><?php echo date('M j, Y g:i A', strtotime($item['created_at'])); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</main>
</div>
</div>

<script>
function exportHistory() {
    showToast('Export feature coming soon!', '#f59e0b');
}

function clearHistory() {
    if (confirm('Are you sure you want to clear all stock history? This action cannot be undone.')) {
        showToast('Clear history feature coming soon!', '#f59e0b');
    }
}

function showToast(msg, color = 'var(--blue)') {
    // Create toast element if it doesn't exist
    let toast = document.getElementById('toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast';
        toast.style.cssText = 'position: fixed; bottom: 20px; right: 20px; background: var(--blue); color: #fff; padding: 10px 16px; border-radius: 8px; font-size: 14px; display: none; z-index: 2000;';
        document.body.appendChild(toast);
    }
    
    toast.textContent = msg;
    toast.style.background = color;
    toast.style.display = 'block';
    setTimeout(() => toast.style.display = 'none', 3000);
}

// Search functionality
document.getElementById('searchInput').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#historyTable tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});
</script>
</body>
</html>
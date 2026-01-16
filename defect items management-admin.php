<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Handle mark item as defective
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_defective'])) {
    $productId = $_POST['product_id'];
    $serialNumber = $_POST['serial_number'];
    $defectNotes = $_POST['defect_notes'];
    $reportedBy = $_POST['reported_by'] ?? '';
    $defectType = $_POST['defect_type'] ?? 'Other';
    $color = $_POST['color'] ?? '';
    $userId = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'admin';
    
    // First check if item exists and is available
    $checkStmt = $db->prepare("SELECT id, status FROM product_stock WHERE product_id = ? AND serial_number = ?");
    $checkStmt->bind_param("is", $productId, $serialNumber);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Item not found.'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $item = $checkResult->fetch_assoc();
    if ($item['status'] !== 'Available') {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Item is not available (status: ' . $item['status'] . ').'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    // Update product_stock table
    $updateStmt = $db->prepare("UPDATE product_stock SET status = 'Defective', defect_notes = ? WHERE product_id = ? AND serial_number = ?");
    $updateStmt->bind_param("sis", $defectNotes, $productId, $serialNumber);
    
    if ($updateStmt->execute()) {
        $stockId = $item['id'];
        
        // Log to stock_history
        $historyStmt = $db->prepare("INSERT INTO stock_history (product_id, stock_id, change_type, previous_status, new_status, changed_by, change_date, notes) 
                                     VALUES (?, ?, 'defective', 'Available', 'Defective', ?, NOW(), ?)");
        $historyNotes = "Marked as defective. Type: $defectType. Reported by: $reportedBy. Notes: $defectNotes";
        $historyStmt->bind_param("iiis", $productId, $stockId, $userId, $historyNotes);
        $historyStmt->execute();
        
        // Log to activity_log
        $activityStmt = $db->prepare("INSERT INTO activity_log (user_id, username, action, details, ip_address, user_agent) 
                                      VALUES (?, ?, 'Stock Marked Defective', ?, ?, ?)");
        $activityDetails = "Marked item as defective - Product ID: $productId, Serial: $serialNumber, Notes: $defectNotes";
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $activityStmt->bind_param("issss", $userId, $username, $activityDetails, $ipAddress, $userAgent);
        $activityStmt->execute();
        
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Item marked as defective successfully.'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Error marking item as defective.'];
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle mark item as repaired/available
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_repaired'])) {
    $productId = $_POST['product_id'];
    $serialNumber = $_POST['serial_number'];
    $repairNotes = $_POST['repair_notes'] ?? '';
    $repairCost = $_POST['repair_cost'] ?? 0;
    $userId = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'admin';
    
    // First check if item exists and is defective
    $checkStmt = $db->prepare("SELECT id, status FROM product_stock WHERE product_id = ? AND serial_number = ?");
    $checkStmt->bind_param("is", $productId, $serialNumber);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Item not found.'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $item = $checkResult->fetch_assoc();
    if ($item['status'] !== 'Defective') {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Item is not marked as defective (status: ' . $item['status'] . ').'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    // Update product_stock table
    $updateStmt = $db->prepare("UPDATE product_stock SET status = 'Available', defect_notes = NULL WHERE product_id = ? AND serial_number = ?");
    $updateStmt->bind_param("is", $productId, $serialNumber);
    
    if ($updateStmt->execute()) {
        $stockId = $item['id'];
        
        // Log to stock_history
        $historyStmt = $db->prepare("INSERT INTO stock_history (product_id, stock_id, change_type, previous_status, new_status, changed_by, change_date, notes) 
                                     VALUES (?, ?, 'repaired', 'Defective', 'Available', ?, NOW(), ?)");
        $historyNotes = "Marked as repaired. Repair cost: ₱$repairCost. Notes: $repairNotes";
        $historyStmt->bind_param("iiis", $productId, $stockId, $userId, $historyNotes);
        $historyStmt->execute();
        
        // Log to activity_log
        $activityStmt = $db->prepare("INSERT INTO activity_log (user_id, username, action, details, ip_address, user_agent) 
                                      VALUES (?, ?, 'Stock Repaired', ?, ?, ?)");
        $activityDetails = "Marked item as repaired - Product ID: $productId, Serial: $serialNumber, Cost: ₱$repairCost";
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $activityStmt->bind_param("issss", $userId, $username, $activityDetails, $ipAddress, $userAgent);
        $activityStmt->execute();
        
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Item marked as repaired successfully.'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Error marking item as repaired.'];
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch defective items with search
$searchQuery = $_GET['search'] ?? '';

// Build query for defective items from product_stock
$defectQuery = "SELECT 
    ps.*,
    p.name as product_name,
    p.category as product_category,
    p.price as product_price,
    sh.change_date as defect_date,
    sh.notes as defect_history_notes,
    u.username as reported_by_user
    FROM product_stock ps
    JOIN products p ON ps.product_id = p.id
    LEFT JOIN stock_history sh ON ps.id = sh.stock_id AND sh.change_type = 'defective'
    LEFT JOIN users u ON sh.changed_by = u.id
    WHERE ps.status = 'Defective'";

$params = [];
$types = "";

// Apply search filter
if (!empty($searchQuery)) {
    $defectQuery .= " AND (
        ps.serial_number LIKE ? OR 
        p.name LIKE ? OR 
        ps.defect_notes LIKE ? OR
        p.category LIKE ?
    )";
    $searchTerm = "%$searchQuery%";
    $params = array_fill(0, 4, $searchTerm);
    $types = str_repeat("s", 4);
}

$defectQuery .= " ORDER BY ps.updated_at DESC";

$defectStmt = $db->prepare($defectQuery);
if (!empty($params)) {
    $defectStmt->bind_param($types, ...$params);
}
$defectStmt->execute();
$defectItems = $defectStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get counts for stats
$countQuery = "SELECT COUNT(*) as total_defects FROM product_stock WHERE status = 'Defective'";
$countResult = $db->query($countQuery);
$defectCounts = $countResult->fetch_assoc();

// Get recent defect history for timeline
$historyQuery = "SELECT 
    sh.*,
    p.name as product_name,
    ps.serial_number,
    u.username
    FROM stock_history sh
    JOIN product_stock ps ON sh.stock_id = ps.id
    JOIN products p ON sh.product_id = p.id
    LEFT JOIN users u ON sh.changed_by = u.id
    WHERE sh.change_type IN ('defective', 'repaired')
    ORDER BY sh.change_date DESC
    LIMIT 10";
$historyResult = $db->query($historyQuery);
$defectHistory = $historyResult->fetch_all(MYSQLI_ASSOC);

// Get toast message from session
$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>JHAY Gadget · Defect Items Management</title>
<style>
:root {
  --blue:#1e88e5; --blue-600:#1976d2;
  --green:#43a047; --red:#e53935;
  --orange:#fb8c00; --purple:#8e24aa;
  --yellow:#fdd835; --cyan:#00acc1;
  --ink:#0f172a; --ink-2:#111827;
  --bg:#000; --card:#111; --border:#333;
  --muted:#9ca3af; --accent:#22c55e;
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
.shell{display:grid; grid-template-columns:260px 1fr; height:calc(100vh - 52px);}
aside{
  background:var(--ink-2); color:#d1d5db; padding:14px 12px; overflow:auto;
  border-right:1px solid #222;
}
.navGroup{margin:10px 0 18px;}
.navTitle{font-size:12px;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);padding:6px 10px;}
.chipRow{display:flex;align-items:center;justify-content:space-between;padding:10px;border-radius:10px;text-decoration:none;color:inherit;transition:background 0.2s;cursor:pointer;}
.chipRow:hover{background:#1f2937;}
.chipRow.active{background:var(--blue);}
.chipLabel{display:flex;align-items:center;gap:10px;}
.dot{width:8px;height:8px;border-radius:999px;background:#6b7280;}
.badge{background:#374151;color:#e5e7eb;font-size:12px;padding:2px 8px;border-radius:999px;} 
.chipRow.active .badge{background:rgba(255,255,255,0.2);}
main{padding:18px;overflow:auto;}
.toolbar{display:flex;gap:10px;align-items:center;margin-bottom:20px;flex-wrap:wrap;}
.search{
  flex:1; display:flex; align-items:center; gap:8px; background:var(--card);
  border:1px solid var(--border); border-radius:12px; padding:8px 10px;
  min-width: 300px;
}
.search input{border:0; outline:0; width:100%; font-size:14px; background:transparent; color:#fff;}
.btn{background:#111;border:1px solid var(--border);border-radius:12px;padding:8px 14px;cursor:pointer;color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.btn.primary{background:var(--blue);color:#fff;border-color:var(--blue-600);}
.btn.success{background:var(--green);color:#fff;border-color:#2e7d32;}
.btn.danger{background:var(--red);color:#fff;border-color:#c62828;}
.btn.warning{background:var(--orange);color:#fff;border-color:#ef6c00;}
.btn.info{background:var(--cyan);color:#fff;border-color:#00838f;}
.btn:active{transform:translateY(1px);}

/* Stats Cards */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin-bottom: 20px;
}

.stat-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
  text-align: center;
}

.stat-card h3 {
  margin: 0 0 8px 0;
  color: var(--muted);
  font-size: 14px;
  font-weight: 500;
}

.stat-card div {
  font-size: 24px;
  font-weight: bold;
}

.stat-defective { color: var(--orange); }
.stat-total { color: var(--purple); }

/* Defect Items Table */
.defect-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  color: #f1f5f9;
  margin-top: 20px;
}

.defect-table thead th {
  font-size: 12px;
  text-transform: uppercase;
  color: #9ca3af;
  background: #1e293b;
  text-align: left;
  padding: 12px;
  border-bottom: 2px solid var(--border);
}

.defect-table tbody td {
  padding: 14px 12px;
  border-bottom: 1px solid var(--border);
}

.defect-table tbody tr:hover {
  background: #1e293b;
}

/* Status Badges */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-defective { background: rgba(251, 140, 0, 0.2); color: #fb8c00; border: 1px solid #fb8c00; }
.status-repaired { background: rgba(67, 160, 71, 0.2); color: #43a047; border: 1px solid #43a047; }

/* Action Buttons */
.action-buttons {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.action-btn {
  background: rgba(0,0,0,0.5);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 6px 10px;
  cursor: pointer;
  color: var(--muted);
  font-size: 12px;
  text-decoration: none;
  white-space: nowrap;
  transition: all 0.2s;
}

.action-btn:hover {
  background: var(--blue);
  color: white;
  border-color: var(--blue);
}

.action-btn.delete:hover {
  background: var(--red);
  border-color: var(--red);
}

.action-btn.edit:hover {
  background: var(--orange);
  border-color: var(--orange);
}

/* Modal Styles */
.modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.7);
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 10px;
}

.modal-content {
  background: var(--card);
  padding: 20px;
  border-radius: 10px;
  width: 100%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 0 10px #000;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--border);
  padding-bottom: 10px;
}

.modal-title {
  font-size: 1.5rem;
  font-weight: 600;
  color: #f9fafb;
  margin: 0;
}

.close-btn {
  background: none;
  border: none;
  color: var(--muted);
  font-size: 24px;
  cursor: pointer;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.close-btn:hover {
  background: rgba(255,255,255,0.1);
  border-radius: 4px;
}

/* Form Styles */
.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  color: #9ca3af;
  font-size: 14px;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 10px;
  border-radius: 6px;
  border: 1px solid #333;
  background: #1f2937;
  color: #fff;
  font-size: 14px;
}

.form-group textarea {
  min-height: 80px;
  resize: vertical;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

.form-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 20px;
}

/* Toast Notification */
#toast {
  position: fixed;
  bottom: 20px;
  right: 20px;
  background: var(--blue);
  color: #fff;
  padding: 12px 20px;
  border-radius: 8px;
  font-size: 14px;
  display: none;
  z-index: 2000;
  box-shadow: 0 4px 12px rgba(0,0,0,0.5);
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 60px 40px;
  color: var(--muted);
}

.empty-state svg {
  width: 64px;
  height: 64px;
  margin-bottom: 16px;
  opacity: 0.5;
}

/* Defect History Timeline */
.history-timeline {
  margin-top: 20px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
}

.history-item {
  display: flex;
  gap: 15px;
  padding: 15px 0;
  border-bottom: 1px solid var(--border);
}

.history-item:last-child {
  border-bottom: none;
}

.history-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.history-icon.defect { background: rgba(251, 140, 0, 0.2); color: #fb8c00; }
.history-icon.repair { background: rgba(67, 160, 71, 0.2); color: #43a047; }

.history-content {
  flex: 1;
}

.history-title {
  font-weight: 600;
  margin-bottom: 5px;
}

.history-meta {
  font-size: 12px;
  color: var(--muted);
  margin-bottom: 5px;
}

.history-notes {
  font-size: 13px;
  color: #d1d5db;
  background: rgba(0,0,0,0.3);
  padding: 8px;
  border-radius: 6px;
  margin-top: 5px;
}

/* Responsive Design */
@media (max-width: 980px){
  .shell{grid-template-columns:80px 1fr;}
  aside{padding:12px 8px;}
  .navTitle{display:none;}
  .chipLabel span{display:none;}
  .filters{display:none;}
}

@media (max-width: 768px){
  .toolbar{flex-direction:column;align-items:stretch;}
  .search{min-width:auto;}
  .form-row{grid-template-columns:1fr;}
  .defect-table{display:block;}
  .defect-table thead{display:none;}
  .defect-table tbody tr{display:block;margin-bottom:15px;border:1px solid var(--border);border-radius:8px;padding:10px;}
  .defect-table tbody td{display:block;border:none;padding:8px 0;}
  .defect-table tbody td::before{content:attr(data-label) ": ";font-weight:600;color:#9ca3af;display:inline-block;min-width:120px;}
  .action-buttons{justify-content:flex-start;}
}

@media (max-width: 480px){
  .stats-grid{grid-template-columns:1fr;}
  .action-buttons{flex-direction:column;align-items:stretch;}
  .action-btn{text-align:center;}
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
  <button class="logout-btn" onclick="location.href='logout.php'">Logout</button>
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
    <a href="user-management-admin.php" class="chipRow">
      <div class="chipLabel"><span class="dot"></span><span>User Management</span></div>
    </a>
    <a href="defect items management-admin.php" class="chipRow active">
      <div class="chipLabel"><span class="dot"></span><span>Defect Items</span></div>
      <span class="badge"><?php echo $defectCounts['total_defects'] ?? 0; ?></span>
    </a>
  </div>
</aside>
<main>
  <div class="toolbar">
    <div class="search">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"/>
        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <form method="GET" style="display: flex; width: 100%; gap: 10px; align-items: center;">
        <input 
          type="text" 
          name="search" 
          placeholder="Search defect items by serial, product, defect notes, etc..." 
          value="<?php echo htmlspecialchars($searchQuery); ?>"
          style="flex: 1; border: none; background: transparent; color: white; outline: none;"
        >
        <button type="submit" class="btn primary" style="padding: 8px 16px;">Search</button>
        <?php if (!empty($searchQuery)): ?>
          <a href="defect-items.php" class="btn" style="padding: 8px 16px;">Clear</a>
        <?php endif; ?>
      </form>
    </div>
    <button class="btn primary" onclick="openMarkDefectModal()">+ Mark Item as Defective</button>
    <button class="btn info" onclick="exportDefectItems()">Export to CSV</button>
  </div>

  <!-- Stats Cards -->
  <div class="stats-grid">
    <div class="stat-card">
      <h3>Total Defective Items</h3>
      <div class="stat-defective"><?php echo $defectCounts['total_defects'] ?? 0; ?></div>
    </div>
    <div class="stat-card">
      <h3>Products with Defects</h3>
      <div class="stat-total">
        <?php 
          $uniqueProducts = [];
          foreach ($defectItems as $item) {
            if (!in_array($item['product_id'], $uniqueProducts)) {
                $uniqueProducts[] = $item['product_id'];
            }
          }
          echo count($uniqueProducts); 
        ?>
      </div>
    </div>
  </div>

  <!-- Defect Items Table -->
  <table class="defect-table">
    <thead>
      <tr>
        <th>Serial Number</th>
        <th>Product</th>
        <th>Defect Notes</th>
        <th>Status</th>
        <th>Date Defective</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($defectItems)): ?>
        <tr>
          <td colspan="6" style="text-align:center;padding:40px;">
            <div class="empty-state">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <h3>No Defective Items Found</h3>
              <p><?php echo empty($searchQuery) ? 'No defective items in inventory.' : 'No defective items match your search criteria.'; ?></p>
            </div>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($defectItems as $item): ?>
          <tr>
            <td data-label="Serial Number">
              <strong><?php echo htmlspecialchars($item['serial_number']); ?></strong>
              <?php if (!empty($item['color'])): ?>
                <br><small style="color: var(--muted);">Color: <?php echo htmlspecialchars($item['color']); ?></small>
              <?php endif; ?>
            </td>
            <td data-label="Product">
              <?php echo htmlspecialchars($item['product_name']); ?>
              <?php if (!empty($item['product_category'])): ?>
                <br><small style="color: var(--muted);"><?php echo htmlspecialchars($item['product_category']); ?></small>
              <?php endif; ?>
              <br><small style="color: var(--muted);">Price: ₱<?php echo number_format($item['product_price'], 2); ?></small>
            </td>
            <td data-label="Defect Notes">
              <?php if (!empty($item['defect_notes'])): ?>
                <?php echo nl2br(htmlspecialchars(substr($item['defect_notes'], 0, 100))); ?>
                <?php echo strlen($item['defect_notes']) > 100 ? '...' : ''; ?>
              <?php else: ?>
                <span style="color: var(--muted);">No notes provided</span>
              <?php endif; ?>
            </td>
            <td data-label="Status">
              <span class="status-badge status-defective">
                Defective
              </span>
            </td>
            <td data-label="Date Defective">
              <?php 
                $defectDate = !empty($item['defect_date']) ? $item['defect_date'] : $item['updated_at'];
                echo date('M j, Y', strtotime($defectDate)); 
              ?>
              <br><small style="color: var(--muted);">
                <?php echo date('g:i A', strtotime($defectDate)); ?>
              </small>
            </td>
            <td data-label="Actions">
              <div class="action-buttons">
                <button class="action-btn" onclick="viewDefectDetails(<?php echo $item['id']; ?>)">View Details</button>
                <button class="action-btn edit" onclick="markAsRepaired('<?php echo $item['serial_number']; ?>', <?php echo $item['product_id']; ?>, '<?php echo addslashes($item['product_name']); ?>')">Mark as Repaired</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- Defect History Timeline -->
  <?php if (!empty($defectHistory)): ?>
  <div class="history-timeline">
    <h3 style="margin-top: 0; color: var(--muted); margin-bottom: 15px;">Recent Defect History</h3>
    <?php foreach ($defectHistory as $history): ?>
      <div class="history-item">
        <div class="history-icon <?php echo $history['change_type']; ?>">
          <?php if ($history['change_type'] == 'defective'): ?>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <line x1="15" y1="9" x2="9" y2="15"/>
              <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
          <?php else: ?>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
              <path d="M22 4L12 14.01l-3-3"/>
            </svg>
          <?php endif; ?>
        </div>
        <div class="history-content">
          <div class="history-title">
            <?php echo htmlspecialchars($history['product_name'] ?? 'Unknown Product'); ?> 
            (<?php echo htmlspecialchars($history['serial_number'] ?? 'N/A'); ?>)
          </div>
          <div class="history-meta">
            <?php echo ucfirst($history['change_type']); ?> by <?php echo htmlspecialchars($history['username'] ?? 'System'); ?> 
            on <?php echo date('M j, Y g:i A', strtotime($history['change_date'])); ?>
          </div>
          <?php if (!empty($history['notes'])): ?>
            <div class="history-notes">
              <?php echo nl2br(htmlspecialchars($history['notes'])); ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
</div>
</div>

<!-- Mark Item as Defective Modal -->
<div id="markDefectModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 class="modal-title">Mark Item as Defective</h2>
      <button class="close-btn" onclick="closeMarkDefectModal()">&times;</button>
    </div>
    <form id="markDefectForm" method="POST">
      <input type="hidden" name="mark_as_defective" value="1">
      
      <div class="form-group">
        <label for="defect_product_search">Search Product</label>
        <input type="text" id="defect_product_search" placeholder="Type to search for product..." 
               onkeyup="searchProductsForDefect(this.value)" style="width:100%;">
        <div id="defect_product_results" style="max-height:200px;overflow-y:auto;margin-top:10px;display:none;"></div>
      </div>
      
      <div class="form-group">
        <label for="defect_serial_search">Search Serial Number</label>
        <input type="text" id="defect_serial_search" placeholder="Type to search for serial number..." 
               onkeyup="searchSerialsForDefect(this.value)" style="width:100%;" disabled>
        <div id="defect_serial_results" style="max-height:200px;overflow-y:auto;margin-top:10px;display:none;"></div>
      </div>
      
      <input type="hidden" id="product_id" name="product_id">
      <input type="hidden" id="serial_number" name="serial_number">
      
      <div class="form-group">
        <label for="selected_product_info">Selected Item</label>
        <div id="selected_product_info" style="background:#2d3748;padding:10px;border-radius:6px;">
          <span style="color:var(--muted);">No item selected</span>
        </div>
      </div>
      
      <div class="form-group">
        <label for="defect_type">Defect Type</label>
        <select id="defect_type" name="defect_type">
          <option value="Screen Damage">Screen Damage</option>
          <option value="Battery Issue">Battery Issue</option>
          <option value="Water Damage">Water Damage</option>
          <option value="Cosmetic Damage">Cosmetic Damage</option>
          <option value="Software Issue">Software Issue</option>
          <option value="Hardware Failure">Hardware Failure</option>
          <option value="Charging Port">Charging Port</option>
          <option value="Speaker/Microphone">Speaker/Microphone</option>
          <option value="Camera Issue">Camera Issue</option>
          <option value="Other" selected>Other</option>
        </select>
      </div>
      
      <div class="form-group">
        <label for="defect_notes">Defect Notes *</label>
        <textarea id="defect_notes" name="defect_notes" required 
                  placeholder="Describe the defect in detail..."></textarea>
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label for="reported_by">Reported By</label>
          <input type="text" id="reported_by" name="reported_by" 
                 placeholder="Your name or identifier">
        </div>
        <div class="form-group">
          <label for="defect_color">Color</label>
          <input type="text" id="defect_color" name="color" 
                 placeholder="Item color (optional)">
        </div>
      </div>
      
      <div class="form-actions">
        <button type="button" class="btn" onclick="closeMarkDefectModal()">Cancel</button>
        <button type="submit" class="btn warning">Mark as Defective</button>
      </div>
    </form>
  </div>
</div>

<!-- Mark as Repaired Modal -->
<div id="markRepairedModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 class="modal-title">Mark Item as Repaired</h2>
      <button class="close-btn" onclick="closeMarkRepairedModal()">&times;</button>
    </div>
    <form id="markRepairedForm" method="POST">
      <input type="hidden" name="mark_as_repaired" value="1">
      <input type="hidden" id="repair_product_id" name="product_id">
      <input type="hidden" id="repair_serial_number" name="serial_number">
      
      <div class="form-group">
        <label for="repair_item_info">Item Information</label>
        <div id="repair_item_info" style="background:#2d3748;padding:15px;border-radius:6px;margin-bottom:10px;">
          <p style="margin:0;"><strong id="repair_product_name"></strong></p>
          <p style="margin:5px 0 0 0;color:var(--muted);">Serial: <span id="repair_serial_display"></span></p>
        </div>
      </div>
      
      <div class="form-group">
        <label for="repair_cost">Repair Cost (₱)</label>
        <input type="number" id="repair_cost" name="repair_cost" step="0.01" min="0" 
               placeholder="0.00">
      </div>
      
      <div class="form-group">
        <label for="repair_notes">Repair Notes</label>
        <textarea id="repair_notes" name="repair_notes" 
                  placeholder="Describe the repair work done..."></textarea>
      </div>
      
      <div class="form-actions">
        <button type="button" class="btn" onclick="closeMarkRepairedModal()">Cancel</button>
        <button type="submit" class="btn success">Mark as Repaired</button>
      </div>
    </form>
  </div>
</div>

<!-- View Defect Details Modal -->
<div id="viewDefectModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 class="modal-title">Defect Item Details</h2>
      <button class="close-btn" onclick="closeViewDefectModal()">&times;</button>
    </div>
    <div id="defectDetailsContent">
      <!-- Content will be loaded here -->
    </div>
  </div>
</div>

<!-- Toast Notification -->
<div id="toast"></div>

<script>
// Toast notification function
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const colors = {
        'success': '#43a047',
        'error': '#e53935',
        'warning': '#fb8c00',
        'info': '#1e88e5'
    };
    
    toast.textContent = message;
    toast.style.background = colors[type] || colors.info;
    toast.style.display = 'block';
    
    setTimeout(() => {
        toast.style.display = 'none';
    }, 3000);
}

// Open mark defect modal
function openMarkDefectModal() {
    document.getElementById('markDefectModal').style.display = 'flex';
    document.getElementById('defect_product_search').focus();
}

// Close mark defect modal
function closeMarkDefectModal() {
    document.getElementById('markDefectModal').style.display = 'none';
    document.getElementById('markDefectForm').reset();
    document.getElementById('defect_product_results').style.display = 'none';
    document.getElementById('defect_serial_results').style.display = 'none';
    document.getElementById('selected_product_info').innerHTML = '<span style="color:var(--muted);">No item selected</span>';
    document.getElementById('defect_serial_search').disabled = true;
    document.getElementById('defect_serial_search').value = '';
}

// Search products for defect marking
async function searchProductsForDefect(query) {
    if (query.length < 2) {
        document.getElementById('defect_product_results').style.display = 'none';
        return;
    }
    
    try {
        const response = await fetch(`search-available-products.php?q=${encodeURIComponent(query)}`);
        if (!response.ok) {
            throw new Error('Failed to search products');
        }
        
        const products = await response.json();
        
        const resultsDiv = document.getElementById('defect_product_results');
        resultsDiv.innerHTML = '';
        
        if (products.length === 0) {
            resultsDiv.innerHTML = '<div style="padding:10px;color:var(--muted);">No available products found</div>';
            resultsDiv.style.display = 'block';
            return;
        }
        
        products.forEach(product => {
            const div = document.createElement('div');
            div.className = 'product-result-item';
            div.style.padding = '10px';
            div.style.borderBottom = '1px solid var(--border)';
            div.style.cursor = 'pointer';
            div.style.transition = 'background 0.2s';
            div.innerHTML = `
                <strong>${product.name}</strong><br>
                <small style="color:var(--muted);">
                    Category: ${product.category} | 
                    Available Stock: ${product.available_stock} | 
                    Price: ₱${parseFloat(product.price).toFixed(2)}
                </small>
            `;
            
            div.onclick = function() {
                document.getElementById('product_id').value = product.id;
                document.getElementById('defect_product_search').value = product.name;
                document.getElementById('defect_product_results').style.display = 'none';
                
                // Enable serial search
                document.getElementById('defect_serial_search').disabled = false;
                document.getElementById('defect_serial_search').focus();
                
                // Update selected product info
                document.getElementById('selected_product_info').innerHTML = `
                    <strong>${product.name}</strong><br>
                    <small style="color:var(--muted);">Category: ${product.category} | Price: ₱${parseFloat(product.price).toFixed(2)}</small>
                `;
            };
            
            div.onmouseover = function() {
                this.style.background = 'var(--ink-2)';
            };
            
            div.onmouseout = function() {
                this.style.background = '';
            };
            
            resultsDiv.appendChild(div);
        });
        
        resultsDiv.style.display = 'block';
    } catch (error) {
        console.error('Error searching products:', error);
        showToast('Error searching products', 'error');
    }
}

// Search serial numbers for defect marking
async function searchSerialsForDefect(query) {
    const productId = document.getElementById('product_id').value;
    if (!productId || query.length < 1) {
        document.getElementById('defect_serial_results').style.display = 'none';
        return;
    }
    
    try {
        const response = await fetch(`search-available-serials.php?product_id=${productId}&q=${encodeURIComponent(query)}`);
        if (!response.ok) {
            throw new Error('Failed to search serials');
        }
        
        const serials = await response.json();
        
        const resultsDiv = document.getElementById('defect_serial_results');
        resultsDiv.innerHTML = '';
        
        if (serials.length === 0) {
            resultsDiv.innerHTML = '<div style="padding:10px;color:var(--muted);">No available serial numbers found</div>';
            resultsDiv.style.display = 'block';
            return;
        }
        
        serials.forEach(serial => {
            const div = document.createElement('div');
            div.className = 'serial-result-item';
            div.style.padding = '10px';
            div.style.borderBottom = '1px solid var(--border)';
            div.style.cursor = 'pointer';
            div.style.transition = 'background 0.2s';
            div.innerHTML = `
                <strong>${serial.serial_number}</strong>
                ${serial.color ? `<br><small style="color:var(--muted);">Color: ${serial.color}</small>` : ''}
            `;
            
            div.onclick = function() {
                document.getElementById('serial_number').value = serial.serial_number;
                document.getElementById('defect_serial_search').value = serial.serial_number;
                document.getElementById('defect_color').value = serial.color || '';
                document.getElementById('defect_serial_results').style.display = 'none';
                
                // Update selected product info
                const productInfo = document.getElementById('selected_product_info');
                productInfo.innerHTML = `
                    <strong>${document.getElementById('defect_product_search').value}</strong><br>
                    <small style="color:var(--muted);">Serial: ${serial.serial_number}</small>
                    ${serial.color ? `<br><small style="color:var(--muted);">Color: ${serial.color}</small>` : ''}
                `;
            };
            
            div.onmouseover = function() {
                this.style.background = 'var(--ink-2)';
            };
            
            div.onmouseout = function() {
                this.style.background = '';
            };
            
            resultsDiv.appendChild(div);
        });
        
        resultsDiv.style.display = 'block';
    } catch (error) {
        console.error('Error searching serials:', error);
        showToast('Error searching serial numbers', 'error');
    }
}

// View defect details
async function viewDefectDetails(stockId) {
    try {
        console.log('Fetching defect details for ID:', stockId);
        
        const response = await fetch(`get-defect-details.php?id=${stockId}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const defect = await response.json();
        console.log('Defect data:', defect);
        
        if (defect.error) {
            showToast(defect.error, 'error');
            return;
        }
        
        const content = document.getElementById('defectDetailsContent');
        content.innerHTML = `
            <div style="padding:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                    <div>
                        <h3 style="margin-top:0;color:var(--muted);">Item Information</h3>
                        <p><strong>Serial Number:</strong> ${defect.serial_number}</p>
                        <p><strong>Product:</strong> ${defect.product_name}</p>
                        <p><strong>Category:</strong> ${defect.product_category}</p>
                        <p><strong>Color:</strong> ${defect.color || 'N/A'}</p>
                        <p><strong>Price:</strong> ₱${parseFloat(defect.product_price || 0).toFixed(2)}</p>
                    </div>
                    <div>
                        <h3 style="margin-top:0;color:var(--muted);">Defect Information</h3>
                        <p><strong>Status:</strong> 
                            <span class="status-badge status-defective">
                                Defective
                            </span>
                        </p>
                        <p><strong>Date Marked Defective:</strong> ${new Date(defect.updated_at).toLocaleString()}</p>
                        ${defect.defect_date ? `<p><strong>Defect History Date:</strong> ${new Date(defect.defect_date).toLocaleString()}</p>` : ''}
                        ${defect.reported_by_user ? `<p><strong>Reported By:</strong> ${defect.reported_by_user}</p>` : ''}
                    </div>
                </div>
                
                <div style="margin-bottom:20px;">
                    <h3 style="color:var(--muted);">Defect Notes</h3>
                    <div style="background:var(--ink-2);padding:15px;border-radius:8px;">
                        ${defect.defect_notes ? defect.defect_notes.replace(/\n/g, '<br>') : 'No defect notes provided.'}
                    </div>
                </div>
                
                ${defect.defect_history_notes ? `
                <div style="margin-bottom:20px;">
                    <h3 style="color:var(--muted);">History Notes</h3>
                    <div style="background:var(--ink-2);padding:15px;border-radius:8px;">
                        ${defect.defect_history_notes.replace(/\n/g, '<br>')}
                    </div>
                </div>
                ` : ''}
                
                <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border);">
                    <h3 style="color:var(--muted);">Stock Information</h3>
                    <p><strong>Stock ID:</strong> ${defect.id}</p>
                    <p><strong>Product ID:</strong> ${defect.product_id}</p>
                    <p><strong>Created:</strong> ${new Date(defect.created_at).toLocaleString()}</p>
                    <p><strong>Last Updated:</strong> ${new Date(defect.updated_at).toLocaleString()}</p>
                </div>
            </div>
        `;
        
        document.getElementById('viewDefectModal').style.display = 'flex';
    } catch (error) {
        console.error('Error loading defect details:', error);
        showToast('Error loading defect details: ' + error.message, 'error');
    }
}

// Close view defect modal
function closeViewDefectModal() {
    document.getElementById('viewDefectModal').style.display = 'none';
}

// Mark as repaired
function markAsRepaired(serialNumber, productId, productName) {
    document.getElementById('repair_product_id').value = productId;
    document.getElementById('repair_serial_number').value = serialNumber;
    document.getElementById('repair_serial_display').textContent = serialNumber;
    document.getElementById('repair_product_name').textContent = productName;
    
    document.getElementById('markRepairedModal').style.display = 'flex';
}

// Close mark repaired modal
function closeMarkRepairedModal() {
    document.getElementById('markRepairedModal').style.display = 'none';
    document.getElementById('markRepairedForm').reset();
}

// Export defect items to CSV
async function exportDefectItems() {
    const search = '<?php echo $searchQuery; ?>';
    
    try {
        showToast('Exporting defect items...', 'info');
        
        const response = await fetch(`export-defect-items.php?search=${encodeURIComponent(search)}`);
        if (!response.ok) {
            throw new Error('Failed to export');
        }
        
        const blob = await response.blob();
        
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `defect_items_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        showToast('Defect items exported successfully!', 'success');
    } catch (error) {
        console.error('Error exporting defect items:', error);
        showToast('Error exporting defect items', 'error');
    }
}

// Close modals when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const modals = ['markDefectModal', 'markRepairedModal', 'viewDefectModal'];
    
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                    if (modalId === 'markDefectModal') {
                        document.getElementById('markDefectForm').reset();
                        document.getElementById('selected_product_info').innerHTML = '<span style="color:var(--muted);">No item selected</span>';
                        document.getElementById('defect_serial_search').disabled = true;
                    }
                }
            });
        }
    });
    
    // Show toast from PHP if exists
    <?php if ($toast): ?>
        showToast('<?php echo addslashes($toast['message']); ?>', '<?php echo $toast['type']; ?>');
    <?php endif; ?>
});
</script>
</body>
</html>
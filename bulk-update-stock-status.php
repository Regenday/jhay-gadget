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

// Create defect items table if it doesn't exist (UPDATED without disposed status)
$createDefectTable = "CREATE TABLE IF NOT EXISTS defect_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    serial_number VARCHAR(100) NOT NULL,
    color VARCHAR(50),
    defect_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    reported_by VARCHAR(255),
    location_found VARCHAR(255),
    status ENUM('pending', 'under_repair', 'repaired') DEFAULT 'pending',
    repair_cost DECIMAL(10,2) DEFAULT 0.00,
    repair_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_product_id (product_id),
    INDEX idx_serial (serial_number),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)";

if (!$db->query($createDefectTable)) {
    error_log("Defect items table creation failed: " . $db->error);
}

// ============================================
// MARK DEFECTIVE FUNCTIONALITY - From Activity Log
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_defective_action'])) {
    $product_id = $_POST['product_id'];
    $serial_numbers = json_decode($_POST['serial_numbers'], true);
    $notes = trim($_POST['notes']);
    
    // Validate input
    if (empty($product_id) || empty($serial_numbers) || empty($notes)) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Missing required fields'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    // Get product name for logging
    $product_stmt = $db->prepare("SELECT name FROM products WHERE id = ?");
    $product_stmt->bind_param("i", $product_id);
    $product_stmt->execute();
    $product_result = $product_stmt->get_result();
    
    if ($product_result->num_rows === 0) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Product not found'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $product_data = $product_result->fetch_assoc();
    $product_name = $product_data['name'];
    
    $db->begin_transaction();
    
    try {
        $marked_count = 0;
        $success_serials = [];
        $errors = [];
        
        foreach ($serial_numbers as $serial) {
            $serial = trim($serial);
            
            // Check if serial exists and is available for this product
            $check_sql = "SELECT ps.id, ps.status, ps.serial_number 
                         FROM product_stock ps 
                         WHERE ps.product_id = ? 
                         AND ps.serial_number = ?";
            $check_stmt = $db->prepare($check_sql);
            $check_stmt->bind_param("is", $product_id, $serial);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows === 0) {
                $errors[] = "Serial '$serial' not found for product '$product_name'";
                continue;
            }
            
            $stock_item = $result->fetch_assoc();
            
            // Check current status
            if ($stock_item['status'] === 'Defective') {
                $errors[] = "Serial '$serial' is already marked as defective";
                continue;
            }
            
            if ($stock_item['status'] === 'Sold') {
                $errors[] = "Serial '$serial' has already been sold";
                continue;
            }
            
            // Update stock status to defective
            $update_sql = "UPDATE product_stock 
                          SET status = 'Defective', 
                              defect_notes = ?, 
                              updated_at = NOW() 
                          WHERE id = ? 
                          AND status = 'Available'";
            $update_stmt = $db->prepare($update_sql);
            $update_stmt->bind_param("si", $notes, $stock_item['id']);
            
            if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                $marked_count++;
                $success_serials[] = $serial;
                
                // Record in stock history
                $history_sql = "INSERT INTO stock_history 
                               (product_id, stock_id, change_type, previous_status, new_status, 
                                changed_by, notes, change_date, quantity_change) 
                               VALUES (?, ?, 'defective', 'Available', 'Defective', ?, ?, NOW(), -1)";
                $history_stmt = $db->prepare($history_sql);
                $history_stmt->bind_param("iiis", 
                    $product_id, 
                    $stock_item['id'], 
                    $_SESSION['user_id'], 
                    $notes
                );
                $history_stmt->execute();
                
                // Also add to defect_items table
                $defect_stmt = $db->prepare("INSERT INTO defect_items 
                                            (product_id, serial_number, defect_type, description, status) 
                                            VALUES (?, ?, 'Defective', ?, 'pending')");
                $defect_stmt->bind_param("iss", $product_id, $serial, $notes);
                $defect_stmt->execute();
                
            } else {
                $errors[] = "Failed to mark serial '$serial' as defective";
            }
        }
        
        // Update product stock count
        if ($marked_count > 0) {
            $update_product_sql = "UPDATE products 
                                  SET stock = stock - ? 
                                  WHERE id = ?";
            $update_product_stmt = $db->prepare($update_product_sql);
            $update_product_stmt->bind_param("ii", $marked_count, $product_id);
            $update_product_stmt->execute();
        }
        
        // Log the activity in the EXACT format from your activity log
        if ($marked_count > 0) {
            $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Unknown';
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $serial_list = implode(', ', $success_serials);
            
            $log_sql = "INSERT INTO activity_log 
                       (user_id, username, action, details, ip_address, user_agent) 
                       VALUES (?, ?, 'Stock Marked Defective', ?, ?, ?)";
            $log_stmt = $db->prepare($log_sql);
            $details = "Marked $marked_count items as defective for $product_name. Reason: $notes. Serials: $serial_list";
            $log_stmt->bind_param("issss", 
                $_SESSION['user_id'], 
                $username, 
                $details, 
                $ip_address, 
                $user_agent
            );
            $log_stmt->execute();
        }
        
        $db->commit();
        
        // Show success message
        $_SESSION['toast'] = [
            'type' => 'success', 
            'message' => "Successfully marked $marked_count item(s) as defective for '$product_name'"
        ];
        
        // Add errors to session if any
        if (!empty($errors)) {
            $_SESSION['toast']['errors'] = $errors;
        }
        
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Error marking items as defective: ' . $e->getMessage()];
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle defect item status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_defect_status'])) {
    $defectId = $_POST['defect_id'];
    $newStatus = $_POST['defect_status'];
    $repairCost = $_POST['repair_cost'] ?? 0;
    $repairNotes = $_POST['repair_notes'] ?? '';
    
    $stmt = $db->prepare("UPDATE defect_items SET status = ?, repair_cost = ?, repair_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("sdsi", $newStatus, $repairCost, $repairNotes, $defectId);
    if ($stmt->execute()) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Defect item status updated successfully.'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Error updating defect item status.'];
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle add new defect item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_defect_item'])) {
    $productId = $_POST['product_id'];
    $serialNumber = $_POST['serial_number'] ?? '';
    $color = $_POST['color'] ?? '';
    $defectType = $_POST['defect_type'];
    $description = $_POST['description'];
    $reportedBy = $_POST['reported_by'] ?? '';
    $locationFound = $_POST['location_found'] ?? '';
    
    // First, update the stock item to be defective
    $updateStock = $db->prepare("UPDATE product_stock SET status = 'Defective', defect_notes = ? WHERE product_id = ? AND serial_number = ?");
    $updateStock->bind_param("sis", $description, $productId, $serialNumber);
    $updateStock->execute();
    
    // Then add to defect_items table
    $stmt = $db->prepare("INSERT INTO defect_items (product_id, serial_number, color, defect_type, description, reported_by, location_found) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $productId, $serialNumber, $color, $defectType, $description, $reportedBy, $locationFound);
    if ($stmt->execute()) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Defect item added successfully.'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Error adding defect item.'];
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle delete defect item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_defect_item'])) {
    $defectId = $_POST['defect_id'];
    
    // Get the serial number before deleting
    $getStmt = $db->prepare("SELECT product_id, serial_number FROM defect_items WHERE id = ?");
    $getStmt->bind_param("i", $defectId);
    $getStmt->execute();
    $result = $getStmt->get_result();
    if ($row = $result->fetch_assoc()) {
        // Update stock status back to Available
        $updateStock = $db->prepare("UPDATE product_stock SET status = 'Available', defect_notes = NULL WHERE product_id = ? AND serial_number = ?");
        $updateStock->bind_param("is", $row['product_id'], $row['serial_number']);
        $updateStock->execute();
    }
    
    $stmt = $db->prepare("DELETE FROM defect_items WHERE id = ?");
    $stmt->bind_param("i", $defectId);
    if ($stmt->execute()) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Defect item deleted successfully.'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Error deleting defect item.'];
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch defect items with search
$searchQuery = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';

// Build query for defect items
$defectQuery = "SELECT 
    di.*,
    p.name as product_name,
    p.category as product_category
    FROM defect_items di
    LEFT JOIN products p ON di.product_id = p.id
    WHERE 1=1";

$params = [];
$types = "";

// Apply search filter
if (!empty($searchQuery)) {
    $defectQuery .= " AND (
        di.serial_number LIKE ? OR 
        p.name LIKE ? OR 
        di.defect_type LIKE ? OR 
        di.description LIKE ? OR
        di.reported_by LIKE ? OR
        di.location_found LIKE ?
    )";
    $searchTerm = "%$searchQuery%";
    $params = array_fill(0, 6, $searchTerm);
    $types = str_repeat("s", 6);
}

// Apply status filter
if ($statusFilter !== 'all') {
    $defectQuery .= " AND di.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$defectQuery .= " ORDER BY di.created_at DESC";

$defectStmt = $db->prepare($defectQuery);
if (!empty($params)) {
    $defectStmt->bind_param($types, ...$params);
}
$defectStmt->execute();
$defectItems = $defectStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get counts for each status
$countQuery = "SELECT 
    status, 
    COUNT(*) as count 
    FROM defect_items 
    GROUP BY status";
$countResult = $db->query($countQuery);
$defectCounts = [
    'all' => 0,
    'pending' => 0,
    'under_repair' => 0,
    'repaired' => 0
];

while ($row = $countResult->fetch_assoc()) {
    $defectCounts[$row['status']] = $row['count'];
    $defectCounts['all'] += $row['count'];
}

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

.stat-pending { color: var(--orange); }
.stat-under_repair { color: var(--blue); }
.stat-repaired { color: var(--green); }
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

.status-pending { background: rgba(251, 140, 0, 0.2); color: #fb8c00; border: 1px solid #fb8c00; }
.status-under_repair { background: rgba(30, 136, 229, 0.2); color: #1e88e5; border: 1px solid #1e88e5; }
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

/* Quick Defect Form Styles */
.quick-defect-section {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 20px;
}

.quick-defect-section h3 {
  margin-top: 0;
  color: #f9fafb;
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.quick-defect-section h3 svg {
  color: var(--orange);
}

.serial-input-container {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 10px;
  margin-bottom: 10px;
}

.serial-list {
  max-height: 200px;
  overflow-y: auto;
  background: var(--ink-2);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px;
  margin-top: 10px;
}

.serial-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px;
  margin-bottom: 5px;
  background: rgba(255,255,255,0.05);
  border-radius: 4px;
}

.remove-serial {
  background: var(--red);
  color: white;
  border: none;
  border-radius: 4px;
  padding: 4px 8px;
  cursor: pointer;
  font-size: 12px;
}

.remove-serial:hover {
  background: #b91c1c;
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
  .serial-input-container{grid-template-columns:1fr;}
}

@media (max-width: 480px){
  .stats-grid{grid-template-columns:1fr;}
  .action-buttons{flex-direction:column;align-items:stretch;}
  .action-btn{text-align:center;}
  .quick-defect-section .form-row{grid-template-columns:1fr;}
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
    <a href="defect-items.php" class="chipRow active">
      <div class="chipLabel"><span class="dot"></span><span>Defect Items</span></div>
      <span class="badge"><?php echo $defectCounts['all']; ?></span>
    </a>
  </div>
  
  <div class="navGroup">
    <div class="navTitle">Filter by Status</div>
    <div class="chipRow <?php echo $statusFilter === 'all' ? 'active' : ''; ?>" onclick="filterByStatus('all')">
      <div class="chipLabel"><span>All Defects</span></div>
      <span class="badge"><?php echo $defectCounts['all']; ?></span>
    </div>
    <div class="chipRow <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>" onclick="filterByStatus('pending')">
      <div class="chipLabel"><span>Pending</span></div>
      <span class="badge"><?php echo $defectCounts['pending']; ?></span>
    </div>
    <div class="chipRow <?php echo $statusFilter === 'under_repair' ? 'active' : ''; ?>" onclick="filterByStatus('under_repair')">
      <div class="chipLabel"><span>Under Repair</span></div>
      <span class="badge"><?php echo $defectCounts['under_repair']; ?></span>
    </div>
    <div class="chipRow <?php echo $statusFilter === 'repaired' ? 'active' : ''; ?>" onclick="filterByStatus('repaired')">
      <div class="chipLabel"><span>Repaired</span></div>
      <span class="badge"><?php echo $defectCounts['repaired']; ?></span>
    </div>
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
        <input type="hidden" name="status" value="<?php echo $statusFilter; ?>">
        <input 
          type="text" 
          name="search" 
          placeholder="Search defect items by serial, product, defect type, etc..." 
          value="<?php echo htmlspecialchars($searchQuery); ?>"
          style="flex: 1; border: none; background: transparent; color: white; outline: none;"
        >
        <button type="submit" class="btn primary" style="padding: 8px 16px;">Search</button>
        <?php if (!empty($searchQuery)): ?>
          <a href="?status=<?php echo $statusFilter; ?>" class="btn" style="padding: 8px 16px;">Clear</a>
        <?php endif; ?>
      </form>
    </div>
    <button class="btn primary" onclick="openAddDefectModal()">+ Add Defect Item</button>
    <button class="btn info" onclick="exportDefectItems()">Export to CSV</button>
  </div>

  <!-- Stats Cards -->
  <div class="stats-grid">
    <div class="stat-card">
      <h3>Total Defects</h3>
      <div class="stat-total"><?php echo $defectCounts['all']; ?></div>
    </div>
    <div class="stat-card">
      <h3>Pending</h3>
      <div class="stat-pending"><?php echo $defectCounts['pending']; ?></div>
    </div>
    <div class="stat-card">
      <h3>Under Repair</h3>
      <div class="stat-under_repair"><?php echo $defectCounts['under_repair']; ?></div>
    </div>
    <div class="stat-card">
      <h3>Repaired</h3>
      <div class="stat-repaired"><?php echo $defectCounts['repaired']; ?></div>
    </div>
  </div>

  <!-- Quick Mark Defective Section -->
  <div class="quick-defect-section">
    <h3>
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/>
        <line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
      Quick Mark Defective (From Activity Log Style)
    </h3>
    
    <form id="quickDefectForm" method="POST">
      <input type="hidden" name="mark_defective_action" value="1">
      
      <div class="form-row">
        <div class="form-group">
          <label for="quick_product_search">Search Product</label>
          <input type="text" id="quick_product_search" placeholder="Type to search for product..." 
                 onkeyup="searchQuickProducts(this.value)" style="width:100%;">
          <div id="quick_product_results" style="max-height:200px;overflow-y:auto;margin-top:10px;display:none;"></div>
        </div>
        <div class="form-group">
          <label for="quick_selected_product">Selected Product</label>
          <input type="text" id="quick_selected_product" readonly style="background:#2d3748;">
          <input type="hidden" id="quick_product_id" name="product_id">
        </div>
      </div>
      
      <div class="form-group">
        <label for="defect_notes">Defect Notes (Required) - Like in Activity Log</label>
        <textarea id="defect_notes" name="notes" required 
                  placeholder="Enter defect reason/details (e.g., Screen damage, Battery issue, Water damage, etc.)" 
                  style="width:100%;padding:10px;border-radius:6px;border:1px solid #333;background:#1f2937;color:#fff;font-size:14px;min-height:80px;resize:vertical;"></textarea>
      </div>
      
      <div class="form-group">
        <label>Add Serial Numbers (One per line)</label>
        <div class="serial-input-container">
          <input type="text" id="quick_serial_input" placeholder="Enter serial number (e.g., 1234, 5678)" style="width:100%;">
          <button type="button" class="btn primary" onclick="addQuickSerial()" style="white-space:nowrap;">Add Serial</button>
        </div>
        <div id="quick_serial_list" class="serial-list">
          <div style="color:#6b7280;text-align:center;padding:20px;">No serial numbers added yet</div>
        </div>
        <input type="hidden" id="quick_serial_numbers" name="serial_numbers">
      </div>
      
      <div class="form-actions">
        <button type="button" class="btn" onclick="clearQuickDefectForm()">Clear</button>
        <button type="submit" class="btn danger">Mark as Defective</button>
      </div>
    </form>
  </div>

  <!-- Defect Items Table -->
  <table class="defect-table">
    <thead>
      <tr>
        <th>Serial Number</th>
        <th>Product</th>
        <th>Defect Type</th>
        <th>Status</th>
        <th>Reported By</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($defectItems)): ?>
        <tr>
          <td colspan="7" style="text-align:center;padding:40px;">
            <div class="empty-state">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <h3>No Defect Items Found</h3>
              <p><?php echo empty($searchQuery) ? 'No defect items have been reported yet.' : 'No defect items match your search criteria.'; ?></p>
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
            </td>
            <td data-label="Defect Type">
              <?php echo htmlspecialchars($item['defect_type']); ?>
              <?php if (!empty($item['description'])): ?>
                <br><small style="color: var(--muted);"><?php echo nl2br(htmlspecialchars(substr($item['description'], 0, 50))); ?><?php echo strlen($item['description']) > 50 ? '...' : ''; ?></small>
              <?php endif; ?>
            </td>
            <td data-label="Status">
              <span class="status-badge status-<?php echo $item['status']; ?>">
                <?php echo str_replace('_', ' ', ucfirst($item['status'])); ?>
              </span>
              <?php if (!empty($item['repair_cost']) && $item['repair_cost'] > 0): ?>
                <br><small style="color: var(--muted);">Cost: ₱<?php echo number_format($item['repair_cost'], 2); ?></small>
              <?php endif; ?>
            </td>
            <td data-label="Reported By">
              <?php echo !empty($item['reported_by']) ? htmlspecialchars($item['reported_by']) : '<span style="color:var(--muted);">N/A</span>'; ?>
              <?php if (!empty($item['location_found'])): ?>
                <br><small style="color: var(--muted);">Location: <?php echo htmlspecialchars($item['location_found']); ?></small>
              <?php endif; ?>
            </td>
            <td data-label="Date">
              <?php echo date('M j, Y', strtotime($item['created_at'])); ?>
              <br><small style="color: var(--muted);"><?php echo date('g:i A', strtotime($item['created_at'])); ?></small>
            </td>
            <td data-label="Actions">
              <div class="action-buttons">
                <button class="action-btn edit" onclick="viewDefectDetails(<?php echo $item['id']; ?>)">View</button>
                <button class="action-btn" onclick="updateDefectStatus(<?php echo $item['id']; ?>)">Update Status</button>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this defect item? This will mark the stock item as available again.');">
                  <input type="hidden" name="defect_id" value="<?php echo $item['id']; ?>">
                  <input type="hidden" name="delete_defect_item" value="1">
                  <button type="submit" class="action-btn delete">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</main>
</div>
</div>

<!-- Add Defect Item Modal -->
<div id="addDefectModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 class="modal-title">Add Defect Item</h2>
      <button class="close-btn" onclick="closeAddDefectModal()">&times;</button>
    </div>
    <form id="addDefectForm" method="POST">
      <input type="hidden" name="add_defect_item" value="1">
      
      <div class="form-group">
        <label for="product_search">Search Product</label>
        <input type="text" id="product_search" placeholder="Type to search for product..." 
               onkeyup="searchProducts(this.value)" style="width:100%;">
        <div id="product_results" style="max-height:200px;overflow-y:auto;margin-top:10px;display:none;"></div>
      </div>
      
      <div class="form-group">
        <label for="selected_product">Selected Product</label>
        <input type="text" id="selected_product" readonly style="background:#2d3748;">
        <input type="hidden" id="product_id" name="product_id">
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label for="serial_number">Serial Number *</label>
          <input type="text" id="serial_number" name="serial_number" required 
                 placeholder="Enter serial number (e.g., SN123456789)">
        </div>
        <div class="form-group">
          <label for="color">Color</label>
          <input type="text" id="color" name="color" placeholder="Enter color (e.g., Black, White)">
        </div>
      </div>
      
      <div class="form-group">
        <label for="defect_type">Defect Type *</label>
        <select id="defect_type" name="defect_type" required>
          <option value="">Select Defect Type</option>
          <option value="Screen Damage">Screen Damage</option>
          <option value="Battery Issue">Battery Issue</option>
          <option value="Water Damage">Water Damage</option>
          <option value="Cosmetic Damage">Cosmetic Damage</option>
          <option value="Software Issue">Software Issue</option>
          <option value="Hardware Failure">Hardware Failure</option>
          <option value="Charging Port">Charging Port</option>
          <option value="Speaker/Microphone">Speaker/Microphone</option>
          <option value="Camera Issue">Camera Issue</option>
          <option value="Other">Other</option>
        </select>
      </div>
      
      <div class="form-group">
        <label for="description">Description *</label>
        <textarea id="description" name="description" required 
                  placeholder="Describe the defect in detail..."></textarea>
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label for="reported_by">Reported By</label>
          <input type="text" id="reported_by" name="reported_by" 
                 placeholder="Name of person who reported">
        </div>
        <div class="form-group">
          <label for="location_found">Location Found</label>
          <input type="text" id="location_found" name="location_found" 
                 placeholder="Where was the defect found?">
        </div>
      </div>
      
      <div class="form-actions">
        <button type="button" class="btn" onclick="closeAddDefectModal()">Cancel</button>
        <button type="submit" class="btn primary">Add Defect Item</button>
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

<!-- Update Status Modal -->
<div id="updateStatusModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 class="modal-title">Update Defect Status</h2>
      <button class="close-btn" onclick="closeUpdateStatusModal()">&times;</button>
    </div>
    <form id="updateStatusForm" method="POST">
      <input type="hidden" id="update_defect_id" name="defect_id">
      <input type="hidden" name="update_defect_status" value="1">
      
      <div class="form-group">
        <label for="defect_status">Status *</label>
        <select id="defect_status" name="defect_status" required>
          <option value="pending">Pending</option>
          <option value="under_repair">Under Repair</option>
          <option value="repaired">Repaired</option>
        </select>
      </div>
      
      <div class="form-group">
        <label for="repair_cost">Repair Cost (₱)</label>
        <input type="number" id="repair_cost" name="repair_cost" step="0.01" min="0" 
               placeholder="0.00">
      </div>
      
      <div class="form-group">
        <label for="repair_notes">Repair Notes</label>
        <textarea id="repair_notes" name="repair_notes" 
                  placeholder="Enter repair details or notes..."></textarea>
      </div>
      
      <div class="form-actions">
        <button type="button" class="btn" onclick="closeUpdateStatusModal()">Cancel</button>
        <button type="submit" class="btn primary">Update Status</button>
      </div>
    </form>
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

// Filter by status
function filterByStatus(status) {
    const url = new URL(window.location.href);
    url.searchParams.set('status', status);
    if (status === 'all') {
        url.searchParams.delete('status');
    }
    window.location.href = url.toString();
}

// ============================================
// QUICK DEFECT MARKING FUNCTIONALITY
// ============================================

let quickSerials = [];

// Search products for quick defect
async function searchQuickProducts(query) {
    if (query.length < 2) {
        document.getElementById('quick_product_results').style.display = 'none';
        return;
    }
    
    try {
        const response = await fetch(`search-products.php?q=${encodeURIComponent(query)}`);
        const products = await response.json();
        
        const resultsDiv = document.getElementById('quick_product_results');
        resultsDiv.innerHTML = '';
        
        if (products.length === 0) {
            resultsDiv.innerHTML = '<div style="padding:10px;color:var(--muted);">No products found</div>';
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
                    Available: ${product.available_stock} | 
                    Category: ${product.category}
                </small>
            `;
            
            div.onclick = function() {
                document.getElementById('quick_selected_product').value = product.name;
                document.getElementById('quick_product_id').value = product.id;
                document.getElementById('quick_product_results').style.display = 'none';
                document.getElementById('quick_product_search').value = '';
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

// Add serial to quick defect list
function addQuickSerial() {
    const serialInput = document.getElementById('quick_serial_input');
    const serial = serialInput.value.trim();
    
    if (!serial) {
        showToast('Please enter a serial number', 'error');
        return;
    }
    
    if (quickSerials.includes(serial)) {
        showToast('Serial number already in list', 'warning');
        return;
    }
    
    quickSerials.push(serial);
    updateQuickSerialList();
    serialInput.value = '';
    serialInput.focus();
    showToast('Serial added to list', 'success');
}

// Update quick serial list display
function updateQuickSerialList() {
    const serialList = document.getElementById('quick_serial_list');
    const serialNumbersInput = document.getElementById('quick_serial_numbers');
    
    if (quickSerials.length === 0) {
        serialList.innerHTML = '<div style="color:#6b7280;text-align:center;padding:20px;">No serial numbers added yet</div>';
        serialNumbersInput.value = '';
    } else {
        serialList.innerHTML = quickSerials.map((serial, index) => `
            <div class="serial-item">
                <div>${serial}</div>
                <button type="button" class="remove-serial" onclick="removeQuickSerial(${index})">Remove</button>
            </div>
        `).join('');
        serialNumbersInput.value = JSON.stringify(quickSerials);
    }
}

// Remove serial from quick list
function removeQuickSerial(index) {
    quickSerials.splice(index, 1);
    updateQuickSerialList();
    showToast('Serial removed from list', 'warning');
}

// Clear quick defect form
function clearQuickDefectForm() {
    quickSerials = [];
    updateQuickSerialList();
    document.getElementById('quick_product_search').value = '';
    document.getElementById('quick_selected_product').value = '';
    document.getElementById('quick_product_id').value = '';
    document.getElementById('defect_notes').value = '';
    document.getElementById('quick_product_results').style.display = 'none';
    showToast('Form cleared', 'info');
}

// Handle quick defect form submission
document.getElementById('quickDefectForm').addEventListener('submit', function(e) {
    const productId = document.getElementById('quick_product_id').value;
    const notes = document.getElementById('defect_notes').value.trim();
    
    if (!productId) {
        e.preventDefault();
        showToast('Please select a product', 'error');
        return;
    }
    
    if (quickSerials.length === 0) {
        e.preventDefault();
        showToast('Please add at least one serial number', 'error');
        return;
    }
    
    if (!notes) {
        e.preventDefault();
        showToast('Please enter defect notes', 'error');
        return;
    }
    
    // Confirm before submitting
    if (!confirm(`Are you sure you want to mark ${quickSerials.length} item(s) as defective?\n\nNotes: ${notes}`)) {
        e.preventDefault();
    }
});

// ============================================
// EXISTING FUNCTIONALITY
// ============================================

// Open add defect modal
function openAddDefectModal() {
    document.getElementById('addDefectModal').style.display = 'flex';
}

// Close add defect modal
function closeAddDefectModal() {
    document.getElementById('addDefectModal').style.display = 'none';
    document.getElementById('addDefectForm').reset();
    document.getElementById('product_results').style.display = 'none';
    document.getElementById('selected_product').value = '';
    document.getElementById('product_id').value = '';
}

// Search products for defect reporting
async function searchProducts(query) {
    if (query.length < 2) {
        document.getElementById('product_results').style.display = 'none';
        return;
    }
    
    try {
        const response = await fetch(`search-products.php?q=${encodeURIComponent(query)}`);
        const products = await response.json();
        
        const resultsDiv = document.getElementById('product_results');
        resultsDiv.innerHTML = '';
        
        if (products.length === 0) {
            resultsDiv.innerHTML = '<div style="padding:10px;color:var(--muted);">No products found</div>';
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
                    Available: ${product.available_stock} | 
                    Price: ₱${parseFloat(product.price).toFixed(2)}
                </small>
            `;
            
            div.onclick = function() {
                document.getElementById('selected_product').value = product.name;
                document.getElementById('product_id').value = product.id;
                document.getElementById('product_results').style.display = 'none';
                document.getElementById('product_search').value = '';
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

// View defect details
async function viewDefectDetails(defectId) {
    try {
        const response = await fetch(`get-defect-details.php?id=${defectId}`);
        const defect = await response.json();
        
        const content = document.getElementById('defectDetailsContent');
        content.innerHTML = `
            <div style="padding:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                    <div>
                        <h3 style="margin-top:0;color:var(--muted);">Basic Information</h3>
                        <p><strong>Serial Number:</strong> ${defect.serial_number}</p>
                        <p><strong>Product:</strong> ${defect.product_name}</p>
                        ${defect.color ? `<p><strong>Color:</strong> ${defect.color}</p>` : ''}
                        <p><strong>Defect Type:</strong> ${defect.defect_type}</p>
                        <p><strong>Status:</strong> 
                            <span class="status-badge status-${defect.status}">
                                ${defect.status.replace('_', ' ')}
                            </span>
                        </p>
                    </div>
                    <div>
                        <h3 style="margin-top:0;color:var(--muted);">Report Details</h3>
                        <p><strong>Reported By:</strong> ${defect.reported_by || 'N/A'}</p>
                        <p><strong>Location:</strong> ${defect.location_found || 'N/A'}</p>
                        <p><strong>Date Reported:</strong> ${new Date(defect.created_at).toLocaleString()}</p>
                        <p><strong>Last Updated:</strong> ${new Date(defect.updated_at).toLocaleString()}</p>
                    </div>
                </div>
                
                <div style="margin-bottom:20px;">
                    <h3 style="color:var(--muted);">Description</h3>
                    <div style="background:var(--ink-2);padding:15px;border-radius:8px;">
                        ${defect.description.replace(/\n/g, '<br>')}
                    </div>
                </div>
                
                ${defect.repair_notes ? `
                <div style="margin-bottom:20px;">
                    <h3 style="color:var(--muted);">Repair Notes</h3>
                    <div style="background:var(--ink-2);padding:15px;border-radius:8px;">
                        ${defect.repair_notes.replace(/\n/g, '<br>')}
                    </div>
                </div>
                ` : ''}
                
                ${defect.repair_cost > 0 ? `
                <div style="margin-bottom:20px;">
                    <h3 style="color:var(--muted);">Repair Cost</h3>
                    <div style="font-size:24px;font-weight:bold;color:var(--green);">
                        ₱${parseFloat(defect.repair_cost).toFixed(2)}
                    </div>
                </div>
                ` : ''}
            </div>
        `;
        
        document.getElementById('viewDefectModal').style.display = 'flex';
    } catch (error) {
        console.error('Error loading defect details:', error);
        showToast('Error loading defect details', 'error');
    }
}

// Close view defect modal
function closeViewDefectModal() {
    document.getElementById('viewDefectModal').style.display = 'none';
}

// Update defect status
async function updateDefectStatus(defectId) {
    try {
        // Load current defect data
        const response = await fetch(`get-defect-details.php?id=${defectId}`);
        const defect = await response.json();
        
        // Populate form
        document.getElementById('update_defect_id').value = defect.id;
        document.getElementById('defect_status').value = defect.status;
        document.getElementById('repair_cost').value = defect.repair_cost || '';
        document.getElementById('repair_notes').value = defect.repair_notes || '';
        
        document.getElementById('updateStatusModal').style.display = 'flex';
    } catch (error) {
        console.error('Error loading defect for update:', error);
        showToast('Error loading defect details', 'error');
    }
}

// Close update status modal
function closeUpdateStatusModal() {
    document.getElementById('updateStatusModal').style.display = 'none';
    document.getElementById('updateStatusForm').reset();
}

// Export defect items to CSV
async function exportDefectItems() {
    const search = '<?php echo $searchQuery; ?>';
    const status = '<?php echo $statusFilter; ?>';
    
    try {
        showToast('Exporting defect items...', 'info');
        
        const response = await fetch(`export-defect-items.php?search=${encodeURIComponent(search)}&status=${status}`);
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
    const modals = ['addDefectModal', 'viewDefectModal', 'updateStatusModal'];
    
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                    if (modalId === 'addDefectModal') {
                        document.getElementById('addDefectForm').reset();
                    }
                }
            });
        }
    });
    
    // Show toast from PHP if exists
    <?php if ($toast): ?>
        <?php if (isset($toast['errors']) && !empty($toast['errors'])): ?>
            showToast('<?php echo $toast['message']; ?> Some items failed: <?php echo implode(", ", $toast['errors']); ?>', 'warning');
        <?php else: ?>
            showToast('<?php echo $toast['message']; ?>', '<?php echo $toast['type']; ?>');
        <?php endif; ?>
    <?php endif; ?>
    
    // Enter key for quick serial input
    document.getElementById('quick_serial_input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addQuickSerial();
        }
    });
    
    // Enter key for quick product search
    document.getElementById('quick_product_search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchQuickProducts(this.value);
        }
    });
});
</script>
</body>
</html>
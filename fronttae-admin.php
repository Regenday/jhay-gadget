<?php
// Error reporting - turned off display for production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Start output buffering to catch any stray output
ob_start();

include 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// End output buffering for main page
ob_end_flush();

// Fetch products with detailed stock counts by color
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

// Get color distribution for each product
$colorDistribution = [];
$colorRes = $db->query("
    SELECT 
        product_id, 
        color, 
        COUNT(*) as total_count,
        COUNT(CASE WHEN status = 'Available' THEN 1 END) as available_count,
        COUNT(CASE WHEN status = 'Sold' THEN 1 END) as sold_count,
        COUNT(CASE WHEN status = 'Defective' THEN 1 END) as defective_count
    FROM product_stock 
    GROUP BY product_id, color
");
if ($colorRes) {
    while ($row = $colorRes->fetch_assoc()) {
        $colorDistribution[$row['product_id']][$row['color']] = [
            'total' => $row['total_count'],
            'available' => $row['available_count'],
            'sold' => $row['sold_count'],
            'defective' => $row['defective_count']
        ];
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

// Helper functions
function getStockLevel($stock, $critical = 10) {
    $stock = intval($stock);
    $critical = intval($critical);
    if ($stock === 0) return 'stock-low';
    if ($stock <= $critical) return 'stock-warning';
    return 'stock-ok';
}

function getStatusClass($status) {
    if (!$status) return '';
    $status = strtolower($status);
    switch($status) {
        case 'sold': return 'sold-status';
        case 'defective': return 'defective-status';
        default: return '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>JHAY Gadget · Products</title>
<!-- Add CDN links for PDF functionality -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<style>
/* ALL YOUR CSS STYLES REMAIN EXACTLY THE SAME */
:root {
  --blue:#1e88e5; --blue-600:#1976d2;
  --ink:#0f172a; --ink-2:#111827;
  --bg:#000; --card:#111; --border:#333;
  --muted:#9ca3af; --accent:#22c55e;
  --stock-low:#dc2626; --stock-ok:#22c55e; --stock-warning:#f59e0b;
  --sold:#8b5cf6; --defective:#f97316;
  --purchase:#10b981;
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
.activity-log-btn {
  background: #8b5cf6;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  transition: background 0.2s;
  margin-right: 10px;
}
.activity-log-btn:hover {
  background: #7c3aed;
}
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
.btn{background:#111;border:1px solid var(--border);border-radius:12px;padding:8px 14px;cursor:pointer;color:#fff;}
.btn.primary{background:var(--blue);color:#fff;border-color:var(--blue-600);}
.btn.success{background:var(--accent);color:#fff;border-color:#16a34a;}
.btn.danger{background:#dc2626;color:#fff;border-color:#b91c1c;}
.btn.warning{background:#f59e0b;color:#fff;border-color:#d97706;}
.btn.purchase{background:var(--purchase);color:#fff;border-color:#059669;}
.btn:active{transform:translateY(1px);}
.card{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:auto;}
table{width:100%;border-collapse:separate;border-spacing:0;color:#f1f5f9;}
thead th{font-size:12px;text-transform:uppercase;color:#9ca3af;background:#1e293b;text-align:left;padding:12px;}
tbody td{padding:14px 12px;border-top:1px solid var(--border);}
tbody tr:hover{background:#1e293b;}
.status{display:inline-flex;align-items:center;gap:8px;background:#065f46;color:#fff;padding:4px 10px;border-radius:999px;font-size:12px;}
.storeLogo{width:40px;height:40px;border-radius:8px;background:#2563eb;display:grid;place-items:center;color:#fff;font-weight:700;object-fit:cover;}
.stockLevel {font-weight:600;font-size:13px;padding:4px 8px;border-radius:6px;display:inline-block;}
.stock-low {background: var(--stock-low);color: white;}
.stock-ok {background: var(--stock-ok);color: white;}
.stock-warning {background: var(--stock-warning);color: white;}
.sold-status {background: var(--sold);color: white;}
.defective-status {background: var(--defective);color: white;}
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
.modal {display:none;position:fixed;top:0;left:0;right:0;bottom:0;background: rgba(0,0,0,0.7);align-items:center;justify-content:center;z-index:1000;padding:10px;}
.modal-content {background:#111;padding:20px;border-radius:10px;width:100%;max-width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 0 10px #000;}
#addProductForm input,#addProductForm select,#addProductForm textarea {width:100%;margin-bottom:10px;padding:8px;border-radius:6px;border:1px solid #333;background:#1f2937;color:#fff;font-size:14px;}
#addProductForm button {padding:10px 14px;border:none;border-radius:6px;cursor:pointer;}
#addProductForm button[type="submit"] {background:var(--blue); color:white;margin-top:10px;}
#addProductForm button#closeModal {background:#374151; color:white;}
#photoPreview {display:block;font-size:14px;color:#f9fafb;padding:6px;background:#1f2937;border-radius:6px;word-break: break-word;margin-bottom:10px;}
#toast {position: fixed; bottom: 20px; right: 20px;background: var(--blue); color: #fff; padding:10px 16px;border-radius:8px; font-size:14px; display:none; z-index:2000;} 
#stock-toast {position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: rgba(17,24,39,0.95); color: #fff; padding:12px 18px; border-radius:8px; font-size:14px; display:none; z-index:3000; max-width:820px; width:calc(100% - 40px); box-shadow:0 8px 30px rgba(0,0,0,0.6);} 
#stock-toast .stock-toast-inner{display:flex;flex-direction:column;gap:10px;max-height:320px;overflow:auto;padding-right:10px}
#stock-toast .stock-section{margin:0;padding:8px;border-radius:6px}
#stock-toast .stock-section-title{font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:8px}
#stock-toast .stock-list{margin:0;padding-left:18px}
#stock-toast .stock-list li{margin-bottom:6px;font-size:13px}
#stock-toast .stock-badge{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:8px}
#stock-toast .stock-badge.out{background:#dc2626}
#stock-toast .stock-badge.low{background:#f59e0b}
#stock-toast .stock-close{position:absolute;top:8px;right:10px;background:transparent;border:0;color:#9ca3af;font-size:18px;cursor:pointer}
#stock-toast .stock-section.out{background:rgba(220,38,38,0.06);border-left:4px solid #dc2626}
#stock-toast .stock-section.low{background:rgba(245,158,11,0.06);border-left:4px solid #f59e0b}
#stock-toast .stock-section.out .stock-section-title{color:#fff}
#stock-toast .stock-section.low .stock-section-title{color:#111} 

/* Serial Numbers Styles */
.serial-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px;
    margin-bottom: 5px;
    background: #1e293b;
    border-radius: 4px;
    border: 1px solid #374151;
}

.remove-serial {
    background: #dc2626;
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

.product-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.product-item:hover {
    background: #1e293b;
    border-color: var(--blue);
}

.product-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.product-stock {
    font-size: 12px;
    color: var(--muted);
}

/* Serial with color item */
.serial-color-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    margin-bottom: 8px;
    background: #1e293b;
    border-radius: 6px;
    border: 1px solid #374151;
}

.serial-color-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
}

.serial-number {
    font-weight: 600;
    color: #f9fafb;
}

.serial-color {
    font-size: 12px;
    color: #9ca3af;
}

.serial-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

/* Two column layout for serial and color input */
.serial-color-input-row {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 10px;
    margin-bottom: 10px;
    align-items: end;
}

/* Stock item styles */
.stock-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    margin-bottom: 8px;
    background: #1e293b;
    border-radius: 6px;
    border: 1px solid #374151;
}

.stock-item-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
}

.stock-serial {
    font-weight: 600;
    color: #f9fafb;
}

.stock-color {
    font-size: 12px;
    color: #9ca3af;
}

.stock-status {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 999px;
    display: inline-block;
    width: fit-content;
}

.status-available { background: #065f46; color: white; }
.status-sold { background: #6d28d9; color: white; }
.status-defective { background: #c2410c; color: white; }

/* Defect notes styles */
.defect-notes {
    font-size: 11px;
    color: #f97316;
    margin-top: 4px;
    font-style: italic;
    background: rgba(249, 115, 22, 0.1);
    padding: 4px 8px;
    border-radius: 4px;
    border-left: 2px solid #f97316;
}

/* Color summary styles */
.color-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.color-summary-item {
    background: #1e293b;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #374151;
    text-align: center;
}

.color-summary-color {
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 5px;
}

.color-summary-count {
    font-size: 14px;
    color: #9ca3af;
}

.color-summary-available {
    color: #22c55e;
    font-weight: 600;
}

.color-summary-sold {
    color: #8b5cf6;
}

.color-summary-defective {
    color: #f97316;
}

/* Product totals styles */
.product-totals {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.product-total-item {
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    border: 1px solid #374151;
}

.product-total-label {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 8px;
    color: #f9fafb;
}

.product-total-count {
    font-size: 24px;
    font-weight: bold;
    color: #f9fafb;
}

/* Checkbox styles */
.checkbox-container {
    display: flex;
    align-items: center;
    gap: 8px;
}

.checkbox-container input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

/* Edit modal styles */
.edit-form-group {
    margin-bottom: 15px;
}

.edit-form-group label {
    display: block;
    margin-bottom: 5px;
    color: #9ca3af;
    font-size: 14px;
}

.edit-form-group input,
.edit-form-group select,
.edit-form-group textarea {
    width: 100%;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #333;
    background: #1f2937;
    color: #fff;
    font-size: 14px;
}

.edit-form-group textarea {
    min-height: 80px;
    resize: vertical;
}

/* Stock History Styles */
.stock-history-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    margin-bottom: 8px;
    background: #1e293b;
    border-radius: 6px;
    border: 1px solid #374151;
    transition: all 0.2s ease;
}

.stock-history-item:hover {
    background: #1f2937;
    border-color: #4b5563;
}

.stock-history-info {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex: 1;
}

.stock-history-action {
    font-weight: 600;
    color: #f9fafb;
    display: flex;
    align-items: center;
    gap: 8px;
}

.stock-history-details {
    font-size: 13px;
    color: #9ca3af;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.stock-history-meta {
    font-size: 12px;
    color: #6b7280;
    display: flex;
    gap: 10px;
}

.stock-history-badge {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 999px;
    display: inline-block;
}

.badge-added { background: #065f46; color: white; }
.badge-sold { background: #6d28d9; color: white; }
.badge-defective { background: #c2410c; color: white; }
.badge-updated { background: #1e40af; color: white; }

.no-history {
    text-align: center;
    padding: 40px;
    color: #6b7280;
    font-style: italic;
}

/* Product column in stock history */
.product-column {
    font-weight: 600;
    color: #f9fafb;
    margin-bottom: 4px;
}

/* Bulk Action Bar Styles */
.bulk-action-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    padding: 12px;
    background: #1e293b;
    border-radius: 8px;
    border: 1px solid #374151;
    align-items: center;
}

.bulk-action-info {
    color: #9ca3af;
    font-size: 14px;
    margin-right: auto;
}

.bulk-select-all {
    background: #374151;
    color: #e5e7eb;
    border: 1px solid #4b5563;
    border-radius: 6px;
    padding: 6px 12px;
    cursor: pointer;
    font-size: 12px;
}

.bulk-select-all:hover {
    background: #4b5563;
}

.stock-item.selected {
    background: #1e40af;
    border-color: #3b82f6;
}

.stock-checkbox {
    margin-right: 10px;
}

/* Purchase Modal Styles */
.purchase-form-group {
    margin-bottom: 15px;
}

.purchase-form-group label {
    display: block;
    margin-bottom: 5px;
    color: #9ca3af;
    font-size: 14px;
}

.purchase-form-group input,
.purchase-form-group select,
.purchase-form-group textarea {
    width: 100%;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #333;
    background: #1f2937;
    color: #fff;
    font-size: 14px;
}

.purchase-details {
    background: #1e293b;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #374151;
    margin-bottom: 15px;
}

.purchase-detail-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.purchase-detail-label {
    color: #9ca3af;
}

.purchase-detail-value {
    color: #f9fafb;
    font-weight: 600;
}

.purchase-summary {
    background: #065f46;
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
    text-align: center;
}

.purchase-summary h4 {
    margin: 0 0 10px 0;
    color: white;
}

.purchase-summary .total-price {
    font-size: 24px;
    font-weight: bold;
    color: white;
}

/* Purchase Cart Styles */
.purchase-cart-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    margin-bottom: 8px;
    background: #1e293b;
    border-radius: 6px;
    border: 1px solid #374151;
}

.cart-item-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
}

.cart-item-name {
    font-weight: 600;
    color: #f9fafb;
}

.cart-item-details {
    font-size: 12px;
    color: #9ca3af;
}

.cart-item-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.quantity-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.quantity-btn {
    background: #374151;
    color: #e5e7eb;
    border: 1px solid #4b5563;
    border-radius: 4px;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.quantity-btn:hover {
    background: #4b5563;
}

.quantity-input {
    width: 50px;
    text-align: center;
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 4px;
    color: #f9fafb;
    padding: 4px;
}

.price-input {
    width: 100px;
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 4px;
    color: #f9fafb;
    padding: 6px;
}

.remove-cart-item {
    background: #dc2626;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 6px 10px;
    cursor: pointer;
    font-size: 12px;
}

.remove-cart-item:hover {
    background: #b91c1c;
}

/* Serial Input Styles */
.serial-input-container {
    margin-top: 10px;
    padding: 10px;
    background: #0f172a;
    border-radius: 6px;
    border: 1px solid #374151;
}

.serial-input-row {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
    align-items: center;
}

.serial-input {
    flex: 1;
    padding: 8px;
    border-radius: 4px;
    border: 1px solid #374151;
    background: #1f2937;
    color: #fff;
    font-size: 12px;
}

.serial-error {
    color: #dc2626;
    font-size: 11px;
    margin-top: 5px;
    display: none;
}

/* Enhanced Receipt Styles */
.receipt-container {
    background: white;
    color: black;
    padding: 25px;
    border-radius: 8px;
    max-width: 350px;
    margin: 0 auto;
    font-family: 'Courier New', monospace;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: 2px solid #000;
}

.receipt-header {
    text-align: center;
    border-bottom: 3px double #000;
    padding-bottom: 15px;
    margin-bottom: 15px;
}

.receipt-header h2 {
    margin: 0 0 8px 0;
    font-size: 20px;
    font-weight: bold;
    text-transform: uppercase;
}

.receipt-header p {
    margin: 3px 0;
    font-size: 12px;
}

.receipt-items {
    margin-bottom: 15px;
}

.receipt-item {
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px dashed #ccc;
}

.receipt-item:last-child {
    border-bottom: none;
}

.receipt-item-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 3px;
}

.receipt-item-name {
    font-weight: bold;
    font-size: 13px;
}

.receipt-item-price {
    font-size: 13px;
}

.receipt-item-quantity {
    font-size: 11px;
    color: #666;
}

.receipt-item-serial {
    font-size: 10px;
    color: #444;
    font-style: italic;
    margin-top: 2px;
}

.receipt-total {
    border-top: 3px double #000;
    padding-top: 15px;
    margin-top: 15px;
    font-weight: bold;
}

.receipt-total-row {
    display:flex;justify-content:space-between;font-size:14px;margin-bottom:5px;
}

.receipt-footer {
    text-align: center;
    margin-top: 20px;
    font-size: 10px;
    color: #666;
    border-top: 1px solid #ccc;
    padding-top: 10px;
}

/* Print Styles for Receipt */
@media print {
    body * {
        visibility: hidden;
    }
    
    .receipt-container, .receipt-container * {
        visibility: visible;
    }
    
    .receipt-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        max-width: 100%;
        box-shadow: none;
        border: none;
        margin: 0;
        padding: 20px;
    }
    
    .no-print {
        display: none !important;
    }
    
    @page {
        margin: 0.5cm;
        size: auto;
    }
}

/* Download button styles */
.btn.download {
    background: #059669;
    color: white;
    border-color: #047857;
}

.btn.download:hover {
    background: #047857;
}

/* Receipt modal specific styles to prevent auto-close */
.receipt-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.8);
    align-items: center;
    justify-content: center;
    z-index: 2000;
    padding: 20px;
}

.receipt-modal .modal-content {
    background: white;
    color: black;
    max-width: 500px;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.receipt-modal .btn {
    margin: 5px;
}

/* Transactions Modal Styles */
.transactions-modal .modal-content {
    max-width: 95%;
    max-height: 90vh;
}

.transaction-item {
    background: #1e293b;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #374151;
    margin-bottom: 10px;
}

.transaction-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #374151;
}

.transaction-id {
    font-weight: bold;
    color: #f9fafb;
}

.transaction-date {
    color: #9ca3af;
    font-size: 14px;
}

.transaction-items {
    margin-bottom: 10px;
}

.transaction-item-row {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px dashed #374151;
}

.transaction-total {
    font-weight: bold;
    font-size: 16px;
    color: #22c55e;
    text-align: right;
    padding-top: 10px;
    border-top: 2px solid #374151;
}

/* Notes field styles */
.notes-field {
    margin-top: 15px;
}

.notes-field label {
    display: block;
    margin-bottom: 5px;
    color: #9ca3af;
    font-size: 14px;
}

.notes-field textarea {
    width: 100%;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #333;
    background: #1f2937;
    color: #fff;
    font-size: 14px;
    min-height: 80px;
    resize: vertical;
}

.notes-preview {
    background: #1e293b;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #374151;
    margin-top: 10px;
    font-size: 14px;
    color: #f9fafb;
}

.notes-preview.empty {
    color: #6b7280;
    font-style: italic;
}

/* Product not found message */
.product-not-found {
    text-align: center;
    padding: 40px;
    color: #9ca3af;
    font-size: 16px;
    grid-column: 1 / -1;
}

.product-not-found i {
    font-size: 48px;
    margin-bottom: 15px;
    display: block;
    color: #6b7280;
}

/* Modal product not found */
.modal-product-not-found {
    text-align: center;
    padding: 40px;
    color: #9ca3af;
    font-size: 14px;
    width: 100%;
}

.modal-product-not-found i {
    font-size: 36px;
    margin-bottom: 10px;
    display: block;
    color: #6b7280;
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
  <a href="activity-log-admin.php">
  <button class="activity-log-btn" id="activityLogBtn">Activity Log</button>
</a>
  <button class="stock-history-btn" id="globalStockHistoryBtn">Stock History</button>
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
  <a href="defect items management-admin.php" class="chipRow">
    <div class="chipLabel"><span class="dot"></span><span>Defect Items</span></div>
  </a>
  <a href="feedback-admin.php" class="chipRow">
    <div class="chipLabel"><span class="dot"></span><span>Feedback</span></div>
  </a>
  <!-- ADDED USER MANAGEMENT BUTTON -->
  <a href="user-management-admin.php" class="chipRow">
    <div class="chipLabel"><span class="dot"></span><span>User Management</span></div>
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
  <div class="filters">
    <div class="filter">
      <label>Category</label>
      <select id="categoryFilter">
        <option value="all">All Categories</option>
        <option value="tech accessories">Tech Accessories</option>
        <option value="phone">Phone</option>
        <option value="laptop">Laptop</option>
      </select>
    </div>
    <div class="filter">
      <label>Available</label>
      <div class="yn">
        <button id="inStockBtn">In Stock</button>
        <button id="outStockBtn">Out of Stock</button>
        <button id="lowStockBtn">Low Stock</button>
        <button id="soldBtn">Sold</button>
        <button id="defectiveBtn">Defective</button>
        <button id="allStockBtn" class="active">Show All</button>
      </div>
    </div>
  </div>
</aside>
<main>
<div class="toolbar">
  <div class="search">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input id="searchInput" placeholder="Search products…" />
  </div>
  <button class="btn primary" id="openModal">+ New Product</button>
  <button class="btn primary" id="addStockBtn">+ Add Stock</button>
  <button class="btn danger" id="markDefectiveBtn">Mark Defective</button>
  <button class="btn purchase" id="purchaseBtn">Purchased</button>
  <button class="btn success" id="viewTransactionsBtn">View Transactions</button>
  <button class="btn danger" id="deleteProductsBtn">Delete Products</button>
</div>
<div class="card">
<table>
<thead>
<tr>
  <th><input type="checkbox" id="selectAllCheckbox"></th>
  <th>Product Name</th>
  <th>Product Photo</th>
  <th>Added Date</th>
  <th>Status</th>
  <th>Product Info</th>
  <th>Category</th>
  <th>Price</th>
  <th>Total Stock</th>
  <th>Actions</th>
</tr>
</thead>
<tbody id="productsTableBody">
<?php foreach ($products as $product): ?>
<tr>
  <td><input type="checkbox" class="product-checkbox" data-id="<?php echo $product['id']; ?>" 
             data-sold-count="<?php echo $product['sold_count']; ?>" 
             data-defective-count="<?php echo $product['defective_count']; ?>"></td>
  <td data-th="Product Name">
    <strong><?php echo htmlspecialchars($product['name']); ?></strong>
  </td>
  <td data-th="Product Photo">
    <?php if (!empty($product['photo'])): ?>
      <img src="<?php echo htmlspecialchars($product['photo']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width:40px;height:40px;border-radius:8px;object-fit:cover;">
    <?php else: ?>
      <div class="storeLogo"><?php echo strtoupper(substr($product['name'], 0, 1)); ?></div>
    <?php endif; ?>
  </td>
  <td data-th="Added Date"><?php echo htmlspecialchars($product['date']); ?></td>
  <td data-th="Status"><span class="status <?php echo getStatusClass($product['status']); ?>"><?php echo htmlspecialchars($product['status']); ?></span></td>
  <td data-th="Product Info"><?php echo htmlspecialchars($product['items']); ?></td>
  <td data-th="Category"><?php echo htmlspecialchars($product['category']); ?></td>
  <td data-th="Price">₱<?php echo number_format($product['price'], 2); ?></td>
  <td data-th="Total Stock">
    <span class="stockLevel <?php echo getStockLevel($product['available_stock'], $product['critical_stock'] ?? 10); ?>">
      <?php echo $product['available_stock']; ?> Available
      <?php if (($product['critical_stock'] ?? 10) > 0): ?>
        <br><small style="font-size:10px;color:#9ca3af;">Critical: <?php echo $product['critical_stock'] ?? 10; ?></small>
      <?php endif; ?>
    </span>
  </td>
  <td data-th="Actions">
    <button class="btn primary view-stock-btn" data-product-id="<?php echo $product['id']; ?>" data-product-name="<?php echo htmlspecialchars($product['name']); ?>">View Stock</button>
    <button class="btn warning edit-product-btn" data-product-id="<?php echo $product['id']; ?>">Edit</button>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</main>
</div>
</div>

<!-- Add Product Modal -->
<div id="addModal" class="modal">
<form id="addProductForm" enctype="multipart/form-data" method="POST" action="add-product.php">
<div class="modal-content">
<h3 id="modalTitle" style="margin-top:0;">Add New Product</h3>

<!-- PRODUCT DETAILS -->
<input type="text" name="name" placeholder="Product Name (e.g., iPhone 13)" required id="productNameInput">
<input type="text" name="items" placeholder="Specs/Description (e.g., 128GB, 5G)" required>
<input type="number" name="price" placeholder="Price (₱)" step="0.01" required>
<select name="category" required>
  <option value="">Select Category</option>
  <option value="phone">Phone</option>
  <option value="laptop">Laptop</option>
  <option value="tablet">Tablet</option>
  <option value="tech accessories">Tech Accessories</option>
  <option value="watch">Smart Watch</option>
</select>
<!-- ADDED: Critical Stock Level -->
<input type="number" name="critical_stock" placeholder="Critical Stock Level (default: 10)" min="0" step="1" value="10">
<input type="date" name="date" required>
<select name="status" required>
  <option value="Available">Available</option>
  <option value="New">New Arrival</option>
  <option value="Limited">Limited Stock</option>
  <option value="Discontinued">Discontinued</option>
</select>

<label style="font-size:13px;color:#9ca3af;">Product Photo (Optional)</label>
<input type="file" name="photo" id="photoInput" accept="image/*">
<div id="photoPreview"></div>
<div style="display:flex; justify-content:space-between; gap:10px;">
  <button type="submit" style="flex:1;">Save Product</button>
  <button type="button" id="closeModal" style="flex:1;">Cancel</button>
</div>
</div>
</form>
</div>

<!-- Edit Product Modal -->
<div id="editProductModal" class="modal">
    <div class="modal-content">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:10px;">
            <h3 style="margin:0;">Edit Product</h3>
            <button type="button" class="btn" id="closeEditProductModal">Close</button>
        </div>
        <form id="editProductForm" enctype="multipart/form-data" method="POST" action="update-product.php">
            <div style="padding:20px;">
                <input type="hidden" id="editProductId" name="id">
                
                <div class="edit-form-group">
                    <label>Product Name</label>
                    <input type="text" id="editProductName" name="name" required>
                </div>
                
                <div class="edit-form-group">
                    <label>Specs/Description</label>
                    <textarea id="editProductItems" name="items" required></textarea>
                </div>
                
                <div class="edit-form-group">
                    <label>Price (₱)</label>
                    <input type="number" id="editProductPrice" name="price" step="0.01" required>
                </div>
                
                <div class="edit-form-group">
                    <label>Category</label>
                    <select id="editProductCategory" name="category" required>
                        <option value="phone">Phone</option>
                        <option value="laptop">Laptop</option>
                        <option value="tablet">Tablet</option>
                        <option value="tech accessories">Tech Accessories</option>
                        <option value="watch">Smart Watch</option>
                    </select>
                </div>
                
                <!-- ADDED: Critical Stock Level -->
                <div class="edit-form-group">
                    <label>Critical Stock Level</label>
                    <input type="number" id="editProductCriticalStock" name="critical_stock" min="0" step="1" required>
                    <small style="color:#9ca3af;font-size:12px;">When available stock reaches this number, it will be marked as "Low Stock"</small>
                </div>
                
                <div class="edit-form-group">
                    <label>Added Date</label>
                    <input type="date" id="editProductDate" name="date" required>
                </div>
                
                <div class="edit-form-group">
                    <label>Status</label>
                    <select id="editProductStatus" name="status" required>
                        <option value="Available">Available</option>
                        <option value="New">New Arrival</option>
                        <option value="Limited">Limited Stock</option>
                        <option value="Discontinued">Discontinued</option>
                    </select>
                </div>
                
                <div class="edit-form-group">
                    <label>Product Photo (Optional)</label>
                    <input type="file" name="photo" accept="image/*">
                    <div id="editPhotoPreview" style="margin-top:10px;"></div>
                </div>
                
                <div style="display:flex;gap:12px;margin-top:20px;">
                    <button type="button" class="btn" id="cancelEditProduct" style="flex:1;">Cancel</button>
                    <button type="submit" class="btn primary" style="flex:1;">Update Product</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Stock Item Modal -->
<div id="editStockModal" class="modal">
    <div class="modal-content">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:10px;">
            <h3 style="margin:0;">Edit Stock Item</h3>
            <button class="btn" id="closeEditStockModal">Close</button>
        </div>
        <form id="editStockForm" method="POST" action="update-stock-item.php">
            <div style="padding:20px;">
                <input type="hidden" id="editStockId" name="id">
                
                <div class="edit-form-group">
                    <label>Serial Number</label>
                    <input type="text" id="editStockSerial" name="serial_number" required>
                </div>
                
                <div class="edit-form-group">
                    <label>Color</label>
                    <input type="text" id="editStockColor" name="color" required>
                </div>
                
                <div class="edit-form-group">
                    <label>Status</label>
                    <select id="editStockStatus" name="status" required>
                        <option value="Available">Available</option>
                        <option value="Sold">Sold</option>
                        <option value="Defective">Defective</option>
                    </select>
                </div>
                
                <!-- ADDED DEFECT NOTES FIELD -->
                <div class="edit-form-group">
                    <label>Defect Notes (if defective)</label>
                    <textarea id="editStockDefectNotes" name="defect_notes" placeholder="Enter details about the defect (e.g., Screen damage, Battery issue, Water damage, etc.)" 
                              style="width:100%;padding:10px;border-radius:6px;border:1px solid #333;background:#1f2937;color:#fff;font-size:14px;min-height:80px;resize:vertical;"></textarea>
                </div>
                
                <div style="display:flex;gap:12px;margin-top:20px;">
                    <button type="button" class="btn" id="cancelEditStock" style="flex:1;">Cancel</button>
                    <button type="submit" class="btn primary" style="flex:1;">Update Stock Item</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Add Stock Modal -->
<div id="addStockModal" class="modal">
    <div class="modal-content">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:10px;">
            <h3 style="margin:0;">Add Stock with Serial Numbers</h3>
            <button class="btn" id="closeAddStockModal">Close</button>
        </div>
        <div style="padding:20px;">
            <!-- Product Selection -->
            <div class="search" style="margin-bottom:15px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input id="stockSearchInput" placeholder="Search products by name..." />
            </div>
            
            <div id="productSelection" style="max-height:300px;overflow-y:auto;margin-bottom:20px;border:1px solid var(--border);border-radius:8px;padding:10px;">
                <?php foreach ($products as $product): ?>
                <div class="product-item" data-product-id="<?php echo $product['id']; ?>">
                    <div class="product-info">
                        <div class="storeLogo" style="width:40px;height:40px;">
                            <?php echo strtoupper(substr($product['name'], 0, 1)); ?>
                        </div>
                        <div>
                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                            <div class="product-stock">
                                Available: <?php echo $product['available_stock']; ?>
                                <?php if (($product['critical_stock'] ?? 10) > 0): ?>
                                <br><small>Critical: <?php echo $product['critical_stock'] ?? 10; ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <button class="btn primary select-product-btn" data-product-id="<?php echo $product['id']; ?>" data-product-name="<?php echo htmlspecialchars($product['name']); ?>">Add Stock</button>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div id="serialInputStep" style="display:none;">
                <h4 style="color:#f9fafb;margin-bottom:15px;">Add Stock Items with Serial Numbers</h4>
                
                <!-- Selected Product Info -->
                <div id="selectedProductInfo" style="background:#1e293b;padding:15px;border-radius:8px;border:1px solid #333;margin-bottom:15px;">
                    <h5 style="margin:0 0 10px 0;color:#f9fafb;">Adding stock to: <span id="selectedProductName"></span></h5>
                </div>
                
                <!-- Serial and Color Input -->
                <div style="background:#1e293b;padding:15px;border-radius:8px;border:1px solid #333;margin-bottom:15px;">
                    <label style="font-size:14px;color:#f9fafb;margin-bottom:10px;display:block;font-weight:600;">Add New Stock Items</label>
                    
                    <div class="serial-color-input-row">
                        <div>
                            <label style="font-size:12px;color:#9ca3af;margin-bottom:4px;display:block;">Serial Number *</label>
                            <input 
                                type="text" 
                                id="addSingleSerialInput" 
                                placeholder="e.g., SN123456789"
                                style="width:100%;padding:10px;border-radius:6px;border:1px solid #333;background:#1f2937;color:#fff;font-size:14px;"
                            >
                        </div>
                        <div>
                            <label style="font-size:12px;color:#9ca3af;margin-bottom:4px;display:block;">
                                Color *
                            </label>
                            <input 
                                type="text" 
                                id="addSerialColorInput" 
                                placeholder="e.g., Black, White, Red"
                                style="width:100%;padding:10px;border-radius:6px;border:1px solid #333;background:#1f2937;color:#fff;font-size:14px;"
                            >
                        </div>
                        <div>
                            <label style="font-size:12px;color:#9ca3af;margin-bottom:4px;display:block;visibility:hidden;">Action</label>
                            <button type="button" class="btn primary" id="addSingleSerialBtn" style="padding:10px 16px;white-space:nowrap;">Add Item</button>
                        </div>
                    </div>
                    
                    <div id="addSerialList" style="max-height:200px;overflow-y:auto;background:#0f172a;border-radius:6px;padding:10px;border:1px solid #333;min-height:60px;margin-top:10px;">
                        <div style="color:#6b7280;text-align:center;padding:20px;">No items added yet. Add serial numbers and colors above.</div>
                    </div>
                </div>
                
                <div style="display:flex;gap:12px;margin-top:20px;">
                    <button class="btn" id="backToSelection" type="button" style="flex:1;padding:12px 20px;">← Back to Products</button>
                    <button class="btn primary" id="confirmAddStock" style="flex:1;padding:12px 20px;">Add Stock Items</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Stock Modal -->
<div id="viewStockModal" class="modal">
    <div class="modal-content">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:10px;">
            <h3 style="margin:0;" id="viewStockTitle">Stock Details</h3>
            <button class="btn" id="closeViewStockModal">Close</button>
        </div>
        <div style="padding:20px;">
            <!-- PRODUCT TOTALS SECTION -->
            <div id="productTotals" style="margin-bottom:20px;">
                <h4 style="color:#f9fafb;margin-bottom:15px;">Product Totals</h4>
                <div class="product-totals" id="productTotalsGrid">
                    <!-- Product totals will be loaded here -->
                </div>
            </div>
            
            <!-- COLOR SUMMARY SECTION -->
            <div id="colorSummary" style="margin-bottom:20px;">
                <h4 style="color:#f9fafb;margin-bottom:15px;">Color Summary</h4>
                <div class="color-summary" id="colorSummaryGrid">
                    <!-- Color summary will be loaded here -->
                </div>
            </div>
            
            <!-- BULK ACTION BAR -->
            <div class="bulk-action-bar" id="bulkActionBar" style="display:none;">
                <div class="bulk-action-info" id="bulkActionInfo">0 items selected</div>
                <button class="bulk-select-all" id="bulkSelectAll">Select All</button>
                <button class="btn purchase" id="bulkMarkSold">Mark Selected as Sold</button>
                <button class="btn danger" id="bulkMarkDefective">Mark Selected as Defective</button>
                <button class="btn success" id="bulkMarkAvailable">Mark Selected as Available</button>
            </div>
            
            <div class="search" style="margin-bottom:15px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input id="viewStockSearchInput" placeholder="Search serial numbers..." />
            </div>
            
            <div id="stockItemsList" style="max-height:400px;overflow-y:auto;">
                <!-- Stock items will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Defective Products Modal -->
<div id="defectiveModal" class="modal">
    <div class="modal-content">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:10px;">
            <h3 style="margin:0;">Mark Items as Defective</h3>
            <button class="btn" id="closeDefectiveModal">Close</button>
        </div>
        <div style="padding:20px;">
            <!-- Product Selection -->
            <div class="search" style="margin-bottom:15px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input id="defectiveSearchInput" placeholder="Search products by name..." />
            </div>
            
            <div id="defectiveProductList" style="max-height:300px;overflow-y:auto;margin-bottom:20px;border:1px solid var(--border);border-radius:8px;padding:10px;">
                <?php foreach ($products as $product): ?>
                <?php if ($product['available_stock'] > 0): ?>
                <div class="product-item" data-product-id="<?php echo $product['id']; ?>" data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                    <div class="product-info">
                        <div class="storeLogo" style="width:40px;height:40px;">
                            <?php echo strtoupper(substr($product['name'], 0, 1)); ?>
                        </div>
                        <div>
                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                            <div class="product-stock">
                                Available: <?php echo $product['available_stock']; ?>
                            </div>
                        </div>
                    </div>
                    <button class="btn danger select-defective-product" data-product-id="<?php echo $product['id']; ?>" data-product-name="<?php echo htmlspecialchars($product['name']); ?>">Mark Defective</button>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <!-- Serial Number Input -->
            <div id="defectiveSerialStep" style="display:none;">
                <h4 style="color:#f9fafb;margin-bottom:15px;">Mark Defective Items</h4>
                
                <!-- Selected Product Info -->
                <div id="selectedDefectiveProductInfo" style="background:#1e293b;padding:15px;border-radius:8px;border:1px solid #333;margin-bottom:15px;">
                    <h5 style="margin:0 0 10px 0;color:#f9fafb;">Marking defective items for: <span id="selectedDefectiveProductName"></span></h5>
                    <div style="color:#9ca3af;font-size:12px;">
                        Available Stock: <span id="defectiveAvailableStock">0</span>
                    </div>
                </div>
                
                <!-- Serial Input Section -->
                <div style="background:#1e293b;padding:15px;border-radius:8px;border:1px solid #333;margin-bottom:15px;">
                    <label style="font-size:14px;color:#f9fafb;margin-bottom:10px;display:block;font-weight:600;">Enter Serial Numbers</label>
                    
                    <div style="display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:10px;">
                        <input 
                            type="text" 
                            id="defectiveSerialInput" 
                            placeholder="Enter serial number (e.g., SN123456789)"
                            style="width:100%;padding:10px;border-radius:6px;border:1px solid #333;background:#1f2937;color:#fff;font-size:14px;"
                        >
                        <button type="button" class="btn danger" id="addDefectiveSerialBtn" style="padding:10px 16px;white-space:nowrap;">Add</button>
                    </div>
                    
                    <!-- NOTES FIELD -->
                    <div class="notes-field">
                        <label style="color:#9ca3af;font-size:12px;">Defect Notes (Required)</label>
                        <textarea 
                            id="defectiveNotes" 
                            placeholder="Enter notes about the defect (e.g., Screen damage, Battery issue, Water damage, Cosmetic damage, etc.)"
                            style="width:100%;padding:10px;border-radius:6px;border:1px solid #333;background:#1f2937;color:#fff;font-size:14px;min-height:80px;resize:vertical;"
                            required
                        ></textarea>
                    </div>
                    
                    <div id="defectiveSerialList" style="max-height:200px;overflow-y:auto;background:#0f172a;border-radius:6px;padding:10px;border:1px solid #333;min-height:60px;margin-top:10px;">
                        <div style="color:#6b7280;text-align:center;padding:20px;">No serial numbers added yet</div>
                    </div>
                </div>
                
                <div style="display:flex;gap:12px;margin-top:20px;">
                    <button class="btn" id="backToDefectiveSelection" type="button" style="flex:1;padding:12px 20px;">← Back to Products</button>
                    <button class="btn danger" id="confirmMarkDefective" style="flex:1;padding:12px 20px;">Mark as Defective</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Purchased Modal -->
<div id="purchaseModal" class="modal">
    <div class="modal-content">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:10px;">
            <h3 style="margin:0;" id="purchaseTitle">Purchased Products</h3>
            <button class="btn" id="closePurchaseModal">Close</button>
        </div>
        <div style="padding:20px;">
            <!-- Product Selection -->
            <div class="search" style="margin-bottom:15px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input id="purchaseSearchInput" placeholder="Search products by name..." />
            </div>
            
            <div id="purchaseProductList" style="max-height:300px;overflow-y:auto;margin-bottom:20px;border:1px solid var(--border);border-radius:8px;padding:10px;">
                <?php foreach ($products as $product): ?>
                <?php if ($product['available_stock'] > 0): ?>
                <div class="product-item" data-product-id="<?php echo $product['id']; ?>" data-product-name="<?php echo htmlspecialchars($product['name']); ?>" data-product-price="<?php echo $product['price']; ?>">
                    <div class="product-info">
                        <input type="checkbox" class="purchase-product-checkbox" data-product-id="<?php echo $product['id']; ?>" 
                               data-product-name="<?php echo htmlspecialchars($product['name']); ?>" 
                               data-product-price="<?php echo $product['price']; ?>">
                        <div class="storeLogo" style="width:40px;height:40px;">
                            <?php echo strtoupper(substr($product['name'], 0, 1)); ?>
                        </div>
                        <div>
                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                            <div class="product-stock">
                                Available: <?php echo $product['available_stock']; ?> | Price: ₱<?php echo number_format($product['price'], 2); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <!-- Shopping Cart with Serial Input -->
            <div id="purchaseCart" style="display:none;">
                <h4 style="color:#f9fafb;margin-bottom:15px;">Shopping Cart - Enter Serial Numbers</h4>
                <div id="cartItems" style="max-height:300px;overflow-y:auto;margin-bottom:15px;">
                    <!-- Cart items with serial input will be loaded here -->
                </div>
                
                <div class="purchase-summary">
                    <h4>Total Amount</h4>
                    <div class="total-price">₱<span id="purchaseTotalAmount">0.00</span></div>
                    <div style="font-size:12px;color:#d1fae5;margin-top:5px;">
                        <span id="purchaseItemCount">0</span> item(s) in cart
                    </div>
                </div>
                
                <div style="display:flex;gap:12px;margin-top:20px;">
                    <button class="btn" id="backToProductSelection" type="button" style="flex:1;padding:12px 20px;">← Back to Products</button>
                    <button class="btn purchase" id="confirmPurchase" style="flex:1;padding:12px 20px;">Complete Purchase</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div id="receiptModal" class="modal receipt-modal">
    <div class="modal-content">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid #ccc;padding-bottom:10px;">
            <h3 style="margin:0;color:#000;">Purchase Receipt</h3>
            <button class="btn" id="closeReceiptModal" style="background:#dc2626;color:white;">Close</button>
        </div>
        <div style="padding:20px;">
            <div id="receiptContent" class="receipt-container">
                <!-- Receipt content will be loaded here -->
            </div>
            <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;">
                <button class="btn" id="printReceipt" style="flex:1;background:#374151;color:white;">Print Receipt</button>
                <button class="btn primary" id="downloadReceipt" style="flex:1;">Download Receipt</button>
                <button class="btn success" id="newPurchase" style="flex:1;background:#059669;color:white;">New Purchase</button>
            </div>
        </div>
    </div>
</div>

<!-- Stock History Modal -->
<div id="stockHistoryModal" class="modal">
    <div class="modal-content">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:10px;">
            <h3 style="margin:0;" id="stockHistoryTitle">Stock History</h3>
            <button class="btn" id="closeStockHistoryModal">Close</button>
        </div>
        <div style="padding:20px;">
            <div class="search" style="margin-bottom:15px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input id="stockHistorySearchInput" placeholder="Search by product, serial, color, or user..." />
            </div>
            
            <div style="display:flex; gap:10px; margin-bottom:15px;">
                <select id="stockHistoryFilter" style="flex:1;">
                    <option value="all">All Changes</option>
                    <option value="added">Stock Added</option>
                    <option value="sold">Stock Sold</option>
                    <option value="defective">Marked Defective</option>
                    <option value="updated">Stock Updated</option>
                </select>
                <select id="stockHistoryTimeframe" style="flex:1;">
                    <option value="all">All Time</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                </select>
            </div>
            
            <div id="stockHistoryList" style="max-height:400px;overflow-y:auto;">
                <!-- Stock history will be loaded here -->
            </div>
            
            <div style="display:flex; justify-content:space-between; margin-top:15px; padding-top:15px; border-top:1px solid var(--border);">
                <button class="btn" id="exportStockHistory">Export to CSV</button>
                <div style="color:#9ca3af; font-size:14px;">
                    Total Records: <span id="historyCount">0</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transactions Modal -->
<div id="transactionsModal" class="modal transactions-modal">
    <div class="modal-content">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:10px;">
            <h3 style="margin:0;">Purchase Transactions</h3>
            <button class="btn" id="closeTransactionsModal">Close</button>
        </div>
        <div style="padding:20px;">
            <div class="search" style="margin-bottom:15px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input id="transactionsSearchInput" placeholder="Search transactions..." />
            </div>
            
            <div id="transactionsList" style="max-height:400px;overflow-y:auto;">
                <!-- Transactions will be loaded here -->
            </div>
        </div>
    </div>
</div>

<div id="toast"></div>

<!-- Stock-specific toast shown at top for low/out-of-stock alerts -->
<div id="stock-toast" aria-live="polite" aria-atomic="true"></div> 

<script>
// ====================== GLOBAL VARIABLES ======================
let data = <?php echo json_encode($products); ?>;
let selectedProduct = null;
let addSerialNumbers = [];
let currentViewProductId = null;
let selectedStockItems = new Set();
let purchaseCart = [];
let currentPurchase = null;
let defectiveSerials = [];
let selectedDefectiveProduct = null;

// ====================== UTILITY FUNCTIONS ======================
function showToast(msg, color = 'var(--blue)') {
    const t = document.getElementById('toast');
    if (t) {
        t.textContent = msg;
        t.style.background = color;
        t.style.display = 'block';
        setTimeout(() => t.style.display = 'none', 3000);
    }
    console.log('Toast:', msg);
}

// Display stock alerts on page load: orange for low stock, red for out-of-stock
function showStockToast(msg, color = 'var(--blue)', duration = 3000) {
    const t = document.getElementById('stock-toast');
    if (t) {
        t.textContent = msg;
        t.style.background = color;
        // ensure readable text on orange
        if (color === '#f59e0b' || color.toLowerCase() === 'var(--stock-warning)') t.style.color = '#111';
        else t.style.color = '#fff';
        t.style.display = 'block';
        setTimeout(() => { if (t.style.display === 'block') t.style.display = 'none'; }, duration);
    }
}

// Show a full-color toast containing a list of products for a given type (out or low)
function showStockFullToast(items, type, duration = 8000) {
    const t = document.getElementById('stock-toast');
    if (!t) return;

    // Clear any previous hide timer
    if (t._hideTimer) { clearTimeout(t._hideTimer); t._hideTimer = null; }

    t.innerHTML = '';
    const inner = document.createElement('div');
    inner.className = 'stock-toast-inner';

    const section = document.createElement('div');
    section.className = 'stock-section ' + (type === 'out' ? 'out' : 'low');

    const title = document.createElement('div'); title.className = 'stock-section-title';
    const badge = document.createElement('span'); badge.className = 'stock-badge ' + (type === 'out' ? 'out' : 'low');
    title.appendChild(badge);
    title.appendChild(document.createTextNode(type === 'out' ? `Out of stock (${items.length})` : `Low stock (${items.length})`));
    section.appendChild(title);

    const ul = document.createElement('ul'); ul.className = 'stock-list';
    items.forEach(p => { const li = document.createElement('li'); li.textContent = (type === 'low') ? `${p.name} (${p.available_stock} left)` : p.name; ul.appendChild(li); });
    section.appendChild(ul);

    inner.appendChild(section);
    t.appendChild(inner);

    const close = document.createElement('button');
    close.className = 'stock-close';
    close.innerHTML = '&times;';
    close.onclick = () => { t.style.display = 'none'; if (t._hideTimer) { clearTimeout(t._hideTimer); t._hideTimer = null; } };
    t.appendChild(close);

    // Full toast color per type
    if (type === 'out') {
        t.style.background = '#dc2626';
        t.style.color = '#fff';
        close.style.color = '#fff';
    } else {
        t.style.background = '#f59e0b';
        t.style.color = '#111';
        close.style.color = '#111';
    }

    t.style.display = 'block';
    t._hideTimer = setTimeout(() => { t.style.display = 'none'; t._hideTimer = null; }, duration);
}

function displayStockAlerts() {
    const out = [];
    const low = [];
    (data || []).forEach(p => {
        const available = parseInt(p.available_stock || 0, 10);
        const critical = parseInt(p.critical_stock || 10, 10);
        if (available === 0) out.push(p);
        else if (available > 0 && available <= critical) low.push(p);
    });

    if (out.length === 0 && low.length === 0) {
        const t = document.getElementById('stock-toast'); if (t) t.style.display = 'none';
        return;
    }

    // Show Out-of-Stock toast (red) first, then Low-stock toast (orange) if both exist
    if (out.length > 0) {
        showStockFullToast(out, 'out', 8000);
    }
    if (low.length > 0) {
        // If both exist, show low after out finishes with small gap
        if (out.length > 0) {
            setTimeout(() => showStockFullToast(low, 'low', 8000), 8200);
        } else {
            showStockFullToast(low, 'low', 8000);
        }
    }
}

// Small delay to allow the page to finish rendering
setTimeout(displayStockAlerts, 500);

// ====================== FIXED SEARCH FUNCTIONALITY ======================

// MAIN PAGE SEARCH
function filterProducts() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
    const category = document.getElementById('categoryFilter').value;
    
    let stockFilter = 'all';
    if (document.getElementById('inStockBtn')?.classList.contains('active')) stockFilter = 'inStock';
    if (document.getElementById('outStockBtn')?.classList.contains('active')) stockFilter = 'outStock';
    if (document.getElementById('lowStockBtn')?.classList.contains('active')) stockFilter = 'lowStock';
    if (document.getElementById('soldBtn')?.classList.contains('active')) stockFilter = 'sold';
    if (document.getElementById('defectiveBtn')?.classList.contains('active')) stockFilter = 'defective';

    const tbody = document.getElementById('productsTableBody');
    let foundProducts = false;
    
    // Get all product rows
    const rows = tbody.querySelectorAll('tr');
    
    rows.forEach(row => {
        if (row.classList.contains('product-not-found-row')) return;
        
        const productName = row.querySelector('td:nth-child(2) strong')?.textContent.toLowerCase() || '';
        const productCategory = row.querySelector('td:nth-child(7)')?.textContent.toLowerCase() || '';
        const stockLevel = row.querySelector('.stockLevel')?.textContent || '';
        const availableStock = parseInt(stockLevel.match(/\d+/)?.[0]) || 0;
        const criticalStock = parseInt(row.querySelector('.stockLevel small')?.textContent?.replace('Critical:', '')?.trim() || '10');
        const checkbox = row.querySelector('.product-checkbox');
        const soldCount = parseInt(checkbox?.dataset.soldCount || 0);
        const defectiveCount = parseInt(checkbox?.dataset.defectiveCount || 0);
        
        let showRow = true;
        
        // Apply search filter
        if (searchTerm) {
            if (!productName.includes(searchTerm)) {
                showRow = false;
            } else {
                foundProducts = true;
            }
        }
        
        // Apply category filter
        if (category !== 'all' && productCategory !== category.toLowerCase()) {
            showRow = false;
        }
        
        // Apply stock status filter
        switch(stockFilter) {
            case 'inStock':
                // Show all items that have any available stock (including low stock); hide only when none available
                if (availableStock === 0) showRow = false;
                break;
            case 'outStock':
                if (availableStock > 0) showRow = false;
                break;
            case 'lowStock':
                if (availableStock === 0 || availableStock > criticalStock) showRow = false;
                break;
            case 'sold':
                if (soldCount === 0) showRow = false;
                break;
            case 'defective':
                if (defectiveCount === 0) showRow = false;
                break;
        }
        
        row.style.display = showRow ? '' : 'none';
    });
    
    // Show "Product not found" message if search term is provided and no products found
    const existingNotFound = tbody.querySelector('.product-not-found-row');
    if (existingNotFound) {
        existingNotFound.remove();
    }
    
    if (searchTerm && !foundProducts) {
        const notFoundRow = document.createElement('tr');
        notFoundRow.className = 'product-not-found-row';
        notFoundRow.innerHTML = `
            <td colspan="10">
                <div class="product-not-found">
                    <div>🔍</div>
                    <h3>Product Not Found</h3>
                    <p>No products found matching "<strong>${searchTerm}</strong>"</p>
                    <p style="font-size:14px;margin-top:10px;">Try checking your spelling or search for a different product</p>
                </div>
            </td>
        `;
        tbody.appendChild(notFoundRow);
    }
}

// ADD STOCK MODAL SEARCH
function filterAddStockProducts() {
    const searchTerm = document.getElementById('stockSearchInput').value.toLowerCase().trim();
    const productSelection = document.getElementById('productSelection');
    const productItems = productSelection.querySelectorAll('.product-item');
    let foundProducts = false;
    
    productItems.forEach(item => {
        const productName = item.querySelector('strong')?.textContent.toLowerCase() || '';
        
        if (searchTerm && !productName.includes(searchTerm)) {
            item.style.display = 'none';
        } else {
            item.style.display = 'flex';
            foundProducts = true;
        }
    });
    
    // Remove existing "not found" message
    const existingNotFound = productSelection.querySelector('.modal-product-not-found');
    if (existingNotFound) {
        existingNotFound.remove();
    }
    
    // Add "not found" message if no products match
    if (searchTerm && !foundProducts) {
        const notFoundDiv = document.createElement('div');
        notFoundDiv.className = 'modal-product-not-found';
        notFoundDiv.innerHTML = `
            <div>🔍</div>
            <h4>Product Not Found</h4>
            <p>No products found matching "<strong>${searchTerm}</strong>"</p>
        `;
        productSelection.appendChild(notFoundDiv);
    }
}

// DEFECTIVE MODAL SEARCH
function filterDefectiveProducts() {
    const searchTerm = document.getElementById('defectiveSearchInput').value.toLowerCase().trim();
    const defectiveProductList = document.getElementById('defectiveProductList');
    const productItems = defectiveProductList.querySelectorAll('.product-item');
    let foundProducts = false;
    
    productItems.forEach(item => {
        const productName = item.querySelector('strong')?.textContent.toLowerCase() || '';
        
        if (searchTerm && !productName.includes(searchTerm)) {
            item.style.display = 'none';
        } else {
            item.style.display = 'flex';
            foundProducts = true;
        }
    });
    
    // Remove existing "not found" message
    const existingNotFound = defectiveProductList.querySelector('.modal-product-not-found');
    if (existingNotFound) {
        existingNotFound.remove();
    }
    
    // Add "not found" message if no products match
    if (searchTerm && !foundProducts) {
        const notFoundDiv = document.createElement('div');
        notFoundDiv.className = 'modal-product-not-found';
        notFoundDiv.innerHTML = `
            <div>🔍</div>
            <h4>Product Not Found</h4>
            <p>No products found matching "<strong>${searchTerm}</strong>"</p>
        `;
        defectiveProductList.appendChild(notFoundDiv);
    }
}

// PURCHASE MODAL SEARCH
function filterPurchaseProducts() {
    const searchTerm = document.getElementById('purchaseSearchInput').value.toLowerCase().trim();
    const purchaseProductList = document.getElementById('purchaseProductList');
    const productItems = purchaseProductList.querySelectorAll('.product-item');
    let foundProducts = false;
    
    productItems.forEach(item => {
        const productName = item.querySelector('strong')?.textContent.toLowerCase() || '';
        
        if (searchTerm && !productName.includes(searchTerm)) {
            item.style.display = 'none';
        } else {
            item.style.display = 'flex';
            foundProducts = true;
        }
    });
    
    // Remove existing "not found" message
    const existingNotFound = purchaseProductList.querySelector('.modal-product-not-found');
    if (existingNotFound) {
        existingNotFound.remove();
    }
    
    // Add "not found" message if no products match
    if (searchTerm && !foundProducts) {
        const notFoundDiv = document.createElement('div');
        notFoundDiv.className = 'modal-product-not-found';
        notFoundDiv.innerHTML = `
            <div>🔍</div>
            <h4>Product Not Found</h4>
            <p>No products found matching "<strong>${searchTerm}</strong>"</p>
        `;
        purchaseProductList.appendChild(notFoundDiv);
    }
}

// STOCK HISTORY SEARCH
function filterStockHistory() {
    const searchTerm = document.getElementById('stockHistorySearchInput').value.toLowerCase().trim();
    const stockHistoryList = document.getElementById('stockHistoryList');
    const historyItems = stockHistoryList.querySelectorAll('.stock-history-item');
    let foundItems = false;
    
    historyItems.forEach(item => {
        const itemText = item.textContent.toLowerCase();
        
        if (searchTerm && !itemText.includes(searchTerm)) {
            item.style.display = 'none';
        } else {
            item.style.display = 'flex';
            foundItems = true;
        }
    });
    
    // Remove existing "not found" message
    const existingNotFound = stockHistoryList.querySelector('.no-history');
    if (existingNotFound && searchTerm) {
        existingNotFound.remove();
    }
    
    // Add "not found" message if search term is provided and no items match
    if (searchTerm && !foundItems) {
        const notFoundDiv = document.createElement('div');
        notFoundDiv.className = 'no-history';
        notFoundDiv.innerHTML = `
            No stock history found matching "<strong>${searchTerm}</strong>"
        `;
        stockHistoryList.appendChild(notFoundDiv);
    }
}

// TRANSACTIONS SEARCH
function filterTransactions() {
    const searchTerm = document.getElementById('transactionsSearchInput').value.toLowerCase().trim();
    const transactionsList = document.getElementById('transactionsList');
    const transactionItems = transactionsList.querySelectorAll('.transaction-item');
    let foundItems = false;
    
    transactionItems.forEach(item => {
        const itemText = item.textContent.toLowerCase();
        
        if (searchTerm && !itemText.includes(searchTerm)) {
            item.style.display = 'none';
        } else {
            item.style.display = 'block';
            foundItems = true;
        }
    });
    
    // Remove existing "not found" message
    const existingNotFound = transactionsList.querySelector('.no-history');
    if (existingNotFound && searchTerm) {
        existingNotFound.remove();
    }
    
    // Add "not found" message if search term is provided and no items match
    if (searchTerm && !foundItems) {
        const notFoundDiv = document.createElement('div');
        notFoundDiv.className = 'no-history';
        notFoundDiv.innerHTML = `
            No transactions found matching "<strong>${searchTerm}</strong>"
        `;
        transactionsList.appendChild(notFoundDiv);
    }
}

// VIEW STOCK SEARCH
function filterViewStockItems() {
    const searchTerm = document.getElementById('viewStockSearchInput').value.toLowerCase().trim();
    const stockItemsList = document.getElementById('stockItemsList');
    const stockItems = stockItemsList.querySelectorAll('.stock-item');
    let foundItems = false;
    
    stockItems.forEach(item => {
        const itemText = item.textContent.toLowerCase();
        
        if (searchTerm && !itemText.includes(searchTerm)) {
            item.style.display = 'none';
        } else {
            item.style.display = 'flex';
            foundItems = true;
        }
    });
    
    // Remove existing "not found" message
    const existingNotFound = stockItemsList.querySelector('.modal-product-not-found');
    if (existingNotFound) {
        existingNotFound.remove();
    }
    
    // Add "not found" message if search term is provided and no items match
    if (searchTerm && !foundItems) {
        const notFoundDiv = document.createElement('div');
        notFoundDiv.className = 'modal-product-not-found';
        notFoundDiv.innerHTML = `
            <div>🔍</div>
            <h4>No Items Found</h4>
            <p>No stock items found matching "<strong>${searchTerm}</strong>"</p>
        `;
        stockItemsList.appendChild(notFoundDiv);
    }
}

// ====================== TEST DATA FUNCTIONS ======================
async function testLoadStockHistory() {
    // Return test data
    return [
        {
            id: 1,
            product_name: 'iPhone 13',
            serial_number: 'SN123456',
            color: 'Black',
            action_type: 'added',
            old_status: null,
            new_status: 'Available',
            quantity: 5,
            notes: 'New stock added',
            user_name: 'Admin',
            created_at: new Date().toISOString()
        },
        {
            id: 2,
            product_name: 'Samsung Galaxy S21',
            serial_number: 'SN789012',
            color: 'White',
            action_type: 'sold',
            old_status: 'Available',
            new_status: 'Sold',
            quantity: 1,
            notes: 'Sold to customer',
            user_name: 'Cashier',
            created_at: new Date(Date.now() - 86400000).toISOString()
        },
        {
            id: 3,
            product_name: 'MacBook Pro',
            serial_number: 'SN345678',
            color: 'Space Gray',
            action_type: 'defective',
            old_status: 'Available',
            new_status: 'Defective',
            quantity: 1,
            notes: 'Screen damage',
            user_name: 'Admin',
            created_at: new Date(Date.now() - 172800000).toISOString()
        }
    ];
}

async function testLoadTransactions() {
    // Return test data
    return [
        {
            id: 1,
            receipt_number: 'TXN-000001',
            total_amount: 45999.00,
            cashier_name: 'Admin',
            created_at: new Date().toISOString(),
            items: [
                {
                    product_name: 'iPhone 13',
                    quantity: 1,
                    sale_price: 45999.00,
                    serial_numbers: ['SN123456']
                }
            ]
        },
        {
            id: 2,
            receipt_number: 'TXN-000002',
            total_amount: 89998.00,
            cashier_name: 'Cashier',
            created_at: new Date(Date.now() - 86400000).toISOString(),
            items: [
                {
                    product_name: 'iPhone 13',
                    quantity: 1,
                    sale_price: 45999.00,
                    serial_numbers: ['SN789012']
                },
                {
                    product_name: 'Samsung Galaxy S21',
                    quantity: 1,
                    sale_price: 43999.00,
                    serial_numbers: ['SN345678']
                }
            ]
        }
    ];
}

// ====================== STOCK HISTORY FUNCTIONS ======================
async function loadStockHistory(searchTerm = '', filter = 'all', timeframe = 'all') {
    try {
        showToast('Loading stock history...', '#f59e0b');
        
        let history;
        
        // Try to fetch from server
        try {
            const response = await fetch(`get-stock-history.php?search=${encodeURIComponent(searchTerm)}&filter=${filter}&timeframe=${timeframe}`);
            if (response.ok) {
                history = await response.json();
                console.log('Stock history loaded from server:', history);
            } else {
                throw new Error('Server response not ok');
            }
        } catch (fetchError) {
            console.log('Using test data for stock history:', fetchError.message);
            // Use test data if fetch fails
            history = await testLoadStockHistory();
            
            // Apply filters to test data
            if (searchTerm) {
                const searchLower = searchTerm.toLowerCase();
                history = history.filter(item => 
                    (item.product_name && item.product_name.toLowerCase().includes(searchLower)) ||
                    (item.serial_number && item.serial_number.toLowerCase().includes(searchLower)) ||
                    (item.color && item.color.toLowerCase().includes(searchLower)) ||
                    (item.user_name && item.user_name.toLowerCase().includes(searchLower)) ||
                    (item.notes && item.notes.toLowerCase().includes(searchLower))
                );
            }
            
            if (filter !== 'all') {
                history = history.filter(item => item.action_type === filter);
            }
            
            // Note: Timeframe filter not applied to test data for simplicity
        }
        
        const historyList = document.getElementById('stockHistoryList');
        const historyCount = document.getElementById('historyCount');
        
        if (!history || history.length === 0) {
            historyList.innerHTML = '<div class="no-history">No stock history found</div>';
            historyCount.textContent = '0';
            return;
        }
        
        historyCount.textContent = history.length;
        
        // Format the history items
        historyList.innerHTML = history.map(item => {
            let badgeClass = '';
            let badgeText = item.action_type || 'Unknown';
            
            switch(item.action_type) {
                case 'added':
                    badgeClass = 'badge-added';
                    badgeText = 'Stock Added';
                    break;
                case 'sold':
                    badgeClass = 'badge-sold';
                    badgeText = 'Sold';
                    break;
                case 'defective':
                    badgeClass = 'badge-defective';
                    badgeText = 'Defective';
                    break;
                case 'updated':
                    badgeClass = 'badge-updated';
                    badgeText = 'Updated';
                    break;
                default:
                    badgeClass = '';
                    badgeText = item.action_type;
            }
            
            // Format date
            let dateStr = 'Unknown date';
            try {
                dateStr = new Date(item.created_at).toLocaleString();
            } catch (e) {
                dateStr = item.created_at || 'Unknown date';
            }
            
            return `
                <div class="stock-history-item">
                    <div class="stock-history-info">
                        <div class="stock-history-action">
                            <span class="stock-history-badge ${badgeClass}">${badgeText}</span>
                            ${item.product_name || 'Unknown Product'}
                        </div>
                        <div class="stock-history-details">
                            ${item.serial_number ? `<span>Serial: ${item.serial_number}</span>` : ''}
                            ${item.color ? `<span>Color: ${item.color}</span>` : ''}
                            ${item.old_status ? `<span>From: ${item.old_status}</span>` : ''}
                            ${item.new_status ? `<span>To: ${item.new_status}</span>` : ''}
                            ${item.quantity ? `<span>Qty: ${item.quantity}</span>` : ''}
                        </div>
                        <div class="stock-history-meta">
                            <span>By: ${item.user_name || 'System'}</span>
                            <span>Date: ${dateStr}</span>
                            ${item.notes ? `<span>Notes: ${item.notes}</span>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        showToast('Stock history loaded', '#22c55e');
    } catch (error) {
        console.error('Error loading stock history:', error);
        document.getElementById('stockHistoryList').innerHTML = `
            <div class="no-history">Error: ${error.message}</div>
        `;
        document.getElementById('historyCount').textContent = '0';
        showToast('Error loading stock history', '#dc2626');
    }
}

// ====================== TRANSACTIONS FUNCTIONS ======================
async function loadTransactions(searchTerm = '') {
    try {
        showToast('Loading transactions...', '#f59e0b');
        
        let transactions;
        
        // Try to fetch from server
        try {
            const response = await fetch(`get-transactions.php?search=${encodeURIComponent(searchTerm)}`);
            if (response.ok) {
                transactions = await response.json();
                console.log('Transactions loaded from server:', transactions);
            } else {
                throw new Error('Server response not ok');
            }
        } catch (fetchError) {
            console.log('Using test data for transactions:', fetchError.message);
            // Use test data if fetch fails
            transactions = await testLoadTransactions();
            
            // Apply search filter to test data
            if (searchTerm) {
                const searchLower = searchTerm.toLowerCase();
                transactions = transactions.filter(transaction => 
                    (transaction.receipt_number && transaction.receipt_number.toLowerCase().includes(searchLower)) ||
                    (transaction.cashier_name && transaction.cashier_name.toLowerCase().includes(searchLower)) ||
                    transaction.items.some(item => 
                        item.product_name && item.product_name.toLowerCase().includes(searchLower)
                    )
                );
            }
        }
        
        const transactionsList = document.getElementById('transactionsList');
        
        if (!transactions || transactions.length === 0) {
            transactionsList.innerHTML = '<div class="no-history">No transactions found</div>';
            return;
        }
        
        // Format the transactions
        transactionsList.innerHTML = transactions.map(transaction => {
            // Format date
            let dateStr = 'Unknown date';
            try {
                dateStr = new Date(transaction.created_at).toLocaleString();
            } catch (e) {
                dateStr = transaction.created_at || 'Unknown date';
            }
            
            return `
                <div class="transaction-item">
                    <div class="transaction-header">
                        <div class="transaction-id">Transaction #${transaction.id}</div>
                        <div class="transaction-date">${dateStr}</div>
                    </div>
                    <div class="transaction-items">
                        ${transaction.items ? transaction.items.map(item => `
                            <div class="transaction-item-row">
                                <div>${item.product_name} (${item.quantity} x ₱${parseFloat(item.sale_price).toFixed(2)})</div>
                                <div>₱${(item.quantity * item.sale_price).toFixed(2)}</div>
                            </div>
                        `).join('') : ''}
                    </div>
                    <div class="transaction-total">
                        Total: ₱${parseFloat(transaction.total_amount).toFixed(2)}
                    </div>
                    <div class="transaction-meta" style="margin-top:10px;font-size:12px;color:#9ca3af;">
                        Cashier: ${transaction.cashier_name || 'Unknown'} | 
                        Receipt: ${transaction.receipt_number || 'N/A'}
                    </div>
                </div>
            `;
        }).join('');
        
        showToast('Transactions loaded', '#22c55e');
    } catch (error) {
        console.error('Error loading transactions:', error);
        document.getElementById('transactionsList').innerHTML = `
            <div class="no-history">Error: ${error.message}</div>
        `;
        showToast('Error loading transactions', '#dc2626');
    }
}

// ====================== VIEW STOCK FUNCTIONS ======================
async function viewStock(productId, productName) {
    try {
        currentViewProductId = productId;
        document.getElementById('viewStockTitle').textContent = `Stock Details - ${productName}`;
        document.getElementById('viewStockModal').style.display = 'flex';
        
        selectedStockItems.clear();
        document.getElementById('bulkActionBar').style.display = 'none';
        
        // Load product totals
        await loadProductTotals(productId);
        
        // Load color summary
        await loadColorSummary(productId);
        
        // Load stock items
        await loadStockItems(productId);
        
        showToast(`Stock details loaded for ${productName}`, '#22c55e');
    } catch (error) {
        console.error('Error in viewStock:', error);
        showToast('Error loading stock details', '#dc2626');
    }
}

async function loadProductTotals(productId) {
    try {
        const response = await fetch(`get-product-totals.php?product_id=${productId}`);
        if (!response.ok) throw new Error('Network response was not ok');
        
        const totals = await response.json();
        const totalWithoutSold = (totals.available_count || 0) + (totals.defective_count || 0);
        
        const productTotalsGrid = document.getElementById('productTotalsGrid');
        productTotalsGrid.innerHTML = `
            <div class="product-total-item" style="background:#065f46;">
                <div class="product-total-label">Available</div>
                <div class="product-total-count">${totals.available_count || 0}</div>
            </div>
            <div class="product-total-item" style="background:#c2410c;">
                <div class="product-total-label">Defective</div>
                <div class="product-total-count">${totals.defective_count || 0}</div>
            </div>
            <div class="product-total-item" style="background:#6d28d9;">
                <div class="product-total-label">Sold</div>
                <div class="product-total-count">${totals.sold_count || 0}</div>
            </div>
            <div class="product-total-item" style="background:#1e40af;">
                <div class="product-total-label">Total (Available + Defective)</div>
                <div class="product-total-count">${totalWithoutSold}</div>
            </div>
        `;
    } catch (error) {
        console.error('Error loading product totals:', error);
        // Fallback to local data
        const product = data.find(p => p.id == productId);
        if (product) {
            const totalWithoutSold = (product.available_stock || 0) + (product.defective_count || 0);
            const productTotalsGrid = document.getElementById('productTotalsGrid');
            productTotalsGrid.innerHTML = `
                <div class="product-total-item" style="background:#065f46;">
                    <div class="product-total-label">Available</div>
                    <div class="product-total-count">${product.available_stock || 0}</div>
                </div>
                <div class="product-total-item" style="background:#c2410c;">
                    <div class="product-total-label">Defective</div>
                    <div class="product-total-count">${product.defective_count || 0}</div>
                </div>
                <div class="product-total-item" style="background:#6d28d9;">
                    <div class="product-total-label">Sold</div>
                    <div class="product-total-count">${product.sold_count || 0}</div>
                </div>
                <div class="product-total-item" style="background:#1e40af;">
                    <div class="product-total-label">Total (Available + Defective)</div>
                    <div class="product-total-count">${totalWithoutSold}</div>
                </div>
            `;
        }
    }
}

async function loadColorSummary(productId) {
    try {
        const response = await fetch(`get-color-summary.php?product_id=${productId}`);
        if (!response.ok) throw new Error('Network response was not ok');
        
        const colorSummary = await response.json();
        const colorSummaryGrid = document.getElementById('colorSummaryGrid');
        
        if (colorSummary.length === 0) {
            colorSummaryGrid.innerHTML = '<div style="color:#6b7280;text-align:center;padding:20px;grid-column:1/-1;">No color data available</div>';
            return;
        }
        
        colorSummaryGrid.innerHTML = colorSummary.map(color => `
            <div class="color-summary-item">
                <div class="color-summary-color">${color.color}</div>
                <div class="color-summary-count">
                    <span class="color-summary-available">${color.available_count} Available</span><br>
                    <span class="color-summary-sold">${color.sold_count} Sold</span><br>
                    <span class="color-summary-defective">${color.defective_count} Defective</span><br>
                    Total: ${color.total_count}
                </div>
            </div>
        `).join('');
    } catch (error) {
        console.error('Error loading color summary:', error);
        colorSummaryGrid.innerHTML = '<div style="color:#6b7280;text-align:center;padding:20px;grid-column:1/-1;">Error loading color data</div>';
    }
}

async function loadStockItems(productId, searchTerm = '') {
    try {
        const response = await fetch(`get-stock-items.php?product_id=${productId}&search=${encodeURIComponent(searchTerm)}`);
        if (!response.ok) throw new Error('Network response was not ok');
        
        const stockItems = await response.json();
        const stockItemsList = document.getElementById('stockItemsList');
        
        if (stockItems.length === 0) {
            stockItemsList.innerHTML = '<div style="color:#6b7280;text-align:center;padding:40px;">No stock items found</div>';
            return;
        }
        
        stockItemsList.innerHTML = stockItems.map(item => {
            const isSelected = selectedStockItems.has(item.id.toString());
            const defectNotes = item.defect_notes ? `
                <div class="defect-notes">
                    <strong>Defect Notes:</strong> ${item.defect_notes}
                </div>
            ` : '';
            
            return `
            <div class="stock-item ${isSelected ? 'selected' : ''}">
                <div class="stock-item-info">
                    <div style="display:flex; align-items:center;">
                        <input type="checkbox" class="stock-checkbox" data-stock-id="${item.id}" ${isSelected ? 'checked' : ''} 
                               onchange="toggleStockItemSelection(${item.id}, this.checked)" style="margin-right:10px;">
                        <div class="stock-serial">${item.serial_number}</div>
                    </div>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <span class="stock-color">Color: ${item.color}</span>
                        <span class="stock-status status-${item.status.toLowerCase()}">${item.status}</span>
                    </div>
                    ${defectNotes}
                    <div style="font-size:11px; color:#9ca3af;">
                        Added: ${new Date(item.created_at).toLocaleDateString()}
                    </div>
                </div>
                <div class="serial-actions">
                    <button class="btn purchase" onclick="updateStockStatus(${item.id}, 'Sold')">Mark Sold</button>
                    <button class="btn danger" onclick="updateStockStatus(${item.id}, 'Defective')">Mark Defective</button>
                    <button class="btn success" onclick="updateStockStatus(${item.id}, 'Available')">Mark Available</button>
                    <button class="btn warning" onclick="editStockItem(${item.id})">Edit</button>
                </div>
            </div>
            `;
        }).join('');
        
        // Clear any existing search term
        document.getElementById('viewStockSearchInput').value = '';
    } catch (error) {
        console.error('Error loading stock items:', error);
        stockItemsList.innerHTML = '<div style="color:#6b7280;text-align:center;padding:40px;">Error loading stock items</div>';
    }
}

// ====================== STOCK ITEM SELECTION ======================
function toggleStockItemSelection(stockId, isSelected) {
    if (isSelected) {
        selectedStockItems.add(stockId.toString());
    } else {
        selectedStockItems.delete(stockId.toString());
    }
    
    const bulkActionBar = document.getElementById('bulkActionBar');
    const bulkActionInfo = document.getElementById('bulkActionInfo');
    
    if (selectedStockItems.size > 0) {
        bulkActionBar.style.display = 'flex';
        bulkActionInfo.textContent = `${selectedStockItems.size} item(s) selected`;
        
        // Update UI for selected items
        document.querySelectorAll('.stock-item').forEach(item => {
            const checkbox = item.querySelector('.stock-checkbox');
            if (checkbox) {
                const itemId = checkbox.getAttribute('data-stock-id');
                if (selectedStockItems.has(itemId)) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            }
        });
    } else {
        bulkActionBar.style.display = 'none';
        document.querySelectorAll('.stock-item').forEach(item => {
            item.classList.remove('selected');
        });
    }
}

// ====================== FORM SUBMISSION FUNCTIONS ======================

// Add Product Form Submission
async function submitAddProductForm(formData) {
    try {
        showToast('Saving product...', '#f59e0b');
        const response = await fetch('add-product.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            showToast(result.message || 'Product added successfully!', '#22c55e');
            try { if (typeof displayStockAlerts === 'function') displayStockAlerts(); } catch(e) { console.warn(e); }
            document.getElementById('addModal').style.display = 'none';
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(result.error || 'Error adding product', '#dc2626');
        }
    } catch (error) {
        console.error('Error adding product:', error);
        showToast('Error adding product: ' + error.message, '#dc2626');
    }
}

// Update Product Form Submission
async function submitUpdateProductForm(formData) {
    try {
        showToast('Updating product...', '#f59e0b');
        const response = await fetch('update-product.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            showToast(result.message || 'Product updated successfully!', '#22c55e');
            try { if (typeof displayStockAlerts === 'function') displayStockAlerts(); } catch(e) { console.warn(e); }
            document.getElementById('editProductModal').style.display = 'none';
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(result.error || 'Error updating product', '#dc2626');
        }
    } catch (error) {
        console.error('Error updating product:', error);
        showToast('Error updating product: ' + error.message, '#dc2626');
    }
}

// Add Stock Items Submission
async function submitAddStockItems(productId, serialData) {
    try {
        showToast('Adding stock items...', '#f59e0b');
        const response = await fetch('add-stock.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                serial_data: serialData
            })
        });
        
        const result = await response.json();
        if (result.success) {
            showToast(result.message || 'Stock items added successfully!', '#22c55e');
            try { if (typeof displayStockAlerts === 'function') displayStockAlerts(); } catch(e) { console.warn(e); }
            document.getElementById('addStockModal').style.display = 'none';
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(result.error || 'Error adding stock items', '#dc2626');
        }
    } catch (error) {
        console.error('Error adding stock items:', error);
        showToast('Error adding stock items: ' + error.message, '#dc2626');
    }
}

// Mark Defective Items Submission
async function submitMarkDefective(productId, serialNumbers, notes) {
    try {
        showToast('Marking items as defective...', '#f59e0b');
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('serial_numbers', JSON.stringify(serialNumbers));
        formData.append('notes', notes);
        
        const response = await fetch('mark-defective.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            showToast(result.message || 'Items marked as defective successfully!', '#22c55e');
            try { if (typeof displayStockAlerts === 'function') displayStockAlerts(); } catch(e) { console.warn(e); }
            document.getElementById('defectiveModal').style.display = 'none';
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(result.error || 'Error marking items as defective', '#dc2626');
        }
    } catch (error) {
        console.error('Error marking items as defective:', error);
        showToast('Error marking items as defective: ' + error.message, '#dc2626');
    }
}

// Process Purchase Submission
async function submitPurchase(cartItems, serialNumbers, totalAmount) {
    try {
        showToast('Processing purchase...', '#f59e0b');
        const response = await fetch('process-purchase.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                cart_items: cartItems,
                serial_numbers: serialNumbers,
                total_amount: totalAmount,
                cashier_id: <?php echo $_SESSION['user_id']; ?>
            })
        });
        
        const result = await response.json();
        if (result.success) {
            currentPurchase = result.purchase;
            showReceipt(currentPurchase);
            showToast('Purchase completed successfully!', '#22c55e');
            // Refresh stock alerts immediately to reflect purchase
            try { if (typeof displayStockAlerts === 'function') displayStockAlerts(); } catch (e) { console.warn('displayStockAlerts call failed', e); }
        } else {
            showToast(result.error || 'Error processing purchase', '#dc2626');
        }
    } catch (error) {
        console.error('Error processing purchase:', error);
        showToast('Error processing purchase: ' + error.message, '#dc2626');
    }
}

// Delete Products Submission
async function submitDeleteProducts(productIds) {
    try {
        showToast('Deleting products...', '#f59e0b');
        const response = await fetch('delete-products.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_ids: productIds
            })
        });
        
        const result = await response.json();
        if (result.success) {
            showToast(result.message || 'Products deleted successfully!', '#22c55e');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(result.error || 'Error deleting products', '#dc2626');
        }
    } catch (error) {
        console.error('Error deleting products:', error);
        showToast('Error deleting products: ' + error.message, '#dc2626');
    }
}

// ====================== EDIT PRODUCT FUNCTIONS ======================
async function editProduct(productId) {
    try {
        showToast('Loading product data...', '#f59e0b');
        
        // Try to fetch from server first
        let product;
        try {
            const response = await fetch(`get-product.php?id=${productId}`);
            if (response.ok) {
                product = await response.json();
                
                if (product.error) {
                    showToast(product.error, '#dc2626');
                    
                    // Fallback: Try to get product from local data
                    product = data.find(p => p.id == productId);
                    if (!product) {
                        throw new Error('Product not found');
                    }
                }
            } else {
                throw new Error('Server response not ok');
            }
        } catch (fetchError) {
            console.log('Using local data for edit:', fetchError.message);
            // Use local data if fetch fails
            product = data.find(p => p.id == productId);
            if (!product) {
                showToast('Product not found', '#dc2626');
                return;
            }
        }
        
        // Populate the edit form
        document.getElementById('editProductId').value = product.id;
        document.getElementById('editProductName').value = product.name || '';
        document.getElementById('editProductItems').value = product.items || '';
        document.getElementById('editProductPrice').value = product.price || '';
        document.getElementById('editProductCategory').value = product.category || '';
        document.getElementById('editProductCriticalStock').value = product.critical_stock || 10;
        document.getElementById('editProductDate').value = product.date || '';
        document.getElementById('editProductStatus').value = product.status || 'Available';
        
        // Show photo preview
        const photoPreview = document.getElementById('editPhotoPreview');
        if (product.photo) {
            photoPreview.innerHTML = `<img src="${product.photo}" alt="${product.name}" style="width:80px;height:80px;border-radius:8px;object-fit:cover;">`;
        } else {
            photoPreview.innerHTML = '<div style="color:#6b7280;font-size:12px;">No photo available</div>';
        }
        
        // Show the modal
        document.getElementById('editProductModal').style.display = 'flex';
        showToast(`Editing product: ${product.name}`, '#22c55e');
    } catch (error) {
        console.error('Error in editProduct:', error);
        showToast('Error loading product data', '#dc2626');
    }
}

// ====================== ADD STOCK FUNCTIONS ======================
function selectProductForStock(productId, productName) {
    selectedProduct = { id: productId, name: productName };
    document.getElementById('selectedProductName').textContent = productName;
    document.getElementById('productSelection').style.display = 'none';
    document.getElementById('serialInputStep').style.display = 'block';
    addSerialNumbers = [];
    updateAddSerialList();
    document.getElementById('addSingleSerialInput').focus();
    showToast(`Selected ${productName} for adding stock`, '#22c55e');
}

function addSingleSerial() {
    const serialInput = document.getElementById('addSingleSerialInput');
    const colorInput = document.getElementById('addSerialColorInput');
    const serial = serialInput.value.trim();
    const color = colorInput.value.trim();
    
    if (!serial) {
        showToast('Please enter a serial number', '#dc2626');
        return;
    }
    
    if (!color) {
        showToast('Please enter a color', '#dc2626');
        return;
    }
    
    if (addSerialNumbers.some(item => item.serial === serial)) {
        showToast('Serial number already in list', '#dc2626');
        return;
    }
    
    addSerialNumbers.push({ serial, color });
    updateAddSerialList();
    serialInput.value = '';
    colorInput.value = '';
    serialInput.focus();
    showToast('Item added to list', '#22c55e');
}

function updateAddSerialList() {
    const addSerialList = document.getElementById('addSerialList');
    
    if (addSerialNumbers.length === 0) {
        addSerialList.innerHTML = '<div style="color:#6b7280;text-align:center;padding:20px;">No items added yet. Add serial numbers and colors above.</div>';
    } else {
        addSerialList.innerHTML = addSerialNumbers.map((item, index) => `
            <div class="serial-color-item">
                <div class="serial-color-info">
                    <div class="serial-number">${item.serial}</div>
                    <div class="serial-color">Color: ${item.color}</div>
                </div>
                <div class="serial-actions">
                    <button type="button" class="remove-serial" onclick="removeAddSerial(${index})">Remove</button>
                </div>
            </div>
        `).join('');
    }
}

function removeAddSerial(index) {
    addSerialNumbers.splice(index, 1);
    updateAddSerialList();
    showToast('Item removed from list', '#f59e0b');
}

async function confirmAddStockItems() {
    if (!selectedProduct || addSerialNumbers.length === 0) {
        showToast('Please select a product and add at least one item', '#dc2626');
        return;
    }
    
    await submitAddStockItems(selectedProduct.id, addSerialNumbers);
}

// ====================== DEFECTIVE PRODUCTS FUNCTIONS ======================
function selectDefectiveProduct(productId, productName, availableStock) {
    selectedDefectiveProduct = { id: productId, name: productName, availableStock };
    document.getElementById('selectedDefectiveProductName').textContent = productName;
    document.getElementById('defectiveAvailableStock').textContent = availableStock;
    document.getElementById('defectiveProductList').style.display = 'none';
    document.getElementById('defectiveSerialStep').style.display = 'block';
    defectiveSerials = [];
    updateDefectiveSerialList();
    document.getElementById('defectiveSerialInput').focus();
    showToast(`Selected ${productName} for marking as defective`, '#f59e0b');
}

function addDefectiveSerial() {
    const serialInput = document.getElementById('defectiveSerialInput');
    const serial = serialInput.value.trim();
    
    if (!serial) {
        showToast('Please enter a serial number', '#dc2626');
        return;
    }
    
    if (defectiveSerials.includes(serial)) {
        showToast('Serial number already in list', '#dc2626');
        return;
    }
    
    defectiveSerials.push(serial);
    updateDefectiveSerialList();
    serialInput.value = '';
    serialInput.focus();
    showToast('Serial added to list', '#22c55e');
}

function updateDefectiveSerialList() {
    const defectiveSerialList = document.getElementById('defectiveSerialList');
    
    if (defectiveSerials.length === 0) {
        defectiveSerialList.innerHTML = '<div style="color:#6b7280;text-align:center;padding:20px;">No serial numbers added yet</div>';
    } else {
        defectiveSerialList.innerHTML = defectiveSerials.map((serial, index) => `
            <div class="serial-color-item">
                <div class="serial-color-info">
                    <div class="serial-number">${serial}</div>
                </div>
                <div class="serial-actions">
                    <button type="button" class="remove-serial" onclick="removeDefectiveSerial(${index})">Remove</button>
                </div>
            </div>
        `).join('');
    }
}

function removeDefectiveSerial(index) {
    defectiveSerials.splice(index, 1);
    updateDefectiveSerialList();
    showToast('Serial removed from list', '#f59e0b');
}

async function confirmMarkDefective() {
    if (!selectedDefectiveProduct || defectiveSerials.length === 0) {
        showToast('Please select a product and add at least one serial number', '#dc2626');
        return;
    }
    
    const notes = document.getElementById('defectiveNotes').value.trim();
    if (!notes) {
        showToast('Please enter defect notes', '#dc2626');
        document.getElementById('defectiveNotes').focus();
        return;
    }
    
    const confirmMark = confirm(`Are you sure you want to mark ${defectiveSerials.length} item(s) as defective?\n\nNotes: ${notes}`);
    if (!confirmMark) return;
    
    await submitMarkDefective(selectedDefectiveProduct.id, defectiveSerials, notes);
}

// ====================== PURCHASE FUNCTIONS ======================
function togglePurchaseProductSelection(productId, productName, productPrice, isSelected) {
    if (isSelected) {
        purchaseCart.push({
            id: parseInt(productId),
            name: productName,
            price: parseFloat(productPrice),
            salePrice: parseFloat(productPrice),
            quantity: 1,
            serials: []
        });
    } else {
        purchaseCart = purchaseCart.filter(item => item.id !== parseInt(productId));
    }
    
    updatePurchaseCart();
}

function updatePurchaseCart() {
    const cartItems = document.getElementById('cartItems');
    const purchaseTotalAmount = document.getElementById('purchaseTotalAmount');
    const purchaseItemCount = document.getElementById('purchaseItemCount');
    
    if (purchaseCart.length === 0) {
        cartItems.innerHTML = '<div style="color:#6b7280;text-align:center;padding:40px;">No items in cart</div>';
        purchaseTotalAmount.textContent = '0.00';
        purchaseItemCount.textContent = '0';
        document.getElementById('purchaseCart').style.display = 'none';
        return;
    }
    
    let totalAmount = 0;
    let totalItems = 0;
    
    cartItems.innerHTML = purchaseCart.map(item => {
        const itemTotal = item.quantity * item.salePrice;
        totalAmount += itemTotal;
        totalItems += item.quantity;
        
        return `
            <div class="purchase-cart-item">
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.name}</div>
                    <div class="cart-item-details">
                        Original Price: ₱${item.price.toFixed(2)} | 
                        Sale Price: ₱${item.salePrice.toFixed(2)} | 
                        Quantity: ${item.quantity} |
                        Subtotal: ₱${itemTotal.toFixed(2)}
                    </div>
                    <div class="serial-input-container">
                        <label style="font-size:12px;color:#9ca3af;margin-bottom:8px;display:block;">
                            Enter ${item.quantity} serial number(s) for ${item.name}:
                        </label>
                        ${Array.from({length: item.quantity}, (_, index) => `
                            <div class="serial-input-row" style="margin-bottom:5px;">
                                <input type="text" 
                                       class="serial-input" 
                                       placeholder="Serial #${index + 1} (e.g., SN123456789)"
                                       data-product-id="${item.id}"
                                       data-index="${index}"
                                       onchange="updatePurchaseSerial(${item.id}, ${index}, this.value)"
                                       style="width:100%;padding:8px;border-radius:4px;border:1px solid #374151;background:#1f2937;color:#fff;font-size:12px;">
                            </div>
                        `).join('')}
                        <div class="serial-error" id="serialError-${item.id}" style="color:#dc2626;font-size:11px;margin-top:5px;display:none;"></div>
                    </div>
                </div>
                <div class="cart-item-controls">
                    <div class="quantity-controls">
                        <button type="button" class="quantity-btn" onclick="updateCartQuantity(${item.id}, -1)">-</button>
                        <input type="number" class="quantity-input" value="${item.quantity}" min="1" 
                               onchange="updateCartQuantityInput(${item.id}, this.value)">
                        <button type="button" class="quantity-btn" onclick="updateCartQuantity(${item.id}, 1)">+</button>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:5px;">
                        <input type="number" class="price-input" value="${item.salePrice.toFixed(2)}" step="0.01" min="0"
                               onchange="updateCartPrice(${item.id}, this.value)" placeholder="Sale Price">
                        <div style="font-size:10px;color:#9ca3af;text-align:center;">Sale Price</div>
                    </div>
                    <button type="button" class="remove-cart-item" onclick="removeFromCart(${item.id})">Remove</button>
                </div>
            </div>
        `;
    }).join('');
    
    purchaseTotalAmount.textContent = totalAmount.toFixed(2);
    purchaseItemCount.textContent = totalItems;
    document.getElementById('purchaseCart').style.display = 'block';
}

function updateCartQuantity(productId, change) {
    const item = purchaseCart.find(item => item.id === productId);
    if (item) {
        const newQuantity = item.quantity + change;
        if (newQuantity >= 1) {
            item.quantity = newQuantity;
            updatePurchaseCart();
        }
    }
}

function updateCartQuantityInput(productId, newQuantity) {
    const item = purchaseCart.find(item => item.id === productId);
    if (item && newQuantity >= 1) {
        item.quantity = parseInt(newQuantity);
        updatePurchaseCart();
    }
}

function updateCartPrice(productId, newPrice) {
    const item = purchaseCart.find(item => item.id === productId);
    if (item && newPrice >= 0) {
        item.salePrice = parseFloat(newPrice);
        updatePurchaseCart();
    }
}

function updatePurchaseSerial(productId, index, serial) {
    const item = purchaseCart.find(item => item.id === productId);
    if (item) {
        if (!item.serials) item.serials = [];
        item.serials[index] = serial.trim();
    }
}

function removeFromCart(productId) {
    purchaseCart = purchaseCart.filter(item => item.id !== productId);
    const checkbox = document.querySelector(`.purchase-product-checkbox[data-product-id="${productId}"]`);
    if (checkbox) {
        checkbox.checked = false;
    }
    updatePurchaseCart();
}

async function completePurchase() {
    // Validate all serial numbers are entered
    let allSerialsValid = true;
    const serialNumbers = {};
    
    for (const item of purchaseCart) {
        if (!item.serials || item.serials.length !== item.quantity || item.serials.some(s => !s)) {
            allSerialsValid = false;
            const errorElement = document.getElementById(`serialError-${item.id}`);
            if (errorElement) {
                errorElement.style.display = 'block';
                errorElement.textContent = 'Please fill in all serial numbers';
            }
        } else {
            serialNumbers[item.id] = item.serials;
        }
    }
    
    if (!allSerialsValid) {
        showToast('Please fill in all serial numbers', '#dc2626');
        return;
    }
    
    const totalAmount = purchaseCart.reduce((sum, item) => sum + (item.quantity * item.salePrice), 0);
    const confirmPurchase = confirm(`Confirm purchase of ${purchaseCart.length} item(s) for ₱${totalAmount.toFixed(2)}?`);
    
    if (!confirmPurchase) return;
    
    await submitPurchase(purchaseCart, serialNumbers, totalAmount);
}

function showReceipt(purchase) {
    const receiptContent = document.getElementById('receiptContent');
    
    receiptContent.innerHTML = `
        <div class="receipt-header">
            <h2>JHAY GADGET</h2>
            <p>Champion Bldg., La Purisima cor. Campaner St.,Zamboanga City Beside SM Mindpro </p>
            contact no:0905-483-2512
            <p>Receipt: ${purchase.receipt_number}</p>
            <p>Date: ${new Date().toLocaleString()}</p>
        </div>
        <div class="receipt-items">
            ${purchase.items.map(item => `
                <div class="receipt-item">
                    <div class="receipt-item-row">
                        <div class="receipt-item-name">${item.name}</div>
                        <div class="receipt-item-price">₱${parseFloat(item.sale_price).toFixed(2)}</div>
                    </div>
                    <div class="receipt-item-row">
                        <div class="receipt-item-quantity">Qty: ${item.quantity}</div>
                        <div class="receipt-item-quantity">Subtotal: ₱${(item.quantity * item.sale_price).toFixed(2)}</div>
                    </div>
                    ${item.serial_numbers.map(serial => `
                        <div class="receipt-item-serial">Serial: ${serial}</div>
                    `).join('')}
                </div>
            `).join('')}
        </div>
        <div class="receipt-total">
            <div class="receipt-total-row">
                <div>TOTAL AMOUNT:</div>
                <div>₱${parseFloat(purchase.total_amount).toFixed(2)}</div>
            </div>
        </div>
        <div class="receipt-footer">
            <p>Thank you for your purchase!</p>
            <p>For warranty claims, please present this receipt</p>
        </div>
    `;
    
    document.getElementById('purchaseModal').style.display = 'none';
    document.getElementById('receiptModal').style.display = 'flex';
}

// ====================== DOWNLOAD RECEIPT FUNCTION ======================
function downloadReceipt() {
    const receiptContent = document.getElementById('receiptContent');
    const receiptText = receiptContent.innerText;
    const receiptNumber = receiptText.match(/Receipt: (.+)/)?.[1] || 'Receipt';
    
    // Create a blob from the receipt content
    const element = document.createElement('a');
    const file = new Blob([receiptText], {type: 'text/plain'});
    element.href = URL.createObjectURL(file);
    element.download = `${receiptNumber.replace(/[^a-zA-Z0-9]/g, '_')}_${new Date().getTime()}.txt`;
    document.body.appendChild(element);
    element.click();
    document.body.removeChild(element);
    
    showToast('Receipt downloaded successfully!', '#22c55e');
}

// ====================== DELETE PRODUCTS FUNCTION ======================
async function deleteSelectedProducts() {
    const selectedCheckboxes = document.querySelectorAll('.product-checkbox:checked');
    const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.dataset.id);
    
    if (selectedIds.length === 0) {
        showToast('Please select at least one product to delete', '#dc2626');
        return;
    }
    
    const confirmDelete = confirm(`Are you sure you want to delete ${selectedIds.length} selected product(s)? This will also delete all associated stock items!`);
    if (!confirmDelete) return;
    
    await submitDeleteProducts(selectedIds);
}

// ====================== DOMContentLoaded EVENT LISTENER ======================
document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin panel loaded - initializing all buttons');
    
    // ========== MAIN PAGE SEARCH ==========
    document.getElementById('searchInput').addEventListener('input', filterProducts);
    
    // ========== FILTER BUTTONS ==========
    document.getElementById('categoryFilter').addEventListener('change', filterProducts);
    
    // Stock filter buttons
    const filterButtons = ['inStockBtn', 'outStockBtn', 'lowStockBtn', 'soldBtn', 'defectiveBtn', 'allStockBtn'];
    filterButtons.forEach(btnId => {
        const btn = document.getElementById(btnId);
        if (btn) {
            btn.addEventListener('click', function() {
                // Remove active class from all buttons
                filterButtons.forEach(id => {
                    const button = document.getElementById(id);
                    if (button) button.classList.remove('active');
                });
                // Add active class to clicked button
                this.classList.add('active');
                filterProducts();
            });
        }
    });
    
    // ========== MODAL SEARCH BARS ==========
    
    // Add Stock Modal Search
    document.getElementById('stockSearchInput')?.addEventListener('input', filterAddStockProducts);
    
    // Defective Modal Search
    document.getElementById('defectiveSearchInput')?.addEventListener('input', filterDefectiveProducts);
    
    // Purchase Modal Search
    document.getElementById('purchaseSearchInput')?.addEventListener('input', filterPurchaseProducts);
    
    // View Stock Modal Search
    document.getElementById('viewStockSearchInput')?.addEventListener('input', filterViewStockItems);
    
    // Stock History Modal Search
    document.getElementById('stockHistorySearchInput')?.addEventListener('input', filterStockHistory);
    
    // Transactions Modal Search
    document.getElementById('transactionsSearchInput')?.addEventListener('input', filterTransactions);
    
    // ========== MAIN TOOLBAR BUTTONS ==========
    document.getElementById('openModal').addEventListener('click', () => {
        document.getElementById('addModal').style.display = 'flex';
        showToast('Add Product modal opened', '#22c55e');
    });
    
    document.getElementById('addStockBtn').addEventListener('click', () => {
        document.getElementById('addStockModal').style.display = 'flex';
        // Clear search when opening modal
        document.getElementById('stockSearchInput').value = '';
        // Reset product display
        const productItems = document.querySelectorAll('#productSelection .product-item');
        productItems.forEach(item => item.style.display = 'flex');
        // Remove any existing "not found" message
        const existingNotFound = document.querySelector('#productSelection .modal-product-not-found');
        if (existingNotFound) existingNotFound.remove();
        showToast('Add Stock modal opened', '#22c55e');
    });
    
    document.getElementById('markDefectiveBtn').addEventListener('click', () => {
        document.getElementById('defectiveModal').style.display = 'flex';
        // Clear search when opening modal
        document.getElementById('defectiveSearchInput').value = '';
        // Reset product display
        const productItems = document.querySelectorAll('#defectiveProductList .product-item');
        productItems.forEach(item => item.style.display = 'flex');
        // Remove any existing "not found" message
        const existingNotFound = document.querySelector('#defectiveProductList .modal-product-not-found');
        if (existingNotFound) existingNotFound.remove();
        showToast('Mark Defective modal opened', '#22c55e');
    });
    
    document.getElementById('purchaseBtn').addEventListener('click', () => {
        document.getElementById('purchaseModal').style.display = 'flex';
        purchaseCart = [];
        document.querySelectorAll('.purchase-product-checkbox').forEach(cb => cb.checked = false);
        // Clear search when opening modal
        document.getElementById('purchaseSearchInput').value = '';
        // Reset product display
        const productItems = document.querySelectorAll('#purchaseProductList .product-item');
        productItems.forEach(item => item.style.display = 'flex');
        // Remove any existing "not found" message
        const existingNotFound = document.querySelector('#purchaseProductList .modal-product-not-found');
        if (existingNotFound) existingNotFound.remove();
        showToast('Purchase modal opened', '#22c55e');
    });
    
    document.getElementById('viewTransactionsBtn').addEventListener('click', async () => {
        document.getElementById('transactionsModal').style.display = 'flex';
        await loadTransactions();
        showToast('Transactions modal opened', '#22c55e');
    });
    
    document.getElementById('deleteProductsBtn').addEventListener('click', deleteSelectedProducts);
    
    document.getElementById('globalStockHistoryBtn').addEventListener('click', async () => {
        document.getElementById('stockHistoryModal').style.display = 'flex';
        await loadStockHistory();
        showToast('Stock History modal opened', '#22c55e');
    });
    
    // ========== MODAL CLOSE BUTTONS ==========
    document.getElementById('closeModal').addEventListener('click', () => {
        document.getElementById('addModal').style.display = 'none';
    });
    
    document.getElementById('closeEditProductModal').addEventListener('click', () => {
        document.getElementById('editProductModal').style.display = 'none';
    });
    
    document.getElementById('cancelEditProduct').addEventListener('click', () => {
        document.getElementById('editProductModal').style.display = 'none';
    });
    
    document.getElementById('closeViewStockModal').addEventListener('click', () => {
        document.getElementById('viewStockModal').style.display = 'none';
    });
    
    document.getElementById('closeAddStockModal').addEventListener('click', () => {
        document.getElementById('addStockModal').style.display = 'none';
    });
    
    document.getElementById('closeDefectiveModal').addEventListener('click', () => {
        document.getElementById('defectiveModal').style.display = 'none';
    });
    
    document.getElementById('closePurchaseModal').addEventListener('click', () => {
        document.getElementById('purchaseModal').style.display = 'none';
    });
    
    document.getElementById('closeStockHistoryModal').addEventListener('click', () => {
        document.getElementById('stockHistoryModal').style.display = 'none';
    });
    
    document.getElementById('closeTransactionsModal').addEventListener('click', () => {
        document.getElementById('transactionsModal').style.display = 'none';
    });
    
    document.getElementById('closeReceiptModal').addEventListener('click', () => {
        document.getElementById('receiptModal').style.display = 'none';
        setTimeout(() => location.reload(), 300);
    });
    
    document.getElementById('closeEditStockModal').addEventListener('click', () => {
        document.getElementById('editStockModal').style.display = 'none';
    });
    
    document.getElementById('cancelEditStock').addEventListener('click', () => {
        document.getElementById('editStockModal').style.display = 'none';
    });
    
    // ========== STOCK HISTORY FILTERS ==========
    document.getElementById('stockHistoryFilter')?.addEventListener('change', async function() {
        const searchTerm = document.getElementById('stockHistorySearchInput').value;
        const filter = this.value;
        const timeframe = document.getElementById('stockHistoryTimeframe').value;
        await loadStockHistory(searchTerm, filter, timeframe);
    });
    
    document.getElementById('stockHistoryTimeframe')?.addEventListener('change', async function() {
        const searchTerm = document.getElementById('stockHistorySearchInput').value;
        const filter = document.getElementById('stockHistoryFilter').value;
        const timeframe = this.value;
        await loadStockHistory(searchTerm, filter, timeframe);
    });
    
    document.getElementById('exportStockHistory')?.addEventListener('click', function() {
        showToast('Export feature coming soon!', '#f59e0b');
    });
    
    // ========== EVENT DELEGATION FOR DYNAMIC BUTTONS ==========
    document.addEventListener('click', function(e) {
        // View Stock buttons
        if (e.target.classList.contains('view-stock-btn')) {
            const productId = e.target.getAttribute('data-product-id');
            const productName = e.target.getAttribute('data-product-name');
            viewStock(productId, productName);
        }
        
        // Edit Product buttons
        if (e.target.classList.contains('edit-product-btn')) {
            const productId = e.target.getAttribute('data-product-id');
            editProduct(productId);
        }
        
        // Add Stock modal - Select Product buttons
        if (e.target.classList.contains('select-product-btn')) {
            const productId = e.target.getAttribute('data-product-id');
            const productName = e.target.getAttribute('data-product-name');
            const productItem = e.target.closest('.product-item');
            const availableStock = productItem.querySelector('.product-stock').textContent.match(/\d+/)?.[0] || 0;
            selectProductForStock(productId, productName);
        }
        
        // Defective modal - Select Defective Product buttons
        if (e.target.classList.contains('select-defective-product')) {
            const productId = e.target.getAttribute('data-product-id');
            const productName = e.target.getAttribute('data-product-name');
            const productItem = e.target.closest('.product-item');
            const availableStock = productItem.querySelector('.product-stock').textContent.match(/\d+/)?.[0] || 0;
            selectDefectiveProduct(productId, productName, availableStock);
        }
        
        // Purchase modal - Checkbox selection
        if (e.target.classList.contains('purchase-product-checkbox')) {
            const productId = e.target.getAttribute('data-product-id');
            const productName = e.target.getAttribute('data-product-name');
            const productPrice = e.target.getAttribute('data-product-price');
            togglePurchaseProductSelection(productId, productName, productPrice, e.target.checked);
        }
        
        // Select All checkbox
        if (e.target.id === 'selectAllCheckbox') {
            const checkboxes = document.querySelectorAll('.product-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = e.target.checked;
            });
        }
        
        // Bulk select all in view stock
        if (e.target.id === 'bulkSelectAll') {
            const checkboxes = document.querySelectorAll('.stock-checkbox');
            checkboxes.forEach(checkbox => {
                const stockId = checkbox.getAttribute('data-stock-id');
                checkbox.checked = true;
                selectedStockItems.add(stockId);
            });
            
            const bulkActionBar = document.getElementById('bulkActionBar');
            const bulkActionInfo = document.getElementById('bulkActionInfo');
            bulkActionBar.style.display = 'flex';
            bulkActionInfo.textContent = `${selectedStockItems.size} item(s) selected`;
            
            document.querySelectorAll('.stock-item').forEach(item => {
                item.classList.add('selected');
            });
        }
    });
    
    // ========== ADD STOCK MODAL SPECIFIC ==========
    document.getElementById('addSingleSerialBtn').addEventListener('click', addSingleSerial);
    document.getElementById('addSingleSerialInput').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addSingleSerial();
        }
    });
    
    document.getElementById('backToSelection').addEventListener('click', () => {
        document.getElementById('serialInputStep').style.display = 'none';
        document.getElementById('productSelection').style.display = 'block';
        selectedProduct = null;
        addSerialNumbers = [];
        // Clear search when going back
        document.getElementById('stockSearchInput').value = '';
        filterAddStockProducts();
    });
    
    document.getElementById('confirmAddStock').addEventListener('click', confirmAddStockItems);
    
    // ========== DEFECTIVE MODAL SPECIFIC ==========
    document.getElementById('addDefectiveSerialBtn').addEventListener('click', addDefectiveSerial);
    document.getElementById('defectiveSerialInput').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addDefectiveSerial();
        }
    });
    
    document.getElementById('backToDefectiveSelection').addEventListener('click', () => {
        document.getElementById('defectiveSerialStep').style.display = 'none';
        document.getElementById('defectiveProductList').style.display = 'block';
        selectedDefectiveProduct = null;
        defectiveSerials = [];
        document.getElementById('defectiveNotes').value = '';
        // Clear search when going back
        document.getElementById('defectiveSearchInput').value = '';
        filterDefectiveProducts();
    });
    
    document.getElementById('confirmMarkDefective').addEventListener('click', confirmMarkDefective);
    
    // ========== PURCHASE MODAL SPECIFIC ==========
    document.getElementById('backToProductSelection').addEventListener('click', () => {
        document.getElementById('purchaseCart').style.display = 'none';
        purchaseCart = [];
        document.querySelectorAll('.purchase-product-checkbox').forEach(cb => cb.checked = false);
        // Clear search when going back
        document.getElementById('purchaseSearchInput').value = '';
        filterPurchaseProducts();
    });
    
    document.getElementById('confirmPurchase').addEventListener('click', completePurchase);
    
    // ========== RECEIPT MODAL SPECIFIC ==========
    document.getElementById('printReceipt').addEventListener('click', () => {
        window.print();
        showToast('Printing receipt...', '#22c55e');
    });
    
    document.getElementById('downloadReceipt').addEventListener('click', () => {
        downloadReceipt();
    });
    
    document.getElementById('newPurchase').addEventListener('click', () => {
        document.getElementById('receiptModal').style.display = 'none';
        setTimeout(() => location.reload(), 300);
    });
    
    // ========== FORM SUBMISSIONS ==========
    document.getElementById('addProductForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        submitAddProductForm(formData);
    });
    
    document.getElementById('editProductForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        submitUpdateProductForm(formData);
    });
    
    document.getElementById('editStockForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        // This form will submit normally since it has action attribute
        this.submit();
    });
    
    // ========== PHOTO PREVIEW ==========
    document.getElementById('photoInput')?.addEventListener('change', function() {
        const preview = document.getElementById('photoPreview');
        if (preview && this.files && this.files[0]) {
            preview.innerHTML = `
                <div style="color:#22c55e;font-size:12px;">
                    Selected: ${this.files[0].name}
                </div>
            `;
        }
    });
    
    // ========== MODAL CLOSE ON BACKGROUND CLICK ==========
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });
    
    showToast('loading successfully!', '#22c55e');
});
</script>
</body>
</html>
<?php
include 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Create tables if they don't exist
$createTables = [
    "CREATE TABLE IF NOT EXISTS chatbot_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255),
        message TEXT NOT NULL,
        type ENUM('general', 'question', 'complaint', 'feedback') DEFAULT 'general',
        is_anonymous TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS complaints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        contact VARCHAR(100),
        product_name VARCHAR(255),
        purchase_date DATE,
        issue_type VARCHAR(100),
        complaint_details TEXT NOT NULL,
        is_anonymous TINYINT(1) DEFAULT 0,
        status ENUM('pending', 'in_progress', 'resolved') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS product_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_name VARCHAR(255) NOT NULL,
        username VARCHAR(100) NOT NULL,
        comment TEXT NOT NULL,
        rating INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending', 'approved') DEFAULT 'approved'
    )"
];

foreach ($createTables as $query) {
    if (!$db->query($query)) {
        error_log("Table creation failed: " . $db->error);
    }
}

// Handle message deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    $messageId = $_POST['message_id'];
    $table = $_POST['table'] ?? 'chatbot_messages';
    
    if ($table === 'product_comments') {
        $stmt = $db->prepare("DELETE FROM product_comments WHERE id = ?");
    } else {
        $stmt = $db->prepare("DELETE FROM chatbot_messages WHERE id = ?");
    }
    
    $stmt->bind_param("i", $messageId);
    if ($stmt->execute()) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Message deleted successfully.'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Error deleting message.'];
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=" . ($_GET['tab'] ?? 'chatbot'));
    exit();
}

// Remove the message type update functionality for chatbot messages
// Only keep it for product comments status updates

// Handle comment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_comment_status'])) {
    $commentId = $_POST['comment_id'];
    $newStatus = $_POST['comment_status'];
    $stmt = $db->prepare("UPDATE product_comments SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $newStatus, $commentId);
    if ($stmt->execute()) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Comment status updated successfully.'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Error updating comment status.'];
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=" . ($_GET['tab'] ?? 'chatbot'));
    exit();
}

// Get current tab
$currentTab = $_GET['tab'] ?? 'chatbot';

// Search functionality
$searchQuery = $_GET['search'] ?? '';

// Fetch data based on current tab
if ($currentTab === 'product_comments') {
    // Fetch product comments
    $query = "SELECT * FROM product_comments WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($searchQuery)) {
        $query .= " AND (product_name LIKE ? OR username LIKE ? OR comment LIKE ?)";
        $searchTerm = "%$searchQuery%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "sss";
    }

    $query .= " ORDER BY created_at DESC";

    $stmt = $db->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Calculate counts for product comments
    $commentCounts = [
        'all' => 0,
        'approved' => 0,
        'pending' => 0
    ];

    $countStmt = $db->prepare("SELECT status, COUNT(*) as count FROM product_comments GROUP BY status");
    $countStmt->execute();
    $countResult = $countStmt->get_result();

    while ($row = $countResult->fetch_assoc()) {
        $commentCounts[$row['status']] = $row['count'];
        $commentCounts['all'] += $row['count'];
    }

    // Calculate new comments (last 24 hours)
    $newCommentStmt = $db->prepare("SELECT COUNT(*) as count FROM product_comments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $newCommentStmt->execute();
    $newCommentCount = $newCommentStmt->get_result()->fetch_assoc()['count'];
    
} else {
    // Fetch chatbot messages (default)
    $query = "SELECT * FROM chatbot_messages WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($searchQuery)) {
        $query .= " AND (message LIKE ? OR email LIKE ?)";
        $searchTerm = "%$searchQuery%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "ss";
    }

    $query .= " ORDER BY created_at DESC";

    $stmt = $db->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Calculate message counts
    $chatbotCounts = [
        'all' => 0,
        'complaint' => 0,
        'feedback' => 0,
        'question' => 0,
        'general' => 0
    ];

    $countStmt = $db->prepare("SELECT type, COUNT(*) as count FROM chatbot_messages GROUP BY type");
    $countStmt->execute();
    $countResult = $countStmt->get_result();

    while ($row = $countResult->fetch_assoc()) {
        $chatbotCounts[$row['type']] = $row['count'];
        $chatbotCounts['all'] += $row['count'];
    }

    // Calculate new messages (last 24 hours)
    $newChatbotStmt = $db->prepare("SELECT COUNT(*) as count FROM chatbot_messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $newChatbotStmt->execute();
    $newChatbotCount = $newChatbotStmt->get_result()->fetch_assoc()['count'];
}

// Get toast message from session
$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Customer Feedback · JHAY Gadget</title>
<style>
:root {
  --blue: #1e88e5; --blue-600: #1976d2;
  --green: #43a047; --red: #e53935;
  --orange: #fb8c00; --purple: #8e24aa;
  --ink: #0f172a; --ink-2: #111827;
  --bg: #000; --card: #111; --border: #333;
  --muted: #9ca3af;
  --black: #000000;
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
.chipRow.active{background:var(--blue);}
.chipLabel{display:flex;align-items:center;gap:10px;}
.dot{width:8px;height:8px;border-radius:999px;background:var(--blue);}
.badge{background:#374151;color:#e5e7eb;font-size:12px;padding:2px 8px;border-radius:999px;} 
.chipRow.active .badge{background:rgba(255,255,255,0.2);}
main{padding:18px;overflow:auto;}
.toolbar{display:flex;gap:10px;align-items:center;margin-bottom:20px;}
.search{
  flex:1; display:flex; align-items:center; gap:8px; background:var(--card);
  border:1px solid var(--border); border-radius:12px; padding:8px 10px;
}
.search input{border:0; outline:0; width:100%; font-size:14px; background:transparent; color:#fff;}
.btn{background:#111;border:1px solid var(--border);border-radius:12px;padding:8px 14px;cursor:pointer;color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.btn.primary{background:var(--blue);color:#fff;border-color:var(--blue-600);}
.btn.danger{background:var(--red);color:#fff;border-color:#dc2626;}

/* Toast Notification Styles */
.toast-container {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 10000;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.toast {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 12px 16px;
  min-width: 300px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  gap: 12px;
  animation: slideIn 0.3s ease-out;
  border-left: 4px solid var(--blue);
}

.toast.success {
  border-left-color: var(--green);
}

.toast.error {
  border-left-color: var(--red);
}

.toast-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

.toast-content {
  flex: 1;
  font-size: 14px;
  color: #f9fafb;
}

.toast-close {
  background: none;
  border: none;
  color: var(--muted);
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.toast-close:hover {
  background: rgba(255,255,255,0.1);
}

@keyframes slideIn {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

@keyframes slideOut {
  from {
    transform: translateX(0);
    opacity: 1;
  }
  to {
    transform: translateX(100%);
    opacity: 0;
  }
}

.toast.hiding {
  animation: slideOut 0.3s ease-in forwards;
}

/* Tab Navigation */
.tab-navigation {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--border);
  padding-bottom: 10px;
}

.tab-btn {
  background: transparent;
  border: none;
  color: var(--muted);
  padding: 10px 20px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.tab-btn.active {
  background: var(--blue);
  color: white;
}

.tab-btn:hover:not(.active) {
  background: var(--ink-2);
}

/* Messages Section Styles */
.messages-section {
  margin-top: 0;
}

.messages-header {
  display: flex;
  justify-content: between;
  align-items: center;
  margin-bottom: 20px;
}

.messages-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 15px;
  margin-bottom: 20px;
}

.stat-card {
  background: var(--card);
  padding: 20px;
  border-radius: 10px;
  border: 1px solid var(--border);
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

.stat-new { color: var(--orange); }
.stat-total { color: var(--blue); }
.stat-complaint { color: var(--red); }
.stat-feedback { color: var(--green); }
.stat-question { color: var(--orange); }
.stat-general { color: var(--purple); }
.stat-approved { color: var(--green); }
.stat-pending { color: var(--orange); }

.message-item {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 20px;
  margin-bottom: 15px;
  position: relative;
}

.message-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
  gap: 15px;
  position: relative;
  padding-right: 120px; /* Space for date and delete button only */
}

.message-meta {
  flex: 1;
  min-width: 0; /* Prevent flex item from overflowing */
}

.message-type-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  margin-bottom: 8px;
  display: inline-block;
}

.type-complaint { background: var(--red); color: white; }
.type-feedback { background: var(--green); color: white; }
.type-question { background: var(--orange); color: white; }
.type-general { background: var(--blue); color: white; }
.status-approved { background: var(--green); color: white; }
.status-pending { background: var(--orange); color: white; }

.message-email {
  color: var(--muted);
  font-size: 14px;
  margin-bottom: 4px;
}

.message-date {
  position: absolute;
  top: 0;
  right: 0;
  font-size: 12px;
  color: var(--muted);
  white-space: nowrap;
  text-align: right;
  z-index: 2; /* Ensure date is above buttons */
}

.message-content {
  background: var(--ink-2);
  padding: 15px;
  border-radius: 8px;
  border-left: 4px solid var(--blue);
  margin-top: 10px;
}

.message-content p {
  margin: 0;
  line-height: 1.5;
  white-space: pre-wrap;
}

.rating-stars {
  color: #fbbf24;
  margin-bottom: 8px;
}

.message-actions {
  position: absolute;
  top: 25px; /* Position below the date */
  right: 0;
  display: flex;
  gap: 8px;
  align-items: center;
  z-index: 1;
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

.type-selector, .status-selector {
  background: var(--ink);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 4px 8px;
  color: white;
  font-size: 11px;
  cursor: pointer;
  white-space: nowrap;
}

/* Fixed category badge - non-interactive */
.fixed-category {
  background: var(--ink);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 4px 8px;
  color: white;
  font-size: 11px;
  white-space: nowrap;
  display: inline-block;
}

.no-messages {
  text-align: center;
  padding: 60px 40px;
  color: var(--muted);
}

.no-messages svg {
  width: 64px;
  height: 64px;
  margin-bottom: 16px;
  opacity: 0.5;
}

/* Modal Styles */
.modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.7);
  z-index: 2000;
  align-items: center;
  justify-content: center;
}

.modal.show {
  display: flex;
}

.modal-content {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 30px;
  max-width: 500px;
  width: 90%;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
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

.modal-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 20px;
}

.anonymous-badge {
  background: var(--purple);
  color: white;
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 10px;
  margin-left: 8px;
}

.product-name {
  color: var(--blue);
  font-weight: 600;
  margin-bottom: 8px;
}

@media (max-width: 980px){
  .shell{grid-template-columns:80px 1fr;}
  aside{padding:12px 8px;}
  .navTitle{display:none;}
  .chipLabel span{display:none;}
}

@media (max-width: 720px){
  .toolbar{flex-wrap:wrap;}
  .message-header {
    flex-direction: column;
    padding-right: 0;
  }
  .message-date {
    position: static;
    text-align: left;
    margin-top: 8px;
    order: 2;
  }
  .message-actions {
    position: static;
    margin-top: 10px;
    justify-content: flex-start;
    order: 3;
  }
  .message-meta {
    order: 1;
  }
  .messages-stats{grid-template-columns:1fr 1fr;}
  .tab-navigation{flex-wrap:wrap;}
  .toast-container {
    right: 10px;
    left: 10px;
  }
  .toast {
    min-width: auto;
  }
}

@media (max-width: 480px) {
  .message-actions {
    flex-wrap: wrap;
  }
  
  .type-selector, .status-selector {
    font-size: 10px;
    padding: 3px 6px;
  }
  
  .action-btn {
    font-size: 10px;
    padding: 4px 8px;
  }
  
  .fixed-category {
    font-size: 10px;
    padding: 3px 6px;
  }
}
</style>
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
        <a href="sales-graphs-admin.php" class="chipRow">
          <div class="chipLabel"><span class="dot"></span><span>Sales Analytics</span></div>
        </a>
        <a href="analytics-admin.php" class="chipRow">
          <div class="chipLabel"><span class="dot"></span><span>Data Analytics</span></div>
        </a>
        <a href="feedback-admin.php" class="chipRow active">
          <div class="chipLabel"><span class="dot"></span><span>Feedback</span></div>
        </a>
      </div>
    </aside>

    <main>
      <!-- Toast Notifications Container -->
      <div class="toast-container" id="toastContainer"></div>

      <div class="toolbar">
        <div class="search">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/>
            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <form method="GET" style="display: flex; width: 100%; gap: 10px; align-items: center;">
            <input type="hidden" name="tab" value="<?php echo $currentTab; ?>">
            <input 
              type="text" 
              name="search" 
              placeholder="<?php echo $currentTab === 'product_comments' ? 'Search product reviews...' : 'Search chatbot messages...'; ?>" 
              value="<?php echo htmlspecialchars($searchQuery); ?>"
              style="flex: 1; border: none; background: transparent; color: white; outline: none;"
            >
            <button type="submit" class="btn primary" style="padding: 8px 16px;">Search</button>
            <?php if (!empty($searchQuery)): ?>
              <a href="?tab=<?php echo $currentTab; ?>" class="btn" style="padding: 8px 16px;">Clear</a>
            <?php endif; ?>
          </form>
        </div>
      </div>

      <!-- Tab Navigation -->
      <div class="tab-navigation">
        <button class="tab-btn <?php echo $currentTab === 'chatbot' ? 'active' : ''; ?>" onclick="switchTab('chatbot')">
          Chatbot Messages
          <?php if ($currentTab === 'chatbot'): ?>
            <span class="badge"><?php echo $chatbotCounts['all'] ?? 0; ?></span>
          <?php endif; ?>
        </button>
        <button class="tab-btn <?php echo $currentTab === 'product_comments' ? 'active' : ''; ?>" onclick="switchTab('product_comments')">
          Product Reviews
          <?php if ($currentTab === 'product_comments'): ?>
            <span class="badge"><?php echo $commentCounts['all'] ?? 0; ?></span>
          <?php endif; ?>
        </button>
      </div>

      <!-- Messages Section -->
      <div class="messages-section">
        <div class="messages-header">
          <h2><?php echo $currentTab === 'product_comments' ? 'Product Reviews' : 'Chatbot Messages'; ?></h2>
        </div>

        <?php if ($currentTab === 'product_comments'): ?>
          <!-- Product Comments Stats -->
          <div class="messages-stats">
            <div class="stat-card">
              <h3>New Reviews (24h)</h3>
              <div class="stat-new"><?php echo $newCommentCount ?? 0; ?></div>
            </div>
            <div class="stat-card">
              <h3>Total Reviews</h3>
              <div class="stat-total"><?php echo $commentCounts['all'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
              <h3>Approved</h3>
              <div class="stat-approved"><?php echo $commentCounts['approved'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
              <h3>Pending</h3>
              <div class="stat-pending"><?php echo $commentCounts['pending'] ?? 0; ?></div>
            </div>
          </div>

          <!-- Product Comments List -->
          <div class="messages-list">
            <?php if (empty($data)): ?>
              <div class="no-messages">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                </svg>
                <h3>No Product Reviews Found</h3>
                <p><?php echo empty($searchQuery) ? 'No product reviews have been submitted yet.' : 'No reviews match your search criteria.'; ?></p>
              </div>
            <?php else: ?>
              <?php foreach ($data as $comment): ?>
                <div class="message-item">
                  <div class="message-header">
                    <div class="message-meta">
                      <div class="message-type-badge status-<?php echo $comment['status']; ?>">
                        <?php echo ucfirst($comment['status']); ?>
                      </div>
                      <div class="product-name"><?php echo htmlspecialchars($comment['product_name']); ?></div>
                      <div class="message-email">By: <?php echo htmlspecialchars($comment['username']); ?></div>
                    </div>
                    <div class="message-date">
                      <?php echo date('M j, Y g:i A', strtotime($comment['created_at'])); ?>
                    </div>
                    <div class="message-actions">
                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                        <select name="comment_status" class="status-selector" onchange="this.form.submit()">
                          <option value="approved" <?php echo $comment['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                          <option value="pending" <?php echo $comment['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                        <input type="hidden" name="update_comment_status" value="1">
                      </form>
                      <button class="action-btn delete" onclick="confirmDelete(<?php echo $comment['id']; ?>, 'product_comments')">
                        Delete
                      </button>
                    </div>
                  </div>
                  
                  <div class="message-content">
                    <div class="rating-stars">
                      <?php echo str_repeat('⭐', $comment['rating']); ?> (<?php echo $comment['rating']; ?>/5)
                    </div>
                    <p><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

        <?php else: ?>
          <!-- Chatbot Messages Stats -->
          <div class="messages-stats">
            <div class="stat-card">
              <h3>New Messages (24h)</h3>
              <div class="stat-new"><?php echo $newChatbotCount ?? 0; ?></div>
            </div>
            <div class="stat-card">
              <h3>Total Messages</h3>
              <div class="stat-total"><?php echo $chatbotCounts['all'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
              <h3>Complaints</h3>
              <div class="stat-complaint"><?php echo $chatbotCounts['complaint'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
              <h3>Feedback</h3>
              <div class="stat-feedback"><?php echo $chatbotCounts['feedback'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
              <h3>Questions</h3>
              <div class="stat-question"><?php echo $chatbotCounts['question'] ?? 0; ?></div>
            </div>
          </div>

          <!-- Chatbot Messages List -->
          <div class="messages-list">
            <?php if (empty($data)): ?>
              <div class="no-messages">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                </svg>
                <h3>No Messages Found</h3>
                <p><?php echo empty($searchQuery) ? 'No messages have been received yet.' : 'No messages match your search criteria.'; ?></p>
              </div>
            <?php else: ?>
              <?php foreach ($data as $message): ?>
                <div class="message-item">
                  <div class="message-header">
                    <div class="message-meta">
                      <div class="message-type-badge type-<?php echo $message['type']; ?>">
                        <?php echo ucfirst($message['type']); ?>
                        <?php if ($message['is_anonymous']): ?>
                          <span class="anonymous-badge">Anonymous</span>
                        <?php endif; ?>
                      </div>
                      <?php if (!empty($message['email']) && !$message['is_anonymous']): ?>
                        <div class="message-email"><?php echo htmlspecialchars($message['email']); ?></div>
                      <?php elseif ($message['is_anonymous']): ?>
                        <div class="message-email" style="color: var(--purple);">Anonymous User</div>
                      <?php endif; ?>
                    </div>
                    <div class="message-date">
                      <?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?>
                    </div>
                    <div class="message-actions">
                      <!-- Fixed category display instead of dropdown -->
                      <div class="fixed-category">
                        <?php echo ucfirst($message['type']); ?>
                      </div>
                      <button class="action-btn delete" onclick="confirmDelete(<?php echo $message['id']; ?>, 'chatbot_messages')">
                        Delete
                      </button>
                    </div>
                  </div>
                  
                  <div class="message-content">
                    <p><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 class="modal-title">Confirm Delete</h2>
      <button class="close-btn" onclick="closeModal()">&times;</button>
    </div>
    <p>Are you sure you want to delete this message? This action cannot be undone.</p>
    <div class="modal-actions">
      <button class="btn" onclick="closeModal()">Cancel</button>
      <form method="POST" id="deleteForm">
        <input type="hidden" name="message_id" id="deleteMessageId">
        <input type="hidden" name="table" id="deleteTable">
        <input type="hidden" name="delete_message" value="1">
        <button type="submit" class="btn danger">Delete</button>
      </form>
    </div>
  </div>
</div>

<script>
function switchTab(tabName) {
  window.location.href = `?tab=${tabName}`;
}

function confirmDelete(messageId, table) {
  document.getElementById('deleteMessageId').value = messageId;
  document.getElementById('deleteTable').value = table;
  document.getElementById('deleteModal').classList.add('show');
}

function closeModal() {
  document.getElementById('deleteModal').classList.remove('show');
}

// Toast notification functions
function showToast(message, type = 'success') {
  const toastContainer = document.getElementById('toastContainer');
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  
  const icon = type === 'success' ? 
    '<svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>' :
    '<svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>';
  
  toast.innerHTML = `
    ${icon}
    <div class="toast-content">${message}</div>
    <button class="toast-close" onclick="this.parentElement.remove()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M18 6L6 18M6 6l12 12"/>
      </svg>
    </button>
  `;
  
  toastContainer.appendChild(toast);
  
  // Auto remove after 5 seconds
  setTimeout(() => {
    if (toast.parentElement) {
      toast.classList.add('hiding');
      setTimeout(() => toast.remove(), 300);
    }
  }, 5000);
}

// Show toast from PHP if exists
<?php if ($toast): ?>
  document.addEventListener('DOMContentLoaded', function() {
    showToast('<?php echo $toast['message']; ?>', '<?php echo $toast['type']; ?>');
  });
<?php endif; ?>

// Close modal when clicking outside
document.getElementById('deleteModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeModal();
  }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeModal();
  }
});
</script>
</body>
</html>
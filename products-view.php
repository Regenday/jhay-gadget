<?php
session_start();
include('db.php');

// Get current logged-in user
$currentUsername = $_SESSION['username'] ?? 'Guest';

// Get category filter from URL
$categoryFilter = $_GET['category'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build SQL query to get products with their stock counts grouped by product name
// EXCLUDE DEFECTIVE STOCK from available count
$sql = "SELECT 
            p.name,
            p.category,
            p.price,
            p.items,
            p.photo,
            p.status,
            COUNT(ps.id) as total_stock,
            COUNT(CASE WHEN ps.status = 'Available' THEN 1 END) as available_stock
        FROM products p 
        LEFT JOIN product_stock ps ON p.id = ps.product_id 
        WHERE p.status != 'Discontinued' 
        AND (ps.status IS NULL OR ps.status != 'Defective')";

if ($categoryFilter !== 'all') {
    $sql .= " AND p.category = ?";
}

if (!empty($searchQuery)) {
    $sql .= " AND (p.name LIKE ? OR p.items LIKE ? OR p.category LIKE ?)";
}

$sql .= " GROUP BY p.name, p.category, p.price, p.items, p.photo, p.status";
$sql .= " ORDER BY p.name ASC";

$stmt = $db->prepare($sql);

if ($categoryFilter !== 'all' && !empty($searchQuery)) {
    $searchTerm = "%$searchQuery%";
    $stmt->bind_param("ssss", $categoryFilter, $searchTerm, $searchTerm, $searchTerm);
} elseif ($categoryFilter !== 'all') {
    $stmt->bind_param("s", $categoryFilter);
} elseif (!empty($searchQuery)) {
    $searchTerm = "%$searchQuery%";
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
}

$stmt->execute();
$result = $stmt->get_result();

// Function to check if comments table exists
function commentsTableExists($db) {
    $result = $db->query("SHOW TABLES LIKE 'product_comments'");
    return $result->num_rows > 0;
}

// Function to get comments for a product (with error handling)
function getProductComments($db, $productName) {
    if (!commentsTableExists($db)) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("SELECT * FROM product_comments WHERE product_name = ? AND status = 'approved' ORDER BY created_at DESC");
        $stmt->bind_param("s", $productName);
        $stmt->execute();
        return $stmt->get_result();
    } catch (Exception $e) {
        return false;
    }
}

// Function to get average rating (with error handling)
function getAverageRating($db, $productName) {
    if (!commentsTableExists($db)) {
        return ['avg_rating' => 0, 'total_reviews' => 0];
    }
    
    try {
        $stmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM product_comments WHERE product_name = ? AND status = 'approved'");
        $stmt->bind_param("s", $productName);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    } catch (Exception $e) {
        return ['avg_rating' => 0, 'total_reviews' => 0];
    }
}

// Handle comment submission (with error handling)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    // Validate and sanitize input
    $productName = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
    $username = isset($_POST['customer_name']) ? trim($_POST['customer_name']) : '';
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    
    // Validate required fields
    if (empty($productName) || empty($username) || empty($comment) || $rating < 1 || $rating > 5) {
        $error_message = "Please fill in all required fields with valid data.";
    } elseif (commentsTableExists($db)) {
        try {
            $stmt = $db->prepare("INSERT INTO product_comments (product_name, username, comment, rating) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $productName, $username, $comment, $rating);
            
            if ($stmt->execute()) {
                $success_message = "Thank you for your comment!";
            } else {
                $error_message = "Error submitting comment. Please try again.";
            }
        } catch (Exception $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    } else {
        $error_message = "Comment system is temporarily unavailable. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Products</title>
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
    }
    * { box-sizing: border-box; }
    body {
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial;
      background: var(--bg);
      color: #f9fafb;
      margin: 0;
      padding: 0;
    }
    header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 16px;
      background: var(--ink-2);
      box-shadow: 0 2px 0 rgba(0,0,0,.5);
      position: relative;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    header img {
      width: 48px;
      height: 48px;
      border-radius: 8px;
      border: 3px solid var(--ink-2);
      object-fit: contain;
    }
    header .title {
      font-weight: 700;
      font-size: 1.5rem;
      color: #f9fafb;
    }
    
    /* Burger Menu Styles */
    .burger-menu {
      position: relative;
    }
    
    .burger-btn {
      background: none;
      border: none;
      color: #f9fafb;
      font-size: 24px;
      cursor: pointer;
      padding: 8px;
      border-radius: 6px;
      transition: background 0.2s;
    }
    
    .burger-btn:hover {
      background: rgba(255,255,255,0.1);
    }
    
    .burger-dropdown {
      position: absolute;
      top: 100%;
      right: 0;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 8px 0;
      min-width: 180px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.5);
      display: none;
      z-index: 1000;
    }
    
    .burger-dropdown.show {
      display: block;
    }
    
    .burger-item {
      display: block;
      padding: 10px 16px;
      color: #f9fafb;
      text-decoration: none;
      transition: background 0.2s;
      border: none;
      background: none;
      width: 100%;
      text-align: left;
      font-size: 14px;
      cursor: pointer;
    }
    
    .burger-item:hover {
      background: rgba(255,255,255,0.1);
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
      max-width: 600px;
      width: 90%;
      max-height: 90vh;
      overflow-y: auto;
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
      border-radius: 4px;
      transition: background 0.2s;
    }
    
    .close-btn:hover {
      background: rgba(255,255,255,0.1);
    }
    
    .contact-info, .about-info {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }
    
    .contact-item, .about-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px;
      background: rgba(255,255,255,0.05);
      border-radius: 8px;
      border: 1px solid var(--border);
    }
    
    .contact-icon, .about-icon {
      width: 20px;
      height: 20px;
      color: var(--blue);
    }
    
    .contact-text, .about-text {
      color: #f9fafb;
      font-size: 14px;
    }
    
    .about-description {
      color: var(--muted);
      line-height: 1.6;
      margin-bottom: 20px;
    }
    
    .container {
      padding: 20px;
    }
    
    /* Combined Search and Filter Styles */
    .search-filters-container {
      display: flex;
      gap: 15px;
      align-items: center;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 15px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    
    .search-bar {
      flex: 1;
      min-width: 250px;
      display: flex;
      align-items: center;
      gap: 8px;
      background: #1f2937;
      border: 1px solid #374151;
      border-radius: 8px;
      padding: 10px 14px;
    }
    
    .search-bar input {
      border: 0;
      outline: 0;
      width: 100%;
      font-size: 14px;
      background: transparent;
      color: #fff;
    }
    
    .search-bar svg {
      width: 18px;
      height: 18px;
      color: var(--muted);
    }
    
    .filters {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    
    .filter-btn {
      background: #1f2937;
      border: 1px solid #374151;
      color: #e5e7eb;
      padding: 8px 16px;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s;
      font-size: 14px;
      text-decoration: none;
      white-space: nowrap;
    }
    
    .filter-btn:hover {
      background: #374151;
    }
    
    .filter-btn.active {
      background: var(--blue);
      border-color: var(--blue-600);
      color: white;
    }
    
    .clear-filters {
      color: var(--blue);
      text-decoration: none;
      font-size: 14px;
      white-space: nowrap;
      margin-left: auto;
    }
    
    .clear-filters:hover {
      text-decoration: underline;
    }
    
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 20px;
    }
    .card {
      background: var(--card);
      border-radius: 12px;
      padding: 15px;
      border: 1px solid var(--border);
      box-shadow: 0 2px 8px rgba(0,0,0,0.5);
      display: flex;
      flex-direction: column;
      align-items: center;
      transition: transform 0.2s, box-shadow 0.2s;
      position: relative;
      cursor: pointer;
    }
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.7);
    }
    .card img {
      width: 100%;
      height: 200px;
      object-fit: cover;
      border-radius: 8px;
      margin-bottom: 10px;
      border: 2px solid var(--border);
      background: #222;
    }
    .card h3 {
      margin: 0;
      color: #f9fafb;
      font-size: 1.2rem;
      text-align: center;
    }
    .price {
      color: var(--blue);
      font-size: 1rem;
      font-weight: bold;
      margin: 5px 0;
    }
    .specs {
      font-size: 0.9rem;
      color: var(--muted);
      text-align: center;
      margin-bottom: 8px;
    }
    
    /* Stock Badge Styles */
    .stock-badge {
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
      margin-top: 5px;
    }
    
    .stock-in-stock {
      background: #065f46;
      color: #fff;
    }
    
    .stock-low {
      background: #92400e;
      color: #fff;
    }
    
    .stock-out {
      background: #991b1b;
      color: #fff;
    }
    
    .category-badge {
      background: var(--blue);
      color: white;
      padding: 4px 8px;
      border-radius: 6px;
      font-size: 0.8rem;
      margin-top: 8px;
    }
    
    /* No results message */
    .no-results {
      text-align: center;
      color: var(--muted);
      font-size: 1.1rem;
      padding: 40px;
      grid-column: 1 / -1;
    }
    
    /* Floating Action Buttons */
    .action-buttons {
      position: fixed;
      bottom: 20px;
      right: 20px;
      display: flex;
      flex-direction: column;
      gap: 15px;
      z-index: 1000;
    }
    
    .action-btn {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      transition: transform 0.3s, background 0.3s;
      color: white;
    }
    
    .action-btn:hover {
      transform: scale(1.05);
    }
    
    .chatbot-btn {
      background: #43a047;
    }
    
    .chatbot-btn:hover {
      background: #388e3c;
    }
    
    .action-btn svg {
      width: 24px;
      height: 24px;
    }

    /* Comment Modal Styles */
    .comment-modal {
      max-width: 800px;
    }
    
    .comment-form {
      display: flex;
      flex-direction: column;
      gap: 15px;
      margin-bottom: 30px;
      padding-bottom: 20px;
      border-bottom: 1px solid var(--border);
    }
    
    .form-group {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }
    
    .form-label {
      color: #f9fafb;
      font-size: 14px;
      font-weight: 500;
    }
    
    .form-input, .form-textarea {
      background: #1f2937;
      border: 1px solid #374151;
      border-radius: 6px;
      padding: 10px 12px;
      color: #f9fafb;
      font-size: 14px;
      font-family: inherit;
    }
    
    .form-textarea {
      min-height: 100px;
      resize: vertical;
    }
    
    .form-input:focus, .form-textarea:focus {
      outline: none;
      border-color: var(--blue);
    }
    
    .submit-btn {
      background: var(--blue);
      color: white;
      border: none;
      padding: 12px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      transition: background 0.2s;
    }
    
    .submit-btn:hover {
      background: var(--blue-600);
    }
    
    .product-preview {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 15px;
      background: rgba(255,255,255,0.05);
      border-radius: 8px;
      margin-bottom: 20px;
    }
    
    .product-preview img {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border-radius: 6px;
      border: 1px solid var(--border);
    }
    
    .product-preview-info {
      flex: 1;
    }
    
    .product-preview-name {
      color: #f9fafb;
      font-size: 16px;
      font-weight: 600;
      margin: 0 0 5px 0;
    }
    
    .product-preview-price {
      color: var(--blue);
      font-size: 14px;
      font-weight: 600;
      margin: 0;
    }
    
    /* Comments Section Styles */
    .comments-section {
      margin-top: 20px;
    }
    
    .comments-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    
    .comments-title {
      font-size: 1.2rem;
      font-weight: 600;
      color: #f9fafb;
      margin: 0;
    }
    
    .average-rating {
      display: flex;
      align-items: center;
      gap: 10px;
      background: rgba(255,255,255,0.05);
      padding: 8px 12px;
      border-radius: 6px;
    }
    
    .rating-stars {
      color: #fbbf24;
    }
    
    .rating-text {
      font-size: 14px;
      color: var(--muted);
    }
    
    .comments-list {
      display: flex;
      flex-direction: column;
      gap: 15px;
      max-height: 400px;
      overflow-y: auto;
    }
    
    .comment-item {
      background: rgba(255,255,255,0.05);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 15px;
    }
    
    .comment-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
    }
    
    .comment-user {
      font-weight: 600;
      color: #f9fafb;
    }
    
    .comment-rating {
      color: #fbbf24;
    }
    
    .comment-date {
      color: var(--muted);
      font-size: 12px;
    }
    
    .comment-text {
      color: #f9fafb;
      line-height: 1.5;
      margin: 0;
    }
    
    .no-comments {
      text-align: center;
      color: var(--muted);
      padding: 20px;
      background: rgba(255,255,255,0.05);
      border-radius: 8px;
    }
    
    /* Alert Messages */
    .alert {
      padding: 12px 16px;
      border-radius: 6px;
      margin-bottom: 15px;
    }
    
    .alert-success {
      background: #065f46;
      color: #fff;
      border: 1px solid #047857;
    }
    
    .alert-error {
      background: #7f1d1d;
      color: #fff;
      border: 1px solid #991b1b;
    }
    
    /* Mobile responsive */
    @media (max-width: 768px) {
      .search-filters-container {
        flex-direction: column;
        align-items: stretch;
      }
      
      .search-bar {
        min-width: auto;
      }
      
      .filters {
        justify-content: center;
      }
      
      .clear-filters {
        margin-left: 0;
        text-align: center;
      }
      
      .action-buttons {
        bottom: 10px;
        right: 10px;
      }
      
      .action-btn {
        width: 50px;
        height: 50px;
      }
      
      .action-btn svg {
        width: 20px;
        height: 20px;
      }
      
      .product-preview {
        flex-direction: column;
        text-align: center;
      }
      
      .comments-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
      }
    }
  </style>
</head>
<body>
  <header>
    <div class="brand">
      <img src="img/jhay-gadget-logo.png.jpg" alt="JHAY GADGET">
      <div class="title">JHAY GADGET · Products</div>
    </div>
    
    <!-- Burger Menu -->
    <div class="burger-menu">
      <button class="burger-btn" id="burgerBtn">☰</button>
      <div class="burger-dropdown" id="burgerDropdown">
        <button class="burger-item" id="contactBtn">Contact Us</button>
        <button class="burger-item" id="aboutBtn">About Us</button>
        <!-- ✅ Added Logout button -->
        <a href="logout.php" class="burger-item" style="color: #ef4444;">Logout</a>
      </div>
    </div>
  </header>

  <!-- About Us Modal -->
  <div class="modal" id="aboutModal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title">About Us</h2>
        <button class="close-btn" id="closeAboutModal">&times;</button>
      </div>
      <div class="about-info">
        <div class="about-description">
          <p>Welcome to JHAY GADGET - your trusted partner for the latest iPhone in technology. We specialize in providing high-quality gadgets, accessories, and tech repair.</p>
          <p>With years of experience in the industry, we pride ourselves on offering genuine products, affordable prices, and exceptional customer service.</p>
        </div>
        <div class="about-item">
          <svg class="about-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          <span class="about-text">Honest and reliable service</span>
        </div>
        <div class="about-item">
          <svg class="about-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
          </svg>
          <span class="about-text">100% Genuine Products Guaranteed</span>
        </div>
        <div class="about-item">
          <svg class="about-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
          </svg>
          <span class="about-text">Trusted Seller since 2016</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Contact Us Modal -->
  <div class="modal" id="contactModal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title">Contact Us</h2>
        <button class="close-btn" id="closeContactModal">&times;</button>
      </div>
      <div class="contact-info">
        <div class="contact-item">
          <svg class="contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
            <circle cx="12" cy="10" r="3"></circle>
          </svg>
          <span class="contact-text">Champion Bldg., La Purisima, Zamboanga City, Zamboanga City, Philippines</span>
        </div>
        <div class="contact-item">
          <svg class="contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
          </svg>
          <span class="contact-text">0905-483-2512</span>
        </div>
        <div class="contact-item">
          <svg class="contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
            <polyline points="22,6 12,13 2,6"></polyline>
          </svg>
          <span class="contact-text">jhaygadget@gmail.com</span>
        </div>
        <div class="contact-item">
          <svg class="contact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="16" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
          </svg>
          <span class="contact-text">Monday - Saturday: 9:00 AM - 8:00 PM</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Comment Modal -->
  <div class="modal" id="commentModal">
    <div class="modal-content comment-modal">
      <div class="modal-header">
        <h2 class="modal-title">Product Reviews</h2>
        <button class="close-btn" id="closeCommentModal">&times;</button>
      </div>
      
      <div class="product-preview" id="productPreview">
        <img id="previewImage" src="" alt="Product Image">
        <div class="product-preview-info">
          <h3 class="product-preview-name" id="previewName"></h3>
          <p class="product-preview-price" id="previewPrice"></p>
        </div>
      </div>
      
      <!-- Comment Form -->
      <form class="comment-form" id="commentForm" method="POST" action="">
        <input type="hidden" id="productName" name="product_name">
        <input type="hidden" name="submit_comment" value="1">
        
        <?php if (isset($success_message)): ?>
          <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
          <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="form-group">
          <label class="form-label">Account Name</label>
          <input type="text" class="form-input" id="customerName" name="customer_name" value="<?php echo htmlspecialchars($currentUsername); ?>" readonly style="background: #2d3748; color: #9ca3af;">
          <small style="color: var(--muted); font-size: 12px;">Automatically using your logged-in account</small>
        </div>
        
        <div class="form-group">
          <label class="form-label" for="comment">Your Review</label>
          <textarea class="form-textarea" id="comment" name="comment" required placeholder="Share your experience with this product..."></textarea>
        </div>
        
        <div class="form-group">
          <label class="form-label" for="rating">Rating</label>
          <select class="form-input" id="rating" name="rating" required>
            <option value="">Select rating</option>
            <option value="5">⭐⭐⭐⭐⭐ (5/5) - Excellent</option>
            <option value="4">⭐⭐⭐⭐ (4/5) - Very Good</option>
            <option value="3">⭐⭐⭐ (3/5) - Good</option>
            <option value="2">⭐⭐ (2/5) - Fair</option>
            <option value="1">⭐ (1/5) - Poor</option>
          </select>
        </div>
        
        <button type="submit" class="submit-btn">Submit Review</button>
      </form>
      
      <!-- Comments Display Section -->
      <div class="comments-section" id="commentsSection">
        <!-- Comments will be loaded here via JavaScript -->
      </div>
    </div>
  </div>

  <div class="container">
    <!-- Combined Search and Filters in one row -->
    <div class="search-filters-container">
      <!-- Search Bar -->
      <div class="search-bar">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/>
          <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <form method="GET" style="display: flex; width: 100%; gap: 10px; align-items: center;">
          <input 
            type="text" 
            name="search" 
            placeholder="Search products..." 
            value="<?php echo htmlspecialchars($searchQuery); ?>"
            style="flex: 1;"
          >
          <input type="hidden" name="category" value="<?php echo htmlspecialchars($categoryFilter); ?>">
          <button type="submit" style="
            background: var(--blue);
            border: 1px solid var(--blue-600);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            white-space: nowrap;
          ">Search</button>
        </form>
      </div>

      <!-- Category Filters -->
      <div class="filters">
        <a href="?search=<?php echo urlencode($searchQuery); ?>&category=all" class="filter-btn <?php echo $categoryFilter === 'all' ? 'active' : ''; ?>">
          All
        </a>
        <a href="?search=<?php echo urlencode($searchQuery); ?>&category=tech accessories" class="filter-btn <?php echo $categoryFilter === 'tech accessories' ? 'active' : ''; ?>">
          Accessories
        </a>
        <a href="?search=<?php echo urlencode($searchQuery); ?>&category=laptop" class="filter-btn <?php echo $categoryFilter === 'laptop' ? 'active' : ''; ?>">
          Laptops
        </a>
        <a href="?search=<?php echo urlencode($searchQuery); ?>&category=phone" class="filter-btn <?php echo $categoryFilter === 'phone' ? 'active' : ''; ?>">
          Phones
        </a>
      </div>

      <?php if (!empty($searchQuery) || $categoryFilter !== 'all'): ?>
        <a href="?" class="clear-filters">Clear all</a>
      <?php endif; ?>
    </div>

    <div class="grid">
      <?php 
      $hasResults = false;
      while($row = $result->fetch_assoc()): 
        $hasResults = true;
        // Determine stock status and color based on available stock (excluding defective)
        $available_stock = $row['available_stock'];
        
        if ($available_stock > 10) {
            $stockClass = 'stock-in-stock';
            $stockText = "$available_stock in stock";
        } elseif ($available_stock > 0) {
            $stockClass = 'stock-low';
            $stockText = "Only $available_stock left";
        } else {
            $stockClass = 'stock-out';
            $stockText = 'Out of stock';
        }
        
        // Get average rating for this product (with error handling)
        $ratingData = getAverageRating($db, $row['name']);
        $avgRating = $ratingData['avg_rating'] ? round($ratingData['avg_rating'], 1) : 0;
        $totalReviews = $ratingData['total_reviews'];
        
        // FIX: Get the correct photo path
        $photoDisplayPath = '';
        if (!empty($row['photo'])) {
            // Try multiple possible locations
            if (file_exists($row['photo'])) {
                $photoDisplayPath = $row['photo'];
            } elseif (file_exists('uploads/products/' . $row['photo'])) {
                $photoDisplayPath = 'uploads/products/' . $row['photo'];
            } elseif (file_exists('uploads/' . $row['photo'])) {
                $photoDisplayPath = 'uploads/' . $row['photo'];
            } elseif (file_exists('img/' . $row['photo'])) {
                $photoDisplayPath = 'img/' . $row['photo'];
            } elseif (file_exists('img/products/' . $row['photo'])) {
                $photoDisplayPath = 'img/products/' . $row['photo'];
            }
        }
        
        // Get first letter for fallback
        $firstLetter = strtoupper(substr($row['name'], 0, 1));
      ?>
        <div class="card" onclick="openCommentModal(
          '<?php echo htmlspecialchars($row['name']); ?>',
          '<?php echo htmlspecialchars($photoDisplayPath ?: $row['photo']); ?>',
          '<?php echo number_format($row['price'], 2); ?>'
        )">
          <!-- FIXED PHOTO DISPLAY - Only changed this part -->
          <?php if (!empty($photoDisplayPath)): ?>
            <img src="<?php echo htmlspecialchars($photoDisplayPath); ?>" 
                 alt="<?php echo htmlspecialchars($row['name']); ?>"
                 onerror="this.onerror=null; this.parentElement.innerHTML='<div style=\'width:100%;height:200px;background:#222;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:48px;font-weight:bold;border:2px solid var(--border);\'><?php echo $firstLetter; ?></div>';">
          <?php else: ?>
            <div style="width:100%;height:200px;background:#222;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:48px;font-weight:bold;border:2px solid var(--border);">
              <?php echo $firstLetter; ?>
            </div>
          <?php endif; ?>
          
          <h3><?php echo htmlspecialchars($row['name']); ?></h3>
          <div class="price">₱<?php echo number_format($row['price'], 2); ?></div>
          <div class="specs"><?php echo htmlspecialchars($row['items']); ?></div>
          
          <!-- Display average rating if available -->
          <?php if ($avgRating > 0): ?>
            <div style="margin: 5px 0; color: #fbbf24; font-size: 0.9rem;">
              <?php echo str_repeat('⭐', round($avgRating)); ?>
              <span style="color: var(--muted);">(<?php echo $avgRating; ?>)</span>
            </div>
          <?php endif; ?>
          
          <div class="stock-badge <?php echo $stockClass; ?>">
            <?php echo $stockText; ?>
          </div>
          <div class="category-badge">
            <?php echo htmlspecialchars(ucfirst($row['category'])); ?>
          </div>
        </div>
      <?php endwhile; ?>
      
      <?php if (!$hasResults): ?>
        <div class="no-results">
          <h3>No products found</h3>
          <p>Try adjusting your search terms or filters</p>
          <a href="?" class="clear-filters">Clear all filters</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Action Buttons - Only Chatbot Button Remains -->
  <div class="action-buttons">
    <!-- Chatbot Button -->
    <button class="action-btn chatbot-btn" id="chatbotToggle" title="Chat with us">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
      </svg>
    </button>
  </div>

  <script>
    // Burger Menu Toggle
    const burgerBtn = document.getElementById('burgerBtn');
    const burgerDropdown = document.getElementById('burgerDropdown');
    const contactBtn = document.getElementById('contactBtn');
    const aboutBtn = document.getElementById('aboutBtn');
    const contactModal = document.getElementById('contactModal');
    const aboutModal = document.getElementById('aboutModal');
    const closeContactModal = document.getElementById('closeContactModal');
    const closeAboutModal = document.getElementById('closeAboutModal');

    // Comment Modal Elements
    const commentModal = document.getElementById('commentModal');
    const closeCommentModal = document.getElementById('closeCommentModal');
    const commentForm = document.getElementById('commentForm');
    const previewImage = document.getElementById('previewImage');
    const previewName = document.getElementById('previewName');
    const previewPrice = document.getElementById('previewPrice');
    const productNameInput = document.getElementById('productName');
    const commentsSection = document.getElementById('commentsSection');

    // Chatbot Button
    const chatbotToggle = document.getElementById('chatbotToggle');

    // Toggle burger dropdown
    burgerBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      burgerDropdown.classList.toggle('show');
    });

    // Close burger dropdown when clicking outside
    document.addEventListener('click', () => {
      burgerDropdown.classList.remove('show');
    });

    // Prevent burger dropdown from closing when clicking inside
    burgerDropdown.addEventListener('click', (e) => {
      e.stopPropagation();
    });

    // Open contact modal
    contactBtn.addEventListener('click', () => {
      contactModal.classList.add('show');
      burgerDropdown.classList.remove('show');
    });

    // Open about modal
    aboutBtn.addEventListener('click', () => {
      aboutModal.classList.add('show');
      burgerDropdown.classList.remove('show');
    });

    // Open comment modal and load comments
    function openCommentModal(name, photo, price) {
      // FIXED: Handle photo path for preview
      let previewSrc = '';
      if (photo) {
        // Try to use the photo path directly
        previewSrc = photo;
      }
      
      previewImage.src = previewSrc;
      previewImage.onerror = function() {
        // If image fails to load, show first letter in preview
        const firstLetter = name.charAt(0).toUpperCase();
        this.style.display = 'none';
        const container = this.parentElement;
        // Check if fallback already exists
        if (!container.querySelector('.preview-fallback')) {
          const fallback = document.createElement('div');
          fallback.className = 'preview-fallback';
          fallback.style.cssText = 'width:80px;height:80px;border-radius:50%;background:var(--blue);display:flex;align-items:center;justify-content:center;color:white;font-size:32px;font-weight:bold;';
          fallback.textContent = firstLetter;
          container.appendChild(fallback);
        }
      };
      
      previewName.textContent = name;
      previewPrice.textContent = '₱' + price;
      productNameInput.value = name;
      
      // Set the customer name field
      document.getElementById('customerName').value = '<?php echo htmlspecialchars($currentUsername); ?>';
      
      // Clear any previous messages
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => alert.remove());
      
      // Reset form
      document.getElementById('commentForm').reset();
      
      // Load comments for this product
      loadComments(name);
      
      commentModal.classList.add('show');
    }

    // Function to load comments via AJAX
    function loadComments(productName) {
      const xhr = new XMLHttpRequest();
      xhr.open('GET', `get_comments.php?product_name=${encodeURIComponent(productName)}`, true);
      xhr.onload = function() {
        if (xhr.status === 200) {
          commentsSection.innerHTML = xhr.responseText;
        } else {
          commentsSection.innerHTML = '<div class="no-comments">Error loading comments</div>';
        }
      };
      xhr.send();
    }

    // Close comment modal
    closeCommentModal.addEventListener('click', () => {
      commentModal.classList.remove('show');
    });

    // Redirect to chatbot page
    chatbotToggle.addEventListener('click', () => {
      window.location.href = 'chatbot.php';
    });

    // Close contact modal
    closeContactModal.addEventListener('click', () => {
      contactModal.classList.remove('show');
    });

    // Close about modal
    closeAboutModal.addEventListener('click', () => {
      aboutModal.classList.remove('show');
    });

    // Close modals when clicking outside
    contactModal.addEventListener('click', (e) => {
      if (e.target === contactModal) {
        contactModal.classList.remove('show');
      }
    });

    aboutModal.addEventListener('click', (e) => {
      if (e.target === aboutModal) {
        aboutModal.classList.remove('show');
      }
    });

    commentModal.addEventListener('click', (e) => {
      if (e.target === commentModal) {
        commentModal.classList.remove('show');
      }
    });

    // Close modals with Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        contactModal.classList.remove('show');
        aboutModal.classList.remove('show');
        commentModal.classList.remove('show');
      }
    });

    // Handle comment form submission
    commentForm.addEventListener('submit', function(e) {
      // Validate form before submission
      const comment = document.getElementById('comment').value.trim();
      const rating = document.getElementById('rating').value;
      const productName = document.getElementById('productName').value;
      
      if (!comment) {
        e.preventDefault();
        alert('Please enter your comment.');
        return;
      }
      
      if (!rating) {
        e.preventDefault();
        alert('Please select a rating.');
        return;
      }
      
      if (!productName) {
        e.preventDefault();
        alert('Product information is missing. Please try again.');
        return;
      }
      
      // Form will submit normally since we're using POST method
      // The page will reload and show success message
    });
  </script>
</body>
</html>
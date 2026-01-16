<?php
session_start();
include('db.php');

function commentsTableExists($db) {
    $result = $db->query("SHOW TABLES LIKE 'product_comments'");
    return $result->num_rows > 0;
}

if (isset($_GET['product_name'])) {
    $productName = $_GET['product_name'];
    
    if (!commentsTableExists($db)) {
        echo '<div class="no-comments">';
        echo '<p>Comment system is being set up. Please check back later.</p>';
        echo '</div>';
        exit;
    }
    
    // Get comments for this product (only approved ones)
    $stmt = $db->prepare("SELECT * FROM product_comments WHERE product_name = ? AND status = 'approved' ORDER BY created_at DESC");
    $stmt->bind_param("s", $productName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Get average rating
    $ratingStmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM product_comments WHERE product_name = ? AND status = 'approved'");
    $ratingStmt->bind_param("s", $productName);
    $ratingStmt->execute();
    $ratingData = $ratingStmt->get_result()->fetch_assoc();
    $avgRating = $ratingData['avg_rating'] ? round($ratingData['avg_rating'], 1) : 0;
    $totalReviews = $ratingData['total_reviews'];
    
    if ($result->num_rows > 0) {
        echo '<div class="comments-header">';
        echo '<h3 class="comments-title">Customer Reviews</h3>';
        if ($avgRating > 0) {
            echo '<div class="average-rating">';
            echo '<div class="rating-stars">' . str_repeat('⭐', round($avgRating)) . '</div>';
            echo '<div class="rating-text">' . $avgRating . ' / 5 (' . $totalReviews . ' reviews)</div>';
            echo '</div>';
        }
        echo '</div>';
        
        echo '<div class="comments-list">';
        while($comment = $result->fetch_assoc()) {
            echo '<div class="comment-item">';
            echo '<div class="comment-header">';
            echo '<div class="comment-user">' . htmlspecialchars($comment['username']) . '</div>';
            echo '<div class="comment-rating">' . str_repeat('⭐', $comment['rating']) . '</div>';
            echo '</div>';
            echo '<div class="comment-date">' . date('F j, Y', strtotime($comment['created_at'])) . '</div>';
            echo '<p class="comment-text">' . nl2br(htmlspecialchars($comment['comment'])) . '</p>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="no-comments">';
        echo '<p>No reviews yet. Be the first to review this product!</p>';
        echo '</div>';
    }
}
?>
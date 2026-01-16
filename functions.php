<?php
// functions.php
function logActivity($db, $user_id, $action, $details = '', $username = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    // If username not provided, get it from database
    if (!$username && $user_id) {
        $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $username = $row['username'];
        }
        $stmt->close();
    }
    
    // Check if activity_log table exists, if not create it
    $tableCheck = $db->query("SHOW TABLES LIKE 'activity_log'");
    if ($tableCheck->num_rows == 0) {
        $db->query("
            CREATE TABLE activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                username VARCHAR(100),
                action VARCHAR(255) NOT NULL,
                details TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                activity_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
    }
    
    $stmt = $db->prepare("
        INSERT INTO activity_log (user_id, username, action, details, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isssss", $user_id, $username, $action, $details, $ip_address, $user_agent);
    $stmt->execute();
    $stmt->close();
    
    return true;
}
?>
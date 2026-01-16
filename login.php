<?php 
session_start();
include 'db.php';

// Check if activity_log table exists, if not create it
$tableCheck = $db->query("SHOW TABLES LIKE 'activity_log'");
if ($tableCheck->num_rows == 0) {
    // Create the activity_log table
    $createTable = $db->query("
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

// If user is already logged in, redirect based on role
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {

    if ($_SESSION['role'] === 'admin') {
        header('Location: fronttae-admin.php'); // Admin page
    } elseif ($_SESSION['role'] === 'employee') {
        header('Location: fronttae.php'); // Employee inventory page
    } else {
        header('Location: products-view.php'); // Normal user
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {

        $stmt = $db->prepare("SELECT id, username, password, role FROM users WHERE BINARY username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            // VALIDATE PASSWORD
            if ($password === $user['password'] || password_verify($password, $user['password'])) {

                // UPDATE LAST LOGIN TIMESTAMP
                $update_stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
                $update_stmt->close();

                // Set session
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['logged_in'] = true;

                // RECORD LOGIN ACTIVITY
                $action = "Login";
                $details = "User logged in successfully";
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                
                $log_stmt = $db->prepare("INSERT INTO activity_log (user_id, username, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
                $log_stmt->bind_param("isssss", $user['id'], $user['username'], $action, $details, $ip_address, $user_agent);
                $log_stmt->execute();
                $log_stmt->close();

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header('Location: fronttae-admin.php');
                } elseif ($user['role'] === 'employee') {
                    header('Location: fronttae.php');
                } else {
                    header('Location: products-view.php');
                }

                exit();

            } else {
                $error = 'Invalid username or password.';
                
                // Record failed login attempt
                $action = "Failed Login";
                $details = "Failed login attempt for username: " . $username;
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                
                $log_stmt = $db->prepare("INSERT INTO activity_log (user_id, username, action, details, ip_address, user_agent) VALUES (NULL, ?, ?, ?, ?, ?)");
                $log_stmt->bind_param("sssss", $username, $action, $details, $ip_address, $user_agent);
                $log_stmt->execute();
                $log_stmt->close();
            }

        } else {
            $error = 'Invalid username or password.';
            
            // Record failed login attempt for non-existent user
            $action = "Failed Login";
            $details = "Failed login attempt for non-existent username: " . $username;
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            
            $log_stmt = $db->prepare("INSERT INTO activity_log (user_id, username, action, details, ip_address, user_agent) VALUES (NULL, ?, ?, ?, ?, ?)");
            $log_stmt->bind_param("sssss", $username, $action, $details, $ip_address, $user_agent);
            $log_stmt->execute();
            $log_stmt->close();
        }

        $stmt->close();

    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - JHAY GADGET</title>
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
      --green:#43a047;
      --red:#e53935;
    }
    * { 
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial;
      background: var(--bg);
      color: #f9fafb;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .login-container { width: 100%; max-width: 400px; }
    .login-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 40px 30px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    }
    .brand-header { text-align: center; margin-bottom: 30px; }
    .brand-logo {
      width: 80px; height: 80px; border-radius: 12px;
      border: 3px solid var(--blue); object-fit: contain;
      margin: 0 auto 15px; display: block;
    }
    .brand-title { font-size: 2rem; font-weight: 700; color: #f9fafb; margin-bottom: 5px; }
    .brand-subtitle { color: var(--muted); font-size: 1rem; }
    .login-form { display: flex; flex-direction: column; gap: 20px; }
    .form-group { display: flex; flex-direction: column; gap: 8px; }
    .form-label { color: #f9fafb; font-size: 14px; font-weight: 500; }
    .form-input {
      background: #1f2937; border: 1px solid #374151;
      border-radius: 8px; padding: 12px 16px; color: #fff;
      font-size: 14px; transition: all 0.2s;
    }
    .form-input:focus {
      outline: none; border-color: var(--blue);
      box-shadow: 0 0 0 3px rgba(30,136,229,0.1);
    }
    .form-input::placeholder { color: var(--muted); }
    .login-btn {
      background: var(--blue); border: 1px solid var(--blue-600);
      color: white; padding: 12px 20px; border-radius: 8px;
      font-size: 16px; font-weight: 600; cursor: pointer;
      transition: background 0.2s; margin-top: 10px;
    }
    .login-btn:hover { background: var(--blue-600); }
    .login-btn:active { transform: translateY(1px); }
    .error-message {
      background: var(--red); color: white; padding: 12px 16px;
      border-radius: 8px; font-size: 14px; text-align: center;
      margin-bottom: 15px; border: 1px solid #dc2626;
    }
    .footer-text { text-align: center; color: var(--muted); font-size: 12px; margin-top: 20px; }
    .loading { display: none; text-align: center; color: var(--blue); font-size: 14px; }
    .loading.show { display: block; }
    @media (max-width: 480px) {
      .login-card { padding: 30px 20px; }
      .brand-logo { width: 60px; height: 60px; }
      .brand-title { font-size: 1.5rem; }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-card">
      <div class="brand-header">
        <img src="img/jhay-gadget-logo.png.jpg" alt="JHAY GADGET" class="brand-logo">
        <h1 class="brand-title">JHAY GADGET</h1>
        <p class="brand-subtitle">Login</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form class="login-form" method="POST" id="loginForm">
        <div class="form-group">
          <label for="username" class="form-label">Username</label>
          <input type="text" id="username" name="username" class="form-input" placeholder="Enter your username" required autocomplete="username">
        </div>
        <div class="form-group">
          <label for="password" class="form-label">Password</label>
          <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required autocomplete="current-password">
        </div>
        <button type="submit" class="login-btn" id="loginBtn">Sign In</button>
        <div class="loading" id="loading">Signing in...</div>
      </form>

      <div class="footer-text">
        Don't have an account? <a href="register.php" style="color:#1e88e5;text-decoration:none;font-weight:500;">Create one</a>
      </div>

      <div class="footer-text" style="margin-top:10px;">&copy; 2025 JHAY GADGET. All rights reserved.</div>
    </div>
  </div>

  <script>
    document.getElementById('loginForm').addEventListener('submit', function() {
      const loginBtn = document.getElementById('loginBtn');
      const loading = document.getElementById('loading');
      loginBtn.style.display = 'none';
      loading.classList.add('show');
    });
  </script>
</body>
</html>
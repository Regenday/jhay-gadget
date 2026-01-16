<?php
session_start();
include 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];
    $role = 'user'; // Users can ONLY register as "user"

    if (!empty($username) && !empty($password) && !empty($confirm)) {
        if ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            // Check if username already exists
            $check = $db->prepare("SELECT id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $error = 'Username already exists.';
            } else {
                // Secure password hashing
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $hashedPassword, $role);

                if ($stmt->execute()) {
                    $success = 'Account created successfully! You can now log in.';
                } else {
                    $error = 'Something went wrong. Please try again.';
                }

                $stmt->close();
            }

            $check->close();
        }
    } else {
        $error = 'All fields are required.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account - JHAY GADGET</title>
  <style>
    :root {
      --blue:#1e88e5;
      --blue-600:#1976d2;
      --ink:#0f172a;
      --card:#111;
      --border:#333;
      --muted:#9ca3af;
      --red:#e53935;
      --green:#43a047;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body {
      font-family: Inter, system-ui, Arial;
      background:#000;
      color:#f9fafb;
      display:flex;
      align-items:center;
      justify-content:center;
      min-height:100vh;
      padding:20px;
    }
    .register-container {width:100%;max-width:400px;}
    .register-card {
      background:var(--card);
      border:1px solid var(--border);
      border-radius:16px;
      padding:40px 30px;
      box-shadow:0 8px 32px rgba(0,0,0,0.3);
    }
    .brand-header{text-align:center;margin-bottom:30px;}
    .brand-logo{
      width:80px;height:80px;border-radius:12px;
      border:3px solid var(--blue);margin:0 auto 15px;display:block;
    }
    .brand-title{font-size:2rem;font-weight:700;}
    .brand-subtitle{color:var(--muted);}
    .form-group{margin-bottom:20px;}
    .form-label{display:block;margin-bottom:6px;font-size:14px;font-weight:500;}
    .form-input{
      width:100%;padding:12px 16px;border-radius:8px;
      border:1px solid #374151;background:#1f2937;color:#fff;font-size:14px;
    }
    .form-input:focus{outline:none;border-color:var(--blue);}
    .register-btn{
      width:100%;background:var(--blue);color:white;
      border:1px solid var(--blue-600);padding:12px 20px;
      border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;
    }
    .register-btn:hover{background:var(--blue-600);}
    .error-message, .success-message{
      text-align:center;padding:12px 16px;border-radius:8px;margin-bottom:15px;
      font-size:14px;
    }
    .error-message{background:var(--red);border:1px solid #dc2626;}
    .success-message{background:var(--green);border:1px solid #2e7d32;}
    .footer-text{text-align:center;color:var(--muted);font-size:12px;margin-top:20px;}
    a{color:var(--blue);text-decoration:none;}
  </style>
</head>
<body>
  <div class="register-container">
    <div class="register-card">
      <div class="brand-header">
        <img src="img/jhay-gadget-logo.png.jpg" alt="JHAY GADGET" class="brand-logo">
        <h1 class="brand-title">Create Account</h1>
        <p class="brand-subtitle">JHAY GADGET Inventory System</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="form-group">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-input" placeholder="Enter username" required>
        </div>

        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-input" placeholder="Enter password" required>
        </div>

        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm" class="form-input" placeholder="Confirm password" required>
        </div>

        <button type="submit" class="register-btn">Create Account</button>
      </form>

      <div class="footer-text">
        Already have an account? <a href="login.php">Sign in</a>
      </div>
    </div>
  </div>
</body>
</html>

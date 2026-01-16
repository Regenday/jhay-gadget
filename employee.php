<?php
session_start();
include 'db.php';

// Allow only admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];
    $role = "employee"; // Employees ONLY

    if (!empty($username) && !empty($password) && !empty($confirm)) {
        if ($password !== $confirm) {
            $error = "Passwords do not match.";
        } else {
            $check = $db->prepare("SELECT id FROM users WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            $exists = $check->get_result();

            if ($exists->num_rows > 0) {
                $error = "Username already exists.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $hashed, $role);

                if ($stmt->execute()) {
                    $success = "Employee account created successfully!";
                } else {
                    $error = "Something went wrong.";
                }
                $stmt->close();
            }
        }
    } else {
        $error = "All fields are required.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Create Employee - Admin</title>
<style>
body { background:#000; color:white; font-family:Arial; display:flex; justify-content:center; padding-top:40px; }
.card { background:#111; padding:25px; border-radius:10px; width:400px; border:1px solid #333; }
input { width:100%; padding:10px; margin-bottom:15px; background:#1f2937; border:1px solid #333; color:white; border-radius:6px; }
button { padding:12px; background:#1e88e5; border:none; border-radius:6px; color:white; cursor:pointer; width:100%; }
.error { background:#e53935; padding:10px; border-radius:6px; margin-bottom:10px; }
.success { background:#43a047; padding:10px; border-radius:6px; margin-bottom:10px; }
</style>
</head>
<body>

<div class="card">
<h2>Create Employee Account</h2>

<?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>

<form method="POST">
    <input type="text" name="username" placeholder="Employee Username" required>
    <input type="password" name="password" placeholder="Employee Password" required>
    <input type="password" name="confirm" placeholder="Confirm Password" required>
    <button type="submit">Create Employee</button>
</form>

</div>

</body>
</html>

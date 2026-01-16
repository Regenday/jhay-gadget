<?php 
include 'db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch all users
$users = [];
$stmt = $db->prepare("SELECT id, username, role, created_at, last_login, is_active FROM users ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $user_id = intval($_POST['user_id'] ?? 0);
        
        switch($_POST['action']) {
            case 'toggle_active':
                // Get current status
                $stmt = $db->prepare("SELECT is_active FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $current_status = $row['is_active'];
                    
                    $new_status = $current_status ? 0 : 1;
                    
                    // Update status
                    $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                    $stmt->bind_param("ii", $new_status, $user_id);
                    $stmt->execute();
                    
                    // Log the activity
                    $admin_id = $_SESSION['user_id'];
                    $admin_username = $_SESSION['username'];
                    $log_stmt = $db->prepare("INSERT INTO activity_log (user_id, username, action, details, ip_address, user_agent) VALUES (?, ?, 'User Status Updated', ?, ?, ?)");
                    $details = "User ID $user_id status changed to " . ($new_status ? 'Active' : 'Inactive');
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $agent = $_SERVER['HTTP_USER_AGENT'];
                    $log_stmt->bind_param("issss", $admin_id, $admin_username, $details, $ip, $agent);
                    $log_stmt->execute();
                }
                $stmt->close();
                break;
                
            case 'change_role':
                $new_role = $_POST['new_role'] ?? '';
                // Validate role
                if (in_array($new_role, ['admin', 'employee', 'user']) && $user_id > 0) {
                    $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $stmt->bind_param("si", $new_role, $user_id);
                    $stmt->execute();
                    
                    // Log the activity
                    $admin_id = $_SESSION['user_id'];
                    $admin_username = $_SESSION['username'];
                    $log_stmt = $db->prepare("INSERT INTO activity_log (user_id, username, action, details, ip_address, user_agent) VALUES (?, ?, 'User Role Updated', ?, ?, ?)");
                    $details = "User ID $user_id role changed to $new_role";
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $agent = $_SERVER['HTTP_USER_AGENT'];
                    $log_stmt->bind_param("issss", $admin_id, $admin_username, $details, $ip, $agent);
                    $log_stmt->execute();
                }
                break;
                
            case 'delete_user':
                // Prevent admin from deleting their own account
                if ($user_id != $_SESSION['user_id'] && $user_id > 0) {
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    
                    // Log the activity
                    $admin_id = $_SESSION['user_id'];
                    $admin_username = $_SESSION['username'];
                    $log_stmt = $db->prepare("INSERT INTO activity_log (user_id, username, action, details, ip_address, user_agent) VALUES (?, ?, 'User Deleted', ?, ?, ?)");
                    $details = "User ID $user_id deleted";
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $agent = $_SERVER['HTTP_USER_AGENT'];
                    $log_stmt->bind_param("issss", $admin_id, $admin_username, $details, $ip, $agent);
                    $log_stmt->execute();
                }
                break;
                
            case 'add_user':
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'user';
                
                // Validate inputs
                if (empty($username) || empty($password)) {
                    $_SESSION['error'] = "Username and password are required";
                    break;
                }
                
                if (strlen($username) < 3) {
                    $_SESSION['error'] = "Username must be at least 3 characters long";
                    break;
                }
                
                if (strlen($password) < 6) {
                    $_SESSION['error'] = "Password must be at least 6 characters long";
                    break;
                }
                
                if (!in_array($role, ['admin', 'employee', 'user'])) {
                    $_SESSION['error'] = "Invalid role selected";
                    break;
                }
                
                // Check if username already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert user (without fullname and email since they're not in your table structure)
                    $stmt = $db->prepare("INSERT INTO users (username, password, role, created_at, is_active) VALUES (?, ?, ?, NOW(), 1)");
                    $stmt->bind_param("sss", $username, $hashed_password, $role);
                    
                    if ($stmt->execute()) {
                        $new_user_id = $stmt->insert_id;
                        
                        // Log the activity
                        $admin_id = $_SESSION['user_id'];
                        $admin_username = $_SESSION['username'];
                        $log_stmt = $db->prepare("INSERT INTO activity_log (user_id, username, action, details, ip_address, user_agent) VALUES (?, ?, 'User Created', ?, ?, ?)");
                        $details = "New user created: $username with role: $role (ID: $new_user_id)";
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $agent = $_SERVER['HTTP_USER_AGENT'];
                        $log_stmt->bind_param("issss", $admin_id, $admin_username, $details, $ip, $agent);
                        $log_stmt->execute();
                        
                        $_SESSION['success'] = "User added successfully";
                    } else {
                        $_SESSION['error'] = "Failed to add user: " . $db->error;
                    }
                } else {
                    $_SESSION['error'] = "Username already exists";
                }
                $stmt->close();
                break;
                
            case 'reset_password':
                $user_id = intval($_POST['user_id'] ?? 0);
                $new_password = $_POST['new_password'] ?? '';
                
                if ($user_id > 0 && !empty($new_password) && strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $hashed_password, $user_id);
                    
                    if ($stmt->execute()) {
                        // Log the activity
                        $admin_id = $_SESSION['user_id'];
                        $admin_username = $_SESSION['username'];
                        $log_stmt = $db->prepare("INSERT INTO activity_log (user_id, username, action, details, ip_address, user_agent) VALUES (?, ?, 'Password Reset', ?, ?, ?)");
                        $details = "Password reset for user ID $user_id";
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $agent = $_SERVER['HTTP_USER_AGENT'];
                        $log_stmt->bind_param("issss", $admin_id, $admin_username, $details, $ip, $agent);
                        $log_stmt->execute();
                        
                        $_SESSION['success'] = "Password reset successfully";
                    } else {
                        $_SESSION['error'] = "Failed to reset password";
                    }
                    $stmt->close();
                } else {
                    $_SESSION['error'] = "Invalid password (must be at least 6 characters)";
                }
                break;
        }
        
        header("Location: user-management-admin.php");
        exit();
    }
}

// Calculate user statistics
$total_users = count($users);
$active_users = count(array_filter($users, function($u) { return $u['is_active']; }));
$admins_count = count(array_filter($users, function($u) { return $u['role'] === 'admin'; }));
$employees_count = count(array_filter($users, function($u) { return $u['role'] === 'employee'; }));
$users_count = count(array_filter($users, function($u) { return $u['role'] === 'user'; }));

// Display messages
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>JHAY Gadget · User Management</title>
<style>
:root {
  --blue:#1e88e5; --blue-600:#1976d2;
  --ink:#0f172a; --ink-2:#111827;
  --bg:#000; --card:#111; --border:#333;
  --muted:#9ca3af; --accent:#22c55e;
  --danger:#dc2626; --warning:#f59e0b; --success:#22c55e;
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
.chipRow{display:flex;align-items:center;justify-content:space-between;padding:10px;border-radius:10px;text-decoration:none;color:inherit;cursor:pointer;}
.chipRow:hover{background:#1f2937;}
.chipLabel{display:flex;align-items:center;gap:10px;}
.dot{width:8px;height:8px;border-radius:999px;background:#6b7280;}
.badge{background:#374151;color:#e5e7eb;font-size:12px;padding:2px 8px;border-radius:999px;} 
main{padding:18px;overflow:auto;}
.toolbar{display:flex;gap:10px;align-items:center;margin-bottom:20px;}
.btn{background:#111;border:1px solid var(--border);border-radius:12px;padding:8px 14px;cursor:pointer;color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font-size:14px;}
.btn.primary{background:var(--blue);color:#fff;border-color:var(--blue-600);}
.btn.success{background:var(--success);color:#fff;border-color:#16a34a;}
.btn.danger{background:var(--danger);color:#fff;border-color:#b91c1c;}
.btn.warning{background:var(--warning);color:#fff;border-color:#d97706;}
.btn.info{background:#4f46e5;color:#fff;border-color:#3730a3;}
.btn:active{transform:translateY(1px);}
.card{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:auto;margin-bottom:20px;}
table{width:100%;border-collapse:separate;border-spacing:0;color:#f1f5f9;}
thead th{font-size:12px;text-transform:uppercase;color:#9ca3af;background:#1e293b;text-align:left;padding:12px;}
tbody td{padding:14px 12px;border-top:1px solid var(--border);}
tbody tr:hover{background:#1e293b;}
.status{display:inline-flex;align-items:center;gap:8px;padding:4px 10px;border-radius:999px;font-size:12px;}
.status.active{background:#065f46;color:white;}
.status.inactive{background:#dc2626;color:white;}
.role-badge{display:inline-block;padding:4px 8px;border-radius:6px;font-size:11px;font-weight:600;}
.role-admin{background:#7c3aed;color:white;}
.role-employee{background:#059669;color:white;}
.role-user{background:#1e40af;color:white;}
.action-buttons{display:flex;gap:6px;flex-wrap:wrap;}
.action-btn{padding:6px 10px;border:none;border-radius:6px;cursor:pointer;font-size:11px;white-space:nowrap;text-decoration:none;display:inline-block;}
.action-btn:hover{opacity:0.9;}
.action-btn.toggle{background:var(--warning);color:white;}
.action-btn.delete{background:var(--danger);color:white;}
.action-btn.reset{background:var(--blue);color:white;}
.action-btn:disabled{opacity:0.5;cursor:not-allowed;}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:15px;margin-bottom:20px;}
.stat-card{background:var(--card);padding:20px;border-radius:12px;border:1px solid var(--border);text-align:center;}
.stat-number{font-size:32px;font-weight:bold;margin-bottom:8px;}
.stat-label{color:var(--muted);font-size:14px;}
.modal {display:none;position:fixed;top:0;left:0;right:0;bottom:0;background: rgba(0,0,0,0.7);align-items:center;justify-content:center;z-index:1000;padding:10px;}
.modal-content {background:#111;padding:20px;border-radius:10px;width:100%;max-width:400px;max-height:90vh;overflow-y:auto;box-shadow:0 0 10px #000;}
.form-group{margin-bottom:15px;}
.form-group label{display:block;margin-bottom:5px;color:#9ca3af;font-size:14px;}
.form-group input, .form-group select{width:100%;padding:10px;border-radius:6px;border:1px solid #333;background:#1f2937;color:#fff;font-size:14px;}
.form-actions{display:flex;gap:10px;margin-top:20px;}
#toast {position: fixed; bottom: 20px; right: 20px;background: var(--blue); color: #fff; padding:10px 16px;border-radius:8px; font-size:14px; display:none; z-index:2000;box-shadow:0 4px 12px rgba(0,0,0,0.5);}
.loading {opacity: 0.6; pointer-events: none;}
.search-container{flex:1;}
.search-input{width:100%;padding:10px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:#fff;}
</style>
</head>
<body>
<header>
<div class="header-left">
  <div class="brand">
    <img id="brandLogo" alt="JHAY Gadget" src="img/jhay-gadget-logo.png.jpg" />
    <div><div class="title">JHAY GADGET - USER MANAGEMENT</div></div>
  </div>
</div>
<div>
  <button class="btn primary" onclick="location.href='fronttae-admin.php'" style="margin-right:10px;">Back to Inventory</button>
  <button class="logout-btn" onclick="location.href='logout.php'">Logout</button>
</div>
</header>
<div class="app">
<div class="shell">
<aside>
  <div class="navGroup">
    <div class="navTitle">Navigation</div>
    <a href="user-management-admin.php" class="chipRow" style="background:#1e293b;">
      <div class="chipLabel"><span class="dot"></span><span>User Management</span></div>
    </a>
    <a href="fronttae-admin.php" class="chipRow">
      <div class="chipLabel"><span class="dot"></span><span>All Product</span></div>
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
    <a href="defect items management-admin.php" class="chipRow">
      <div class="chipLabel"><span class="dot"></span><span>Defect Items</span></div>
    </a>
  </div>
  <div class="navGroup">
    <div class="navTitle">User Statistics</div>
    <div class="chipRow">
      <div class="chipLabel"><span>Total Users</span></div><span class="badge"><?php echo $total_users; ?></span>
    </div>
    <div class="chipRow">
      <div class="chipLabel"><span>Active Users</span></div><span class="badge"><?php echo $active_users; ?></span>
    </div>
    <div class="chipRow">
      <div class="chipLabel"><span>Admins</span></div><span class="badge"><?php echo $admins_count; ?></span>
    </div>
    <div class="chipRow">
      <div class="chipLabel"><span>Employees</span></div><span class="badge"><?php echo $employees_count; ?></span>
    </div>
    <div class="chipRow">
      <div class="chipLabel"><span>Users</span></div><span class="badge"><?php echo $users_count; ?></span>
    </div>
  </div>
</aside>
<main>
<div class="toolbar">
  <button class="btn primary" id="addUserBtn">+ Add New User</button>
  <div class="search-container">
    <input type="text" id="userSearch" class="search-input" placeholder="Search users..." onkeyup="searchUsers()">
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-number"><?php echo $total_users; ?></div>
    <div class="stat-label">Total Users</div>
  </div>
  <div class="stat-card">
    <div class="stat-number"><?php echo $active_users; ?></div>
    <div class="stat-label">Active Users</div>
  </div>
  <div class="stat-card">
    <div class="stat-number"><?php echo $admins_count; ?></div>
    <div class="stat-label">Administrators</div>
  </div>
  <div class="stat-card">
    <div class="stat-number"><?php echo $employees_count + $users_count; ?></div>
    <div class="stat-label">Regular Users</div>
  </div>
</div>

<div class="card">
<table id="usersTable">
<thead>
<tr>
  <th>ID</th>
  <th>Username</th>
  <th>Role</th>
  <th>Status</th>
  <th>Created Date</th>
  <th>Last Login</th>
  <th>Actions</th>
</tr>
</thead>
<tbody id="usersTableBody">
<?php foreach ($users as $user): ?>
<tr data-id="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>" data-role="<?php echo $user['role']; ?>">
  <td><?php echo $user['id']; ?></td>
  <td><?php echo htmlspecialchars($user['username']); ?></td>
  <td>
    <span class="role-badge role-<?php echo $user['role']; ?>">
      <?php echo ucfirst($user['role']); ?>
    </span>
  </td>
  <td>
    <span class="status <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
      <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
    </span>
  </td>
  <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
  <td><?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?></td>
  <td>
    <div class="action-buttons">
      <button class="action-btn toggle" 
              onclick="toggleUserStatus(<?php echo $user['id']; ?>, <?php echo $user['is_active'] ? 'true' : 'false'; ?>)"
              title="<?php echo $user['is_active'] ? 'Deactivate User' : 'Activate User'; ?>">
        <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
      </button>
      
      <select class="role-select" data-user-id="<?php echo $user['id']; ?>" style="padding:4px;border-radius:4px;background:#1f2937;color:white;border:1px solid #374151;font-size:11px;">
        <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>User</option>
        <option value="employee" <?php echo $user['role'] == 'employee' ? 'selected' : ''; ?>>Employee</option>
        <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
      </select>
      
      <button class="action-btn reset" onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" title="Reset Password">Reset PW</button>
      
      <?php if ($user['id'] != $_SESSION['user_id']): ?>
      <button class="action-btn delete" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" title="Delete User">Delete</button>
      <?php else: ?>
      <button class="action-btn delete" disabled title="Cannot delete your own account">Delete</button>
      <?php endif; ?>
    </div>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</main>
</div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:10px;">
            <h3 style="margin:0;">Add New User</h3>
            <button class="btn" id="closeAddUserModal">Close</button>
        </div>
        <form id="addUserForm">
            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" required placeholder="Enter username (min. 3 characters)">
                <small style="color:var(--muted);">Minimum 3 characters</small>
            </div>
            
            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required placeholder="Enter password (min. 6 characters)">
                <small style="color:var(--muted);">Minimum 6 characters</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm password">
            </div>
            
            <div class="form-group">
                <label for="role">Role *</label>
                <select id="role" name="role" required>
                    <option value="user">User</option>
                    <option value="employee">Employee</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn" id="cancelAddUser" style="flex:1;">Cancel</button>
                <button type="submit" class="btn primary" id="createUserBtn" style="flex:1;">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="modal">
    <div class="modal-content">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:10px;">
            <h3 style="margin:0;" id="resetPasswordTitle">Reset Password</h3>
            <button class="btn" id="closeResetPasswordModal">Close</button>
        </div>
        <form id="resetPasswordForm">
            <input type="hidden" id="reset_user_id" name="user_id">
            
            <div class="form-group">
                <label for="new_password">New Password *</label>
                <input type="password" id="new_password" name="new_password" required placeholder="Enter new password (min. 6 characters)">
                <small style="color:var(--muted);">Minimum 6 characters</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_new_password">Confirm New Password *</label>
                <input type="password" id="confirm_new_password" name="confirm_new_password" required placeholder="Confirm new password">
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn" id="cancelResetPassword" style="flex:1;">Cancel</button>
                <button type="submit" class="btn primary" id="resetPasswordBtn" style="flex:1;">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<div id="toast"></div>

<script>
// Display messages from PHP
<?php if (isset($success_message)): ?>
    showToast('<?php echo addslashes($success_message); ?>', 'var(--success)');
<?php endif; ?>
<?php if (isset($error_message)): ?>
    showToast('<?php echo addslashes($error_message); ?>', 'var(--danger)');
<?php endif; ?>

function showToast(msg, color = 'var(--blue)') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.background = color;
    t.style.display = 'block';
    setTimeout(() => t.style.display = 'none', 3000);
}

// Search functionality
function searchUsers() {
    const searchInput = document.getElementById('userSearch');
    const filter = searchInput.value.toLowerCase();
    const tableRows = document.querySelectorAll('#usersTableBody tr');
    
    tableRows.forEach(row => {
        const username = row.getAttribute('data-username').toLowerCase();
        const role = row.getAttribute('data-role').toLowerCase();
        const id = row.getAttribute('data-id');
        
        if (username.includes(filter) || role.includes(filter) || id.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Modal functionality
const addUserModal = document.getElementById('addUserModal');
const addUserBtn = document.getElementById('addUserBtn');
const closeAddUserModal = document.getElementById('closeAddUserModal');
const cancelAddUser = document.getElementById('cancelAddUser');

const resetPasswordModal = document.getElementById('resetPasswordModal');
const closeResetPasswordModal = document.getElementById('closeResetPasswordModal');
const cancelResetPassword = document.getElementById('cancelResetPassword');

// Add User Modal
if (addUserBtn && addUserModal) {
    addUserBtn.addEventListener('click', () => {
        addUserModal.style.display = 'flex';
        document.getElementById('username').focus();
    });
}

if (closeAddUserModal && addUserModal) {
    closeAddUserModal.addEventListener('click', () => {
        addUserModal.style.display = 'none';
        document.getElementById('addUserForm').reset();
    });
}

if (cancelAddUser && addUserModal) {
    cancelAddUser.addEventListener('click', () => {
        addUserModal.style.display = 'none';
        document.getElementById('addUserForm').reset();
    });
}

// Reset Password Modal
if (closeResetPasswordModal && resetPasswordModal) {
    closeResetPasswordModal.addEventListener('click', () => {
        resetPasswordModal.style.display = 'none';
        document.getElementById('resetPasswordForm').reset();
    });
}

if (cancelResetPassword && resetPasswordModal) {
    cancelResetPassword.addEventListener('click', () => {
        resetPasswordModal.style.display = 'none';
        document.getElementById('resetPasswordForm').reset();
    });
}

// Close modals when clicking outside
if (addUserModal) {
    addUserModal.addEventListener('click', (e) => {
        if (e.target === addUserModal) {
            addUserModal.style.display = 'none';
            document.getElementById('addUserForm').reset();
        }
    });
}

if (resetPasswordModal) {
    resetPasswordModal.addEventListener('click', (e) => {
        if (e.target === resetPasswordModal) {
            resetPasswordModal.style.display = 'none';
            document.getElementById('resetPasswordForm').reset();
        }
    });
}

// Add User Form Submission
const addUserForm = document.getElementById('addUserForm');
if (addUserForm) {
    addUserForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const role = document.getElementById('role').value;
        const submitBtn = document.getElementById('createUserBtn');
        const originalText = submitBtn.textContent;
        
        // Validation
        if (!username) {
            showToast('Please enter a username', 'var(--danger)');
            return;
        }
        
        if (username.length < 3) {
            showToast('Username must be at least 3 characters long', 'var(--danger)');
            return;
        }
        
        if (password.length < 6) {
            showToast('Password must be at least 6 characters long', 'var(--danger)');
            return;
        }
        
        if (password !== confirmPassword) {
            showToast('Passwords do not match', 'var(--danger)');
            return;
        }
        
        // Show loading state
        submitBtn.textContent = 'Creating...';
        submitBtn.disabled = true;
        
        try {
            const formData = new FormData();
            formData.append('action', 'add_user');
            formData.append('username', username);
            formData.append('password', password);
            formData.append('role', role);
            
            const response = await fetch('user-management-admin.php', {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                // Success message will be handled by PHP session
                addUserModal.style.display = 'none';
                addUserForm.reset();
                
                // Reload the page to show updated user list
                window.location.reload();
            } else {
                showToast('Error adding user', 'var(--danger)');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Error adding user', 'var(--danger)');
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    });
}

// Reset Password Form Submission
const resetPasswordForm = document.getElementById('resetPasswordForm');
if (resetPasswordForm) {
    resetPasswordForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const userId = document.getElementById('reset_user_id').value;
        const newPassword = document.getElementById('new_password').value;
        const confirmNewPassword = document.getElementById('confirm_new_password').value;
        const submitBtn = document.getElementById('resetPasswordBtn');
        const originalText = submitBtn.textContent;
        
        // Validation
        if (newPassword.length < 6) {
            showToast('Password must be at least 6 characters long', 'var(--danger)');
            return;
        }
        
        if (newPassword !== confirmNewPassword) {
            showToast('Passwords do not match', 'var(--danger)');
            return;
        }
        
        // Show loading state
        submitBtn.textContent = 'Resetting...';
        submitBtn.disabled = true;
        
        try {
            const formData = new FormData();
            formData.append('action', 'reset_password');
            formData.append('user_id', userId);
            formData.append('new_password', newPassword);
            
            const response = await fetch('user-management-admin.php', {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                // Success message will be handled by PHP session
                resetPasswordModal.style.display = 'none';
                resetPasswordForm.reset();
                
                // Reload the page
                window.location.reload();
            } else {
                showToast('Error resetting password', 'var(--danger)');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Error resetting password', 'var(--danger)');
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    });
}

// Toggle User Status
async function toggleUserStatus(userId, isCurrentlyActive) {
    if (!confirm(`Are you sure you want to ${isCurrentlyActive ? 'deactivate' : 'activate'} this user?`)) {
        return;
    }
    
    const button = event.target;
    const originalText = button.textContent;
    
    // Show loading state
    button.textContent = 'Processing...';
    button.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('action', 'toggle_active');
        formData.append('user_id', userId);
        
        const response = await fetch('user-management-admin.php', {
            method: 'POST',
            body: formData
        });
        
        if (response.ok) {
            // Reload to show updated status
            window.location.reload();
        } else {
            showToast('Error updating user status', 'var(--danger)');
            button.textContent = originalText;
            button.disabled = false;
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Error updating user status', 'var(--danger)');
        button.textContent = originalText;
        button.disabled = false;
    }
}

// Change User Role
document.querySelectorAll('.role-select').forEach(select => {
    select.addEventListener('change', async function() {
        const userId = this.dataset.userId;
        const newRole = this.value;
        
        const originalValue = this.value;
        
        if (!confirm(`Change user role to ${newRole}?`)) {
            this.value = originalValue;
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'change_role');
            formData.append('user_id', userId);
            formData.append('new_role', newRole);
            
            const response = await fetch('user-management-admin.php', {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                // Reload to show updated role
                window.location.reload();
            } else {
                showToast('Error updating user role', 'var(--danger)');
                this.value = originalValue;
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Error updating user role', 'var(--danger)');
            this.value = originalValue;
        }
    });
});

// Reset Password
function resetPassword(userId, username) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('resetPasswordTitle').textContent = `Reset Password for ${username}`;
    resetPasswordModal.style.display = 'flex';
    document.getElementById('new_password').focus();
}

// Delete User
async function deleteUser(userId, username) {
    if (!confirm(`Are you sure you want to delete user "${username}"?\n\nThis action cannot be undone and will permanently remove the user.`)) {
        return;
    }
    
    const button = event.target;
    const originalText = button.textContent;
    
    // Show loading state
    button.textContent = 'Deleting...';
    button.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete_user');
        formData.append('user_id', userId);
        
        const response = await fetch('user-management-admin.php', {
            method: 'POST',
            body: formData
        });
        
        if (response.ok) {
            // Reload to show updated list
            window.location.reload();
        } else {
            showToast('Error deleting user', 'var(--danger)');
            button.textContent = originalText;
            button.disabled = false;
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Error deleting user', 'var(--danger)');
        button.textContent = originalText;
        button.disabled = false;
    }
}

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + N to add new user
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        if (addUserBtn) {
            addUserBtn.click();
        }
    }
    
    // Ctrl/Cmd + F to focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.getElementById('userSearch');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
    
    // Escape to close modals
    if (e.key === 'Escape') {
        if (addUserModal && addUserModal.style.display === 'flex') {
            addUserModal.style.display = 'none';
            document.getElementById('addUserForm').reset();
        }
        if (resetPasswordModal && resetPasswordModal.style.display === 'flex') {
            resetPasswordModal.style.display = 'none';
            document.getElementById('resetPasswordForm').reset();
        }
    }
});

// Focus search on page load
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('userSearch');
    if (searchInput) {
        searchInput.focus();
    }
});
</script>
</body>
</html>
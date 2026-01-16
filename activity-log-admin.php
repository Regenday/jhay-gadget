<?php
include 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

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
    
    if ($createTable) {
        // Log the table creation
        $db->query("
            INSERT INTO activity_log (user_id, username, action, details, ip_address, user_agent) 
            VALUES (" . $_SESSION['user_id'] . ", '" . ($_SESSION['username'] ?? 'System') . "', 'System Setup', 'Activity log table created', '" . $_SERVER['REMOTE_ADDR'] . "', '" . $_SERVER['HTTP_USER_AGENT'] . "')
        ");
    }
}

// Fetch activity log data with search and filter functionality
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';
$timeframe = $_GET['timeframe'] ?? 'all';

$activities = [];
$whereConditions = [];
$params = [];
$paramTypes = '';

// Build query conditions
if (!empty($search)) {
    $whereConditions[] = "(al.action LIKE ? OR al.details LIKE ? OR al.username LIKE ? OR al.ip_address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $paramTypes .= 'ssss';
}

if ($filter !== 'all') {
    $whereConditions[] = "al.action LIKE ?";
    $params[] = "%$filter%";
    $paramTypes .= 's';
}

// Timeframe filter
if ($timeframe !== 'all') {
    switch($timeframe) {
        case 'today':
            $whereConditions[] = "DATE(al.activity_date) = CURDATE()";
            break;
        case 'week':
            $whereConditions[] = "YEARWEEK(al.activity_date) = YEARWEEK(CURDATE())";
            break;
        case 'month':
            $whereConditions[] = "YEAR(al.activity_date) = YEAR(CURDATE()) AND MONTH(al.activity_date) = MONTH(CURDATE())";
            break;
        case 'year':
            $whereConditions[] = "YEAR(al.activity_date) = YEAR(CURDATE())";
            break;
    }
}

// Build the query
$query = "
    SELECT al.* 
    FROM activity_log al 
";

if (!empty($whereConditions)) {
    $query .= " WHERE " . implode(" AND ", $whereConditions);
}

$query .= " ORDER BY al.activity_date DESC LIMIT 1000";

// Prepare and execute query
if (!empty($params)) {
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = false;
    }
} else {
    $res = $db->query($query);
}

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $activities[] = $row;
    }
}

// Get activity statistics for sidebar
$stats = [
    'total' => 0,
    'today' => 0,
    'week' => 0,
    'month' => 0
];

// Total activities
$totalRes = $db->query("SELECT COUNT(*) as total FROM activity_log");
if ($totalRes) {
    $stats['total'] = $totalRes->fetch_assoc()['total'];
}

// Today's activities
$todayRes = $db->query("SELECT COUNT(*) as today FROM activity_log WHERE DATE(activity_date) = CURDATE()");
if ($todayRes) {
    $stats['today'] = $todayRes->fetch_assoc()['today'];
}

// This week's activities
$weekRes = $db->query("SELECT COUNT(*) as week FROM activity_log WHERE YEARWEEK(activity_date) = YEARWEEK(CURDATE())");
if ($weekRes) {
    $stats['week'] = $weekRes->fetch_assoc()['week'];
}

// This month's activities
$monthRes = $db->query("SELECT COUNT(*) as month FROM activity_log WHERE YEAR(activity_date) = YEAR(CURDATE()) AND MONTH(activity_date) = MONTH(CURDATE())");
if ($monthRes) {
    $stats['month'] = $monthRes->fetch_assoc()['month'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JHAY Gadget · Activity Log</title>
    <style>
        /* Copy the exact same CSS from your main file */
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
        .back-btn {
            background: #374151; color: white; border: none; padding: 8px 16px;
            border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block;
            margin-right: 10px;
        }
        .back-btn:hover {background: #4b5563;}
        .shell{display:grid; grid-template-columns:260px 1fr; height:calc(100vh - 52px);}
        aside{
            background:var(--ink-2); color:#d1d5db; padding:14px 12px; overflow:auto;
            border-right:1px solid #222;
        }
        .navGroup{margin:10px 0 18px;}
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
        .card{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:auto;}
        
        /* Activity Log Specific Styles */
        .activity-item {
            background: #111;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.2s ease;
        }
        
        .activity-item:hover {
            background: #1e293b;
            border-color: #4b5563;
        }
        
        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        
        .activity-user {
            font-weight: bold;
            color: #8b5cf6;
        }
        
        .activity-date {
            color: #9ca3af;
            font-size: 14px;
        }
        
        .activity-action {
            color: #f9fafb;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .activity-details {
            color: #9ca3af;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .activity-meta {
            display: flex;
            gap: 15px;
            margin-top: 8px;
            font-size: 12px;
            color: #6b7280;
        }
        
        .activity-ip {
            font-family: monospace;
        }
        
        .no-activities {
            text-align: center;
            padding: 40px;
            color: #6b7280;
            font-style: italic;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            background: #1e293b;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #374151;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #f9fafb;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        select {
            width: 100%;
            padding: 6px 10px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--card);
            color: #f9fafb;
            font-size: 14px;
            appearance: none;
            cursor: pointer;
        }
        
        select:focus {
            outline: 2px solid var(--blue);
        }
        
        @media (max-width: 980px){
            .shell{grid-template-columns:80px 1fr;}
            aside{padding:12px 8px;}
            .navTitle{display:none;}
            .chipLabel span{display:none;}
            .filters{display:none;}
        }
        
        @media (max-width: 720px){
            .toolbar{flex-wrap:wrap;}
            .activity-header {
                flex-direction: column;
                gap: 8px;
            }
            .activity-meta {
                flex-direction: column;
                gap: 5px;
            }
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
        <a href="fronttae-admin.php" class="back-btn">← Back to Products</a>
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
            </div>
            
            <div class="navGroup">
                <div class="navTitle">Activity Statistics</div>
                <div class="chipRow">
                    <div class="chipLabel"><span>Total Activities</span></div>
                    <span class="badge"><?php echo $stats['total']; ?></span>
                </div>
                <div class="chipRow">
                    <div class="chipLabel"><span>Today</span></div>
                    <span class="badge"><?php echo $stats['today']; ?></span>
                </div>
                <div class="chipRow">
                    <div class="chipLabel"><span>This Week</span></div>
                    <span class="badge"><?php echo $stats['week']; ?></span>
                </div>
                <div class="chipRow">
                    <div class="chipLabel"><span>This Month</span></div>
                    <span class="badge"><?php echo $stats['month']; ?></span>
                </div>
            </div>
            
            <div class="filters">
                <div class="filter">
                    <label>Action Type</label>
                    <select id="actionFilter" onchange="applyFilters()">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Actions</option>
                        <option value="Login" <?php echo $filter === 'Login' ? 'selected' : ''; ?>>User Login</option>
                        <option value="Logout" <?php echo $filter === 'Logout' ? 'selected' : ''; ?>>User Logout</option>
                        <option value="Added" <?php echo $filter === 'Added' ? 'selected' : ''; ?>>Items Added</option>
                        <option value="Sold" <?php echo $filter === 'Sold' ? 'selected' : ''; ?>>Items Sold</option>
                        <option value="Defective" <?php echo $filter === 'Defective' ? 'selected' : ''; ?>>Defective Items</option>
                        <option value="Updated" <?php echo $filter === 'Updated' ? 'selected' : ''; ?>>Items Updated</option>
                        <option value="Deleted" <?php echo $filter === 'Deleted' ? 'selected' : ''; ?>>Items Deleted</option>
                    </select>
                </div>
                
                <div class="filter">
                    <label>Timeframe</label>
                    <select id="timeframeFilter" onchange="applyFilters()">
                        <option value="all" <?php echo $timeframe === 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="today" <?php echo $timeframe === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $timeframe === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $timeframe === 'month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="year" <?php echo $timeframe === 'year' ? 'selected' : ''; ?>>This Year</option>
                    </select>
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
                    <input type="text" id="searchInput" placeholder="Search activities, users, or IP addresses..." 
                           value="<?php echo htmlspecialchars($search); ?>" onkeyup="applyFilters()">
                </div>
                <button class="btn danger" onclick="clearActivityLog()" title="Clear all activity logs">Clear Log</button>
                <button class="btn primary" onclick="exportActivityLog()" title="Export to CSV">Export CSV</button>
            </div>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Activities</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['today']; ?></div>
                    <div class="stat-label">Today</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['week']; ?></div>
                    <div class="stat-label">This Week</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['month']; ?></div>
                    <div class="stat-label">This Month</div>
                </div>
            </div>
            
            <div class="card">
                <div style="padding: 20px;">
                    <?php if (empty($activities)): ?>
                        <div class="no-activities">
                            No activity records found.
                            <?php if (!empty($search) || $filter !== 'all' || $timeframe !== 'all'): ?>
                                <div style="margin-top: 10px; font-size: 14px;">
                                    Try adjusting your search or filters.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-header">
                                    <span class="activity-user">
                                        <?php 
                                        if (!empty($activity['username'])) {
                                            echo htmlspecialchars($activity['username']);
                                        } elseif ($activity['user_id']) {
                                            echo 'User ID: ' . $activity['user_id'];
                                        } else {
                                            echo 'System';
                                        }
                                        ?>
                                    </span>
                                    <span class="activity-date">
                                        <?php echo date('M j, Y g:i A', strtotime($activity['activity_date'])); ?>
                                    </span>
                                </div>
                                <div class="activity-action">
                                    <?php echo htmlspecialchars($activity['action']); ?>
                                </div>
                                <div class="activity-details">
                                    <?php echo htmlspecialchars($activity['details'] ?: 'No additional details'); ?>
                                </div>
                                <div class="activity-meta">
                                    <?php if (!empty($activity['ip_address'])): ?>
                                        <span class="activity-ip">IP: <?php echo htmlspecialchars($activity['ip_address']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($activity['user_agent'])): ?>
                                        <span title="<?php echo htmlspecialchars($activity['user_agent']); ?>">
                                            Browser: <?php echo getBrowserName($activity['user_agent']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const filter = document.getElementById('actionFilter').value;
    const timeframe = document.getElementById('timeframeFilter').value;
    
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (filter !== 'all') params.append('filter', filter);
    if (timeframe !== 'all') params.append('timeframe', timeframe);
    
    window.location.href = 'activity-log-admin.php?' + params.toString();
}

function clearActivityLog() {
    if (confirm('Are you sure you want to clear all activity logs? This action cannot be undone.')) {
        fetch('clear-activity-log-admin.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Activity log cleared successfully');
                    location.reload();
                } else {
                    alert('Error clearing activity log: ' + data.error);
                }
            })
            .catch(error => {
                alert('Error clearing activity log: ' + error);
            });
    }
}

function exportActivityLog() {
    const search = document.getElementById('searchInput').value;
    const filter = document.getElementById('actionFilter').value;
    const timeframe = document.getElementById('timeframeFilter').value;
    
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (filter !== 'all') params.append('filter', filter);
    if (timeframe !== 'all') params.append('timeframe', timeframe);
    params.append('export', 'csv');
    
    window.location.href = 'export-activity-log.php?' + params.toString();
}

// Auto-refresh every 30 seconds to show new activities
setTimeout(() => {
    location.reload();
}, 30000);
</script>
</body>
</html>

<?php
// Helper function to get browser name from user agent
function getBrowserName($user_agent) {
    if (strpos($user_agent, 'Chrome') !== false) return 'Chrome';
    if (strpos($user_agent, 'Firefox') !== false) return 'Firefox';
    if (strpos($user_agent, 'Safari') !== false) return 'Safari';
    if (strpos($user_agent, 'Edge') !== false) return 'Edge';
    if (strpos($user_agent, 'Opera') !== false) return 'Opera';
    return 'Unknown';
}
?>
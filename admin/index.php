<?php
/**
 * Admin Dashboard - Protected Page
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();

// Protect this route: only admins allowed
requireAdmin('/signin.php');

$userName = $_SESSION['user_name'] ?? 'Admin';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard</title>
    <style>
        body { font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; }
        .navbar { display:flex; justify-content:space-between; align-items:center; padding:16px 24px; background:#111827; border-bottom:1px solid #1f2937; }
        .brand { font-weight:700; color:#60a5fa; }
        .btn { color:#e2e8f0; border:1px solid #374151; padding:8px 14px; border-radius:6px; text-decoration:none; }
        .btn:hover { background:#1f2937; }
        .container { max-width:1200px; margin:24px auto; padding:0 16px; }
        .card { background:#111827; border:1px solid #1f2937; border-radius:12px; padding:24px; margin-bottom:16px; }
        h2 { margin-top:0; }
        .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px; }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="brand">Koren LMS Admin</div>
        <div>
            <span style="margin-right:12px; opacity:0.8;">Welcome, <?php echo htmlspecialchars($userName); ?></span>
            <a class="btn" href="/logout.php">Logout</a>
        </div>
    </div>
    <div class="container">
        <div class="card">
            <h2>Admin Panel</h2>
            <p>Only admins can see this page. Use this area to manage users, courses, and system settings.</p>
        </div>
        <div class="grid">
            <div class="card">
                <h3>Users</h3>
                <p>View and manage user accounts.</p>
            </div>
            <div class="card">
                <h3>Courses</h3>
                <p>Create, edit, and publish courses.</p>
            </div>
            <div class="card">
                <h3>Settings</h3>
                <p>Platform configuration and preferences.</p>
            </div>
        </div>
    </div>
</body>
</html>

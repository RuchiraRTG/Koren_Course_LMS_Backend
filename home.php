<?php
/**
 * Home Page
 * This is the main dashboard page after successful login
 */

// Include required files
require_once 'config/database.php';
require_once 'includes/functions.php';

// Start session
startSecureSession();

// Check if user is logged in, if not redirect to signin
if (!isLoggedIn()) {
    redirect('signin.php');
}

// If admin accidentally lands here, redirect to admin dashboard
if (function_exists('isAdmin') && isAdmin()) {
    redirect('admin/index.php');
}

// Get user information
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

// Get flash message if any
$flashMessage = getFlashMessage();

// Get user details from database
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT first_name, last_name, email, phone_number, created_at, last_login FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Koren LMS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar h1 {
            font-size: 24px;
        }
        
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            color: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }
        
        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 20px;
            border: 1px solid white;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .success-message {
            background: #efe;
            border: 1px solid #cfc;
            color: #3c3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .welcome-section {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .welcome-section h2 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .user-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .detail-item {
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .detail-item label {
            display: block;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .detail-item p {
            color: #333;
            font-size: 16px;
            font-weight: 500;
        }
        
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card h3 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .card p {
            color: #666;
            margin-bottom: 20px;
        }
        
        .card-btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 25px;
            border-radius: 5px;
            text-decoration: none;
            transition: transform 0.2s;
        }
        
        .card-btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>Koren LMS</h1>
        <div class="navbar-right">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                </div>
                <span><?php echo htmlspecialchars($userName); ?></span>
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <?php if ($flashMessage): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($flashMessage['message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="welcome-section">
            <h2>Welcome to Koren LMS Dashboard!</h2>
            <p>You have successfully logged in to your Learning Management System account.</p>
            
            <div class="user-details">
                <div class="detail-item">
                    <label>Full Name</label>
                    <p><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                </div>
                <div class="detail-item">
                    <label>Email Address</label>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
                <div class="detail-item">
                    <label>Phone Number</label>
                    <p><?php echo htmlspecialchars($user['phone_number']); ?></p>
                </div>
                <div class="detail-item">
                    <label>Member Since</label>
                    <p><?php echo date('F d, Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>
        </div>
        
        <div class="dashboard-cards">
            <div class="card">
                <h3>My Courses</h3>
                <p>View and manage your enrolled courses</p>
                <a href="#" class="card-btn">View Courses</a>
            </div>
            
            <div class="card">
                <h3>Assignments</h3>
                <p>Check your pending assignments</p>
                <a href="#" class="card-btn">View Assignments</a>
            </div>
            
            <div class="card">
                <h3>Profile Settings</h3>
                <p>Update your profile information</p>
                <a href="#" class="card-btn">Edit Profile</a>
            </div>
        </div>
    </div>
</body>
</html>

<?php
session_start();
require '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin'){
    header("Location: /water_refill_project/login.php");
    exit;
}

$full_name = $_SESSION['full_name'];
$user_type = $_SESSION['user_type'];
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Water Refill</title>
<link rel="stylesheet" href="/water_refill_project/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.dashboard-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 10px;
}
.dashboard-header h1 { margin: 0; }
.logout-btn {
    background: #dc3545;
    color: #fff;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    transition: background 0.2s;
    white-space: nowrap;
}
.logout-btn:hover { background: #a71d2a; }
</style>
</head>

<body>

<div class="dashboard">

    <div class="dashboard-header">
        <h1>💧 Water Refilling Station - Admin</h1>
        <a href="#" class="logout-btn" onclick="confirmLogout()">🚪 Logout</a>
    </div>

    <div class="welcome">
        <h2>Welcome, <?php echo htmlspecialchars($full_name); ?>! 👋</h2>
        <p>User Type: <strong><?php echo $user_type; ?></strong></p>
    </div>

    <div class="card-container">

        <div class="card">
            <h3>👥 Manage Customers</h3>
            <p>View, edit, or delete customers.</p>
            <a href="/water_refill_project/admin/manage_customers.php">Go</a>
        </div>

        <div class="card">
            <h3>🧑‍💼 Manage Staff</h3>
            <p>Create or remove staff accounts.</p>
            <a href="/water_refill_project/admin/manage_staff.php">Go</a>
        </div>

        <div class="card">
            <h3>🗺️ Manage Routes</h3>
            <p>Set barangay routes for staff.</p>
            <a href="/water_refill_project/admin/manage_routes.php">Go</a>
        </div>

        <div class="card">
            <h3>🛒 Check all Orders</h3>
            <p>Check all orders from customers.</p>
            <a href="/water_refill_project/admin/manage_orders.php">Go</a>
        </div>

        <div class="card">
            <h3>⭐ Rewards</h3>
            <p>View and manage customer points.</p>
            <a href="/water_refill_project/admin/manage_rewards.php">Go</a>
        </div>

        <div class="card">
            <h3>📊 Analytics & Forecasting</h3>
            <p>Sales trends, demand forecast, barangay insights.</p>
            <a href="/water_refill_project/admin/analytics.php">Go</a>
        </div>

    </div>

</div>

<script>
function confirmLogout() {
    Swal.fire({
        title: 'Logout?',
        text: 'Are you sure you want to logout?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#666',
        confirmButtonText: 'Yes, logout',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if(result.isConfirmed){
            window.location.href = '/water_refill_project/logout.php';
        }
    });
}
</script>

</body>
</html>
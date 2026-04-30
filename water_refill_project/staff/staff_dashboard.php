<?php
session_start();
require '../config.php';

if(!isset($_SESSION['user_id']) || strtolower($_SESSION['user_type']) != 'staff'){
    header("Location: /water_refill_project/login.php");
    exit;
}

$staff_id = $_SESSION['user_id'];

// Fetch assigned routes
$stmt = $conn->prepare("
    SELECT r.barangay_name, r.description
    FROM tb_staff_routes sr
    JOIN tb_routes r ON sr.route_id = r.route_id
    WHERE sr.staff_id = ?
    ORDER BY r.barangay_name ASC
");
$stmt->execute([$staff_id]);
$assigned_routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch quick stats for this staff
$stats = $conn->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN order_status = 'Pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN order_status IN ('Completed','Delivered') THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_orders,
        SUM(gallons) as total_gallons
    FROM tb_orders
    WHERE assigned_staff_id = ? OR (order_type = 'walk-in' AND assigned_staff_id IS NULL)
");
$stats->execute([$staff_id]);
$staffStats = $stats->fetch(PDO::FETCH_ASSOC);

// Fetch today's orders
$todayStmt = $conn->prepare("
    SELECT o.order_id, o.order_type, o.order_status, o.gallons, u.full_name, u.brgy
    FROM tb_orders o
    JOIN tb_users u ON o.user_id = u.id
    WHERE (o.assigned_staff_id = ? OR o.order_type = 'walk-in')
    AND DATE(o.created_at) = CURDATE()
    ORDER BY o.created_at DESC
    LIMIT 5
");
$todayStmt->execute([$staff_id]);
$todayOrders = $todayStmt->fetchAll(PDO::FETCH_ASSOC);

$can_walkin = $conn->query("SELECT walkin_assigned FROM tb_users WHERE id = $staff_id")->fetchColumn();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Water Refill</title>
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

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 14px;
        margin: 20px 0;
    }
    .stat-card {
        background: #fff;
        border-radius: 12px;
        padding: 18px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: transform 0.2s;
        border-top: 4px solid #1e88e5;
    }
    .stat-card:hover { transform: translateY(-3px); }
    .stat-card .icon {
        font-size: 1.8rem;
        margin-bottom: 6px;
    }
    .stat-card .value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e3a5f;
    }
    .stat-card .label {
        font-size: 0.78rem;
        color: #888;
        margin-top: 4px;
    }

    /* Profile Banner */
    .profile-banner {
        background: linear-gradient(90deg, #0d47a1 0%, #1e88e5 60%, #0097a7 100%);
        border-radius: 12px;
        padding: 24px;
        color: #fff;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
        box-shadow: 0 2px 8px rgba(30,136,229,0.18);
    }
    .profile-banner .avatar {
        width: 60px;
        height: 60px;
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
    }
    .profile-banner h2 { margin: 0; font-size: 1.2rem; color: #fff; }
    .profile-banner p { margin: 4px 0 0; opacity: 0.9; font-size: 0.85rem; color: #fff; }
    .profile-banner .badge {
        background: rgba(255,255,255,0.2);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        margin-left: auto;
        color: #fff;
    }

    /* Routes Section */
    .routes-section {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        margin-top: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    .routes-section h3 {
        color: #1e3a5f;
        margin-bottom: 15px;
        font-size: 1rem;
    }
    .route-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
    }
    .route-card {
        background: #f0f7ff;
        padding: 16px;
        border-radius: 10px;
        border-left: 4px solid #1e88e5;
    }
    .route-card strong {
        color: #1565c0;
        font-size: 0.95rem;
    }
    .route-card p {
        margin: 4px 0 0;
        color: #555;
        font-size: 0.82rem;
    }
    .no-routes {
        text-align: center;
        color: #888;
        padding: 20px;
    }

    /* Today's Orders */
    .orders-section {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        margin-top: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    .orders-section h3 {
        color: #1e3a5f;
        margin-bottom: 15px;
        font-size: 1rem;
    }
    .mini-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .mini-table th {
        background: #1e88e5;
        color: #fff;
        padding: 10px 12px;
        text-align: left;
        font-weight: 600;
    }
    .mini-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #f0f7ff;
    }
    .mini-table tr:nth-child(even) { background: #f0f7ff; }
    .mini-table tr:hover { background: #dceeff; }
    .status-badge {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-pending { background: #fff3cd; color: #856404; }
    .status-completed { background: #d4edda; color: #155724; }
    .status-delivered { background: #d1ecf1; color: #0c5460; }
    .status-cancelled { background: #f8d7da; color: #721c24; }

    /* Quick Actions */
    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 14px;
        margin-top: 20px;
    }
    .action-card {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        text-align: center;
        transition: transform 0.2s;
    }
    .action-card:hover { transform: translateY(-3px); }
    .action-card .icon {
        font-size: 2rem;
        margin-bottom: 10px;
    }
    .action-card h3 {
        margin: 0 0 8px;
        font-size: 1rem;
        color: #1e3a5f;
    }
    .action-card p {
        margin: 0 0 14px;
        color: #666;
        font-size: 0.85rem;
    }
    .action-card a {
        display: inline-block;
        background: #1e88e5;
        color: #fff;
        padding: 8px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.85rem;
    }
    .action-card a:hover { background: #1565c0; }
    </style>
</head>
<body>

<div class="dashboard">

    <div class="dashboard-header">
        <h1>💧 Water Refilling Station</h1>
        <a href="#" class="logout-btn" onclick="confirmLogout()">🚪 Logout</a>
    </div>

    <!-- Profile Banner -->
    <div class="profile-banner">
        <div class="avatar">🧑‍💼</div>
        <div>
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
            <p>Staff Member | <?php echo count($assigned_routes); ?> Route(s) Assigned</p>
        </div>
        <?php if($can_walkin): ?>
            <span class="badge">✓ Walk-in Orders Enabled</span>
        <?php endif; ?>
    </div>

    <!-- Assigned Routes Header -->
    <?php if(!empty($assigned_routes)): ?>
    <div style="background: #fff; border-radius: 12px; padding: 16px 20px; margin-bottom: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <h3 style="margin: 0; font-size: 0.95rem; color: #1e3a5f;">🗺️ My Assigned Routes:</h3>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php foreach($assigned_routes as $route): ?>
                <span style="background: linear-gradient(135deg, #e3f2fd, #bbdefb); color: #1565c0; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; border-left: 3px solid #1e88e5;">📍 <?php echo htmlspecialchars($route['barangay_name']); ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <h3 style="color:#1e3a5f;margin:20px 0 10px;font-size:1rem;">📋 Your Orders Overview</h3>
    <div class="stats-grid">
        <div class="stat-card pending">
            <div class="icon">⏳</div>
            <div class="value"><?php echo (int)$staffStats['pending_orders']; ?></div>
            <div class="label">Your Pending</div>
        </div>
        <div class="stat-card completed">
            <div class="icon">✅</div>
            <div class="value"><?php echo (int)$staffStats['completed_orders']; ?></div>
            <div class="label">Your Completed</div>
        </div>
        <div class="stat-card today">
            <div class="icon">📅</div>
            <div class="value"><?php echo (int)$staffStats['today_orders']; ?></div>
            <div class="label">Your Today</div>
        </div>
        <div class="stat-card gallons">
            <div class="icon">🫙</div>
            <div class="value"><?php echo number_format((int)$staffStats['total_gallons']); ?></div>
            <div class="label">Your Gallons</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="actions-grid">
        <div class="action-card">
            <div class="icon">📋</div>
            <h3>Manage Orders</h3>
            <p>Process and update customer orders.</p>
            <a href="/water_refill_project/staff/manage_orders.php">Go to Orders</a>
        </div>
        <div class="action-card">
            <div class="icon">⭐</div>
            <h3>Customer Rewards</h3>
            <p>Check points and redemption status.</p>
            <a href="/water_refill_project/customer/rewards.php">View Rewards</a>
        </div>
    </div>

    <!-- Today's Orders -->
    <div class="orders-section">
        <h3>📅 Today's Orders</h3>
        <?php if(empty($todayOrders)): ?>
            <p style="color:#888;text-align:center;padding:20px;">No orders today yet.</p>
        <?php else: ?>
            <table class="mini-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Gallons</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($todayOrders as $order): 
                    $statusClass = 'status-' . strtolower($order['order_status']);
                ?>
                    <tr>
                        <td>#<?php echo $order['order_id']; ?></td>
                        <td><?php echo htmlspecialchars($order['full_name']); ?></td>
                        <td><?php echo ucfirst($order['order_type']); ?></td>
                        <td><?php echo $order['gallons']; ?></td>
                        <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $order['order_status']; ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
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
<?php
session_start();
require '../config.php';

// Only admin can access
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin'){
    header("Location: /water_refill_project/login.php");
    exit;
}

// Get all customer rewards
$stmt = $conn->query("
SELECT tb_users.full_name, tb_users.email, tb_rewards.points
FROM tb_rewards
JOIN tb_users ON tb_rewards.customer_id = tb_users.id
ORDER BY tb_rewards.points DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer Rewards - Admin</title>
<link rel="stylesheet" href="/water_refill_project/css/style.css">
<style>
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 18px;
}
.page-header h1 { margin: 0; }
.btn-back {
    background: #1e88e5;
    color: #fff;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
    transition: background 0.2s;
    white-space: nowrap;
}
.btn-back:hover { background: #1565c0; }
</style>
</head>
<body>

<div class="dashboard">

    <div class="page-header">
        <h1>⭐ Customer Rewards</h1>
        <a class="btn-back" href="/water_refill_project/admin/admin_dashboard.php">⬅ Back to Dashboard</a>
    </div>

    <div class="card-container">

    <?php while($row = $stmt->fetch()): ?>
        <div class="card">
            <h3><?php echo htmlspecialchars($row['full_name']); ?></h3>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($row['email']); ?></p>
            <p><strong>Points:</strong> <span class="points-badge"><?php echo $row['points']; ?></span></p>
        </div>
    <?php endwhile; ?>

    </div>

</div>

</body>
</html>
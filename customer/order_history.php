<?php
session_start();
require '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'customer'){
    header("Location: /water_refill_project/login.php");
    exit;
}

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

define('PRICE_PER_GALLON', 20);

$stmt = $conn->prepare("
    SELECT order_id, created_at, gallons, order_type, container_type, address, order_status, is_reward
    FROM tb_orders
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_orders    = count($orders);
$total_gallons   = array_sum(array_column($orders, 'gallons'));
$total_spent     = 0;
$cancelled_count = count(array_filter($orders, fn($o) => strtolower($o['order_status']) === 'cancelled'));
$completed_count = count(array_filter($orders, fn($o) => in_array(strtolower($o['order_status']), ['completed','delivered'])));
$reward_count    = count(array_filter($orders, fn($o) => (int)$o['is_reward'] === 1));

foreach($orders as $o){
    if(!(int)$o['is_reward']){
        $total_spent += (int)$o['gallons'] * PRICE_PER_GALLON;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order History - Water Refill</title>
<link rel="stylesheet" href="/water_refill_project/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

.stats-grid { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 24px; }
.stat-card { flex: 1; min-width: 120px; background: #fff; border-radius: 12px; padding: 16px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-top: 4px solid #0097a7; }
.stat-card .stat-value { font-size: 1.6rem; font-weight: 700; color: #006064; }
.stat-card .stat-label { font-size: 0.75rem; color: #888; margin-top: 4px; }

.filter-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; align-items: center; }
.filter-bar select, .filter-bar input { padding: 8px 12px; border: 1px solid #cde; border-radius: 8px; font-size: 0.88rem; background: #fff; color: #333; }
.filter-bar select:focus, .filter-bar input:focus { outline: none; border-color: #0097a7; }

.history-table-wrapper { overflow-x: auto; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
.history-table { width: 100%; border-collapse: collapse; background: #fff; font-size: 0.92rem; }
.history-table thead { background: #0097a7; color: #fff; }
.history-table th, .history-table td { padding: 12px 14px; text-align: left; white-space: nowrap; }
.history-table tbody tr:nth-child(even) { background: #f0f7ff; }
.history-table tbody tr:hover { background: #dceeff; transition: background 0.2s; }

.badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
.badge-pending          { background: #fff3cd; color: #856404; }
.badge-confirmed        { background: #cfe2ff; color: #084298; }
.badge-preparing        { background: #d1ecf1; color: #0c5460; }
.badge-out_for_delivery { background: #d4edda; color: #155724; }
.badge-delivered        { background: #c3e6cb; color: #155724; }
.badge-ready            { background: #d4edda; color: #155724; }
.badge-completed        { background: #c3e6cb; color: #155724; }
.badge-cancelled        { background: #f8d7da; color: #721c24; }

.badge-reward { background: #d4edda; color: #155724; font-size: 0.72rem; padding: 2px 8px; border-radius: 20px; font-weight: 600; display: inline-block; margin-top: 4px; }

.total-price { font-weight: 700; color: #006064; }
.no-orders { text-align: center; padding: 40px; color: #888; background: #fff; border-radius: 10px; }
.grand-total-row td { background: #e0f7fa; font-weight: 700; color: #006064; border-top: 2px solid #0097a7; }
</style>
</head>
<body>

<div class="dashboard">

    <div class="page-header">
        <h1>💧 Water Refilling Station</h1>
        <a href="/water_refill_project/customer/index.php" class="btn-back">⬅ Back to Dashboard</a>
    </div>

    <div class="container" style="max-width:900px;">
        <h2>📜 Order History</h2>
        <p style="color:#888;font-size:0.9rem;">Welcome, <strong><?php echo htmlspecialchars($full_name); ?></strong> — here are all your orders.</p>

        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_orders; ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $completed_count; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $cancelled_count; ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $reward_count; ?></div>
                <div class="stat-label">🎁 Reward Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_gallons; ?></div>
                <div class="stat-label">Total Gallons</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">₱<?php echo number_format($total_spent, 0); ?></div>
                <div class="stat-label">Total Spent</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <select id="filterStatus" onchange="filterTable()">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="preparing">Preparing</option>
                <option value="ready">Ready</option>
                <option value="out_for_delivery">Out for Delivery</option>
                <option value="delivered">Delivered</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <select id="filterType" onchange="filterTable()">
                <option value="">All Types</option>
                <option value="walk-in">Walk-in</option>
                <option value="delivery">Delivery</option>
            </select>
            <select id="filterReward" onchange="filterTable()">
                <option value="">All Orders</option>
                <option value="1">🎁 Reward Only</option>
                <option value="0">Regular Only</option>
            </select>
            <input type="text" id="filterSearch" placeholder="🔍 Search by date or order #" oninput="filterTable()">
        </div>

        <?php if(empty($orders)): ?>
            <div class="no-orders">
                <p>🚿 No orders yet. <a href="/water_refill_project/customer/order.php">Place your first order!</a></p>
            </div>
        <?php else: ?>
            <div class="history-table-wrapper">
                <table class="history-table" id="historyTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Container</th>
                            <th>Gallons</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Address</th>
                        </tr>
                    </thead>
                    <tbody id="historyBody">
                    <?php
                    $grand_total = 0;
                    foreach($orders as $order):
                        $statusKey   = strtolower(str_replace(' ', '_', $order['order_status']));
                        $statusLabel = ucfirst(str_replace('_', ' ', $order['order_status']));
                        $gallons     = (int)$order['gallons'];
                        $total       = $gallons * PRICE_PER_GALLON;
                        $isReward    = (int)$order['is_reward'];
                        $address     = !empty($order['address']) ? htmlspecialchars($order['address']) : '—';
                        if(!$isReward) $grand_total += $total;
                    ?>
                    <tr class="order-row"
                        data-status="<?php echo $statusKey; ?>"
                        data-type="<?php echo strtolower($order['order_type']); ?>"
                        data-reward="<?php echo $isReward; ?>"
                        data-search="<?php echo $order['order_id'] . ' ' . date('M d Y', strtotime($order['created_at'])); ?>">
                        <td><?php echo $order['order_id']; ?></td>
                        <td><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></td>
                        <td><?php echo ucfirst(htmlspecialchars($order['order_type'])); ?></td>
                        <td><?php echo ucfirst(htmlspecialchars($order['container_type'])); ?></td>
                        <td>
                            <?php echo $gallons; ?> gal
                            <?php if($isReward): ?>
                                <br><span class="badge-reward">🎁 Reward</span>
                            <?php endif; ?>
                        </td>
                        <td class="total-price">
                            <?php if($isReward): ?>
                                <span style="text-decoration:line-through;color:#aaa;font-size:0.8rem;">₱<?php echo number_format($total, 2); ?></span>
                                <br><span style="color:#155724;font-weight:700;">FREE</span>
                            <?php else: ?>
                                ₱<?php echo number_format($total, 2); ?>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?php echo $statusKey; ?>"><?php echo $statusLabel; ?></span></td>
                        <td style="max-width:160px;white-space:normal;font-size:0.82rem;"><?php echo $address; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="grand-total-row">
                            <td colspan="5" style="text-align:right;">💰 Grand Total (excl. rewards):</td>
                            <td>₱<?php echo number_format($grand_total, 2); ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p style="margin-top:12px;font-size:0.82rem;color:#aaa;">
                Showing <span id="visibleCount"><?php echo $total_orders; ?></span> of <?php echo $total_orders; ?> orders
            </p>
        <?php endif; ?>

    </div>
</div>

<script>
function filterTable(){
    const status  = document.getElementById('filterStatus').value.toLowerCase();
    const type    = document.getElementById('filterType').value.toLowerCase();
    const reward  = document.getElementById('filterReward').value;
    const search  = document.getElementById('filterSearch').value.toLowerCase();
    const rows    = document.querySelectorAll('.order-row');
    let visible   = 0;

    rows.forEach(row => {
        const matchStatus = !status || row.dataset.status === status;
        const matchType   = !type   || row.dataset.type   === type;
        const matchReward = reward === '' || row.dataset.reward === reward;
        const matchSearch = !search || row.dataset.search.toLowerCase().includes(search);

        if(matchStatus && matchType && matchReward && matchSearch){
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('visibleCount').textContent = visible;
}
</script>

</body>
</html>
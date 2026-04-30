<?php
session_start();
require '../config.php';

if(!isset($_SESSION['user_id'])){
    header("Location: /water_refill_project/login.php");
    exit;
}

$full_name = $_SESSION['full_name'];
$user_type = $_SESSION['user_type'];
$user_id   = $_SESSION['user_id'];

if(isset($_POST['cancel_order']) && $user_type == 'customer'){
    $cancel_id = (int)$_POST['order_id'];

    $check = $conn->prepare("SELECT order_status, gallons, is_reward FROM tb_orders WHERE order_id = ? AND user_id = ?");
    $check->execute([$cancel_id, $user_id]);
    $order = $check->fetch(PDO::FETCH_ASSOC);

    if($order && strtolower($order['order_status']) === 'pending'){
        $stmt = $conn->prepare("UPDATE tb_orders SET order_status = 'Cancelled' WHERE order_id = ? AND user_id = ?");
        $stmt->execute([$cancel_id, $user_id]);

        $gallons = (int)$order['gallons'];

        if((int)$order['is_reward']){
            $points_to_return = $gallons * 10;
            $give = $conn->prepare("INSERT INTO tb_rewards (customer_id, points) VALUES (?, ?) ON DUPLICATE KEY UPDATE points = points + ?");
            $give->execute([$user_id, $points_to_return, $points_to_return]);
            $cancel_msg = "Reward order #$cancel_id cancelled. $points_to_return point(s) have been returned to your rewards!";
        } else {
            $deduct = $conn->prepare("UPDATE tb_rewards SET points = GREATEST(points - ?, 0) WHERE customer_id = ?");
            $deduct->execute([$gallons, $user_id]);
            $cancel_msg = "Order #$cancel_id cancelled and $gallons point(s) have been deducted.";
        }
    } else {
        $cancel_error = "Order #$cancel_id cannot be cancelled (status: " . ($order['order_status'] ?? 'unknown') . ").";
    }
}

define('PRICE_PER_GALLON', 20);

$sf = $conn->query("SELECT setting_value FROM tb_settings WHERE setting_key = 'shipping_fee'");
$delivery_fee_per_gallon = (int)($sf->fetchColumn() ?? 0);

$points = 0;
if($user_type == 'customer'){
    $pts = $conn->prepare("SELECT COALESCE(SUM(points), 0) FROM tb_rewards WHERE customer_id = ?");
    $pts->execute([$user_id]);
    $points = (int)$pts->fetchColumn();
}

$orders = [];
if($user_type == 'customer'){
    $stmt = $conn->prepare("
        SELECT o.order_id, o.created_at, o.gallons, o.order_type, o.order_status, o.is_reward, o.assigned_staff_id, o.cancel_reason,
               u.full_name as staff_name
        FROM tb_orders o
        LEFT JOIN tb_users u ON o.assigned_staff_id = u.id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Water Refill</title>
<link rel="stylesheet" href="/water_refill_project/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.delivery-notice {
    background: linear-gradient(90deg, #0d47a1 0%, #1e88e5 60%, #0097a7 100%);
    color: #fff;
    text-align: center;
    padding: 10px 18px;
    font-size: 0.88rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 2px 8px rgba(30,136,229,0.18);
}
.delivery-notice span.pin { font-size: 1.1rem; }
.delivery-notice span.text { line-height: 1.4; }
.delivery-notice strong { text-decoration: underline; text-underline-offset: 3px; }

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

.order-status-section { margin-top: 30px; }
.order-status-section h3 { font-size: 1.2rem; margin-bottom: 14px; color: #1e3a5f; }
.order-table-wrapper { overflow-x: auto; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
.order-table { width: 100%; border-collapse: collapse; background: #fff; font-size: 0.92rem; }
.order-table thead { background: #1e88e5; color: #fff; }
.order-table th, .order-table td { padding: 12px 14px; text-align: left; white-space: nowrap; }
.order-table tbody tr:nth-child(even) { background: #f0f7ff; }
.order-table tbody tr:hover { background: #dceeff; transition: background 0.2s; }

.badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
.badge-pending           { background: #fff3cd; color: #856404; }
.badge-confirmed         { background: #cfe2ff; color: #084298; }
.badge-preparing         { background: #d1ecf1; color: #0c5460; }
.badge-out_for_delivery  { background: #d4edda; color: #155724; }
.badge-delivered         { background: #c3e6cb; color: #155724; }
.badge-ready             { background: #d4edda; color: #155724; }
.badge-completed         { background: #d4edda; color: #155724; }
.badge-cancelled         { background: #f8d7da; color: #721c24; }
.badge-reward            { background: #d4edda; color: #155724; font-size: 0.72rem; padding: 2px 8px; border-radius: 20px; font-weight: 600; display: inline-block; margin-top: 4px; }

.total-price { font-weight: 700; color: black; font-size: 0.95rem; }
.delivery-tag { font-size: 0.7rem; color: #0097a7; background: #e0f7fa; padding: 2px 7px; border-radius: 20px; display: inline-block; margin-top: 3px; }

.order-progress { display: none; padding: 16px 18px; background: #f8fbff; border-top: 1px dashed #bee3f8; }
.order-progress.open { display: block; }
.progress-steps { display: flex; align-items: center; flex-wrap: wrap; }
.step { display: flex; flex-direction: column; align-items: center; flex: 1; min-width: 65px; position: relative; }
.step:not(:last-child)::after { content: ''; position: absolute; top: 14px; left: 50%; width: 100%; height: 3px; background: #ccc; z-index: 0; }
.step.done:not(:last-child)::after { background: #1e88e5; }
.step-dot { width: 28px; height: 28px; border-radius: 50%; background: #ccc; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; color: #fff; z-index: 1; position: relative; }
.step.done .step-dot   { background: #1e88e5; }
.step.active .step-dot { background: #0d47a1; box-shadow: 0 0 0 4px #bee3f8; }
.step-label { font-size: 0.68rem; margin-top: 5px; text-align: center; color: #888; max-width: 70px; }
.step.done .step-label, .step.active .step-label { color: #1e3a5f; font-weight: 600; }

.no-orders { text-align: center; padding: 28px; color: #888; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
.toggle-btn { background: none; border: none; color: #1e88e5; cursor: pointer; font-size: 0.85rem; text-decoration: underline; padding: 0; }
.cancel-btn { background: none; border: none; color: #dc3545; cursor: pointer; font-size: 0.85rem; text-decoration: underline; padding: 0; }
.cancel-btn:hover { color: #a71d2a; }
</style>
</head>
<body>

<?php if(isset($cancel_msg)): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    Swal.fire({ icon: 'success', title: 'Order Cancelled!', text: '<?php echo addslashes($cancel_msg); ?>', confirmButtonColor: '#0097a7', timer: 3500, showConfirmButton: true });
});
</script>
<?php endif; ?>

<?php if(isset($cancel_error)): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    Swal.fire({ icon: 'error', title: 'Cannot Cancel', text: '<?php echo addslashes($cancel_error); ?>', confirmButtonColor: '#0097a7' });
});
</script>
<?php endif; ?>

<div class="delivery-notice">
    <span class="pin">📍</span>
    <span class="text">We deliver within the vicinity of <strong>Poblacion Lambunao</strong> only.</span>
</div>

<div class="dashboard">

    <div class="dashboard-header">
        <h1>💧 Water Refilling Station</h1>
        <a href="#" class="logout-btn" onclick="confirmLogout()">🚪 Logout</a>
    </div>

    <div class="welcome">
        <h2>Welcome, <?php echo htmlspecialchars($full_name); ?>! 👋</h2>
        <p>User Type: <strong><?php echo $user_type; ?></strong></p>
    </div>

<?php if($user_type == 'customer'): ?>

<div class="card-container">
    <div class="card">
        <h3>🛒 Place Order</h3>
        <p>Order gallons for walk-in or delivery.</p>
        <a href="/water_refill_project/customer/order.php">Order Now</a>
    </div>
    <div class="card">
        <h3>⭐ Rewards</h3>
        <p>Your current points:</p>
        <div style="font-size:1.8rem;font-weight:700;color:#0097a7;margin:6px 0;">
            ⭐ <?php echo $points; ?> pts
        </div>
        <?php if($points >= 10): ?>
            <div style="font-size:0.78rem;color:#155724;background:#d4edda;padding:4px 10px;border-radius:20px;display:inline-block;margin-bottom:6px;">
                🎁 Ready to redeem!
            </div>
        <?php else: ?>
            <div style="font-size:0.78rem;color:#856404;background:#fff3cd;padding:4px 10px;border-radius:20px;display:inline-block;margin-bottom:6px;">
                <?php echo 10 - $points; ?> pts needed for free gallon
            </div>
        <?php endif; ?>
        <br>
        <a href="/water_refill_project/customer/rewards.php">Check Rewards</a>
    </div>
    <div class="card">
        <h3>📜 Order History</h3>
        <p>View all your past orders.</p>
        <a href="/water_refill_project/customer/order_history.php">View History</a>
    </div>
</div>

<div class="order-status-section">
    <h3>📋 My Recent Orders</h3>

    <?php if(empty($orders)): ?>
        <div class="no-orders">
            <p>🚿 No orders yet. <a href="/water_refill_project/customer/order.php">Place your first order!</a></p>
        </div>
    <?php else: ?>
        <div class="order-table-wrapper">
            <table class="order-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Gallons</th>
                        <th>Type</th>
                        <th>Assigned Staff</th>
                        <th>Total Price</th>
                        <th>Status</th>
                        <th>Track</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($orders as $order):
                    $statusKey   = strtolower(str_replace(' ', '_', $order['order_status']));
                    $statusLabel = ucfirst(str_replace('_', ' ', $order['order_status']));
                    $orderId     = (int)$order['order_id'];
                    $delivery    = strtolower($order['order_type']);
                    $gallons     = (int)$order['gallons'];
                    $waterPrice  = $gallons * PRICE_PER_GALLON;
                    $isReward    = (int)$order['is_reward'];

                    $delivery_fee = ($delivery === 'delivery') ? ($delivery_fee_per_gallon * $gallons) : 0;

                    if($isReward){
                        $totalPrice = ($delivery === 'delivery') ? $delivery_fee : 0;
                    } else {
                        $totalPrice = $waterPrice + $delivery_fee;
                    }

                    if($delivery === 'delivery'){
                        $steps  = ['pending','confirmed','preparing','out_for_delivery','delivered'];
                        $labels = ['Pending','Confirmed','Preparing','Out for Delivery','Delivered'];
                        $icons  = ['🕐','✅','🔧','🚚','🏠'];
                    } else {
                        $steps  = ['pending','confirmed','preparing','ready','completed'];
                        $labels = ['Pending','Confirmed','Preparing','Ready','Completed'];
                        $icons  = ['🕐','✅','🔧','💧','✔️'];
                    }

                    $currentIndex = array_search($statusKey, $steps);
                    if($currentIndex === false) $currentIndex = -1;
                ?>
                <tr>
                    <td><?php echo $orderId; ?></td>
                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                    <td>
                        <?php echo $gallons; ?> gal
                        <?php if($isReward): ?>
                            <br><span class="badge-reward">🎁 Reward</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo ucfirst(htmlspecialchars($order['order_type'])); ?></td>
                    <td>
                        <?php if($delivery === 'walk-in'): ?>
                            <span style="color: #0097a7;">🏠 Walk-in</span>
                        <?php elseif($order['assigned_staff_id'] && $order['staff_name']): ?>
                            <strong style="color: #1e88e5;">👤 <?php echo htmlspecialchars($order['staff_name']); ?></strong>
                        <?php else: ?>
                            <span style="color: #ff9800;">⏳ Pending Assignment</span>
                        <?php endif; ?>
                    </td>
                    <td class="total-price">
                        <?php if($isReward && $delivery !== 'delivery'): ?>
                            <span style="color:#155724;font-weight:700;">FREE</span>
                        <?php elseif($isReward && $delivery === 'delivery'): ?>
                            <?php if($delivery_fee > 0): ?>
                                ₱<?php echo number_format($delivery_fee, 2); ?>
                                <br><span class="delivery-tag">🚚 delivery only (₱<?php echo $delivery_fee_per_gallon; ?>/gal)</span>
                            <?php else: ?>
                                <span style="color:#155724;font-weight:700;">FREE</span>
                            <?php endif; ?>
                        <?php else: ?>
                            ₱<?php echo number_format($totalPrice, 2); ?>
                            <?php if($delivery === 'delivery' && $delivery_fee > 0): ?>
                                <br><span class="delivery-tag">incl. ₱<?php echo number_format($delivery_fee, 2); ?> delivery (₱<?php echo $delivery_fee_per_gallon; ?>/gal)</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo $statusKey; ?>"><?php echo $statusLabel; ?></span>
                        <?php if(strtolower($order['order_status']) === 'cancelled' && !empty($order['cancel_reason'])): ?>
                            <br><span style="color: #dc3545; font-size: 0.75rem; margin-top: 4px; display: block;">Reason: <?php echo htmlspecialchars($order['cancel_reason']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="toggle-btn" onclick="toggleProgress('prog-<?php echo $orderId; ?>', this)">🔍 Track</button>
                    </td>
                    <td>
                        <?php if(strtolower($order['order_status']) === 'pending'): ?>
                        <button class="cancel-btn" onclick="cancelOrder(<?php echo $orderId; ?>, <?php echo $gallons; ?>, <?php echo $isReward; ?>)">❌ Cancel</button>
                        <form id="cancel-form-<?php echo $orderId; ?>" method="POST" style="display:none;">
                            <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                            <input type="hidden" name="cancel_order" value="1">
                        </form>
                        <?php else: ?>
                            <span style="color:#999;font-size:0.8rem;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td colspan="9" style="padding:0;border:none;">
                        <div class="order-progress" id="prog-<?php echo $orderId; ?>">
                            <div class="progress-steps">
                                <?php foreach($steps as $si => $step):
                                    $cls = '';
                                    if($si < $currentIndex)      $cls = 'done';
                                    elseif($si == $currentIndex) $cls = 'done active';
                                ?>
                                <div class="step <?php echo $cls; ?>">
                                    <div class="step-dot"><?php echo $icons[$si]; ?></div>
                                    <div class="step-label"><?php echo $labels[$si]; ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

</div>

<script>
function toggleProgress(id, btn){
    const el = document.getElementById(id);
    const isOpen = el.classList.toggle('open');
    btn.textContent = isOpen ? '🔼 Hide' : '🔍 Track';
}

function cancelOrder(id, gallons, isReward){
    const pointsMsg = isReward
        ? '<span style="color:#0097a7;">🎁 This is a <strong>reward order</strong> — <strong>' + (gallons * 10) + ' point(s)</strong> will be returned to your rewards!</span>'
        : '<span style="color:#dc3545;">⚠️ <strong>' + gallons + ' point(s)</strong> will be deducted from your rewards.</span>';
    Swal.fire({
        title: 'Cancel Order?',
        html: 'Are you sure you want to cancel Order <strong>#' + id + '</strong>?<br><br>' + pointsMsg,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#666',
        confirmButtonText: 'Yes, cancel it!',
        cancelButtonText: 'No, keep it'
    }).then((result) => {
        if(result.isConfirmed) document.getElementById('cancel-form-' + id).submit();
    });
}

function confirmLogout(){
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
        if(result.isConfirmed) window.location.href = '/water_refill_project/logout.php';
    });
}
</script>

</body>
</html>
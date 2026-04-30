<?php
session_start();
require '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'staff'){
    header("Location: /water_refill_project/login.php");
    exit;
}

define('PRICE_PER_GALLON', 20);

$staff_id = $_SESSION['user_id'];
$message = "";

// Check if staff can handle walk-in orders
$staff_stmt = $conn->prepare("SELECT walkin_assigned FROM tb_users WHERE id = ? AND user_type = 'staff'");
$staff_stmt->execute([$staff_id]);
$staff_data = $staff_stmt->fetch(PDO::FETCH_ASSOC);
$can_walkin = $staff_data ? (int)$staff_data['walkin_assigned'] : 0;

// Get staff's assigned barangay routes
$route_stmt = $conn->prepare("
    SELECT r.barangay_name
    FROM tb_staff_routes sr
    JOIN tb_routes r ON sr.route_id = r.route_id
    WHERE sr.staff_id = ?
");
$route_stmt->execute([$staff_id]);
$assigned_barangays = $route_stmt->fetchAll(PDO::FETCH_COLUMN);

// Trim whitespace from barangay names
$assigned_barangays = array_map('trim', $assigned_barangays);

// Handle delivery fee update
if(isset($_POST['update_shipping'])){
    $fee = (int)$_POST['shipping_fee'];
    if($fee < 0) $fee = 0;
    $stmt = $conn->prepare("INSERT INTO tb_settings (setting_key, setting_value) VALUES ('shipping_fee', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$fee, $fee]);
    header("Location: " . $_SERVER['PHP_SELF'] . "?updated=" . urlencode("Delivery fee updated to ₱$fee per gallon!"));
    exit;
}

// Handle status update
if(isset($_POST['update_status'])){
    $id         = (int)$_POST['order_id'];
    $new_status = trim($_POST['order_status']);
    $cancel_reason = isset($_POST['cancel_reason']) ? trim($_POST['cancel_reason']) : '';

    $fetch = $conn->prepare("SELECT order_status, gallons, user_id, is_reward, order_type FROM tb_orders WHERE order_id = ?");
    $fetch->execute([$id]);
    $order = $fetch->fetch(PDO::FETCH_ASSOC);

    if($order){
        $old_status  = strtolower(trim($order['order_status']));
        $new_lower   = strtolower(trim($new_status));
        $gallons     = (int)$order['gallons'];
        $customer_id = (int)$order['user_id'];
        $is_reward   = (int)$order['is_reward'];
        $order_type  = strtolower(trim($order['order_type']));

        $complete_status = ($order_type === 'delivery') ? 'delivered' : 'completed';

        if(
            $new_lower === $complete_status &&
            $old_status !== $complete_status &&
            !$is_reward
        ){
            $points_earned = $gallons;
            $rwd = $conn->prepare("
                INSERT INTO tb_rewards (customer_id, points)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE points = points + ?
            ");
            $rwd->execute([$customer_id, $points_earned, $points_earned]);
        }

        // Save cancel reason if status is Cancelled
        if($new_lower === 'cancelled' && !empty($cancel_reason)){
            $stmt = $conn->prepare("UPDATE tb_orders SET order_status = ?, cancel_reason = ? WHERE order_id = ?");
            $stmt->execute([$new_status, $cancel_reason, $id]);
        } else {
            $stmt = $conn->prepare("UPDATE tb_orders SET order_status = ? WHERE order_id = ?");
            $stmt->execute([$new_status, $id]);
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "?updated=" . urlencode("Order #$id updated to $new_status!"));
        exit;
    }
}

if(isset($_GET['updated'])){
    $message = $_GET['updated'];
}

// Fetch current delivery fee per gallon
$sf = $conn->query("SELECT setting_value FROM tb_settings WHERE setting_key = 'shipping_fee'");
$delivery_fee_per_gallon = (int)($sf->fetchColumn() ?? 0);

// Fetch orders for staff:
// 1. Delivery orders from assigned barangays
// 2. Walk-in orders (only if staff is assigned to handle walk-ins)
if(!empty($assigned_barangays)){
    $placeholders = implode(',', array_fill(0, count($assigned_barangays), '?'));
    $walkin_sql = $can_walkin ? "OR (tb_orders.order_type = 'walk-in')" : "";
    $stmt = $conn->prepare("
        SELECT tb_orders.*, tb_users.full_name, tb_users.brgy
        FROM tb_orders
        JOIN tb_users ON tb_orders.user_id = tb_users.id
        WHERE (
            (tb_orders.order_type = 'delivery' AND tb_users.brgy IN ($placeholders) AND tb_orders.assigned_staff_id = ?)
            $walkin_sql
        )
        ORDER BY tb_orders.created_at DESC
    ");
    
    // Add staff_id to the parameters
    $params = array_merge($assigned_barangays, [$staff_id]);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // If no barangays assigned, show only walk-in orders (if allowed)
    if($can_walkin){
        $stmt = $conn->prepare("
            SELECT tb_orders.*, tb_users.full_name, tb_users.brgy
            FROM tb_orders
            JOIN tb_users ON tb_orders.user_id = tb_users.id
            WHERE tb_orders.order_type = 'walk-in'
            ORDER BY tb_orders.created_at DESC
        ");
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $orders = [];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Orders - Staff</title>
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

.badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.badge-pending           { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
.badge-confirmed         { background: #cfe2ff; color: #084298; border: 1px solid #9ec5fe; }
.badge-preparing         { background: #d1ecf1; color: #0c5460; border: 1px solid #a6dae8; }
.badge-out_for_delivery  { background: #d4edda; color: #155724; border: 1px solid #b1dfbb; }
.badge-delivered         { background: #c3e6cb; color: #155724; border: 1px solid #a8d5b5; }
.badge-ready             { background: #d4edda; color: #155724; border: 1px solid #b1dfbb; }
.badge-completed         { background: #c3e6cb; color: #155724; border: 1px solid #a8d5b5; }
.badge-cancelled         { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.badge-reward            { background: #d4edda; color: #155724; font-size: 0.65rem; padding: 3px 8px; border-radius: 20px; font-weight: 600; display: inline-block; margin-top: 4px; border: 1px solid #b1dfbb; }

.total-price { font-weight: 700; color: #0097a7; font-size: 0.95rem; }

.delivery-fee-box {
    background: #e3f2fd;
    border: 1px solid #90caf9;
    border-radius: 12px;
    padding: 18px 24px;
    margin-bottom: 22px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
}
.delivery-fee-box label { font-weight: 700; color: #1e3a5f; font-size: 0.95rem; }
.delivery-fee-box .fee-display { font-size: 1.3rem; font-weight: 800; color: #0097a7; }
.delivery-fee-box input[type=number] { padding: 6px 10px; border-radius: 8px; border: 1px solid #90caf9; font-size: 0.9rem; width: 110px; }
.delivery-fee-box button { padding: 7px 18px; background: #1e88e5; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 0.88rem; font-weight: 600; }
.delivery-fee-box button:hover { background: #1565c0; }
.fee-note { font-size: 0.75rem; color: #555; }
.delivery-tag { font-size: 0.6rem; color: #0097a7; background: #e0f7fa; padding: 2px 6px; border-radius: 12px; display: inline-block; margin-top: 3px; white-space: nowrap; }

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

.toggle-btn { 
    background: linear-gradient(135deg, #1e88e5, #1565c0); 
    border: none; 
    color: #fff; 
    cursor: pointer; 
    font-size: 0.8rem; 
    padding: 6px 12px; 
    border-radius: 20px;
    font-weight: 600;
    transition: all 0.2s ease;
}
.toggle-btn:hover { 
    background: linear-gradient(135deg, #1565c0, #0d47a1); 
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(30,136,229,0.3);
}
.status-form select { padding: 5px 8px; border-radius: 6px; border: 1px solid #ccc; font-size: 0.82rem; margin-right: 4px; }
.status-form button { padding: 5px 10px; background: #1e88e5; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 0.82rem; }
.status-form button:hover { background: #1565c0; }

/* Table styling improvements */
.table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}
table {
    width: 100%;
    min-width: 900px;
    border-collapse: separate;
    border-spacing: 0;
    background: #fff;
    font-size: 0.8rem;
    border-radius: 12px;
    overflow: hidden;
    margin-top: 20px;
}
table thead {
    background: linear-gradient(135deg, #2196F3, #1976D2);
    color: #fff;
}
table th {
    padding: 12px 8px;
    text-align: left;
    font-weight: 600;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    white-space: nowrap;
}
table td {
    padding: 10px 8px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
    color: #444;
    font-size: 0.8rem;
}
table tbody tr:hover {
    background: #f8fbff;
    transition: background 0.2s;
}
table tbody tr:last-child td {
    border-bottom: none;
}
table tbody tr:nth-child(even) {
    background: #fafbfc;
}

.routes-info-box {
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    border-left: 4px solid #1e88e5;
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 20px;
}
.routes-info-box h3 {
    margin: 0 0 10px 0;
    color: #1e3a5f;
    font-size: 1rem;
}
.routes-info-box ul {
    margin: 0;
    padding-left: 20px;
    color: #1565c0;
    font-weight: 600;
}
.routes-info-box li {
    margin-bottom: 5px;
}
.no-routes-notice {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 20px;
    color: #856404;
}
.no-routes-notice strong {
    display: block;
    margin-bottom: 5px;
}
</style>
</head>
<body>

<?php if($message): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    Swal.fire({ icon: 'success', title: 'Done!', text: '<?php echo addslashes($message); ?>', timer: 2000, showConfirmButton: false, toast: true, position: 'top-end' });
});
</script>
<?php endif; ?>

<div class="dashboard">
<div class="container" style="max-width: 98%; padding: 10px;">

    <div class="page-header">
        <h1>📋 My Assigned Orders</h1>
        <a href="/water_refill_project/staff/staff_dashboard.php" class="btn-back">⬅ Back to Dashboard</a>
    </div>

    <?php if(empty($assigned_barangays) && !$can_walkin): ?>
        <div class="no-routes-notice">
            <strong>⚠️ No Routes Assigned</strong>
            <p style="margin: 0;">You don't have any barangay routes assigned yet. Please contact your admin to assign routes to you.</p>
        </div>
    <?php elseif(!empty($assigned_barangays)): ?>
        <div class="routes-info-box">
            <h3>🗺️ Your Assigned Barangay Routes</h3>
            <ul>
                <?php foreach($assigned_barangays as $brgy): ?>
                    <li>📍 <?php echo htmlspecialchars($brgy); ?></li>
                <?php endforeach; ?>
            </ul>
            <p style="margin-top: 10px; margin-bottom: 0; font-size: 0.85rem; color: #555;">
                You can only view and manage orders from customers in these barangays.
            </p>
        </div>
    <?php endif; ?>

    <?php if(!empty($assigned_barangays) || $can_walkin): ?>
    <!-- Delivery Fee Setting -->
    <div class="delivery-fee-box">
        <div>
            <label>🚚 Delivery Fee (per gallon)</label><br>
            <span class="fee-note">Applied per gallon on all delivery orders (including reward deliveries).</span>
        </div>
        <div class="fee-display">₱<?php echo number_format($delivery_fee_per_gallon, 2); ?> / gallon</div>
        <form method="POST" style="display:flex;align-items:center;gap:8px;">
            <input type="number" name="shipping_fee" min="0" value="<?php echo $delivery_fee_per_gallon; ?>" placeholder="e.g. 5">
            <button type="button" onclick="confirmDeliveryFee(this.closest('form'))">💾 Update Fee</button>
            <input type="hidden" name="update_shipping" value="1">
        </form>
    </div>

    <?php if(empty($orders)): ?>
        <div style="text-align: center; padding: 40px; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <p style="font-size: 1.1rem; color: #888;">No orders from your assigned barangays yet.</p>
        </div>
    <?php else: ?>
    <div class="table-wrapper">
    <table>
    <thead>
    <tr>
        <th>Customer</th>
        <th>Barangay</th>
        <th>Type</th>
        <th>Container</th>
        <th>Address</th>
        <th>Qty</th>
        <th>Price</th>
        <th>Date</th>
        <th>Status</th>
        <th>Update</th>
        <th>Track</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach($orders as $row):
        $statusKey   = strtolower(str_replace(' ', '_', $row['order_status']));
        $statusLabel = ucfirst(str_replace('_', ' ', $row['order_status']));
        $orderId     = (int)$row['order_id'];
        $delivery    = strtolower($row['order_type']);
        $gallons     = (int)$row['gallons'];
        $waterPrice  = $gallons * PRICE_PER_GALLON;
        $isReward    = (int)$row['is_reward'];

        $delivery_fee = ($delivery === 'delivery') ? ($delivery_fee_per_gallon * $gallons) : 0;

        if($isReward){
            $totalPrice = ($delivery === 'delivery') ? $delivery_fee : 0;
        } else {
            $totalPrice = $waterPrice + $delivery_fee;
        }

        if($delivery === 'delivery'){
            $steps         = ['pending','confirmed','preparing','out_for_delivery','delivered'];
            $labels        = ['Pending','Confirmed','Preparing','Out for Delivery','Delivered'];
            $icons         = ['🕐','✅','🔧','🚚','🏠'];
            $statusOptions = ['Pending','Confirmed','Preparing','Out for Delivery','Delivered','Cancelled'];
        } else {
            $steps         = ['pending','confirmed','preparing','ready','completed'];
            $labels        = ['Pending','Confirmed','Preparing','Ready','Completed'];
            $icons         = ['🕐','✅','🔧','💧','✔️'];
            $statusOptions = ['Pending','Confirmed','Preparing','Ready','Completed','Cancelled'];
        }

        $currentIndex = array_search($statusKey, $steps);
        if($currentIndex === false) $currentIndex = -1;
    ?>
    <tr>
        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
        <td><strong style="color: #1e88e5;"><?php echo htmlspecialchars($row['brgy'] ?? 'N/A'); ?></strong></td>
        <td><?php echo ucfirst(htmlspecialchars($row['order_type'])); ?></td>
        <td><?php echo isset($row['container_type']) ? htmlspecialchars($row['container_type']) : 'N/A'; ?></td>
        <td><?php echo $row['address'] ? htmlspecialchars(substr($row['address'], 0, 20)) . (strlen($row['address']) > 20 ? '...' : '') : 'N/A'; ?></td>
        <td>
            <?php echo $gallons; ?> gal
            <?php if($isReward): ?>
                <br><span class="badge-reward">🎁 Reward</span>
            <?php endif; ?>
        </td>
        <td class="total-price" style="white-space: nowrap;">
            <?php if($isReward && $delivery === 'walk-in'): ?>
                <span style="color:#155724;font-weight:700;">FREE</span>
            <?php elseif($isReward && $delivery === 'delivery'): ?>
                <span style="color:#155724;font-weight:700;">FREE</span>
                <?php if($delivery_fee > 0): ?>
                    <br><span class="delivery-tag">+₱<?php echo number_format($delivery_fee, 2); ?> del.</span>
                <?php endif; ?>
            <?php else: ?>
                ₱<?php echo number_format($totalPrice, 2); ?>
                <?php if($delivery === 'delivery' && $delivery_fee > 0): ?>
                    <br><span class="delivery-tag">+₱<?php echo number_format($delivery_fee, 2); ?> del.</span>
                <?php endif; ?>
            <?php endif; ?>
        </td>
        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
        <td>
            <span class="badge badge-<?php echo $statusKey; ?>"><?php echo $statusLabel; ?></span>
            <?php if($statusKey === 'cancelled' && !empty($row['cancel_reason'])): ?>
                <br><span style="color: #dc3545; font-size: 0.7rem; margin-top: 3px; display: block;">📝 <?php echo htmlspecialchars(substr($row['cancel_reason'], 0, 30)) . (strlen($row['cancel_reason']) > 30 ? '...' : ''); ?></span>
            <?php endif; ?>
        </td>
        <td>
            <form class="status-form" method="POST" id="form-<?php echo $orderId; ?>">
                <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                <select name="order_status">
                    <?php foreach($statusOptions as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php echo (strtolower($row['order_status']) == strtolower($opt)) ? 'selected' : ''; ?>>
                        <?php echo $opt; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" onclick="confirmUpdate(<?php echo $orderId; ?>, this.closest('form'), <?php echo $isReward ? 'true' : 'false'; ?>)">💾 Save</button>
                <input type="hidden" name="update_status" value="1">
            </form>
        </td>
        <td>
            <button class="toggle-btn" onclick="toggleProgress('prog-<?php echo $orderId; ?>', this)">🔍 Track</button>
        </td>
    </tr>
    <tr>
        <td colspan="11" style="padding:0; border:none;">
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
    <?php endif; ?>

</div>
</div>

<script>
function toggleProgress(id, btn){
    const el = document.getElementById(id);
    const isOpen = el.classList.toggle('open');
    btn.textContent = isOpen ? '🔼 Hide' : '🔍 Track';
}

function confirmDeliveryFee(form){
    const fee = form.querySelector('input[name=shipping_fee]').value;
    Swal.fire({
        title: 'Update Delivery Fee?',
        html: `Set delivery fee to <strong>₱${parseFloat(fee).toFixed(2)} per gallon</strong>?<br><span style="font-size:0.82rem;color:#555;">Applies to all delivery orders including reward deliveries.</span>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1e88e5',
        cancelButtonColor: '#666',
        confirmButtonText: 'Yes, update!'
    }).then((result) => {
        if(result.isConfirmed) form.submit();
    });
}

function confirmUpdate(id, form, isReward){
    const status = form.querySelector('select').value;
    const isComplete = status === 'Completed' || status === 'Delivered';
    const isCancelled = status === 'Cancelled';
    const pointsNote = (!isReward && isComplete)
        ? '<br><span style="color:#0097a7;font-size:0.85rem;">⭐ Points will be added to customer rewards!</span>'
        : (isReward && isComplete)
        ? '<br><span style="color:#856404;font-size:0.85rem;">🎁 Reward order — no points added.</span>'
        : '';
    
    if(isCancelled){
        Swal.fire({
            title: 'Cancel Order?',
            html: `You are about to cancel Order <strong>#${id}</strong>.<br><br>Please specify the reason for cancellation:`,
            input: 'textarea',
            inputPlaceholder: 'Enter cancellation reason...',
            inputAttributes: {
                'aria-label': 'Cancellation reason',
                'required': 'true'
            },
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#666',
            confirmButtonText: 'Yes, Cancel Order',
            cancelButtonText: 'Go Back',
            preConfirm: (reason) => {
                if(!reason || reason.trim() === ''){
                    Swal.showValidationMessage('Please provide a reason for cancellation');
                    return false;
                }
                return reason;
            }
        }).then((result) => {
            if(result.isConfirmed){
                const reasonInput = document.createElement('input');
                reasonInput.type = 'hidden';
                reasonInput.name = 'cancel_reason';
                reasonInput.value = result.value;
                form.appendChild(reasonInput);
                form.submit();
            }
        });
    } else {
        Swal.fire({
            title: 'Update Status?',
            html: `Set Order <strong>#${id}</strong> to <strong>"${status}"</strong>?${pointsNote}`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#1e88e5',
            cancelButtonColor: '#666',
            confirmButtonText: 'Yes, update!'
        }).then((result) => {
            if(result.isConfirmed) form.submit();
        });
    }
}
</script>

</body>
</html>
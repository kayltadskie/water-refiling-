<?php
session_start();
require '../config.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['user_type'] != 'customer' && $_SESSION['user_type'] != 'staff')){
    header("Location: /water_refill_project/login.php");
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$message   = "";
$message_type = "success";

// Fetch shipping fee
$sf = $conn->query("SELECT setting_value FROM tb_settings WHERE setting_key = 'shipping_fee'");
$shipping_fee = (int)($sf->fetchColumn() ?? 0);

// Fetch all available barangay routes
$routes = $conn->query("SELECT barangay_name FROM tb_routes WHERE status = 'active' ORDER BY barangay_name ASC")->fetchAll(PDO::FETCH_COLUMN);

// Redeem: 10 points = 1 free gallon
if(isset($_POST['redeem']) && $user_type == 'customer'){

    $stmt = $conn->prepare("SELECT COALESCE(SUM(points), 0) FROM tb_rewards WHERE customer_id = ?");
    $stmt->execute([$user_id]);
    $points = (int)$stmt->fetchColumn();

    $order_type = isset($_POST['order_type']) && $_POST['order_type'] === 'delivery' ? 'delivery' : 'walk-in';
    $address    = ($order_type === 'delivery') ? trim($_POST['address'] ?? '') : '';
    $selected_brgy = ($order_type === 'delivery') ? trim($_POST['barangay'] ?? '') : '';

    if($order_type === 'delivery' && $selected_brgy === ''){
        $message      = "Please select your barangay.";
        $message_type = "error";
    } elseif($order_type === 'delivery' && $address === ''){
        $message      = "Please enter your delivery address.";
        $message_type = "error";
    } elseif($points >= 10){
        $free_gallons  = (int)floor($points / 10);
        $remaining_pts = $points % 10;

        $upd = $conn->prepare("UPDATE tb_rewards SET points = ? WHERE customer_id = ?");
        $upd->execute([$remaining_pts, $user_id]);

        $assigned_staff_id = null;
        if($order_type === 'delivery' && !empty($selected_brgy)){
            // Update customer's barangay
            $conn->prepare("UPDATE tb_users SET brgy = ? WHERE id = ?")->execute([$selected_brgy, $user_id]);
            $_SESSION['brgy'] = $selected_brgy;

            // Find staff assigned to this barangay
            $staff_stmt = $conn->prepare("
                SELECT sr.staff_id 
                FROM tb_staff_routes sr
                JOIN tb_routes r ON sr.route_id = r.route_id
                WHERE r.barangay_name = ?
                LIMIT 1
            ");
            $staff_stmt->execute([$selected_brgy]);
            $assigned_staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
            $assigned_staff_id = $assigned_staff ? $assigned_staff['staff_id'] : null;
        }

        $ord = $conn->prepare("
            INSERT INTO tb_orders (user_id, assigned_staff_id, order_type, container_type, address, gallons, is_reward)
            VALUES (?, ?, 'delivery', 'round', ?, ?, 1)
        ");
        $ord->execute([$user_id, $assigned_staff_id, $address, $free_gallons]);

        $type_label = $order_type === 'delivery' ? 'Delivery' : 'Walk-in';

        if($order_type === 'delivery' && $shipping_fee > 0){
            $message = "You redeemed $points point(s) for $free_gallons free gallon(s) ($type_label)! Shipping fee of ₱$shipping_fee applies. Remaining points: $remaining_pts";
        } else {
            $message = "You redeemed $points point(s) for $free_gallons free gallon(s) ($type_label)! Remaining points: $remaining_pts";
        }
        $message_type = "success";
    } else {
        $message      = "You need at least 10 points to redeem. You currently have $points point(s).";
        $message_type = "error";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rewards - Water Refill</title>
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

.redeem-options {
    display: flex;
    gap: 14px;
    margin: 16px 0 10px;
    flex-wrap: wrap;
}
.order-type-card {
    flex: 1;
    min-width: 130px;
    border: 2px solid #b2ebf2;
    border-radius: 12px;
    padding: 14px 16px;
    cursor: pointer;
    text-align: center;
    background: #fff;
    transition: border-color 0.2s, background 0.2s;
    user-select: none;
}
.order-type-card:hover { border-color: #0097a7; background: #e0f7fa; }
.order-type-card.selected { border-color: #0097a7; background: #e0f7fa; }
.order-type-card .type-icon { font-size: 1.6rem; margin-bottom: 6px; }
.order-type-card .type-label { font-weight: 700; font-size: 0.88rem; color: #1e3a5f; }
.order-type-card .type-note { font-size: 0.7rem; color: #555; margin-top: 3px; }

#delivery-address-box {
    display: none;
    margin-top: 10px;
    animation: fadeIn 0.2s ease;
}
#delivery-address-box input {
    width: 100%;
    padding: 9px 12px;
    border-radius: 8px;
    border: 1px solid #90caf9;
    font-size: 0.9rem;
    margin-top: 6px;
    box-sizing: border-box;
}
#delivery-address-box label { font-size: 0.82rem; font-weight: 600; color: #1e3a5f; }

.free-note {
    font-size: 0.75rem;
    color: #155724;
    background: #d4edda;
    padding: 5px 12px;
    border-radius: 20px;
    display: inline-block;
    margin-top: 8px;
}
.shipping-note {
    font-size: 0.75rem;
    color: #856404;
    background: #fff3cd;
    padding: 5px 12px;
    border-radius: 20px;
    display: inline-block;
    margin-top: 8px;
}

#delivery-fee-note { display: none; margin-top: 8px; }

@keyframes fadeIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:none; } }
</style>
</head>
<body>

<?php if($message): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    Swal.fire({
        icon: '<?php echo $message_type; ?>',
        title: '<?php echo $message_type === "success" ? "Redeemed!" : "Oops!"; ?>',
        text: '<?php echo addslashes($message); ?>',
        confirmButtonColor: '#0097a7'
    });
});
</script>
<?php endif; ?>

<div class="dashboard">
<div class="container" style="max-width:700px">

    <!-- Header with Back button -->
    <div class="page-header">
        <h1>⭐ Rewards</h1>
        <?php if($user_type == 'customer'): ?>
            <a href="/water_refill_project/customer/index.php" class="btn-back">⬅ Back to Dashboard</a>
        <?php else: ?>
            <a href="/water_refill_project/staff/staff_dashboard.php" class="btn-back">⬅ Back to Dashboard</a>
        <?php endif; ?>
    </div>

<?php if($user_type == 'customer'):

    $stmt = $conn->prepare("SELECT COALESCE(SUM(points), 0) FROM tb_rewards WHERE customer_id = ?");
    $stmt->execute([$user_id]);
    $points = (int)$stmt->fetchColumn();

    $free_gallons_available = (int)floor($points / 10);
    $points_progress        = $points % 10;
    $points_to_next         = ($points_progress === 0 && $points > 0) ? 0 : (10 - $points_progress);
?>

<!-- Points Summary Card -->
<div style="background:#e0f7fa;padding:24px;border-radius:12px;margin-bottom:20px;text-align:center;">
    <p style="font-size:0.9rem;color:#555;margin-bottom:8px;font-weight:600;">YOUR POINTS</p>
    <div class="points-badge" style="font-size:1.4rem;padding:10px 28px;">⭐ <?php echo $points; ?> Points</div>

    <div style="margin-top:16px;display:flex;justify-content:center;gap:30px;flex-wrap:wrap;">
        <div style="text-align:center;">
            <div style="font-size:1.5rem;font-weight:700;color:#006064;"><?php echo $free_gallons_available; ?></div>
            <div style="font-size:0.78rem;color:#666;">Free Gallon(s) Ready</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:1.5rem;font-weight:700;color:#f57f17;"><?php echo $points_progress; ?></div>
            <div style="font-size:0.78rem;color:#666;">Points Progress (<?php echo $points_progress; ?>/10)</div>
        </div>
    </div>

    <!-- Progress bar -->
    <div style="margin-top:14px;">
        <div style="background:#b2ebf2;border-radius:50px;height:12px;overflow:hidden;">
            <div style="background:linear-gradient(90deg,#0097a7,#00bcd4);height:100%;width:<?php echo min($points_progress * 10, 100); ?>%;border-radius:50px;transition:width 0.5s;"></div>
        </div>
        <p style="font-size:0.78rem;color:#666;margin-top:6px;">
            <?php if($free_gallons_available >= 1): ?>
                🎉 You have enough points to redeem!
            <?php elseif($points_to_next > 0): ?>
                <?php echo $points_to_next; ?> more point(s) needed for next free gallon
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- How it works -->
<div class="success" style="margin-bottom:20px;">
    💡 <strong>How it works:</strong> Every <strong>1 gallon ordered</strong> = <strong>1 point</strong> &nbsp;|&nbsp; Every <strong>10 points</strong> = <strong>1 free gallon</strong>
</div>

<?php if($free_gallons_available >= 1): ?>

<!-- Redeem Form -->
<form method="POST" id="redeem-form">
    <input type="hidden" name="redeem" value="1">
    <input type="hidden" name="order_type" id="selected-order-type" value="walk-in">

    <p style="font-weight:700;color:#1e3a5f;margin-bottom:4px;">🚚 How would you like to receive your free gallon(s)?</p>

    <div class="redeem-options">
        <div class="order-type-card selected" id="card-walkin" onclick="selectType('walk-in')">
            <div class="type-icon">🏪</div>
            <div class="type-label">Walk-in</div>
            <div class="type-note">Pick up — FREE</div>
        </div>
        <div class="order-type-card" id="card-delivery" onclick="selectType('delivery')">
            <div class="type-icon">🚚</div>
            <div class="type-label">Delivery</div>
            <div class="type-note">
                Gallon FREE<?php if($shipping_fee > 0): ?> + ₱<?php echo number_format($shipping_fee, 2); ?> shipping<?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Dynamic fee note -->
    <div id="walkin-note">
        <span class="free-note">🎁 Walk-in is completely FREE!</span>
    </div>
    <div id="delivery-fee-note">
        <?php if($shipping_fee > 0): ?>
            <span class="shipping-note">🚚 Gallon is FREE — only ₱<?php echo number_format($shipping_fee, 2); ?> shipping fee applies.</span>
        <?php else: ?>
            <span class="free-note">🎁 Delivery is also FREE — no shipping fee set!</span>
        <?php endif; ?>
    </div>

    <!-- Delivery address -->
    <div id="delivery-address-box">
        <label for="barangay">📍 Select Barangay</label>
        <input type="text" id="brgy_search" placeholder="Search barangay..." onkeyup="filterBarangay()" style="margin-bottom: 8px;">
        <select name="barangay" id="barangay" onchange="updateAddress()" style="margin-bottom: 10px;">
            <option value="">-- Select your barangay --</option>
            <?php foreach($routes as $route): ?>
                <option value="<?php echo htmlspecialchars($route); ?>"><?php echo htmlspecialchars($route); ?></option>
            <?php endforeach; ?>
        </select>
        <?php if(empty($routes)): ?>
            <p style="color: #dc3545; font-size: 0.85rem; margin-top: 5px; margin-bottom: 10px;">⚠️ No routes available. Please contact admin.</p>
        <?php endif; ?>

        <label for="address">📍 Delivery Address</label>
        <input type="text" name="address" id="address" placeholder="Enter your full delivery address...">
    </div>

    <br>
    <button type="button" onclick="confirmRedeem(<?php echo $points; ?>, <?php echo $free_gallons_available; ?>, <?php echo $shipping_fee; ?>)">
        🎁 Redeem <?php echo $points; ?> Point(s) for <?php echo $free_gallons_available; ?> Free Gallon(s)
    </button>
</form>

<?php else: ?>
<div class="error">
    You need <strong>10 points</strong> to redeem a free gallon. Keep ordering to earn more points!
</div>
<?php endif; ?>

<?php elseif($user_type == 'staff'): ?>

<table>
<thead>
<tr>
    <th>Customer Name</th>
    <th>Points</th>
    <th>Free Gallons Available</th>
</tr>
</thead>
<tbody>
<?php
$stmt = $conn->query("
    SELECT tb_users.full_name, SUM(tb_rewards.points) AS points
    FROM tb_rewards
    JOIN tb_users ON tb_rewards.customer_id = tb_users.id
    GROUP BY tb_rewards.customer_id, tb_users.full_name
    ORDER BY points DESC
");
while($row = $stmt->fetch()):
    $fp = (int)floor($row['points'] / 10);
?>
<tr>
    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
    <td><span class="points-badge">⭐ <?php echo $row['points']; ?></span></td>
    <td>
        <?php if($fp > 0): ?>
            <span class="status-completed">💧 <?php echo $fp; ?> gallon(s)</span>
        <?php else: ?>
            <span class="status-pending">Not enough yet</span>
        <?php endif; ?>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

<?php endif; ?>

</div>
</div>

<script>
function selectType(type){
    document.getElementById('selected-order-type').value = type;
    document.getElementById('card-walkin').classList.toggle('selected', type === 'walk-in');
    document.getElementById('card-delivery').classList.toggle('selected', type === 'delivery');

    const addrBox       = document.getElementById('delivery-address-box');
    const walkinNote    = document.getElementById('walkin-note');
    const deliveryNote  = document.getElementById('delivery-fee-note');

    addrBox.style.display      = (type === 'delivery') ? 'block' : 'none';
    walkinNote.style.display   = (type === 'walk-in')  ? 'block' : 'none';
    deliveryNote.style.display = (type === 'delivery') ? 'block' : 'none';

    if(type !== 'delivery'){
        document.getElementById('address').value = '';
    }
}

function confirmRedeem(points, gallons, shippingFee){
    const type    = document.getElementById('selected-order-type').value;
    const address = document.getElementById('address').value.trim();
    const barangay = document.getElementById('barangay').value;
    const label   = type === 'delivery' ? '🚚 Delivery' : '🏪 Walk-in';

    if(type === 'delivery' && !barangay){
        Swal.fire({ icon: 'warning', title: 'Missing Barangay', text: 'Please select your barangay before redeeming.', confirmButtonColor: '#0097a7' });
        return;
    }
    if(type === 'delivery' && address === ''){
        Swal.fire({ icon: 'warning', title: 'Missing Address', text: 'Please enter your delivery address before redeeming.', confirmButtonColor: '#0097a7' });
        return;
    }

    let priceNote = '';
    if(type === 'delivery' && shippingFee > 0){
        priceNote = `<br><span style="font-size:0.78rem;color:#856404;">🚚 Gallon FREE + ₱${shippingFee.toFixed(2)} shipping fee applies</span>`;
    } else {
        priceNote = `<br><span style="font-size:0.78rem;color:#155724;">✅ Completely FREE!</span>`;
    }

    Swal.fire({
        title: 'Redeem Points?',
        html: `Use <strong>${points} point(s)</strong> for <strong>${gallons} free gallon(s)</strong>?<br>
               <span style="font-size:0.85rem;color:#555;">Order type: <strong>${label}</strong></span>
               ${priceNote}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0097a7',
        cancelButtonColor: '#666',
        confirmButtonText: 'Yes, redeem!',
        cancelButtonText: 'Not yet'
    }).then((result) => {
        if(result.isConfirmed){
            document.getElementById('redeem-form').submit();
        }
    });
}

function filterBarangay(){
    var search = document.getElementById("brgy_search").value.toLowerCase();
    var select = document.getElementById("barangay");
    var options = select.getElementsByTagName("option");
    
    for(var i = 0; i < options.length; i++){
        var text = options[i].text.toLowerCase();
        if(i === 0 || text.indexOf(search) > -1){
            options[i].style.display = "";
        } else {
            options[i].style.display = "none";
        }
    }
}

function updateAddress(){
    var barangay = document.getElementById("barangay").value;
    var addressField = document.getElementById("address");
    if(barangay && addressField){
        addressField.value = "Barangay " + barangay + ", ";
        addressField.focus();
    }
}
</script>

</body>
</html>
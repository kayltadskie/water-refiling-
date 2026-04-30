<?php
session_start();
require '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'customer'){
    header("Location: /water_refill_project/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";
$error   = "";

// Fetch all available barangay routes
$routes = $conn->query("SELECT barangay_name FROM tb_routes WHERE status = 'active' ORDER BY barangay_name ASC")->fetchAll(PDO::FETCH_COLUMN);

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $order_type     = $_POST['order_type']     ?? '';
    $container_type = $_POST['container_type'] ?? '';
    $gallons        = (int)($_POST['gallons']  ?? 0);
    $address        = ($order_type == "delivery") ? trim($_POST['address'] ?? '') : "";
    $selected_brgy  = trim($_POST['barangay'] ?? '');

    if($gallons > 0 && !empty($container_type) && ($order_type !== 'delivery' || !empty($selected_brgy))){
        try {
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

            $stmt = $conn->prepare("INSERT INTO tb_orders (user_id, assigned_staff_id, order_type, container_type, address, gallons, is_reward) VALUES (?, ?, ?, ?, ?, ?, 0)");
            $stmt->execute([$user_id, $assigned_staff_id, $order_type, $container_type, $address, $gallons]);
            
            if($assigned_staff_id){
                $staff_name_stmt = $conn->prepare("SELECT full_name FROM tb_users WHERE id = ?");
                $staff_name_stmt->execute([$assigned_staff_id]);
                $staff_name = $staff_name_stmt->fetchColumn();
                $message = "Order placed successfully! Your order has been assigned to $staff_name. Points will be added once your order is completed.";
            } else {
                $message = "Order placed successfully! Points will be added once your order is completed.";
            }
        } catch(PDOException $e){
            $error = "DB Error: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Place Order - Water Refill</title>
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

.price-info {
    background: linear-gradient(135deg, #e0f7fa, #b2ebf2);
    border-left: 4px solid #0097a7;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
    font-size: 14px;
    color: #006064;
}
.price-info span { font-weight: bold; }
.total-box {
    background: #0097a7;
    color: white;
    border-radius: 10px;
    padding: 14px 18px;
    margin: 16px 0;
    font-size: 18px;
    font-weight: bold;
    text-align: center;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}
.total-box .total-label { font-size: 14px; font-weight: normal; opacity: 0.9; }
.total-box .total-amount { font-size: 22px; }
</style>
</head>
<body>

<?php if($message): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    Swal.fire({ icon:'success', title:'Order Placed!', text:'<?php echo addslashes($message); ?>', confirmButtonColor:'#0097a7' })
    .then(function(){ window.location.href='/water_refill_project/customer/index.php'; });
});
</script>
<?php endif; ?>

<?php if($error): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    Swal.fire({ icon:'error', title:'Error!', text:'<?php echo addslashes($error); ?>', confirmButtonColor:'#0097a7' });
});
</script>
<?php endif; ?>

<div class="dashboard">

    <div class="page-header">
        <h1>💧 Water Refilling Station</h1>
        <a href="/water_refill_project/customer/index.php" class="btn-back">⬅ Back to Dashboard</a>
    </div>

    <div class="container">
    <h2>🛒 Place Your Order</h2>

    <div class="price-info">
        💰 Price: <span>₱20.00 per gallon/container</span> &nbsp;|&nbsp; ⭐ <span>1 point per gallon</span> earned when order is completed!
    </div>

    <form method="POST" action="" id="orderForm" onsubmit="return false;">

        <label>Order Type</label>
        <select name="order_type" id="order_type" onchange="toggleAddress()">
            <option value="walk-in">Walk-in</option>
            <option value="delivery">Delivery</option>
        </select>

        <div id="barangay_field" style="display:none;">
            <label>Select Barangay</label>
            <input type="text" id="brgy_search" placeholder="Search barangay..." onkeyup="filterBarangay()" style="margin-bottom: 8px;">
            <select name="barangay" id="barangay" onchange="updateAddress()">
                <option value="">-- Select your barangay --</option>
                <?php foreach($routes as $route): ?>
                    <option value="<?php echo htmlspecialchars($route); ?>"><?php echo htmlspecialchars($route); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if(empty($routes)): ?>
                <p style="color: #dc3545; font-size: 0.85rem; margin-top: 5px;">⚠️ No routes available. Please contact admin.</p>
            <?php endif; ?>
        </div>

        <div id="address_field" style="display:none;">
            <label>Delivery Address</label>
            <textarea name="address" id="address" placeholder="Enter your delivery address"></textarea>
        </div>

        <label>Container Type</label>
        <select name="container_type" id="container_type">
            <option value="">-- Select container --</option>
            <option value="jug">Jug</option>
            <option value="round">Round Gallon</option>
        </select>

        <label>Number of Containers</label>
        <input type="number" name="gallons" id="gallons" min="1" placeholder="Enter quantity" oninput="updateTotal()">

        <div class="total-box" id="totalBox" style="display:none;">
            <div>
                <div class="total-label">Total Order Amount</div>
                <div class="total-amount" id="totalAmount">₱0.00</div>
            </div>
            <div style="font-size:28px;">🧾</div>
        </div>

        <input type="submit" value="Place Order" onclick="confirmOrder()">

    </form>

    </div>
</div>

<script>
const PRICE_PER_GALLON = 20;

function toggleAddress(){
    var type = document.getElementById("order_type").value;
    document.getElementById("address_field").style.display = (type == "delivery") ? "block" : "none";
    document.getElementById("barangay_field").style.display = (type == "delivery") ? "block" : "none";
}

function updateTotal(){
    var qty = parseInt(document.getElementById("gallons").value) || 0;
    var total = qty * PRICE_PER_GALLON;
    var totalBox = document.getElementById("totalBox");
    if(qty > 0){
        document.getElementById("totalAmount").textContent = "₱" + total.toLocaleString('en-PH', {minimumFractionDigits: 2});
        totalBox.style.display = "flex";
    } else {
        totalBox.style.display = "none";
    }
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

function confirmOrder(){
    var orderType     = document.getElementById("order_type").value;
    var barangay      = document.getElementById("barangay").value;
    var containerType = document.getElementById("container_type").value;
    var qty           = parseInt(document.getElementById("gallons").value) || 0;
    var address       = document.getElementById("address") ? document.getElementById("address").value.trim() : "";

    if(orderType === 'delivery' && !barangay){
        Swal.fire({ icon:'warning', title:'Missing Info', text:'Please select your barangay.', confirmButtonColor:'#0097a7' });
        return;
    }
    if(!containerType){
        Swal.fire({ icon:'warning', title:'Missing Info', text:'Please select a container type.', confirmButtonColor:'#0097a7' });
        return;
    }
    if(qty < 1){
        Swal.fire({ icon:'warning', title:'Missing Info', text:'Please enter the number of containers.', confirmButtonColor:'#0097a7' });
        return;
    }
    if(orderType === 'delivery' && address === ''){
        Swal.fire({ icon:'warning', title:'Missing Info', text:'Please enter your delivery address.', confirmButtonColor:'#0097a7' });
        return;
    }

    var total = qty * PRICE_PER_GALLON;
    var containerLabel = containerType === 'jug' ? 'Jug' : 'Round Gallon';
    var orderLabel = orderType === 'delivery' ? 'Delivery' : 'Walk-in';
    var brgyLabel = barangay;

    var orderDetails = 
        '<table style="width:100%;text-align:left;font-size:15px;border-collapse:collapse;">' +
        '<tr><td style="padding:6px 0;color:#555;">Order Type:</td><td style="padding:6px 0;font-weight:bold;">' + orderLabel + '</td></tr>';
    
    if(orderType === 'delivery' && brgyLabel){
        orderDetails += '<tr><td style="padding:6px 0;color:#555;">Barangay:</td><td style="padding:6px 0;font-weight:bold;">' + brgyLabel + '</td></tr>';
    }
    
    orderDetails += 
        '<tr><td style="padding:6px 0;color:#555;">Container:</td><td style="padding:6px 0;font-weight:bold;">' + containerLabel + '</td></tr>' +
        '<tr><td style="padding:6px 0;color:#555;">Quantity:</td><td style="padding:6px 0;font-weight:bold;">' + qty + ' container(s)</td></tr>' +
        '<tr><td style="padding:6px 0;color:#555;">Points:</td><td style="padding:6px 0;font-weight:bold;color:#0097a7;">⭐ +' + qty + ' pts (on completion)</td></tr>' +
        '<tr style="border-top:2px solid #0097a7;"><td style="padding:10px 0;color:#0097a7;font-weight:bold;font-size:17px;">Total:</td><td style="padding:10px 0;color:#0097a7;font-weight:bold;font-size:17px;">₱' + total.toLocaleString('en-PH', {minimumFractionDigits: 2}) + '</td></tr>' +
        '</table>';

    Swal.fire({
        icon: 'question',
        title: 'Confirm Order',
        html: orderDetails,
        showCancelButton: true,
        confirmButtonText: '✅ Place Order',
        cancelButtonText: '❌ Cancel',
        confirmButtonColor: '#0097a7',
        cancelButtonColor: '#e53935',
        reverseButtons: true
    }).then(function(result){
        if(result.isConfirmed){
            document.getElementById("orderForm").submit();
        }
    });
}
</script>
</body>
</html>
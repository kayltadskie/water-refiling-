<?php
session_start();
require '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin'){
    header("Location: /water_refill_project/login.php");
    exit;
}

define('PRICE_PER_GALLON', 20);

// Delete order
if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM tb_orders WHERE order_id=?");
    $stmt->execute([$id]);
    header("Location: " . $_SERVER['PHP_SELF'] . "?deleted=1");
    exit;
}

// Assign staff to walk-in order
if(isset($_POST['assign_staff'])){
    $order_id = (int)$_POST['order_id'];
    $staff_id = (int)$_POST['staff_id'];
    
    $stmt = $conn->prepare("UPDATE tb_orders SET assigned_staff_id = ? WHERE order_id = ? AND order_type = 'walk-in'");
    $stmt->execute([$staff_id, $order_id]);
    header("Location: " . $_SERVER['PHP_SELF'] . "?assigned=1");
    exit;
}

// Get all staff who can handle walk-in orders
$walkin_staff = $conn->query("
    SELECT id, full_name 
    FROM tb_users 
    WHERE user_type = 'staff' AND walkin_assigned = 1
    ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get all orders
$stmt = $conn->query("
    SELECT tb_orders.*, tb_users.full_name, tb_users.email, s.full_name as assigned_staff_name
    FROM tb_orders
    JOIN tb_users ON tb_orders.user_id = tb_users.id
    LEFT JOIN tb_users s ON tb_orders.assigned_staff_id = s.id
    ORDER BY created_at DESC
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Check All Orders - Admin</title>
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

.badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
.badge-pending           { background: #fff3cd; color: #856404; }
.badge-confirmed         { background: #cfe2ff; color: #084298; }
.badge-preparing         { background: #d1ecf1; color: #0c5460; }
.badge-out_for_delivery  { background: #d4edda; color: #155724; }
.badge-delivered         { background: #c3e6cb; color: #155724; }
.badge-ready             { background: #d4edda; color: #155724; }
.badge-completed         { background: #c3e6cb; color: #155724; }
.badge-cancelled         { background: #f8d7da; color: #721c24; }
.badge-reward            { background: #d4edda; color: #155724; font-size: 0.72rem; padding: 2px 8px; border-radius: 20px; font-weight: 600; display: inline-block; margin-top: 4px; }

.total-price { font-weight: 700; color: #0097a7; font-size: 0.95rem; }

.delete-btn { background: none; border: none; color: #dc3545; cursor: pointer; font-size: 0.85rem; text-decoration: underline; padding: 0; }
.delete-btn:hover { color: #a71d2a; }

table { width: 100%; border-collapse: collapse; background: #fff; font-size: 0.92rem; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
thead { background: #1e88e5; color: #fff; }
th, td { padding: 12px 14px; text-align: left; white-space: nowrap; }
tbody tr:nth-child(even) { background: #f0f7ff; }
tbody tr:hover { background: #dceeff; transition: background 0.2s; }
</style>
</head>
<body>

<?php if(isset($_GET['deleted'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    Swal.fire({ icon: 'success', title: 'Deleted!', text: 'Order has been deleted.', timer: 2000, showConfirmButton: false, toast: true, position: 'top-end' });
});
</script>
<?php endif; ?>

<?php if(isset($_GET['assigned'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    Swal.fire({ icon: 'success', title: 'Assigned!', text: 'Staff assigned to walk-in order.', timer: 2000, showConfirmButton: false, toast: true, position: 'top-end' });
});
</script>
<?php endif; ?>

<div class="dashboard">
<div class="container" style="max-width:1200px">

    <div class="page-header">
        <h1>🛒 All Orders</h1>
        <a href="/water_refill_project/admin/admin_dashboard.php" class="btn-back">⬅ Back to Dashboard</a>
    </div>

    <?php if(empty($orders)): ?>
        <div style="text-align:center;padding:28px;color:#888;background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.06);">
            <p>No orders found.</p>
        </div>
    <?php else: ?>
    <div style="overflow-x:auto; border-radius:10px;">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Customer</th>
                <th>Email</th>
                <th>Order Type</th>
                <th>Container</th>
                <th>Address</th>
                <th>Gallons</th>
                <th>Total Price</th>
                <th>Assigned Staff</th>
                <th>Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($orders as $row):
            $statusKey   = strtolower(str_replace(' ', '_', $row['order_status']));
            $statusLabel = ucfirst(str_replace('_', ' ', $row['order_status']));
            $orderId     = (int)$row['order_id'];
            $gallons     = (int)$row['gallons'];
            $totalPrice  = $gallons * PRICE_PER_GALLON;
            $isReward    = (int)$row['is_reward'];
            $orderType   = strtolower($row['order_type']);
            $assignedStaff = $row['assigned_staff_name'];
        ?>
        <tr>
            <td><?php echo $orderId; ?></td>
            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
            <td><?php echo htmlspecialchars($row['email']); ?></td>
            <td><?php echo ucfirst(htmlspecialchars($row['order_type'])); ?></td>
            <td><?php echo isset($row['container_type']) ? htmlspecialchars($row['container_type']) : 'N/A'; ?></td>
            <td><?php echo $row['address'] ? htmlspecialchars($row['address']) : 'N/A'; ?></td>
            <td>
                <?php echo $gallons; ?> gal
                <?php if($isReward): ?>
                    <br><span class="badge-reward">🎁 Reward</span>
                <?php endif; ?>
            </td>
            <td class="total-price">
                <?php if($isReward): ?>
                    <span style="text-decoration:line-through;color:#aaa;font-size:0.8rem;">₱<?php echo number_format($totalPrice, 2); ?></span>
                    <br><span style="color:#155724;font-weight:700;">FREE</span>
                <?php else: ?>
                    ₱<?php echo number_format($totalPrice, 2); ?>
                <?php endif; ?>
            </td>
            <td>
                <?php if($orderType === 'walk-in'): ?>
                    <?php if($assignedStaff): ?>
                        <span style="color:#1e88e5;font-weight:600;">🧑‍💼 <?php echo htmlspecialchars($assignedStaff); ?></span>
                    <?php else: ?>
                        <span style="color:#888;font-size:0.8rem;">— Not Assigned —</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="color:#43a047;font-size:0.8rem;">🤖 Auto-assigned</span>
                <?php endif; ?>
            </td>
            <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
            <td><span class="badge badge-<?php echo $statusKey; ?>"><?php echo $statusLabel; ?></span></td>
            <td>
                <?php if($orderType === 'walk-in' && empty($assignedStaff) && !empty($walkin_staff)): ?>
                    <form method="POST" style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">
                        <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                        <select name="staff_id" style="padding:4px 8px;border-radius:6px;border:1px solid #ccc;font-size:0.8rem;max-width:120px;">
                            <option value="">Assign Staff</option>
                            <?php foreach($walkin_staff as $ws): ?>
                                <option value="<?php echo $ws['id']; ?>"><?php echo htmlspecialchars($ws['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="assign_staff" style="padding:4px 10px;background:#1e88e5;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:0.8rem;font-weight:600;">✓</button>
                    </form>
                <?php endif; ?>
                <button class="delete-btn" onclick="confirmDelete(<?php echo $orderId; ?>)">🗑 Delete</button>
                <form id="delete-form-<?php echo $orderId; ?>" method="GET" style="display:none;">
                    <input type="hidden" name="delete" value="<?php echo $orderId; ?>">
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

</div>
</div>

<script>
function confirmDelete(id){
    Swal.fire({
        title: 'Delete Order?',
        text: 'Order #' + id + ' will be permanently deleted. This cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#666',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if(result.isConfirmed){
            document.getElementById('delete-form-' + id).submit();
        }
    });
}
</script>

</body>
</html>
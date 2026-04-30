<?php
session_start();
require '../config.php';

// Only admin can access
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin'){
    header("Location: /water_refill_project/login.php");
    exit;
}

// Delete customer if requested
if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM tb_users WHERE id=? AND user_type='customer'");
    $stmt->execute([$id]);
    // Also delete their orders and rewards
    $conn->prepare("DELETE FROM tb_orders WHERE user_id=?")->execute([$id]);
    $conn->prepare("DELETE FROM tb_rewards WHERE customer_id=?")->execute([$id]);
    header("Location: " . $_SERVER['PHP_SELF'] . "?deleted=1");
    exit;
}

// Get all customers with their reward points and order counts
$stmt = $conn->query("
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.created_at,
        COALESCE(r.points, 0) AS points,
        COUNT(o.order_id) AS total_orders,
        SUM(CASE WHEN LOWER(o.order_status) IN ('delivered','completed') THEN 1 ELSE 0 END) AS completed_orders,
        SUM(CASE WHEN LOWER(o.order_status) = 'pending' THEN 1 ELSE 0 END) AS pending_orders
    FROM tb_users u
    LEFT JOIN tb_rewards r ON r.customer_id = u.id
    LEFT JOIN tb_orders o ON o.user_id = u.id
    WHERE u.user_type = 'customer'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Customers - Admin</title>
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
.page-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.customer-count {
    font-size: 0.85rem;
    background: #e3f2fd;
    color: #1e3a5f;
    padding: 5px 14px;
    border-radius: 20px;
    font-weight: 600;
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

.search-bar {
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.search-bar input {
    padding: 8px 14px;
    border-radius: 8px;
    border: 1px solid #90caf9;
    font-size: 0.88rem;
    width: 280px;
    outline: none;
}
.search-bar input:focus { border-color: #1e88e5; box-shadow: 0 0 0 3px #e3f2fd; }

.table-wrapper { overflow-x: auto; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.09); }
table { width: 100%; border-collapse: collapse; background: #fff; font-size: 0.86rem; }
thead tr { background: linear-gradient(90deg, #1565c0, #1e88e5); color: #fff; }
th { padding: 12px 14px; text-align: left; font-weight: 700; white-space: nowrap; font-size: 0.82rem; letter-spacing: 0.02em; }
td { padding: 11px 14px; border-bottom: 1px solid #f0f4f8; vertical-align: middle; white-space: nowrap; }
tbody tr:nth-child(even) { background: #f5f9ff; }
tbody tr:hover { background: #dceeff; transition: background 0.15s; }

.avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: linear-gradient(135deg, #1e88e5, #0097a7);
    color: #fff; font-weight: 700; font-size: 0.95rem;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.name-cell { display: flex; align-items: center; gap: 10px; }
.name-cell .name { font-weight: 600; color: #1e3a5f; }
.name-cell .joined { font-size: 0.72rem; color: #888; }

.stat-pill {
    display: inline-block; padding: 3px 9px;
    border-radius: 20px; font-size: 0.75rem; font-weight: 600;
}
.pill-orders   { background: #e3f2fd; color: #0d47a1; }
.pill-done     { background: #d4edda; color: #155724; }
.pill-pending  { background: #fff3cd; color: #856404; }
.pill-points   { background: #fce4ec; color: #880e4f; }

.contact-info { font-size: 0.8rem; }
.contact-info .email { color: #1e88e5; }

.btn-delete {
    display: inline-block;
    background: #dc3545;
    color: #fff;
    border: none;
    border-radius: 7px;
    padding: 6px 14px;
    font-size: 0.8rem;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.15s;
}
.btn-delete:hover { background: #a71d2a; }

.empty-state { text-align: center; padding: 40px; color: #aaa; background: #fff; border-radius: 12px; }
.no-result { display: none; text-align: center; padding: 30px; color: #aaa; }
</style>
</head>
<body>

<?php if(isset($_GET['deleted'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    Swal.fire({ icon: 'success', title: 'Deleted!', text: 'Customer has been removed.', timer: 2000, showConfirmButton: false, toast: true, position: 'top-end' });
});
</script>
<?php endif; ?>

<div class="dashboard">
<div class="container" style="max-width:1200px">

    <div class="page-header">
        <div class="page-header-left">
            <h1>👥 Manage Customers</h1>
            <span class="customer-count">👤 <?php echo count($customers); ?> Customer<?php echo count($customers) != 1 ? 's' : ''; ?></span>
        </div>
        <a href="/water_refill_project/admin/admin_dashboard.php" class="btn-back">⬅ Back to Dashboard</a>
    </div>

    <!-- Search -->
    <div class="search-bar">
        <input type="text" id="searchInput" placeholder="🔍  Search by name, email, or contact..." oninput="filterTable()">
    </div>

    <?php if(empty($customers)): ?>
        <div class="empty-state">
            <p style="font-size:2rem;">🚿</p>
            <p>No customers registered yet.</p>
        </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table id="customerTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Email</th>
                    <th>Orders</th>
                    <th>Completed</th>
                    <th>Pending</th>
                    <th>⭐ Points</th>
                    <th>Joined</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($customers as $i => $row):
                $initial = strtoupper(mb_substr($row['full_name'], 0, 1));
            ?>
            <tr>
                <td style="color:#aaa;font-size:0.78rem;"><?php echo $i + 1; ?></td>
                <td>
                    <div class="name-cell">
                        <div class="avatar"><?php echo htmlspecialchars($initial); ?></div>
                        <div>
                            <div class="name"><?php echo htmlspecialchars($row['full_name']); ?></div>
                            <div class="joined">ID #<?php echo $row['id']; ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="contact-info">
                        <div class="email">✉️ <?php echo htmlspecialchars($row['email']); ?></div>
                    </div>
                </td>
                <td><span class="stat-pill pill-orders">🛒 <?php echo (int)$row['total_orders']; ?></span></td>
                <td><span class="stat-pill pill-done">✔️ <?php echo (int)$row['completed_orders']; ?></span></td>
                <td><span class="stat-pill pill-pending">🕐 <?php echo (int)$row['pending_orders']; ?></span></td>
                <td><span class="stat-pill pill-points">⭐ <?php echo (int)$row['points']; ?></span></td>
                <td style="font-size:0.78rem;color:#666;"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                <td>
                    <button class="btn-delete" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo addslashes($row['full_name']); ?>')">🗑 Delete</button>
                    <!-- Hidden delete form for reliable POST -->
                    <form id="delete-form-<?php echo $row['id']; ?>" method="GET" action="" style="display:none;">
                        <input type="hidden" name="delete" value="<?php echo $row['id']; ?>">
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="no-result" id="noResult">😔 No customers match your search.</div>
    </div>
    <?php endif; ?>

</div>
</div>

<script>
function confirmDelete(id, name){
    Swal.fire({
        title: 'Delete Customer?',
        html: `Are you sure you want to delete <strong>${name}</strong>?<br><span style="font-size:0.82rem;color:#dc3545;">This will permanently remove their account, orders, and rewards.</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#666',
        confirmButtonText: 'Yes, delete!',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if(result.isConfirmed){
            document.getElementById('delete-form-' + id).submit();
        }
    });
}

function filterTable(){
    const q = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#customerTable tbody tr');
    let visible = 0;
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const show = text.includes(q);
        row.style.display = show ? '' : 'none';
        if(show) visible++;
    });
    document.getElementById('noResult').style.display = visible === 0 ? 'block' : 'none';
}
</script>

</body>
</html>
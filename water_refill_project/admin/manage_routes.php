<?php
session_start();
require '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin'){
    header("Location: /water_refill_project/login.php");
    exit;
}

$success = "";
$error   = "";

if(isset($_POST['add_route'])){
    $barangay_name = trim($_POST['barangay_name']);
    $description = trim($_POST['description']);

    if(empty($barangay_name)){
        $error = "Please enter barangay name!";
    } else {
        $check = $conn->prepare("SELECT route_id FROM tb_routes WHERE barangay_name = ?");
        $check->execute([$barangay_name]);
        if($check->fetch()){
            $error = "This barangay route already exists!";
        } else {
            $stmt = $conn->prepare("INSERT INTO tb_routes (barangay_name, description) VALUES (?,?)");
            if($stmt->execute([$barangay_name, $description])){
                $success = "Route added successfully!";
            } else {
                $error = "Failed to add route!";
            }
        }
    }
}

if(isset($_POST['delete_route'])){
    $route_id = (int)$_POST['route_id'];
    $stmt = $conn->prepare("DELETE FROM tb_routes WHERE route_id = ?");
    $stmt->execute([$route_id]);
    $success = "Route deleted successfully!";
}

if(isset($_POST['assign_route'])){
    $staff_id = (int)$_POST['staff_id'];
    $route_id = (int)$_POST['route_id'];

    if(empty($staff_id) || empty($route_id)){
        $error = "Please select staff and route!";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO tb_staff_routes (staff_id, route_id) VALUES (?,?)");
            $stmt->execute([$staff_id, $route_id]);
            $success = "Route assigned to staff successfully!";
        } catch(PDOException $e) {
            if($e->getCode() == 23000){
                $error = "This route is already assigned to this staff!";
            } else {
                $error = "Failed to assign route!";
            }
        }
    }
}

if(isset($_POST['remove_assignment'])){
    $assignment_id = (int)$_POST['assignment_id'];
    $stmt = $conn->prepare("DELETE FROM tb_staff_routes WHERE assignment_id = ?");
    $stmt->execute([$assignment_id]);
    $success = "Route assignment removed!";
}

$routes = $conn->query("SELECT * FROM tb_routes ORDER BY barangay_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$staffList = $conn->query("SELECT id, full_name, email FROM tb_users WHERE user_type = 'staff' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$assignments = $conn->query("
    SELECT sr.assignment_id, sr.staff_id, sr.route_id, sr.assigned_date,
           u.full_name as staff_name,
           r.barangay_name
    FROM tb_staff_routes sr
    JOIN tb_users u ON sr.staff_id = u.id
    JOIN tb_routes r ON sr.route_id = r.route_id
    ORDER BY u.full_name, r.barangay_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Routes - Admin</title>
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

.section-title {
    color: #1e3a5f;
    margin: 30px 0 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #0097a7;
}

.table-wrapper { overflow-x: auto; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-top: 15px; }
.data-table { width: 100%; border-collapse: collapse; background: #fff; font-size: 0.92rem; }
.data-table thead { background: #1e88e5; color: #fff; }
.data-table th, .data-table td { padding: 12px 14px; text-align: left; }
.data-table tbody tr:nth-child(even) { background: #f0f7ff; }
.data-table tbody tr:hover { background: #dceeff; transition: background 0.2s; }

.delete-btn {
    display: inline-block;
    background: #dc3545;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 5px 12px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.delete-btn:hover { background: #a71d2a; }

.remove-btn {
    display: inline-block;
    background: #ff9800;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 5px 12px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.remove-btn:hover { background: #f57c00; }

select, textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-family: 'Poppins', sans-serif;
    font-size: 0.95rem;
    margin-top: 5px;
    box-sizing: border-box;
}

textarea {
    resize: vertical;
    min-height: 80px;
}

.no-data { text-align: center; padding: 28px; color: #888; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); margin-top: 20px; }
</style>
</head>
<body>

<div class="dashboard">

    <div class="page-header">
        <h1>🗺️ Manage Routes</h1>
        <a href="/water_refill_project/admin/admin_dashboard.php" class="btn-back">⬅ Back to Dashboard</a>
    </div>

    <div class="container">

        <?php if($success): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            Swal.fire({
                icon: 'success',
                title: '🎉 Success!',
                html: '<p style="font-size:15px;"><?php echo addslashes($success); ?></p>',
                confirmButtonColor: '#0097a7',
                confirmButtonText: 'OK'
            });
        });
        </script>
        <?php endif; ?>

        <?php if($error): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            Swal.fire({
                icon: 'error',
                title: 'Failed!',
                text: '<?php echo addslashes($error); ?>',
                confirmButtonColor: '#0097a7'
            });
        });
        </script>
        <?php endif; ?>

        <h2 class="section-title">📍 Add New Route</h2>
        <form method="POST">
            <input type="hidden" name="add_route" value="1">
            
            <div>
                <label>Barangay Name</label>
                <input type="text" name="barangay_name" placeholder="Enter barangay name" required>
            </div>

            <div>
                <label>Description (Optional)</label>
                <textarea name="description" placeholder="Enter route description or notes"></textarea>
            </div>

            <input type="submit" value="➕ Add Route" style="margin-top: 15px;">
        </form>

        <h2 class="section-title">📋 Existing Routes</h2>
        <?php if(empty($routes)): ?>
            <div class="no-data">
                <p>No routes created yet.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Barangay Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($routes as $index => $route): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($route['barangay_name']); ?></td>
                            <td><?php echo htmlspecialchars($route['description'] ?? '—'); ?></td>
                            <td><?php echo ucfirst($route['status']); ?></td>
                            <td>
                                <button class="delete-btn" onclick="confirmDeleteRoute(<?php echo $route['route_id']; ?>, '<?php echo addslashes($route['barangay_name']); ?>')">🗑️ Delete</button>
                                <form id="delete-route-form-<?php echo $route['route_id']; ?>" method="POST" style="display:none;">
                                    <input type="hidden" name="route_id" value="<?php echo $route['route_id']; ?>">
                                    <input type="hidden" name="delete_route" value="1">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h2 class="section-title">👤 Assign Route to Staff</h2>
        <form method="POST">
            <input type="hidden" name="assign_route" value="1">
            
            <div>
                <label>Select Staff</label>
                <select name="staff_id" required>
                    <option value="">-- Choose Staff --</option>
                    <?php foreach($staffList as $staff): ?>
                        <option value="<?php echo $staff['id']; ?>"><?php echo htmlspecialchars($staff['full_name']); ?> (<?php echo htmlspecialchars($staff['email']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-top: 15px;">
                <label>Select Route</label>
                <select name="route_id" required>
                    <option value="">-- Choose Route --</option>
                    <?php foreach($routes as $route): ?>
                        <option value="<?php echo $route['route_id']; ?>"><?php echo htmlspecialchars($route['barangay_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <input type="submit" value="🔗 Assign Route" style="margin-top: 15px;">
        </form>

        <h2 class="section-title">📊 Current Route Assignments</h2>
        <?php if(empty($assignments)): ?>
            <div class="no-data">
                <p>No route assignments yet.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Staff Name</th>
                            <th>Assigned Route (Barangay)</th>
                            <th>Assigned Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($assignments as $index => $assignment): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($assignment['staff_name']); ?></td>
                            <td><strong><?php echo htmlspecialchars($assignment['barangay_name']); ?></strong></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($assignment['assigned_date'])); ?></td>
                            <td>
                                <button class="remove-btn" onclick="confirmRemoveAssignment(<?php echo $assignment['assignment_id']; ?>)">❌ Remove</button>
                                <form id="remove-assignment-form-<?php echo $assignment['assignment_id']; ?>" method="POST" style="display:none;">
                                    <input type="hidden" name="assignment_id" value="<?php echo $assignment['assignment_id']; ?>">
                                    <input type="hidden" name="remove_assignment" value="1">
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
function confirmDeleteRoute(id, name) {
    Swal.fire({
        title: 'Delete Route?',
        text: 'Are you sure you want to delete route "' + name + '"? This will also remove all staff assignments for this route.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#666',
        confirmButtonText: 'Yes, delete!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if(result.isConfirmed){
            document.getElementById('delete-route-form-' + id).submit();
        }
    });
}

function confirmRemoveAssignment(id) {
    Swal.fire({
        title: 'Remove Assignment?',
        text: 'Are you sure you want to remove this route assignment?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff9800',
        cancelButtonColor: '#666',
        confirmButtonText: 'Yes, remove!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if(result.isConfirmed){
            document.getElementById('remove-assignment-form-' + id).submit();
        }
    });
}
</script>

</body>
</html>
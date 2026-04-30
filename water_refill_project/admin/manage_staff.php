<?php
session_start();
require '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin'){
    header("Location: /water_refill_project/login.php");
    exit;
}

$success = "";
$error   = "";

// Handle staff creation
if(isset($_POST['add_staff'])){
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $password  = $_POST['password'];
    $contact   = trim($_POST['contact']);
    $walkin    = isset($_POST['walkin_assigned']) ? 1 : 0;

    if(empty($full_name) || empty($email) || empty($password) || empty($contact)){
        $error = "Please fill in all fields!";
    } elseif(strlen($password) < 6){
        $error = "Password must be at least 6 characters!";
    } else {
        $check = $conn->prepare("SELECT id FROM tb_users WHERE email = ?");
        $check->execute([$email]);
        if($check->fetch()){
            $error = "Email already exists!";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO tb_users (user_type, full_name, email, password, contact, walkin_assigned) VALUES ('staff',?,?,?,?,?)");
            if($stmt->execute([$full_name, $email, $hashed, $contact, $walkin])){
                $success = "Staff account created successfully!";
            } else {
                $error = "Failed to create staff account!";
            }
        }
    }
}

// Handle staff edit
if(isset($_POST['edit_staff'])){
    $edit_id   = (int)$_POST['staff_id'];
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $contact   = trim($_POST['contact']);
    $password  = $_POST['password'];
    $walkin    = isset($_POST['walkin_assigned']) ? 1 : 0;

    if(empty($full_name) || empty($email) || empty($contact)){
        $error = "Please fill in all fields!";
    } else {
        $check = $conn->prepare("SELECT id FROM tb_users WHERE email = ? AND id != ?");
        $check->execute([$email, $edit_id]);
        if($check->fetch()){
            $error = "Email already exists!";
        } else {
            if(!empty($password)){
                if(strlen($password) < 6){
                    $error = "Password must be at least 6 characters!";
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE tb_users SET full_name=?, email=?, contact=?, password=?, walkin_assigned=? WHERE id=? AND user_type='staff'");
                    $stmt->execute([$full_name, $email, $contact, $hashed, $walkin, $edit_id]);
                    $success = "Staff account updated successfully!";
                }
            } else {
                $stmt = $conn->prepare("UPDATE tb_users SET full_name=?, email=?, contact=?, walkin_assigned=? WHERE id=? AND user_type='staff'");
                $stmt->execute([$full_name, $email, $contact, $walkin, $edit_id]);
                $success = "Staff account updated successfully!";
            }
        }
    }
}

// Handle staff deletion
if(isset($_POST['delete_staff'])){
    $delete_id = (int)$_POST['staff_id'];
    $stmt = $conn->prepare("DELETE FROM tb_users WHERE id = ? AND user_type = 'staff'");
    $stmt->execute([$delete_id]);
    $success = "Staff account deleted.";
}

// Fetch all staff
$staffList = $conn->query("SELECT id, full_name, email, contact, walkin_assigned FROM tb_users WHERE user_type = 'staff' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Staff - Admin</title>
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

.staff-table-wrapper { overflow-x: auto; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-top: 30px; }
.staff-table { width: 100%; border-collapse: collapse; background: #fff; font-size: 0.92rem; }
.staff-table thead { background: #1e88e5; color: #fff; }
.staff-table th, .staff-table td { padding: 12px 14px; text-align: left; }
.staff-table tbody tr:nth-child(even) { background: #f0f7ff; }
.staff-table tbody tr:hover { background: #dceeff; transition: background 0.2s; }

.edit-btn {
    display: inline-block;
    background: #1e88e5;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 5px 12px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.2s;
    margin-bottom: 5px;
    width: 90px;
    text-align: center;
}
.edit-btn:hover { background: #1565c0; }

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
    text-decoration: none;
    transition: background 0.2s;
    width: 90px;
    text-align: center;
}
.delete-btn:hover { background: #a71d2a; }

.no-staff { text-align: center; padding: 28px; color: #888; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); margin-top: 20px; }
input[type="button"] {
    background: #0097a7; color: white; border: none;
    padding: 13px 30px; border-radius: 50px;
    font-family: 'Poppins', sans-serif; font-size: 0.95rem;
    font-weight: 600; cursor: pointer;
    transition: background 0.2s ease, transform 0.2s ease;
    width: 100%; margin-top: 4px;
}
input[type="button"]:hover { background: #006064; transform: translateY(-2px); }
input[type="button"]:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
</style>
</head>
<body>

<div class="dashboard">

    <div class="page-header">
        <h1>🧑‍💼 Manage Staff</h1>
        <a href="/water_refill_project/admin/admin_dashboard.php" class="btn-back">⬅ Back to Dashboard</a>
    </div>

    <div class="container">

        <div class="water-logo">
            <div class="drop">🧑‍💼</div>
        </div>

        <h2 style="text-align:center; border:none;">Create Staff Account</h2>

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

        <!-- Create Form -->
        <form method="POST" id="staffForm">
            <input type="hidden" name="add_staff" value="1">

            <div>
                <label>Full Name</label>
                <input type="text" id="full_name" name="full_name"
                       placeholder="Enter full name"
                       value="<?php echo isset($_POST['full_name']) && isset($_POST['add_staff']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
            </div>

            <div>
                <label>Email</label>
                <input type="email" id="email" name="email"
                       placeholder="Enter email address"
                       value="<?php echo isset($_POST['email']) && isset($_POST['add_staff']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div>
                <label>Password</label>
                <input type="password" id="password" name="password"
                       placeholder="Enter password (min. 6 characters)">
            </div>

            <div>
                <label>Contact</label>
                <input type="text" id="contact" name="contact"
                       placeholder="Enter contact number"
                       value="<?php echo isset($_POST['contact']) && isset($_POST['add_staff']) ? htmlspecialchars($_POST['contact']) : ''; ?>">
            </div>

            <div style="display:flex;align-items:center;gap:10px;margin:10px 0;">
                <input type="checkbox" id="walkin_assigned" name="walkin_assigned" value="1" style="width:auto;margin:0;">
                <label for="walkin_assigned" style="margin:0;font-weight:500;cursor:pointer;">Can handle walk-in orders</label>
            </div>

            <input type="button" id="createBtn" value="Create Staff Account" onclick="confirmCreate()">
        </form>

        <!-- Staff List -->
        <?php if(empty($staffList)): ?>
            <div class="no-staff">
                <p>No staff accounts found.</p>
            </div>
        <?php else: ?>
            <div class="staff-table-wrapper">
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Walk-in</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($staffList as $index => $staff): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($staff['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($staff['email']); ?></td>
                            <td><?php echo htmlspecialchars($staff['contact'] ?? '—'); ?></td>
                            <td>
                                <?php if($staff['walkin_assigned']): ?>
                                    <span style="color: #28a745; font-weight: bold;">✓ Yes</span>
                                <?php else: ?>
                                    <span style="color: #dc3545;">✗ No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex;flex-direction:column;gap:5px;align-items:flex-start;">
                                    <button class="edit-btn" onclick="openEdit(
                                        <?php echo $staff['id']; ?>,
                                        '<?php echo addslashes($staff['full_name']); ?>',
                                        '<?php echo addslashes($staff['email']); ?>',
                                        '<?php echo addslashes($staff['contact'] ?? ''); ?>',
                                        <?php echo $staff['walkin_assigned']; ?>
                                    )">✏️ Edit</button>
                                    <button class="delete-btn" onclick="confirmDelete(<?php echo $staff['id']; ?>, '<?php echo addslashes($staff['full_name']); ?>')">🗑️ Delete</button>
                                </div>

                                <form id="delete-form-<?php echo $staff['id']; ?>" method="POST" style="display:none;">
                                    <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                    <input type="hidden" name="delete_staff" value="1">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Hidden Edit Form -->
        <form id="edit-form" method="POST" style="display:none;">
            <input type="hidden" name="edit_staff" value="1">
            <input type="hidden" name="staff_id" id="edit-staff-id">
            <input type="hidden" name="full_name" id="edit-full-name-input">
            <input type="hidden" name="email" id="edit-email-input">
            <input type="hidden" name="contact" id="edit-contact-input">
            <input type="hidden" name="password" id="edit-password-input">
            <input type="hidden" name="walkin_assigned" id="edit-walkin-input" value="0">
        </form>

    </div>
</div>

<script>
function confirmCreate() {
    const full_name = document.getElementById('full_name').value.trim();
    const email     = document.getElementById('email').value.trim();
    const password  = document.getElementById('password').value.trim();
    const contact   = document.getElementById('contact').value.trim();

    if(!full_name){
        Swal.fire({ icon: 'warning', title: 'Missing Field', text: 'Please enter the full name.', confirmButtonColor: '#0097a7' });
        return;
    }
    if(!email){
        Swal.fire({ icon: 'warning', title: 'Missing Field', text: 'Please enter the email.', confirmButtonColor: '#0097a7' });
        return;
    }
    if(password.length < 6){
        Swal.fire({ icon: 'warning', title: 'Weak Password', text: 'Password must be at least 6 characters.', confirmButtonColor: '#0097a7' });
        return;
    }
    if(!contact){
        Swal.fire({ icon: 'warning', title: 'Missing Field', text: 'Please enter the contact number.', confirmButtonColor: '#0097a7' });
        return;
    }

    Swal.fire({
        icon: 'question',
        title: 'Confirm Staff Creation',
        html:
            '<table style="width:100%;text-align:left;font-size:14px;border-collapse:collapse;">' +
            '<tr><td style="padding:5px 0;color:#555;">Full Name:</td><td style="padding:5px 0;font-weight:bold;">' + full_name + '</td></tr>' +
            '<tr><td style="padding:5px 0;color:#555;">Email:</td><td style="padding:5px 0;font-weight:bold;">' + email + '</td></tr>' +
            '<tr><td style="padding:5px 0;color:#555;">Contact:</td><td style="padding:5px 0;font-weight:bold;">' + contact + '</td></tr>' +
            '</table>',
        showCancelButton: true,
        confirmButtonText: '✅ Yes, Create Account!',
        cancelButtonText: '❌ Cancel',
        confirmButtonColor: '#0097a7',
        cancelButtonColor: '#e53935',
        reverseButtons: true
    }).then(function(result){
        if(result.isConfirmed){
            const btn = document.getElementById('createBtn');
            btn.value = 'Creating...';
            btn.disabled = true;
            document.getElementById('staffForm').submit();
        }
    });
}

function openEdit(id, name, email, contact, walkin) {
    Swal.fire({
        title: '✏️ Edit Staff',
        html:
            `<div style="text-align:left;">
                <label style="font-size:0.82rem;font-weight:600;color:#1e3a5f;">Full Name</label>
                <input id="swal-name" class="swal2-input" placeholder="Full Name" value="${name}" style="margin:4px 0 10px;">
                <label style="font-size:0.82rem;font-weight:600;color:#1e3a5f;">Email</label>
                <input id="swal-email" type="email" class="swal2-input" placeholder="Email" value="${email}" style="margin:4px 0 10px;">
                <label style="font-size:0.82rem;font-weight:600;color:#1e3a5f;">Contact</label>
                <input id="swal-contact" class="swal2-input" placeholder="Contact" value="${contact}" style="margin:4px 0 10px;">
                <label style="font-size:0.82rem;font-weight:600;color:#1e3a5f;">New Password <span style="color:#888;font-weight:400;">(leave blank to keep current)</span></label>
                <input id="swal-password" type="password" class="swal2-input" placeholder="New password (optional)" style="margin:4px 0 4px;">
                <div style="display:flex;align-items:center;gap:8px;margin-top:10px;">
                    <input type="checkbox" id="swal-walkin" value="1" ${walkin ? 'checked' : ''} style="width:auto;margin:0;">
                    <label for="swal-walkin" style="margin:0;font-size:0.85rem;cursor:pointer;">Can handle walk-in orders</label>
                </div>
            </div>`,
        showCancelButton: true,
        confirmButtonText: '💾 Save Changes',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#0097a7',
        cancelButtonColor: '#666',
        focusConfirm: false,
        preConfirm: () => {
            const newName     = document.getElementById('swal-name').value.trim();
            const newEmail    = document.getElementById('swal-email').value.trim();
            const newContact  = document.getElementById('swal-contact').value.trim();
            const newPassword = document.getElementById('swal-password').value.trim();
            const newWalkin   = document.getElementById('swal-walkin').checked ? 1 : 0;

            if(!newName || !newEmail || !newContact){
                Swal.showValidationMessage('Please fill in all required fields.');
                return false;
            }
            if(newPassword && newPassword.length < 6){
                Swal.showValidationMessage('Password must be at least 6 characters.');
                return false;
            }
            return { newName, newEmail, newContact, newPassword, newWalkin };
        }
    }).then((result) => {
        if(result.isConfirmed){
            document.getElementById('edit-staff-id').value        = id;
            document.getElementById('edit-full-name-input').value = result.value.newName;
            document.getElementById('edit-email-input').value     = result.value.newEmail;
            document.getElementById('edit-contact-input').value   = result.value.newContact;
            document.getElementById('edit-password-input').value  = result.value.newPassword;
            document.getElementById('edit-walkin-input').value    = result.value.newWalkin;
            document.getElementById('edit-form').submit();
        }
    });
}

function confirmDelete(id, name) {
    Swal.fire({
        title: 'Delete Staff?',
        text: 'Are you sure you want to delete "' + name + '"? This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#666',
        confirmButtonText: 'Yes, delete!',
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
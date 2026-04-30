<?php
session_start();
require '../config.php';

// Only admin can access
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin'){
    header("Location: /water_refill_project/login.php");
    exit;
}

$message = "";

// Handle staff creation
if(isset($_POST['add_staff'])){
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $contact = $_POST['contact'];

    $stmt = $conn->prepare("INSERT INTO tb_users (user_type, full_name, email, password, contact) 
                            VALUES ('staff',?,?,?,?)");
    if($stmt->execute([$full_name, $email, $password, $contact])){
        $message = "✅ Staff Account Created!";
    } else {
        $message = "❌ Failed to create staff account!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Staff - Admin</title>
<link rel="stylesheet" href="/water_refill_project/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<div class="dashboard">

<h1>🧑‍💼 Create Staff Account</h1>

<div class="container">

<?php if($message): ?>
    <p class="success"><?php echo $message; ?></p>
<?php endif; ?>

<form method="POST" id="staffForm">
    <label>Full Name:</label>
    <input type="text" name="full_name" required>

    <label>Email:</label>
    <input type="email" name="email" required>

    <label>Password:</label>
    <input type="password" name="password" required>

    <label>Contact:</label>
    <input type="text" name="contact">

    <input type="submit" name="add_staff" value="Create Staff">
</form>

<a class="btn-back" href="/water_refill_project/admin/admin_dashboard.php">⬅ Back to Dashboard</a>

</div>
</div>

<script>
document.getElementById('staffForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const fullName = this.full_name.value;

    Swal.fire({
        title: 'Create Staff Account?',
        text: 'Are you sure you want to create an account for "' + fullName + '"?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1e88e5',
        cancelButtonColor: '#666',
        confirmButtonText: 'Yes, create it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if(result.isConfirmed){
            this.submit();
        }
    });
});
</script>

</body>
</html>
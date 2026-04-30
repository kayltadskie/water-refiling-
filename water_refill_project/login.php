<?php
session_start();
require __DIR__ . '/config.php';

$error = "";

if(isset($_POST['login'])){
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $role     = isset($_POST['role']) ? $_POST['role'] : '';

    if(empty($role)){
        $error = "Please select a role!";
    } else {
        $stmt = $conn->prepare("SELECT * FROM tb_users WHERE email = ? AND user_type = ?");
        $stmt->execute([$email, $role]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if($user && password_verify($password, $user['password'])){
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['full_name'] = $user['full_name'];

            if($user['user_type'] == 'admin'){
                header("Location: /water_refill_project/admin/admin_dashboard.php");
                exit;
            } elseif($user['user_type'] == 'staff'){
                header("Location: /water_refill_project/staff/staff_dashboard.php");
                exit;
            } else {
                header("Location: /water_refill_project/customer/index.php");
                exit;
            }

        } else {
            // DEBUG: Remove these 3 lines once login works!
            $stmt2 = $conn->prepare("SELECT * FROM tb_users WHERE email = ?");
            $stmt2->execute([$email]);
            $debugUser = $stmt2->fetch(PDO::FETCH_ASSOC);

            if(!$debugUser){
                $error = "No account found with that email.";
            } elseif($debugUser['user_type'] !== $role){
                $error = "Wrong role selected. Your account role is: " . $debugUser['user_type'];
            } else {
                $error = "Wrong password.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Water Refill Station</title>
    <link rel="stylesheet" href="/water_refill_project/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<div class="container">
    <h2>💧 Water Refill Station Login</h2>

    <?php if($error): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            Swal.fire({
                icon: 'error',
                title: 'Login Failed',
                text: '<?php echo addslashes($error); ?>',
                confirmButtonColor: '#1e88e5'
            });
        });
        </script>
    <?php endif; ?>

    <form method="POST">
        <label>Email:</label>
        <input type="email" name="email" required
               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        <br><br>

        <label>Password:</label>
        <input type="password" name="password" required>
        <br><br>

        <label>Login as:</label>
        <select name="role" required>
            <option value="">Select role</option>
            <option value="admin"    <?php echo (isset($_POST['role']) && $_POST['role']=='admin')    ? 'selected' : ''; ?>>Admin</option>
            <option value="staff"   <?php echo (isset($_POST['role']) && $_POST['role']=='staff')    ? 'selected' : ''; ?>>Staff</option>
            <option value="customer"<?php echo (isset($_POST['role']) && $_POST['role']=='customer') ? 'selected' : ''; ?>>Customer</option>
        </select>
        <br><br>

        <input type="submit" name="login" value="Login">
    </form>

    <p>Don't have an account? <a href="/water_refill_project/register.php">Register here</a></p>
</div>

</body>
</html>
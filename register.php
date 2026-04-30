<?php
require 'config.php';

$success = "";
$error = "";

if(isset($_POST['register'])){
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $password  = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $contact   = trim($_POST['contact']);
    $brgy      = trim($_POST['brgy']);

    // Check if email already exists
    $check = $conn->prepare("SELECT id FROM tb_users WHERE email=?");
    $check->execute([$email]);
    if($check->fetch()){
        $error = "Email already exists!";
    } else {
        $stmt = $conn->prepare("INSERT INTO tb_users (user_type, full_name, email, password, contact, brgy) VALUES ('customer',?,?,?,?,?)");
        $stmt->execute([$full_name, $email, $password, $contact, $brgy]);
        $success = "You have successfully registered! You can now login.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Water Refill Station</title>
    <link rel="stylesheet" href="/water_refill_project/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
    input[type="button"] {
        background: #0097a7;
        color: white;
        border: none;
        padding: 13px 30px;
        border-radius: 50px;
        font-family: 'Poppins', sans-serif;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s ease, transform 0.2s ease;
        width: 100%;
        margin-top: 4px;
    }
    input[type="button"]:hover {
        background: #006064;
        transform: translateY(-2px);
    }
    input[type="button"]:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    </style>
</head>
<body>

<div class="container">

    <div class="water-logo">
        <div class="drop">💧</div>
    </div>

    <h2 style="text-align:center; border:none;">Customer Registration</h2>

    <?php if($success): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        Swal.fire({
            icon: 'success',
            title: '🎉 Registered Successfully!',
            html: '<p style="font-size:15px;">Welcome to <strong>Water Refill Station</strong>!<br>You can now login with your account.</p>',
            confirmButtonColor: '#0097a7',
            confirmButtonText: 'Go to Login'
        }).then(function(){
            window.location.href = '/water_refill_project/login.php';
        });
    });
    </script>
    <?php endif; ?>

    <?php if($error): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        Swal.fire({
            icon: 'error',
            title: 'Registration Failed!',
            text: '<?php echo addslashes($error); ?>',
            confirmButtonColor: '#0097a7'
        });
    });
    </script>
    <?php endif; ?>

    <form method="POST" id="registerForm">

        <input type="hidden" name="register" value="1">

        <div>
            <label>Full Name</label>
            <input type="text" id="full_name" name="full_name" placeholder="Enter your full name">
        </div>

        <div>
            <label>Email</label>
            <input type="email" id="email" name="email" placeholder="Enter your email">
        </div>

        <div>
            <label>Password</label>
            <input type="password" id="password" name="password" placeholder="Enter your password (min. 6 characters)">
        </div>

        <div>
            <label>Contact</label>
            <input type="text" id="contact" name="contact" placeholder="Enter your contact number">
        </div>

        <div>
            <label>Barangay</label>
            <input type="text" id="brgy" name="brgy" placeholder="Enter your barangay">
        </div>

        <input type="button" id="registerBtn" value="Register" onclick="confirmRegister()">

    </form>

    <p>Already have an account? <a href="/water_refill_project/login.php">Login here</a></p>

</div>

<script>
function confirmRegister() {
    const full_name = document.getElementById('full_name').value.trim();
    const email     = document.getElementById('email').value.trim();
    const password  = document.getElementById('password').value.trim();
    const contact   = document.getElementById('contact').value.trim();
    const brgy      = document.getElementById('brgy').value.trim();

    // Validation
    if(!full_name){
        Swal.fire({ icon: 'warning', title: 'Missing Field', text: 'Please enter your full name.', confirmButtonColor: '#0097a7' });
        return;
    }
    if(!email){
        Swal.fire({ icon: 'warning', title: 'Missing Field', text: 'Please enter your email.', confirmButtonColor: '#0097a7' });
        return;
    }
    if(password.length < 6){
        Swal.fire({ icon: 'warning', title: 'Weak Password', text: 'Password must be at least 6 characters.', confirmButtonColor: '#0097a7' });
        return;
    }
    if(!contact){
        Swal.fire({ icon: 'warning', title: 'Missing Field', text: 'Please enter your contact number.', confirmButtonColor: '#0097a7' });
        return;
    }
    if(!brgy){
        Swal.fire({ icon: 'warning', title: 'Missing Field', text: 'Please enter your barangay.', confirmButtonColor: '#0097a7' });
        return;
    }

    // Confirmation popup
    Swal.fire({
        icon: 'question',
        title: 'Confirm Registration',
        html:
            '<table style="width:100%;text-align:left;font-size:14px;border-collapse:collapse;">' +
            '<tr><td style="padding:5px 0;color:#555;">Full Name:</td><td style="padding:5px 0;font-weight:bold;">' + full_name + '</td></tr>' +
            '<tr><td style="padding:5px 0;color:#555;">Email:</td><td style="padding:5px 0;font-weight:bold;">' + email + '</td></tr>' +
            '<tr><td style="padding:5px 0;color:#555;">Contact:</td><td style="padding:5px 0;font-weight:bold;">' + contact + '</td></tr>' +
            '<tr><td style="padding:5px 0;color:#555;">Barangay:</td><td style="padding:5px 0;font-weight:bold;">' + brgy + '</td></tr>' +
            '</table>',
        showCancelButton: true,
        confirmButtonText: '✅ Yes, Register Me!',
        cancelButtonText: '❌ Cancel',
        confirmButtonColor: '#0097a7',
        cancelButtonColor: '#e53935',
        reverseButtons: true
    }).then(function(result){
        if(result.isConfirmed){
            const btn = document.getElementById('registerBtn');
            btn.value = 'Registering...';
            btn.disabled = true;
            document.getElementById('registerForm').submit();
        }
    });
}
</script>

</body>
</html>

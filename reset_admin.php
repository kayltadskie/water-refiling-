<?php
require __DIR__ . '/config.php';

$hash = password_hash('admin123', PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE tb_users SET password = ? WHERE email = 'admin@gmail.com'");
$stmt->execute([$hash]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reset Admin Password</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; background: #f0f4f8; }
        .box { background: #fff; padding: 30px; border-radius: 10px; max-width: 500px; margin: auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2   { color: #1e3a5f; }
        p    { font-size: 15px; margin: 8px 0; }
        .success { color: #155724; font-weight: bold; font-size: 16px; }
        .info    { background: #cfe2ff; padding: 14px; border-radius: 6px; margin-top: 16px; color: #084298; }
        .warning { margin-top: 20px; padding: 14px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 6px; color: #856404; font-weight: bold; }
    </style>
</head>
<body>
<div class="box">
    <h2>🔧 Reset Admin Password</h2>

    <p class="success">✅ Admin password has been reset successfully!</p>

    <div class="info">
        <p>📧 <strong>Email:</strong> admin@gmail.com</p>
        <p>🔑 <strong>Password:</strong> admin123</p>
        <p>👤 <strong>Role:</strong> Admin</p>
    </div>

    <div class="warning">
        ⚠️ DELETE this file immediately after use!<br>
        Never leave it on your server.
    </div>

    <p style="margin-top:20px;">
        <a href="/water_refill_project/login.php" style="color:#1e88e5; font-weight:bold;">→ Go to Login</a>
    </p>
</div>
</body>
</html>
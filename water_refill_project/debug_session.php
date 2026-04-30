<?php
// Simple session test - run this in your browser
echo "<h2>Session Diagnostic</h2>";

// Test 1: Check if session can start
echo "<h3>Test 1: Session Start</h3>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "<p style='color:green'>Session started successfully. ID: " . session_id() . "</p>";
} else {
    echo "<p style='color:orange'>Session was already active. ID: " . session_id() . "</p>";
}

// Test 2: Write and read a test value
echo "<h3>Test 2: Session Write/Read</h3>";
if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 0;
    echo "<p>First visit - counter initialized to 0</p>";
} else {
    $_SESSION['test_counter']++;
    echo "<p style='color:green'>Counter incremented to: " . $_SESSION['test_counter'] . "</p>";
}

// Test 3: Check cookies
echo "<h3>Test 3: Cookies Received</h3>";
if (empty($_COOKIE)) {
    echo "<p style='color:red'>WARNING: No cookies received from browser!</p>";
    echo "<p>Make sure cookies are enabled in your browser.</p>";
} else {
    echo "<pre>";
    print_r($_COOKIE);
    echo "</pre>";
}

// Test 4: Check session save path
echo "<h3>Test 4: Session Save Path</h3>";
$savePath = session_save_path();
if (empty($savePath)) {
    $savePath = sys_get_temp_dir();
}
echo "<p>Save path: $savePath</p>";
echo "<p>Exists: " . (is_dir($savePath) ? 'YES' : 'NO') . "</p>";
echo "<p>Writable: " . (is_writable($savePath) ? 'YES' : 'NO') . "</p>";

// Test 5: Check for headers already sent
echo "<h3>Test 5: Headers</h3>";
$file = '';
$line = 0;
$headersSent = headers_sent($file, $line);
echo "<p>Headers already sent: " . ($headersSent ? "YES (in $file line $line)" : "NO") . "</p>";

echo "<hr><p><a href='debug_session.php'><b>Click here to refresh</b></a> - Counter should go up each time.</p>";
echo "<p>If counter resets to 0 on refresh, sessions are NOT working.</p>";

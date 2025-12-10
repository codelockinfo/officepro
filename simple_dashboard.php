<?php
/**
 * Simple Dashboard - No includes, test session
 */

session_start();

echo "<h1>Simple Dashboard Test</h1>";

echo "<h2>Session Status:</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Status: " . session_status() . " (2 = active)<br><br>";

echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

if (isset($_SESSION['user_id'])) {
    echo "<p style='color: green; font-weight: bold;'>✓ You are logged in!</p>";
    echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
    echo "<p>Email: " . ($_SESSION['email'] ?? 'Not set') . "</p>";
    echo "<p>Name: " . ($_SESSION['full_name'] ?? 'Not set') . "</p>";
    echo "<p>Role: " . ($_SESSION['role'] ?? 'Not set') . "</p>";
    
    echo "<hr>";
    echo "<h3>Try accessing real dashboard:</h3>";
    echo "<a href='/officepro/app/views/dashboard.php' style='padding: 10px 20px; background: #4da6ff; color: white; text-decoration: none; border-radius: 5px; display: inline-block;'>Go to Dashboard</a>";
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ You are NOT logged in!</p>";
    echo "<p><a href='/officepro/login.php'>Go to Login</a></p>";
}

echo "<hr>";
echo "<a href='/officepro/debug_login.php'>Debug Login</a> | ";
echo "<a href='/officepro/test_session.php'>Test Session</a> | ";
echo "<a href='/officepro/'>Home</a>";
?>



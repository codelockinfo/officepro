<?php
/**
 * Debug Login - Simple test login without redirects
 */
session_start();

require_once __DIR__ . '/app/helpers/Database.php';
require_once __DIR__ . '/app/helpers/Auth.php';

echo "<h1>Login Debug</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    echo "<h2>Attempting Login...</h2>";
    echo "<p>Email: " . htmlspecialchars($email) . "</p>";
    
    $result = Auth::login($email, $password);
    
    echo "<h3>Result:</h3>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    echo "<h3>Session After Login:</h3>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    
    echo "<h3>Session ID: " . session_id() . "</h3>";
    
    if ($result['success']) {
        echo "<p style='color: green; font-weight: bold;'>✓ Login successful!</p>";
        echo "<p><a href='/public_html/app/views/dashboard.php'>Go to Dashboard</a></p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>✗ Login failed!</p>";
    }
} else {
    // Check if user is logged in
    echo "<h2>Current Session Status:</h2>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    
    echo "<p>Is Logged In: " . (Auth::isLoggedIn() ? 'YES' : 'NO') . "</p>";
    
    echo "<hr>";
    echo "<h2>Test Login:</h2>";
    
    // Get first user from database for testing
    $db = Database::getInstance();
    $user = $db->fetchOne("SELECT email FROM users LIMIT 1");
    
    ?>
    <form method="POST">
        <div style="margin-bottom: 10px;">
            <label>Email:</label><br>
            <input type="email" name="email" value="<?php echo $user['email'] ?? 'admin@system.com'; ?>" style="width: 300px; padding: 5px;">
        </div>
        <div style="margin-bottom: 10px;">
            <label>Password:</label><br>
            <input type="password" name="password" placeholder="Enter password" style="width: 300px; padding: 5px;">
        </div>
        <button type="submit" style="padding: 10px 20px; background: #4da6ff; color: white; border: none; cursor: pointer;">Login</button>
    </form>
    
    <hr>
    <p><a href="/public_html/">Back to Home</a></p>
    <?php
}
?>



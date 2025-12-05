<?php
/**
 * Check Profile Photo Debug
 */
session_start();

require_once __DIR__ . '/app/helpers/Database.php';
require_once __DIR__ . '/app/helpers/Auth.php';

if (!Auth::isLoggedIn()) {
    die('Please login first');
}

$userId = Auth::getCurrentUser()['id'];
$db = Database::getInstance();

echo "<h1>Profile Photo Diagnostic</h1>";

// Check database
$user = $db->fetchOne("SELECT id, full_name, email, profile_image FROM users WHERE id = ?", [$userId]);

echo "<h2>Database Info:</h2>";
echo "<pre>";
print_r($user);
echo "</pre>";

echo "<h2>Session Info:</h2>";
echo "<pre>";
echo "Profile Image in Session: " . ($_SESSION['profile_image'] ?? 'NOT SET') . "\n";
echo "</pre>";

echo "<h2>File Path Check:</h2>";
$imagePath = $user['profile_image'];
$fullPath = __DIR__ . '/' . $imagePath;
echo "Image Path from DB: " . htmlspecialchars($imagePath) . "<br>";
echo "Full Path: " . htmlspecialchars($fullPath) . "<br>";
echo "File Exists: " . (file_exists($fullPath) ? 'YES ✓' : 'NO ✗') . "<br>";

if (file_exists($fullPath)) {
    echo "File Size: " . filesize($fullPath) . " bytes<br>";
    echo "File Modified: " . date('Y-m-d H:i:s', filemtime($fullPath)) . "<br>";
}

echo "<h2>Image Display Test:</h2>";
echo "<p>Using path from database:</p>";
echo "<img src='/officepro/" . htmlspecialchars($imagePath) . "' style='max-width: 200px; border: 2px solid red;'>";

echo "<h2>Uploaded Files in uploads/profiles/:</h2>";
$files = glob('uploads/profiles/*');
echo "<ul>";
foreach ($files as $file) {
    $isCurrentFile = (str_replace('\\', '/', $file) === str_replace('\\', '/', $imagePath));
    echo "<li style='" . ($isCurrentFile ? 'font-weight: bold; color: green;' : '') . "'>";
    echo basename($file) . " (" . filesize($file) . " bytes) ";
    if ($isCurrentFile) echo " ← CURRENT";
    echo "<br><img src='$file' style='max-width: 100px; margin: 5px;'>";
    echo "</li>";
}
echo "</ul>";

echo "<hr>";
echo "<a href='/officepro/app/views/profile.php'>Go to Profile Page</a> | ";
echo "<a href='/officepro/app/views/dashboard.php'>Go to Dashboard</a>";
?>


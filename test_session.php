<?php
/**
 * Session Test Page - Debug Helper
 */
session_start();

echo "<h1>Session Debug Info</h1>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . " (1=none, 2=active)\n";
echo "\nSession Data:\n";
print_r($_SESSION);
echo "\n\nServer Info:\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Current URL: " . $_SERVER['REQUEST_URI'] . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "</pre>";

echo "<hr>";
echo "<a href='/officepro/login.php'>Go to Login</a> | ";
echo "<a href='/officepro/app/views/dashboard.php'>Go to Dashboard</a> | ";
echo "<a href='javascript:history.back()'>Go Back</a>";


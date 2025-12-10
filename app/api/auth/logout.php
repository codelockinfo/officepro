<?php
/**
 * Logout API Endpoint
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Auth.php';

$result = Auth::logout();

echo json_encode($result);





<?php
/**
 * User Details API (System Admin)
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';

// Check authentication
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

Auth::requireRole(['system_admin']);

$userId = $_GET['id'] ?? 0;
$db = Database::getInstance();

// Get user details
$user = $db->fetchOne(
    "SELECT u.*, c.company_name, d.name as department_name 
    FROM users u 
    JOIN companies c ON u.company_id = c.id 
    LEFT JOIN departments d ON u.department_id = d.id 
    WHERE u.id = ?",
    [$userId]
);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Remove password from response
unset($user['password']);

echo json_encode([
    'success' => true,
    'data' => $user
]);



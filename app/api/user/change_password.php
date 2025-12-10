<?php
/**
 * Change Password API
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Validator.php';

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId = Auth::getCurrentUser()['id'];
$input = json_decode(file_get_contents('php://input'), true);
$db = Database::getInstance();

$currentPassword = $input['current_password'] ?? '';
$newPassword = $input['new_password'] ?? '';

$validator = new Validator();
$validator->required($currentPassword, 'Current Password');
$validator->required($newPassword, 'New Password');
$validator->minLength($newPassword, 8, 'New Password');

if ($validator->hasErrors()) {
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->getErrors()]);
    exit;
}

// Get current user password
$user = $db->fetchOne("SELECT password FROM users WHERE id = ?", [$userId]);

if (!$user || !password_verify($currentPassword, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
    exit;
}

try {
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    
    $db->execute(
        "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?",
        [$hashedPassword, $userId]
    );
    
    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
} catch (Exception $e) {
    error_log("Change Password Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to change password']);
}



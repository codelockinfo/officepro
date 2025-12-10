<?php
/**
 * Mark Notification as Read API
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Tenant.php';

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$companyId = Tenant::getCurrentCompanyId();
$userId = Auth::getCurrentUser()['id'];
$input = json_decode(file_get_contents('php://input'), true);
$db = Database::getInstance();

$notificationId = $input['id'] ?? 0;

try {
    $db->execute(
        "UPDATE notifications SET read_status = 1 
        WHERE id = ? AND company_id = ? AND user_id = ?",
        [$notificationId, $companyId, $userId]
    );
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Mark Read Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to mark as read']);
}





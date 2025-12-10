<?php
/**
 * Fetch Notifications API
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

$companyId = Tenant::getCurrentCompanyId();
$userId = Auth::getCurrentUser()['id'];
$db = Database::getInstance();

// Get unread count
$unreadCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM notifications 
    WHERE company_id = ? AND user_id = ? AND read_status = 0",
    [$companyId, $userId]
);

// Get recent notifications (last 20)
$notifications = $db->fetchAll(
    "SELECT * FROM notifications 
    WHERE company_id = ? AND user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20",
    [$companyId, $userId]
);

echo json_encode([
    'success' => true,
    'data' => [
        'unread_count' => $unreadCount['count'] ?? 0,
        'notifications' => $notifications
    ]
]);





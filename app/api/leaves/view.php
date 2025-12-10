<?php
/**
 * View Leave Details API
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
$leaveId = $_GET['id'] ?? 0;
$db = Database::getInstance();

$leave = $db->fetchOne(
    "SELECT l.*, u.full_name as approved_by_name 
    FROM leaves l 
    LEFT JOIN users u ON l.approved_by = u.id 
    WHERE l.id = ? AND l.company_id = ?",
    [$leaveId, $companyId]
);

if (!$leave) {
    echo json_encode(['success' => false, 'message' => 'Leave not found']);
    exit;
}

// Check if user can view (own leave or manager)
$canView = ($leave['user_id'] == $userId || Auth::hasRole(['company_owner', 'manager']));

if (!$canView) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

echo json_encode(['success' => true, 'data' => $leave]);





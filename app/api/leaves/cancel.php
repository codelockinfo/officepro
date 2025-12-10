<?php
/**
 * Cancel Leave Request API
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
$leaveId = $_GET['id'] ?? 0;
$db = Database::getInstance();

// Get leave
$leave = $db->fetchOne(
    "SELECT * FROM leaves WHERE id = ? AND company_id = ? AND user_id = ?",
    [$leaveId, $companyId, $userId]
);

if (!$leave) {
    echo json_encode(['success' => false, 'message' => 'Leave not found']);
    exit;
}

if ($leave['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'Can only cancel pending leave requests']);
    exit;
}

try {
    $db->execute(
        "DELETE FROM leaves WHERE id = ? AND company_id = ?",
        [$leaveId, $companyId]
    );
    
    echo json_encode(['success' => true, 'message' => 'Leave request cancelled']);
} catch (Exception $e) {
    error_log("Cancel Leave Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to cancel leave']);
}





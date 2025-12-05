<?php
/**
 * Company Details API (System Admin)
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

$companyId = $_GET['id'] ?? 0;
$db = Database::getInstance();

// Get company details
$company = $db->fetchOne(
    "SELECT c.*, u.full_name as owner_name, u.email as owner_email,
    (SELECT COUNT(*) FROM users WHERE company_id = c.id AND status = 'active') as employee_count
    FROM companies c 
    LEFT JOIN users u ON c.owner_id = u.id 
    WHERE c.id = ?",
    [$companyId]
);

if (!$company) {
    echo json_encode(['success' => false, 'message' => 'Company not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => $company
]);


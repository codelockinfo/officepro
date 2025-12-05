<?php
/**
 * Company Employees API (for dropdowns and listings)
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Tenant.php';

// Check authentication
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$companyId = Tenant::getCurrentCompanyId();
$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        // Get all employees in the company
        $employees = $db->fetchAll(
            "SELECT id, full_name, email, role, department_id, status, profile_image 
            FROM users 
            WHERE company_id = ? AND status = 'active'
            ORDER BY full_name ASC",
            [$companyId]
        );
        
        echo json_encode(['success' => true, 'data' => $employees]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}



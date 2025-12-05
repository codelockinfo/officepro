<?php
/**
 * Holiday Management API
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Tenant.php';
require_once __DIR__ . '/../../helpers/Validator.php';

// Check authentication and authorization
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

Auth::requireRole(['company_owner']);

$companyId = Tenant::getCurrentCompanyId();
$userId = Auth::getCurrentUser()['id'];
$db = Database::getInstance();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $validator = new Validator();
        
        $name = $validator->sanitize($input['name'] ?? '');
        $date = $input['date'] ?? '';
        $recurring = isset($input['recurring']) && $input['recurring'] ? 1 : 0;
        
        $validator->required($name, 'Holiday Name');
        $validator->required($date, 'Date');
        $validator->date($date, 'Date');
        
        if ($validator->hasErrors()) {
            echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->getErrors()]);
            exit;
        }
        
        try {
            $db->execute(
                "INSERT INTO holidays (company_id, name, date, recurring, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())",
                [$companyId, $name, $date, $recurring, $userId]
            );
            
            echo json_encode(['success' => true, 'message' => 'Holiday created', 'id' => $db->lastInsertId()]);
        } catch (Exception $e) {
            error_log("Create Holiday Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to create holiday']);
        }
        break;
        
    case 'delete':
        $id = $_GET['id'] ?? 0;
        
        try {
            $db->execute(
                "DELETE FROM holidays WHERE id = ? AND company_id = ?",
                [$id, $companyId]
            );
            
            echo json_encode(['success' => true, 'message' => 'Holiday deleted']);
        } catch (Exception $e) {
            error_log("Delete Holiday Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to delete holiday']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}




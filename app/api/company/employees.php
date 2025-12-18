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
$userId = Auth::getCurrentUser()['id'];
$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        // Get all employees in the company (exclude company owners)
        $employees = $db->fetchAll(
            "SELECT id, full_name, email, role, department_id, status, profile_image 
            FROM users 
            WHERE company_id = ? AND status = 'active' AND role != 'company_owner'
            ORDER BY full_name ASC",
            [$companyId]
        );
        
        echo json_encode(['success' => true, 'data' => $employees]);
        break;
        
    case 'update':
        // Only company owners can update employees
        Auth::requireRole(['company_owner']);
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }
        
        $employeeId = $_GET['id'] ?? 0;
        
        // Handle both JSON and FormData
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
        } else {
            $input = $_POST;
        }
        
        // Check if employee exists and belongs to company
        $employee = $db->fetchOne(
            "SELECT id FROM users WHERE id = ? AND company_id = ?",
            [$employeeId, $companyId]
        );
        
        if (!$employee) {
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            exit;
        }
        
        // Prevent editing company owner
        $employeeRole = $db->fetchOne(
            "SELECT role FROM users WHERE id = ?",
            [$employeeId]
        );
        
        if ($employeeRole['role'] === 'company_owner' && $employeeId != $userId) {
            echo json_encode(['success' => false, 'message' => 'Cannot edit company owner']);
            exit;
        }
        
        require_once __DIR__ . '/../../helpers/Validator.php';
        $validator = new Validator();
        
        $fullName = $validator->sanitize($input['full_name'] ?? '');
        $email = $validator->sanitize($input['email'] ?? '');
        $joinDate = $input['join_date'] ?? '';
        $status = $input['status'] ?? 'active';
        
        // Validate status
        if (!in_array($status, ['active', 'pending', 'suspended'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address']);
            exit;
        }
        
        // Validate join date
        if (empty($joinDate)) {
            echo json_encode(['success' => false, 'message' => 'Join date is required']);
            exit;
        }
        
        // Validate date format and convert to datetime
        $joinDateTime = null;
        if ($joinDate) {
            $dateParts = explode('-', $joinDate);
            if (count($dateParts) === 3 && checkdate($dateParts[1], $dateParts[2], $dateParts[0])) {
                $joinDateTime = $joinDate . ' 00:00:00';
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid join date format']);
                exit;
            }
        }
        
        // Check if email already exists for another user in the same company
        $existingEmail = $db->fetchOne(
            "SELECT id FROM users WHERE email = ? AND company_id = ? AND id != ?",
            [$email, $companyId, $employeeId]
        );
        
        if ($existingEmail) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit;
        }
        
        $validator->required($fullName, 'Full Name');
        $validator->required($email, 'Email');
        
        if ($validator->hasErrors()) {
            $errors = $validator->getErrors();
            $errorMessage = 'Validation failed: ' . implode(', ', array_values($errors));
            echo json_encode(['success' => false, 'message' => $errorMessage, 'errors' => $errors]);
            exit;
        }
        
        try {
            $db->execute(
                "UPDATE users SET full_name = ?, email = ?, status = ?, created_at = ?, updated_at = NOW() 
                WHERE id = ? AND company_id = ?",
                [$fullName, $email, $status, $joinDateTime, $employeeId, $companyId]
            );
            
            echo json_encode(['success' => true, 'message' => 'Employee updated successfully']);
        } catch (Exception $e) {
            error_log("Update Employee Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to update employee: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}





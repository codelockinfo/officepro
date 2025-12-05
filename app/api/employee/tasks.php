<?php
/**
 * Task Management API
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Tenant.php';
require_once __DIR__ . '/../../helpers/Validator.php';

// Check authentication
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

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
        
        $title = $validator->sanitize($input['title'] ?? '');
        $description = $validator->sanitize($input['description'] ?? '');
        $assignedTo = $input['assigned_to'] ?? 0;
        $dueDate = $input['due_date'] ?? null;
        $priority = $input['priority'] ?? 'medium';
        $status = $input['status'] ?? 'todo';
        
        $validator->required($title, 'Title');
        $validator->required($assignedTo, 'Assigned To');
        
        if ($validator->hasErrors()) {
            echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->getErrors()]);
            exit;
        }
        
        // Validate assigned_to belongs to company
        $validUser = $db->fetchOne(
            "SELECT id FROM users WHERE id = ? AND company_id = ?",
            [$assignedTo, $companyId]
        );
        
        if (!$validUser) {
            echo json_encode(['success' => false, 'message' => 'Invalid user selected']);
            exit;
        }
        
        try {
            $db->execute(
                "INSERT INTO tasks (company_id, created_by, assigned_to, title, description, due_date, priority, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$companyId, $userId, $assignedTo, $title, $description, $dueDate, $priority, $status]
            );
            
            $taskId = $db->lastInsertId();
            
            // Create notification if assigning to someone else
            if ($assignedTo != $userId) {
                $currentUser = Auth::getCurrentUser();
                $db->execute(
                    "INSERT INTO notifications (company_id, user_id, type, message, link, created_at) 
                    VALUES (?, ?, 'task_assigned', ?, '/officepro/app/views/employee/tasks.php', NOW())",
                    [$companyId, $assignedTo, "{$currentUser['full_name']} assigned you a task: {$title}"]
                );
            }
            
            echo json_encode(['success' => true, 'message' => 'Task created', 'id' => $taskId]);
        } catch (Exception $e) {
            error_log("Create Task Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to create task']);
        }
        break;
        
    case 'update_status':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }
        
        $id = $_GET['id'] ?? 0;
        $input = json_decode(file_get_contents('php://input'), true);
        $status = $input['status'] ?? '';
        
        if (!in_array($status, ['todo', 'in_progress', 'done'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }
        
        try {
            $completedAt = $status === 'done' ? 'NOW()' : 'NULL';
            
            $db->execute(
                "UPDATE tasks SET status = ?, completed_at = $completedAt, updated_at = NOW() 
                WHERE id = ? AND company_id = ?",
                [$status, $id, $companyId]
            );
            
            echo json_encode(['success' => true, 'message' => 'Task status updated']);
        } catch (Exception $e) {
            error_log("Update Task Status Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to update task']);
        }
        break;
        
    case 'delete':
        $id = $_GET['id'] ?? 0;
        
        // Check if user created the task or is admin
        $task = $db->fetchOne(
            "SELECT created_by FROM tasks WHERE id = ? AND company_id = ?",
            [$id, $companyId]
        );
        
        if (!$task || ($task['created_by'] != $userId && !Auth::canManageCompany())) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        try {
            $db->execute(
                "DELETE FROM tasks WHERE id = ? AND company_id = ?",
                [$id, $companyId]
            );
            
            echo json_encode(['success' => true, 'message' => 'Task deleted']);
        } catch (Exception $e) {
            error_log("Delete Task Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to delete task']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}


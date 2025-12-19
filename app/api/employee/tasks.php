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
        
        // Handle both JSON and FormData
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
        } else {
            $input = $_POST;
        }
        
        $validator = new Validator();
        
        $title = $validator->sanitize($input['title'] ?? '');
        $description = $validator->sanitize($input['description'] ?? '');
        $assignedTo = !empty($input['assigned_to']) ? (int)$input['assigned_to'] : 0;
        $dueDate = !empty($input['due_date']) ? $input['due_date'] : null;
        $startTime = !empty($input['start_time']) ? $input['start_time'] : null;
        $endTime = !empty($input['end_time']) ? $input['end_time'] : null;
        $priority = $input['priority'] ?? 'medium';
        $status = $input['status'] ?? 'todo';
        
        $validator->required($title, 'Title');
        $validator->required($assignedTo, 'Assigned To');
        
        if ($validator->hasErrors()) {
            $errors = $validator->getErrors();
            $errorMessage = 'Validation failed: ' . implode(', ', array_values($errors));
            echo json_encode(['success' => false, 'message' => $errorMessage, 'errors' => $errors]);
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
            error_log("Create Task Error Trace: " . $e->getTraceAsString());
            echo json_encode(['success' => false, 'message' => 'Failed to create task: ' . $e->getMessage()]);
        }
        break;
        
    case 'update_status':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }
        
        $id = $_GET['id'] ?? 0;
        
        // Handle both JSON and FormData
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
        } else {
            $input = $_POST;
        }
        
        $status = $input['status'] ?? '';
        
        if (!in_array($status, ['todo', 'in_progress', 'done'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }
        
        // Check if task exists and user can update (must be assigned to them or be the creator/owner)
        $task = $db->fetchOne(
            "SELECT t.assigned_to, t.created_by, t.status, t.title, u.role as user_role 
             FROM tasks t 
             JOIN users u ON t.assigned_to = u.id 
             WHERE t.id = ? AND t.company_id = ?",
            [$id, $companyId]
        );
        
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Task not found']);
            exit;
        }
        
        // Employee can only update tasks assigned to them
        // Owner/Manager can update any task
        if ($task['assigned_to'] != $userId && !Auth::canManageCompany()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        // Get current user info
        $currentUser = Auth::getCurrentUser();
        $isEmployee = $currentUser['role'] === 'employee';
        
        try {
            $wasCompleted = $task['status'] === 'done';
            $isNowCompleted = $status === 'done';
            
            if ($isNowCompleted && !$wasCompleted) {
                $db->execute(
                    "UPDATE tasks SET status = ?, completed_at = NOW(), updated_at = NOW() 
                    WHERE id = ? AND company_id = ?",
                    [$status, $id, $companyId]
                );
            } elseif (!$isNowCompleted && $wasCompleted) {
                $db->execute(
                    "UPDATE tasks SET status = ?, completed_at = NULL, updated_at = NOW() 
                    WHERE id = ? AND company_id = ?",
                    [$status, $id, $companyId]
                );
            } else {
                $db->execute(
                    "UPDATE tasks SET status = ?, updated_at = NOW() 
                    WHERE id = ? AND company_id = ?",
                    [$status, $id, $companyId]
                );
            }
            
            // Notify company owner if employee updated the task status
            if ($isEmployee && $task['status'] != $status) {
                // Get company owner ID
                $company = $db->fetchOne(
                    "SELECT owner_id FROM companies WHERE id = ?",
                    [$companyId]
                );
                
                if ($company && $company['owner_id']) {
                    $ownerId = $company['owner_id'];
                    $statusLabels = [
                        'todo' => 'To Do',
                        'in_progress' => 'In Progress',
                        'done' => 'Done'
                    ];
                    $statusLabel = $statusLabels[$status] ?? ucfirst($status);
                    
                    $message = "{$currentUser['full_name']} updated task '{$task['title']}' status to {$statusLabel}";
                    
                    $db->execute(
                        "INSERT INTO notifications (company_id, user_id, type, message, link, created_at) 
                        VALUES (?, ?, 'task_status', ?, '/officepro/app/views/company/tasks.php', NOW())",
                        [$companyId, $ownerId, $message]
                    );
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Task status updated']);
        } catch (Exception $e) {
            error_log("Update Task Status Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to update task']);
        }
        break;
        
    case 'get':
        $id = $_GET['id'] ?? 0;
        
        try {
            $task = $db->fetchOne(
                "SELECT t.*, 
                 creator.full_name as created_by_name,
                 assignee.full_name as assigned_to_name
                 FROM tasks t 
                 LEFT JOIN users creator ON t.created_by = creator.id
                 LEFT JOIN users assignee ON t.assigned_to = assignee.id
                 WHERE t.id = ? AND t.company_id = ?",
                [$id, $companyId]
            );
            
            if (!$task) {
                echo json_encode(['success' => false, 'message' => 'Task not found']);
                exit;
            }
            
            // Check if user can view this task (created by them, assigned to them, or is company owner/manager)
            $canView = ($task['created_by'] == $userId) || 
                      ($task['assigned_to'] == $userId) || 
                      Auth::canManageCompany();
            
            if (!$canView) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }
            
            echo json_encode(['success' => true, 'data' => $task]);
        } catch (Exception $e) {
            error_log("Get Task Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to fetch task']);
        }
        break;
        
    case 'update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }
        
        $id = $_GET['id'] ?? 0;
        
        // Handle both JSON and FormData
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
        } else {
            $input = $_POST;
        }
        
        $validator = new Validator();
        
        // Check if task exists and user can edit
        $task = $db->fetchOne(
            "SELECT created_by, status FROM tasks WHERE id = ? AND company_id = ?",
            [$id, $companyId]
        );
        
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Task not found']);
            exit;
        }
        
        // Only creator or company owner/manager can edit
        if ($task['created_by'] != $userId && !Auth::canManageCompany()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        $title = $validator->sanitize($input['title'] ?? '');
        $description = $validator->sanitize($input['description'] ?? '');
        $assignedTo = !empty($input['assigned_to']) ? (int)$input['assigned_to'] : 0;
        $dueDate = !empty($input['due_date']) ? $input['due_date'] : null;
        $priority = $input['priority'] ?? 'medium';
        $status = $input['status'] ?? 'todo';
        
        $validator->required($title, 'Title');
        $validator->required($assignedTo, 'Assigned To');
        
        if ($validator->hasErrors()) {
            $errors = $validator->getErrors();
            $errorMessage = 'Validation failed: ' . implode(', ', array_values($errors));
            echo json_encode(['success' => false, 'message' => $errorMessage, 'errors' => $errors]);
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
            $wasCompleted = $task['status'] === 'done';
            $isNowCompleted = $status === 'done';
            
            if ($isNowCompleted && !$wasCompleted) {
                $db->execute(
                    "UPDATE tasks SET 
                        title = ?, 
                        description = ?, 
                        assigned_to = ?, 
                        due_date = ?,
                        priority = ?, 
                        status = ?,
                        completed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ? AND company_id = ?",
                    [$title, $description, $assignedTo, $dueDate, $priority, $status, $id, $companyId]
                );
            } elseif (!$isNowCompleted && $wasCompleted) {
                $db->execute(
                    "UPDATE tasks SET 
                        title = ?, 
                        description = ?, 
                        assigned_to = ?, 
                        due_date = ?,
                        priority = ?, 
                        status = ?,
                        completed_at = NULL,
                        updated_at = NOW()
                    WHERE id = ? AND company_id = ?",
                    [$title, $description, $assignedTo, $dueDate, $priority, $status, $id, $companyId]
                );
            } else {
                $db->execute(
                    "UPDATE tasks SET 
                        title = ?, 
                        description = ?, 
                        assigned_to = ?, 
                        due_date = ?,
                        priority = ?, 
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ? AND company_id = ?",
                    [$title, $description, $assignedTo, $dueDate, $priority, $status, $id, $companyId]
                );
            }
            
            // Create notification if assigned to changed
            $oldTask = $db->fetchOne("SELECT assigned_to FROM tasks WHERE id = ?", [$id]);
            if ($oldTask && $assignedTo != $oldTask['assigned_to']) {
                $currentUser = Auth::getCurrentUser();
                $db->execute(
                    "INSERT INTO notifications (company_id, user_id, type, message, link, created_at) 
                    VALUES (?, ?, 'task_assigned', ?, '/officepro/app/views/employee/tasks.php', NOW())",
                    [$companyId, $assignedTo, "{$currentUser['full_name']} assigned you a task: {$title}"]
                );
            }
            
            echo json_encode(['success' => true, 'message' => 'Task updated']);
        } catch (Exception $e) {
            error_log("Update Task Error: " . $e->getMessage());
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



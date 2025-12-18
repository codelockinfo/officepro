<?php
/**
 * Leave Approval/Decline API
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Tenant.php';
require_once __DIR__ . '/../../helpers/Validator.php';
require_once __DIR__ . '/../../helpers/Email.php';

// Check authentication and authorization
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

Auth::requireRole(['company_owner', 'manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$companyId = Tenant::getCurrentCompanyId();
$approverId = Auth::getCurrentUser()['id'];
$input = json_decode(file_get_contents('php://input'), true);
$db = Database::getInstance();
$validator = new Validator();

// Set timezone from config
$appConfig = require __DIR__ . '/../../config/app.php';
date_default_timezone_set($appConfig['timezone']);

$leaveId = $input['leave_id'] ?? 0;
$action = $input['action'] ?? '';
$comments = $validator->sanitize($input['comments'] ?? '');

// Validate action
if (!in_array($action, ['approve', 'decline'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Get leave details
$leave = $db->fetchOne(
    "SELECT l.*, u.full_name as employee_name, u.email as employee_email 
    FROM leaves l 
    JOIN users u ON l.user_id = u.id 
    WHERE l.id = ? AND l.company_id = ?",
    [$leaveId, $companyId]
);

if (!$leave) {
    echo json_encode(['success' => false, 'message' => 'Leave not found']);
    exit;
}

if ($leave['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'Leave request already processed']);
    exit;
}

try {
    $db->beginTransaction();
    
    $status = $action === 'approve' ? 'approved' : 'declined';
    
    // Update leave status
    $db->execute(
        "UPDATE leaves SET status = ?, approved_by = ?, comments = ?, updated_at = NOW() 
        WHERE id = ? AND company_id = ?",
        [$status, $approverId, $comments, $leaveId, $companyId]
    );
    
    // If approved, deduct from leave balance
    if ($action === 'approve') {
        // Deduct from paid leave balance
        if ($leave['leave_type'] === 'paid_leave') {
            $currentYear = date('Y');
            $db->execute(
                "UPDATE leave_balances 
                SET paid_leave = paid_leave - ? 
                WHERE company_id = ? AND user_id = ? AND year = ?",
                [$leave['days_count'], $companyId, $leave['user_id'], $currentYear]
            );
        }
    }
    
    // Create notification for employee
    $message = $action === 'approve' 
        ? "Your leave request has been approved" 
        : "Your leave request has been declined";
    
    if ($comments) {
        $message .= ": " . $comments;
    }
    
    $db->execute(
        "INSERT INTO notifications (company_id, user_id, type, message, link, created_at) 
        VALUES (?, ?, 'leave_status', ?, '/app/views/leaves.php', NOW())",
        [$companyId, $leave['user_id'], $message]
    );
    
    $db->commit();
    
    // Send email notification
    $leaveTypeLabels = [
        'paid_leave' => 'Paid Leave'
    ];
    
    Email::sendLeaveStatusUpdate(
        $leave['employee_email'],
        $leave['employee_name'],
        $leaveTypeLabels[$leave['leave_type']] ?? $leave['leave_type'],
        $status,
        $comments
    );
    
    echo json_encode([
        'success' => true,
        'message' => ucfirst($action) . 'd successfully'
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Leave Approval Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to process approval']);
}





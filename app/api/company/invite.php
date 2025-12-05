<?php
/**
 * Send Employee Invitation API
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Tenant.php';
require_once __DIR__ . '/../../helpers/Validator.php';
require_once __DIR__ . '/../../helpers/Invitation.php';
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
$userId = Auth::getCurrentUser()['id'];
$input = json_decode(file_get_contents('php://input'), true);

$validator = new Validator();

$email = $validator->sanitize($input['email'] ?? '');
$role = $input['role'] ?? 'employee';
$message = $validator->sanitize($input['message'] ?? '');

// Validate inputs
$validator->required($email, 'Email');
$validator->email($email, 'Email');

if (!in_array($role, ['employee', 'manager'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit;
}

if ($validator->hasErrors()) {
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->getErrors()]);
    exit;
}

$db = Database::getInstance();

// Check if user already exists
$existingUser = $db->fetchOne(
    "SELECT id FROM users WHERE email = ? AND company_id = ?",
    [$email, $companyId]
);

if ($existingUser) {
    echo json_encode(['success' => false, 'message' => 'User with this email already exists in your company']);
    exit;
}

// Check if there's already a pending invitation
$existingInvitation = $db->fetchOne(
    "SELECT id FROM invitations WHERE email = ? AND company_id = ? AND status = 'pending'",
    [$email, $companyId]
);

if ($existingInvitation) {
    echo json_encode(['success' => false, 'message' => 'A pending invitation already exists for this email']);
    exit;
}

// Create invitation
$result = Invitation::createInvitation($companyId, $email, $role, $userId);

if ($result['success']) {
    // Get company name for email
    $company = $db->fetchOne("SELECT company_name FROM companies WHERE id = ?", [$companyId]);
    $companyName = $company['company_name'] ?? 'Your Company';
    
    $currentUser = Auth::getCurrentUser();
    $inviterName = $currentUser['full_name'];
    
    // Send invitation email
    Email::sendEmployeeInvitation($email, $result['token'], $companyName, $inviterName);
    
    echo json_encode([
        'success' => true,
        'message' => 'Invitation sent successfully',
        'data' => [
            'invitation_id' => $result['invitation_id'],
            'token' => $result['token'],
            'expires_at' => $result['expires_at']
        ]
    ]);
} else {
    echo json_encode($result);
}




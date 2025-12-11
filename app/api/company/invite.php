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

// Check if user already exists and is active (not pending)
$existingUser = $db->fetchOne(
    "SELECT id, status FROM users WHERE email = ? AND company_id = ?",
    [$email, $companyId]
);

if ($existingUser && $existingUser['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'User with this email already exists in your company']);
    exit;
}

// If user exists with pending status, we'll update the invitation instead of creating new user

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

// Log invitation creation for debugging
error_log("Invitation creation attempt - Company ID: {$companyId}, Email: {$email}, Role: {$role}, User ID: {$userId}");
error_log("Invitation creation result: " . json_encode($result));

// Verify invitation and user were stored in database
if ($result['success']) {
    $db = Database::getInstance();
    
    // Verify invitation
    $verifyInvitation = $db->fetchOne(
        "SELECT id, email, role, token, status FROM invitations WHERE id = ?",
        [$result['invitation_id']]
    );
    
    if ($verifyInvitation) {
        error_log("Invitation verified in database - ID: {$verifyInvitation['id']}, Email: {$verifyInvitation['email']}, Role: {$verifyInvitation['role']}, Status: {$verifyInvitation['status']}");
    } else {
        error_log("ERROR: Invitation was not found in database after creation!");
    }
    
    // Verify user was created
    if (isset($result['user_id'])) {
        $verifyUser = $db->fetchOne(
            "SELECT id, email, role, status FROM users WHERE id = ?",
            [$result['user_id']]
        );
        
        if ($verifyUser) {
            error_log("User verified in database - ID: {$verifyUser['id']}, Email: {$verifyUser['email']}, Role: {$verifyUser['role']}, Status: {$verifyUser['status']}");
        } else {
            error_log("ERROR: User was not found in database after creation!");
        }
    }
}

if ($result['success']) {
    // Get company name for email
    $company = $db->fetchOne("SELECT company_name FROM companies WHERE id = ?", [$companyId]);
    $companyName = $company['company_name'] ?? 'Your Company';
    
    $currentUser = Auth::getCurrentUser();
    $inviterName = $currentUser['full_name'];
    
    // Send invitation email with personal message (non-blocking)
    // Use output buffering to prevent hanging
    try {
        $emailSent = @Email::sendEmployeeInvitation($email, $result['token'], $companyName, $inviterName, $message);
        
        if (!$emailSent) {
            // Log error but don't fail the invitation creation
            error_log("Failed to send invitation email to: {$email}");
        }
    } catch (Exception $e) {
        // Log exception but continue
        error_log("Email sending exception: " . $e->getMessage());
    }
    
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





<?php
/**
 * Manage Invitations API (resend, cancel)
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Tenant.php';
require_once __DIR__ . '/../../helpers/Invitation.php';
require_once __DIR__ . '/../../helpers/Email.php';

// Check authentication and authorization
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

Auth::requireRole(['company_owner', 'manager']);

$companyId = Tenant::getCurrentCompanyId();
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

switch ($action) {
    case 'resend':
        $result = Invitation::resendInvitation($id);
        
        if ($result['success']) {
            // Get invitation details for email
            $db = Database::getInstance();
            $invitation = $db->fetchOne(
                "SELECT email, company_id FROM invitations WHERE id = ?",
                [$id]
            );
            
            if ($invitation && $invitation['company_id'] == $companyId) {
                $company = $db->fetchOne("SELECT company_name FROM companies WHERE id = ?", [$companyId]);
                $companyName = $company['company_name'] ?? 'Your Company';
                
                $currentUser = Auth::getCurrentUser();
                $inviterName = $currentUser['full_name'];
                
                // Send email with new token
                Email::sendEmployeeInvitation($invitation['email'], $result['token'], $companyName, $inviterName);
            }
        }
        
        echo json_encode($result);
        break;
        
    case 'cancel':
        $result = Invitation::cancelInvitation($id, $companyId);
        
        if ($result > 0) {
            echo json_encode(['success' => true, 'message' => 'Invitation cancelled']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to cancel invitation']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}





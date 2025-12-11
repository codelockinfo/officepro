<?php
/**
 * Test Invitation Creation
 */

require_once __DIR__ . '/app/config/init.php';
require_once __DIR__ . '/app/helpers/Database.php';
require_once __DIR__ . '/app/helpers/Invitation.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyId = intval($_POST['company_id'] ?? 0);
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? 'employee';
    
    if ($companyId && $email) {
        $result = Invitation::createInvitation($companyId, $email, $role, 1);
        
        echo "<h2>Result:</h2>";
        echo "<pre>" . print_r($result, true) . "</pre>";
        
        if ($result['success']) {
            // Verify in database
            $db = Database::getInstance();
            $invitation = $db->fetchOne(
                "SELECT * FROM invitations WHERE id = ?",
                [$result['invitation_id']]
            );
            
            echo "<h2>Verification:</h2>";
            echo "<pre>" . print_r($invitation, true) . "</pre>";
        }
    } else {
        echo "Missing required fields";
    }
    
    echo "<br><a href='check_invitation.php'>Back to Invitations Check</a>";
} else {
    header('Location: check_invitation.php');
}


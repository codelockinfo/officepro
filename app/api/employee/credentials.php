<?php
/**
 * Credentials Management API
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
$isAdmin = Auth::canManageCompany();
$db = Database::getInstance();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        // Get all credentials user can see (own + shared with them + admin can see all)
        if ($isAdmin) {
            $credentials = $db->fetchAll(
                "SELECT id, user_id, website_name, website_url, username, is_shared, created_at 
                FROM saved_credentials 
                WHERE company_id = ? 
                ORDER BY website_name ASC",
                [$companyId]
            );
        } else {
            $credentials = $db->fetchAll(
                "SELECT id, user_id, website_name, website_url, username, is_shared, created_at 
                FROM saved_credentials 
                WHERE company_id = ? AND (user_id = ? OR JSON_CONTAINS(shared_with, ?))
                ORDER BY website_name ASC",
                [$companyId, $userId, json_encode($userId)]
            );
        }
        
        // Add can_edit flag
        foreach ($credentials as &$cred) {
            $cred['can_edit'] = ($cred['user_id'] == $userId || $isAdmin);
        }
        
        echo json_encode(['success' => true, 'data' => $credentials]);
        break;
        
    case 'view':
        $id = $_GET['id'] ?? 0;
        
        // Get credential with password
        $credential = $db->fetchOne(
            "SELECT * FROM saved_credentials 
            WHERE id = ? AND company_id = ?",
            [$id, $companyId]
        );
        
        if (!$credential) {
            echo json_encode(['success' => false, 'message' => 'Credential not found']);
            exit;
        }
        
        // Check access: owner, shared user, or admin
        $sharedWith = json_decode($credential['shared_with'] ?? '[]', true);
        $hasAccess = ($credential['user_id'] == $userId || in_array($userId, $sharedWith) || $isAdmin);
        
        if (!$hasAccess) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        // Parse shared_with JSON
        $credential['shared_with'] = $sharedWith;
        
        // Log access in audit_log
        $db->execute(
            "INSERT INTO audit_log (company_id, admin_id, action, target_table, target_id, details, created_at) 
            VALUES (?, ?, 'view_credential', 'saved_credentials', ?, ?, NOW())",
            [$companyId, $userId, $id, json_encode(['website' => $credential['website_name']])]
        );
        
        echo json_encode(['success' => true, 'data' => $credential]);
        break;
        
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $validator = new Validator();
        
        $websiteName = $validator->sanitize($input['website_name'] ?? '');
        $websiteUrl = $validator->sanitize($input['website_url'] ?? '');
        $username = $validator->sanitize($input['username'] ?? '');
        $password = $input['password'] ?? '';
        $notes = $validator->sanitize($input['notes'] ?? '');
        
        $validator->required($websiteName, 'Website Name');
        
        if ($validator->hasErrors()) {
            echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->getErrors()]);
            exit;
        }
        
        try {
            $db->execute(
                "INSERT INTO saved_credentials (company_id, user_id, website_name, website_url, username, password, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                [$companyId, $userId, $websiteName, $websiteUrl, $username, $password, $notes]
            );
            
            echo json_encode(['success' => true, 'message' => 'Credential created', 'id' => $db->lastInsertId()]);
        } catch (Exception $e) {
            error_log("Create Credential Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to create credential']);
        }
        break;
        
    case 'update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }
        
        $id = $_GET['id'] ?? 0;
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Check ownership
        $existing = $db->fetchOne(
            "SELECT user_id FROM saved_credentials WHERE id = ? AND company_id = ?",
            [$id, $companyId]
        );
        
        if (!$existing || ($existing['user_id'] != $userId && !$isAdmin)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        $validator = new Validator();
        $websiteName = $validator->sanitize($input['website_name'] ?? '');
        $websiteUrl = $validator->sanitize($input['website_url'] ?? '');
        $username = $validator->sanitize($input['username'] ?? '');
        $password = $input['password'] ?? '';
        $notes = $validator->sanitize($input['notes'] ?? '');
        
        $validator->required($websiteName, 'Website Name');
        
        if ($validator->hasErrors()) {
            echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->getErrors()]);
            exit;
        }
        
        try {
            $db->execute(
                "UPDATE saved_credentials SET website_name = ?, website_url = ?, username = ?, password = ?, notes = ?, updated_at = NOW() 
                WHERE id = ? AND company_id = ?",
                [$websiteName, $websiteUrl, $username, $password, $notes, $id, $companyId]
            );
            
            echo json_encode(['success' => true, 'message' => 'Credential updated']);
        } catch (Exception $e) {
            error_log("Update Credential Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to update credential']);
        }
        break;
        
    case 'delete':
        $id = $_GET['id'] ?? 0;
        
        // Check ownership
        $existing = $db->fetchOne(
            "SELECT user_id FROM saved_credentials WHERE id = ? AND company_id = ?",
            [$id, $companyId]
        );
        
        if (!$existing || ($existing['user_id'] != $userId && !$isAdmin)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        try {
            $db->execute(
                "DELETE FROM saved_credentials WHERE id = ? AND company_id = ?",
                [$id, $companyId]
            );
            
            echo json_encode(['success' => true, 'message' => 'Credential deleted']);
        } catch (Exception $e) {
            error_log("Delete Credential Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to delete credential']);
        }
        break;
        
    case 'share':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? 0;
        $sharedWith = $input['shared_with'] ?? [];
        
        // Check ownership
        $existing = $db->fetchOne(
            "SELECT user_id FROM saved_credentials WHERE id = ? AND company_id = ?",
            [$id, $companyId]
        );
        
        if (!$existing || ($existing['user_id'] != $userId && !$isAdmin)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        // Validate that all user IDs belong to the company
        if (count($sharedWith) > 0) {
            $placeholders = implode(',', array_fill(0, count($sharedWith), '?'));
            $validUsers = $db->fetchAll(
                "SELECT id FROM users WHERE company_id = ? AND id IN ($placeholders)",
                array_merge([$companyId], $sharedWith)
            );
            
            if (count($validUsers) !== count($sharedWith)) {
                echo json_encode(['success' => false, 'message' => 'Invalid user IDs']);
                exit;
            }
        }
        
        try {
            $isShared = count($sharedWith) > 0 ? 1 : 0;
            $sharedWithJson = json_encode($sharedWith);
            
            $db->execute(
                "UPDATE saved_credentials SET is_shared = ?, shared_with = ?, updated_at = NOW() 
                WHERE id = ? AND company_id = ?",
                [$isShared, $sharedWithJson, $id, $companyId]
            );
            
            echo json_encode(['success' => true, 'message' => 'Sharing updated']);
        } catch (Exception $e) {
            error_log("Update Sharing Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to update sharing']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}




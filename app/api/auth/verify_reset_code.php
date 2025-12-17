<?php
/**
 * Verify Reset Code API - Step 2: Verify Reset Code
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Validator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON data from request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Fallback to $_POST if JSON parsing fails (for form data)
if (json_last_error() !== JSON_ERROR_NONE) {
    $data = $_POST;
}

$validator = new Validator();
$email = $validator->sanitize($data['email'] ?? '');
$resetCode = $validator->sanitize($data['reset_code'] ?? '');

// Validate inputs
if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

if (empty($resetCode)) {
    echo json_encode(['success' => false, 'message' => 'Reset code is required']);
    exit;
}

// Validate reset code format (6 digits)
if (!preg_match('/^\d{6}$/', $resetCode)) {
    echo json_encode(['success' => false, 'message' => 'Invalid reset code format']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Find valid reset code
    $reset = $db->fetchOne(
        "SELECT id, email, reset_code, expires_at, used 
         FROM password_resets 
         WHERE email = ? AND reset_code = ? AND used = FALSE 
         ORDER BY created_at DESC 
         LIMIT 1",
        [$email, $resetCode]
    );
    
    if (!$reset) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset code']);
        exit;
    }
    
    // Check if code has expired
    $now = date('Y-m-d H:i:s');
    if ($reset['expires_at'] < $now) {
        echo json_encode(['success' => false, 'message' => 'Reset code has expired. Please request a new one.']);
        exit;
    }
    
    // Mark code as used
    $db->execute(
        "UPDATE password_resets SET used = TRUE WHERE id = ?",
        [$reset['id']]
    );
    
    // Store email in session for password reset step
    $_SESSION['reset_email'] = $email;
    $_SESSION['reset_code_verified'] = true;
    
    error_log("Reset code verified for: {$email}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Reset code verified successfully. You can now set a new password.'
    ]);
    
} catch (Exception $e) {
    error_log("Verify Reset Code Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}


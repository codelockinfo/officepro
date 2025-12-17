<?php
/**
 * Reset Password API - Step 3: Update Password
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Validator.php';
require_once __DIR__ . '/../../helpers/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if reset code was verified
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_code_verified']) || $_SESSION['reset_code_verified'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Please verify your reset code first']);
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
$email = $_SESSION['reset_email'];
$newPassword = $data['new_password'] ?? '';
$confirmPassword = $data['confirm_password'] ?? '';

// Validate passwords
if (empty($newPassword)) {
    echo json_encode(['success' => false, 'message' => 'New password is required']);
    exit;
}

if (empty($confirmPassword)) {
    echo json_encode(['success' => false, 'message' => 'Please confirm your password']);
    exit;
}

$validator->required($newPassword, 'New Password');
$validator->minLength($newPassword, 8, 'New Password');

if ($validator->hasErrors()) {
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->getErrors()]);
    exit;
}

// Check if passwords match
if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Verify user exists
    $user = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $db->execute(
        "UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?",
        [$hashedPassword, $email]
    );
    
    // Clear reset session
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_code_verified']);
    
    // Invalidate all reset codes for this email
    $db->execute(
        "UPDATE password_resets SET used = TRUE WHERE email = ?",
        [$email]
    );
    
    error_log("Password reset successful for: {$email}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Password has been reset successfully. You can now login with your new password.'
    ]);
    
} catch (Exception $e) {
    error_log("Reset Password Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to reset password. Please try again.']);
}


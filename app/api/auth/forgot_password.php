<?php
/**
 * Forgot Password API - Step 1: Send Reset Code
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Validator.php';
require_once __DIR__ . '/../../helpers/Email.php';

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

// Validate email
if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

if (!$validator->email($email, 'Email')) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format', 'errors' => $validator->getErrors()]);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Check if user exists
    $user = $db->fetchOne("SELECT id, email, full_name FROM users WHERE email = ? LIMIT 1", [$email]);
    
    if (!$user) {
        // Don't reveal if email exists or not for security
        echo json_encode(['success' => true, 'message' => 'If an account exists with this email, a reset code has been sent.']);
        exit;
    }
    
    // Generate 6-digit reset code
    $resetCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Set expiration to 15 minutes from now
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Invalidate any existing unused codes for this email
    $db->execute(
        "UPDATE password_resets SET used = TRUE WHERE email = ? AND used = FALSE",
        [$email]
    );
    
    // Insert new reset code
    $db->execute(
        "INSERT INTO password_resets (email, reset_code, expires_at) VALUES (?, ?, ?)",
        [$email, $resetCode, $expiresAt]
    );
    
    // Send email with reset code
    $emailSent = Email::sendPasswordResetCode($email, $resetCode, $user['full_name']);
    
    if ($emailSent) {
        error_log("Password reset code sent to: {$email}");
        echo json_encode([
            'success' => true,
            'message' => 'A password reset code has been sent to your email address.'
        ]);
    } else {
        error_log("Failed to send password reset code to: {$email}");
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send reset code. Please try again later.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Forgot Password Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}


<?php
/**
 * Employee Registration API Endpoint (with invitation)
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Validator.php';
require_once __DIR__ . '/../../helpers/Invitation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$validator = new Validator();

$token = $_POST['token'] ?? '';
if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Invitation token is required']);
    exit;
}

// Validate user data
$userData = [
    'full_name' => $validator->sanitize($_POST['full_name'] ?? ''),
    'email' => $validator->sanitize($_POST['email'] ?? ''),
    'password' => $_POST['password'] ?? '',
    'department_id' => $_POST['department_id'] ?? null,
];

$validator->required($userData['full_name'], 'Full Name');
$validator->required($userData['email'], 'Email');
$validator->email($userData['email'], 'Email');
$validator->required($userData['password'], 'Password');
$validator->minLength($userData['password'], 8, 'Password');

// Handle profile image upload (required)
if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Profile image is required']);
    exit;
}

if ($validator->image($_FILES['profile_image'], 'Profile Image')) {
    $profileFilename = Validator::uploadFile($_FILES['profile_image'], 'uploads/profiles', 'profile_');
    if ($profileFilename) {
        $userData['profile_image'] = 'uploads/profiles/' . $profileFilename;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload profile image']);
        exit;
    }
}

if ($validator->hasErrors()) {
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->getErrors()]);
    exit;
}

// Log registration attempt
error_log("Registration attempt - Token: {$token}, Email: {$userData['email']}, Name: {$userData['full_name']}");

// Validate invitation token first
$invitation = Invitation::validateToken($token);
if (!$invitation) {
    error_log("Invalid invitation token: {$token}");
    echo json_encode(['success' => false, 'message' => 'Invalid or expired invitation token']);
    exit;
}

error_log("Invitation found - Company ID: {$invitation['company_id']}, Email: {$invitation['email']}, Role: {$invitation['role']}");

// IMPORTANT: Override form email with invitation email to ensure it matches
$userData['email'] = $invitation['email'];
error_log("Using email from invitation: {$userData['email']}");

// Register user
$result = Auth::register($userData, $token);

// Log registration result
error_log("Registration result: " . json_encode($result));

if ($result['success']) {
    // Auto-login
    $loginResult = Auth::login($userData['email'], $userData['password']);
    error_log("Auto-login result: " . json_encode($loginResult));
}

echo json_encode($result);





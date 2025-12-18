<?php
/**
 * Company Registration API Endpoint
 */

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
header('Content-Type: application/json');

// Log the request
error_log("Company Registration API - Request received");
error_log("Company Registration API - Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Company Registration API - POST data: " . print_r($_POST, true));
error_log("Company Registration API - FILES data: " . print_r($_FILES, true));

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Validator.php';
require_once __DIR__ . '/../../helpers/Email.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Company Registration API - Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Expected POST.']);
    exit;
}

$validator = new Validator();

// Validate company data
$companyData = [
    'company_name' => $validator->sanitize($_POST['company_name'] ?? ''),
    'company_email' => $validator->sanitize($_POST['company_email'] ?? ''),
    'phone' => $validator->sanitize($_POST['phone'] ?? ''),
    'address' => $validator->sanitize($_POST['address'] ?? ''),
];

$validator->required($companyData['company_name'], 'Company Name');
$validator->required($companyData['company_email'], 'Company Email');
$validator->email($companyData['company_email'], 'Company Email');

// Validate phone if provided
if (!empty($companyData['phone'])) {
    $validator->phone($companyData['phone'], 'Phone Number');
}

// Handle logo upload
if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../../../uploads/logos';
    $logoFilename = Validator::uploadFile($_FILES['logo'], $uploadDir, 'logo_');
    if ($logoFilename) {
        $companyData['logo'] = 'uploads/logos/' . $logoFilename;
    }
}

// Validate owner data
$ownerData = [
    'full_name' => $validator->sanitize($_POST['full_name'] ?? ''),
    'email' => $validator->sanitize($_POST['email'] ?? ''),
    'password' => $_POST['password'] ?? '',
];

$validator->required($ownerData['full_name'], 'Full Name');
$validator->required($ownerData['email'], 'Email');
$validator->email($ownerData['email'], 'Email');
$validator->required($ownerData['password'], 'Password');
$validator->minLength($ownerData['password'], 8, 'Password');

// Handle profile image upload (required)
if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    $validator->required('', 'Profile Image');
} else {
    error_log("Company Registration - Profile image upload attempt");
    error_log("Company Registration - Files array: " . print_r($_FILES, true));
    
    if ($validator->image($_FILES['profile_image'], 'Profile Image')) {
        // Use absolute path for upload directory (same as profile page)
        $uploadDir = __DIR__ . '/../../../uploads/profiles';
        error_log("Company Registration - Upload directory: $uploadDir");
        error_log("Company Registration - Directory exists: " . (file_exists($uploadDir) ? 'YES' : 'NO'));
        error_log("Company Registration - Directory writable: " . (is_writable($uploadDir) ? 'YES' : 'NO'));
        
        // Ensure directory exists
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                error_log("Company Registration - Failed to create upload directory");
                echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
                exit;
            }
        }
        
        $profileFilename = Validator::uploadFile($_FILES['profile_image'], $uploadDir, 'profile_');
        if ($profileFilename) {
            $ownerData['profile_image'] = 'uploads/profiles/' . $profileFilename;
            error_log("Company Registration - Profile image uploaded successfully: " . $ownerData['profile_image']);
            
            // Verify file exists
            $fullPath = __DIR__ . '/../../../' . $ownerData['profile_image'];
            if (file_exists($fullPath)) {
                error_log("Company Registration - Verified profile image file exists at: $fullPath");
            } else {
                error_log("Company Registration - WARNING: Profile image file does not exist at: $fullPath");
            }
        } else {
            error_log("Company Registration - Profile image upload failed - Validator::uploadFile returned false");
            $uploadErrors = $validator->getErrors();
            error_log("Company Registration - Upload errors: " . json_encode($uploadErrors));
            echo json_encode(['success' => false, 'message' => 'Failed to upload profile image: ' . json_encode($uploadErrors)]);
            exit;
        }
    } else {
        $validationErrors = $validator->getErrors();
        error_log("Company Registration - Profile image validation failed: " . json_encode($validationErrors));
        echo json_encode(['success' => false, 'message' => 'Invalid profile image', 'errors' => $validationErrors]);
        exit;
    }
}

if ($validator->hasErrors()) {
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->getErrors()]);
    exit;
}

// Register company
try {
    $result = Auth::registerCompany($companyData, $ownerData);
    
    if ($result['success']) {
        // Send welcome email (don't fail registration if email fails)
        try {
            Email::sendCompanyWelcome($ownerData['email'], $ownerData['full_name'], $companyData['company_name']);
        } catch (Exception $e) {
            error_log("Company Registration - Email send failed: " . $e->getMessage());
            // Continue with registration even if email fails
        }
        
        // Auto-login - this sets the session including profile_image
        $loginResult = Auth::login($ownerData['email'], $ownerData['password']);
        
        if ($loginResult['success']) {
            error_log("Company registered and owner logged in. Profile image: " . ($_SESSION['profile_image'] ?? 'not set'));
            echo json_encode([
                'success' => true,
                'message' => 'Company registered successfully',
                'session_data' => [
                    'user_id' => $_SESSION['user_id'],
                    'profile_image' => $_SESSION['profile_image'] ?? null,
                    'company_name' => $_SESSION['company_name'] ?? null
                ]
            ]);
        } else {
            error_log("Company Registration - Login failed after registration: " . ($loginResult['message'] ?? 'Unknown error'));
            echo json_encode([
                'success' => true, 
                'message' => 'Company registered successfully but login failed. Please login manually.',
                'login_error' => $loginResult['message'] ?? 'Unknown error'
            ]);
        }
    } else {
        error_log("Company Registration - Registration failed: " . ($result['message'] ?? 'Unknown error'));
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Registration failed',
            'errors' => $result['errors'] ?? []
        ]);
    }
} catch (Exception $e) {
    error_log("Company Registration - Exception: " . $e->getMessage());
    error_log("Company Registration - Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Registration failed: ' . $e->getMessage(),
        'error_type' => get_class($e)
    ]);
}




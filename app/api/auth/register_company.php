<?php
/**
 * Company Registration API Endpoint
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Validator.php';
require_once __DIR__ . '/../../helpers/Email.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
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

// Handle logo upload
if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $logoFilename = Validator::uploadFile($_FILES['logo'], 'uploads/logos', 'logo_');
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
    if ($validator->image($_FILES['profile_image'], 'Profile Image')) {
        $profileFilename = Validator::uploadFile($_FILES['profile_image'], 'uploads/profiles', 'profile_');
        if ($profileFilename) {
            $ownerData['profile_image'] = 'uploads/profiles/' . $profileFilename;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload profile image']);
            exit;
        }
    }
}

if ($validator->hasErrors()) {
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->getErrors()]);
    exit;
}

// Register company
$result = Auth::registerCompany($companyData, $ownerData);

if ($result['success']) {
    // Send welcome email
    Email::sendCompanyWelcome($ownerData['email'], $ownerData['full_name'], $companyData['company_name']);
    
    // Auto-login
    Auth::login($ownerData['email'], $ownerData['password']);
}

echo json_encode($result);



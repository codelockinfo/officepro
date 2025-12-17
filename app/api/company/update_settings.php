<?php
/**
 * Update Company Settings API
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Tenant.php';
require_once __DIR__ . '/../../helpers/Validator.php';

// Check authentication and authorization
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

Auth::requireRole(['company_owner']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$companyId = Tenant::getCurrentCompanyId();
$db = Database::getInstance();
$validator = new Validator();

// Get current company data
$currentCompany = $db->fetchOne("SELECT * FROM companies WHERE id = ?", [$companyId]);
if (!$currentCompany) {
    echo json_encode(['success' => false, 'message' => 'Company not found']);
    exit;
}

// Handle logo removal
if (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
    try {
        $db->beginTransaction();
        
        // Delete old logo file if exists
        if ($currentCompany['logo'] && !empty(trim($currentCompany['logo']))) {
            $oldLogoPath = __DIR__ . '/../../../' . $currentCompany['logo'];
            if (file_exists($oldLogoPath)) {
                @unlink($oldLogoPath);
            }
        }
        
        // Update company to remove logo
        $db->execute(
            "UPDATE companies SET logo = NULL, updated_at = NOW() WHERE id = ?",
            [$companyId]
        );
        
        // Update session
        $_SESSION['company_logo'] = null;
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Logo removed successfully'
        ]);
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Remove Logo Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to remove logo']);
        exit;
    }
}

// Validate and sanitize input
$companyName = $validator->sanitize($_POST['company_name'] ?? '');
$companyEmail = $validator->sanitize($_POST['company_email'] ?? '');
$phone = $validator->sanitize($_POST['phone'] ?? '');
$address = $validator->sanitize($_POST['address'] ?? '');

$validator->required($companyName, 'Company Name');
$validator->required($companyEmail, 'Company Email');
$validator->email($companyEmail, 'Company Email');

if ($validator->hasErrors()) {
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->getErrors()]);
    exit;
}

try {
    $db->beginTransaction();
    
    // Handle logo upload if provided
    // Start with existing logo (preserve it if no new upload)
    $logoPath = $currentCompany['logo'] ?? null;
    $logoUpdated = false;
    
    // If existing logo is empty string, treat as null
    if ($logoPath === '') {
        $logoPath = null;
    }
    
    error_log("Update Settings - Starting update for company ID: $companyId");
    error_log("Update Settings - Current logo in DB: " . ($currentCompany['logo'] ?? 'NULL'));
    error_log("Update Settings - POST data: " . print_r($_POST, true));
    error_log("Update Settings - FILES data: " . print_r($_FILES, true));
    
    // Check if logo file is being uploaded
    // More thorough check for file upload
    $hasLogoFile = false;
    $logoFileError = null;
    
    if (isset($_FILES['logo'])) {
        $logoFileError = $_FILES['logo']['error'] ?? null;
        $hasLogoFile = isset($_FILES['logo']['tmp_name']) && 
                       !empty($_FILES['logo']['tmp_name']) && 
                       is_uploaded_file($_FILES['logo']['tmp_name']) &&
                       $logoFileError === UPLOAD_ERR_OK;
        
        error_log("Update Settings - Logo file check: hasLogoFile=" . ($hasLogoFile ? 'YES' : 'NO') . 
                  ", error=" . ($logoFileError ?? 'NULL') . 
                  ", tmp_name=" . ($_FILES['logo']['tmp_name'] ?? 'NULL') . 
                  ", name=" . ($_FILES['logo']['name'] ?? 'NULL') . 
                  ", size=" . ($_FILES['logo']['size'] ?? 'NULL'));
    } else {
        error_log("Update Settings - No logo file in request (FILES array doesn't contain 'logo')");
    }
    
    if ($hasLogoFile) {
        error_log("Update Settings - Logo file detected and valid, proceeding with upload");
        
        // Validate image (skip dimension check for company logo)
        $validator->clearErrors(); // Clear any previous errors
        if ($validator->image($_FILES['logo'], 'Company Logo', false)) { // false = don't check dimensions for logo
            error_log("Update Settings - Logo validation passed");
            
            // Delete old logo if exists (and not default)
            if ($currentCompany['logo'] && !empty(trim($currentCompany['logo']))) {
                $oldLogoPath = __DIR__ . '/../../../' . $currentCompany['logo'];
                if (file_exists($oldLogoPath)) {
                    @unlink($oldLogoPath);
                    error_log("Update Settings - Deleted old logo: $oldLogoPath");
                }
            }
            
            // Upload new logo
            $uploadDir = __DIR__ . '/../../../uploads/logos';
            error_log("Update Settings - Upload directory: $uploadDir");
            error_log("Update Settings - Directory exists: " . (file_exists($uploadDir) ? 'YES' : 'NO'));
            error_log("Update Settings - Directory writable: " . (is_writable($uploadDir) ? 'YES' : 'NO'));
            
            // Ensure directory exists
            if (!file_exists($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log("Update Settings - Failed to create upload directory");
                    throw new Exception('Failed to create upload directory');
                }
            }
            
            $logoFilename = Validator::uploadFile($_FILES['logo'], $uploadDir, 'logo_');
            if ($logoFilename) {
                $logoPath = 'uploads/logos/' . $logoFilename;
                $logoUpdated = true;
                error_log("Update Settings - Logo uploaded successfully: $logoPath");
                
                // Verify file actually exists
                $fullPath = __DIR__ . '/../../../' . $logoPath;
                if (file_exists($fullPath)) {
                    error_log("Update Settings - Verified logo file exists at: $fullPath");
                } else {
                    error_log("Update Settings - WARNING: Logo file does not exist at: $fullPath");
                }
            } else {
                error_log("Update Settings - Logo upload failed - Validator::uploadFile returned false");
                $uploadErrors = $validator->getErrors();
                error_log("Update Settings - Upload errors: " . json_encode($uploadErrors));
                throw new Exception('Failed to upload logo: ' . json_encode($uploadErrors));
            }
        } else {
            $validationErrors = $validator->getErrors();
            error_log("Update Settings - Logo validation failed: " . json_encode($validationErrors));
            // If validation fails, don't update logo but continue with other fields
            // Set logoPath back to existing to preserve it
            $logoPath = $currentCompany['logo'] ?? null;
            $logoUpdated = false;
        }
    } else {
        if (isset($_FILES['logo'])) {
            error_log("Update Settings - Logo file present but invalid. Error code: " . ($logoFileError ?? 'NULL'));
            if ($logoFileError !== null && $logoFileError !== UPLOAD_ERR_OK) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
                ];
                error_log("Update Settings - Upload error meaning: " . ($errorMessages[$logoFileError] ?? 'Unknown error'));
            }
        } else {
            error_log("Update Settings - No logo file in request - preserving existing logo");
        }
    }
    
    // Update company in database
    error_log("Update Settings - About to update database. Logo path: " . ($logoPath ?? 'NULL') . ", Logo updated: " . ($logoUpdated ? 'YES' : 'NO'));
    error_log("Update Settings - Current company logo before update: " . ($currentCompany['logo'] ?? 'NULL'));
    
    // If no new logo uploaded, preserve existing logo
    if (!$logoUpdated) {
        if ($logoPath === null || $logoPath === '') {
            $logoPath = $currentCompany['logo'] ?? null;
            error_log("Update Settings - Preserving existing logo: " . ($logoPath ?? 'NULL'));
        }
    }
    
    // CRITICAL: Only update logo if we have a valid path (either new or existing)
    // If logoPath is still null/empty and no existing logo, set to null explicitly
    if ($logoPath === '' || (empty($logoPath) && !$currentCompany['logo'])) {
        $logoPath = null;
    }
    
    error_log("Update Settings - Final logo path to save: " . ($logoPath ?? 'NULL'));
    
    // Always update all fields including logo
    $db->execute(
        "UPDATE companies SET 
            company_name = ?, 
            company_email = ?, 
            phone = ?, 
            address = ?, 
            logo = ?,
            updated_at = NOW()
        WHERE id = ?",
        [$companyName, $companyEmail, $phone, $address, $logoPath, $companyId]
    );
    
    error_log("Update Settings - Database update executed with logo: " . ($logoPath ?? 'NULL'));
    
    // Save working hours setting
    $workingHours = floatval($_POST['working_hours'] ?? 8);
    
    // Validate working hours (between 1 and 24)
    if ($workingHours < 1 || $workingHours > 24) {
        $workingHours = 8; // Default to 8 hours
    }
    
    // Save working hours to company_settings table
    $db->execute(
        "INSERT INTO company_settings (company_id, setting_key, setting_value, updated_at) 
         VALUES (?, 'working_hours', ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()",
        [$companyId, $workingHours, $workingHours]
    );
    
    error_log("Update Settings - Working hours saved: $workingHours hours per day");
    
    $db->commit();
    
    // Verify the logo was saved
    $updatedCompany = $db->fetchOne("SELECT logo FROM companies WHERE id = ?", [$companyId]);
    $savedLogo = $updatedCompany['logo'] ?? null;
    
    error_log("Update Settings - After save, logo in DB: " . ($savedLogo ?? 'NULL'));
    
    // Update session with new company data
    $_SESSION['company_name'] = $companyName;
    $_SESSION['company_logo'] = $savedLogo;
    
    error_log("Company settings updated - Company ID: $companyId, Logo saved: $savedLogo, Logo path: $logoPath");
    
    // Return the saved logo from database (not the variable, to ensure accuracy)
    $finalLogo = $savedLogo ?? $logoPath ?? null;
    
    // Always include logo in response (even if null, so frontend knows the state)
    $responseData = [
        'success' => true,
        'message' => 'Company settings updated successfully',
        'data' => [
            'company_name' => $companyName,
            'company_logo' => $finalLogo
        ]
    ];
    
    error_log("Update Settings - API Response sent. Logo: " . ($finalLogo ?? 'NULL'));
    error_log("Update Settings - Full response: " . json_encode($responseData));
    
    echo json_encode($responseData);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Update Company Settings Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update company settings: ' . $e->getMessage()]);
}


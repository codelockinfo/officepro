<?php
/**
 * Change Profile Photo API
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Validator.php';

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId = Auth::getCurrentUser()['id'];
$db = Database::getInstance();
$validator = new Validator();

// Validate image upload
if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Profile image is required']);
    exit;
}

if (!$validator->image($_FILES['profile_image'], 'Profile Image')) {
    echo json_encode(['success' => false, 'message' => 'Invalid image', 'errors' => $validator->getErrors()]);
    exit;
}

try {
    // Get old image path to delete
    $oldImage = $db->fetchOne("SELECT profile_image FROM users WHERE id = ?", [$userId]);
    
    // Log upload attempt
    error_log("Change Photo: Starting upload for user $userId");
    error_log("Change Photo: Files array: " . print_r($_FILES, true));
    
    // Upload new image
    $uploadDir = __DIR__ . '/../../../uploads/profiles';
    error_log("Change Photo: Upload directory: $uploadDir");
    error_log("Change Photo: Directory exists: " . (file_exists($uploadDir) ? 'YES' : 'NO'));
    error_log("Change Photo: Directory writable: " . (is_writable($uploadDir) ? 'YES' : 'NO'));
    
    $filename = Validator::uploadFile($_FILES['profile_image'], $uploadDir, 'profile_');
    
    if (!$filename) {
        error_log("Change Photo: Upload failed - Validator::uploadFile returned false");
        echo json_encode(['success' => false, 'message' => 'Failed to upload image. Check file permissions.']);
        exit;
    }
    
    error_log("Change Photo: Upload successful. Filename: $filename");
    
    $newImagePath = 'uploads/profiles/' . $filename;
    
    // Update database
    $affected = $db->execute(
        "UPDATE users SET profile_image = ? WHERE id = ?",
        [$newImagePath, $userId]
    );
    
    error_log("Change Photo: Database updated. Affected rows: $affected. New path: $newImagePath");
    
    // Update session
    $_SESSION['profile_image'] = $newImagePath;
    error_log("Change Photo: Session updated");
    
    // Delete old image if not default
    if ($oldImage && $oldImage['profile_image'] !== 'assets/images/default-avatar.png') {
        $oldPath = __DIR__ . '/../../../' . $oldImage['profile_image'];
        if (file_exists($oldPath)) {
            @unlink($oldPath);
            error_log("Change Photo: Deleted old image: $oldPath");
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile photo updated',
        'new_image' => $newImagePath,
        'debug' => [
            'uploaded_file' => $filename,
            'full_path' => $newImagePath,
            'session_updated' => $_SESSION['profile_image'],
            'db_updated' => $affected > 0
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Change Photo Error: " . $e->getMessage());
    error_log("Change Photo Stack Trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Failed to update photo: ' . $e->getMessage()]);
}


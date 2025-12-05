<?php
/**
 * Check-in API Endpoint
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Tenant.php';

// Check authentication
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$companyId = Tenant::getCurrentCompanyId();
$userId = Auth::getCurrentUser()['id'];
$db = Database::getInstance();

// Set timezone from config
$appConfig = require __DIR__ . '/../../config/app.php';
date_default_timezone_set($appConfig['timezone']);

$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');

error_log("Check-in: User $userId checking in at $now");

// Check if already checked in
$existing = $db->fetchOne(
    "SELECT id FROM attendance WHERE company_id = ? AND user_id = ? AND date = ? AND status = 'in' LIMIT 1",
    [$companyId, $userId, $today]
);

if ($existing) {
    echo json_encode(['success' => false, 'message' => 'You are already checked in']);
    exit;
}

try {
    // Insert check-in record
    $db->execute(
        "INSERT INTO attendance (company_id, user_id, check_in, date, status, created_at) 
        VALUES (?, ?, ?, ?, 'in', NOW())",
        [$companyId, $userId, $now, $today]
    );
    
    $attendanceId = $db->lastInsertId();
    error_log("Check-in: Success! Attendance ID: $attendanceId, Time: $now");
    
    echo json_encode([
        'success' => true,
        'message' => 'Checked in successfully',
        'data' => [
            'check_in_time' => $now,
            'attendance_id' => $attendanceId
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Check-in Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to check in']);
}




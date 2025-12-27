<?php
/**
 * Attendance Status API Endpoint
 * Returns current attendance status for timer polling
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

$companyId = Tenant::getCurrentCompanyId();
$userId = Auth::getCurrentUser()['id'];
$db = Database::getInstance();

// Set timezone from config
$appConfig = require __DIR__ . '/../../config/app.php';
date_default_timezone_set($appConfig['timezone']);

$today = date('Y-m-d');

// Get current check-in status
$attendance = $db->fetchOne(
    "SELECT * FROM attendance WHERE company_id = ? AND user_id = ? AND date = ? AND status = 'in' ORDER BY check_in DESC LIMIT 1",
    [$companyId, $userId, $today]
);

if ($attendance) {
    // Calculate elapsed time
    $checkInTime = new DateTime($attendance['check_in'], new DateTimeZone($appConfig['timezone']));
    $now = new DateTime('now', new DateTimeZone($appConfig['timezone']));
    $interval = $checkInTime->diff($now);
    
    $elapsedSeconds = ($interval->days * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    
    // Check if overtime
    $standardWorkHours = $appConfig['standard_work_hours'];
    $isOvertime = $elapsedSeconds >= ($standardWorkHours * 3600);
    
    // Format check-in time as ISO 8601 with timezone
    $checkInTimeISO = $checkInTime->format('c'); // ISO 8601 format with timezone
    
    echo json_encode([
        'success' => true,
        'data' => [
            'status' => 'in',
            'check_in_time' => $checkInTimeISO,
            'check_in_time_display' => $attendance['check_in'], // Keep original format for display
            'elapsed_seconds' => $elapsedSeconds,
            'is_overtime' => $isOvertime
        ]
    ]);
} else {
    echo json_encode([
        'success' => true,
        'data' => [
            'status' => 'out',
            'check_in_time' => null,
            'elapsed_seconds' => 0,
            'is_overtime' => false
        ]
    ]);
}





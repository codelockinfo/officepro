<?php
/**
 * Resume Timer Session API Endpoint
 * Resumes a stopped timer session
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

// Get current check-in record
$attendance = $db->fetchOne(
    "SELECT * FROM attendance WHERE company_id = ? AND user_id = ? AND date = ? AND status = 'in' ORDER BY check_in DESC LIMIT 1",
    [$companyId, $userId, $today]
);

if (!$attendance) {
    echo json_encode(['success' => false, 'message' => 'No active check-in found']);
    exit;
}

// Get stopped timer session
$stoppedSession = $db->fetchOne(
    "SELECT * FROM timer_sessions WHERE attendance_id = ? AND status = 'stopped' ORDER BY start_time DESC LIMIT 1",
    [$attendance['id']]
);

if (!$stoppedSession) {
    echo json_encode(['success' => false, 'message' => 'No stopped timer session found']);
    exit;
}

// Check if there's already a running timer
$runningSession = $db->fetchOne(
    "SELECT * FROM timer_sessions WHERE attendance_id = ? AND status = 'running' ORDER BY start_time DESC LIMIT 1",
    [$attendance['id']]
);

if ($runningSession) {
    echo json_encode(['success' => false, 'message' => 'Timer is already running']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Calculate the worked duration before pause
    $startTime = new DateTime($stoppedSession['start_time']);
    $stopTime = new DateTime($stoppedSession['stop_time']);
    $workedDuration = $stopTime->getTimestamp() - $startTime->getTimestamp();
    
    // Calculate new start_time = current_time - worked_duration
    // This way, when we calculate duration later, it will be correct
    $now = new DateTime();
    $newStartTime = $now->getTimestamp() - $workedDuration;
    $newStartTimeFormatted = date('Y-m-d H:i:s', $newStartTime);
    
    error_log("Resume Timer - Original start: {$stoppedSession['start_time']}, Stop: {$stoppedSession['stop_time']}, Worked: {$workedDuration}s, New start: {$newStartTimeFormatted}");
    
    // Resume the stopped session - adjust start_time to account for paused time
    // This ensures duration calculation from new start_time to end_time is correct
    $db->execute(
        "UPDATE timer_sessions SET start_time = ?, stop_time = NULL, duration_seconds = 0, status = 'running', updated_at = NOW() 
        WHERE id = ?",
        [$newStartTimeFormatted, $stoppedSession['id']]
    );
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Timer resumed successfully',
        'data' => [
            'session_id' => $stoppedSession['id'],
            'start_time' => $newStartTimeFormatted
        ]
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Resume Timer Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to resume timer']);
}


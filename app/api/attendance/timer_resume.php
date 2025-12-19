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
    
    // IMPORTANT: Preserve the accumulated duration from previous pauses
    // Get the already worked duration (from duration_seconds if set, otherwise calculate)
    $accumulatedDuration = intval($stoppedSession['duration_seconds'] ?? 0);
    
    // If duration_seconds is 0 or not set, calculate from start_time to stop_time
    if ($accumulatedDuration == 0) {
        $startTime = new DateTime($stoppedSession['start_time']);
        $stopTime = new DateTime($stoppedSession['stop_time']);
        $accumulatedDuration = $stopTime->getTimestamp() - $startTime->getTimestamp();
    }
    
    // Calculate new start_time = current_time - accumulated_duration
    // This way, when we calculate duration later, it will include all previous work
    $now = new DateTime();
    $newStartTime = $now->getTimestamp() - $accumulatedDuration;
    $newStartTimeFormatted = date('Y-m-d H:i:s', $newStartTime);
    
    error_log("Resume Timer - Original start: {$stoppedSession['start_time']}, Stop: {$stoppedSession['stop_time']}, Accumulated duration: {$accumulatedDuration}s, New start: {$newStartTimeFormatted}");
    
    // Resume the stopped session - adjust start_time but PRESERVE accumulated duration
    // Keep duration_seconds so we can accumulate more time when pausing again
    $db->execute(
        "UPDATE timer_sessions SET start_time = ?, stop_time = NULL, status = 'running', updated_at = NOW() 
        WHERE id = ?",
        [$newStartTimeFormatted, $stoppedSession['id']]
    );
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Timer resumed successfully',
        'data' => [
            'session_id' => $stoppedSession['id'],
            'start_time' => $newStartTimeFormatted,
            'accumulated_duration' => $accumulatedDuration
        ]
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Resume Timer Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to resume timer']);
}


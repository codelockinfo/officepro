<?php
/**
 * Stop Timer Session API Endpoint
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

$now = date('Y-m-d H:i:s');

// Get current check-in record
$today = date('Y-m-d');
$attendance = $db->fetchOne(
    "SELECT * FROM attendance WHERE company_id = ? AND user_id = ? AND date = ? AND status = 'in' ORDER BY check_in DESC LIMIT 1",
    [$companyId, $userId, $today]
);

if (!$attendance) {
    echo json_encode(['success' => false, 'message' => 'No active check-in found']);
    exit;
}

// Get running timer session
$runningSession = $db->fetchOne(
    "SELECT * FROM timer_sessions WHERE attendance_id = ? AND status = 'running' ORDER BY start_time DESC LIMIT 1",
    [$attendance['id']]
);

if (!$runningSession) {
    echo json_encode(['success' => false, 'message' => 'No running timer found']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Calculate duration using timestamps for accuracy
    $startTime = new DateTime($runningSession['start_time']);
    $stopTime = new DateTime($now);
    $startTimestamp = $startTime->getTimestamp();
    $stopTimestamp = $stopTime->getTimestamp();
    $durationSeconds = $stopTimestamp - $startTimestamp;
    
    // Ensure positive duration
    if ($durationSeconds < 0) {
        error_log("Stop Timer Warning: Negative duration calculated. Start: {$startTimestamp}, Stop: {$stopTimestamp}");
        $durationSeconds = 0;
    }
    
    error_log("Stop Timer - Session ID: {$runningSession['id']}, Start: {$runningSession['start_time']}, Stop: {$now}, Duration: {$durationSeconds} seconds");
    
    // Update timer session to stopped
    $db->execute(
        "UPDATE timer_sessions SET stop_time = ?, duration_seconds = ?, status = 'stopped', updated_at = NOW() 
        WHERE id = ?",
        [$now, $durationSeconds, $runningSession['id']]
    );
    
    $db->commit();
    
    error_log("Stop Timer - Successfully stopped session {$runningSession['id']} with duration: {$durationSeconds}s");
    
    echo json_encode([
        'success' => true,
        'message' => 'Timer stopped successfully',
        'data' => [
            'session_id' => $runningSession['id'],
            'duration_seconds' => $durationSeconds
        ]
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Stop Timer Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to stop timer: ' . $e->getMessage()]);
}


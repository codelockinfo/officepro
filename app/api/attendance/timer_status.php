<?php
/**
 * Timer Status API Endpoint
 * Returns current timer session status
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

// Get current check-in record
$attendance = $db->fetchOne(
    "SELECT * FROM attendance WHERE company_id = ? AND user_id = ? AND date = ? AND status = 'in' ORDER BY check_in DESC LIMIT 1",
    [$companyId, $userId, $today]
);

if (!$attendance) {
    echo json_encode([
        'success' => true,
        'data' => [
            'has_checkin' => false,
            'timer_status' => null
        ]
    ]);
    exit;
}

// Get current timer session
$timerSession = $db->fetchOne(
    "SELECT * FROM timer_sessions WHERE attendance_id = ? AND status IN ('running', 'stopped') ORDER BY start_time DESC LIMIT 1",
    [$attendance['id']]
);

if ($timerSession) {
    // Calculate elapsed time
    $startTime = new DateTime($timerSession['start_time']);
    $now = new DateTime();
    $interval = $startTime->diff($now);
    $elapsedSeconds = ($interval->days * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'has_checkin' => true,
            'timer_status' => $timerSession['status'],
            'session_id' => $timerSession['id'],
            'start_time' => $timerSession['start_time'],
            'stop_time' => $timerSession['stop_time'],
            'elapsed_seconds' => $elapsedSeconds
        ]
    ]);
} else {
    echo json_encode([
        'success' => true,
        'data' => [
            'has_checkin' => true,
            'timer_status' => 'not_started',
            'session_id' => null
        ]
    ]);
}


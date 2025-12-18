<?php
/**
 * Start Timer Session API Endpoint
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

// Check if timer_sessions table exists
try {
    $db->fetchOne("SELECT 1 FROM timer_sessions LIMIT 1");
} catch (Exception $e) {
    error_log("Timer Sessions table does not exist. Please run migration: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Timer system not initialized. Please run the migration: database/migration_timer_sessions.sql'
    ]);
    exit;
}

// Check if there's already a running timer session for today
$runningSession = $db->fetchOne(
    "SELECT * FROM timer_sessions WHERE company_id = ? AND user_id = ? AND date = ? AND status = 'running' ORDER BY start_time DESC LIMIT 1",
    [$companyId, $userId, $today]
);

if ($runningSession) {
    echo json_encode(['success' => false, 'message' => 'Timer is already running']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Create new timer session (no attendance_id required)
    $db->execute(
        "INSERT INTO timer_sessions (company_id, user_id, date, start_time, status, created_at) 
        VALUES (?, ?, ?, ?, 'running', NOW())",
        [$companyId, $userId, $today, $now]
    );
    
    $sessionId = $db->lastInsertId();
    
    // Mark user as present for today (create or update attendance record)
    $attendance = $db->fetchOne(
        "SELECT * FROM attendance WHERE company_id = ? AND user_id = ? AND date = ? LIMIT 1",
        [$companyId, $userId, $today]
    );
    
    if ($attendance) {
        // Update is_present to 1
        $db->execute(
            "UPDATE attendance SET is_present = 1, updated_at = NOW() WHERE id = ?",
            [$attendance['id']]
        );
    } else {
        // Create new attendance record with is_present = 1
        $db->execute(
            "INSERT INTO attendance (company_id, user_id, date, is_present, regular_hours, overtime_hours, created_at) 
            VALUES (?, ?, ?, 1, 0.00, 0.00, NOW())",
            [$companyId, $userId, $today]
        );
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Timer started successfully',
        'data' => [
            'session_id' => $sessionId,
            'start_time' => $now
        ]
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Start Timer Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to start timer']);
}


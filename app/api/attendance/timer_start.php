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

// Get current check-in record
$attendance = $db->fetchOne(
    "SELECT * FROM attendance WHERE company_id = ? AND user_id = ? AND date = ? AND status = 'in' ORDER BY check_in DESC LIMIT 1",
    [$companyId, $userId, $today]
);

if (!$attendance) {
    echo json_encode(['success' => false, 'message' => 'Please check in first']);
    exit;
}

// Check if timer_sessions table exists, if not return helpful error
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

// Check if there's already a running timer session
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
    
    // Create new timer session
    $db->execute(
        "INSERT INTO timer_sessions (company_id, user_id, attendance_id, start_time, status, created_at) 
        VALUES (?, ?, ?, ?, 'running', NOW())",
        [$companyId, $userId, $attendance['id'], $now]
    );
    
    $sessionId = $db->lastInsertId();
    
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


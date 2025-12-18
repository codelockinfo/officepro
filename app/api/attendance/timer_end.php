<?php
/**
 * End Timer Session API Endpoint
 * Ends the timer session and calculates regular/overtime hours
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Tenant.php';
require_once __DIR__ . '/../../helpers/AttendanceCalculator.php';

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

// Get running or stopped timer session
// IMPORTANT: Get the most recent session by updated_at, not start_time
// This ensures we get the correct session even after resume
$timerSession = $db->fetchOne(
    "SELECT * FROM timer_sessions WHERE attendance_id = ? AND status IN ('running', 'stopped') ORDER BY updated_at DESC, start_time DESC LIMIT 1",
    [$attendance['id']]
);

if (!$timerSession) {
    echo json_encode(['success' => false, 'message' => 'No active timer session found']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Determine the end time for calculation
    // Rule: If timer status is 'stopped' and stop_time exists, use stop_time as end_time
    //       Otherwise, use current time as end_time
    $startTime = new DateTime($timerSession['start_time']);
    
    if ($timerSession['status'] === 'stopped' && !empty($timerSession['stop_time'])) {
        // Timer was stopped (paused) - use the stop_time as the end_time
        $endTime = new DateTime($timerSession['stop_time']);
        $finalEndTime = $timerSession['stop_time'];
        error_log("Timer End - Using stop_time as end_time: {$finalEndTime}");
    } else {
        // Timer is running - use current time as end_time
        // NOTE: If timer was resumed, start_time was already adjusted in timer_resume.php
        // So duration calculation from start_time to now will be correct
        $endTime = new DateTime($now);
        $finalEndTime = $now;
        error_log("Timer End - Using current time as end_time: {$finalEndTime}");
    }
    
    // Calculate duration in seconds using timestamps
    $startTimestamp = $startTime->getTimestamp();
    $endTimestamp = $endTime->getTimestamp();
    $durationSeconds = $endTimestamp - $startTimestamp;
    
    // Ensure positive duration
    if ($durationSeconds < 0) {
        error_log("Timer End ERROR: Negative duration calculated. Start: {$startTimestamp} ({$timerSession['start_time']}), End: {$endTimestamp} ({$finalEndTime})");
        $durationSeconds = 0;
    }
    
    // Minimum duration check - warn if suspiciously short
    if ($durationSeconds < 1) {
        error_log("Timer End WARNING: Duration is less than 1 second. Start: {$timerSession['start_time']}, End: {$finalEndTime}");
    }
    
    $durationHours = $durationSeconds / 3600;
    
    error_log("Timer End - Session ID: {$timerSession['id']}, Status: {$timerSession['status']}, Start: {$timerSession['start_time']}, End: {$finalEndTime}, Duration: {$durationSeconds} seconds ({$durationHours} hours)");
    
    // Calculate regular and overtime hours
    // Regular hours: up to standard work hours (e.g., 8 hours)
    // Overtime hours: anything beyond standard work hours
    $standardWorkHours = floatval($appConfig['standard_work_hours'] ?? 8);
    $regularHours = min($durationHours, $standardWorkHours);
    $overtimeHours = max(0, $durationHours - $standardWorkHours);
    
    // Round to 2 decimal places for storage
    $regularHours = round($regularHours, 2);
    $overtimeHours = round($overtimeHours, 2);
    
    error_log("Timer End - Calculated hours: Regular: {$regularHours}h, Overtime: {$overtimeHours}h (Standard: {$standardWorkHours}h, Total Duration: {$durationHours}h)");
    
    // Update timer session - set end_time, duration, and hours
    $updateResult = $db->execute(
        "UPDATE timer_sessions SET end_time = ?, duration_seconds = ?, regular_hours = ?, overtime_hours = ?, status = 'ended', updated_at = NOW() 
        WHERE id = ?",
        [$finalEndTime, $durationSeconds, $regularHours, $overtimeHours, $timerSession['id']]
    );
    
    if (!$updateResult) {
        throw new Exception("Failed to update timer session");
    }
    
    error_log("Timer End - Successfully updated session {$timerSession['id']} with end_time: {$finalEndTime}, duration: {$durationSeconds}s, regular: {$regularHours}h, overtime: {$overtimeHours}h");
    
    // Update attendance record with accumulated hours from all ended timer sessions
    // IMPORTANT: Calculate overtime based on TOTAL hours worked, not per session
    $allSessions = $db->fetchAll(
        "SELECT SUM(regular_hours + overtime_hours) as total_hours
         FROM timer_sessions 
         WHERE attendance_id = ? AND status = 'ended'",
        [$attendance['id']]
    );
    
    $totalHoursWorked = floatval($allSessions[0]['total_hours'] ?? 0);
    
    // Calculate regular and overtime based on TOTAL hours for the day
    // Regular hours: up to standard work hours
    // Overtime hours: anything beyond standard work hours
    $standardWorkHours = floatval($appConfig['standard_work_hours'] ?? 8);
    $totalRegular = min($totalHoursWorked, $standardWorkHours);
    $totalOvertime = max(0, $totalHoursWorked - $standardWorkHours);
    
    // Round to 2 decimal places
    $totalRegular = round($totalRegular, 2);
    $totalOvertime = round($totalOvertime, 2);
    
    error_log("Timer End - Total hours worked: {$totalHoursWorked}h, Regular: {$totalRegular}h, Overtime: {$totalOvertime}h (Standard: {$standardWorkHours}h)");
    
    // CRITICAL: Preserve check_in time explicitly - never modify it
    $originalCheckIn = $attendance['check_in'];
    error_log("Timer End - Preserving check_in time: {$originalCheckIn}");
    
    // Update attendance record - explicitly preserve check_in time
    $db->execute(
        "UPDATE attendance SET check_in = ?, regular_hours = ?, overtime_hours = ?, updated_at = NOW() 
        WHERE id = ?",
        [$originalCheckIn, $totalRegular, $totalOvertime, $attendance['id']]
    );
    
    // Verify check_in was not modified
    $verify = $db->fetchOne("SELECT check_in FROM attendance WHERE id = ?", [$attendance['id']]);
    if ($verify['check_in'] != $originalCheckIn) {
        error_log("Timer End - ERROR: check_in was modified! Restoring original value.");
        $db->execute("UPDATE attendance SET check_in = ? WHERE id = ?", [$originalCheckIn, $attendance['id']]);
    }
    
    $db->commit();
    
    // Verify the update was successful
    $verifySession = $db->fetchOne("SELECT * FROM timer_sessions WHERE id = ?", [$timerSession['id']]);
    if (!$verifySession) {
        throw new Exception("Failed to verify timer session update");
    }
    
    error_log("Timer End - Verification: Session duration = {$verifySession['duration_seconds']}s, regular = {$verifySession['regular_hours']}h, overtime = {$verifySession['overtime_hours']}h, status = {$verifySession['status']}");
    
    // Double-check that status is 'ended'
    if ($verifySession['status'] !== 'ended') {
        error_log("Timer End - WARNING: Session status is not 'ended'! Current status: {$verifySession['status']}");
        // Try to fix it
        $db->execute("UPDATE timer_sessions SET status = 'ended' WHERE id = ?", [$timerSession['id']]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Timer ended successfully',
        'data' => [
            'session_id' => $timerSession['id'],
            'duration_seconds' => intval($verifySession['duration_seconds']),
            'regular_hours' => round(floatval($verifySession['regular_hours']), 2),
            'overtime_hours' => round(floatval($verifySession['overtime_hours']), 2),
            'total_regular' => round($totalRegular, 2),
            'total_overtime' => round($totalOvertime, 2)
        ]
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("End Timer Error: " . $e->getMessage());
    error_log("End Timer Error Trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Failed to end timer: ' . $e->getMessage()]);
}


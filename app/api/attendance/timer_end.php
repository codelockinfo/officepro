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
$today = date('Y-m-d');

// Get running timer session for today
$timerSession = $db->fetchOne(
    "SELECT * FROM timer_sessions WHERE company_id = ? AND user_id = ? AND date = ? AND status = 'running' ORDER BY start_time DESC LIMIT 1",
    [$companyId, $userId, $today]
);

if (!$timerSession) {
    echo json_encode(['success' => false, 'message' => 'No running timer session found']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Simple calculation: duration from start_time to now
    $startTime = new DateTime($timerSession['start_time']);
    $endTime = new DateTime($now);
    $startTimestamp = $startTime->getTimestamp();
    $endTimestamp = $endTime->getTimestamp();
    $durationSeconds = $endTimestamp - $startTimestamp;
    
    // Ensure positive duration
    if ($durationSeconds < 0) {
        error_log("Timer Stop ERROR: Negative duration calculated. Start: {$timerSession['start_time']}, End: {$now}");
        $durationSeconds = 0;
    }
    
    if ($durationSeconds < 1) {
        error_log("Timer Stop WARNING: Duration is less than 1 second.");
    }
    
    $durationHours = $durationSeconds / 3600;
    
    error_log("Timer Stop - Session ID: {$timerSession['id']}, Start: {$timerSession['start_time']}, End: {$now}, Duration: {$durationSeconds} seconds ({$durationHours} hours)");
    
    // For individual session, store raw duration (regular/overtime calculated at total level)
    // Update timer session to ended status
    $db->execute(
        "UPDATE timer_sessions SET end_time = ?, duration_seconds = ?, status = 'ended', updated_at = NOW() 
        WHERE id = ?",
        [$now, $durationSeconds, $timerSession['id']]
    );
    
    // Calculate total hours for today from all ended sessions (including the current one we're about to end)
    // First, get all previously ended sessions
    $allSessions = $db->fetchAll(
        "SELECT SUM(duration_seconds) as total_seconds
         FROM timer_sessions 
         WHERE company_id = ? AND user_id = ? AND date = ? AND status = 'ended' AND id != ?",
        [$companyId, $userId, $today, $timerSession['id']]
    );
    
    $previousSeconds = intval($allSessions[0]['total_seconds'] ?? 0);
    // Add current session duration to get total
    $totalSeconds = $previousSeconds + $durationSeconds;
    $totalHoursWorked = $totalSeconds / 3600;
    
    // Calculate regular and overtime based on TOTAL hours for the day
    // Get working hours from company settings (office time), fallback to app config
    $workingHoursSetting = Tenant::getCompanySetting('working_hours', null);
    $standardWorkHours = floatval($workingHoursSetting ?? $appConfig['standard_work_hours'] ?? 8);
    
    // Overtime calculation: total_time - office_time = overtime
    // regular_hours = min(total_hours, office_time)
    // overtime_hours = max(0, total_hours - office_time)
    $totalRegular = min($totalHoursWorked, $standardWorkHours);
    $totalOvertime = max(0, $totalHoursWorked - $standardWorkHours);
    
    // Round to 2 decimal places
    $totalRegular = round($totalRegular, 2);
    $totalOvertime = round($totalOvertime, 2);
    
    error_log("Timer Stop - Company ID: {$companyId}, Working hours setting: " . ($workingHoursSetting ?? 'NULL') . ", Standard: {$standardWorkHours}h");
    error_log("Timer Stop - Total hours worked today: {$totalHoursWorked}h, Regular: {$totalRegular}h, Overtime: {$totalOvertime}h");
    
    // Update or create attendance record with totals and mark as present
    $attendance = $db->fetchOne(
        "SELECT * FROM attendance WHERE company_id = ? AND user_id = ? AND date = ? LIMIT 1",
        [$companyId, $userId, $today]
    );
    
    if ($attendance) {
        $db->execute(
            "UPDATE attendance SET regular_hours = ?, overtime_hours = ?, is_present = 1, updated_at = NOW() 
            WHERE id = ?",
            [$totalRegular, $totalOvertime, $attendance['id']]
        );
    } else {
        $db->execute(
            "INSERT INTO attendance (company_id, user_id, date, regular_hours, overtime_hours, is_present, created_at) 
            VALUES (?, ?, ?, ?, ?, 1, NOW())",
            [$companyId, $userId, $today, $totalRegular, $totalOvertime]
        );
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Timer stopped successfully',
        'data' => [
            'session_id' => $timerSession['id'],
            'duration_seconds' => $durationSeconds,
            'duration_hours' => round($durationHours, 2),
            'total_regular' => round($totalRegular, 2),
            'total_overtime' => round($totalOvertime, 2),
            'total_hours' => round($totalHoursWorked, 2)
        ]
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Stop Timer Error: " . $e->getMessage());
    error_log("Stop Timer Error Trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Failed to stop timer: ' . $e->getMessage()]);
}


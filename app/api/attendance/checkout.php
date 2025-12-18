<?php
/**
 * Check-out API Endpoint
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

$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');

// Find current check-in record
$attendance = $db->fetchOne(
    "SELECT * FROM attendance WHERE company_id = ? AND user_id = ? AND date = ? AND status = 'in' ORDER BY check_in DESC LIMIT 1",
    [$companyId, $userId, $today]
);

if (!$attendance) {
    echo json_encode(['success' => false, 'message' => 'No active check-in found']);
    exit;
}

try {
    // Store original check_in time to ensure it's not modified
    $originalCheckIn = $attendance['check_in'];
    
    error_log("Checkout: Original check_in time: {$originalCheckIn}");
    error_log("Checkout: Checkout time: {$now}");
    error_log("Checkout: Attendance ID: {$attendance['id']}");
    
    // Calculate hours from ended timer sessions
    $sessionTotals = $db->fetchOne(
        "SELECT SUM(regular_hours) as total_regular, SUM(overtime_hours) as total_overtime 
         FROM timer_sessions 
         WHERE attendance_id = ? AND status = 'ended'",
        [$attendance['id']]
    );
    
    $regularHours = floatval($sessionTotals['total_regular'] ?? 0);
    $overtimeHours = floatval($sessionTotals['total_overtime'] ?? 0);
    $totalHours = $regularHours + $overtimeHours;
    
    // If no timer sessions, calculate from check-in to check-out (fallback)
    if ($totalHours == 0) {
        $hoursCalculation = AttendanceCalculator::calculateHours($originalCheckIn, $now, $companyId);
        $regularHours = $hoursCalculation['regular_hours'];
        $overtimeHours = $hoursCalculation['overtime_hours'];
        $totalHours = $hoursCalculation['total_hours'];
    }
    
    error_log("Checkout: Calculated hours - Regular: $regularHours, Overtime: $overtimeHours, Total: $totalHours");
    
    // Update attendance record - explicitly preserve check_in time by including it in SET clause
    $db->execute(
        "UPDATE attendance SET check_in = ?, check_out = ?, status = 'out', regular_hours = ?, overtime_hours = ?, updated_at = NOW() 
        WHERE id = ?",
        [$originalCheckIn, $now, $regularHours, $overtimeHours, $attendance['id']]
    );
    
    // Verify check_in was not modified
    $verify = $db->fetchOne("SELECT check_in, check_out FROM attendance WHERE id = ?", [$attendance['id']]);
    error_log("Checkout: After update - check_in: {$verify['check_in']}, check_out: {$verify['check_out']}");
    
    if ($verify['check_in'] != $originalCheckIn) {
        // If check_in was modified, restore it
        error_log("Checkout: WARNING - check_in was modified! Restoring original value.");
        $db->execute(
            "UPDATE attendance SET check_in = ? WHERE id = ?",
            [$originalCheckIn, $attendance['id']]
        );
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Checked out successfully',
        'data' => [
            'check_out_time' => $now,
            'regular_hours' => round($regularHours, 2),
            'overtime_hours' => round($overtimeHours, 2),
            'total_hours' => round($totalHours, 2)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Check-out Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to check out']);
}





<?php
/**
 * Employee Details API
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

Auth::requireRole(['company_owner', 'manager']);

$companyId = Tenant::getCurrentCompanyId();
$employeeId = $_GET['id'] ?? 0;
$db = Database::getInstance();

// Set timezone from config
$appConfig = require __DIR__ . '/../../config/app.php';
date_default_timezone_set($appConfig['timezone']);

// Get employee details
$employee = $db->fetchOne(
    "SELECT u.*, d.name as department_name 
    FROM users u 
    LEFT JOIN departments d ON u.department_id = d.id 
    WHERE u.id = ? AND u.company_id = ?",
    [$employeeId, $companyId]
);

if (!$employee) {
    echo json_encode(['success' => false, 'message' => 'Employee not found']);
    exit;
}

// Get this month's attendance stats
$currentMonth = date('Y-m');
$attendanceStats = $db->fetchOne(
    "SELECT 
        COUNT(DISTINCT date) as days_worked,
        SUM(regular_hours) as regular_hours,
        SUM(overtime_hours) as overtime_hours,
        SUM(regular_hours + overtime_hours) as total_hours
    FROM attendance 
    WHERE company_id = ? AND user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ? AND is_present = 1",
    [$companyId, $employeeId, $currentMonth]
);

// Get current year leave balance - Calculate from allocation minus taken leaves
$currentYear = date('Y');

// Get paid leave allocation from company_settings
$paidLeaveSetting = $db->fetchOne(
    "SELECT setting_value FROM company_settings WHERE company_id = ? AND setting_key = 'paid_leave_allocation' LIMIT 1",
    [$companyId]
);
$paidLeaveAllocation = $paidLeaveSetting ? floatval($paidLeaveSetting['setting_value']) : 12.0;

// Calculate total approved leaves taken for this year
$takenLeave = $db->fetchOne(
    "SELECT COALESCE(SUM(days_count), 0) as total_days
     FROM leaves 
     WHERE company_id = ? AND user_id = ? 
     AND status = 'approved'
     AND leave_type = 'paid_leave'
     AND YEAR(start_date) = ?",
    [$companyId, $employeeId, $currentYear]
);
$takenLeaveDays = floatval($takenLeave['total_days'] ?? 0);

// Calculate remaining balance
$remainingBalance = max(0, $paidLeaveAllocation - $takenLeaveDays);

// Helper function to convert decimal hours to HH:MM:SS format
function formatHoursToTime($decimalHours) {
    if ($decimalHours <= 0) {
        return '00:00:00';
    }
    $totalSeconds = round($decimalHours * 3600);
    $hours = floor($totalSeconds / 3600);
    $minutes = floor(($totalSeconds % 3600) / 60);
    $seconds = $totalSeconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

// Format attendance stats
if ($attendanceStats) {
    $regularHours = floatval($attendanceStats['regular_hours'] ?? 0);
    $overtimeHours = floatval($attendanceStats['overtime_hours'] ?? 0);
    $totalHours = floatval($attendanceStats['total_hours'] ?? 0);
    
    $employee['attendance_stats'] = [
        'days_worked' => (int) $attendanceStats['days_worked'],
        'regular_hours' => formatHoursToTime($regularHours),
        'overtime_hours' => formatHoursToTime($overtimeHours),
        'total_hours' => formatHoursToTime($totalHours)
    ];
}

// Add leave balance (calculated value, not from database)
$employee['leave_balance'] = [
    'paid_leave' => $remainingBalance,
    'year' => $currentYear
];

// Remove password from response
unset($employee['password']);

echo json_encode([
    'success' => true,
    'data' => $employee
]);



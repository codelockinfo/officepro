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

try {
    $companyId = Tenant::getCurrentCompanyId();
    $employeeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $db = Database::getInstance();
    
    // Validate employee ID
    if ($employeeId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
        exit;
    }
    
    // Debug logging
    error_log("Employee Details API - Request for Employee ID: $employeeId, Company ID: $companyId");

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
} catch (Exception $e) {
    error_log("Employee Details API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load employee details. Please try again.']);
    exit;
}

try {
    // Get attendance stats for specified month/year or current month
    $statsMonth = isset($_GET['month']) ? (int) $_GET['month'] : date('n');
    $statsYear = isset($_GET['year']) ? (int) $_GET['year'] : date('Y');
    
    // Validate month and year
    if ($statsMonth < 1 || $statsMonth > 12) {
        $statsMonth = date('n');
    }
    if ($statsYear < 2020 || $statsYear > 2100) {
        $statsYear = date('Y');
    }
    
    $monthYear = sprintf('%04d-%02d', $statsYear, $statsMonth);
    
    $attendanceStats = $db->fetchOne(
        "SELECT 
            COUNT(DISTINCT date) as days_worked,
            SUM(regular_hours) as regular_hours,
            SUM(overtime_hours) as overtime_hours,
            SUM(regular_hours + overtime_hours) as total_hours
        FROM attendance 
        WHERE company_id = ? AND user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ? AND is_present = 1",
        [$companyId, $employeeId, $monthYear]
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
} catch (Exception $e) {
    error_log("Employee Details API - Stats Error: " . $e->getMessage());
    // Continue with empty stats if there's an error
    $attendanceStats = null;
    $currentYear = date('Y');
    $remainingBalance = 0;
}

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

// Calendar data fetching removed - not needed anymore

// Remove password from response
unset($employee['password']);

// Final debug logging
error_log("Employee Details API - Final Response Summary:");
error_log("  - Employee ID: " . ($employee['id'] ?? 'N/A'));
error_log("  - Has attendance_stats: " . (isset($employee['attendance_stats']) ? 'YES' : 'NO'));
error_log("  - Has leave_balance: " . (isset($employee['leave_balance']) ? 'YES' : 'NO'));
error_log("  - Has calendar_data: " . (isset($employee['calendar_data']) ? 'YES' : 'NO'));
if (isset($employee['calendar_data'])) {
    $cal = $employee['calendar_data'];
    error_log("  - Calendar data counts - Attendance: " . count($cal['attendance'] ?? []) . 
              ", Leaves: " . count($cal['leaves'] ?? []) . 
              ", Holidays: " . count($cal['holidays'] ?? []) . 
              ", Overtime: " . count($cal['overtime'] ?? []));
}

try {
    $response = [
        'success' => true,
        'data' => $employee
    ];
    echo json_encode($response);
} catch (Exception $e) {
    error_log("Employee Details API - JSON Encode Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to format response. Please try again.']);
}



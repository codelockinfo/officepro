<?php
/**
 * Attendance Report Data API
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Tenant.php';

// Check authentication and authorization
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

Auth::requireRole(['company_owner', 'manager']);

$companyId = Tenant::getCurrentCompanyId();
$db = Database::getInstance();

// Set timezone from config
$appConfig = require __DIR__ . '/../../config/app.php';
date_default_timezone_set($appConfig['timezone']);

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$employeeId = $_GET['employee_id'] ?? '';

if (!$startDate || !$endDate) {
    echo json_encode(['success' => false, 'message' => 'Start and end dates are required']);
    exit;
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

// Build query - get first session start (created_at) and last session end (end_time or updated_at) from timer_sessions
$sql = "SELECT a.*, u.full_name as employee_name, u.email as employee_email,
        MIN(ts.created_at) as first_session_start,
        MAX(COALESCE(ts.end_time, ts.updated_at)) as last_session_end
        FROM attendance a 
        JOIN users u ON a.user_id = u.id 
        LEFT JOIN timer_sessions ts ON ts.company_id = a.company_id 
            AND ts.user_id = a.user_id 
            AND ts.date = a.date 
            AND ts.status = 'ended'
        WHERE a.company_id = ? AND a.date BETWEEN ? AND ? AND a.is_present = 1";
$params = [$companyId, $startDate, $endDate];

if ($employeeId) {
    $sql .= " AND a.user_id = ?";
    $params[] = $employeeId;
}

$sql .= " GROUP BY a.id
          ORDER BY a.date DESC, u.full_name ASC";

try {
    $data = $db->fetchAll($sql, $params);
    
    // Calculate summary statistics
    $totalRegularHours = 0;
    $totalOvertimeHours = 0;
    $totalAttendanceDays = 0;
    
    // Format times from timer sessions and convert hours to HH:MM:SS
    foreach ($data as &$row) {
        $row['check_in'] = $row['first_session_start'] ? date('h:i A', strtotime($row['first_session_start'])) : '-';
        $row['check_out'] = $row['last_session_end'] ? date('h:i A', strtotime($row['last_session_end'])) : '-';
        
        // Convert hours to HH:MM:SS format
        $row['regular_hours_formatted'] = formatHoursToTime($row['regular_hours'] ?? 0);
        $row['overtime_hours_formatted'] = formatHoursToTime($row['overtime_hours'] ?? 0);
        $totalHours = ($row['regular_hours'] ?? 0) + ($row['overtime_hours'] ?? 0);
        $row['total_hours_formatted'] = formatHoursToTime($totalHours);
        
        // Accumulate totals
        $totalRegularHours += ($row['regular_hours'] ?? 0);
        $totalOvertimeHours += ($row['overtime_hours'] ?? 0);
        $totalAttendanceDays++;
    }
    
    // Calculate total hours
    $totalHours = $totalRegularHours + $totalOvertimeHours;
    
    // Calculate total leave days for the selected period
    $leaveParams = [$companyId, $startDate, $endDate];
    $leaveSql = "SELECT COALESCE(SUM(days_count), 0) as total_days
                 FROM leaves 
                 WHERE company_id = ? 
                 AND status = 'approved'
                 AND (
                     (start_date BETWEEN ? AND ?) 
                     OR (end_date BETWEEN ? AND ?)
                     OR (start_date <= ? AND end_date >= ?)
                 )";
    $leaveParams = [$companyId, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate];
    
    if ($employeeId) {
        $leaveSql .= " AND user_id = ?";
        $leaveParams[] = $employeeId;
    }
    
    $totalLeaveResult = $db->fetchOne($leaveSql, $leaveParams);
    $totalLeaveDays = floatval($totalLeaveResult['total_days'] ?? 0);
    
    // Prepare summary statistics
    $summary = [
        'total_hours' => $totalHours,
        'total_hours_formatted' => formatHoursToTime($totalHours),
        'total_overtime' => $totalOvertimeHours,
        'total_overtime_formatted' => formatHoursToTime($totalOvertimeHours),
        'total_leave_days' => $totalLeaveDays,
        'total_attendance' => $totalAttendanceDays
    ];
    
    echo json_encode([
        'success' => true, 
        'data' => $data,
        'summary' => $summary
    ]);
} catch (Exception $e) {
    error_log("Report Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to generate report']);
}





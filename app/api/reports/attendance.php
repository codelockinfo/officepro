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

$startDate  = $_GET['start_date'] ?? '';
$endDate    = $_GET['end_date'] ?? '';
$employeeId = $_GET['employee_id'] ?? '';

if (!$startDate || !$endDate) {
    echo json_encode(['success' => false, 'message' => 'Start and end dates are required']);
    exit;
}

/**
 * Convert decimal hours to HH:MM:SS
 */
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

/**
 * Convert seconds to HH:MM:SS (for lunch)
 */
function secondsToTime($seconds) {
    if ($seconds <= 0) {
        return '00:00:00';
    }
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

// -------------------------------------------------
// Attendance + Timer Sessions
// -------------------------------------------------
$sql = "SELECT a.*, 
               u.full_name AS employee_name, 
               u.email AS employee_email,
               MIN(ts.created_at) AS first_session_start,
               MAX(COALESCE(ts.end_time, ts.updated_at)) AS last_session_end
        FROM attendance a 
        JOIN users u ON a.user_id = u.id 
        LEFT JOIN timer_sessions ts 
            ON ts.company_id = a.company_id 
           AND ts.user_id = a.user_id 
           AND ts.date = a.date 
           AND ts.status = 'ended'
        WHERE a.company_id = ? 
          AND a.date BETWEEN ? AND ? 
          AND a.is_present = 1";

$params = [$companyId, $startDate, $endDate];

if ($employeeId) {
    $sql .= " AND a.user_id = ?";
    $params[] = $employeeId;
}

$sql .= " GROUP BY a.id
          ORDER BY a.date DESC, u.full_name ASC";

try {
    $data = $db->fetchAll($sql, $params);

    // -------------------------------------------------
    // Fetch Lunch Breaks (SUM per date)
    // -------------------------------------------------
    $lunchSql = "SELECT date, SUM(duration_seconds) AS total_lunch_seconds
                 FROM lunch_breaks
                 WHERE company_id = ?
                   AND date BETWEEN ? AND ?
                   AND status = 'ended'";

    $lunchParams = [$companyId, $startDate, $endDate];

    if ($employeeId) {
        $lunchSql .= " AND user_id = ?";
        $lunchParams[] = $employeeId;
    }

    $lunchSql .= " GROUP BY date";

    $lunchRows = $db->fetchAll($lunchSql, $lunchParams);

    // Map lunch time: date => seconds
    $lunchMap = [];
    foreach ($lunchRows as $lr) {
        $lunchMap[$lr['date']] = (int)$lr['total_lunch_seconds'];
    }

    // -------------------------------------------------
    // Summary calculations
    // -------------------------------------------------
    $totalRegularHours  = 0;
    $totalOvertimeHours = 0;
    $totalAttendanceDays = 0;

    foreach ($data as &$row) {

        // Check-in / Check-out
        $row['check_in'] = $row['first_session_start']
            ? date('h:i A', strtotime($row['first_session_start']))
            : '-';

        $row['check_out'] = $row['last_session_end']
            ? date('h:i A', strtotime($row['last_session_end']))
            : '-';

        // Lunch Time
        $lunchSeconds = $lunchMap[$row['date']] ?? 0;
        $row['lunch_time'] = secondsToTime($lunchSeconds);

        // Hours formatting
        $row['regular_hours_formatted']  = formatHoursToTime($row['regular_hours'] ?? 0);
        $row['overtime_hours_formatted'] = formatHoursToTime($row['overtime_hours'] ?? 0);

        $totalHours = ($row['regular_hours'] ?? 0) + ($row['overtime_hours'] ?? 0);
        $row['total_hours_formatted'] = formatHoursToTime($totalHours);

        // Totals
        $totalRegularHours  += ($row['regular_hours'] ?? 0);
        $totalOvertimeHours += ($row['overtime_hours'] ?? 0);
        $totalAttendanceDays++;
    }

    // -------------------------------------------------
    // Leave calculation
    // -------------------------------------------------
    $leaveSql = "SELECT COALESCE(SUM(days_count), 0) AS total_days
                 FROM leaves 
                 WHERE company_id = ?
                   AND status = 'approved'
                   AND (
                        (start_date BETWEEN ? AND ?) 
                     OR (end_date BETWEEN ? AND ?)
                     OR (start_date <= ? AND end_date >= ?)
                   )";

    $leaveParams = [
        $companyId,
        $startDate, $endDate,
        $startDate, $endDate,
        $startDate, $endDate
    ];

    if ($employeeId) {
        $leaveSql .= " AND user_id = ?";
        $leaveParams[] = $employeeId;
    }

    $leaveResult = $db->fetchOne($leaveSql, $leaveParams);
    $totalLeaveDays = floatval($leaveResult['total_days'] ?? 0);

    // -------------------------------------------------
    // Summary
    // -------------------------------------------------
    $summary = [
        'total_hours' => $totalRegularHours + $totalOvertimeHours,
        'total_hours_formatted' => formatHoursToTime($totalRegularHours + $totalOvertimeHours),
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
    exit;

} catch (Exception $e) {
    error_log("Report Error: " . $e->getMessage());
    error_log($e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate report'
    ]);
    exit;
}

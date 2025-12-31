<?php
/**
 * Export Attendance Report (CSV/PDF)
 */

session_start();

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Tenant.php';
require_once __DIR__ . '/../../helpers/PDF.php';

// Check authentication
if (!Auth::isLoggedIn()) {
    die('Unauthorized');
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
$format     = $_GET['format'] ?? 'csv';

if (!$startDate || !$endDate) {
    die('Start and end dates are required');
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
 * Convert seconds to HH:MM:SS (Lunch)
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
$sql = "SELECT a.*, u.full_name AS employee_name,
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

$data = $db->fetchAll($sql, $params);

// -------------------------------------------------
// Fetch Lunch Breaks
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

// Map lunch: date => seconds
$lunchMap = [];
foreach ($lunchRows as $lr) {
    $lunchMap[$lr['date']] = (int)$lr['total_lunch_seconds'];
}

/* =====================================================
   CSV EXPORT
   ===================================================== */
if ($format === 'csv') {

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . $startDate . '_' . $endDate . '.csv"');

    $output = fopen('php://output', 'w');

    // Headers
    fputcsv($output, [
        'Employee',
        'Date',
        'Check In',
        'Check Out',
        'Lunch Time',
        'Regular Hours',
        'Overtime Hours',
        'Total Hours'
    ]);

    foreach ($data as $row) {
        $totalHours = ($row['regular_hours'] ?? 0) + ($row['overtime_hours'] ?? 0);
        $lunchSeconds = $lunchMap[$row['date']] ?? 0;

        fputcsv($output, [
            $row['employee_name'],
            $row['date'],
            $row['first_session_start'] ? date('h:i A', strtotime($row['first_session_start'])) : '-',
            $row['last_session_end'] ? date('h:i A', strtotime($row['last_session_end'])) : '-',
            secondsToTime($lunchSeconds),
            formatHoursToTime($row['regular_hours'] ?? 0),
            formatHoursToTime($row['overtime_hours'] ?? 0),
            formatHoursToTime($totalHours)
        ]);
    }

    fclose($output);
    exit;
}

/* =====================================================
   PDF EXPORT
   ===================================================== */
$company = $db->fetchOne("SELECT company_name, logo FROM companies WHERE id = ?", [$companyId]);
$companyName = $company['company_name'] ?? 'Company';
$companyLogo = $company['logo'] ?? null;

$reportData = [];
foreach ($data as $row) {
    $totalHours = ($row['regular_hours'] ?? 0) + ($row['overtime_hours'] ?? 0);
    $lunchSeconds = $lunchMap[$row['date']] ?? 0;

    $reportData[] = [
        'employee_name' => $row['employee_name'],
        'date' => $row['date'],
        'check_in' => $row['first_session_start'] ? date('h:i A', strtotime($row['first_session_start'])) : '-',
        'check_out' => $row['last_session_end'] ? date('h:i A', strtotime($row['last_session_end'])) : '-',
        'lunch_time' => secondsToTime($lunchSeconds),
        'regular_hours' => formatHoursToTime($row['regular_hours'] ?? 0),
        'overtime_hours' => formatHoursToTime($row['overtime_hours'] ?? 0),
        'total_hours' => formatHoursToTime($totalHours)
    ];
}

PDF::generateAttendanceReport(
    $companyName,
    $companyLogo,
    $reportData,
    $startDate,
    $endDate
);

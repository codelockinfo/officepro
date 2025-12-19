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

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$employeeId = $_GET['employee_id'] ?? '';
$format = $_GET['format'] ?? 'csv';

if (!$startDate || !$endDate) {
    die('Start and end dates are required');
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
$sql = "SELECT a.*, u.full_name as employee_name,
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

$data = $db->fetchAll($sql, $params);

if ($format === 'csv') {
    // CSV Export
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . $startDate . '_' . $endDate . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, ['Employee', 'Date', 'Check In', 'Check Out', 'Regular Hours', 'Overtime Hours', 'Total Hours']);
    
    // Data
    foreach ($data as $row) {
        $totalHours = ($row['regular_hours'] ?? 0) + ($row['overtime_hours'] ?? 0);
        fputcsv($output, [
            $row['employee_name'],
            $row['date'],
            $row['first_session_start'] ? date('h:i A', strtotime($row['first_session_start'])) : '-',
            $row['last_session_end'] ? date('h:i A', strtotime($row['last_session_end'])) : '-',
            formatHoursToTime($row['regular_hours'] ?? 0),
            formatHoursToTime($row['overtime_hours'] ?? 0),
            formatHoursToTime($totalHours)
        ]);
    }
    
    fclose($output);
} else {
    // PDF Export
    $company = $db->fetchOne("SELECT company_name, logo FROM companies WHERE id = ?", [$companyId]);
    $companyName = $company['company_name'] ?? 'Company';
    $companyLogo = $company['logo'] ?? null;
    
    // Format data for PDF
    $reportData = [];
    foreach ($data as $row) {
        $totalHours = ($row['regular_hours'] ?? 0) + ($row['overtime_hours'] ?? 0);
        $reportData[] = [
            'employee_name' => $row['employee_name'],
            'date' => $row['date'],
            'check_in' => $row['first_session_start'] ? date('h:i A', strtotime($row['first_session_start'])) : '-',
            'check_out' => $row['last_session_end'] ? date('h:i A', strtotime($row['last_session_end'])) : '-',
            'regular_hours' => formatHoursToTime($row['regular_hours'] ?? 0),
            'overtime_hours' => formatHoursToTime($row['overtime_hours'] ?? 0),
            'total_hours' => formatHoursToTime($totalHours)
        ];
    }
    
    PDF::generateAttendanceReport($companyName, $companyLogo, $reportData, $startDate, $endDate);
}





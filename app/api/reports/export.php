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

// Build query
$sql = "SELECT a.*, u.full_name as employee_name 
        FROM attendance a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.company_id = ? AND a.date BETWEEN ? AND ? AND a.status = 'out'";
$params = [$companyId, $startDate, $endDate];

if ($employeeId) {
    $sql .= " AND a.user_id = ?";
    $params[] = $employeeId;
}

$sql .= " ORDER BY a.date DESC, u.full_name ASC";

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
        fputcsv($output, [
            $row['employee_name'],
            $row['date'],
            date('h:i A', strtotime($row['check_in'])),
            $row['check_out'] ? date('h:i A', strtotime($row['check_out'])) : '-',
            number_format($row['regular_hours'], 2),
            number_format($row['overtime_hours'], 2),
            number_format($row['regular_hours'] + $row['overtime_hours'], 2)
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
        $reportData[] = [
            'employee_name' => $row['employee_name'],
            'date' => $row['date'],
            'check_in' => date('h:i A', strtotime($row['check_in'])),
            'check_out' => $row['check_out'] ? date('h:i A', strtotime($row['check_out'])) : '-',
            'regular_hours' => number_format($row['regular_hours'], 2),
            'overtime_hours' => number_format($row['overtime_hours'], 2)
        ];
    }
    
    PDF::generateAttendanceReport($companyName, $companyLogo, $reportData, $startDate, $endDate);
}





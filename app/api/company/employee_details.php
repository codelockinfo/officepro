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
    WHERE company_id = ? AND user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ? AND status = 'out'",
    [$companyId, $employeeId, $currentMonth]
);

// Get current year leave balance
$currentYear = date('Y');
$leaveBalance = $db->fetchOne(
    "SELECT * FROM leave_balances 
    WHERE company_id = ? AND user_id = ? AND year = ?",
    [$companyId, $employeeId, $currentYear]
);

// Format attendance stats
if ($attendanceStats) {
    $employee['attendance_stats'] = [
        'days_worked' => (int) $attendanceStats['days_worked'],
        'regular_hours' => number_format($attendanceStats['regular_hours'] ?? 0, 1),
        'overtime_hours' => number_format($attendanceStats['overtime_hours'] ?? 0, 1),
        'total_hours' => number_format($attendanceStats['total_hours'] ?? 0, 1)
    ];
}

// Add leave balance
if ($leaveBalance) {
    $employee['leave_balance'] = $leaveBalance;
}

// Remove password from response
unset($employee['password']);

echo json_encode([
    'success' => true,
    'data' => $employee
]);



<?php
/**
 * Calendar Employee List API
 * Returns filtered list of employees based on type (attendance/leave/overtime) for a specific date
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

// Only company owners can access
Auth::requireRole(['company_owner']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$companyId = Tenant::getCurrentCompanyId();
$db = Database::getInstance();

// Set timezone from config
$appConfig = require __DIR__ . '/../../config/app.php';
date_default_timezone_set($appConfig['timezone']);

$type = $_GET['type'] ?? '';
$date = $_GET['date'] ?? date('Y-m-d');

// Validate type
if (!in_array($type, ['attendance', 'leave', 'overtime'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid filter type']);
    exit;
}

try {
    $employees = [];
    
    if ($type === 'attendance') {
        // Get employees who were present (checked in or checked out) on this date
        $employees = $db->fetchAll(
            "SELECT DISTINCT u.id, u.full_name, u.profile_image, 
                    MIN(a.check_in) as check_in_time
             FROM attendance a
             JOIN users u ON a.user_id = u.id
             WHERE a.company_id = ? 
             AND a.date = ? 
             AND u.status = 'active'
             AND u.id NOT IN (
                 SELECT DISTINCT l.user_id 
                 FROM leaves l
                 WHERE l.company_id = ? 
                 AND l.status = 'approved' 
                 AND ? BETWEEN l.start_date AND l.end_date
             )
             GROUP BY u.id, u.full_name, u.profile_image
             ORDER BY u.full_name ASC",
            [$companyId, $date, $companyId, $date]
        );
        
        // Format check-in time
        foreach ($employees as &$emp) {
            if ($emp['check_in_time']) {
                $checkInTime = DateTime::createFromFormat('Y-m-d H:i:s', $emp['check_in_time']);
                if ($checkInTime) {
                    $emp['check_in_time'] = $checkInTime->format('h:i A');
                }
            }
        }
        
    } elseif ($type === 'leave') {
        // Get employees on approved leave for this date
        $employees = $db->fetchAll(
            "SELECT DISTINCT u.id, u.full_name, u.profile_image,
                    l.leave_type, l.days_count, l.start_date, l.end_date
             FROM leaves l
             JOIN users u ON l.user_id = u.id
             WHERE l.company_id = ? 
             AND l.status = 'approved' 
             AND ? BETWEEN l.start_date AND l.end_date
             AND u.status = 'active'
             ORDER BY u.full_name ASC",
            [$companyId, $date]
        );
        
        // Format leave type
        foreach ($employees as &$emp) {
            $leaveTypeMap = [
                'paid_leave' => 'Paid Leave'
            ];
            $emp['leave_type'] = $leaveTypeMap[$emp['leave_type']] ?? $emp['leave_type'];
        }
        
    } elseif ($type === 'overtime') {
        // Get employees who worked overtime on this date
        $employees = $db->fetchAll(
            "SELECT DISTINCT u.id, u.full_name, u.profile_image,
                    SUM(a.overtime_hours) as overtime_hours
             FROM attendance a
             JOIN users u ON a.user_id = u.id
             WHERE a.company_id = ? 
             AND a.date = ? 
             AND a.status = 'out'
             AND a.overtime_hours > 0
             AND u.status = 'active'
             GROUP BY u.id, u.full_name, u.profile_image
             HAVING SUM(a.overtime_hours) > 0
             ORDER BY SUM(a.overtime_hours) DESC, u.full_name ASC",
            [$companyId, $date]
        );
        
        // Format overtime hours
        foreach ($employees as &$emp) {
            $emp['overtime_hours'] = number_format($emp['overtime_hours'], 2);
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $employees
    ]);
    
} catch (Exception $e) {
    error_log("Calendar Employee List Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch employee list']);
}


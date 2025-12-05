<?php
/**
 * Check-out API Endpoint
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$companyId = Tenant::getCurrentCompanyId();
$userId = Auth::getCurrentUser()['id'];
$db = Database::getInstance();

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
    // Calculate hours worked
    $checkInTime = new DateTime($attendance['check_in']);
    $checkOutTime = new DateTime($now);
    $interval = $checkInTime->diff($checkOutTime);
    
    $totalHours = $interval->h + ($interval->i / 60) + ($interval->s / 3600);
    if ($interval->days > 0) {
        $totalHours += $interval->days * 24;
    }
    
    // Get standard work hours from config
    $appConfig = require __DIR__ . '/../../config/app.php';
    $standardWorkHours = $appConfig['standard_work_hours'];
    
    // Calculate regular and overtime hours
    $regularHours = min($totalHours, $standardWorkHours);
    $overtimeHours = max(0, $totalHours - $standardWorkHours);
    
    // Update attendance record
    $db->execute(
        "UPDATE attendance SET check_out = ?, status = 'out', regular_hours = ?, overtime_hours = ?, updated_at = NOW() 
        WHERE id = ?",
        [$now, $regularHours, $overtimeHours, $attendance['id']]
    );
    
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



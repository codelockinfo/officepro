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

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$employeeId = $_GET['employee_id'] ?? '';

if (!$startDate || !$endDate) {
    echo json_encode(['success' => false, 'message' => 'Start and end dates are required']);
    exit;
}

// Build query
$sql = "SELECT a.*, u.full_name as employee_name, u.email as employee_email 
        FROM attendance a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.company_id = ? AND a.date BETWEEN ? AND ? AND a.status = 'out'";
$params = [$companyId, $startDate, $endDate];

if ($employeeId) {
    $sql .= " AND a.user_id = ?";
    $params[] = $employeeId;
}

$sql .= " ORDER BY a.date DESC, u.full_name ASC";

try {
    $data = $db->fetchAll($sql, $params);
    
    // Format times
    foreach ($data as &$row) {
        $row['check_in'] = date('h:i A', strtotime($row['check_in']));
        if ($row['check_out']) {
            $row['check_out'] = date('h:i A', strtotime($row['check_out']));
        }
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    error_log("Report Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to generate report']);
}



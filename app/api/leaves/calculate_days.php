<?php
/**
 * Calculate Leave Days API - Returns count excluding Sundays and holidays
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

$companyId = Tenant::getCurrentCompanyId();
$db = Database::getInstance();

// Get parameters
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$leaveDuration = $_GET['leave_duration'] ?? 'full_day';

if (empty($startDate)) {
    echo json_encode(['success' => false, 'message' => 'Start date is required']);
    exit;
}

// For half day, return 0.5
if ($leaveDuration === 'half_day') {
    echo json_encode(['success' => true, 'days_count' => 0.5]);
    exit;
}

if (empty($endDate)) {
    echo json_encode(['success' => false, 'message' => 'End date is required']);
    exit;
}

// Get holidays in the date range to exclude them
$holidays = $db->fetchAll(
    "SELECT date FROM holidays 
    WHERE company_id = ? AND date BETWEEN ? AND ?",
    [$companyId, $startDate, $endDate]
);

// Create lookup array for holiday dates
$holidayDates = [];
foreach ($holidays as $holiday) {
    $holidayDates[$holiday['date']] = true;
}

// Count all days except Sunday (Sunday=0) and holidays
$start = new DateTime($startDate);
$end = new DateTime($endDate);
$end->modify('+1 day'); // Include end date in iteration

$daysCount = 0;
$current = clone $start;

while ($current < $end) {
    $dayOfWeek = (int)$current->format('w'); // 0 = Sunday, 1-6 = Monday-Saturday
    $dateStr = $current->format('Y-m-d');
    
    // Count all days except Sunday (0) and holidays
    if ($dayOfWeek !== 0 && !isset($holidayDates[$dateStr])) {
        $daysCount++;
    }
    $current->modify('+1 day');
}

echo json_encode(['success' => true, 'days_count' => $daysCount]);


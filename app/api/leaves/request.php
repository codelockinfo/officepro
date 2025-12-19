<?php
/**
 * Leave Request API Endpoint
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Tenant.php';
require_once __DIR__ . '/../../helpers/Validator.php';

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
$validator = new Validator();

// Set timezone from config
$appConfig = require __DIR__ . '/../../config/app.php';
date_default_timezone_set($appConfig['timezone']);

// Get form data
$leaveType = $_POST['leave_type'] ?? 'paid_leave'; // Default to paid_leave if not provided
$leaveDuration = $_POST['leave_duration'] ?? 'full_day';
$halfDayPeriod = $_POST['half_day_period'] ?? '';
$startDate = $_POST['start_date'] ?? '';
$endDate = $_POST['end_date'] ?? '';
$reason = $validator->sanitize($_POST['reason'] ?? '');

// Validate inputs
$validator->required($startDate, 'Start Date');
$validator->required($reason, 'Reason');
$validator->date($startDate, 'Start Date');

// Validate leave duration
if (!in_array($leaveDuration, ['full_day', 'half_day'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid leave duration']);
    exit;
}

// For half day, end date should be same as start date
if ($leaveDuration === 'half_day') {
    $endDate = $startDate;
    if (empty($halfDayPeriod) || !in_array($halfDayPeriod, ['morning', 'afternoon'])) {
        echo json_encode(['success' => false, 'message' => 'Half day period is required']);
        exit;
    }
} else {
    $validator->required($endDate, 'End Date');
    $validator->date($endDate, 'End Date');
    $validator->dateRange($startDate, $endDate, 'Date Range');
}

if ($leaveType !== 'paid_leave') {
    echo json_encode(['success' => false, 'message' => 'Invalid leave type']);
    exit;
}

if ($validator->hasErrors()) {
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->getErrors()]);
    exit;
}

// Calculate days (excluding Sunday and holidays, Saturday is counted)
if ($leaveDuration === 'half_day') {
    $daysCount = 0.5;
} else {
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
}

// Check leave balance
$currentYear = date('Y');
$balance = $db->fetchOne(
    "SELECT * FROM leave_balances WHERE company_id = ? AND user_id = ? AND year = ?",
    [$companyId, $userId, $currentYear]
);

if (!$balance) {
    echo json_encode(['success' => false, 'message' => 'Leave balance not found']);
    exit;
}

// Get available paid leave balance
$availableBalance = $balance['paid_leave'] ?? 0;

// For half days, check if at least 0.5 days are available
if ($daysCount > $availableBalance) {
    echo json_encode(['success' => false, 'message' => "Insufficient leave balance. You have {$availableBalance} days available."]);
    exit;
}

// Handle file upload
$attachment = null;
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    if ($validator->document($_FILES['attachment'], 'Attachment')) {
        $filename = Validator::uploadFile($_FILES['attachment'], 'uploads/documents', 'leave_');
        if ($filename) {
            $attachment = 'uploads/documents/' . $filename;
        }
    }
}

try {
    $db->beginTransaction();
    
    // Insert leave request
    $db->execute(
        "INSERT INTO leaves (company_id, user_id, leave_type, leave_duration, half_day_period, start_date, end_date, days_count, reason, attachment, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())",
        [$companyId, $userId, $leaveType, $leaveDuration, $leaveDuration === 'half_day' ? $halfDayPeriod : null, $startDate, $endDate, $daysCount, $reason, $attachment]
    );
    
    $leaveId = $db->lastInsertId();
    
    // Create notification for managers
    $managers = $db->fetchAll(
        "SELECT id FROM users WHERE company_id = ? AND role IN ('company_owner', 'manager') AND status = 'active'",
        [$companyId]
    );
    
    $currentUser = Auth::getCurrentUser();
    $message = "{$currentUser['full_name']} requested {$leaveType} from {$startDate} to {$endDate}";
    
    foreach ($managers as $manager) {
        $db->execute(
            "INSERT INTO notifications (company_id, user_id, type, message, link, created_at) 
            VALUES (?, ?, 'leave_request', ?, '/officepro/app/views/leave_approvals.php', NOW())",
            [$companyId, $manager['id'], $message]
        );
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Leave request submitted successfully',
        'data' => ['leave_id' => $leaveId]
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Leave Request Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to submit leave request']);
}





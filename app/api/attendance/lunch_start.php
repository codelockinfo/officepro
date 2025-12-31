<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Tenant.php';

if (!Auth::isLoggedIn()) {
    echo json_encode(['success'=>false,'message'=>'Session expired. Please login again.']);
    exit;
}

$currentUser = Auth::getCurrentUser();
$userId = $currentUser['id'];
$companyId = Tenant::getCurrentCompanyId();
$today = date('Y-m-d');

$db = Database::getInstance();

/* ❌ Block if work timer running */
$timer = $db->fetchOne(
    "SELECT id FROM timer_sessions 
     WHERE company_id=? AND user_id=? AND date=? AND status='running'",
    [$companyId, $userId, $today]
);

if ($timer) {
    echo json_encode(['success'=>false,'message'=>'Stop work timer before lunch']);
    exit;
}

/* ❌ Block duplicate lunch */
$lunch = $db->fetchOne(
    "SELECT id FROM lunch_breaks 
     WHERE company_id=? AND user_id=? AND date=? AND status='taken'",
    [$companyId, $userId, $today]
);

if ($lunch) {
    echo json_encode(['success'=>false,'message'=>'Lunch already running']);
    exit;
}

/* ✅ Start lunch */
$db->query(
    "INSERT INTO lunch_breaks 
     (company_id, user_id, date, lunch_start, status)
     VALUES (?, ?, ?, ?, ?)",
    [
        $companyId,
        $userId,
        $today,
        date('Y-m-d H:i:s'),
        'taken'
    ]
);


echo json_encode(['success'=>true]);

<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/init.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Tenant.php';

if (!Auth::isLoggedIn()) {
    echo json_encode(['success'=>false,'message'=>'Session expired']);
    exit;
}

$currentUser = Auth::getCurrentUser();
$userId = $currentUser['id'];
$companyId = Tenant::getCurrentCompanyId();
$today = date('Y-m-d');

$db = Database::getInstance();

/* ✅ Get active lunch */
$lunch = $db->fetchOne(
    "SELECT * FROM lunch_breaks 
     WHERE company_id = ? 
     AND user_id = ? 
     AND date = ? 
     AND status = 'taken'
     ORDER BY lunch_start DESC 
     LIMIT 1",
    [$companyId, $userId, $today]
);

if (!$lunch) {
    echo json_encode(['success'=>false,'message'=>'No active lunch found']);
    exit;
}

/* ✅ End lunch */
$lunchEnd = date('Y-m-d H:i:s');
$duration = strtotime($lunchEnd) - strtotime($lunch['lunch_start']);

$db->query(
    "UPDATE lunch_breaks 
     SET lunch_end = ?, 
         duration_seconds = ?, 
         status = 'ended' 
     WHERE id = ?",
    [$lunchEnd, $duration, $lunch['id']]
);

echo json_encode(['success'=>true]);

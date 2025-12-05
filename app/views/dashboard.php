<?php
/**
 * Employee Dashboard - Main View with Attendance Check-in/out
 */
session_start();

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';

require_once __DIR__ . '/../helpers/Database.php';
require_once __DIR__ . '/../helpers/Tenant.php';

$companyId = Tenant::getCurrentCompanyId();
$userId = $currentUser['id'];
$db = Database::getInstance();

// Get today's attendance status
$today = date('Y-m-d');
$currentAttendance = $db->fetchOne(
    "SELECT * FROM attendance WHERE company_id = ? AND user_id = ? AND date = ? AND status = 'in' ORDER BY check_in DESC LIMIT 1",
    [$companyId, $userId, $today]
);

// Get today's attendance history
$todayHistory = $db->fetchAll(
    "SELECT * FROM attendance WHERE company_id = ? AND user_id = ? AND date = ? ORDER BY check_in DESC",
    [$companyId, $userId, $today]
);

// Calculate today's totals
$totalRegularHours = 0;
$totalOvertimeHours = 0;
foreach ($todayHistory as $record) {
    $totalRegularHours += $record['regular_hours'];
    $totalOvertimeHours += $record['overtime_hours'];
}
?>

<h1>Welcome, <?php echo htmlspecialchars($currentUser['full_name']); ?>! 👋</h1>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 30px;">
    <!-- Attendance Card -->
    <div class="card">
        <h2 class="card-title">Today's Attendance</h2>
        
        <?php if ($currentAttendance): ?>
            <div style="text-align: center; padding: 20px;">
                <div style="font-size: 48px; color: var(--success-green); margin-bottom: 20px;">✓</div>
                <p style="font-size: 18px; font-weight: 600; color: var(--success-green); margin-bottom: 10px;">Checked In</p>
                <p>Check-in time: <?php echo date('h:i A', strtotime($currentAttendance['check_in'])); ?></p>
                
                <div style="margin: 20px 0;">
                    <div id="timer-display" class="timer-regular" style="font-size: 36px; font-weight: bold; color: var(--primary-blue);"></div>
                    <div id="overtime-badge" class="badge badge-overtime" style="display: none; margin-top: 10px;"></div>
                </div>
                
                <button onclick="checkOut()" class="btn btn-danger btn-lg">Check Out</button>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 20px;">
                <div style="font-size: 48px; color: var(--dark-gray); margin-bottom: 20px;">⏰</div>
                <p style="font-size: 18px; margin-bottom: 20px;">You haven't checked in today</p>
                <button onclick="checkIn()" class="btn btn-success btn-lg">Check In</button>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Today's Summary -->
    <div class="card">
        <h2 class="card-title">Today's Summary</h2>
        <div style="padding: 20px;">
            <div style="margin-bottom: 15px;">
                <span style="font-weight: 600;">Regular Hours:</span>
                <span style="float: right; color: var(--primary-blue); font-weight: bold;"><?php echo number_format($totalRegularHours, 2); ?>h</span>
            </div>
            <div style="margin-bottom: 15px;">
                <span style="font-weight: 600;">Overtime Hours:</span>
                <span style="float: right; color: var(--overtime-orange); font-weight: bold;"><?php echo number_format($totalOvertimeHours, 2); ?>h</span>
            </div>
            <div style="border-top: 2px solid var(--border-color); padding-top: 15px;">
                <span style="font-weight: 600; font-size: 18px;">Total:</span>
                <span style="float: right; color: var(--primary-blue); font-weight: bold; font-size: 18px;">
                    <?php echo number_format($totalRegularHours + $totalOvertimeHours, 2); ?>h
                </span>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card">
        <h2 class="card-title">Quick Actions</h2>
        <div style="padding: 20px; display: flex; flex-direction: column; gap: 10px;">
            <a href="/app/views/leaves.php" class="btn btn-primary">Request Leave</a>
            <a href="/app/views/employee/tasks.php" class="btn btn-secondary">View My Tasks</a>
            <a href="/app/views/calendar.php" class="btn btn-secondary">View Calendar</a>
            <a href="/app/views/employee/credentials.php" class="btn btn-secondary">My Credentials</a>
        </div>
    </div>
</div>

<!-- Today's Attendance History -->
<?php if (count($todayHistory) > 0): ?>
<div class="card" style="margin-top: 20px;">
    <h2 class="card-title">Today's Check-in/out History</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Regular Hours</th>
                <th>Overtime Hours</th>
                <th>Total Hours</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($todayHistory as $record): ?>
            <tr>
                <td><?php echo date('h:i A', strtotime($record['check_in'])); ?></td>
                <td><?php echo $record['check_out'] ? date('h:i A', strtotime($record['check_out'])) : '-'; ?></td>
                <td><?php echo number_format($record['regular_hours'], 2); ?>h</td>
                <td class="<?php echo $record['overtime_hours'] > 0 ? 'overtime' : ''; ?>">
                    <?php echo number_format($record['overtime_hours'], 2); ?>h
                </td>
                <td><?php echo number_format($record['regular_hours'] + $record['overtime_hours'], 2); ?>h</td>
                <td>
                    <?php if ($record['status'] === 'in'): ?>
                        <span class="badge badge-success">Checked In</span>
                    <?php else: ?>
                        <span class="badge badge-primary">Checked Out</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<style>
    .timer-regular {
        color: var(--primary-blue);
    }
    .timer-overtime {
        color: var(--overtime-orange);
    }
</style>

<script>
    function checkIn() {
        ajaxRequest('/app/api/attendance/checkin.php', 'POST', {}, (response) => {
            if (response.success) {
                showMessage('success', 'Checked in successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage('error', response.message || 'Failed to check in');
            }
        });
    }
    
    function checkOut() {
        confirmDialog('Are you sure you want to check out?', () => {
            ajaxRequest('/app/api/attendance/checkout.php', 'POST', {}, (response) => {
                if (response.success) {
                    showMessage('success', 'Checked out successfully!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showMessage('error', response.message || 'Failed to check out');
                }
            });
        });
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>



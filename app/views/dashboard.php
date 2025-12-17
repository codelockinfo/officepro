<?php
/**
 * Employee Dashboard - Main View with Attendance Check-in/out
 */

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';

require_once __DIR__ . '/../helpers/Database.php';
require_once __DIR__ . '/../helpers/Tenant.php';

try {
    $companyId = Tenant::getCurrentCompanyId();
} catch (Exception $e) {
    error_log("Dashboard: Tenant error - " . $e->getMessage());
    header('Location: /officepro/login.php');
    exit;
}

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
    <?php if ($currentUser['role'] !== 'company_owner'): ?>
    <!-- Attendance Card - Only for Employees and Managers -->
    <div class="card">
        <h2 class="card-title">Today's Attendance</h2>
        
        <?php if ($currentAttendance): ?>
            <div style="text-align: center; padding: 20px;">
                <div style="font-size: 48px; color: var(--success-green); margin-bottom: 20px;">✓</div>
                <p style="font-size: 18px; font-weight: 600; color: var(--success-green); margin-bottom: 10px;">Checked In</p>
                <p>Check-in time: <?php echo date('h:i A', strtotime($currentAttendance['check_in'])); ?></p>
                
                <div style="margin: 20px 0;">
                    <div id="timer-display" class="timer-regular" style="font-size: 48px; font-weight: bold; color: var(--primary-blue);">00:00:00</div>
                    <div id="overtime-badge" class="badge badge-overtime" style="display: none; margin-top: 10px; font-size: 14px;"></div>
                </div>
                
                <button onclick="checkOut()" class="btn btn-danger btn-lg">Check Out</button>
            </div>
            <script>
                // Start timer immediately with check-in time from server
                document.addEventListener('DOMContentLoaded', function() {
                    const checkInTime = '<?php echo $currentAttendance['check_in']; ?>';
                    console.log('Starting timer with check-in time:', checkInTime);
                    startTimer(checkInTime);
                });
            </script>
        <?php else: ?>
            <div style="text-align: center; padding: 20px;">
                <div style="font-size: 48px; color: var(--dark-gray); margin-bottom: 20px;">⏰</div>
                <p style="font-size: 18px; margin-bottom: 20px;">You haven't checked in today</p>
                <button onclick="checkIn()" class="btn btn-success btn-lg">Check In</button>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Today's Summary - Only for Employees and Managers -->
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
    <?php else: ?>
    <!-- Company Overview for Owner -->
    <div class="card">
        <h2 class="card-title">Company Overview</h2>
        <div style="padding: 20px;">
            <?php
            // Get company stats
            $totalEmployees = $db->fetchOne(
                "SELECT COUNT(*) as count FROM users WHERE company_id = ? AND status = 'active'",
                [$companyId]
            );
            $presentToday = $db->fetchOne(
                "SELECT COUNT(DISTINCT user_id) as count FROM attendance WHERE company_id = ? AND date = ? AND status = 'out'",
                [$companyId, $today]
            );
            $pendingLeaves = $db->fetchOne(
                "SELECT COUNT(*) as count FROM leaves WHERE company_id = ? AND status = 'pending'",
                [$companyId]
            );
            ?>
            <div style="margin-bottom: 15px;">
                <span style="font-weight: 600;">Total Employees:</span>
                <span style="float: right; color: var(--primary-blue); font-weight: bold;"><?php echo $totalEmployees['count']; ?></span>
            </div>
            <div style="margin-bottom: 15px;">
                <span style="font-weight: 600;">Present Today:</span>
                <span style="float: right; color: var(--success-green); font-weight: bold;"><?php echo $presentToday['count']; ?></span>
            </div>
            <div>
                <span style="font-weight: 600;">Pending Leaves:</span>
                <span style="float: right; color: var(--warning-yellow); font-weight: bold;"><?php echo $pendingLeaves['count']; ?></span>
            </div>
        </div>
    </div>
    
    <!-- Quick Stats for Owner -->
    <div class="card">
        <h2 class="card-title">This Month</h2>
        <div style="padding: 20px;">
            <?php
            $currentMonth = date('Y-m');
            $monthStats = $db->fetchOne(
                "SELECT 
                    SUM(overtime_hours) as total_overtime,
                    COUNT(DISTINCT user_id) as active_employees
                FROM attendance 
                WHERE company_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?",
                [$companyId, $currentMonth]
            );
            ?>
            <div style="margin-bottom: 15px;">
                <span style="font-weight: 600;">Active Employees:</span>
                <span style="float: right; color: var(--primary-blue); font-weight: bold;"><?php echo $monthStats['active_employees'] ?? 0; ?></span>
            </div>
            <div>
                <span style="font-weight: 600;">Total Overtime:</span>
                <span style="float: right; color: var(--overtime-orange); font-weight: bold;"><?php echo number_format($monthStats['total_overtime'] ?? 0, 1); ?>h</span>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Quick Actions -->
    <div class="card">
        <h2 class="card-title">Quick Actions</h2>
        <div style="padding: 20px; display: flex; flex-direction: column; gap: 10px;">
            <?php if ($currentUser['role'] !== 'company_owner'): ?>
                <a href="/officepro/app/views/leaves.php" class="btn btn-primary">Request Leave</a>
            <?php endif; ?>
            <?php if ($currentUser['role'] === 'company_owner'): ?>
                <a href="/officepro/app/views/company/employees.php" class="btn btn-primary custom-btn-primary">Manage Employees</a>
                <a href="/officepro/app/views/company/invitations.php" class="btn btn-primary custom-btn-primary">Invite Employees</a>
                <a href="/officepro/app/views/leave_approvals.php" class="btn btn-secondary">Leave Approvals</a>
                <a href="/officepro/app/views/reports/report_dashboard.php" class="btn btn-secondary">View Reports</a>
            <?php else: ?>
                <a href="/officepro/app/views/employee/tasks.php" class="btn btn-secondary">View My Tasks</a>
                <a href="/officepro/app/views/employee/credentials.php" class="btn btn-secondary">My Credentials</a>
            <?php endif; ?>
            <a href="/officepro/app/views/calendar.php" class="btn btn-secondary">View Calendar</a>
        </div>
    </div>
</div>

<!-- Today's Attendance History - Only for employees and managers -->
<?php if ($currentUser['role'] !== 'company_owner' && count($todayHistory) > 0): ?>
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

<script>
    function checkIn() {
        ajaxRequest('/officepro/app/api/attendance/checkin.php', 'POST', {}, (response) => {
            if (response.success) {
                showMessage('success', 'Checked in successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage('error', response.message || 'Failed to check in');
            }
        });
    }
    
    function checkOut() {
        // Custom checkout confirmation modal
        const modalContent = `
            <div style="text-align: center; padding: 30px 20px;">
                <div style="font-size: 64px; margin-bottom: 20px;">⏰</div>
                <h3 style="color: #333; margin-bottom: 15px;">Confirm Check Out</h3>
                <p style="color: #666; font-size: 16px;">Are you sure you want to check out?</p>
                <p style="color: #999; font-size: 14px; margin-top: 10px;">Your work hours will be calculated.</p>
            </div>
        `;
        
        const modalFooter = `
            <button type="button" class="btn btn-secondary" onclick="closeModal(this.closest('.modal-overlay').id)">Cancel</button>
            <button type="button" class="btn btn-danger" onclick="confirmCheckOut()">Yes, Check Out</button>
        `;
        
        createModal('', modalContent, modalFooter, 'modal-sm');
    }
    
    function confirmCheckOut() {
        // Close the modal
        const activeModal = document.querySelector('.modal-overlay.active');
        if (activeModal) {
            closeModal(activeModal.id);
        }
        
        // Perform checkout
        ajaxRequest('/officepro/app/api/attendance/checkout.php', 'POST', {}, (response) => {
            if (response.success) {
                showMessage('success', 'Checked out successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage('error', response.message || 'Failed to check out');
            }
        });
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>




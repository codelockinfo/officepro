<?php
/**
 * Attendance History Page
 */

$pageTitle = 'Attendance History';
include __DIR__ . '/includes/header.php';

require_once __DIR__ . '/../helpers/Database.php';
require_once __DIR__ . '/../helpers/Tenant.php';

$companyId = Tenant::getCurrentCompanyId();
$userId = $currentUser['id'];
$db = Database::getInstance();

// Get date range from query or default to current month
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Get attendance records
$attendance = $db->fetchAll(
    "SELECT * FROM attendance 
    WHERE company_id = ? AND user_id = ? AND date BETWEEN ? AND ? 
    ORDER BY date DESC, check_in DESC",
    [$companyId, $userId, $startDate, $endDate]
);

// Calculate totals
$totalRegular = 0;
$totalOvertime = 0;
$totalDays = 0;

foreach ($attendance as $record) {
    if ($record['status'] === 'out') {
        $totalRegular += $record['regular_hours'];
        $totalOvertime += $record['overtime_hours'];
        $totalDays++;
    }
}
?>

<h1>⏰ Attendance History</h1>

<!-- Filter Card -->
<div class="card" style="margin: 20px 0;">
    <div style="padding: 20px;">
        <form method="GET" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 20px; align-items: end;">
            <div class="form-group" style="margin: 0;">
                <label class="form-label" for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label class="form-label" for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
            </div>
            
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--primary-blue); margin-bottom: 10px;">Total Days</h3>
        <div style="font-size: 36px; font-weight: bold; color: var(--primary-blue);">
            <?php echo $totalDays; ?>
        </div>
        <p style="color: #666;">days worked</p>
    </div>
    
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--primary-blue); margin-bottom: 10px;">Regular Hours</h3>
        <div style="font-size: 36px; font-weight: bold; color: var(--primary-blue);">
            <?php echo formatHoursToTime($totalRegular); ?>
        </div>
        <p style="color: #666;">HH:MM:SS</p>
    </div>
    
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--overtime-orange); margin-bottom: 10px;">Overtime Hours</h3>
        <div style="font-size: 36px; font-weight: bold; color: var(--overtime-orange);">
            <?php echo formatHoursToTime($totalOvertime); ?>
        </div>
        <p style="color: #666;">HH:MM:SS</p>
    </div>
    
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--success-green); margin-bottom: 10px;">Total Hours</h3>
        <div style="font-size: 36px; font-weight: bold; color: var(--success-green);">
            <?php echo formatHoursToTime($totalRegular + $totalOvertime); ?>
        </div>
        <p style="color: #666;">HH:MM:SS</p>
    </div>
</div>

<!-- Attendance History Table -->
<div class="card">
    <h2 class="card-title">Attendance Records (<?php echo date('M d', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?>)</h2>
    
    <?php if (count($attendance) === 0): ?>
        <p style="text-align: center; padding: 40px; color: #666;">No attendance records found for this period</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Regular Hours</th>
                    <th>Overtime Hours</th>
                    <th>Total Hours</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attendance as $record): ?>
                <tr>
                    <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                    <td><?php echo date('h:i A', strtotime($record['check_in'])); ?></td>
                    <td>
                        <?php 
                        if ($record['check_out']) {
                            echo date('h:i A', strtotime($record['check_out']));
                        } else {
                            echo '<span class="badge badge-success">Still Checked In</span>';
                        }
                        ?>
                    </td>
                    <td><?php echo formatHoursToTime($record['regular_hours']); ?></td>
                    <td style="color: var(--overtime-orange); font-weight: <?php echo $record['overtime_hours'] > 0 ? 'bold' : 'normal'; ?>;">
                        <?php echo formatHoursToTime($record['overtime_hours']); ?>
                        <?php if ($record['overtime_hours'] > 0): ?>
                            <span class="badge badge-overtime">OT</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo formatHoursToTime($record['regular_hours'] + $record['overtime_hours']); ?></strong></td>
                    <td>
                        <?php if ($record['status'] === 'in'): ?>
                            <span class="badge badge-success">Checked In</span>
                        <?php else: ?>
                            <span class="badge badge-primary">Completed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight: bold; background: var(--light-blue);">
                    <td colspan="3">TOTAL</td>
                    <td><?php echo formatHoursToTime($totalRegular); ?></td>
                    <td style="color: var(--overtime-orange);"><?php echo formatHoursToTime($totalOvertime); ?></td>
                    <td><?php echo formatHoursToTime($totalRegular + $totalOvertime); ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>



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

// Helper function to convert decimal hours to HH:MM:SS format
function formatHoursToTime($decimalHours) {
    if ($decimalHours <= 0) {
        return '00:00:00';
    }
    $totalSeconds = round($decimalHours * 3600);
    $hours = floor($totalSeconds / 3600);
    $minutes = floor(($totalSeconds % 3600) / 60);
    $seconds = $totalSeconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

// Get date range from query or default to current month
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Get attendance records (now based on timer sessions)
$attendance = $db->fetchAll(
    "SELECT a.*, 
            COUNT(DISTINCT ts.id) as session_count,
            MIN(ts.start_time) as first_session_start,
            MAX(ts.end_time) as last_session_end
     FROM attendance a
     LEFT JOIN timer_sessions ts ON ts.company_id = a.company_id 
         AND ts.user_id = a.user_id 
         AND ts.date = a.date 
         AND ts.status = 'ended'
     WHERE a.company_id = ? AND a.user_id = ? AND a.date BETWEEN ? AND ?
     GROUP BY a.id
     ORDER BY a.date DESC",
    [$companyId, $userId, $startDate, $endDate]
);

// Calculate totals
$totalRegular = 0;
$totalOvertime = 0;
$totalDays = 0;

foreach ($attendance as $record) {
    if ($record['is_present']) {
        $totalRegular += floatval($record['regular_hours'] ?? 0);
        $totalOvertime += floatval($record['overtime_hours'] ?? 0);
        if ($record['regular_hours'] > 0 || $record['overtime_hours'] > 0) {
            $totalDays++;
        }
    }
}
?>

<h1><i class="fas fa-clock"></i> Attendance History</h1>

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
            
            <button type="submit" class="btn btn-secondary custom-btn-secondary">Filter</button>
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
                    <th>First Session</th>
                    <th>Last Session</th>
                    <th>Sessions</th>
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
                    <td>
                        <?php 
                        if ($record['first_session_start']) {
                            echo date('h:i A', strtotime($record['first_session_start']));
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        if ($record['last_session_end']) {
                            echo date('h:i A', strtotime($record['last_session_end']));
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        $sessionCount = intval($record['session_count'] ?? 0);
                        echo $sessionCount > 0 ? $sessionCount : '-';
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
                        <?php if ($record['is_present']): ?>
                            <span class="badge badge-success">Present</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Absent</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight: bold; background: var(--light-blue);">
                    <td colspan="4">TOTAL</td>
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

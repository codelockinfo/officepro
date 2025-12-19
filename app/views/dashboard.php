<?php
/**
 * Employee Dashboard - Main View with Attendance Check-in/out
 */

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';

require_once __DIR__ . '/../helpers/Database.php';
require_once __DIR__ . '/../helpers/Tenant.php';

// Set timezone from config
$appConfig = require __DIR__ . '/../config/app.php';
date_default_timezone_set($appConfig['timezone']);

try {
    $companyId = Tenant::getCurrentCompanyId();
} catch (Exception $e) {
    error_log("Dashboard: Tenant error - " . $e->getMessage());
    header('Location: /officepro/login.php');
    exit;
}

$userId = $currentUser['id'];
$db = Database::getInstance();

// Get today's date
$today = date('Y-m-d');

// Get current running timer session for today
$timerSession = $db->fetchOne(
    "SELECT * FROM timer_sessions WHERE company_id = ? AND user_id = ? AND date = ? AND status = 'running' ORDER BY start_time DESC LIMIT 1",
    [$companyId, $userId, $today]
);

// Get all ended timer sessions for today
$todaySessions = $db->fetchAll(
    "SELECT * FROM timer_sessions 
     WHERE company_id = ? AND user_id = ? AND date = ? AND status = 'ended'
     ORDER BY start_time ASC",
    [$companyId, $userId, $today]
);

// Check if user is present today (has at least one timer session)
$isPresentToday = $db->fetchOne(
    "SELECT COUNT(*) as count FROM timer_sessions 
     WHERE company_id = ? AND user_id = ? AND date = ?",
    [$companyId, $userId, $today]
);
$isPresent = ($isPresentToday['count'] ?? 0) > 0;

// Get today's attendance record for totals
$todayAttendance = $db->fetchOne(
    "SELECT * FROM attendance WHERE company_id = ? AND user_id = ? AND date = ? LIMIT 1",
    [$companyId, $userId, $today]
);

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

// Helper function to convert seconds to HH:MM:SS format
function formatSecondsToTime($seconds) {
    if ($seconds <= 0) {
        return '00:00:00';
    }
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
}

// Calculate today's totals from attendance record
$totalRegularHours = floatval($todayAttendance['regular_hours'] ?? 0);
$totalOvertimeHours = floatval($todayAttendance['overtime_hours'] ?? 0);
$totalHoursWorked = $totalRegularHours + $totalOvertimeHours;
?>

<h1>Welcome, <?php echo htmlspecialchars($currentUser['full_name']); ?>! </h1>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 30px;">
    <?php if ($currentUser['role'] !== 'company_owner'): ?>
    <!-- Timer Card - Only for Employees and Managers -->
    <div class="card">
        <h2 class="card-title">Work Timer</h2>
        
        <!-- Present Status Badge -->
        <?php if ($isPresent): ?>
        <div style="padding: 10px 15px; background-color: #28a745; color: white; border-radius: 5px; margin-bottom: 15px; text-align: center;">
            <i class="fas fa-check-circle"></i> <strong>Present Today</strong>
        </div>
        <?php endif; ?>
        
        <!-- Today's Total Hours Summary -->
        <div style="padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; margin-bottom: 20px; color: white;">
            <div style="text-align: center;">
                <p style="font-size: 14px; opacity: 0.9; margin-bottom: 5px;">Total Hours Today</p>
                <p style="font-size: 36px; font-weight: bold; margin-bottom: 10px;"><?php echo formatHoursToTime($totalHoursWorked); ?></p>
                <div style="display: flex; justify-content: space-around; margin-top: 15px;">
                    <div>
                        <p style="font-size: 12px; opacity: 0.9;">Regular</p>
                        <p style="font-size: 18px; font-weight: bold;"><?php echo formatHoursToTime($totalRegularHours); ?></p>
                    </div>
                    <div>
                        <p style="font-size: 12px; opacity: 0.9;">Overtime</p>
                        <p style="font-size: 18px; font-weight: bold;"><?php echo formatHoursToTime($totalOvertimeHours); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Timer Controls -->
        <div style="text-align: center; padding: 20px;">
            <?php if ($timerSession): ?>
                <!-- Timer is running -->
                <div style="margin: 20px 0;">
                    <div id="timer-display" class="timer-regular" style="font-size: 48px; font-weight: bold; color: var(--primary-blue);">00:00:00</div>
                    <div id="overtime-badge" class="badge badge-overtime" style="display: none; margin-top: 10px; font-size: 14px;"></div>
                </div>
                <div style="display: flex; gap: 15px; justify-content: center; margin-top: 20px; flex-wrap: wrap;">
                    <button onclick="stopWorkTimer()" class="btn" style="width: 60px; height: 60px; border-radius: 50%; background-color: #dc3545; color: #fff; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2); padding: 0;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                            <rect x="6" y="6" width="12" height="12"/>
                        </svg>
                    </button>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const startTime = '<?php echo $timerSession['start_time']; ?>';
                        startWorkTimer(startTime);
                    });
                </script>
            <?php else: ?>
                <!-- No timer running - Show Start Button -->
                <div style="margin: 20px 0;">
                    <p style="color: var(--dark-gray); margin-bottom: 20px;">Click to start tracking your work time</p>
                    <button onclick="startWorkTimerSession()" class="btn" style="width: 70px; height: 70px; border-radius: 50%; background-color: #28a745; color: #fff; border: none; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2); padding: 0;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                            <polygon points="8,5 19,12 8,19"/>
                        </svg>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Tasks Section for Employee -->
    <?php
    $employeeTasks = $db->fetchAll(
        "SELECT t.*, creator.full_name as created_by_name
         FROM tasks t
         LEFT JOIN users creator ON t.created_by = creator.id
         WHERE t.company_id = ? AND t.assigned_to = ?
         ORDER BY 
            CASE 
                WHEN t.status = 'todo' THEN 1
                WHEN t.status = 'in_progress' THEN 2
                WHEN t.status = 'done' THEN 3
            END,
            t.created_at DESC
         LIMIT 5",
        [$companyId, $userId]
    );
    ?>
    <?php else: ?>
    <!-- Company Overview for Owner -->
    <div class="card">
        <h2 class="card-title">Company Overview</h2>
        <div style="padding: 20px;">
            <?php
            // Get company stats (exclude company owners)
            $totalEmployees = $db->fetchOne(
                "SELECT COUNT(*) as count FROM users WHERE company_id = ? AND status = 'active' AND role != 'company_owner'",
                [$companyId]
            );
            
            // Count employees present today
            // Count distinct employees who have attendance records for today
            // Exclude employees who are on approved leave today
            $presentToday = $db->fetchOne(
                "SELECT COUNT(DISTINCT a.user_id) as count 
                 FROM attendance a
                 JOIN users u ON a.user_id = u.id
                 WHERE a.company_id = ? 
                 AND a.date = ? 
                 AND u.status = 'active'
                 AND a.user_id NOT IN (
                     SELECT DISTINCT l.user_id 
                     FROM leaves l
                     WHERE l.company_id = ? 
                     AND l.status = 'approved' 
                     AND ? BETWEEN l.start_date AND l.end_date
                 )",
                [$companyId, $today, $companyId, $today]
            );
            
            $presentCount = $presentToday['count'] ?? 0;
            
            // Count employees on leave today (approved leaves where today falls between start_date and end_date)
            $onLeaveToday = $db->fetchOne(
                "SELECT COUNT(DISTINCT l.user_id) as count 
                 FROM leaves l
                 JOIN users u ON l.user_id = u.id
                 WHERE l.company_id = ? 
                 AND l.status = 'approved' 
                 AND ? BETWEEN l.start_date AND l.end_date
                 AND u.status = 'active'",
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
                <span style="float: right; color: var(--success-green); font-weight: bold;"><?php echo $presentCount; ?></span>
            </div>
            <div style="margin-bottom: 15px;">
                <span style="font-weight: 600;">On Leave Today:</span>
                <span style="float: right; color:rgb(236, 9, 32); font-weight: bold;"><?php echo $onLeaveToday['count']; ?></span>
            </div>
        </div>
    </div>
    
    <!-- Tasks Section for Owner -->
    <?php
    $ownerTasks = $db->fetchAll(
        "SELECT t.*, assignee.full_name as assigned_to_name
         FROM tasks t
         LEFT JOIN users assignee ON t.assigned_to = assignee.id
         WHERE t.company_id = ?
         ORDER BY 
            CASE 
                WHEN t.status = 'todo' THEN 1
                WHEN t.status = 'in_progress' THEN 2
                WHEN t.status = 'done' THEN 3
            END,
            t.created_at DESC
         LIMIT 5",
        [$companyId]
    );
    ?>
    <?php endif; ?>
    
    <!-- Quick Actions -->
    <div class="card">
        <h2 class="card-title">Quick Actions</h2>
        <div style="padding: 20px; display: flex; flex-direction: column; gap: 10px;">
            <?php if ($currentUser['role'] !== 'company_owner'): ?>
                <a href="/officepro/app/views/leaves.php" class="btn btn-primary custom-btn-primary">Request Leave</a>
            <?php endif; ?>
            <?php if ($currentUser['role'] === 'company_owner'): ?>
                <a href="/officepro/app/views/company/employees.php" class="btn btn-primary custom-btn-primary">Manage Employees</a>
                <a href="/officepro/app/views/company/invitations.php" class="btn btn-primary custom-btn-primary">Invite Employees</a>
                <a href="/officepro/app/views/leave_approvals.php" class="btn btn-secondary custom-btn-secondary">Leave Approvals</a>
                <a href="/officepro/app/views/reports/report_dashboard.php" class="btn btn-secondary custom-btn-secondary">View Reports</a>
            <?php else: ?>
                <a href="/officepro/app/views/employee/tasks.php" class="btn btn-secondary custom-btn-secondary">View My Tasks</a>
                <a href="/officepro/app/views/employee/credentials.php" class="btn btn-secondary custom-btn-secondary">My Credentials</a>
            <?php endif; ?>
            <a href="/officepro/app/views/calendar.php" class="btn btn-secondary custom-btn-secondary">View Calendar</a>
        </div>
    </div>
</div>

<!-- Today's Timer Sessions - Only for employees and managers -->
<?php if ($currentUser['role'] !== 'company_owner' && count($todaySessions) > 0): ?>
<div class="card" style="margin-top: 20px;">
    <h2 class="card-title">Today's Work Sessions</h2>
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Duration</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($todaySessions as $index => $session): ?>
            <tr>
                <td><?php echo $index + 1; ?></td>
                <td><?php 
                    $startTime = DateTime::createFromFormat('Y-m-d H:i:s', $session['start_time']);
                    echo $startTime ? $startTime->format('h:i A') : '-';
                ?></td>
                <td><?php 
                    // Calculate end time from start_time + duration_seconds for accurate display
                    if ($session['start_time'] && isset($session['duration_seconds']) && $session['duration_seconds'] > 0) {
                        $startTime = DateTime::createFromFormat('Y-m-d H:i:s', $session['start_time']);
                        if ($startTime) {
                            $endTime = clone $startTime;
                            $endTime->modify('+' . intval($session['duration_seconds']) . ' seconds');
                            echo $endTime->format('h:i A');
                        } else {
                            echo '-';
                        }
                    } elseif ($session['end_time']) {
                        // Fallback to stored end_time if duration not available
                        $endTime = DateTime::createFromFormat('Y-m-d H:i:s', $session['end_time']);
                        echo $endTime ? $endTime->format('h:i A') : '-';
                    } else {
                        echo '-';
                    }
                ?></td>
                <td><?php echo formatSecondsToTime($session['duration_seconds'] ?? 0); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>
    // Timer functions
    let workTimerInterval = null;
    let workTimerStartTime = null;
    
    function startWorkTimerSession() {
        showLoader();
        ajaxRequest('/officepro/app/api/attendance/timer_start.php', 'POST', {}, (response) => {
            hideLoader();
            if (response.success) {
                showMessage('success', 'Timer started!');
                setTimeout(() => location.reload(), 500);
            } else {
                showMessage('error', response.message || 'Failed to start timer');
            }
        });
    }
    
    function stopWorkTimer() {
        // Stop the JavaScript timer immediately
        if (workTimerInterval) {
            clearInterval(workTimerInterval);
            workTimerInterval = null;
        }
        workTimerStartTime = null;
        
        // Stop any other timer intervals
        if (typeof timerInterval !== 'undefined' && timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        
        // Make API call to stop timer
        showLoader();
        ajaxRequest('/officepro/app/api/attendance/timer_end.php', 'POST', {}, (response) => {
            hideLoader();
            if (response.success) {
                showMessage('success', 'Timer stopped! Session saved.');
                setTimeout(() => location.reload(), 500);
            } else {
                showMessage('error', response.message || 'Failed to stop timer');
            }
        });
    }
    
    
    function startWorkTimer(startTime) {
        // Stop any existing timer
        if (workTimerInterval) {
            clearInterval(workTimerInterval);
        }
        
        // Parse start time
        workTimerStartTime = new Date(startTime.replace(' ', 'T')).getTime();
        
        // Update immediately
        updateWorkTimerDisplay();
        
        // Update every second
        workTimerInterval = setInterval(updateWorkTimerDisplay, 1000);
    }
    
    function updateWorkTimerDisplay() {
        // CRITICAL: If timer interval is null or start time is null, stop updating
        if (!workTimerInterval || !workTimerStartTime) {
            return;
        }
        
        const now = Date.now();
        const elapsed = Math.floor((now - workTimerStartTime) / 1000);
        
        // Safety check: don't update if elapsed is negative
        if (elapsed < 0) {
            return;
        }
        
        const hours = Math.floor(elapsed / 3600);
        const minutes = Math.floor((elapsed % 3600) / 60);
        const seconds = elapsed % 60;
        
        const display = document.getElementById('timer-display');
        if (display) {
            const hoursStr = String(hours).padStart(2, '0');
            const minutesStr = String(minutes).padStart(2, '0');
            const secondsStr = String(seconds).padStart(2, '0');
            display.textContent = `${hoursStr}:${minutesStr}:${secondsStr}`;
            
            // Check for overtime (8 hours = 28800 seconds)
            const standardWorkSeconds = 8 * 3600;
            if (elapsed >= standardWorkSeconds) {
                display.style.color = 'var(--overtime-orange)';
                const overtimeHours = Math.floor((elapsed - standardWorkSeconds) / 3600);
                const overtimeMinutes = Math.floor(((elapsed - standardWorkSeconds) % 3600) / 60);
                const overtimeBadge = document.getElementById('overtime-badge');
                if (overtimeBadge) {
                    overtimeBadge.textContent = `⏰ Overtime: ${overtimeHours}h ${overtimeMinutes}m`;
                    overtimeBadge.style.display = 'block';
                }
            } else {
                display.style.color = 'var(--primary-blue)';
                const overtimeBadge = document.getElementById('overtime-badge');
                if (overtimeBadge) {
                    overtimeBadge.style.display = 'none';
                }
            }
        }
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>




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

// Get today's attendance status
$today = date('Y-m-d');
$currentAttendance = $db->fetchOne(
    "SELECT * FROM attendance WHERE company_id = ? AND user_id = ? AND date = ? AND status = 'in' ORDER BY check_in DESC LIMIT 1",
    [$companyId, $userId, $today]
);

// Get current timer session status if checked in
$timerSession = null;
if ($currentAttendance) {
    $timerSession = $db->fetchOne(
        "SELECT * FROM timer_sessions WHERE attendance_id = ? AND status IN ('running', 'stopped') ORDER BY start_time DESC LIMIT 1",
        [$currentAttendance['id']]
    );
}

// Get today's attendance history
$todayHistory = $db->fetchAll(
    "SELECT * FROM attendance 
     WHERE company_id = ? AND user_id = ? AND date = ? 
     ORDER BY check_in DESC",
    [$companyId, $userId, $today]
);

// Get all ended timer sessions for today with attendance info
$attendanceSessions = [];
foreach ($todayHistory as $attendance) {
    $sessions = $db->fetchAll(
        "SELECT ts.*, a.check_in, a.check_out, a.status as attendance_status
         FROM timer_sessions ts
         JOIN attendance a ON ts.attendance_id = a.id
         WHERE ts.attendance_id = ? AND ts.status = 'ended'
         ORDER BY ts.start_time ASC",
        [$attendance['id']]
    );
    
    // If no sessions, still show the attendance record
    if (count($sessions) == 0) {
        $attendanceSessions[] = [
            'type' => 'attendance_only',
            'check_in' => $attendance['check_in'],
            'check_out' => $attendance['check_out'],
            'status' => $attendance['status'],
            'regular_hours' => $attendance['regular_hours'] ?? 0,
            'overtime_hours' => $attendance['overtime_hours'] ?? 0
        ];
    } else {
        foreach ($sessions as $session) {
            $attendanceSessions[] = [
                'type' => 'session',
                'check_in' => $session['check_in'],
                'check_out' => $session['check_out'],
                'session_start' => $session['start_time'],
                'session_end' => $session['end_time'],
                'duration_seconds' => $session['duration_seconds'] ?? 0,
                'regular_hours' => floatval($session['regular_hours'] ?? 0),
                'overtime_hours' => floatval($session['overtime_hours'] ?? 0),
                'status' => $session['attendance_status']
            ];
        }
    }
}

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

// Calculate today's totals - sum from ended timer sessions and completed check-outs
$totalRegularHours = 0;
$totalOvertimeHours = 0;

// Sum from ended timer sessions for current attendance
if ($currentAttendance) {
    $sessionTotals = $db->fetchOne(
        "SELECT SUM(regular_hours) as total_regular, SUM(overtime_hours) as total_overtime 
         FROM timer_sessions 
         WHERE attendance_id = ? AND status = 'ended'",
        [$currentAttendance['id']]
    );
    $totalRegularHours += floatval($sessionTotals['total_regular'] ?? 0);
    $totalOvertimeHours += floatval($sessionTotals['total_overtime'] ?? 0);
}

// Sum from completed check-outs (status = 'out')
foreach ($todayHistory as $record) {
    if ($record['status'] === 'out' && $record['check_out'] !== null) {
        $totalRegularHours += floatval($record['regular_hours'] ?? 0);
        $totalOvertimeHours += floatval($record['overtime_hours'] ?? 0);
    }
}
?>

<h1>Welcome, <?php echo htmlspecialchars($currentUser['full_name']); ?>! </h1>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 30px;">
    <?php if ($currentUser['role'] !== 'company_owner'): ?>
    <!-- Attendance Card - Only for Employees and Managers -->
    <div class="card">
        <h2 class="card-title">Today's Attendance</h2>
        
        <?php if ($currentAttendance): ?>
            <div style="text-align: center; padding: 20px;">
                <div style="font-size: 48px; color: var(--success-green); margin-bottom: 20px;"><i class="fas fa-check-circle"></i></div>
                <p style="font-size: 18px; font-weight: 600; color: var(--success-green); margin-bottom: 10px;">Checked In</p>
                <p style="margin-bottom: 20px;">Check-in time: <?php 
                    // Parse database timestamp directly (already in correct timezone)
                    $checkInTime = DateTime::createFromFormat('Y-m-d H:i:s', $currentAttendance['check_in']);
                    if ($checkInTime) {
                        echo $checkInTime->format('h:i A');
                    } else {
                        echo date('h:i A', strtotime($currentAttendance['check_in']));
                    }
                ?></p>
                
                <?php if ($timerSession && $timerSession['status'] === 'running'): ?>
                    <!-- Timer is running -->
                    <div style="margin: 20px 0;">
                        <div id="timer-display" class="timer-regular" style="font-size: 48px; font-weight: bold; color: var(--primary-blue);">00:00:00</div>
                        <div id="overtime-badge" class="badge badge-overtime" style="display: none; margin-top: 10px; font-size: 14px;"></div>
                    </div>
                    <div style="display: flex; gap: 15px; justify-content: center; margin-top: 20px; flex-wrap: wrap;">
                        <button onclick="stopWorkTimer()" class="btn" style="width: 60px; height: 60px; border-radius: 50%; background-color: #ffc107; color: #000; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2); padding: 0;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                                <rect x="6" y="5" width="4" height="14"/>
                                <rect x="14" y="5" width="4" height="14"/>
                            </svg>
                        </button>
                        <button onclick="endWorkTimer()" class="btn" style="width: 60px; height: 60px; border-radius: 50%; background-color: #dc3545; color: #fff; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2); padding: 0;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                                <rect x="6" y="6" width="12" height="12"/>
                            </svg>
                        </button>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            // Only start timer if status is running
                            const timerStatus = '<?php echo $timerSession['status']; ?>';
                            if (timerStatus === 'running') {
                                const startTime = '<?php echo $timerSession['start_time']; ?>';
                                startWorkTimer(startTime);
                            } else {
                                // Make sure timer is stopped
                                if (workTimerInterval) {
                                    clearInterval(workTimerInterval);
                                    workTimerInterval = null;
                                }
                                if (typeof stopTimer === 'function') {
                                    stopTimer();
                                }
                            }
                        });
                    </script>
                <?php elseif ($timerSession && $timerSession['status'] === 'stopped'): ?>
                    <!-- Timer is stopped -->
                    <div style="margin: 20px 0;">
                        <div id="timer-display" style="font-size: 36px; font-weight: bold; color: var(--warning-yellow);">
                            <?php
                            $startTime = new DateTime($timerSession['start_time']);
                            $stopTime = new DateTime($timerSession['stop_time']);
                            $interval = $startTime->diff($stopTime);
                            echo sprintf('%02d:%02d:%02d', $interval->h, $interval->i, $interval->s);
                            ?>
                        </div>
                        <p style="color: var(--warning-yellow); margin-top: 10px;">Timer Stopped</p>
                    </div>
                    <div style="display: flex; gap: 15px; justify-content: center; margin-top: 20px; flex-wrap: wrap;">
                        <button onclick="resumeWorkTimer()" class="btn" style="width: 60px; height: 60px; border-radius: 50%; background-color: #28a745; color: #fff; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2); padding: 0;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                                <polygon points="8,5 19,12 8,19"/>
                            </svg>
                        </button>
                        <button onclick="endWorkTimer()" class="btn" style="width: 60px; height: 60px; border-radius: 50%; background-color: #dc3545; color: #fff; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2); padding: 0;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                                <rect x="6" y="6" width="12" height="12"/>
                            </svg>
                        </button>
                    </div>
                <?php else: ?>
                    <!-- No timer started yet -->
                    <div style="margin: 20px 0; text-align: center;">
                        <p style="color: var(--dark-gray); margin-bottom: 15px;">Start your work timer</p>
                        <button onclick="startWorkTimerSession()" class="btn" style="width: 50px; height: 50px; border-radius: 50%; background-color: #28a745; color: #fff; border: none; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2); padding: 0;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                                <polygon points="8,5 19,12 8,19"/>
                            </svg>
                        </button>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                    <button onclick="checkOut()" class="btn btn-danger btn-lg">Check Out</button>
                </div>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 20px;">
                <div style="font-size: 48px; color: var(--dark-gray); margin-bottom: 20px;"><i class="fas fa-clock"></i></div>
                <p style="font-size: 18px; margin-bottom: 20px;">You haven't checked in today</p>
                <button onclick="checkIn()" class="btn btn-success custom-btn-success">Check In</button>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Today's Summary - Only for Employees and Managers -->
    <div class="card">
        <h2 class="card-title">Today's Summary</h2>
        <div style="padding: 20px;">
            <div style="margin-bottom: 15px;">
                <span style="font-weight: 600;">Regular Hours:</span>
                <span style="float: right; color: var(--primary-blue); font-weight: bold;"><?php echo formatHoursToTime($totalRegularHours); ?></span>
            </div>
            <div style="margin-bottom: 15px;">
                <span style="font-weight: 600;">Overtime Hours:</span>
                <span style="float: right; color: var(--overtime-orange); font-weight: bold;"><?php echo formatHoursToTime($totalOvertimeHours); ?></span>
            </div>
            <div style="border-top: 2px solid var(--border-color); padding-top: 15px;">
                <span style="font-weight: 600; font-size: 18px;">Total:</span>
                <span style="float: right; color: var(--primary-blue); font-weight: bold; font-size: 18px;">
                    <?php echo formatHoursToTime($totalRegularHours + $totalOvertimeHours); ?>
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

<!-- Today's Attendance & Sessions - Only for employees and managers -->
<?php if ($currentUser['role'] !== 'company_owner' && count($attendanceSessions) > 0): ?>
<div class="card" style="margin-top: 20px;">
    <h2 class="card-title">Today's Attendance & Sessions</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Session Time</th>
                <th>Regular Hours</th>
                <th>Overtime Hours</th>
                <th>Total Hours</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attendanceSessions as $item): ?>
            <tr>
                <td><?php 
                    if ($item['check_in'] && $item['check_in'] !== '0000-00-00 00:00:00') {
                        $checkInTime = DateTime::createFromFormat('Y-m-d H:i:s', $item['check_in']);
                        echo $checkInTime ? $checkInTime->format('h:i A') : date('h:i A', strtotime($item['check_in']));
                    } else {
                        echo '-';
                    }
                ?></td>
                <td><?php 
                    if ($item['check_out'] && $item['check_out'] !== '0000-00-00 00:00:00' && $item['check_out'] !== null) {
                        $checkOutTime = DateTime::createFromFormat('Y-m-d H:i:s', $item['check_out']);
                        echo $checkOutTime ? $checkOutTime->format('h:i A') : date('h:i A', strtotime($item['check_out']));
                    } else {
                        echo '-';
                    }
                ?></td>
                <td>
                    <?php if ($item['type'] === 'session' && isset($item['session_start'])): ?>
                        <?php
                        $sessionStart = DateTime::createFromFormat('Y-m-d H:i:s', $item['session_start']);
                        $sessionEnd = isset($item['session_end']) ? DateTime::createFromFormat('Y-m-d H:i:s', $item['session_end']) : null;
                        echo ($sessionStart ? $sessionStart->format('h:i A') : '-') . ' - ' . ($sessionEnd ? $sessionEnd->format('h:i A') : '-');
                        ?>
                    <?php else: ?>
                        <span style="color: #999;">-</span>
                    <?php endif; ?>
                </td>
                <td><?php echo formatHoursToTime($item['regular_hours'] ?? 0); ?></td>
                <td class="<?php echo ($item['overtime_hours'] ?? 0) > 0 ? 'overtime' : ''; ?>">
                    <?php echo formatHoursToTime($item['overtime_hours'] ?? 0); ?>
                </td>
                <td><?php echo formatHoursToTime(($item['regular_hours'] ?? 0) + ($item['overtime_hours'] ?? 0)); ?></td>
                <td>
                    <?php if (isset($item['status'])): ?>
                        <?php if ($item['status'] === 'in'): ?>
                            <span class="badge badge-success">Checked In</span>
                        <?php else: ?>
                            <span class="badge badge-primary">Checked Out</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge badge-primary">Completed</span>
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
                <div style="font-size: 64px; margin-bottom: 20px; color: var(--primary-blue);"><i class="fas fa-clock"></i></div>
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
        // CRITICAL: Stop ALL timers IMMEDIATELY - do this FIRST before anything else
        console.log('Stop Timer: Clearing intervals...');
        
        // Stop work timer interval
        if (workTimerInterval) {
            console.log('Stop Timer: Clearing workTimerInterval');
            clearInterval(workTimerInterval);
            workTimerInterval = null;
        }
        
        // Stop any other timer intervals
        if (typeof timerInterval !== 'undefined' && timerInterval) {
            console.log('Stop Timer: Clearing timerInterval');
            clearInterval(timerInterval);
            timerInterval = null;
        }
        
        // Call stopTimer if it exists (old timer function)
        if (typeof stopTimer === 'function') {
            console.log('Stop Timer: Calling stopTimer()');
            try {
                stopTimer();
            } catch (e) {
                console.error('Stop Timer: Error calling stopTimer():', e);
            }
        }
        
        // Freeze the display immediately
        const timerDisplay = document.getElementById('timer-display');
        if (timerDisplay && workTimerStartTime) {
            const now = Date.now();
            const elapsed = Math.floor((now - workTimerStartTime) / 1000);
            const hours = Math.floor(elapsed / 3600);
            const minutes = Math.floor((elapsed % 3600) / 60);
            const seconds = elapsed % 60;
            timerDisplay.textContent = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            timerDisplay.style.color = 'var(--warning-yellow)';
            console.log('Stop Timer: Display frozen at', timerDisplay.textContent);
        }
        
        // Clear start time to prevent any further updates
        const savedStartTime = workTimerStartTime;
        workTimerStartTime = null;
        
        // Make API call
        console.log('Stop Timer: Making API call...');
        showLoader();
        ajaxRequest('/officepro/app/api/attendance/timer_stop.php', 'POST', {}, (response) => {
            hideLoader();
            console.log('Stop Timer: API response:', response);
            if (response.success) {
                showMessage('success', 'Timer stopped!');
                // Reload immediately to show stopped state
                setTimeout(() => {
                    console.log('Stop Timer: Reloading page...');
                    location.reload();
                }, 300);
            } else {
                showMessage('error', response.message || 'Failed to stop timer');
                console.error('Stop Timer: API call failed:', response.message);
                // Don't restore timer - keep it stopped
            }
        }, (error) => {
            hideLoader();
            showMessage('error', 'Failed to stop timer');
            console.error('Stop Timer: AJAX error:', error);
            // Don't restore timer - keep it stopped
        });
    }
    
    function resumeWorkTimer() {
        showLoader();
        ajaxRequest('/officepro/app/api/attendance/timer_resume.php', 'POST', {}, (response) => {
            hideLoader();
            if (response.success) {
                showMessage('success', 'Timer resumed!');
                setTimeout(() => location.reload(), 500);
            } else {
                showMessage('error', response.message || 'Failed to resume timer');
            }
        });
    }
    
    function endWorkTimer() {
        if (!confirm('End this timer session? The hours will be added to your work record.')) {
            return;
        }
        
        // CRITICAL: Stop the JavaScript timer immediately
        if (workTimerInterval) {
            clearInterval(workTimerInterval);
            workTimerInterval = null;
        }
        
        // Clear start time to prevent further updates
        workTimerStartTime = null;
        
        // Also stop the old attendance timer if it exists
        if (typeof stopTimer === 'function') {
            stopTimer();
        }
        if (typeof timerInterval !== 'undefined' && timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        
        // Update display to show stopped immediately
        const timerDisplay = document.getElementById('timer-display');
        if (timerDisplay) {
            timerDisplay.style.color = 'var(--success-green)';
        }
        
        showLoader();
        ajaxRequest('/officepro/app/api/attendance/timer_end.php', 'POST', {}, (response) => {
            hideLoader();
            if (response.success) {
                showMessage('success', 'Timer session ended! Regular: ' + response.data.regular_hours + 'h, Overtime: ' + response.data.overtime_hours + 'h');
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage('error', response.message || 'Failed to end timer');
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




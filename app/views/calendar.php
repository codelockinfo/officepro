<?php
/**
 * Calendar View - Shows Attendance, Leaves, Holidays, Tasks
 */

$pageTitle = 'Calendar';
include __DIR__ . '/includes/header.php';

require_once __DIR__ . '/../helpers/Database.php';
require_once __DIR__ . '/../helpers/Tenant.php';

$companyId = Tenant::getCurrentCompanyId();
$userId = $currentUser['id'];
$db = Database::getInstance();

// Get current month/year or from query
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');

$month = (int) $month;
$year = (int) $year;

// Get holidays for the month
$holidays = $db->fetchAll(
    "SELECT * FROM holidays 
    WHERE company_id = ? AND MONTH(date) = ? AND YEAR(date) = ? 
    ORDER BY date",
    [$companyId, $month, $year]
);

// Get leaves for the month (user's and team's if manager)
$isManager = Auth::hasRole(['company_owner', 'manager']);
if ($isManager) {
    $leaves = $db->fetchAll(
        "SELECT l.*, u.full_name as employee_name 
        FROM leaves l 
        JOIN users u ON l.user_id = u.id 
        WHERE l.company_id = ? AND l.status = 'approved' 
        AND ((MONTH(start_date) = ? AND YEAR(start_date) = ?) OR (MONTH(end_date) = ? AND YEAR(end_date) = ?))
        ORDER BY start_date",
        [$companyId, $month, $year, $month, $year]
    );
} else {
    $leaves = $db->fetchAll(
        "SELECT l.*, u.full_name as employee_name 
        FROM leaves l 
        JOIN users u ON l.user_id = u.id 
        WHERE l.company_id = ? AND l.user_id = ? 
        AND ((MONTH(start_date) = ? AND YEAR(start_date) = ?) OR (MONTH(end_date) = ? AND YEAR(end_date) = ?))
        ORDER BY start_date",
        [$companyId, $userId, $month, $year, $month, $year]
    );
}

// Get attendance for the month (user's own)
$attendance = $db->fetchAll(
    "SELECT DATE(date) as date_only, SUM(overtime_hours) as total_overtime 
    FROM attendance 
    WHERE company_id = ? AND user_id = ? AND MONTH(date) = ? AND YEAR(date) = ? AND status = 'out'
    GROUP BY DATE(date)",
    [$companyId, $userId, $month, $year]
);

// Create arrays for easy lookup
$holidayDates = [];
foreach ($holidays as $h) {
    $holidayDates[$h['date']] = $h['name'];
}

$leaveDates = [];
foreach ($leaves as $leave) {
    $start = new DateTime($leave['start_date']);
    $end = new DateTime($leave['end_date']);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
    
    foreach ($period as $date) {
        $dateStr = $date->format('Y-m-d');
        if (!isset($leaveDates[$dateStr])) {
            $leaveDates[$dateStr] = [];
        }
        $leaveDates[$dateStr][] = $leave;
    }
}

$attendanceDates = [];
$overtimeDates = [];
foreach ($attendance as $att) {
    $attendanceDates[$att['date_only']] = true;
    if ($att['total_overtime'] > 0) {
        $overtimeDates[$att['date_only']] = $att['total_overtime'];
    }
}

// Calculate calendar data
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$startDayOfWeek = date('w', $firstDay);

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1>📆 Calendar</h1>
    <?php if (Auth::hasRole(['company_owner'])): ?>
        <button onclick="openAddHolidayModal()" class="btn btn-primary">+ Add Holiday</button>
    <?php endif; ?>
</div>

<!-- Month Navigation -->
<div class="card" style="margin-bottom: 20px;">
    <div style="padding: 20px; display: flex; justify-content: space-between; align-items: center;">
        <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="btn btn-secondary">← Previous</a>
        <h2 style="margin: 0;"><?php echo date('F Y', $firstDay); ?></h2>
        <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="btn btn-secondary">Next →</a>
    </div>
</div>

<!-- Legend -->
<div class="card" style="margin-bottom: 20px;">
    <div style="padding: 15px; display: flex; gap: 20px; flex-wrap: wrap;">
        <div><span style="display: inline-block; width: 15px; height: 15px; background: #28a745; border-radius: 3px;"></span> Attendance</div>
        <div><span style="display: inline-block; width: 15px; height: 15px; background: #4da6ff; border-radius: 3px;"></span> Leave</div>
        <div><span style="display: inline-block; width: 15px; height: 15px; background: #dc3545; border-radius: 3px;"></span> Holiday</div>
        <div><span style="display: inline-block; width: 15px; height: 15px; background: #ff9933; border-radius: 3px;"></span> Overtime</div>
    </div>
</div>

<!-- Calendar Grid -->
<div class="card">
    <style>
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #ddd;
        }
        .calendar-day-header {
            background: var(--primary-blue);
            color: white;
            padding: 10px;
            text-align: center;
            font-weight: 600;
        }
        .calendar-day {
            background: white;
            min-height: 100px;
            padding: 5px;
            position: relative;
            cursor: pointer;
            transition: background 0.3s;
        }
        .calendar-day:hover {
            background: var(--light-blue);
        }
        .calendar-day.other-month {
            background: #f5f5f5;
        }
        .calendar-day.today {
            border: 2px solid var(--primary-blue);
        }
        .day-number {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .day-event {
            font-size: 10px;
            padding: 2px 4px;
            margin: 2px 0;
            border-radius: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .event-holiday { background: #dc3545; color: white; }
        .event-leave { background: #4da6ff; color: white; }
        .event-attendance { background: #28a745; color: white; }
        .event-overtime { background: #ff9933; color: white; }
    </style>
    
    <div class="calendar">
        <div class="calendar-day-header">Sun</div>
        <div class="calendar-day-header">Mon</div>
        <div class="calendar-day-header">Tue</div>
        <div class="calendar-day-header">Wed</div>
        <div class="calendar-day-header">Thu</div>
        <div class="calendar-day-header">Fri</div>
        <div class="calendar-day-header">Sat</div>
        
        <?php
        // Fill empty days at start
        for ($i = 0; $i < $startDayOfWeek; $i++) {
            echo '<div class="calendar-day other-month"></div>';
        }
        
        // Days of the month
        $today = date('Y-m-d');
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $isToday = ($date === $today);
            
            echo '<div class="calendar-day' . ($isToday ? ' today' : '') . '" onclick="viewDayDetails(\'' . $date . '\')">';
            echo '<div class="day-number">' . $day . '</div>';
            
            // Show holiday
            if (isset($holidayDates[$date])) {
                echo '<div class="day-event event-holiday">🎉 ' . htmlspecialchars($holidayDates[$date]) . '</div>';
            }
            
            // Show leave
            if (isset($leaveDates[$date])) {
                foreach ($leaveDates[$date] as $leave) {
                    $name = $isManager ? $leave['employee_name'] : 'Leave';
                    echo '<div class="day-event event-leave">📅 ' . htmlspecialchars($name) . '</div>';
                }
            }
            
            // Show attendance
            if (isset($attendanceDates[$date])) {
                echo '<div class="day-event event-attendance">✓ Present</div>';
            }
            
            // Show overtime
            if (isset($overtimeDates[$date])) {
                echo '<div class="day-event event-overtime">⏰ OT: ' . number_format($overtimeDates[$date], 1) . 'h</div>';
            }
            
            echo '</div>';
        }
        
        // Fill remaining days
        $remainingDays = (7 - (($startDayOfWeek + $daysInMonth) % 7)) % 7;
        for ($i = 0; $i < $remainingDays; $i++) {
            echo '<div class="calendar-day other-month"></div>';
        }
        ?>
    </div>
</div>

<!-- Add Holiday Modal (Company Owner Only) -->
<?php if (Auth::hasRole(['company_owner'])): ?>
<div id="add-holiday-modal" class="modal-overlay">
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h3 class="modal-title">Add Holiday</h3>
            <button type="button" class="modal-close" onclick="closeModal('add-holiday-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="holiday-form" onsubmit="submitHoliday(event)">
                <div class="form-group">
                    <label class="form-label" for="holiday_name">Holiday Name *</label>
                    <input type="text" id="holiday_name" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="holiday_date">Date *</label>
                    <input type="date" id="holiday_date" name="date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="recurring" name="recurring" value="1">
                        <span>Recurring (every year)</span>
                    </label>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('add-holiday-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Holiday</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    function openAddHolidayModal() {
        document.getElementById('holiday-form').reset();
        openModal('add-holiday-modal');
    }
    
    function submitHoliday(event) {
        event.preventDefault();
        const formData = new FormData(event.target);
        const data = {
            name: formData.get('name'),
            date: formData.get('date'),
            recurring: formData.get('recurring') ? 1 : 0
        };
        
        ajaxRequest('/officepro/app/api/admin/holidays.php?action=create', 'POST', data, (response) => {
            if (response.success) {
                showMessage('success', 'Holiday added successfully!');
                closeModal('add-holiday-modal');
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage('error', response.message || 'Failed to add holiday');
            }
        });
    }
    
    function viewDayDetails(date) {
        // This could be expanded to show more details
        console.log('View details for:', date);
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>




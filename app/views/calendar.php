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

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <h1 style="display: flex; align-items: center; gap: 10px; margin: 0;">
        <i class="fas fa-calendar-alt" style="color: var(--primary-blue);"></i> Calendar
    </h1>
    <?php if (Auth::hasRole(['company_owner'])): ?>
        <button onclick="openAddHolidayModal()" class="btn btn-primary custom-btn-primary">+ Add Holiday</button>

    <?php endif; ?>
</div>

<!-- Month Navigation -->
<div class="card" style="margin-bottom: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
    <div style="padding: 25px; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, var(--primary-blue) 0%, #3d8ce6 100%); border-radius: 12px;">
        <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" 
           class="btn btn-secondary" 
           style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 10px 20px; border-radius: 8px; transition: all 0.3s; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-chevron-left"></i> Previous
        </a>
        <h2 style="margin: 0; color: white; font-size: 28px; font-weight: 600; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
            <?php echo date('F Y', $firstDay); ?>
        </h2>
        <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" 
           class="btn btn-secondary" 
           style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 10px 20px; border-radius: 8px; transition: all 0.3s; display: flex; align-items: center; gap: 8px;">
            Next <i class="fas fa-chevron-right"></i>
        </a>
    </div>
</div>

<!-- Legend -->
<div class="card" style="margin-bottom: 25px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.08);">
    <div style="padding: 20px;">
        <h3 style="margin: 0 0 15px 0; color: var(--primary-blue); font-size: 16px; font-weight: 600;">
            <i class="fas fa-info-circle"></i> Legend
        </h3>
        <div style="display: flex; gap: 30px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="display: inline-block; width: 20px; height: 20px; background: #28a745; border-radius: 5px; box-shadow: 0 2px 4px rgba(40,167,69,0.3);"></span>
                <span style="font-weight: 500;">Attendance</span>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="display: inline-block; width: 20px; height: 20px; background: #4da6ff; border-radius: 5px; box-shadow: 0 2px 4px rgba(77,166,255,0.3);"></span>
                <span style="font-weight: 500;">Leave</span>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="display: inline-block; width: 20px; height: 20px; background: #dc3545; border-radius: 5px; box-shadow: 0 2px 4px rgba(220,53,69,0.3);"></span>
                <span style="font-weight: 500;">Holiday</span>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="display: inline-block; width: 20px; height: 20px; background: #ff9933; border-radius: 5px; box-shadow: 0 2px 4px rgba(255,153,51,0.3);"></span>
                <span style="font-weight: 500;">Overtime</span>
            </div>
        </div>
    </div>
</div>

<!-- Calendar Grid -->
<div class="card" style="border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); overflow: hidden; padding: 0;">
    <style>
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0;
            background: #e8e8e8;
            border-radius: 12px;
            overflow: hidden;
        }
        .calendar-day-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #3d8ce6 100%);
            color: white;
            padding: 15px 10px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        .calendar-day-header:last-child {
            border-right: none;
        }
        .calendar-day {
            background: white;
            min-height: 120px;
            padding: 10px 8px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
            border-right: 1px solid #e8e8e8;
            border-bottom: 1px solid #e8e8e8;
            display: flex;
            flex-direction: column;
        }
        .calendar-day:hover {
            background: var(--light-blue);
            transform: scale(1.01);
            z-index: 1;
            box-shadow: 0 0 2px rgba(0,0,0,0.15);
        }
        .calendar-day.other-month {
            background: #f8f9fa;
            color: #adb5bd;
        }
        .calendar-day.other-month:hover {
            background: #e9ecef;
        }
        .calendar-day.today {
            background: linear-gradient(135deg, #e6f2ff 0%, #ffffff 100%);
            border: 2px solid var(--primary-blue);
            box-shadow: 0 0 0 2px rgba(77, 166, 255, 0.2);
        }
        .calendar-day.today .day-number {
            color: var(--primary-blue);
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
        }
        .day-number {
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 15px;
            color: #333;
            width: fit-content;
        }
        .day-events {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
            overflow-y: auto;
        }
        .day-event {
            font-size: 11px;
            padding: 4px 6px;
            margin: 0;
            border-radius: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .day-event i {
            font-size: 10px;
        }
        .event-holiday { 
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); 
            color: white; 
        }
        .event-leave { 
            background: linear-gradient(135deg, #4da6ff 0%, #3d8ce6 100%); 
            color: white; 
        }
        .event-attendance { 
            background: linear-gradient(135deg, #28a745 0%, #218838 100%); 
            color: white; 
        }
        .event-overtime { 
            background: linear-gradient(135deg, #ff9933 0%, #ff8800 100%); 
            color: white; 
        }
        @media (max-width: 768px) {
            .calendar-day {
                min-height: 80px;
                padding: 6px 4px;
            }
            .day-event {
                font-size: 9px;
                padding: 3px 4px;
            }
            .calendar-day-header {
                padding: 10px 5px;
                font-size: 12px;
            }
        }
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
            echo '<div class="day-events">';
            
            // Show holiday
            if (isset($holidayDates[$date])) {
                echo '<div class="day-event event-holiday"><i class="fas fa-gift"></i> ' . htmlspecialchars($holidayDates[$date]) . '</div>';
            }
            
            // Show leave
            if (isset($leaveDates[$date])) {
                foreach ($leaveDates[$date] as $leave) {
                    $name = $isManager ? $leave['employee_name'] : 'Leave';
                    echo '<div class="day-event event-leave"><i class="fas fa-calendar-alt"></i> ' . htmlspecialchars($name) . '</div>';
                }
            }
            
            // Show attendance
            if (isset($attendanceDates[$date])) {
                echo '<div class="day-event event-attendance"><i class="fas fa-check-circle"></i> Present</div>';
            }
            
            // Show overtime
            if (isset($overtimeDates[$date])) {
                echo '<div class="day-event event-overtime"><i class="fas fa-clock"></i> OT: ' . number_format($overtimeDates[$date], 1) . 'h</div>';
            }
            
            echo '</div></div>';
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
        // This could be expanded to show more details in a modal
        const dateObj = new Date(date);
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const formattedDate = dateObj.toLocaleDateString('en-US', options);
        console.log('View details for:', formattedDate);
        // You can add a modal here to show day details
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>




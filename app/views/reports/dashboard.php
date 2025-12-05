<?php
/**
 * Reports Dashboard
 */

$pageTitle = 'Reports Dashboard';
include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Tenant.php';

// Only managers and owners can access
Auth::checkRole(['company_owner', 'manager'], 'Only managers and company owners can access reports.');

$companyId = Tenant::getCurrentCompanyId();
$db = Database::getInstance();

// Today's stats
$today = date('Y-m-d');

$presentToday = $db->fetchOne(
    "SELECT COUNT(DISTINCT user_id) as count 
    FROM attendance 
    WHERE company_id = ? AND date = ? AND status = 'out'",
    [$companyId, $today]
);

$onLeaveToday = $db->fetchOne(
    "SELECT COUNT(DISTINCT user_id) as count 
    FROM leaves 
    WHERE company_id = ? AND status = 'approved' AND ? BETWEEN start_date AND end_date",
    [$companyId, $today]
);

// This month's overtime
$currentMonth = date('Y-m');
$overtimeThisMonth = $db->fetchOne(
    "SELECT SUM(overtime_hours) as total 
    FROM attendance 
    WHERE company_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?",
    [$companyId, $currentMonth]
);

// Late arrivals this month (after 9:15 AM)
$lateArrivals = $db->fetchOne(
    "SELECT COUNT(*) as count 
    FROM attendance 
    WHERE company_id = ? AND DATE_FORMAT(date, '%Y-%m') = ? 
    AND TIME(check_in) > '09:15:00'",
    [$companyId, $currentMonth]
);

// Top overtime employees this month
$topOvertime = $db->fetchAll(
    "SELECT u.full_name, SUM(a.overtime_hours) as total_overtime 
    FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.company_id = ? AND DATE_FORMAT(a.date, '%Y-%m') = ? 
    GROUP BY a.user_id, u.full_name 
    HAVING total_overtime > 0 
    ORDER BY total_overtime DESC 
    LIMIT 5",
    [$companyId, $currentMonth]
);

// Total employees
$totalEmployees = $db->fetchOne(
    "SELECT COUNT(*) as count FROM users WHERE company_id = ? AND status = 'active'",
    [$companyId]
);
?>

<h1>📈 Reports Dashboard</h1>

<!-- KPI Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;">
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--success-green); margin-bottom: 10px;">Present Today</h3>
        <div style="font-size: 48px; font-weight: bold; color: var(--success-green);">
            <?php echo $presentToday['count'] ?? 0; ?>
        </div>
        <p style="color: #666;">out of <?php echo $totalEmployees['count']; ?> employees</p>
    </div>
    
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--primary-blue); margin-bottom: 10px;">On Leave</h3>
        <div style="font-size: 48px; font-weight: bold; color: var(--primary-blue);">
            <?php echo $onLeaveToday['count'] ?? 0; ?>
        </div>
        <p style="color: #666;">employees</p>
    </div>
    
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--overtime-orange); margin-bottom: 10px;">Overtime (Month)</h3>
        <div style="font-size: 48px; font-weight: bold; color: var(--overtime-orange);">
            <?php echo number_format($overtimeThisMonth['total'] ?? 0, 1); ?>
        </div>
        <p style="color: #666;">hours</p>
    </div>
    
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--warning-yellow); margin-bottom: 10px;">Late Arrivals</h3>
        <div style="font-size: 48px; font-weight: bold; color: #856404;">
            <?php echo $lateArrivals['count'] ?? 0; ?>
        </div>
        <p style="color: #666;">this month</p>
    </div>
</div>

<!-- Top Overtime Employees -->
<?php if (count($topOvertime) > 0): ?>
<div class="card" style="margin-bottom: 20px;">
    <h2 class="card-title">Top Overtime Employees (This Month)</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Total Overtime Hours</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($topOvertime as $emp): ?>
            <tr>
                <td><?php echo htmlspecialchars($emp['full_name']); ?></td>
                <td style="color: var(--overtime-orange); font-weight: bold;">
                    <?php echo number_format($emp['total_overtime'], 2); ?> hours
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Generate Reports -->
<div class="card">
    <h2 class="card-title">Generate Reports</h2>
    <div style="padding: 20px;">
        <form id="report-form" style="display: grid; gap: 20px;">
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label" for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="employee_id">Employee (Optional - leave blank for all)</label>
                <select id="employee_id" name="employee_id" class="form-control">
                    <option value="">All Employees</option>
                    <?php
                    $employees = $db->fetchAll(
                        "SELECT id, full_name FROM users WHERE company_id = ? AND status = 'active' ORDER BY full_name",
                        [$companyId]
                    );
                    foreach ($employees as $emp) {
                        echo '<option value="' . $emp['id'] . '">' . htmlspecialchars($emp['full_name']) . '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="button" onclick="generateReport('view')" class="btn btn-primary">View Report</button>
                <button type="button" onclick="generateReport('csv')" class="btn btn-secondary">Export CSV</button>
                <button type="button" onclick="generateReport('pdf')" class="btn btn-secondary">Export PDF</button>
            </div>
        </form>
        
        <div id="report-results" style="margin-top: 30px;"></div>
    </div>
</div>

<script>
    function generateReport(format) {
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        const employeeId = document.getElementById('employee_id').value;
        
        if (!startDate || !endDate) {
            showMessage('error', 'Please select start and end dates');
            return;
        }
        
        const params = new URLSearchParams({
            start_date: startDate,
            end_date: endDate,
            employee_id: employeeId,
            format: format
        });
        
        if (format === 'view') {
            ajaxRequest(`/officepro/app/api/reports/attendance.php?${params}`, 'GET', null, (response) => {
                if (response.success) {
                    displayReport(response.data);
                } else {
                    showMessage('error', response.message || 'Failed to generate report');
                }
            });
        } else {
            // For CSV and PDF, open in new window
            window.open(`/officepro/app/api/reports/export.php?${params}`, '_blank');
        }
    }
    
    function displayReport(data) {
        if (data.length === 0) {
            document.getElementById('report-results').innerHTML = '<p style="text-align: center; padding: 40px;">No data found for selected period</p>';
            return;
        }
        
        let html = '<h3>Attendance Report</h3><table class="table"><thead><tr>';
        html += '<th>Employee</th><th>Date</th><th>Check In</th><th>Check Out</th>';
        html += '<th>Regular Hours</th><th>Overtime Hours</th><th>Total Hours</th></tr></thead><tbody>';
        
        let totalRegular = 0;
        let totalOvertime = 0;
        
        data.forEach(row => {
            const regular = parseFloat(row.regular_hours);
            const overtime = parseFloat(row.overtime_hours);
            totalRegular += regular;
            totalOvertime += overtime;
            
            html += '<tr>';
            html += `<td>${row.employee_name}</td>`;
            html += `<td>${row.date}</td>`;
            html += `<td>${row.check_in}</td>`;
            html += `<td>${row.check_out || '-'}</td>`;
            html += `<td>${regular.toFixed(2)}</td>`;
            html += `<td style="color: var(--overtime-orange);">${overtime.toFixed(2)}</td>`;
            html += `<td>${(regular + overtime).toFixed(2)}</td>`;
            html += '</tr>';
        });
        
        html += '<tr style="font-weight: bold; background: var(--light-blue);"><td colspan="4">TOTAL</td>';
        html += `<td>${totalRegular.toFixed(2)}</td>`;
        html += `<td style="color: var(--overtime-orange);">${totalOvertime.toFixed(2)}</td>`;
        html += `<td>${(totalRegular + totalOvertime).toFixed(2)}</td></tr>`;
        html += '</tbody></table>';
        
        document.getElementById('report-results').innerHTML = html;
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>




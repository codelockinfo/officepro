<?php
/**
 * Employee Leave Management Page
 */

$pageTitle = 'My Leaves';
include __DIR__ . '/includes/header.php';

require_once __DIR__ . '/../helpers/Database.php';
require_once __DIR__ . '/../helpers/Tenant.php';

$companyId = Tenant::getCurrentCompanyId();
$userId = $currentUser['id'];
$db = Database::getInstance();

// Get selected month/year from query parameter (default to current month)
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month and year
if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = date('n');
}
if ($selectedYear < 2020 || $selectedYear > 2100) {
    $selectedYear = date('Y');
}

// Get paid leave allocation from company settings (default to 12) - This is YEARLY allocation
$paidLeaveAllocation = floatval(Tenant::getCompanySetting('paid_leave_allocation', '12'));

// Calculate taken leave for the ENTIRE YEAR (not just selected month)
$takenLeave = $db->fetchOne(
    "SELECT COALESCE(SUM(days_count), 0) as total_days
     FROM leaves 
     WHERE company_id = ? AND user_id = ? 
     AND status = 'approved'
     AND YEAR(start_date) = ?",
    [$companyId, $userId, $selectedYear]
);
$takenLeaveDays = floatval($takenLeave['total_days'] ?? 0);

// Calculate remaining paid leave for the year
$remainingPaidLeave = max(0, $paidLeaveAllocation - $takenLeaveDays);

// Get leave history for selected month
$leaves = $db->fetchAll(
    "SELECT l.*, u.full_name as approved_by_name 
    FROM leaves l 
    LEFT JOIN users u ON l.approved_by = u.id 
    WHERE l.company_id = ? AND l.user_id = ? 
    AND YEAR(l.start_date) = ? AND MONTH(l.start_date) = ?
    ORDER BY l.created_at DESC",
    [$companyId, $userId, $selectedYear, $selectedMonth]
);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
    <h1><i class="fas fa-calendar-alt"></i> My Leaves</h1>
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <div class="form-group" style="margin: 0; position: relative;">
            <select id="month_selector" class="form-control month-selector" onchange="filterByMonth()" style="min-width: 220px; padding: 10px 40px 10px 15px; border: 2px solid var(--primary-blue); border-radius: 25px; background: var(--white); color: var(--primary-blue); font-weight: 500; font-size: 14px; cursor: pointer; appearance: none; -webkit-appearance: none; -moz-appearance: none; transition: all 0.3s;">
                <?php
                $months = [
                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                ];
                $currentYear = date('Y');
                $currentMonth = date('n');
                
                // Show months from previous year to current month only
                for ($year = $currentYear - 1; $year <= $currentYear; $year++) {
                    $startMonth = ($year == $currentYear - 1) ? 1 : 1;
                    $endMonth = ($year == $currentYear) ? $currentMonth : 12;
                    
                    foreach ($months as $monthNum => $monthName) {
                        // Skip future months
                        if ($year > $currentYear || ($year == $currentYear && $monthNum > $currentMonth)) {
                            continue;
                        }
                        
                        $value = $year . '-' . str_pad($monthNum, 2, '0', STR_PAD_LEFT);
                        $selected = ($selectedYear == $year && $selectedMonth == $monthNum) ? 'selected' : '';
                        $display = $monthName . ' ' . $year;
                        if ($year == $currentYear && $monthNum == $currentMonth) {
                            $display .= ' (Current)';
                        }
                        echo "<option value=\"{$value}\" {$selected}>{$display}</option>";
                    }
                }
                ?>
            </select>
            <i class="fas fa-chevron-down" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: var(--primary-blue); pointer-events: none; font-size: 12px;"></i>
        </div>
        <button onclick="openRequestLeaveModal()" class="btn btn-primary custom-btn-primary"><i class="fas fa-plus"></i> Request Leave</button>
    </div>
</div>

<!-- Leave Balance Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--primary-blue); margin-bottom: 10px;">Paid Leave</h3>
        <div style="font-size: 36px; font-weight: bold; color: var(--primary-blue);">
            <?php echo number_format($remainingPaidLeave, 1); ?>
        </div>
        <p style="color: #666;">days remaining</p>
        <small style="color: #999; font-size: 12px;">Yearly Allocation: <?php echo number_format($paidLeaveAllocation, 1); ?> days (<?php echo $selectedYear; ?>)</small>
    </div>
    
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--success-green); margin-bottom: 10px;">Taken Leave</h3>
        <div style="font-size: 36px; font-weight: bold; color: var(--success-green);">
            <?php echo number_format($takenLeaveDays, 1); ?>
        </div>
        <p style="color: #666;">days taken</p>
        <small style="color: #999; font-size: 12px;">Total for <?php echo $selectedYear; ?></small>
    </div>
</div>

<!-- Leave History -->
<div class="card">
    <h2 class="card-title">Leave History - <?php echo date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)); ?></h2>
    
    <?php if (count($leaves) === 0): ?>
        <p style="text-align: center; padding: 40px; color: #666;">No leave requests yet</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaves as $leave): ?>
                <tr>
                    <td>
                        <?php
                        $typeLabels = [
                            'paid_leave' => 'Paid Leave'
                        ];
                        $leaveTypeLabel = $typeLabels[$leave['leave_type']] ?? $leave['leave_type'];
                        
                        // Add half day information if applicable
                        if (isset($leave['leave_duration']) && $leave['leave_duration'] === 'half_day') {
                            $period = isset($leave['half_day_period']) ? ucfirst($leave['half_day_period']) : '';
                            $leaveTypeLabel .= ' (Half Day';
                            if ($period) {
                                $leaveTypeLabel .= ' - ' . $period;
                            }
                            $leaveTypeLabel .= ')';
                        }
                        echo $leaveTypeLabel;
                        ?>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($leave['start_date'])); ?></td>
                    <td><?php 
                        // For half days, show same date or just show start date
                        if (isset($leave['leave_duration']) && $leave['leave_duration'] === 'half_day') {
                            echo date('M d, Y', strtotime($leave['start_date']));
                        } else {
                            echo date('M d, Y', strtotime($leave['end_date']));
                        }
                    ?></td>
                    <td><?php 
                        // Format days count - show 0.5 as "0.5" or "½"
                        if ($leave['days_count'] == 0.5 || $leave['days_count'] == '0.5') {
                            echo '0.5';
                        } else {
                            echo number_format($leave['days_count'], 1);
                        }
                    ?></td>
                    <td><?php echo htmlspecialchars(substr($leave['reason'], 0, 50)) . (strlen($leave['reason']) > 50 ? '...' : ''); ?></td>
                    <td>
                        <?php
                        $statusClasses = [
                            'pending' => 'badge-warning',
                            'approved' => 'badge-success',
                            'declined' => 'badge-danger'
                        ];
                        ?>
                        <span class="badge <?php echo $statusClasses[$leave['status']]; ?>">
                            <?php echo ucfirst($leave['status']); ?>
                        </span>
                    </td>
                    <td>
                        <button onclick="viewLeaveDetails(<?php echo $leave['id']; ?>)" class="btn btn-sm btn-primary">View</button>
                        <?php if ($leave['status'] === 'pending'): ?>
                            <button onclick="cancelLeave(<?php echo $leave['id']; ?>)" class="btn btn-sm btn-danger">Cancel</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Request Leave Modal -->
<div id="request-leave-modal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Request Leave</h3>
            <button type="button" class="modal-close" onclick="closeModal('request-leave-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="leave-request-form" enctype="multipart/form-data" onsubmit="submitLeaveRequest(event)">
                <div class="form-group">
                    <label class="form-label">Leave Duration *</label>
                    <div style="display: flex; gap: 20px; margin-top: 8px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="radio" name="leave_duration" value="full_day" id="leave_duration_full" checked onchange="handleLeaveDurationChange()" style="margin-right: 8px; width: auto;">
                            <span>Full Day Leave</span>
                        </label>
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="radio" name="leave_duration" value="half_day" id="leave_duration_half" onchange="handleLeaveDurationChange()" style="margin-right: 8px; width: auto;">
                            <span>Half Day Leave</span>
                        </label>
                    </div>
                </div>
                
                <div class="form-group" id="half_day_period_group" style="display: none;">
                    <label class="form-label" for="half_day_period">Half Day Period *</label>
                    <select id="half_day_period" name="half_day_period" class="form-control">
                        <option value="morning">Morning</option>
                        <option value="afternoon">Afternoon</option>
                    </select>
                </div>
                
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label" for="start_date">Start Date *</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" required onchange="handleStartDateChange()">
                    </div>
                    
                    <div class="form-group" id="end_date_group">
                        <label class="form-label" for="end_date">End Date *</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" required onchange="calculateDays()">
                    </div>
                </div>
                
                <div class="alert alert-info" id="days-info" style="display: none;">
                    Total days: <strong id="days-count">0</strong>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="reason">Reason *</label>
                    <textarea id="reason" name="reason" class="form-control" rows="4" required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="attachment">Attachment (Optional)</label>
                    <input type="file" id="attachment" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    <small class="text-muted">Max 2MB (PDF, DOC, DOCX, JPG, PNG)</small>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('request-leave-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openRequestLeaveModal() {
        document.getElementById('leave-request-form').reset();
        document.getElementById('days-info').style.display = 'none';
        document.getElementById('half_day_period_group').style.display = 'none';
        document.getElementById('leave_duration_full').checked = true;
        handleLeaveDurationChange();
        openModal('request-leave-modal');
    }
    
    function handleLeaveDurationChange() {
        const isHalfDay = document.getElementById('leave_duration_half').checked;
        const endDateGroup = document.getElementById('end_date_group');
        const endDateInput = document.getElementById('end_date');
        const halfDayPeriodGroup = document.getElementById('half_day_period_group');
        const startDate = document.getElementById('start_date').value;
        
        if (isHalfDay) {
            // Hide end date for half day
            endDateGroup.style.display = 'none';
            endDateInput.required = false;
            // Set end date to start date
            if (startDate) {
                endDateInput.value = startDate;
            }
            // Show half day period selector
            halfDayPeriodGroup.style.display = 'block';
            document.getElementById('half_day_period').required = true;
        } else {
            // Show end date for full day
            endDateGroup.style.display = 'block';
            endDateInput.required = true;
            // Hide half day period selector
            halfDayPeriodGroup.style.display = 'none';
            document.getElementById('half_day_period').required = false;
        }
        calculateDays();
    }
    
    function handleStartDateChange() {
        const isHalfDay = document.getElementById('leave_duration_half').checked;
        if (isHalfDay) {
            const startDate = document.getElementById('start_date').value;
            if (startDate) {
                document.getElementById('end_date').value = startDate;
            }
        }
        calculateDays();
    }
    
    function calculateDays() {
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        const isHalfDay = document.getElementById('leave_duration_half').checked;
        
        if (startDate) {
            let daysCount = 0;
            
            if (isHalfDay) {
                // Half day = 0.5 days
                daysCount = 0.5;
            } else if (endDate) {
                // Full day calculation
                const start = new Date(startDate);
                const end = new Date(endDate);
                const diffTime = Math.abs(end - start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                daysCount = diffDays;
            }
            
            if (daysCount > 0) {
                document.getElementById('days-count').textContent = daysCount;
                document.getElementById('days-info').style.display = 'block';
            } else {
                document.getElementById('days-info').style.display = 'none';
            }
        } else {
            document.getElementById('days-info').style.display = 'none';
        }
    }
    
    function submitLeaveRequest(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        
        showLoader();
        
            fetch('/officepro/app/api/leaves/request.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoader();
            if (data.success) {
                showMessage('success', 'Leave request submitted successfully!');
                closeModal('request-leave-modal');
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage('error', data.message || 'Failed to submit leave request');
            }
        })
        .catch(error => {
            hideLoader();
            showMessage('error', 'An error occurred. Please try again.');
        });
    }
    
    function viewLeaveDetails(id) {
        ajaxRequest(`/officepro/app/api/leaves/view.php?id=${id}`, 'GET', null, (response) => {
            if (response.success) {
                const leave = response.data;
                const typeLabels = {
                    'paid_leave': 'Paid Leave'
                };
                
                let leaveTypeLabel = typeLabels[leave.leave_type] || leave.leave_type;
                if (leave.leave_duration === 'half_day') {
                    const period = leave.half_day_period ? ` - ${leave.half_day_period.charAt(0).toUpperCase() + leave.half_day_period.slice(1)}` : '';
                    leaveTypeLabel += ` (Half Day${period})`;
                }
                
                let content = `
                    <div style="padding: 20px;">
                        <p><strong>Leave Type:</strong> ${leaveTypeLabel}</p>
                        <p><strong>Start Date:</strong> ${new Date(leave.start_date).toLocaleDateString()}</p>
                        ${leave.leave_duration === 'half_day' ? '' : `<p><strong>End Date:</strong> ${new Date(leave.end_date).toLocaleDateString()}</p>`}
                        <p><strong>Days:</strong> ${parseFloat(leave.days_count) === 0.5 ? '0.5' : leave.days_count}</p>
                        <p><strong>Status:</strong> <span class="badge badge-${leave.status === 'approved' ? 'success' : leave.status === 'declined' ? 'danger' : 'warning'}">${leave.status.toUpperCase()}</span></p>
                        <p><strong>Reason:</strong><br>${leave.reason}</p>
                `;
                
                if (leave.comments) {
                    content += `<p><strong>Manager Comments:</strong><br>${leave.comments}</p>`;
                }
                
                if (leave.attachment) {
                    content += `<p><strong>Attachment:</strong> <a href="/${leave.attachment}" target="_blank">Download</a></p>`;
                }
                
                content += '</div>';
                
                const footer = `<button class="btn btn-primary" onclick="closeModal(this.closest('.modal-overlay').id)">Close</button>`;
                createModal('Leave Details', content, footer);
            }
        });
    }
    
    function cancelLeave(id) {
        confirmDialog(
            'This action cannot be undone.',
            () => {
                ajaxRequest(`/officepro/app/api/leaves/cancel.php?id=${id}`, 'POST', null, (response) => {
                    if (response.success) {
                        showMessage('success', 'Leave request cancelled');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showMessage('error', response.message || 'Failed to cancel leave');
                    }
                });
            },
            null,
            'Cancel Leave Request',
            '<i class="fas fa-calendar-alt"></i>'
        );
    }
    
    function filterByMonth() {
        const selectedValue = document.getElementById('month_selector').value;
        const [year, month] = selectedValue.split('-');
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('year', year);
        currentUrl.searchParams.set('month', month);
        window.location.href = currentUrl.toString();
    }
    
    // Add hover effect for month selector
    document.addEventListener('DOMContentLoaded', function() {
        const monthSelector = document.getElementById('month_selector');
        if (monthSelector) {
            monthSelector.addEventListener('mouseenter', function() {
                this.style.background = 'var(--light-blue)';
                this.style.borderColor = '#3d8ce6';
            });
            monthSelector.addEventListener('mouseleave', function() {
                this.style.background = 'var(--white)';
                this.style.borderColor = 'var(--primary-blue)';
            });
            monthSelector.addEventListener('focus', function() {
                this.style.background = 'var(--light-blue)';
                this.style.borderColor = '#3d8ce6';
                this.style.outline = 'none';
                this.style.boxShadow = '0 0 0 3px rgba(77, 166, 255, 0.2)';
            });
            monthSelector.addEventListener('blur', function() {
                this.style.background = 'var(--white)';
                this.style.borderColor = 'var(--primary-blue)';
                this.style.boxShadow = 'none';
            });
        }
    });
</script>

<style>
    .month-selector option {
        padding: 10px;
        background: var(--white);
        color: var(--dark-gray);
    }
    
    .month-selector option:hover {
        background: var(--light-blue);
    }
    
    .month-selector option:checked {
        background: var(--primary-blue);
        color: white;
    }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>




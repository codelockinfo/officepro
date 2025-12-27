<?php
/**
 * Leave Approval Page (Manager/Owner)
 */

$pageTitle = 'Leave Approvals';
include __DIR__ . '/includes/header.php';

require_once __DIR__ . '/../helpers/Database.php';
require_once __DIR__ . '/../helpers/Tenant.php';

// Set timezone from config
$appConfig = require __DIR__ . '/../config/app.php';
date_default_timezone_set($appConfig['timezone']);

// Only managers and owners can access
Auth::checkRole(['company_owner', 'manager'], 'Only managers and company owners can approve leaves.');

$companyId = Tenant::getCurrentCompanyId();
$db = Database::getInstance();

// Get pending leaves
$pendingLeaves = $db->fetchAll(
    "SELECT l.*, u.full_name as employee_name, u.email as employee_email 
    FROM leaves l 
    JOIN users u ON l.user_id = u.id 
    WHERE l.company_id = ? AND l.status = 'pending' 
    ORDER BY l.created_at DESC",
    [$companyId]
);

// Get all leaves (approved/declined)
$allLeaves = $db->fetchAll(
    "SELECT l.*, u.full_name as employee_name, a.full_name as approved_by_name 
    FROM leaves l 
    JOIN users u ON l.user_id = u.id 
    LEFT JOIN users a ON l.approved_by = a.id 
    WHERE l.company_id = ? AND l.status != 'pending' 
    ORDER BY l.updated_at DESC 
    LIMIT 50",
    [$companyId]
);
?>

<h1><i class="fas fa-check-circle"></i> Leave Approvals</h1>

<!-- Pending Approvals -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title">Pending Approvals (<?php echo count($pendingLeaves); ?>)</h2>
    </div>
    
    <?php if (count($pendingLeaves) === 0): ?>
        <p style="text-align: center; padding: 40px; color: #666;">No pending leave requests</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>Requested On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingLeaves as $leave): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($leave['employee_name']); ?></strong></td>
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
                        // Format days count - show 0.5 as "0.5"
                        if ($leave['days_count'] == 0.5 || $leave['days_count'] == '0.5') {
                            echo '0.5';
                        } else {
                            echo number_format($leave['days_count'], 1);
                        }
                    ?></td>
                    <td><?php 
                        // MySQL TIMESTAMP columns are stored in UTC internally
                        // Convert from UTC to India/Kolkata (IST = UTC+5:30)
                        try {
                            // Try to parse as UTC first (most reliable)
                            $utcTime = new DateTime($leave['created_at'], new DateTimeZone('UTC'));
                            $istTime = clone $utcTime;
                            $istTime->setTimezone(new DateTimeZone('Asia/Kolkata'));
                            echo $istTime->format('M d, Y h:i A');
                        } catch (Exception $e) {
                            // Fallback: assume it's already in IST
                            $dateTime = new DateTime($leave['created_at'], new DateTimeZone('Asia/Kolkata'));
                            echo $dateTime->format('M d, Y h:i A');
                        }
                    ?></td>
                    <td>
                        <button onclick="viewLeaveForApproval(<?php echo $leave['id']; ?>)" class="btn btn-sm btn-primary">View & Approve</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Recent Approved/Declined -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h2 class="card-title">Recent Actions</h2>
    </div>
    
    <?php if (count($allLeaves) === 0): ?>
        <p style="text-align: center; padding: 40px; color: #666;">No approved/declined leaves yet</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Dates</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Approved By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allLeaves as $leave): ?>
                <tr>
                    <td><?php echo htmlspecialchars($leave['employee_name']); ?></td>
                    <td>
                        <?php
                        $typeLabels = [
                            'paid_leave' => 'Paid Leave',
                            'sick_leave' => 'Sick Leave',
                            'casual_leave' => 'Casual Leave',
                            'work_from_home' => 'WFH'
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
                    <td><?php 
                        // For half days, show only start date
                        if (isset($leave['leave_duration']) && $leave['leave_duration'] === 'half_day') {
                            echo date('M d, Y', strtotime($leave['start_date']));
                        } else {
                            echo date('M d', strtotime($leave['start_date'])) . ' - ' . date('M d, Y', strtotime($leave['end_date']));
                        }
                    ?></td>
                    <td><?php 
                        // Format days count - show 0.5 as "0.5"
                        if ($leave['days_count'] == 0.5 || $leave['days_count'] == '0.5') {
                            echo '0.5';
                        } else {
                            echo number_format($leave['days_count'], 1);
                        }
                    ?></td>
                    <td>
                        <span class="badge badge-<?php echo $leave['status'] === 'approved' ? 'success' : 'danger'; ?>">
                            <?php echo ucfirst($leave['status']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($leave['approved_by_name'] ?? '-'); ?></td>
                    <td>
                        <button onclick="viewLeaveDetails(<?php echo $leave['id']; ?>)" class="btn btn-sm btn-primary">View</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Approval Modal -->
<div id="approval-modal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Leave Approval</h3>
            <button type="button" class="modal-close" onclick="closeModal('approval-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="leave-details"></div>
            
            <form id="approval-form" onsubmit="submitApproval(event)" style="margin-top: 20px; border-top: 1px solid #ddd; padding-top: 20px;">
                <input type="hidden" id="leave_id" name="leave_id">
                <input type="hidden" id="action_type" name="action">
                
                <div class="form-group">
                    <label class="form-label" for="comments">Comments (Optional)</label>
                    <textarea id="comments" name="comments" class="form-control" rows="3" placeholder="Add your comments..."></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('approval-modal')">Close</button>
                    <button type="button" class="btn btn-danger" onclick="prepareAction('decline')">Decline</button>
                    <button type="button" class="btn btn-success" onclick="prepareAction('approve')">Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function viewLeaveForApproval(id) {
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
                
                let details = `
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 5px;">
                        <p><strong>Employee:</strong> ${leave.employee_name || 'N/A'}</p>
                        <p><strong>Leave Type:</strong> ${leaveTypeLabel}</p>
                        <p><strong>Start Date:</strong> ${new Date(leave.start_date).toLocaleDateString()}</p>
                        ${leave.leave_duration === 'half_day' ? '' : `<p><strong>End Date:</strong> ${new Date(leave.end_date).toLocaleDateString()}</p>`}
                        <p><strong>Total Days:</strong> ${parseFloat(leave.days_count) === 0.5 ? '0.5' : leave.days_count}</p>
                        <p><strong>Reason:</strong><br>${leave.reason}</p>
                `;
                
                if (leave.attachment) {
                    details += `<p><strong>Attachment:</strong> <a href="/${leave.attachment}" target="_blank" class="btn btn-sm btn-secondary">Download</a></p>`;
                }
                
                details += '</div>';
                
                document.getElementById('leave-details').innerHTML = details;
                document.getElementById('leave_id').value = id;
                document.getElementById('comments').value = '';
                openModal('approval-modal');
            }
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
                        <p><strong>Employee:</strong> ${leave.employee_name || 'N/A'}</p>
                        <p><strong>Leave Type:</strong> ${leaveTypeLabel}</p>
                        <p><strong>Start Date:</strong> ${new Date(leave.start_date).toLocaleDateString()}</p>
                        ${leave.leave_duration === 'half_day' ? '' : `<p><strong>End Date:</strong> ${new Date(leave.end_date).toLocaleDateString()}</p>`}
                        <p><strong>Days:</strong> ${parseFloat(leave.days_count) === 0.5 ? '0.5' : leave.days_count}</p>
                        <p><strong>Status:</strong> <span class="badge badge-${leave.status === 'approved' ? 'success' : 'danger'}">${leave.status.toUpperCase()}</span></p>
                        <p><strong>Reason:</strong><br>${leave.reason}</p>
                `;
                
                if (leave.comments) {
                    content += `<p><strong>Comments:</strong><br>${leave.comments}</p>`;
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
    
    function prepareAction(action) {
        document.getElementById('action_type').value = action;
        
        const message = action === 'approve' 
            ? 'The employee will be notified and their leave balance will be deducted.' 
            : 'The employee will be notified of the decline.';
        
        const title = action === 'approve' ? 'Approve Leave Request' : 'Decline Leave Request';
        const icon = action === 'approve' ? '<i class="fas fa-check-circle" style="color: var(--success-green);"></i>' : '<i class="fas fa-times-circle" style="color: var(--danger-red);"></i>';
        const confirmButtonClass = action === 'approve' ? 'btn-success' : 'btn-danger';
        
        confirmDialog(
            message,
            () => {
                document.getElementById('approval-form').dispatchEvent(new Event('submit'));
            },
            null,
            title,
            icon,
            action === 'approve' ? 'var(--success-green)' : 'var(--danger-red)',
            confirmButtonClass
        );
    }
    
    function submitApproval(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const data = Object.fromEntries(formData);
        
        ajaxRequest('/officepro/app/api/leaves/approve.php', 'POST', data, (response) => {
            if (response.success) {
                showMessage('success', `Leave ${data.action}d successfully!`);
                closeModal('approval-modal');
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage('error', response.message || 'Failed to process approval');
            }
        });
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>




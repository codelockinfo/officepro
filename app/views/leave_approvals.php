<?php
/**
 * Leave Approval Page (Manager/Owner)
 */
session_start();

$pageTitle = 'Leave Approvals';
include __DIR__ . '/includes/header.php';

require_once __DIR__ . '/../helpers/Database.php';
require_once __DIR__ . '/../helpers/Tenant.php';

// Only managers and owners can access
Auth::requireRole(['company_owner', 'manager']);

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

<h1>✓ Leave Approvals</h1>

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
                            'paid_leave' => 'Paid Leave',
                            'sick_leave' => 'Sick Leave',
                            'casual_leave' => 'Casual Leave',
                            'work_from_home' => 'Work From Home'
                        ];
                        echo $typeLabels[$leave['leave_type']] ?? $leave['leave_type'];
                        ?>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($leave['start_date'])); ?></td>
                    <td><?php echo date('M d, Y', strtotime($leave['end_date'])); ?></td>
                    <td><?php echo $leave['days_count']; ?></td>
                    <td><?php echo date('M d, Y h:i A', strtotime($leave['created_at'])); ?></td>
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
                        echo $typeLabels[$leave['leave_type']] ?? $leave['leave_type'];
                        ?>
                    </td>
                    <td><?php echo date('M d', strtotime($leave['start_date'])) . ' - ' . date('M d, Y', strtotime($leave['end_date'])); ?></td>
                    <td><?php echo $leave['days_count']; ?></td>
                    <td>
                        <span class="badge badge-<?php echo $leave['status'] === 'approved' ? 'success' : 'danger'; ?>">
                            <?php echo ucfirst($leave['status']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($leave['approved_by_name'] ?? '-'); ?></td>
                    <td>
                        <button onclick="viewLeaveDetails(<?php echo $leave['id']; ?>)" class="btn btn-sm btn-secondary">View</button>
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
        ajaxRequest(`/app/api/leaves/view.php?id=${id}`, 'GET', null, (response) => {
            if (response.success) {
                const leave = response.data;
                const typeLabels = {
                    'paid_leave': 'Paid Leave',
                    'sick_leave': 'Sick Leave',
                    'casual_leave': 'Casual Leave',
                    'work_from_home': 'Work From Home'
                };
                
                let details = `
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 5px;">
                        <p><strong>Employee:</strong> ${leave.employee_name || 'N/A'}</p>
                        <p><strong>Leave Type:</strong> ${typeLabels[leave.leave_type]}</p>
                        <p><strong>Start Date:</strong> ${new Date(leave.start_date).toLocaleDateString()}</p>
                        <p><strong>End Date:</strong> ${new Date(leave.end_date).toLocaleDateString()}</p>
                        <p><strong>Total Days:</strong> ${leave.days_count}</p>
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
        ajaxRequest(`/app/api/leaves/view.php?id=${id}`, 'GET', null, (response) => {
            if (response.success) {
                const leave = response.data;
                const typeLabels = {
                    'paid_leave': 'Paid Leave',
                    'sick_leave': 'Sick Leave',
                    'casual_leave': 'Casual Leave',
                    'work_from_home': 'Work From Home'
                };
                
                let content = `
                    <div style="padding: 20px;">
                        <p><strong>Employee:</strong> ${leave.employee_name || 'N/A'}</p>
                        <p><strong>Leave Type:</strong> ${typeLabels[leave.leave_type]}</p>
                        <p><strong>Start Date:</strong> ${new Date(leave.start_date).toLocaleDateString()}</p>
                        <p><strong>End Date:</strong> ${new Date(leave.end_date).toLocaleDateString()}</p>
                        <p><strong>Days:</strong> ${leave.days_count}</p>
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
            ? 'Are you sure you want to approve this leave request?' 
            : 'Are you sure you want to decline this leave request?';
        
        if (confirm(message)) {
            document.getElementById('approval-form').dispatchEvent(new Event('submit'));
        }
    }
    
    function submitApproval(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const data = Object.fromEntries(formData);
        
        ajaxRequest('/app/api/leaves/approve.php', 'POST', data, (response) => {
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



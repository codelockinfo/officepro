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

// Get current year leave balance
$currentYear = date('Y');
$balance = $db->fetchOne(
    "SELECT * FROM leave_balances WHERE company_id = ? AND user_id = ? AND year = ?",
    [$companyId, $userId, $currentYear]
);

// Get leave history
$leaves = $db->fetchAll(
    "SELECT l.*, u.full_name as approved_by_name 
    FROM leaves l 
    LEFT JOIN users u ON l.approved_by = u.id 
    WHERE l.company_id = ? AND l.user_id = ? 
    ORDER BY l.created_at DESC",
    [$companyId, $userId]
);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1>📅 My Leaves</h1>
    <button onclick="openRequestLeaveModal()" class="btn btn-primary">+ Request Leave</button>
</div>

<!-- Leave Balance Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--primary-blue); margin-bottom: 10px;">Paid Leave</h3>
        <div style="font-size: 36px; font-weight: bold; color: var(--primary-blue);">
            <?php echo $balance['paid_leave'] ?? 0; ?>
        </div>
        <p style="color: #666;">days remaining</p>
    </div>
    
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--success-green); margin-bottom: 10px;">Sick Leave</h3>
        <div style="font-size: 36px; font-weight: bold; color: var(--success-green);">
            <?php echo $balance['sick_leave'] ?? 0; ?>
        </div>
        <p style="color: #666;">days remaining</p>
    </div>
    
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--warning-yellow); margin-bottom: 10px;">Casual Leave</h3>
        <div style="font-size: 36px; font-weight: bold; color: #856404;">
            <?php echo $balance['casual_leave'] ?? 0; ?>
        </div>
        <p style="color: #666;">days remaining</p>
    </div>
    
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--primary-blue); margin-bottom: 10px;">WFH Days</h3>
        <div style="font-size: 36px; font-weight: bold; color: var(--primary-blue);">
            <?php echo $balance['wfh_days'] ?? 0; ?>
        </div>
        <p style="color: #666;">days remaining</p>
    </div>
</div>

<!-- Leave History -->
<div class="card">
    <h2 class="card-title">Leave History</h2>
    
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
                    <label class="form-label" for="leave_type">Leave Type *</label>
                    <select id="leave_type" name="leave_type" class="form-control" required>
                        <option value="paid_leave">Paid Leave (<?php echo $balance['paid_leave'] ?? 0; ?> available)</option>
                        <option value="sick_leave">Sick Leave (<?php echo $balance['sick_leave'] ?? 0; ?> available)</option>
                        <option value="casual_leave">Casual Leave (<?php echo $balance['casual_leave'] ?? 0; ?> available)</option>
                        <option value="work_from_home">Work From Home (<?php echo $balance['wfh_days'] ?? 0; ?> available)</option>
                    </select>
                </div>
                
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label" for="start_date">Start Date *</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" required onchange="calculateDays()">
                    </div>
                    
                    <div class="form-group">
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
        openModal('request-leave-modal');
    }
    
    function calculateDays() {
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        
        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            
            document.getElementById('days-count').textContent = diffDays;
            document.getElementById('days-info').style.display = 'block';
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
                    'paid_leave': 'Paid Leave',
                    'sick_leave': 'Sick Leave',
                    'casual_leave': 'Casual Leave',
                    'work_from_home': 'Work From Home'
                };
                
                let content = `
                    <div style="padding: 20px;">
                        <p><strong>Leave Type:</strong> ${typeLabels[leave.leave_type]}</p>
                        <p><strong>Start Date:</strong> ${new Date(leave.start_date).toLocaleDateString()}</p>
                        <p><strong>End Date:</strong> ${new Date(leave.end_date).toLocaleDateString()}</p>
                        <p><strong>Days:</strong> ${leave.days_count}</p>
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
            '📅'
        );
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>




<?php
/**
 * Company Invitations Management Page
 */

$pageTitle = 'Employee Invitations';
include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Tenant.php';
require_once __DIR__ . '/../../helpers/Invitation.php';

// Only company owners and managers can access
Auth::checkRole(['company_owner', 'manager'], 'Only company owners and managers can manage invitations.');

$companyId = Tenant::getCurrentCompanyId();
$invitations = Invitation::getCompanyInvitations($companyId);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1><i class="fas fa-envelope"></i> Employee Invitations</h1>
    <button onclick="openInviteModal()" class="btn btn-primary custom-btn-primary"><i class="fas fa-plus"></i> Invite Employee</button>
</div>

<div class="card">
    <h2 class="card-title">Invitations</h2>
    
    <?php if (count($invitations) === 0): ?>
        <p style="text-align: center; padding: 40px; color: #666;">No invitations sent yet</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Invited By</th>
                    <th>Expires At</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invitations as $inv): ?>
                <tr>
                    <td><?php echo htmlspecialchars($inv['email']); ?></td>
                    <td><span class="badge badge-primary"><?php echo ucfirst($inv['role']); ?></span></td>
                    <td><?php echo htmlspecialchars($inv['invited_by_name']); ?></td>
                    <td><?php echo date('M d, Y h:i A', strtotime($inv['expires_at'])); ?></td>
                    <td>
                        <?php
                        $statusClass = [
                            'pending' => 'badge-warning',
                            'accepted' => 'badge-success',
                            'expired' => 'badge-danger',
                            'cancelled' => 'badge-secondary'
                        ];
                        ?>
                        <span class="badge <?php echo $statusClass[$inv['status']] ?? 'badge-secondary'; ?>">
                            <?php echo ucfirst($inv['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($inv['status'] === 'pending'): ?>
                            <button onclick="copyInviteLink('<?php echo htmlspecialchars($inv['token']); ?>')" class="btn btn-sm invite-action-btn" title="Copy Link" style="padding: 8px 12px; width: 40px; height: 36px; margin-right: 5px; background: #4da6ff; color: white; border: 1px solid #4da6ff;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                            </button>
                            <button onclick="resendInvite(<?php echo $inv['id']; ?>)" class="btn btn-sm invite-action-btn" title="Resend" style="padding: 8px 12px; width: 40px; height: 36px; margin-right: 5px; background: #f0f0f0; color: #333; border: 1px solid #ddd;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                                    <path d="M21 3v5h-5"></path>
                                    <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                                    <path d="M3 21v-5h5"></path>
                                </svg>
                            </button>
                            <button onclick="cancelInvite(<?php echo $inv['id']; ?>)" class="btn btn-sm btn-danger invite-action-btn" title="Cancel" style="padding: 8px 12px; width: 40px; height: 36px;"><i class="fas fa-times"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Invite Modal -->
<div id="invite-modal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Invite Employee</h3>
            <button type="button" class="modal-close" onclick="closeModal('invite-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="invite-form" onsubmit="sendInvite(event)">
                <div class="form-group">
                    <label class="form-label" for="email">Employee Email *</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="role">Role *</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="employee">Employee</option>
                        <option value="manager">Manager</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="message">Personal Message (Optional)</label>
                    <textarea id="message" name="message" class="form-control" rows="3" placeholder="Add a personal message to the invitation email"></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('invite-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Invitation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .invite-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
    }
    
    .invite-action-btn:first-child:hover {
        background: #000000 !important;
        color: #ffffff !important;
        border-color: #000000 !important;
    }
</style>

<script>
    function openInviteModal() {
        document.getElementById('invite-form').reset();
        openModal('invite-modal');
    }
    
    function sendInvite(event) {
        event.preventDefault();
        const formData = new FormData(event.target);
        const data = Object.fromEntries(formData);
        
        ajaxRequest('/officepro/app/api/company/invite.php', 'POST', data, (response) => {
            if (response.success) {
                showMessage('success', 'Invitation sent successfully!');
                closeModal('invite-modal');
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage('error', response.message || 'Failed to send invitation');
            }
        });
    }
    
    function copyInviteLink(token) {
        const link = `${window.location.origin}/register.php?token=${token}`;
        navigator.clipboard.writeText(link).then(() => {
            showMessage('success', 'Invitation link copied to clipboard!');
        }).catch(() => {
            showMessage('error', 'Failed to copy link');
        });
    }
    
    function resendInvite(id) {
        ajaxRequest(`/officepro/app/api/company/invitations.php?action=resend&id=${id}`, 'POST', null, (response) => {
            if (response.success) {
                showMessage('success', 'Invitation resent successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage('error', response.message || 'Failed to resend invitation');
            }
        });
    }
    
    function cancelInvite(id) {
        confirmDialog(
            'The employee will no longer be able to use this invitation link.',
            () => {
                ajaxRequest(`/officepro/app/api/company/invitations.php?action=cancel&id=${id}`, 'POST', null, (response) => {
                    if (response.success) {
                        showMessage('success', 'Invitation cancelled');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showMessage('error', response.message || 'Failed to cancel invitation');
                    }
                });
            },
            null,
            'Cancel Invitation',
            '<i class="fas fa-envelope"></i>'
        );
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>




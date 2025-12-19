<?php
/**
 * User Profile Page
 */

$pageTitle = 'My Profile';
include __DIR__ . '/includes/header.php';

require_once __DIR__ . '/../helpers/Database.php';
require_once __DIR__ . '/../helpers/Tenant.php';

$companyId = Tenant::getCurrentCompanyId();
$userId = $currentUser['id'];
$db = Database::getInstance();

// Get full user data with department and company details
$user = $db->fetchOne(
    "SELECT u.*, d.name as department_name, c.company_name, c.company_email, c.phone, c.address
    FROM users u 
    LEFT JOIN departments d ON u.department_id = d.id 
    JOIN companies c ON u.company_id = c.id 
    WHERE u.id = ? AND u.company_id = ?",
    [$userId, $companyId]
);

// Get this month's attendance summary (only for non-company owners)
$attendanceSummary = null;
if ($user['role'] !== 'company_owner') {
    $currentMonth = date('Y-m');
    $attendanceSummary = $db->fetchOne(
        "SELECT 
            COUNT(DISTINCT date) as days_worked,
            SUM(regular_hours) as total_regular,
            SUM(overtime_hours) as total_overtime
        FROM attendance 
        WHERE company_id = ? AND user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ? AND is_present = 1",
        [$companyId, $userId, $currentMonth]
    );
}

// Get leave balance (only for non-company owners) - Calculate from allocation minus taken leaves
$leaveBalance = null;
if ($user['role'] !== 'company_owner') {
    $currentYear = date('Y');
    
    // Get paid leave allocation from company_settings
    $paidLeaveAllocation = floatval(Tenant::getCompanySetting('paid_leave_allocation', '12'));
    
    // Calculate total approved leaves taken for this year
    $takenLeave = $db->fetchOne(
        "SELECT COALESCE(SUM(days_count), 0) as total_days
         FROM leaves 
         WHERE company_id = ? AND user_id = ? 
         AND status = 'approved'
         AND leave_type = 'paid_leave'
         AND YEAR(start_date) = ?",
        [$companyId, $userId, $currentYear]
    );
    $takenLeaveDays = floatval($takenLeave['total_days'] ?? 0);
    
    // Calculate remaining balance
    $remainingBalance = max(0, $paidLeaveAllocation - $takenLeaveDays);
    
    // Set leave balance with calculated value
    $leaveBalance = [
        'paid_leave' => $remainingBalance,
        'year' => $currentYear
    ];
}
?>

<h1><i class="fas fa-user"></i> My Profile</h1>

<!-- Profile Information Card -->
<div class="card" style="margin-top: 20px;">
    <h2 class="card-title">Profile Information</h2>
    
    <div style="padding: 20px; display: grid; grid-template-columns: 200px 1fr; gap: 30px;">
        <div style="text-align: center;">
            <img id="current-profile-image" src="/officepro/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                 alt="Profile" 
                 style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-blue);"
                 onerror="this.src='/officepro/assets/images/default-avatar.png'">
            <button onclick="openChangePhotoModal()" class="btn btn-sm btn-secondary custom-btn-secondary" style="margin-top: 15px; width: 100%;">
                Change Photo
            </button>
        </div>
        
        <div>
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 12px; font-weight: 600; width: 180px;">Full Name:</td>
                    <td style="padding: 12px;"><?php echo htmlspecialchars($user['full_name']); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 12px; font-weight: 600;">Email:</td>
                    <td style="padding: 12px;"><?php echo htmlspecialchars($user['email']); ?></td>
                </tr>
                <?php if ($user['role'] === 'company_owner'): ?>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 12px; font-weight: 600;">Contact No:</td>
                        <td style="padding: 12px;"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></td>
                    </tr>
                <?php endif; ?>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 12px; font-weight: 600;">Company:</td>
                    <td style="padding: 12px;"><?php echo htmlspecialchars($user['company_name']); ?></td>
                </tr>
                <?php if ($user['role'] === 'company_owner'): ?>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 12px; font-weight: 600;">Company Address:</td>
                        <td style="padding: 12px;"><?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?></td>
                    </tr>  
                <?php endif; ?>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 12px; font-weight: 600;">Role:</td>
                    <td style="padding: 12px;">
                        <span class="badge badge-primary">
                            <?php echo str_replace('_', ' ', ucwords($user['role'], '_')); ?>
                        </span>
                    </td>
                </tr>
                <?php if ($user['role'] !== 'company_owner'): ?>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 12px; font-weight: 600;">Status:</td>
                    <td style="padding: 12px;">
                        <span class="badge badge-success"><?php echo ucfirst($user['status']); ?></span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 12px; font-weight: 600;">Member Since:</td>
                    <td style="padding: 12px;"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></td>
                </tr>
                <?php endif; ?>
            </table>
            
            <div style="margin-top: 20px;">
                <button onclick="openChangePasswordModal()" class="btn btn-primary custom-btn-primary">Change Password</button>
            </div>
        </div>
    </div>
</div>

<?php if ($user['role'] !== 'company_owner'): ?>
<!-- This Month's Summary -->
<?php
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
?>
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--primary-blue); margin-bottom: 10px;">Days Worked</h3>
        <div style="font-size: 36px; font-weight: bold; color: var(--primary-blue);">
            <?php echo $attendanceSummary['days_worked'] ?? 0; ?>
        </div>
        <p style="color: #666;">this month</p>
    </div>
    
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--primary-blue); margin-bottom: 10px;">Regular Hours</h3>
        <div style="font-size: 36px; font-weight: bold; color: var(--primary-blue);">
            <?php echo formatHoursToTime($attendanceSummary['total_regular'] ?? 0); ?>
        </div>
        <p style="color: #666;">HH:MM:SS this month</p>
    </div>
    
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--overtime-orange); margin-bottom: 10px;">Overtime Hours</h3>
        <div style="font-size: 36px; font-weight: bold; color: var(--overtime-orange);">
            <?php echo formatHoursToTime($attendanceSummary['total_overtime'] ?? 0); ?>
        </div>
        <p style="color: #666;">HH:MM:SS this month</p>
    </div>
</div>
<?php endif; ?>

<?php if ($user['role'] !== 'company_owner'): ?>
<!-- Leave Balance -->
<div class="card" style="margin-top: 20px;">
    <h2 class="card-title">Leave Balance (<?php echo date('Y'); ?>)</h2>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; padding: 20px;">
        <div style="text-align: center; padding: 15px; background: var(--light-blue); border-radius: 8px;">
            <div style="font-size: 24px; font-weight: bold; color: var(--primary-blue);">
                <?php echo $leaveBalance['paid_leave'] ?? 0; ?>
            </div>
            <p style="margin: 0; color: #666;">Paid Leave</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Change Password Modal -->
<div id="change-password-modal" class="modal-overlay">
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h3 class="modal-title">Change Password</h3>
            <button type="button" class="modal-close" onclick="closeModal('change-password-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="password-form" onsubmit="changePassword(event)">
                <div class="form-group">
                    <label class="form-label" for="current_password">Current Password *</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="new_password">New Password * (min. 8 characters)</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" minlength="8" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('change-password-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Photo Modal -->
<div id="change-photo-modal" class="modal-overlay">
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h3 class="modal-title">Change Profile Photo</h3>
            <button type="button" class="modal-close" onclick="closeModal('change-photo-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="photo-form" enctype="multipart/form-data" onsubmit="changePhoto(event)">
                <div class="form-group">
                    <label class="form-label" for="new_photo">New Profile Photo *</label>
                    <input type="file" id="new_photo" name="profile_image" class="form-control" accept="image/*" onchange="previewPhoto(this)" required>
                    <small class="text-muted">Minimum 200x200 pixels, max 2MB</small>
                    <div id="photo-preview" style="margin-top: 15px; text-align: center;">
                        <img src="/officepro/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                             style="max-width: 200px; max-height: 200px; border-radius: 8px;">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('change-photo-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Photo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openChangePasswordModal() {
        document.getElementById('password-form').reset();
        openModal('change-password-modal');
    }
    
    function openChangePhotoModal() {
        document.getElementById('photo-form').reset();
        openModal('change-photo-modal');
    }
    
    function previewPhoto(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('photo-preview').innerHTML = '<img src="' + e.target.result + '" style="max-width: 200px; max-height: 200px; border-radius: 8px;">';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function changePassword(event) {
        event.preventDefault();
        
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (newPassword !== confirmPassword) {
            showMessage('error', 'Passwords do not match');
            return;
        }
        
        const formData = new FormData(event.target);
        const data = Object.fromEntries(formData);
        
        ajaxRequest('/officepro/app/api/user/change_password.php', 'POST', data, (response) => {
            if (response.success) {
                showMessage('success', 'Password changed successfully!');
                closeModal('change-password-modal');
            } else {
                showMessage('error', response.message || 'Failed to change password');
            }
        });
    }
    
    function changePhoto(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        
        showLoader();
        
        fetch('/officepro/app/api/user/change_photo.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoader();
            if (data.success) {
                showMessage('success', 'Profile photo updated!');
                closeModal('change-photo-modal');
                
                // Update all profile images on the page without reload
                const newImageUrl = '/officepro/' + data.new_image + '?v=' + Date.now();
                document.getElementById('current-profile-image').src = newImageUrl;
                
                // Update header avatar if exists
                const headerAvatar = document.querySelector('.user-avatar');
                if (headerAvatar) {
                    headerAvatar.src = newImageUrl;
                }
                
                // Also reload after 2 seconds to update everything
                setTimeout(() => location.reload(true), 2000);
            } else {
                showMessage('error', data.message || 'Failed to update photo');
            }
        })
        .catch(error => {
            hideLoader();
            showMessage('error', 'An error occurred. Please try again.');
        });
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>


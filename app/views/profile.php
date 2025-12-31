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

// Check if user data was found
if (!$user) {
    die('User not found. Please contact administrator.');
}

// Get this month's attendance summary (only for non-company owners)
$attendanceSummary = null;
if ($user['role'] !== 'company_owner') {
    try {
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
        // Ensure we have default values even if query returns null
        if (!$attendanceSummary) {
            $attendanceSummary = [
                'days_worked' => 0,
                'total_regular' => 0,
                'total_overtime' => 0
            ];
        }
    } catch (Exception $e) {
        error_log("Profile page attendance summary error: " . $e->getMessage());
        $attendanceSummary = [
            'days_worked' => 0,
            'total_regular' => 0,
            'total_overtime' => 0
        ];
    }
}

// Get leave balance (only for non-company owners) - Calculate from allocation minus taken leaves
$leaveBalance = null;
if ($user['role'] !== 'company_owner') {
    try {
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
    } catch (Exception $e) {
        error_log("Profile page leave balance error: " . $e->getMessage());
        $leaveBalance = [
            'paid_leave' => 0,
            'year' => date('Y')
        ];
    }
}
?>

<h1><i class="fas fa-user"></i> My Profile</h1>

<!-- Profile Information Card -->
<div class="card" style="margin-top: 20px;">
    <h2 class="card-title">Profile Information</h2>
    
    <div style="padding: 20px; display: grid; grid-template-columns: 200px 1fr; gap: 30px;">
        <div style="text-align: center;">
            <?php 
            $profileImage = trim($user['profile_image'] ?? '');
            $hasProfileImage = !empty($profileImage) && $profileImage !== 'assets/images/default-avatar.png';
            if ($hasProfileImage): 
            ?>
                <img id="current-profile-image" src="/officepro/<?php echo htmlspecialchars($profileImage); ?>" 
                     alt="Profile" 
                     style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-blue);"
                     onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div id="current-profile-icon" style="width: 150px; height: 150px; border-radius: 50%; border: 3px solid var(--primary-blue); background: #f5f5f5; display: none; align-items: center; justify-content: center; margin: 0 auto;">
                    <i class="fas fa-user" style="font-size: 75px; color: #999;"></i>
                </div>
            <?php else: ?>
                <div id="current-profile-icon" style="width: 150px; height: 150px; border-radius: 50%; border: 3px solid var(--primary-blue); background: #f5f5f5; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                    <i class="fas fa-user" style="font-size: 75px; color: #999;"></i>
                </div>
            <?php endif; ?>
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
                        <?php if ($hasProfileImage): ?>
                            <img src="/officepro/<?php echo htmlspecialchars($profileImage); ?>" 
                                 style="max-width: 200px; max-height: 200px; border-radius: 8px;"
                                 onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div style="width: 200px; height: 200px; border-radius: 8px; border: 2px solid #e0e0e0; background: #f5f5f5; display: none; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-user" style="font-size: 100px; color: #999;"></i>
                            </div>
                        <?php else: ?>
                            <div style="width: 200px; height: 200px; border-radius: 8px; border: 2px solid #e0e0e0; background: #f5f5f5; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-user" style="font-size: 100px; color: #999;"></i>
                            </div>
                        <?php endif; ?>
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
                const currentProfileImage = document.getElementById('current-profile-image');
                // If it's an img tag, update src; if it's a div with icon, replace with img
                if (currentProfileImage.tagName === 'IMG') {
                    currentProfileImage.src = newImageUrl;
                } else {
                    // Replace icon div with image
                    const newImg = document.createElement('img');
                    newImg.id = 'current-profile-image';
                    newImg.src = newImageUrl;
                    newImg.alt = 'Profile';
                    newImg.style.cssText = 'width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-blue);';
                    currentProfileImage.parentNode.replaceChild(newImg, currentProfileImage);
                }
                
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


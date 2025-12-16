<?php
/**
 * Company Settings Page
 */

$pageTitle = 'Company Settings';
include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Tenant.php';

// Only company owners can access
Auth::checkRole(['company_owner'], 'Only company owners can access company settings.');

$companyId = Tenant::getCurrentCompanyId();
$db = Database::getInstance();

// Get company data - explicitly select logo to ensure it's loaded
$company = $db->fetchOne("SELECT * FROM companies WHERE id = ?", [$companyId]);

// Debug: Log what we got from database
error_log("Company Settings - Company ID: $companyId, Logo from DB: " . ($company['logo'] ?? 'NULL'));
?>

<h1>⚙️ Company Settings</h1>

<div class="card" style="margin-top: 20px;">
    <h2 class="card-title">Company Information</h2>
    
    <form id="company-settings-form" enctype="multipart/form-data" onsubmit="saveSettings(event); return false;" style="padding: 20px;">
        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="company_name">Company Name *</label>
                <input type="text" id="company_name" name="company_name" class="form-control" 
                       value="<?php echo htmlspecialchars($company['company_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="company_email">Company Email *</label>
                <input type="email" id="company_email" name="company_email" class="form-control" 
                       value="<?php echo htmlspecialchars($company['company_email']); ?>" required>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="phone">Phone Number</label>
            <?php
            // Parse existing phone to separate country code and number
            $existingPhone = $company['phone'] ?? '';
            $countryCode = '+91';
            $phoneNumber = '';
            
            if ($existingPhone) {
                // Try to extract country code
                if (preg_match('/^(\+\d+)\s*(.+)$/', $existingPhone, $matches)) {
                    $countryCode = $matches[1];
                    $phoneNumber = preg_replace('/[^0-9]/', '', $matches[2]);
                } else {
                    $phoneNumber = preg_replace('/[^0-9]/', '', $existingPhone);
                }
            }
            ?>
            <div style="display: grid; grid-template-columns: 150px 1fr; gap: 10px;">
                <select id="country_code" name="country_code" class="form-control">
                    <option value="+91" <?php echo $countryCode === '+91' ? 'selected' : ''; ?>>🇮🇳 India (+91)</option>
                    <option value="+1" <?php echo $countryCode === '+1' ? 'selected' : ''; ?>>🇺🇸 USA (+1)</option>
                    <option value="+44" <?php echo $countryCode === '+44' ? 'selected' : ''; ?>>🇬🇧 UK (+44)</option>
                    <option value="+61" <?php echo $countryCode === '+61' ? 'selected' : ''; ?>>🇦🇺 Australia (+61)</option>
                    <option value="+86" <?php echo $countryCode === '+86' ? 'selected' : ''; ?>>🇨🇳 China (+86)</option>
                </select>
                <input type="tel" 
                       id="phone" 
                       name="phone_number" 
                       class="form-control" 
                       value="<?php echo htmlspecialchars($phoneNumber); ?>"
                       placeholder="1234567890"
                       maxlength="10"
                       pattern="[0-9]{10}"
                       oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
            </div>
            <small class="text-muted">Enter 10-digit mobile number</small>
        </div>
        
        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            
            <div class="form-group">
                <label class="form-label" for="logo">Company Logo</label>
                <?php 
                // Check if logo exists in database - be more thorough
                $hasLogo = isset($company['logo']) && $company['logo'] !== null && trim($company['logo']) !== '';
                error_log("Company Settings - Logo check: hasLogo=" . ($hasLogo ? 'true' : 'false') . ", logo=" . ($company['logo'] ?? 'NULL'));
                ?>
                <div id="logo-upload-section" style="<?php echo $hasLogo ? 'display: none;' : ''; ?>">
                    <input type="file" id="logo" name="logo" class="form-control" accept="image/jpeg,image/jpg,image/png,image/webp" onchange="previewLogo(this)">
                </div>
                <div id="logo-preview-container" style="margin-top: 10px;">
                    <?php if ($hasLogo): ?>
                        <div id="current-logo">
                            <img id="current-logo-img" src="/officepro/<?php echo htmlspecialchars($company['logo']); ?>" 
                                 alt="Current Logo" 
                                 style="max-height: 60px; border: 1px solid #ddd; padding: 5px; border-radius: 4px; display: block;"
                                 onerror="handleLogoError();">
                            <button type="button" onclick="removeLogo()" class="btn btn-sm btn-danger" style="margin-top: 10px;">
                                Remove Logo
                            </button>
                        </div>
                    <?php else: ?>
                        <div id="current-logo" style="display: none;">
                            <img id="current-logo-img" src="" alt="Current Logo" 
                                 style="max-height: 60px; border: 1px solid #ddd; padding: 5px; border-radius: 4px;"
                                 onerror="handleLogoError();">
                            <button type="button" onclick="removeLogo()" class="btn btn-sm btn-danger" style="margin-top: 10px; display: none;">
                                Remove Logo
                            </button>
                        </div>
                    <?php endif; ?>
                    <div id="new-logo-preview" style="display: none; margin-top: 10px;">
                        <p style="font-size: 12px; color: #666; margin-bottom: 5px;">New logo preview:</p>
                        <img id="new-logo-img" src="" alt="New Logo Preview" 
                             style="max-height: 60px; border: 1px solid #28a745; padding: 5px; border-radius: 4px;">
                    </div>
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="address">Address</label>
            <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($company['address'] ?? ''); ?></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary custom-btn-primary">Save Changes</button>
    </form>
</div>

<script>
    function handleLogoError() {
        // If logo fails to load, show upload section
        document.getElementById('logo-upload-section').style.display = 'block';
        document.getElementById('current-logo').style.display = 'none';
    }
    
    function previewLogo(input) {
        const previewContainer = document.getElementById('new-logo-preview');
        const previewImg = document.getElementById('new-logo-img');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewContainer.style.display = 'block';
            };
            
            reader.readAsDataURL(input.files[0]);
        } else {
            previewContainer.style.display = 'none';
        }
    }
    
    function removeLogo() {
        if (!confirm('Are you sure you want to remove the company logo?')) {
            return;
        }
        
        // Show loading
        const removeBtn = event.target;
        const originalText = removeBtn.textContent;
        removeBtn.disabled = true;
        removeBtn.textContent = 'Removing...';
        
        // Call API to remove logo
        const formData = new FormData();
        formData.append('remove_logo', '1');
        
        ajaxRequest('/officepro/app/api/company/update_settings.php', 'POST', formData, (response) => {
            removeBtn.disabled = false;
            removeBtn.textContent = originalText;
            
            if (response.success) {
                showMessage('success', 'Logo removed successfully');
                
                // Hide current logo
                document.getElementById('current-logo').style.display = 'none';
                
                // Show upload section
                document.getElementById('logo-upload-section').style.display = 'block';
                
                // Update header (remove logo)
                setTimeout(() => {
                    const headerLogo = document.querySelector('.company-logo');
                    if (headerLogo) {
                        headerLogo.style.display = 'none';
                    }
                    location.reload();
                }, 500);
            } else {
                showMessage('error', response.message || 'Failed to remove logo');
            }
        });
    }
    
    function saveSettings(event) {
        console.log('saveSettings function called');
        event.preventDefault();
        console.log('Form submission prevented');
        
        const formData = new FormData(event.target);
        console.log('FormData created');
        
        // Combine country code and phone number
        const countryCode = document.getElementById('country_code').value;
        const phoneNumber = document.getElementById('phone').value;
        console.log('Phone:', countryCode, phoneNumber);
        
        if (phoneNumber) {
            formData.set('phone', countryCode + ' ' + phoneNumber);
        }
        
        // Show loading state
        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
        console.log('Button state updated');
        
        // Check if ajaxRequest is available
        if (typeof ajaxRequest === 'undefined') {
            console.error('ajaxRequest function is not defined!');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            alert('Error: ajaxRequest function not found. Please check if app.js is loaded.');
            return;
        }
        
        console.log('Calling ajaxRequest...');
        // Send to API
        ajaxRequest('/officepro/app/api/company/update_settings.php', 'POST', formData, (response) => {
            console.log('API Response received:', response);
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            
            if (response && response.success) {
                console.log('Save successful! Response data:', response.data);
                showMessage('success', response.message || 'Settings saved successfully!');
                
                // Update UI immediately if logo was uploaded
                if (response.data && response.data.company_logo) {
                    console.log('Logo in response:', response.data.company_logo);
                    // Update the current logo display
                    const currentLogoImg = document.getElementById('current-logo-img');
                    const currentLogoDiv = document.getElementById('current-logo');
                    const logoUploadSection = document.getElementById('logo-upload-section');
                    const removeBtn = currentLogoDiv ? currentLogoDiv.querySelector('button') : null;
                    
                    if (currentLogoImg && currentLogoDiv) {
                        currentLogoImg.src = '/officepro/' + response.data.company_logo;
                        currentLogoImg.style.display = 'block';
                        currentLogoDiv.style.display = 'block';
                        
                        if (removeBtn) {
                            removeBtn.style.display = 'block';
                        }
                        
                        if (logoUploadSection) {
                            logoUploadSection.style.display = 'none';
                        }
                    }
                    
                    // Hide new logo preview
                    const newLogoPreview = document.getElementById('new-logo-preview');
                    if (newLogoPreview) {
                        newLogoPreview.style.display = 'none';
                    }
                    
                    // Clear file input
                    const logoInput = document.getElementById('logo');
                    if (logoInput) {
                        logoInput.value = '';
                    }
                    
                    // Update header logo
                    const headerLogo = document.querySelector('.company-logo');
                    if (headerLogo) {
                        headerLogo.src = '/officepro/' + response.data.company_logo;
                        headerLogo.style.display = 'block';
                    }
                }
                
                // Reload after a short delay to ensure everything is updated
                setTimeout(() => {
                    console.log('Reloading page...');
                    location.reload();
                }, 1000);
            } else {
                const errorMsg = response && response.message ? response.message : 'Failed to save settings';
                showMessage('error', errorMsg);
                if (response && response.errors) {
                    console.error('Validation errors:', response.errors);
                }
            }
        }, (error) => {
            // Error callback
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            console.error('Save settings error:', error);
            showMessage('error', 'An error occurred while saving. Please check the console for details.');
        }); // FormData is automatically detected
    }
    
    // Real-time phone validation
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOMContentLoaded - Company Settings page');
        
        // Verify form exists
        const form = document.getElementById('company-settings-form');
        if (!form) {
            console.error('Form not found!');
        } else {
            console.log('Form found, setting up event listener');
            // Also add event listener as backup
            form.addEventListener('submit', function(e) {
                console.log('Form submit event triggered');
                saveSettings(e);
            });
        }
        
        // Verify ajaxRequest is available
        if (typeof ajaxRequest === 'undefined') {
            console.error('WARNING: ajaxRequest function is not available!');
            console.log('Available functions:', Object.keys(window).filter(k => typeof window[k] === 'function'));
        } else {
            console.log('ajaxRequest function is available');
        }
        
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                const value = this.value;
                const countDigits = value.length;
                
                if (countDigits === 10) {
                    this.style.borderColor = '#28a745'; // Green
                } else if (countDigits > 0) {
                    this.style.borderColor = '#ffc107'; // Yellow
                } else {
                    this.style.borderColor = '#ddd'; // Default
                }
            });
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


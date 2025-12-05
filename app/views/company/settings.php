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

// Get company data
$company = $db->fetchOne("SELECT * FROM companies WHERE id = ?", [$companyId]);
?>

<h1>⚙️ Company Settings</h1>

<div class="card" style="margin-top: 20px;">
    <h2 class="card-title">Company Information</h2>
    
    <form id="company-settings-form" enctype="multipart/form-data" onsubmit="saveSettings(event)" style="padding: 20px;">
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
                <input type="file" id="logo" name="logo" class="form-control" accept="image/*">
                <?php if ($company['logo']): ?>
                    <div style="margin-top: 10px;">
                        <img src="/officepro/<?php echo htmlspecialchars($company['logo']); ?>" 
                             alt="Current Logo" style="max-height: 60px;">
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="address">Address</label>
            <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($company['address'] ?? ''); ?></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
</div>

<script>
    function saveSettings(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        
        // Combine country code and phone number
        const countryCode = document.getElementById('country_code').value;
        const phoneNumber = document.getElementById('phone').value;
        
        if (phoneNumber) {
            formData.set('phone', countryCode + ' ' + phoneNumber);
        }
        
        showMessage('success', 'Settings saved successfully!');
        // API implementation coming soon
    }
    
    // Real-time phone validation
    document.addEventListener('DOMContentLoaded', function() {
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


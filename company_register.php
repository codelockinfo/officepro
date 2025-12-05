<?php
/**
 * Company Registration Page
 */

// Initialize application
require_once __DIR__ . '/app/config/init.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: app/views/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Your Company - OfficePro</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        .register-container {
            background: white;
            border-radius: 10px;
            padding: 40px;
            max-width: 800px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .register-header h1 {
            color: #4da6ff;
            margin-bottom: 5px;
        }
        .section-title {
            color: #4da6ff;
            border-bottom: 2px solid #e6f2ff;
            padding-bottom: 10px;
            margin: 30px 0 20px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        .image-preview {
            width: 150px;
            height: 150px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
            overflow: hidden;
        }
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        /* Phone input hint */
        input[type="tel"]:invalid {
            border-color: #ffc107;
        }
        input[type="tel"]:valid {
            border-color: #28a745;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>🏢 Register Your Company</h1>
            <p>Create your company account and start managing your team</p>
        </div>
        
        <form id="company-register-form" enctype="multipart/form-data" onsubmit="handleRegister(event)">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="company_name">Company Name *</label>
                    <input type="text" id="company_name" name="company_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="company_email">Company Email *</label>
                    <input type="email" id="company_email" name="company_email" class="form-control" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="phone">Phone Number</label>
                <div style="display: grid; grid-template-columns: 150px 1fr; gap: 10px;">
                    <select id="country_code" name="country_code" class="form-control">
                        <option value="+91" selected>🇮🇳 India (+91)</option>
                        <option value="+1">🇺🇸 USA (+1)</option>
                        <option value="+44">🇬🇧 UK (+44)</option>
                        <option value="+61">🇦🇺 Australia (+61)</option>
                        <option value="+86">🇨🇳 China (+86)</option>
                        <option value="+81">🇯🇵 Japan (+81)</option>
                        <option value="+82">🇰🇷 Korea (+82)</option>
                        <option value="+65">🇸🇬 Singapore (+65)</option>
                        <option value="+971">🇦🇪 UAE (+971)</option>
                        <option value="+966">🇸🇦 Saudi (+966)</option>
                        <option value="+92">🇵🇰 Pakistan (+92)</option>
                        <option value="+880">🇧🇩 Bangladesh (+880)</option>
                        <option value="+94">🇱🇰 Sri Lanka (+94)</option>
                        <option value="+977">🇳🇵 Nepal (+977)</option>
                    </select>
                    <input type="tel" 
                           id="phone" 
                           name="phone" 
                           class="form-control" 
                           placeholder="1234567890"
                           maxlength="10"
                           pattern="[0-9]{10}"
                           title="Please enter a 10-digit phone number"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                </div>
                <small class="text-muted">Enter 10-digit mobile number</small>
            </div>
            
            <div class="form-row">
                
                <div class="form-group">
                    <label class="form-label" for="address">Company Address</label>
                    <input type="text" id="address" name="address" class="form-control" placeholder="123 Business St, City, State">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="logo">Company Logo (Optional)</label>
                    <input type="file" id="logo" name="logo" class="form-control" accept="image/*" onchange="previewImage(this, 'logo-preview')">
                    <div id="logo-preview" class="image-preview" style="width: 120px; height: 120px;">
                        <span style="font-size: 12px; color: #999;">No logo</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="profile_image">Your Profile Photo *</label>
                    <input type="file" id="profile_image" name="profile_image" class="form-control" accept="image/jpeg,image/png,image/jpg" onchange="previewImage(this, 'profile-preview')" required>
                    <small class="text-muted">JPG or PNG, minimum 200x200 pixels, max 2MB</small>
                    <div id="profile-preview" class="image-preview" style="width: 120px; height: 120px;">
                        <span style="font-size: 12px; color: #999;">No photo</span>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="full_name">Your Full Name *</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" placeholder="John Doe" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">Your Email Address *</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="john@example.com" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Create Password * (min. 8 characters)</label>
                <input type="password" id="password" name="password" class="form-control" minlength="8" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg w-100 mt-20">Register Company & Create Account</button>
        </form>
        
        <div class="login-links text-center mt-20">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
    <script>
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '">';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function handleRegister(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            
            // Combine country code and phone number
            const countryCode = document.getElementById('country_code').value;
            const phoneNumber = document.getElementById('phone').value;
            
            if (phoneNumber) {
                formData.set('phone', countryCode + ' ' + phoneNumber);
            }
            
            showLoader();
            
            fetch('/officepro/app/api/auth/register_company.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoader();
                if (data.success) {
                    showMessage('success', 'Company registered successfully! Redirecting...');
                    setTimeout(() => {
                        window.location.href = '/officepro/app/views/dashboard.php';
                    }, 1500);
                } else {
                    showMessage('error', data.message || 'Registration failed');
                }
            })
            .catch(error => {
                hideLoader();
                showMessage('error', 'An error occurred. Please try again.');
            });
        }
        
        // Real-time phone validation feedback
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInput = document.getElementById('phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    const value = this.value;
                    const countDigits = value.replace(/[^0-9]/g, '').length;
                    
                    if (countDigits === 10) {
                        this.style.borderColor = '#28a745';
                    } else if (countDigits > 0) {
                        this.style.borderColor = '#ffc107';
                    } else {
                        this.style.borderColor = '#ddd';
                    }
                });
            }
        });
    </script>
</body>
</html>




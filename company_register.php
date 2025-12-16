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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: url('assets/images/first.gif') center center / cover no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.8) 0%, rgba(118, 75, 162, 0.8) 100%);
            z-index: 0;
        }
        body > * {
            position: relative;
            z-index: 1;
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
        .register-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
        }
        .register-header-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .register-header-icon img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }
        .register-header h1 {
            color: #667eea;
            margin-bottom: 5px;
            font-size: 32px;
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
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-control:hover {
            border-color: #b0b0b0;
        }
        .password-wrapper {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #666;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            transition: color 0.3s ease;
            width: 30px;
            height: 30px;
        }
        .password-toggle:hover {
            color: #667eea;
        }
        .password-toggle .eye-icon {
            font-size: 16px;
        }
        .password-wrapper .form-control {
            padding-right: 45px;
        }
        /* Hide browser default password reveal buttons */
        .password-wrapper input[type="password"]::-ms-reveal,
        .password-wrapper input[type="password"]::-ms-clear {
            display: none;
        }
        .password-wrapper input[type="text"][name*="password"]::-ms-reveal,
        .password-wrapper input[type="text"][name*="password"]::-ms-clear {
            display: none;
        }
        input[type="password"]::-webkit-credentials-auto-fill-button {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
            position: absolute !important;
            right: 0 !important;
        }
        .file-upload-wrapper {
            position: relative;
            margin-top: 10px;
        }
        .file-upload-btn {
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        .file-upload-btn:hover {
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
            transform: translateY(-2px);
        }
        .file-input-hidden {
            position: absolute;
            width: 0;
            height: 0;
            opacity: 0;
            overflow: hidden;
        }
        .file-name {
            margin-top: 8px;
            font-size: 14px;
            color: #666;
        }
        .image-preview {
            width: 150px;
            height: 150px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 15px;
            overflow: hidden;
            background: #f9f9f9;
            position: relative;
        }
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .image-preview .preview-placeholder {
            font-size: 12px;
            color: #999;
            text-align: center;
            padding: 10px;
        }
        .image-preview .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255, 0, 0, 0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            font-size: 14px;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .image-preview:hover .remove-image {
            display: flex;
        }
        /* Phone input hint - removed green border validation */
        .btn-register-submit {
            width: 100%;
            padding: 15px 40px;
            font-size: 18px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            margin-top: 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
        }
        .btn-register-submit:hover {
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
            background: linear-gradient(135deg, #5568d3 0%, #653a8f 100%);
            transform: translateY(-3px);
            color: white;
        }
        .btn-register-submit:active {
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="register-header-icon">
                <img src="assets/images/logo-icon.svg" alt="OfficePro Icon" onerror="this.style.display='none'; this.parentElement.innerHTML='🏢';">
            </div>
            <div>
                <h1>Register Your Company</h1>
                <p style="margin: 0; color: #666;">Create your company account and start managing your team</p>
            </div>
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
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number</label>
                    <div style="display: grid; grid-template-columns: 90px 1fr; gap: 10px;">
                        <select id="country_code" name="country_code" class="form-control">
                            <option value="+91" selected>+91</option>
                            <option value="+1">+1</option>
                            <option value="+44">+44</option>
                            <option value="+61">+61</option>
                            <option value="+86">+86</option>
                            <option value="+81">+81</option>
                            <option value="+82">+82</option>
                            <option value="+65">+65</option>
                            <option value="+971">+971</option>
                            <option value="+966">+966</option>
                            <option value="+92">+92</option>
                            <option value="+880">+880</option>
                            <option value="+94">+94</option>
                            <option value="+977">+977</option>
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
                
                <div class="form-group">
                    <label class="form-label" for="address">Company Address</label>
                    <input type="text" id="address" name="address" class="form-control" placeholder="123 Business St, City, State">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="logo">Company Logo (Optional)</label>
                    <div class="file-upload-wrapper">
                        <label for="logo" class="file-upload-btn">Choose File</label>
                        <input type="file" id="logo" name="logo" class="file-input-hidden" accept="image/*" onchange="handleFileSelect(this, 'logo-preview', 'logo-name')">
                        <div id="logo-name" class="file-name">No file chosen</div>
                    </div>
                    <div id="logo-preview" class="image-preview">
                        <span class="preview-placeholder">No logo</span>
                        <button type="button" class="remove-image" onclick="removeImage('logo', 'logo-preview', 'logo-name')" title="Remove">×</button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="profile_image">Your Profile Photo *</label>
                    <div class="file-upload-wrapper">
                        <label for="profile_image" class="file-upload-btn">Choose File</label>
                        <input type="file" id="profile_image" name="profile_image" class="file-input-hidden" accept="image/jpeg,image/png,image/jpg" onchange="handleFileSelect(this, 'profile-preview', 'profile-name')" required>
                        <div id="profile-name" class="file-name">No file chosen</div>
                    </div>
                    <small class="text-muted">JPG or PNG, minimum 100x100 pixels, max 2MB</small>
                    <div id="profile-preview" class="image-preview">
                        <span class="preview-placeholder">No photo</span>
                        <button type="button" class="remove-image" onclick="removeImage('profile_image', 'profile-preview', 'profile-name')" title="Remove">×</button>
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
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="password">Create Password * (min. 8 characters)</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" class="form-control" minlength="8" required>
                        <button type="button" class="password-toggle" id="password-toggle" onclick="togglePassword('password', this)" style="display: none;">
                            <i class="fas fa-eye eye-icon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password *</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="8" required>
                        <button type="button" class="password-toggle" id="confirm_password-toggle" onclick="togglePassword('confirm_password', this)" style="display: none;">
                            <i class="fas fa-eye eye-icon"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn-register-submit">Register Company & Create Account</button>
        </form>
        
        <div class="login-links text-center mt-20">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
    <script>
        function handleFileSelect(input, previewId, nameId) {
            const preview = document.getElementById(previewId);
            const nameDisplay = document.getElementById(nameId);
            const file = input.files[0];
            
            if (file) {
                // Validate file size (2MB max)
                if (file.size > 2 * 1024 * 1024) {
                    alert('File size must be less than 2MB');
                    input.value = '';
                    return;
                }
                
                // Validate file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!validTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPG or PNG)');
                    input.value = '';
                    return;
                }
                
                // Show file name
                nameDisplay.textContent = file.name;
                nameDisplay.style.color = '#667eea';
                
                // Preview image
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">' + 
                                      '<button type="button" class="remove-image" onclick="removeImage(\'' + input.id + '\', \'' + previewId + '\', \'' + nameId + '\')" title="Remove">×</button>';
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '<span class="preview-placeholder">' + (previewId === 'logo-preview' ? 'No logo' : 'No photo') + '</span>' +
                                  '<button type="button" class="remove-image" onclick="removeImage(\'' + input.id + '\', \'' + previewId + '\', \'' + nameId + '\')" title="Remove">×</button>';
                nameDisplay.textContent = 'No file chosen';
                nameDisplay.style.color = '#666';
            }
        }
        
        function removeImage(inputId, previewId, nameId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            const nameDisplay = document.getElementById(nameId);
            
            input.value = '';
            preview.innerHTML = '<span class="preview-placeholder">' + (previewId === 'logo-preview' ? 'No logo' : 'No photo') + '</span>' +
                              '<button type="button" class="remove-image" onclick="removeImage(\'' + inputId + '\', \'' + previewId + '\', \'' + nameId + '\')" title="Remove">×</button>';
            nameDisplay.textContent = 'No file chosen';
            nameDisplay.style.color = '#666';
        }
        
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('.eye-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Show password toggle when user starts typing
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.getElementById('password-toggle');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const confirmPasswordToggle = document.getElementById('confirm_password-toggle');
            
            if (passwordInput && passwordToggle) {
                // Show toggle on any keypress or input
                passwordInput.addEventListener('keydown', function() {
                    if (this.value.length >= 0) {
                        passwordToggle.style.display = 'flex';
                    }
                });
                
                passwordInput.addEventListener('input', function() {
                    if (this.value.length > 0) {
                        passwordToggle.style.display = 'flex';
                    } else {
                        passwordToggle.style.display = 'none';
                    }
                });
                
                passwordInput.addEventListener('focus', function() {
                    if (this.value.length > 0) {
                        passwordToggle.style.display = 'flex';
                    }
                });
                
                passwordInput.addEventListener('blur', function() {
                    if (this.value.length === 0) {
                        passwordToggle.style.display = 'none';
                    }
                });
            }
            
            if (confirmPasswordInput && confirmPasswordToggle) {
                // Show toggle on any keypress or input
                confirmPasswordInput.addEventListener('keydown', function() {
                    if (this.value.length >= 0) {
                        confirmPasswordToggle.style.display = 'flex';
                    }
                });
                
                confirmPasswordInput.addEventListener('input', function() {
                    if (this.value.length > 0) {
                        confirmPasswordToggle.style.display = 'flex';
                    } else {
                        confirmPasswordToggle.style.display = 'none';
                    }
                });
                
                confirmPasswordInput.addEventListener('focus', function() {
                    if (this.value.length > 0) {
                        confirmPasswordToggle.style.display = 'flex';
                    }
                });
                
                confirmPasswordInput.addEventListener('blur', function() {
                    if (this.value.length === 0) {
                        confirmPasswordToggle.style.display = 'none';
                    }
                });
            }
        });
        
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
        
        // Password confirmation validation
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            function validatePasswordMatch() {
                if (confirmPasswordInput.value && passwordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.setCustomValidity('Passwords do not match');
                    confirmPasswordInput.style.borderColor = '#dc3545';
                } else {
                    confirmPasswordInput.setCustomValidity('');
                    confirmPasswordInput.style.borderColor = '';
                }
            }
            
            if (passwordInput && confirmPasswordInput) {
                passwordInput.addEventListener('input', validatePasswordMatch);
                confirmPasswordInput.addEventListener('input', validatePasswordMatch);
            }
        });
    </script>
</body>
</html>




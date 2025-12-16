<?php
/**
 * Employee Registration Page (with invitation token)
 */

// Initialize application
require_once __DIR__ . '/app/config/init.php';
require_once __DIR__ . '/app/helpers/Database.php';
require_once __DIR__ . '/app/helpers/Invitation.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: app/views/dashboard.php');
    exit;
}

$token = $_GET['token'] ?? '';
$invitation = null;
$companyName = '';

if ($token) {
    $invitation = Invitation::validateToken($token);
    if (!$invitation) {
        $error = 'Invalid or expired invitation link';
    } else {
        $db = Database::getInstance();
        $company = $db->fetchOne(
            "SELECT company_name FROM companies WHERE id = ?",
            [$invitation['company_id']]
        );
        $companyName = $company['company_name'] ?? '';
    }
} else {
    $error = 'No invitation token provided';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Registration - OfficePro</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
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
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 50px 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.5);
            animation: fadeInUp 0.8s ease-out;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .register-header {
            text-align: center;
            margin-bottom: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        .register-header-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .register-header-icon img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }
        .register-header h1 {
            color: #667eea;
            margin-bottom: 5px;
            font-size: 32px;
        }
        .register-header p {
            color: #666;
            margin: 0;
        }
        .company-info {
            background: #e6f2ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
            border-left: 4px solid #667eea;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .form-control:hover {
            border-color: #b0b0b0;
        }
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-control[readonly] {
            background: #f5f5f5;
            cursor: not-allowed;
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
            font-size: 15px;
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
            margin: 15px auto 0;
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
        .btn-register-submit {
            width: 100%;
            padding: 15px 40px;
            font-size: 18px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            margin-top: 20px;
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
                <img src="assets/images/logo-icon.svg" alt="OfficePro Icon" onerror="this.style.display='none'; this.parentElement.innerHTML='👤';">
            </div>
            <h1>Employee Registration</h1>
            <p>Complete your registration</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <div class="text-center">
                <a href="login.php" class="btn btn-primary">Go to Login</a>
            </div>
        <?php else: ?>
            <div class="company-info">
                <p><strong>You're joining:</strong></p>
                <h3 style="color: #4da6ff; margin: 5px 0;"><?php echo htmlspecialchars($companyName); ?></h3>
            </div>
            
            <form id="employee-register-form" enctype="multipart/form-data" onsubmit="handleRegister(event)">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label class="form-label" for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">Email Address *</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($invitation['email'] ?? ''); ?>" readonly required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Password * (min. 8 characters)</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" class="form-control" minlength="8" required>
                        <button type="button" class="password-toggle" id="password-toggle" onclick="togglePassword('password', this)" style="display: none;">
                            <i class="fas fa-eye eye-icon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="profile_image">Profile Image *</label>
                    <div class="file-upload-wrapper">
                        <label for="profile_image" class="file-upload-btn">Choose File</label>
                        <input type="file" id="profile_image" name="profile_image" class="file-input-hidden" accept="image/jpeg,image/png,image/jpg" onchange="handleFileSelect(this, 'profile-preview', 'profile-name')" required>
                        <div id="profile-name" class="file-name">No file chosen</div>
                    </div>
                    <small class="text-muted">JPG or PNG, minimum 100x100 pixels, max 2MB</small>
                    <div id="profile-preview" class="image-preview">
                        <span class="preview-placeholder">No image selected</span>
                        <button type="button" class="remove-image" onclick="removeImage('profile_image', 'profile-preview', 'profile-name')" title="Remove">×</button>
                    </div>
                </div>
                
                <button type="submit" class="btn-register-submit">Complete Registration</button>
            </form>
        <?php endif; ?>
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
                preview.innerHTML = '<span class="preview-placeholder">No image selected</span>' +
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
            preview.innerHTML = '<span class="preview-placeholder">No image selected</span>' +
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
        });
        
        function handleRegister(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            
            showLoader();
            
            fetch('/officepro/app/api/auth/register.php', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                // Check if response is ok
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error('HTTP error: ' + response.status + ' - ' + errorText);
                }
                // Parse JSON response
                return response.json();
            })
            .then(data => {
                hideLoader();
                console.log('Registration response:', data);
                if (data.success) {
                    showMessage('success', 'Registration successful! Redirecting...');
                    setTimeout(() => {
                        window.location.href = '/officepro/app/views/dashboard.php';
                    }, 1500);
                } else {
                    const errorMsg = data.message || 'Registration failed';
                    console.error('Registration error details:', data);
                    
                    // Show detailed error message
                    let displayMsg = errorMsg;
                    if (data.errors) {
                        console.error('Validation errors:', data.errors);
                        const errorsText = Array.isArray(data.errors) 
                            ? data.errors.join(', ') 
                            : (typeof data.errors === 'object' ? JSON.stringify(data.errors) : data.errors);
                        displayMsg = errorMsg + (errorsText ? ' - ' + errorsText : '');
                    }
                    
                    showMessage('error', displayMsg);
                    
                    // Also log to console for debugging
                    console.error('Full error response:', JSON.stringify(data, null, 2));
                }
            })
            .catch(error => {
                hideLoader();
                console.error('Registration error:', error);
                showMessage('error', 'An error occurred: ' + (error.message || 'Please try again'));
            });
        }
    </script>
</body>
</html>




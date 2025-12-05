<?php
/**
 * Company Registration Page
 */
session_start();

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
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>🏢 Register Your Company</h1>
            <p>Start managing your team's attendance and leaves</p>
        </div>
        
        <form id="company-register-form" enctype="multipart/form-data" onsubmit="handleRegister(event)">
            <h3 class="section-title">Company Information</h3>
            
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
                    <label class="form-label" for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="logo">Company Logo</label>
                    <input type="file" id="logo" name="logo" class="form-control" accept="image/*" onchange="previewImage(this, 'logo-preview')">
                    <div id="logo-preview" class="image-preview">
                        <span>No logo selected</span>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="address">Address</label>
                <textarea id="address" name="address" class="form-control" rows="3"></textarea>
            </div>
            
            <h3 class="section-title">Owner Account</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">Email Address *</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="password">Password * (min. 8 characters)</label>
                    <input type="password" id="password" name="password" class="form-control" minlength="8" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="profile_image">Profile Image *</label>
                    <input type="file" id="profile_image" name="profile_image" class="form-control" accept="image/*" onchange="previewImage(this, 'profile-preview')" required>
                    <div id="profile-preview" class="image-preview">
                        <span>No image selected</span>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg w-100 mt-20">Register Company</button>
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
            
            showLoader();
            
            fetch('/app/api/auth/register_company.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoader();
                if (data.success) {
                    showMessage('success', 'Company registered successfully! Redirecting...');
                    setTimeout(() => {
                        window.location.href = 'app/views/dashboard.php';
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
    </script>
</body>
</html>



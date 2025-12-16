<?php
/**
 * Login Page
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
    <title>Login - OfficePro</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: url('assets/images/first.gif') center center / cover no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
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
        .login-container {
            background: white;
            border-radius: 10px;
            padding: 40px;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        .login-header-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-header-icon img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }
        .login-header h1 {
            color: #667eea;
            margin-bottom: 5px;
            font-size: 32px;
        }
        .login-header p {
            color: #666;
            margin: 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
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
        .btn-login {
            width: 100%;
            padding: 15px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
            background: linear-gradient(135deg, #5568d3 0%, #653a8f 100%);
            transform: translateY(-3px);
            color: white;
        }
        .btn-login:active {
            transform: translateY(-1px);
        }
        .login-links {
            text-align: center;
            margin-top: 20px;
        }
        .login-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .login-links a:hover {
            color: #5568d3;
        }
        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-header-icon">
                <img src="assets/images/logo-icon.svg" alt="OfficePro Icon" onerror="this.style.display='none'; this.parentElement.innerHTML='🏢';">
            </div>
            <h1>OfficePro</h1>
            <p>Welcome back! Please login to your account.</p>
        </div>
        
        <div id="error-message" class="error-message"></div>
        
        <form id="login-form" onsubmit="handleLogin(event)">
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" class="form-control" required>
                    <button type="button" class="password-toggle" id="password-toggle" onclick="togglePassword('password', this)" style="display: none;">
                        <i class="fas fa-eye eye-icon"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn-login">Login</button>
        </form>
        
        <div class="login-links">
            <p>Don't have an account? <a href="company_register.php">Register your company</a></p>
            <p><a href="index.php">← Back to home</a></p>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
    <script>
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
        
        function handleLogin(event) {
            event.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            ajaxRequest('/officepro/app/api/auth/login.php', 'POST', { email, password }, (response) => {
                if (response.success) {
                    showMessage('success', 'Login successful! Redirecting...');
                    setTimeout(() => {
                        window.location.href = '/officepro/app/views/dashboard.php';
                    }, 1000);
                } else {
                    const errorDiv = document.getElementById('error-message');
                    errorDiv.textContent = response.message || 'Login failed';
                    errorDiv.style.display = 'block';
                }
            });
        }
    </script>
</body>
</html>




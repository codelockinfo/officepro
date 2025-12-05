<?php
/**
 * Login Page
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
    <title>Login - OfficePro</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
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
        }
        .login-header h1 {
            color: #4da6ff;
            margin-bottom: 5px;
        }
        .login-header p {
            color: #666;
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
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-control:focus {
            outline: none;
            border-color: #4da6ff;
            box-shadow: 0 0 0 3px rgba(77, 166, 255, 0.1);
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #4da6ff;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-login:hover {
            background: #3d8ce6;
        }
        .login-links {
            text-align: center;
            margin-top: 20px;
        }
        .login-links a {
            color: #4da6ff;
            text-decoration: none;
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
            <h1>🏢 OfficePro</h1>
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
                <input type="password" id="password" name="password" class="form-control" required>
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
        function handleLogin(event) {
            event.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            ajaxRequest('/app/api/auth/login.php', 'POST', { email, password }, (response) => {
                if (response.success) {
                    showMessage('success', 'Login successful! Redirecting...');
                    setTimeout(() => {
                        window.location.href = 'app/views/dashboard.php';
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



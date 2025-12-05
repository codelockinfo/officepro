<?php
/**
 * Landing Page / Home
 */

// Initialize application
require_once __DIR__ . '/app/config/init.php';

// Redirect to dashboard if already logged in
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
    <title>OfficePro - Employee Attendance & Leave Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .landing-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            padding: 20px;
        }
        .hero-content {
            max-width: 800px;
        }
        .hero-title {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 20px;
            color: white;
        }
        .hero-subtitle {
            font-size: 20px;
            margin-bottom: 40px;
            opacity: 0.9;
        }
        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .hero-btn {
            padding: 15px 40px;
            font-size: 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.3s, box-shadow 0.3s;
            display: inline-block;
        }
        .hero-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .btn-register {
            background: white;
            color: #667eea;
        }
        .btn-login {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        .features {
            padding: 80px 20px;
            background: white;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .feature-card {
            text-align: center;
            padding: 30px;
        }
        .feature-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        .feature-title {
            font-size: 20px;
            margin-bottom: 10px;
            color: #4da6ff;
        }
    </style>
</head>
<body>
    <div class="landing-hero">
        <div class="hero-content">
            <h1 class="hero-title">🏢 OfficePro</h1>
            <p class="hero-subtitle">
                Complete Multi-Tenant Employee Attendance & Leave Management System
            </p>
            <div class="hero-buttons">
                <a href="company_register.php" class="hero-btn btn-register">Register Your Company</a>
                <a href="login.php" class="hero-btn btn-login">Login</a>
            </div>
        </div>
    </div>
    
    <div class="features">
        <h2 style="text-align: center; margin-bottom: 60px; font-size: 36px; color: #4da6ff;">Features</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">⏰</div>
                <h3 class="feature-title">Attendance Tracking</h3>
                <p>Real-time check-in/out with live timer and overtime calculation</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📅</div>
                <h3 class="feature-title">Leave Management</h3>
                <p>Request, approve, and track all types of leaves with balance management</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">✉️</div>
                <h3 class="feature-title">Employee Invitations</h3>
                <p>Send secure invitation links to employees via email</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3 class="feature-title">Reports & Analytics</h3>
                <p>Generate detailed reports with CSV/PDF export</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3 class="feature-title">Secure Credentials</h3>
                <p>Save and share website credentials within your team</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">✓</div>
                <h3 class="feature-title">Task Management</h3>
                <p>Create and assign tasks to team members</p>
            </div>
        </div>
    </div>
</body>
</html>




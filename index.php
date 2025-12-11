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
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .landing-hero {
            background: url('assets/images/first.gif') center center / cover no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            padding: 20px;
            position: relative;
        }
        .landing-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.8) 0%, rgba(118, 75, 162, 0.8) 100%);
            z-index: 1;
        }
        .hero-content {
            max-width: 800px;
            position: relative;
            z-index: 2;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 50px 40px;
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
        .hero-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .hero-logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            animation: fadeInUp 0.8s ease-out 0.1s both, pulse 2s ease-in-out infinite 1s;
        }
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
        .hero-logo-icon img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }
        .hero-title {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #667eea;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }
        .hero-subtitle {
            font-size: 20px;
            margin-bottom: 40px;
            color: #555;
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }
        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 0.8s ease-out 0.6s both;
        }
        .hero-btn {
            padding: 15px 40px;
            font-size: 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            position: relative;
        }
        .hero-btn:hover {
            transform: translateY(-3px);
        }
        .hero-btn:active {
            transform: translateY(-1px);
        }
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-register:hover {
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
            background: linear-gradient(135deg, #5568d3 0%, #653a8f 100%);
            color: white;
        }
        .btn-login {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }
        .btn-login:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        @keyframes buttonShimmer {
            0% {
                background-position: -1000px 0;
            }
            100% {
                background-position: 1000px 0;
            }
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
            <div class="hero-logo">
                <div class="hero-logo-icon">
                    <img src="assets/images/logo-icon.svg" alt="OfficePro Icon" onerror="this.style.display='none'; this.parentElement.innerHTML='🏢';">
                </div>
                <h1 class="hero-title">OfficePro</h1>
            </div>
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




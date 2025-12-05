<?php
/**
 * Shared Header Component
 */

// Initialize application
if (!defined('HEADER_LOADED')) {
    define('HEADER_LOADED', true);
    
    require_once __DIR__ . '/../../config/init.php';
    require_once __DIR__ . '/../../helpers/Auth.php';
    
    // Check if user is logged in
    if (!Auth::isLoggedIn()) {
        header('Location: /officepro/login.php');
        exit;
    }
    
    $currentUser = Auth::getCurrentUser();
    if (!$currentUser || !isset($currentUser['id'])) {
        header('Location: /officepro/login.php');
        exit;
    }
}
$companyName = $_SESSION['company_name'] ?? 'OfficePro';
$companyLogo = $_SESSION['company_logo'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Dashboard'; ?> - OfficePro</title>
    <link rel="stylesheet" href="/officepro/assets/css/style.css">
    <link rel="stylesheet" href="/officepro/assets/css/modal.css">
</head>
<body>
    <div class="main-layout">
        <?php include __DIR__ . '/sidebar.php'; ?>
        
        <div class="content-wrapper">
            <header class="header">
                <div class="header-left">
                    <button id="sidebar-toggle" class="btn btn-secondary" style="display: none;">☰</button>
                    <?php if ($companyLogo): ?>
                        <img src="/officepro/<?php echo htmlspecialchars($companyLogo); ?>" alt="Company Logo" class="company-logo" loading="lazy">
                    <?php endif; ?>
                    <span class="company-name"><?php echo htmlspecialchars($companyName); ?></span>
                </div>
                
                <div class="header-right">
                    <div class="notification-icon" id="notification-icon">
                        🔔
                        <span id="notification-badge" class="notification-badge" style="display: none;">0</span>
                    </div>
                    
                    <div class="user-profile" onclick="toggleUserMenu()">
                        <img src="/officepro/<?php echo htmlspecialchars($currentUser['profile_image']); ?>" 
                             alt="Profile" 
                             class="user-avatar"
                             onerror="this.src='/officepro/assets/images/default-avatar.png'"
                             loading="lazy">
                        <span class="user-name"><?php echo htmlspecialchars($currentUser['full_name']); ?></span>
                        <span>▼</span>
                    </div>
                    
                    <div id="user-menu" class="user-menu" style="display: none; position: absolute; right: 20px; top: 60px; background: white; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); min-width: 200px;">
                        <div style="padding: 10px; border-bottom: 1px solid #ddd;">
                            <strong><?php echo htmlspecialchars($currentUser['full_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($currentUser['role']); ?></small>
                        </div>
                        <a href="/officepro/app/views/profile.php" style="display: block; padding: 10px; color: #333;">My Profile</a>
                        <a href="#" onclick="logout()" style="display: block; padding: 10px; color: #dc3545;">Logout</a>
                    </div>
                </div>
            </header>
            
            <main class="main-content" style="padding: 20px;">




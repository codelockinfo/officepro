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

// If logo not in session, fetch from database
if (empty($companyLogo) && isset($_SESSION['company_id'])) {
    require_once __DIR__ . '/../../helpers/Database.php';
    $db = Database::getInstance();
    $company = $db->fetchOne("SELECT logo FROM companies WHERE id = ?", [$_SESSION['company_id']]);
    if ($company && !empty($company['logo'])) {
        $companyLogo = $company['logo'];
        $_SESSION['company_logo'] = $companyLogo; // Update session
    }
}

// Ensure profile image has a valid path
$profileImage = $currentUser['profile_image'] ?? 'assets/images/default-avatar.png';
if (empty($profileImage) || trim($profileImage) === '') {
    $profileImage = 'assets/images/default-avatar.png';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Dashboard'; ?> - OfficePro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/officepro/assets/css/style.css">
    <link rel="stylesheet" href="/officepro/assets/css/modal.css">
</head>
<body>
    <!-- SVG Filter for Blob Button Gooey Effect -->
    <svg style="position: absolute; width: 0; height: 0;">
        <defs>
            <filter id="goo">
                <feGaussianBlur in="SourceGraphic" stdDeviation="10" result="blur" />
                <feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 19 -9" result="goo" />
                <feComposite in="SourceGraphic" in2="goo" operator="atop"/>
            </filter>
        </defs>
    </svg>
    <div class="main-layout">
        <div class="sidebar-overlay" id="sidebar-overlay"></div>
        <?php include __DIR__ . '/sidebar.php'; ?>
        
        <div class="content-wrapper">
            <header class="header">
                <div class="header-left">
                    <button id="sidebar-toggle" class="togglebtn" style="display: none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" style="display: block;">
                            <path d="M4 18L20 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M4 12L20 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M4 6L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                    <?php if ($companyLogo && !empty(trim($companyLogo))): ?>
                        <img src="/officepro/<?php echo htmlspecialchars($companyLogo); ?>" 
                             alt="Company Logo" 
                             class="company-logo" 
                             loading="lazy"
                             onerror="this.style.display='none'; document.querySelector('.header-left .company-name').style.display='inline';">
                        <span class="company-name" style="display: none;"><?php echo htmlspecialchars($companyName); ?></span>
                    <?php else: ?>
                        <span class="company-name"><?php echo htmlspecialchars($companyName); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="header-right">
                    <div class="notification-wrapper" style="position: relative;">
                        <div class="notification-icon" id="notification-icon" onclick="toggleNotificationDropdown()">
                            <i class="fas fa-bell"></i>
                            <span id="notification-badge" class="notification-badge" style="display: none;">0</span>
                        </div>
                        <div id="notification-dropdown" class="notification-dropdown" style="display: none;">
                            <div class="notification-header">
                                <h3>Notifications</h3>
                            </div>
                            <div id="notification-list" class="notification-list">
                                <div class="notification-empty">Loading notifications...</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="user-profile" onclick="toggleUserMenu()">
                        <img src="/officepro/<?php echo htmlspecialchars($profileImage); ?>" 
                             alt="Profile" 
                             class="user-avatar"
                             onerror="this.onerror=null; this.src='/officepro/assets/images/default-avatar.png'"
                             loading="lazy">
                        <span class="user-name"><?php echo htmlspecialchars($currentUser['full_name']); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    
                    <div id="user-menu" class="user-menu" style="display: none; position: absolute; right: 20px; top: 70px; background: white; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); min-width: 200px;">
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




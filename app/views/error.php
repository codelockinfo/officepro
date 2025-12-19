<?php
/**
 * Error Page
 */

// Initialize application
require_once __DIR__ . '/../config/init.php';

$errorCode = $_GET['code'] ?? '403';
$errorMessage = $_GET['message'] ?? 'Access Denied';

$errorTitles = [
    '403' => 'Access Denied',
    '404' => 'Page Not Found',
    '500' => 'Internal Server Error'
];

$errorDescriptions = [
    '403' => 'You do not have permission to access this page.',
    '404' => 'The page you are looking for could not be found.',
    '500' => 'Something went wrong on our end. Please try again later.'
];

$title = $errorTitles[$errorCode] ?? 'Error';
$description = $_GET['message'] ?? $errorDescriptions[$errorCode] ?? 'An error occurred.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - OfficePro</title>
    <link rel="stylesheet" href="/public_html/assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .error-container {
            background: white;
            border-radius: 10px;
            padding: 60px 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .error-code {
            font-size: 120px;
            font-weight: bold;
            color: #4da6ff;
            line-height: 1;
            margin-bottom: 20px;
        }
        .error-title {
            font-size: 32px;
            color: #333;
            margin-bottom: 15px;
        }
        .error-description {
            font-size: 16px;
            color: #666;
            margin-bottom: 40px;
        }
        .error-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code"><?php echo $errorCode; ?></div>
        <h1 class="error-title"><?php echo htmlspecialchars($title); ?></h1>
        <p class="error-description"><?php echo htmlspecialchars($description); ?></p>
        
        <div class="error-actions">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/public_html/app/views/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
            <?php else: ?>
                <a href="/public_html/login.php" class="btn btn-primary">Login</a>
                <a href="/public_html/" class="btn btn-secondary">Go Home</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>



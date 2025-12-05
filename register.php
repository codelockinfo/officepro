<?php
/**
 * Employee Registration Page (with invitation token)
 */
session_start();
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
            max-width: 600px;
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
        .company-info {
            background: #e6f2ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 30px;
            text-align: center;
        }
        .image-preview {
            width: 150px;
            height: 150px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 10px auto 0;
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
            <h1>👤 Employee Registration</h1>
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
                    <input type="password" id="password" name="password" class="form-control" minlength="8" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="profile_image">Profile Image *</label>
                    <input type="file" id="profile_image" name="profile_image" class="form-control" accept="image/*" onchange="previewImage(this)" required>
                    <small class="text-muted">Minimum 200x200 pixels, max 2MB</small>
                    <div id="profile-preview" class="image-preview">
                        <span>No image selected</span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg w-100 mt-20">Complete Registration</button>
            </form>
        <?php endif; ?>
    </div>
    
    <script src="assets/js/app.js"></script>
    <script>
        function previewImage(input) {
            const preview = document.getElementById('profile-preview');
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
            
            fetch('/app/api/auth/register.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoader();
                if (data.success) {
                    showMessage('success', 'Registration successful! Redirecting...');
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



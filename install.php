<?php
/**
 * Installation Script
 * One-time setup for the application
 */

// Start session
session_start();

// Check if already installed
if (file_exists('installed.lock')) {
    die('Application is already installed. Delete installed.lock file to reinstall.');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = $_POST['db_host'] ?? 'localhost';
    $dbName = $_POST['db_name'] ?? 'officepro_attendance';
    $dbUser = $_POST['db_user'] ?? 'root';
    $dbPass = $_POST['db_pass'] ?? '';
    
    $systemAdminEmail = $_POST['admin_email'] ?? '';
    $systemAdminPassword = $_POST['admin_password'] ?? '';
    $systemAdminName = $_POST['admin_name'] ?? 'System Administrator';
    
    // Validate inputs
    if (empty($systemAdminEmail) || empty($systemAdminPassword)) {
        $errors[] = 'System admin email and password are required';
    }
    
    if (strlen($systemAdminPassword) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    
    if (empty($errors)) {
        try {
            // Connect to database
            $pdo = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");
            
            // Read and execute schema
            $schema = file_get_contents(__DIR__ . '/database/schema.sql');
            $pdo->exec($schema);
            
            // Create system admin user (in a special system company)
            $hashedPassword = password_hash($systemAdminPassword, PASSWORD_BCRYPT);
            
            // Create system company
            $pdo->exec("INSERT INTO companies (company_name, company_email, subscription_status, created_at) 
                       VALUES ('System', 'system@officepro.local', 'active', NOW())");
            $systemCompanyId = $pdo->lastInsertId();
            
            // Create system admin user
            $stmt = $pdo->prepare("INSERT INTO users (company_id, email, password, full_name, profile_image, role, status, created_at) 
                                   VALUES (?, ?, ?, ?, 'default-avatar.png', 'system_admin', 'active', NOW())");
            $stmt->execute([$systemCompanyId, $systemAdminEmail, $hashedPassword, $systemAdminName]);
            
            $adminId = $pdo->lastInsertId();
            
            // Update company owner
            $pdo->exec("UPDATE companies SET owner_id = {$adminId} WHERE id = {$systemCompanyId}");
            
            // Update database config
            $configContent = "<?php\n/**\n * Database Configuration\n */\n\nreturn [\n";
            $configContent .= "    'host' => '{$dbHost}',\n";
            $configContent .= "    'dbname' => '{$dbName}',\n";
            $configContent .= "    'username' => '{$dbUser}',\n";
            $configContent .= "    'password' => '{$dbPass}',\n";
            $configContent .= "    'charset' => 'utf8mb4',\n";
            $configContent .= "    'options' => [\n";
            $configContent .= "        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
            $configContent .= "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
            $configContent .= "        PDO::ATTR_EMULATE_PREPARES => false,\n";
            $configContent .= "    ]\n";
            $configContent .= "];\n";
            
            file_put_contents(__DIR__ . '/app/config/database.php', $configContent);
            
            // Create uploads directories
            $uploadDirs = [
                'uploads/profiles',
                'uploads/documents',
                'uploads/logos',
                'logs',
                'backups'
            ];
            
            foreach ($uploadDirs as $dir) {
                if (!file_exists($dir)) {
                    mkdir($dir, 0755, true);
                }
            }
            
            // Create default avatar
            copy(__DIR__ . '/assets/images/default-avatar.png', __DIR__ . '/uploads/profiles/default-avatar.png');
            
            // Create lock file
            file_put_contents('installed.lock', date('Y-m-d H:i:s'));
            
            $success = true;
            
        } catch (Exception $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - OfficePro Attendance System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .install-container {
            background: white;
            border-radius: 10px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #4da6ff;
            margin-bottom: 10px;
            text-align: center;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        input:focus {
            outline: none;
            border-color: #4da6ff;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: #4da6ff;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #3d8ce6;
        }
        .error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success {
            background: #efe;
            border: 1px solid #cfc;
            color: #3c3;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
        }
        .success h2 {
            color: #3c3;
            margin-bottom: 10px;
        }
        .success a {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 30px;
            background: #4da6ff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .info-box {
            background: #e6f2ff;
            border-left: 4px solid #4da6ff;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <h1>🏢 OfficePro Installation</h1>
        <p class="subtitle">Multi-Tenant Attendance & Leave Management System</p>
        
        <?php if ($success): ?>
            <div class="success">
                <h2>✓ Installation Successful!</h2>
                <p>The system has been installed successfully.</p>
                <p><strong>System Admin Credentials:</strong></p>
                <p>Email: <?php echo htmlspecialchars($systemAdminEmail); ?></p>
                <p>Password: (as you entered)</p>
                <a href="login.php">Go to Login</a>
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <strong>Prerequisites:</strong>
                <ul>
                    <li>PHP 7.4 or higher</li>
                    <li>MySQL 5.7 or higher</li>
                    <li>Composer installed</li>
                    <li>Run <code>composer install</code> before installation</li>
                </ul>
            </div>
            
            <form method="POST">
                <h3 style="margin-bottom: 15px; color: #4da6ff;">Database Configuration</h3>
                
                <div class="form-group">
                    <label for="db_host">Database Host</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                </div>
                
                <div class="form-group">
                    <label for="db_name">Database Name</label>
                    <input type="text" id="db_name" name="db_name" value="officepro_attendance" required>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Database Username</label>
                    <input type="text" id="db_user" name="db_user" value="root" required>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">Database Password</label>
                    <input type="password" id="db_pass" name="db_pass">
                </div>
                
                <h3 style="margin: 30px 0 15px; color: #4da6ff;">System Administrator</h3>
                
                <div class="form-group">
                    <label for="admin_name">Full Name</label>
                    <input type="text" id="admin_name" name="admin_name" value="System Administrator" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_email">Email</label>
                    <input type="email" id="admin_email" name="admin_email" placeholder="admin@example.com" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_password">Password (min. 8 characters)</label>
                    <input type="password" id="admin_password" name="admin_password" required>
                </div>
                
                <button type="submit" class="btn">Install System</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>



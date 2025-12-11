<?php
/**
 * Check Invitation Status
 * Use this to verify invitations are being stored correctly
 */

require_once __DIR__ . '/app/config/init.php';
require_once __DIR__ . '/app/helpers/Database.php';

$db = Database::getInstance();

// Get all invitations
$invitations = $db->fetchAll("SELECT * FROM invitations ORDER BY created_at DESC LIMIT 10");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Invitations - OfficePro</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #667eea;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .status-pending { color: orange; font-weight: bold; }
        .status-accepted { color: green; font-weight: bold; }
        .status-expired { color: red; font-weight: bold; }
        .status-cancelled { color: gray; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Invitations Check</h1>
        <p>Total invitations found: <?php echo count($invitations); ?></p>
        
        <?php if (empty($invitations)): ?>
            <p style="color: red;">No invitations found in database!</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Company ID</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Token</th>
                        <th>Status</th>
                        <th>Expires At</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invitations as $inv): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($inv['id']); ?></td>
                            <td><?php echo htmlspecialchars($inv['company_id']); ?></td>
                            <td><?php echo htmlspecialchars($inv['email']); ?></td>
                            <td><?php echo htmlspecialchars($inv['role']); ?></td>
                            <td style="font-size: 10px; max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo htmlspecialchars(substr($inv['token'], 0, 20)) . '...'; ?>
                            </td>
                            <td class="status-<?php echo htmlspecialchars($inv['status']); ?>">
                                <?php echo htmlspecialchars($inv['status']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($inv['expires_at']); ?></td>
                            <td><?php echo htmlspecialchars($inv['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <hr style="margin: 30px 0;">
        
        <h2>Test Invitation Creation</h2>
        <form method="POST" action="test_create_invitation.php">
            <p>
                <label>Company ID: <input type="number" name="company_id" value="1" required></label>
            </p>
            <p>
                <label>Email: <input type="email" name="email" placeholder="test@example.com" required></label>
            </p>
            <p>
                <label>Role: 
                    <select name="role" required>
                        <option value="employee">Employee</option>
                        <option value="manager">Manager</option>
                    </select>
                </label>
            </p>
            <p>
                <button type="submit">Create Test Invitation</button>
            </p>
        </form>
    </div>
</body>
</html>


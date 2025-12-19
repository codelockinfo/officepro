<?php
/**
 * System Admin Dashboard
 */

$pageTitle = 'System Admin Dashboard';
include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../../helpers/Database.php';

// Only system admins can access
Auth::checkRole(['system_admin'], 'Only system administrators can access this page.');

$db = Database::getInstance();

// Get platform statistics
$totalCompanies = $db->fetchOne("SELECT COUNT(*) as count FROM companies");
$activeCompanies = $db->fetchOne("SELECT COUNT(*) as count FROM companies WHERE subscription_status = 'active'");
$totalUsers = $db->fetchOne("SELECT COUNT(*) as count FROM users");
$totalInvitations = $db->fetchOne("SELECT COUNT(*) as count FROM invitations WHERE status = 'pending'");

// Recent companies
$recentCompanies = $db->fetchAll(
    "SELECT c.*, u.full_name as owner_name 
    FROM companies c 
    LEFT JOIN users u ON c.owner_id = u.id 
    ORDER BY c.created_at DESC 
    LIMIT 10"
);
?>

<h1>🔧 System Admin Dashboard</h1>

<!-- Platform Statistics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;">
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--primary-blue); margin-bottom: 10px;">Total Companies</h3>
        <div style="font-size: 48px; font-weight: bold; color: var(--primary-blue);">
            <?php echo $totalCompanies['count']; ?>
        </div>
    </div>
    
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--success-green); margin-bottom: 10px;">Active Companies</h3>
        <div style="font-size: 48px; font-weight: bold; color: var(--success-green);">
            <?php echo $activeCompanies['count']; ?>
        </div>
    </div>
    
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--primary-blue); margin-bottom: 10px;">Total Users</h3>
        <div style="font-size: 48px; font-weight: bold; color: var(--primary-blue);">
            <?php echo $totalUsers['count']; ?>
        </div>
    </div>
    
    <div class="card" style="text-align: center;">
        <h3 style="color: var(--warning-yellow); margin-bottom: 10px;">Pending Invites</h3>
        <div style="font-size: 48px; font-weight: bold; color: #856404;">
            <?php echo $totalInvitations['count']; ?>
        </div>
    </div>
</div>

<!-- Recent Companies -->
<div class="card">
    <h2 class="card-title">Recent Companies</h2>
    
    <?php if (count($recentCompanies) === 0): ?>
        <p style="text-align: center; padding: 40px; color: #666;">No companies registered yet</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Company Name</th>
                    <th>Email</th>
                    <th>Owner</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentCompanies as $company): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($company['company_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($company['company_email']); ?></td>
                    <td><?php echo htmlspecialchars($company['owner_name'] ?? '-'); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $company['subscription_status'] === 'active' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($company['subscription_status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($company['created_at'])); ?></td>
                    <td>
                        <a href="/public_html/app/views/system_admin/companies.php?id=<?php echo $company['id']; ?>" class="btn btn-sm btn-primary">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>


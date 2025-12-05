<?php
/**
 * System Admin - Users Management
 */

$pageTitle = 'Users Management';
include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../../helpers/Database.php';

// Only system admins can access
Auth::checkRole(['system_admin'], 'Only system administrators can manage users.');

$db = Database::getInstance();

// Get all users across all companies
$users = $db->fetchAll(
    "SELECT u.*, c.company_name, d.name as department_name 
    FROM users u 
    JOIN companies c ON u.company_id = c.id 
    LEFT JOIN departments d ON u.department_id = d.id 
    ORDER BY u.created_at DESC"
);
?>

<h1>👥 Users Management</h1>

<div class="card" style="margin-top: 20px;">
    <div style="padding: 20px; border-bottom: 1px solid #ddd; display: flex; gap: 20px;">
        <input type="text" id="search-users" placeholder="Search users..." class="form-control" style="flex: 1;" onkeyup="searchUsers()">
        <select id="filter-company" class="form-control" style="width: 250px;" onchange="filterUsers()">
            <option value="">All Companies</option>
            <?php
            $companies = $db->fetchAll("SELECT id, company_name FROM companies ORDER BY company_name");
            foreach ($companies as $company) {
                echo '<option value="' . $company['id'] . '">' . htmlspecialchars($company['company_name']) . '</option>';
            }
            ?>
        </select>
        <select id="filter-role" class="form-control" style="width: 150px;" onchange="filterUsers()">
            <option value="">All Roles</option>
            <option value="company_owner">Company Owner</option>
            <option value="manager">Manager</option>
            <option value="employee">Employee</option>
        </select>
    </div>
    
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Company</th>
                <th>Department</th>
                <th>Role</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="users-tbody">
            <?php foreach ($users as $user): ?>
            <tr class="user-row" data-company="<?php echo $user['company_id']; ?>" data-role="<?php echo $user['role']; ?>">
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <img src="/officepro/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                             alt="Profile" 
                             style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover;">
                        <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                    </div>
                </td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars($user['company_name']); ?></td>
                <td><?php echo htmlspecialchars($user['department_name'] ?? '-'); ?></td>
                <td>
                    <span class="badge badge-primary">
                        <?php echo str_replace('_', ' ', ucwords($user['role'], '_')); ?>
                    </span>
                </td>
                <td>
                    <?php
                    $statusColors = [
                        'active' => 'badge-success',
                        'pending' => 'badge-warning',
                        'suspended' => 'badge-danger'
                    ];
                    ?>
                    <span class="badge <?php echo $statusColors[$user['status']]; ?>">
                        <?php echo ucfirst($user['status']); ?>
                    </span>
                </td>
                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                <td>
                    <button onclick="viewUser(<?php echo $user['id']; ?>)" class="btn btn-sm btn-primary">View</button>
                    <?php if ($user['status'] === 'active'): ?>
                        <button onclick="suspendUser(<?php echo $user['id']; ?>)" class="btn btn-sm btn-warning">Suspend</button>
                    <?php else: ?>
                        <button onclick="activateUser(<?php echo $user['id']; ?>)" class="btn btn-sm btn-success">Activate</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    function searchUsers() {
        filterUsers();
    }
    
    function filterUsers() {
        const search = document.getElementById('search-users').value.toLowerCase();
        const companyFilter = document.getElementById('filter-company').value;
        const roleFilter = document.getElementById('filter-role').value;
        const rows = document.querySelectorAll('.user-row');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const matchesSearch = search === '' || text.includes(search);
            const matchesCompany = companyFilter === '' || row.dataset.company === companyFilter;
            const matchesRole = roleFilter === '' || row.dataset.role === roleFilter;
            
            row.style.display = (matchesSearch && matchesCompany && matchesRole) ? '' : 'none';
        });
    }
    
    function viewUser(id) {
        // Get user details
        ajaxRequest(`/officepro/app/api/system_admin/user_details.php?id=${id}`, 'GET', null, (response) => {
            if (response.success) {
                const user = response.data;
                
                const roleLabels = {
                    'system_admin': 'System Administrator',
                    'company_owner': 'Company Owner',
                    'manager': 'Manager',
                    'employee': 'Employee'
                };
                
                const statusColors = {
                    'active': 'badge-success',
                    'pending': 'badge-warning',
                    'suspended': 'badge-danger'
                };
                
                const content = `
                    <div style="padding: 20px;">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <img src="/officepro/${user.profile_image}" 
                                 alt="Profile" 
                                 style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-blue);"
                                 onerror="this.src='/officepro/assets/images/default-avatar.png'">
                        </div>
                        
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600; width: 40%;">Full Name:</td>
                                <td style="padding: 12px;">${user.full_name}</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Email:</td>
                                <td style="padding: 12px;">${user.email}</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Company:</td>
                                <td style="padding: 12px;"><strong>${user.company_name}</strong></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Department:</td>
                                <td style="padding: 12px;">${user.department_name || 'Not assigned'}</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Role:</td>
                                <td style="padding: 12px;">
                                    <span class="badge badge-primary">${roleLabels[user.role] || user.role}</span>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Status:</td>
                                <td style="padding: 12px;">
                                    <span class="badge ${statusColors[user.status]}">${user.status.toUpperCase()}</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 12px; font-weight: 600;">Member Since:</td>
                                <td style="padding: 12px;">${new Date(user.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</td>
                            </tr>
                        </table>
                    </div>
                `;
                
                const footer = `<button type="button" class="btn btn-primary" onclick="closeModal(this.closest('.modal-overlay').id)">Close</button>`;
                
                createModal('👤 User Details', content, footer);
            } else {
                showMessage('error', response.message || 'Failed to load user details');
            }
        });
    }
    
    function suspendUser(id) {
        confirmDialog(
            'This user will be unable to access the system.',
            () => {
                showMessage('info', 'User management API will be available soon!');
            },
            null,
            'Suspend User',
            '⚠️'
        );
    }
    
    function activateUser(id) {
        confirmDialog(
            'This user will regain access to the system.',
            () => {
                showMessage('info', 'User management API will be available soon!');
            },
            null,
            'Activate User',
            '✓'
        );
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


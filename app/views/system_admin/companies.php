<?php
/**
 * System Admin - Companies Management
 */

$pageTitle = 'Companies Management';
include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../../helpers/Database.php';

// Only system admins can access
Auth::checkRole(['system_admin'], 'Only system administrators can manage companies.');

$db = Database::getInstance();

// Get all companies
$companies = $db->fetchAll(
    "SELECT c.*, u.full_name as owner_name, u.email as owner_email,
    (SELECT COUNT(*) FROM users WHERE company_id = c.id AND status = 'active') as employee_count
    FROM companies c 
    LEFT JOIN users u ON c.owner_id = u.id 
    ORDER BY c.created_at DESC"
);
?>

<h1>🏢 Companies Management</h1>

<div class="card" style="margin-top: 20px;">
    <div style="padding: 20px; border-bottom: 1px solid #ddd;">
        <input type="text" id="search-companies" placeholder="Search companies..." class="form-control" onkeyup="searchCompanies()">
    </div>
    
    <div id="companies-list">
        <table class="table">
            <thead>
                <tr>
                    <th>Company Name</th>
                    <th>Email</th>
                    <th>Owner</th>
                    <th>Employees</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $company): ?>
                <tr class="company-row">
                    <td>
                        <strong><?php echo htmlspecialchars($company['company_name']); ?></strong>
                        <?php if ($company['logo']): ?>
                            <br><small>Has logo</small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($company['company_email']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($company['owner_name'] ?? '-'); ?>
                        <?php if ($company['owner_email']): ?>
                            <br><small><?php echo htmlspecialchars($company['owner_email']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $company['employee_count']; ?></td>
                    <td>
                        <?php
                        $statusColors = [
                            'active' => 'badge-success',
                            'suspended' => 'badge-danger',
                            'trial' => 'badge-warning'
                        ];
                        ?>
                        <span class="badge <?php echo $statusColors[$company['subscription_status']] ?? 'badge-secondary'; ?>">
                            <?php echo ucfirst($company['subscription_status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($company['created_at'])); ?></td>
                    <td>
                        <button onclick="viewCompany(<?php echo $company['id']; ?>)" class="btn btn-sm btn-primary">View</button>
                        <?php if ($company['subscription_status'] === 'active'): ?>
                            <button onclick="suspendCompany(<?php echo $company['id']; ?>)" class="btn btn-sm btn-warning">Suspend</button>
                        <?php else: ?>
                            <button onclick="activateCompany(<?php echo $company['id']; ?>)" class="btn btn-sm btn-success">Activate</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function searchCompanies() {
        const search = document.getElementById('search-companies').value.toLowerCase();
        const rows = document.querySelectorAll('.company-row');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(search) ? '' : 'none';
        });
    }
    
    function viewCompany(id) {
        ajaxRequest(`/officepro/app/api/system_admin/company_details.php?id=${id}`, 'GET', null, (response) => {
            if (response.success) {
                const company = response.data;
                
                const statusColors = {
                    'active': 'badge-success',
                    'trial': 'badge-warning',
                    'suspended': 'badge-danger'
                };
                
                const content = `
                    <div style="padding: 20px;">
                        ${company.logo ? `
                            <div style="text-align: center; margin-bottom: 20px;">
                                <img src="/officepro/${company.logo}" 
                                     alt="Company Logo" 
                                     style="max-height: 80px; max-width: 200px;">
                            </div>
                        ` : ''}
                        
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600; width: 40%;">Company Name:</td>
                                <td style="padding: 12px;"><strong>${company.company_name}</strong></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Email:</td>
                                <td style="padding: 12px;">${company.company_email}</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Phone:</td>
                                <td style="padding: 12px;">${company.phone || 'Not provided'}</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Address:</td>
                                <td style="padding: 12px;">${company.address || 'Not provided'}</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Owner:</td>
                                <td style="padding: 12px;">${company.owner_name || 'Not assigned'}</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Total Employees:</td>
                                <td style="padding: 12px;"><strong>${company.employee_count || 0}</strong></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Status:</td>
                                <td style="padding: 12px;">
                                    <span class="badge ${statusColors[company.subscription_status]}">${company.subscription_status.toUpperCase()}</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 12px; font-weight: 600;">Registered:</td>
                                <td style="padding: 12px;">${new Date(company.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</td>
                            </tr>
                        </table>
                    </div>
                `;
                
                const footer = `<button type="button" class="btn btn-primary" onclick="closeModal(this.closest('.modal-overlay').id)">Close</button>`;
                
                createModal('🏢 Company Details', content, footer);
            } else {
                showMessage('error', response.message || 'Failed to load company details');
            }
        });
    }
    
    function suspendCompany(id) {
        confirmDialog(
            'All employees of this company will be unable to access the system.',
            () => {
                ajaxRequest(`/officepro/app/api/system_admin/companies.php?action=suspend&id=${id}`, 'POST', null, (response) => {
                    if (response.success) {
                        showMessage('success', 'Company suspended');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showMessage('error', response.message || 'Failed to suspend company');
                    }
                });
            },
            null,
            'Suspend Company',
            '⚠️'
        );
    }
    
    function activateCompany(id) {
        ajaxRequest(`/officepro/app/api/system_admin/companies.php?action=activate&id=${id}`, 'POST', null, (response) => {
            if (response.success) {
                showMessage('success', 'Company activated');
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage('error', response.message || 'Failed to activate company');
            }
        });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


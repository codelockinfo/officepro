<?php
/**
 * Company Employees Management
 */

$pageTitle = 'Employees Management';
include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Tenant.php';

// Only company owners and managers can access
Auth::checkRole(['company_owner', 'manager'], 'Only company owners and managers can manage employees.');

$companyId = Tenant::getCurrentCompanyId();
$db = Database::getInstance();

// Get all employees
$employees = $db->fetchAll(
    "SELECT u.*, d.name as department_name 
    FROM users u 
    LEFT JOIN departments d ON u.department_id = d.id 
    WHERE u.company_id = ? 
    ORDER BY u.full_name ASC",
    [$companyId]
);
?>

<h1>👥 Employees</h1>

<div class="card" style="margin-top: 20px;">
    <div style="padding: 20px; border-bottom: 1px solid #ddd; display: flex; gap: 20px;">
        <input type="text" id="search-employees" placeholder="Search employees..." class="form-control" style="flex: 1;" onkeyup="searchEmployees()">
        <select id="filter-status" class="form-control" style="width: 150px;" onchange="filterEmployees()">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="pending">Pending</option>
            <option value="suspended">Suspended</option>
        </select>
    </div>
    
    <table class="table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Email</th>
                <th>Department</th>
                <th>Role</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="employees-tbody">
            <?php foreach ($employees as $emp): ?>
            <tr class="employee-row" data-status="<?php echo $emp['status']; ?>">
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <img src="/officepro/<?php echo htmlspecialchars($emp['profile_image']); ?>" 
                             alt="Profile" 
                             style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                        <strong><?php echo htmlspecialchars($emp['full_name']); ?></strong>
                    </div>
                </td>
                <td><?php echo htmlspecialchars($emp['email']); ?></td>
                <td><?php echo htmlspecialchars($emp['department_name'] ?? '-'); ?></td>
                <td>
                    <span class="badge badge-primary">
                        <?php echo str_replace('_', ' ', ucwords($emp['role'], '_')); ?>
                    </span>
                </td>
                <td>
                    <?php
                    $statusColors = ['active' => 'badge-success', 'pending' => 'badge-warning', 'suspended' => 'badge-danger'];
                    ?>
                    <span class="badge <?php echo $statusColors[$emp['status']]; ?>">
                        <?php echo ucfirst($emp['status']); ?>
                    </span>
                </td>
                <td><?php echo date('M d, Y', strtotime($emp['created_at'])); ?></td>
                <td>
                    <button onclick="viewEmployee(<?php echo $emp['id']; ?>)" class="btn btn-sm btn-primary">View</button>
                    <?php if (Auth::hasRole(['company_owner'])): ?>
                        <button onclick="editEmployee(<?php echo $emp['id']; ?>)" class="btn btn-sm btn-secondary">Edit</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    function searchEmployees() {
        filterEmployees();
    }
    
    function filterEmployees() {
        const search = document.getElementById('search-employees').value.toLowerCase();
        const statusFilter = document.getElementById('filter-status').value;
        const rows = document.querySelectorAll('.employee-row');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const matchesSearch = search === '' || text.includes(search);
            const matchesStatus = statusFilter === '' || row.dataset.status === statusFilter;
            
            row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
        });
    }
    
    function viewEmployee(id) {
        // Find employee data from table
        ajaxRequest(`/officepro/app/api/company/employee_details.php?id=${id}`, 'GET', null, (response) => {
            if (response.success) {
                const emp = response.data;
                
                const roleLabels = {
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
                            <img src="/officepro/${emp.profile_image}" 
                                 alt="Profile" 
                                 style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-blue);"
                                 onerror="this.src='/officepro/assets/images/default-avatar.png'">
                        </div>
                        
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600; width: 40%;">Full Name:</td>
                                <td style="padding: 12px;">${emp.full_name}</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Email:</td>
                                <td style="padding: 12px;">${emp.email}</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Department:</td>
                                <td style="padding: 12px;">${emp.department_name || 'Not assigned'}</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Role:</td>
                                <td style="padding: 12px;">
                                    <span class="badge badge-primary">${roleLabels[emp.role] || emp.role}</span>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Status:</td>
                                <td style="padding: 12px;">
                                    <span class="badge ${statusColors[emp.status]}">${emp.status.toUpperCase()}</span>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Joined:</td>
                                <td style="padding: 12px;">${new Date(emp.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</td>
                            </tr>
                        </table>
                        
                        ${emp.attendance_stats ? `
                            <div style="margin-top: 30px; padding: 20px; background: var(--light-blue); border-radius: 8px;">
                                <h4 style="color: var(--primary-blue); margin-bottom: 15px;">This Month's Stats</h4>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div>
                                        <p style="margin: 0; color: #666;">Days Worked</p>
                                        <p style="margin: 0; font-size: 24px; font-weight: bold; color: var(--primary-blue);">${emp.attendance_stats.days_worked || 0}</p>
                                    </div>
                                    <div>
                                        <p style="margin: 0; color: #666;">Total Hours</p>
                                        <p style="margin: 0; font-size: 24px; font-weight: bold; color: var(--primary-blue);">${emp.attendance_stats.total_hours || 0}h</p>
                                    </div>
                                    <div>
                                        <p style="margin: 0; color: #666;">Regular Hours</p>
                                        <p style="margin: 0; font-size: 24px; font-weight: bold; color: var(--primary-blue);">${emp.attendance_stats.regular_hours || 0}h</p>
                                    </div>
                                    <div>
                                        <p style="margin: 0; color: #666;">Overtime Hours</p>
                                        <p style="margin: 0; font-size: 24px; font-weight: bold; color: var(--overtime-orange);">${emp.attendance_stats.overtime_hours || 0}h</p>
                                    </div>
                                </div>
                            </div>
                        ` : ''}
                        
                        ${emp.leave_balance ? `
                            <div style="margin-top: 20px; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                                <h4 style="color: var(--primary-blue); margin-bottom: 15px;">Leave Balance (${new Date().getFullYear()})</h4>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                                    <div>
                                        <p style="margin: 0; color: #666;">Paid Leave</p>
                                        <p style="margin: 0; font-size: 20px; font-weight: bold;">${emp.leave_balance.paid_leave || 0} days</p>
                                    </div>
                                    <div>
                                        <p style="margin: 0; color: #666;">Sick Leave</p>
                                        <p style="margin: 0; font-size: 20px; font-weight: bold;">${emp.leave_balance.sick_leave || 0} days</p>
                                    </div>
                                    <div>
                                        <p style="margin: 0; color: #666;">Casual Leave</p>
                                        <p style="margin: 0; font-size: 20px; font-weight: bold;">${emp.leave_balance.casual_leave || 0} days</p>
                                    </div>
                                    <div>
                                        <p style="margin: 0; color: #666;">WFH Days</p>
                                        <p style="margin: 0; font-size: 20px; font-weight: bold;">${emp.leave_balance.wfh_days || 0} days</p>
                                    </div>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                `;
                
                const footer = `
                    <button type="button" class="btn btn-secondary" onclick="closeModal(this.closest('.modal-overlay').id)">Close</button>
                    ${emp.role !== 'company_owner' ? `<button type="button" class="btn btn-primary" onclick="closeModal(this.closest('.modal-overlay').id); editEmployee(${id});">Edit Employee</button>` : ''}
                `;
                
                createModal('👤 Employee Details', content, footer, 'modal-lg');
            } else {
                showMessage('error', response.message || 'Failed to load employee details');
            }
        });
    }
    
    function editEmployee(id) {
        showMessage('info', 'Edit employee feature will be available soon!');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


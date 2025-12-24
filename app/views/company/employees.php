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

// Get all employees (exclude company owners)
$employees = $db->fetchAll(
    "SELECT u.* 
    FROM users u 
    WHERE u.company_id = ? AND u.role != 'company_owner'
    ORDER BY 
        CASE WHEN u.status = 'active' THEN 1 ELSE 2 END,
        u.full_name ASC",
    [$companyId]
);
?>

<h1><i class="fas fa-users icon-employees"></i> Employees</h1>

<div class="card" style="margin-top: 20px;">
    <div style="padding: 20px; border-bottom: 1px solid #ddd; display: flex; gap: 20px;">
        <input type="text" id="search-employees" placeholder="Search employees..." class="form-control" style="flex: 1;" onkeyup="searchEmployees()">
        <select id="filter-status" class="form-control" style="width: 150px;" onchange="filterEmployees()">
            <option value="">All Employees</option>
            <option value="current">Current</option>
            <option value="past">Past</option>
        </select>
    </div>
    
    <div id="employees-container" style="padding: 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
        <?php foreach ($employees as $emp): 
            // Determine if current or past
            $isCurrent = $emp['status'] === 'active';
            $statusType = $isCurrent ? 'current' : 'past';
            $statusLabel = $isCurrent ? 'Current' : 'Past';
            $statusBadgeClass = $isCurrent ? 'badge-success' : 'badge-danger';
        ?>
        <div class="employee-card" data-status="<?php echo $statusType; ?>" style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); position: relative; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'" onmouseout="this.style.transform=''; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.1)'">
            <!-- Status Badge -->
            <div style="position: absolute; top: 15px; left: 15px;">
                <span class="badge <?php echo $statusBadgeClass; ?>" style="font-size: 11px; padding: 5px 10px;">
                    <?php echo $statusLabel; ?>
                </span>
            </div>
            
            <!-- Menu Button -->
            <div style="position: absolute; top: 15px; right: 15px;">
                <button onclick="showEmployeeMenu(<?php echo $emp['id']; ?>)" style="background: none; border: none; cursor: pointer; padding: 5px; color: #666;" onmouseover="this.style.color='#333'" onmouseout="this.style.color='#666'">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
            </div>
            
            <!-- Profile Picture -->
            <div style="text-align: center; margin-top: 10px; margin-bottom: 15px;">
                <?php 
                $profileImage = trim($emp['profile_image'] ?? '');
                $hasProfileImage = !empty($profileImage) && $profileImage !== 'assets/images/default-avatar.png';
                if ($hasProfileImage): 
                ?>
                    <img src="/officepro/<?php echo htmlspecialchars($profileImage); ?>" 
                         alt="Profile" 
                         style="width: 100px; height: 100px; border-radius: 12px; object-fit: cover; border: 2px solid #e0e0e0;"
                         onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div style="width: 100px; height: 100px; border-radius: 12px; border: 2px solid #e0e0e0; background: #f5f5f5; display: none; align-items: center; justify-content: center; margin: 0 auto;">
                        <i class="fas fa-user" style="font-size: 50px; color: #999;"></i>
                    </div>
                <?php else: ?>
                    <div style="width: 100px; height: 100px; border-radius: 12px; border: 2px solid #e0e0e0; background: #f5f5f5; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <i class="fas fa-user" style="font-size: 50px; color: #999;"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Name -->
            <h3 style="margin: 0 0 5px 0; text-align: center; color: #1a237e; font-size: 18px; font-weight: 600;">
                <?php echo htmlspecialchars($emp['full_name']); ?>
            </h3>
            
            <!-- Email -->
            <div style="margin: 10px 0; display: flex; align-items: center; gap: 8px; color: #666; font-size: 14px;">
                <i class="fas fa-envelope" style="color: #999; width: 16px;"></i>
                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($emp['email']); ?></span>
            </div>
            
            <!-- Hired Date -->
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0; color: #999; font-size: 13px; text-align: center;">
                <i class="fas fa-calendar-alt" style="margin-right: 5px;"></i>
                Hired: <?php echo date('d M Y', strtotime($emp['created_at'])); ?>
            </div>
            
            <!-- Actions (hidden menu) -->
            <div id="menu-<?php echo $emp['id']; ?>" style="display: none; position: absolute; top: 40px; right: 15px; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 10; min-width: 120px; padding: 5px 0;">
                <button onclick="viewEmployee(<?php echo $emp['id']; ?>); hideEmployeeMenu(<?php echo $emp['id']; ?>);" style="width: 100%; text-align: left; padding: 10px 15px; border: none; background: none; cursor: pointer; color: #333; font-size: 14px;" onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='none'">
                    <i class="fas fa-eye" style="margin-right: 8px;"></i> View
                </button>
                <?php if (Auth::hasRole(['company_owner'])): ?>
                <button onclick="editEmployee(<?php echo $emp['id']; ?>); hideEmployeeMenu(<?php echo $emp['id']; ?>);" style="width: 100%; text-align: left; padding: 10px 15px; border: none; background: none; cursor: pointer; color: #333; font-size: 14px;" onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='none'">
                    <i class="fas fa-edit" style="margin-right: 8px;"></i> Edit
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php if (count($employees) === 0): ?>
    <div style="text-align: center; padding: 60px 20px; color: #666;">
        <i class="fas fa-users" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
        <p>No employees found</p>
    </div>
    <?php endif; ?>
</div>

<script>
    function searchEmployees() {
        filterEmployees();
    }
    
    function filterEmployees() {
        const search = document.getElementById('search-employees').value.toLowerCase();
        const statusFilter = document.getElementById('filter-status').value;
        const cards = document.querySelectorAll('.employee-card');
        
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            const matchesSearch = search === '' || text.includes(search);
            const matchesStatus = statusFilter === '' || card.dataset.status === statusFilter;
            
            card.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
        });
    }
    
    function showEmployeeMenu(id) {
        // Hide all menus first
        document.querySelectorAll('[id^="menu-"]').forEach(menu => {
            menu.style.display = 'none';
        });
        
        // Show the clicked menu
        const menu = document.getElementById('menu-' + id);
        if (menu) {
            menu.style.display = 'block';
        }
        
        // Close menu when clicking outside
        setTimeout(() => {
            document.addEventListener('click', function closeMenu(e) {
                if (!e.target.closest('[id^="menu-"]') && !e.target.closest('button[onclick*="showEmployeeMenu"]')) {
                    document.querySelectorAll('[id^="menu-"]').forEach(m => m.style.display = 'none');
                    document.removeEventListener('click', closeMenu);
                }
            });
        }, 10);
    }
    
    function hideEmployeeMenu(id) {
        const menu = document.getElementById('menu-' + id);
        if (menu) {
            menu.style.display = 'none';
        }
    }
    
    function viewEmployee(id) {
        // Find employee data from table
        ajaxRequest(`/officepro/app/api/company/employee_details.php?id=${id}`, 'GET', null, (response) => {
            if (response && response.success) {
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
                
                // Check if profile image exists (not empty and not default avatar)
                const hasProfileImage = emp.profile_image && emp.profile_image.trim() !== '' && emp.profile_image !== 'assets/images/default-avatar.png';
                const profileImageHtml = hasProfileImage 
                    ? `<img src="/officepro/${emp.profile_image}" alt="Profile" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-blue);" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';"><div style="width: 120px; height: 120px; border-radius: 50%; border: 3px solid var(--primary-blue); background: #f5f5f5; display: none; align-items: center; justify-content: center; margin: 0 auto;"><i class="fas fa-user" style="font-size: 60px; color: #999;"></i></div>`
                    : `<div style="width: 120px; height: 120px; border-radius: 50%; border: 3px solid var(--primary-blue); background: #f5f5f5; display: flex; align-items: center; justify-content: center; margin: 0 auto;"><i class="fas fa-user" style="font-size: 60px; color: #999;"></i></div>`;
                
                const content = `
                    <div style="padding: 20px;">
                        <div style="text-align: center; margin-bottom: 20px;">
                            ${profileImageHtml}
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
                                <td style="padding: 12px; font-weight: 600;">Status:</td>
                                <td style="padding: 12px;">
                                    <span class="badge ${emp.status === 'active' ? 'badge-success' : 'badge-danger'}">${emp.status === 'active' ? 'Current' : 'Past'}</span>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 12px; font-weight: 600;">Joined:</td>
                                <td style="padding: 12px;">${new Date(emp.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</td>
                            </tr>
                        </table>
                        
                        ${emp.leave_balance ? `
                            <div style="margin-top: 20px; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                                <h4 style="color: var(--primary-blue); margin-bottom: 15px;">Leave Balance (${new Date().getFullYear()})</h4>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                                    <div>
                                        <p style="margin: 0; color: #666;">Paid Leave</p>
                                        <p style="margin: 0; font-size: 20px; font-weight: bold;">${emp.leave_balance.paid_leave || 0} days</p>
                                    </div>
                                </div>
                            </div>
                        ` : ''}
                        
                        <div style="margin-top: 20px; padding: 20px; background: #f0f8ff; border-radius: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h4 style="color: var(--primary-blue); margin: 0;">Monthly Stats</h4>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <select id="stats-month-${id}" class="form-control" style="width: 140px; padding: 8px 12px; font-size: 14px; border: 1px solid #ddd; border-radius: 6px;" onchange="changeEmployeeStatsMonth(${id})">
                                        ${generateMonthOptions()}
                                    </select>
                                    <select id="stats-year-${id}" class="form-control" style="width: 100px; padding: 8px 12px; font-size: 14px; border: 1px solid #ddd; border-radius: 6px;" onchange="changeEmployeeStatsMonth(${id})">
                                        ${generateYearOptions()}
                                    </select>
                                </div>
                            </div>
                            <div id="employee-stats-${id}">
                                ${emp.attendance_stats ? `
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                        <div>
                                            <p style="margin: 0; color: #666;">Days Worked</p>
                                            <p style="margin: 0; font-size: 24px; font-weight: bold; color: var(--primary-blue);">${emp.attendance_stats.days_worked || 0}</p>
                                        </div>
                                        <div>
                                            <p style="margin: 0; color: #666;">Total Hours</p>
                                            <p style="margin: 0; font-size: 24px; font-weight: bold; color: var(--primary-blue);">${emp.attendance_stats.total_hours || '00:00:00'}</p>
                                        </div>
                                        <div>
                                            <p style="margin: 0; color: #666;">Regular Hours</p>
                                            <p style="margin: 0; font-size: 24px; font-weight: bold; color: var(--primary-blue);">${emp.attendance_stats.regular_hours || '00:00:00'}</p>
                                        </div>
                                        <div>
                                            <p style="margin: 0; color: #666;">Overtime Hours</p>
                                            <p style="margin: 0; font-size: 24px; font-weight: bold; color: var(--overtime-orange);">${emp.attendance_stats.overtime_hours || '00:00:00'}</p>
                                        </div>
                                    </div>
                                ` : '<p style="text-align: center; color: #666; padding: 20px;">No stats available for this month</p>'}
                            </div>
                        </div>
                    </div>
                `;
                
                const footer = `
                    <button type="button" class="btn btn-secondary" onclick="closeModal(this.closest('.modal-overlay').id)">Close</button>
                    ${emp.role !== 'company_owner' ? `<button type="button" class="btn btn-primary" onclick="closeModal(this.closest('.modal-overlay').id); editEmployee(${id});">Edit Employee</button>` : ''}
                `;
                
                createModal('<i class="fas fa-user"></i> Employee Details', content, footer, 'modal-lg');
                
                // Set default month/year in selectors
                const currentDate = new Date();
                const currentMonth = currentDate.getMonth() + 1;
                const currentYear = currentDate.getFullYear();
                
                setTimeout(() => {
                    const monthSelect = document.getElementById('stats-month-' + id);
                    const yearSelect = document.getElementById('stats-year-' + id);
                    if (monthSelect) monthSelect.value = currentMonth;
                    if (yearSelect) yearSelect.value = currentYear;
                }, 100);
            } else {
                showMessage('error', (response && response.message) ? response.message : 'Failed to load employee details');
            }
        }, (error) => {
            console.error('View employee error:', error);
            showMessage('error', 'Failed to load employee details. Please check your connection and try again.');
        });
    }
    
    function generateMonthOptions() {
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                           'July', 'August', 'September', 'October', 'November', 'December'];
        let options = '';
        for (let i = 1; i <= 12; i++) {
            options += `<option value="${i}">${monthNames[i - 1]}</option>`;
        }
        return options;
    }
    
    function generateYearOptions() {
        const currentYear = new Date().getFullYear();
        let options = '';
        // Show current year and 2 years back
        for (let i = currentYear; i >= currentYear - 2; i--) {
            options += `<option value="${i}">${i}</option>`;
        }
        return options;
    }
    
    function changeEmployeeStatsMonth(employeeId) {
        const monthSelect = document.getElementById('stats-month-' + employeeId);
        const yearSelect = document.getElementById('stats-year-' + employeeId);
        const statsContainer = document.getElementById('employee-stats-' + employeeId);
        
        if (!monthSelect || !yearSelect || !statsContainer) return;
        
        const month = parseInt(monthSelect.value);
        const year = parseInt(yearSelect.value);
        
        // Show loading
        statsContainer.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">Loading stats...</p>';
        
        // Fetch stats for selected month/year
        ajaxRequest(`/officepro/app/api/company/employee_details.php?id=${employeeId}&month=${month}&year=${year}`, 'GET', null, (response) => {
            if (response && response.success && response.data) {
                const emp = response.data;
                let statsHTML = '';
                
                if (emp.attendance_stats) {
                    statsHTML = `
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <p style="margin: 0; color: #666;">Days Worked</p>
                                <p style="margin: 0; font-size: 24px; font-weight: bold; color: var(--primary-blue);">${emp.attendance_stats.days_worked || 0}</p>
                            </div>
                            <div>
                                <p style="margin: 0; color: #666;">Total Hours</p>
                                <p style="margin: 0; font-size: 24px; font-weight: bold; color: var(--primary-blue);">${emp.attendance_stats.total_hours || '00:00:00'}</p>
                            </div>
                            <div>
                                <p style="margin: 0; color: #666;">Regular Hours</p>
                                <p style="margin: 0; font-size: 24px; font-weight: bold; color: var(--primary-blue);">${emp.attendance_stats.regular_hours || '00:00:00'}</p>
                            </div>
                            <div>
                                <p style="margin: 0; color: #666;">Overtime Hours</p>
                                <p style="margin: 0; font-size: 24px; font-weight: bold; color: var(--overtime-orange);">${emp.attendance_stats.overtime_hours || '00:00:00'}</p>
                            </div>
                        </div>
                    `;
                } else {
                    statsHTML = '<p style="text-align: center; color: #666; padding: 20px;">No stats available for this month</p>';
                }
                
                statsContainer.innerHTML = statsHTML;
            } else {
                statsContainer.innerHTML = '<p style="text-align: center; color: #dc3545; padding: 20px;">Failed to load stats. Please try again.</p>';
            }
        }, (error) => {
            console.error('Failed to load employee stats:', error);
            statsContainer.innerHTML = '<p style="text-align: center; color: #dc3545; padding: 20px;">Error loading stats. Please try again.</p>';
        });
    }
    
    // Calendar functions removed - now using month selector for stats only
    
    function editEmployee(id) {
        // Fetch employee details
        ajaxRequest(`/officepro/app/api/company/employee_details.php?id=${id}`, 'GET', null, (response) => {
            if (response && response.success) {
                const emp = response.data;
                
                // Format join date for date input (YYYY-MM-DD)
                const joinDate = emp.created_at ? new Date(emp.created_at).toISOString().split('T')[0] : '';
                
                const content = `
                    <form id="edit-employee-form" onsubmit="saveEmployee(event, ${id})">
                        <div class="form-group">
                            <label class="form-label" for="edit_full_name">Full Name *</label>
                            <input type="text" id="edit_full_name" name="full_name" class="form-control" value="${emp.full_name}" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="edit_email">Email *</label>
                            <input type="email" id="edit_email" name="email" class="form-control" value="${emp.email}" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="edit_join_date">Join Date *</label>
                            <input type="date" id="edit_join_date" name="join_date" class="form-control" value="${joinDate}" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="edit_status">Status *</label>
                            <select id="edit_status" name="status" class="form-control" required>
                                <option value="active" ${emp.status === 'active' ? 'selected' : ''}>Current</option>
                                <option value="pending" ${emp.status === 'pending' ? 'selected' : ''}>Past (Pending)</option>
                                <option value="suspended" ${emp.status === 'suspended' ? 'selected' : ''}>Past (Suspended)</option>
                            </select>
                        </div>
                    </form>
                `;
                
                const footer = `
                    <button type="button" class="btn btn-secondary" onclick="closeModal(this.closest('.modal-overlay').id)">Cancel</button>
                    <button type="submit" form="edit-employee-form" class="btn btn-primary">Save Changes</button>
                `;
                
                createModal('<i class="fas fa-edit"></i> Edit Employee', content, footer, 'modal-md');
            } else {
                showMessage('error', (response && response.message) ? response.message : 'Failed to load employee details');
            }
        }, (error) => {
            console.error('Edit employee error:', error);
            showMessage('error', 'Failed to load employee details. Please check your connection and try again.');
        });
    }
    
    function saveEmployee(event, id) {
        event.preventDefault();
        const formData = new FormData(event.target);
        const data = Object.fromEntries(formData);
        
        ajaxRequest(`/officepro/app/api/company/employees.php?action=update&id=${id}`, 'POST', data, (response) => {
            if (response.success) {
                showMessage('success', 'Employee updated successfully!');
                closeModal(document.querySelector('.modal-overlay.active').id);
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage('error', response.message || 'Failed to update employee');
            }
        }, (error) => {
            console.error('Update employee error:', error);
            showMessage('error', 'An error occurred while updating employee');
        });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


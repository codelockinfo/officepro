<?php
/**
 * Shared Sidebar Navigation
 */

$currentRole = $_SESSION['role'] ?? 'employee';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<nav class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">🏢 OfficePro</div>
    </div>
    
    <div class="sidebar-nav">
        <a href="/officepro/app/views/dashboard.php" class="nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
            <span>📊</span> Dashboard
        </a>
        
        <?php if ($currentRole !== 'company_owner'): ?>
        <a href="/officepro/app/views/attendance.php" class="nav-item <?php echo $currentPage === 'attendance' ? 'active' : ''; ?>">
            <span>⏰</span> Attendance
        </a>
        
        <a href="/officepro/app/views/leaves.php" class="nav-item <?php echo $currentPage === 'leaves' ? 'active' : ''; ?>">
            <span>📅</span> My Leaves
        </a>
        <?php endif; ?>
        
        <?php if (in_array($currentRole, ['manager', 'company_owner', 'system_admin'])): ?>
        <a href="/officepro/app/views/leave_approvals.php" class="nav-item <?php echo $currentPage === 'leave_approvals' ? 'active' : ''; ?>">
            <span>✓</span> Leave Approvals
        </a>
        <?php endif; ?>
        
        <a href="/officepro/app/views/calendar.php" class="nav-item <?php echo $currentPage === 'calendar' ? 'active' : ''; ?>">
            <span>📆</span> Calendar
        </a>
        
        <?php if ($currentRole !== 'company_owner'): ?>
        <a href="/officepro/app/views/employee/credentials.php" class="nav-item <?php echo $currentPage === 'credentials' ? 'active' : ''; ?>">
            <span>🔑</span> My Credentials
        </a>
        
        <a href="/officepro/app/views/employee/tasks.php" class="nav-item <?php echo $currentPage === 'tasks' ? 'active' : ''; ?>">
            <span>✓</span> My Tasks
        </a>
        <?php endif; ?>
        
        <?php if (in_array($currentRole, ['manager', 'company_owner'])): ?>
        <a href="/officepro/app/views/reports/dashboard.php" class="nav-item <?php echo $currentPage === 'dashboard' && strpos($_SERVER['PHP_SELF'], 'reports') !== false ? 'active' : ''; ?>">
            <span>📈</span> Reports
        </a>
        <?php endif; ?>
        
        <?php if (in_array($currentRole, ['company_owner'])): ?>
        <hr style="border: none; border-top: 1px solid rgba(77, 166, 255, 0.3); margin: 10px 0;">
        
        <a href="/officepro/app/views/company/settings.php" class="nav-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
            <span>⚙️</span> Company Settings
        </a>
        
        <a href="/officepro/app/views/company/employees.php" class="nav-item <?php echo $currentPage === 'employees' ? 'active' : ''; ?>">
            <span>👥</span> Employees
        </a>
        
        <a href="/officepro/app/views/company/departments.php" class="nav-item <?php echo $currentPage === 'departments' ? 'active' : ''; ?>">
            <span>🏢</span> Departments
        </a>
        
        <a href="/officepro/app/views/company/invitations.php" class="nav-item <?php echo $currentPage === 'invitations' ? 'active' : ''; ?>">
            <span>✉️</span> Invitations
        </a>
        <?php endif; ?>
        
        <?php if ($currentRole === 'system_admin'): ?>
        <hr style="border: none; border-top: 1px solid rgba(77, 166, 255, 0.3); margin: 10px 0;">
        
        <a href="/officepro/app/views/system_admin/dashboard.php" class="nav-item">
            <span>🔧</span> System Admin
        </a>
        
        <a href="/officepro/app/views/system_admin/companies.php" class="nav-item">
            <span>🏢</span> Companies
        </a>
        
        <a href="/officepro/app/views/system_admin/users.php" class="nav-item">
            <span>👥</span> All Users
        </a>
        <?php endif; ?>
    </div>
</nav>




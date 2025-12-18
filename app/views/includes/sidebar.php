<?php
/**
 * Shared Sidebar Navigation
 */

$currentRole = $_SESSION['role'] ?? 'employee';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<nav class="sidebar">
    <div class="sidebar-header">
        <a href="/officepro/app/views/dashboard.php">
        <div class="sidebar-logo">
            <img src="/officepro/assets/images/logo1.png" alt="OfficePro Logo" style="height: 50px; width: auto; margin-right: 10px;">
            <span>OfficePro</span>
        </div>
        </a>
    </div>
    
    <div class="sidebar-nav">
        <a href="/officepro/app/views/dashboard.php" class="nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line icon-dashboard"></i> Dashboard
        </a>
        
        <?php if ($currentRole !== 'company_owner'): ?>
        <a href="/officepro/app/views/attendance.php" class="nav-item <?php echo $currentPage === 'attendance' ? 'active' : ''; ?>">
            <i class="fas fa-clock icon-attendance"></i> Attendance
        </a>
        
        <a href="/officepro/app/views/leaves.php" class="nav-item <?php echo $currentPage === 'leaves' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt icon-leaves"></i> My Leaves
        </a>
        <?php endif; ?>
        
        <?php if (in_array($currentRole, ['manager', 'company_owner', 'system_admin'])): ?>
        <a href="/officepro/app/views/leave_approvals.php" class="nav-item <?php echo $currentPage === 'leave_approvals' ? 'active' : ''; ?>">
            <i class="fas fa-check-circle icon-approvals"></i> Leave Approvals
        </a>
        <?php endif; ?>
        
        <a href="/officepro/app/views/calendar.php" class="nav-item <?php echo $currentPage === 'calendar' ? 'active' : ''; ?>">
            <i class="fas fa-calendar icon-calendar"></i> Calendar
        </a>
        
        <?php if ($currentRole !== 'company_owner'): ?>
        <a href="/officepro/app/views/employee/credentials.php" class="nav-item <?php echo $currentPage === 'credentials' ? 'active' : ''; ?>">
            <i class="fas fa-key icon-credentials"></i> My Credentials
        </a>
        
        <a href="/officepro/app/views/employee/tasks.php" class="nav-item <?php echo $currentPage === 'tasks' ? 'active' : ''; ?>">
            <i class="fas fa-tasks icon-tasks"></i> My Tasks
        </a>
        <?php endif; ?>
        
        <?php if (in_array($currentRole, ['manager', 'company_owner'])): ?>
        <a href="/officepro/app/views/reports/report_dashboard.php" class="nav-item <?php echo $currentPage === 'report_dashboard' && strpos($_SERVER['PHP_SELF'], 'reports') !== false ? 'active' : ''; ?>">
            <i class="fas fa-chart-line icon-reports"></i> Reports
        </a>
        <?php endif; ?>
        
        <?php if (in_array($currentRole, ['company_owner'])): ?>
        <hr style="border: none; border-top: 1px solid rgba(77, 166, 255, 0.3); margin: 10px 0;">
        
        <a href="/officepro/app/views/company/settings.php" class="nav-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
            <i class="fas fa-cog icon-settings"></i> Company Settings
        </a>
        
        <a href="/officepro/app/views/company/employees.php" class="nav-item <?php echo $currentPage === 'employees' ? 'active' : ''; ?>">
            <i class="fas fa-users icon-employees"></i> Employees
        </a>
        
        <a href="/officepro/app/views/company/departments.php" class="nav-item <?php echo $currentPage === 'departments' ? 'active' : ''; ?>">
            <i class="fas fa-building icon-departments"></i> Departments
        </a>
        
        <a href="/officepro/app/views/company/invitations.php" class="nav-item <?php echo $currentPage === 'invitations' ? 'active' : ''; ?>">
            <i class="fas fa-envelope icon-invitations"></i> Invitations
        </a>
        
        <a href="/officepro/app/views/company/tasks.php" class="nav-item <?php echo $currentPage === 'tasks' && strpos($_SERVER['PHP_SELF'], 'company') !== false ? 'active' : ''; ?>">
            <i class="fas fa-tasks icon-tasks"></i> Task Management
        </a>
        <?php endif; ?>
        
        <?php if ($currentRole === 'system_admin'): ?>
        <hr style="border: none; border-top: 1px solid rgba(77, 166, 255, 0.3); margin: 10px 0;">
        
        <a href="/officepro/app/views/system_admin/dashboard.php" class="nav-item">
            <i class="fas fa-tools icon-admin"></i> System Admin
        </a>
        
        <a href="/officepro/app/views/system_admin/companies.php" class="nav-item">
            <i class="fas fa-building icon-companies"></i> Companies
        </a>
        
        <a href="/officepro/app/views/system_admin/users.php" class="nav-item">
            <i class="fas fa-users icon-all-users"></i> All Users
        </a>
        <?php endif; ?>
    </div>
</nav>




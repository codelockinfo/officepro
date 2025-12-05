<?php
/**
 * Departments Management Page
 */

$pageTitle = 'Departments';
include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Tenant.php';

// Only company owners can access
Auth::checkRole(['company_owner'], 'Only company owners can manage departments.');

$companyId = Tenant::getCurrentCompanyId();
$db = Database::getInstance();

// Get departments
$departments = $db->fetchAll(
    "SELECT d.*, u.full_name as manager_name,
    (SELECT COUNT(*) FROM users WHERE department_id = d.id AND status = 'active') as employee_count
    FROM departments d 
    LEFT JOIN users u ON d.manager_id = u.id 
    WHERE d.company_id = ? 
    ORDER BY d.name ASC",
    [$companyId]
);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1>🏢 Departments</h1>
    <button onclick="openAddDepartmentModal()" class="btn btn-primary">+ Add Department</button>
</div>

<div class="card">
    <h2 class="card-title">Company Departments</h2>
    
    <?php if (count($departments) === 0): ?>
        <p style="text-align: center; padding: 40px; color: #666;">No departments created yet</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Department Name</th>
                    <th>Manager</th>
                    <th>Employees</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($departments as $dept): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($dept['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($dept['manager_name'] ?? 'Not assigned'); ?></td>
                    <td><?php echo $dept['employee_count']; ?> employees</td>
                    <td><?php echo date('M d, Y', strtotime($dept['created_at'])); ?></td>
                    <td>
                        <button onclick="editDepartment(<?php echo $dept['id']; ?>)" class="btn btn-sm btn-secondary">Edit</button>
                        <button onclick="deleteDepartment(<?php echo $dept['id']; ?>)" class="btn btn-sm btn-danger">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Add Department Modal -->
<div id="department-modal" class="modal-overlay">
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h3 class="modal-title" id="dept-modal-title">Add Department</h3>
            <button type="button" class="modal-close" onclick="closeModal('department-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="department-form" onsubmit="saveDepartment(event)">
                <input type="hidden" id="dept_id" name="id">
                
                <div class="form-group">
                    <label class="form-label" for="dept_name">Department Name *</label>
                    <input type="text" id="dept_name" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="manager_id">Manager (Optional)</label>
                    <select id="manager_id" name="manager_id" class="form-control">
                        <option value="">No manager</option>
                        <?php
                        $managers = $db->fetchAll(
                            "SELECT id, full_name FROM users 
                            WHERE company_id = ? AND role IN ('manager', 'company_owner') AND status = 'active' 
                            ORDER BY full_name",
                            [$companyId]
                        );
                        foreach ($managers as $mgr) {
                            echo '<option value="' . $mgr['id'] . '">' . htmlspecialchars($mgr['full_name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('department-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openAddDepartmentModal() {
        document.getElementById('department-form').reset();
        document.getElementById('dept_id').value = '';
        document.getElementById('dept-modal-title').textContent = 'Add Department';
        openModal('department-modal');
    }
    
    function saveDepartment(event) {
        event.preventDefault();
        showMessage('success', 'Department saved successfully!');
        closeModal('department-modal');
        // API implementation coming soon
    }
    
    function editDepartment(id) {
        showMessage('info', 'Edit department feature coming soon!');
    }
    
    function deleteDepartment(id) {
        confirmDialog(
            'This department and all related data will be deleted.',
            () => {
                showMessage('info', 'Delete department API coming soon!');
            },
            null,
            'Delete Department',
            '🏢'
        );
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


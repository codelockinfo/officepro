<?php
/**
 * Task Management Page for Company Owner
 */

$pageTitle = 'Task Management';
include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Tenant.php';

$companyId = Tenant::getCurrentCompanyId();
$userId = $currentUser['id'];
$db = Database::getInstance();

// Get all tasks for the company
$allTasks = $db->fetchAll(
    "SELECT t.*, 
     creator.full_name as created_by_name,
     assignee.full_name as assigned_to_name
     FROM tasks t 
     LEFT JOIN users creator ON t.created_by = creator.id
     LEFT JOIN users assignee ON t.assigned_to = assignee.id
     WHERE t.company_id = ? 
     ORDER BY 
        CASE 
            WHEN t.status = 'todo' THEN 1
            WHEN t.status = 'in_progress' THEN 2
            WHEN t.status = 'done' THEN 3
        END,
        t.created_at DESC",
    [$companyId]
);

// Get employees for assignment (exclude company owners)
$employees = $db->fetchAll(
    "SELECT id, full_name, email FROM users WHERE company_id = ? AND status = 'active' AND role != 'company_owner' ORDER BY full_name",
    [$companyId]
);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1><i class="fas fa-tasks"></i> Task Management</h1>
    <button onclick="openCreateTaskModal()" class="btn btn-primary custom-btn-primary">+ Create Task</button>
</div>

<div class="card">
    <h2 class="card-title">All Tasks</h2>
    
    <?php if (count($allTasks) === 0): ?>
        <p style="text-align: center; padding: 40px; color: #666;">No tasks created yet</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Task Name</th>
                        <th>Description</th>
                        <th>Assigned To</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Priority</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allTasks as $task): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($task['title']); ?></strong></td>
                        <td><?php echo htmlspecialchars(substr($task['description'] ?? '', 0, 50)) . (strlen($task['description'] ?? '') > 50 ? '...' : ''); ?></td>
                        <td><?php echo htmlspecialchars($task['assigned_to_name'] ?? 'N/A'); ?></td>
                        <td>
                            <?php
                            $statusColors = [
                                'todo' => '#c36522',
                                'in_progress' => '#1276e2',
                                'done' => '#00ad25'
                            ];
                            $statusLabels = [
                                'todo' => 'Pending',
                                'in_progress' => 'Processing',
                                'done' => 'Completed'
                            ];
                            $currentStatus = $task['status'] ?? 'todo';
                            $badgeColor = $statusColors[$currentStatus] ?? '#c36522';
                            $badgeText = $statusLabels[$currentStatus] ?? 'Pending';
                            ?>
                            <span class="badge" style="background-color: <?php echo $badgeColor; ?>; color: white; padding: 6px 12px; border-radius: 4px; font-weight: 500;">
                                <?php echo $badgeText; ?>
                            </span>
                        </td>
                        <td><?php echo $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : '-'; ?></td>
                        <td>
                            <?php
                            $priorityColors = [
                                'low' => 'badge-info',
                                'medium' => 'badge-warning',
                                'high' => 'badge-danger'
                            ];
                            $priorityLabels = [
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High'
                            ];
                            ?>
                            <span class="badge <?php echo $priorityColors[$task['priority']] ?? 'badge-secondary'; ?>">
                                <?php echo $priorityLabels[$task['priority']] ?? ucfirst($task['priority']); ?>
                            </span>
                        </td>
                        <td>
                            <button onclick="editTask(<?php echo $task['id']; ?>)" class="btn btn-sm btn-primary">Edit</button>
                            <button onclick="deleteTask(<?php echo $task['id']; ?>)" class="btn btn-sm btn-danger">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Create/Edit Task Modal -->
<div id="task-modal" class="modal-overlay">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3 class="modal-title" id="task-modal-title">Create Task</h3>
            <button type="button" class="modal-close" onclick="closeModal('task-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="task-form" onsubmit="saveTask(event)">
                <input type="hidden" id="task_id" name="id">
                
                <div class="form-group">
                    <label class="form-label" for="task_title">Task Name *</label>
                    <input type="text" id="task_title" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="task_description">Task Description</label>
                    <textarea id="task_description" name="description" class="form-control" rows="4" placeholder="Enter task description..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="assigned_to">Assign To Employee *</label>
                    <select id="assigned_to" name="assigned_to" class="form-control" required>
                        <option value="">Select employee...</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name'] . ' (' . $emp['email'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label" for="due_date">Due Date</label>
                        <input type="date" id="due_date" name="due_date" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="priority">Priority</label>
                        <select id="priority" name="priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>
                
                <input type="hidden" id="status" name="status" value="todo">
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('task-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openCreateTaskModal() {
        document.getElementById('task-form').reset();
        document.getElementById('task_id').value = '';
        document.getElementById('status').value = 'todo'; // Default to pending
        document.getElementById('task-modal-title').textContent = 'Create Task';
        openModal('task-modal');
    }
    
    function editTask(id) {
        ajaxRequest(`/officepro/app/api/employee/tasks.php?action=get&id=${id}`, 'GET', null, (response) => {
            if (response.success && response.data) {
                const task = response.data;
                
                document.getElementById('task_id').value = task.id;
                document.getElementById('task_title').value = task.title || '';
                document.getElementById('task_description').value = task.description || '';
                document.getElementById('assigned_to').value = task.assigned_to || '';
                
                if (task.due_date) {
                    // Format date from YYYY-MM-DD to date input format
                    document.getElementById('due_date').value = task.due_date.split(' ')[0];
                } else {
                    document.getElementById('due_date').value = '';
                }
                
                document.getElementById('priority').value = task.priority || 'medium';
                // Status is hidden, always 'todo' for new tasks, but keep existing status when editing
                if (id) {
                    document.getElementById('status').value = task.status || 'todo';
                } else {
                    document.getElementById('status').value = 'todo';
                }
                
                document.getElementById('task-modal-title').textContent = 'Edit Task';
                openModal('task-modal');
            } else {
                showMessage('error', response.message || 'Failed to load task details');
            }
        });
    }
    
    function saveTask(event) {
        event.preventDefault();
        const formData = new FormData(event.target);
        const data = Object.fromEntries(formData);
        const id = data.id;
        delete data.id;
        
        // due_date is already in YYYY-MM-DD format from date input
        // Ensure status is always 'todo' (pending) for new tasks
        if (!id) {
            data.status = 'todo';
        }
        
        const action = id ? 'update' : 'create';
        const url = id ? `/officepro/app/api/employee/tasks.php?action=${action}&id=${id}` : `/officepro/app/api/employee/tasks.php?action=${action}`;
        
        ajaxRequest(url, 'POST', data, (response) => {
            if (response.success) {
                showMessage('success', 'Task saved successfully!');
                closeModal('task-modal');
                setTimeout(() => location.reload(), 1000);
            } else {
                const errorMsg = response.message || 'Failed to save task';
                console.error('Task save error:', response);
                showMessage('error', errorMsg);
            }
        }, (error) => {
            console.error('Task save request failed:', error);
            showMessage('error', 'An error occurred. Please check the console for details.');
        });
    }
    
    function deleteTask(id) {
        confirmDialog(
            'This task will be permanently deleted.',
            () => {
                ajaxRequest(`/officepro/app/api/employee/tasks.php?action=delete&id=${id}`, 'DELETE', null, (response) => {
                    if (response.success) {
                        showMessage('success', 'Task deleted');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showMessage('error', response.message || 'Failed to delete task');
                    }
                });
            },
            null,
            'Delete Task',
            '<i class="fas fa-trash-alt"></i>',
            'var(--danger-red)'
        );
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


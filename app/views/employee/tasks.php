<?php
/**
 * Task Management Page
 */

$pageTitle = 'My Tasks';
include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Tenant.php';

$companyId = Tenant::getCurrentCompanyId();
$userId = $currentUser['id'];
$db = Database::getInstance();

// Get tasks assigned to user
$myTasks = $db->fetchAll(
    "SELECT t.*, u.full_name as created_by_name 
    FROM tasks t 
    JOIN users u ON t.created_by = u.id 
    WHERE t.company_id = ? AND t.assigned_to = ? 
    ORDER BY 
        CASE 
            WHEN t.status = 'todo' THEN 1
            WHEN t.status = 'in_progress' THEN 2
            WHEN t.status = 'done' THEN 3
        END,
        t.due_date ASC",
    [$companyId, $userId]
);

// Get tasks created by user
$createdTasks = $db->fetchAll(
    "SELECT t.*, u.full_name as assigned_to_name 
    FROM tasks t 
    JOIN users u ON t.assigned_to = u.id 
    WHERE t.company_id = ? AND t.created_by = ? 
    ORDER BY t.created_at DESC",
    [$companyId, $userId]
);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h1>✓ My Tasks</h1>
    <button onclick="openCreateTaskModal()" class="btn btn-primary">+ Create Task</button>
</div>

<!-- Tabs -->
<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; gap: 20px; padding: 15px; border-bottom: 1px solid #ddd;">
        <button onclick="showTab('my-tasks')" id="tab-my-tasks" class="btn btn-secondary" style="background: var(--primary-blue); color: white;">
            My Tasks (<?php echo count($myTasks); ?>)
        </button>
        <button onclick="showTab('created-tasks')" id="tab-created-tasks" class="btn btn-secondary">
            Created by Me (<?php echo count($createdTasks); ?>)
        </button>
    </div>
</div>

<!-- My Tasks Tab -->
<div id="my-tasks-content" class="card">
    <h2 class="card-title">Tasks Assigned to Me</h2>
    
    <?php if (count($myTasks) === 0): ?>
        <p style="text-align: center; padding: 40px; color: #666;">No tasks assigned to you</p>
    <?php else: ?>
        <div style="display: grid; gap: 15px; padding: 20px;">
            <?php foreach ($myTasks as $task): ?>
                <div class="task-card" style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; background: white;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <div style="flex: 1;">
                            <h3 style="margin: 0 0 5px 0; color: #333;"><?php echo htmlspecialchars($task['title']); ?></h3>
                            <p style="margin: 0; color: #666; font-size: 14px;">Created by: <?php echo htmlspecialchars($task['created_by_name']); ?></p>
                        </div>
                        <div style="display: flex; gap: 5px;">
                            <?php
                            $priorityColors = [
                                'low' => 'badge-secondary',
                                'medium' => 'badge-warning',
                                'high' => 'badge-danger'
                            ];
                            $statusColors = [
                                'todo' => 'badge-secondary',
                                'in_progress' => 'badge-warning',
                                'done' => 'badge-success'
                            ];
                            ?>
                            <span class="badge <?php echo $priorityColors[$task['priority']]; ?>">
                                <?php echo ucfirst($task['priority']); ?>
                            </span>
                            <span class="badge <?php echo $statusColors[$task['status']]; ?>">
                                <?php echo str_replace('_', ' ', ucfirst($task['status'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if ($task['description']): ?>
                        <p style="margin: 10px 0; color: #666;"><?php echo htmlspecialchars($task['description']); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($task['due_date']): ?>
                        <p style="margin: 10px 0; font-size: 14px;">
                            <strong>Due:</strong> <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                            <?php
                            $daysUntil = (strtotime($task['due_date']) - time()) / 86400;
                            if ($daysUntil < 0 && $task['status'] !== 'done') {
                                echo '<span class="badge badge-danger">Overdue</span>';
                            } elseif ($daysUntil <= 2 && $task['status'] !== 'done') {
                                echo '<span class="badge badge-warning">Due Soon</span>';
                            }
                            ?>
                        </p>
                    <?php endif; ?>
                    
                    <div style="margin-top: 15px; display: flex; gap: 10px;">
                        <?php if ($task['status'] !== 'done'): ?>
                            <button onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'done')" class="btn btn-sm btn-success">Mark Complete</button>
                        <?php endif; ?>
                        <button onclick="viewTaskDetails(<?php echo $task['id']; ?>)" class="btn btn-sm btn-primary">View Details</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Created Tasks Tab -->
<div id="created-tasks-content" class="card" style="display: none;">
    <h2 class="card-title">Tasks I Created</h2>
    
    <?php if (count($createdTasks) === 0): ?>
        <p style="text-align: center; padding: 40px; color: #666;">You haven't created any tasks yet</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Assigned To</th>
                    <th>Due Date</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($createdTasks as $task): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($task['title']); ?></strong></td>
                    <td><?php echo htmlspecialchars($task['assigned_to_name']); ?></td>
                    <td><?php echo $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : '-'; ?></td>
                    <td>
                        <?php
                        $priorityColors = ['low' => 'badge-secondary', 'medium' => 'badge-warning', 'high' => 'badge-danger'];
                        ?>
                        <span class="badge <?php echo $priorityColors[$task['priority']]; ?>">
                            <?php echo ucfirst($task['priority']); ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $statusColors = ['todo' => 'badge-secondary', 'in_progress' => 'badge-warning', 'done' => 'badge-success'];
                        ?>
                        <span class="badge <?php echo $statusColors[$task['status']]; ?>">
                            <?php echo str_replace('_', ' ', ucfirst($task['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <button onclick="viewTaskDetails(<?php echo $task['id']; ?>)" class="btn btn-sm btn-primary">View</button>
                        <button onclick="editTask(<?php echo $task['id']; ?>)" class="btn btn-sm btn-secondary">Edit</button>
                        <button onclick="deleteTask(<?php echo $task['id']; ?>)" class="btn btn-sm btn-danger">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Create/Edit Task Modal -->
<div id="task-modal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="task-modal-title">Create Task</h3>
            <button type="button" class="modal-close" onclick="closeModal('task-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="task-form" onsubmit="saveTask(event)">
                <input type="hidden" id="task_id" name="id">
                
                <div class="form-group">
                    <label class="form-label" for="task_title">Title *</label>
                    <input type="text" id="task_title" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="task_description">Description</label>
                    <textarea id="task_description" name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label" for="assigned_to">Assign To *</label>
                        <select id="assigned_to" name="assigned_to" class="form-control" required>
                            <option value="">Select employee...</option>
                            <?php
                            $employees = $db->fetchAll(
                                "SELECT id, full_name FROM users WHERE company_id = ? AND status = 'active' ORDER BY full_name",
                                [$companyId]
                            );
                            foreach ($employees as $emp) {
                                $selected = $emp['id'] == $userId ? 'selected' : '';
                                echo '<option value="' . $emp['id'] . '" ' . $selected . '>' . htmlspecialchars($emp['full_name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="due_date">Due Date</label>
                        <input type="date" id="due_date" name="due_date" class="form-control">
                    </div>
                </div>
                
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label" for="priority">Priority</label>
                        <select id="priority" name="priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="todo" selected>To Do</option>
                            <option value="in_progress">In Progress</option>
                            <option value="done">Done</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('task-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function showTab(tabName) {
        // Hide all tabs
        document.getElementById('my-tasks-content').style.display = 'none';
        document.getElementById('created-tasks-content').style.display = 'none';
        
        // Reset button styles
        document.getElementById('tab-my-tasks').style.background = '';
        document.getElementById('tab-my-tasks').style.color = '';
        document.getElementById('tab-created-tasks').style.background = '';
        document.getElementById('tab-created-tasks').style.color = '';
        
        // Show selected tab
        if (tabName === 'my-tasks') {
            document.getElementById('my-tasks-content').style.display = 'block';
            document.getElementById('tab-my-tasks').style.background = 'var(--primary-blue)';
            document.getElementById('tab-my-tasks').style.color = 'white';
        } else {
            document.getElementById('created-tasks-content').style.display = 'block';
            document.getElementById('tab-created-tasks').style.background = 'var(--primary-blue)';
            document.getElementById('tab-created-tasks').style.color = 'white';
        }
    }
    
    function openCreateTaskModal() {
        document.getElementById('task-form').reset();
        document.getElementById('task_id').value = '';
        document.getElementById('task-modal-title').textContent = 'Create Task';
        openModal('task-modal');
    }
    
    function saveTask(event) {
        event.preventDefault();
        const formData = new FormData(event.target);
        const data = Object.fromEntries(formData);
        const id = data.id;
        delete data.id;
        
        const action = id ? 'update' : 'create';
        const url = id ? `/officepro/app/api/employee/tasks.php?action=${action}&id=${id}` : `/officepro/app/api/employee/tasks.php?action=${action}`;
        
        ajaxRequest(url, 'POST', data, (response) => {
            if (response.success) {
                showMessage('success', 'Task saved successfully!');
                closeModal('task-modal');
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage('error', response.message || 'Failed to save task');
            }
        });
    }
    
    function updateTaskStatus(id, status) {
        ajaxRequest(`/officepro/app/api/employee/tasks.php?action=update_status&id=${id}`, 'POST', { status }, (response) => {
            if (response.success) {
                showMessage('success', 'Task updated!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage('error', response.message || 'Failed to update task');
            }
        });
    }
    
    function viewTaskDetails(id) {
        showMessage('info', 'Task details feature coming soon!');
    }
    
    function editTask(id) {
        showMessage('info', 'Edit task feature coming soon!');
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
            '✓'
        );
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


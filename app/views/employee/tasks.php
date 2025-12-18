<?php
/**
 * Employee Task Management Page
 */

$pageTitle = 'My Tasks';
include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../../helpers/Database.php';
require_once __DIR__ . '/../../helpers/Tenant.php';

$companyId = Tenant::getCurrentCompanyId();
$userId = $currentUser['id'];
$db = Database::getInstance();

// Get all tasks assigned to user
$allTasks = $db->fetchAll(
    "SELECT t.*, u.full_name as created_by_name 
    FROM tasks t 
    JOIN users u ON t.created_by = u.id 
    WHERE t.company_id = ? AND t.assigned_to = ? 
    ORDER BY t.created_at DESC",
    [$companyId, $userId]
);

// Filter tasks by status
$myTasks = array_filter($allTasks, function($task) {
    return $task['status'] === 'todo';
});

$processingTasks = array_filter($allTasks, function($task) {
    return $task['status'] === 'in_progress';
});

$completedTasks = array_filter($allTasks, function($task) {
    return $task['status'] === 'done';
});
?>

<div style="margin-bottom: 20px;">
    <h1><i class="fas fa-tasks"></i> My Tasks</h1>
</div>

<!-- Tabs -->
<div class="card" style="margin-bottom: 20px;">
    <div style="display: flex; gap: 20px; padding: 15px; border-bottom: 1px solid #ddd;">
        <button onclick="showTab('my-tasks')" id="tab-my-tasks" class="btn btn-secondary custom-btn-secondary" style="background: var(--primary-blue); color: white;">
            My Tasks (<?php echo count($myTasks); ?>)
        </button>
        <button onclick="showTab('processing')" id="tab-processing" class="btn btn-secondary custom-btn-secondary">
            Processing (<?php echo count($processingTasks); ?>)
        </button>
        <button onclick="showTab('complete')" id="tab-complete" class="btn btn-secondary custom-btn-secondary">
            Complete (<?php echo count($completedTasks); ?>)
        </button>
    </div>
</div>

<!-- My Tasks Tab (Pending/Todo) -->
<div id="my-tasks-content" class="card">
    <h2 class="card-title">My Tasks</h2>
    
    <?php if (count($myTasks) === 0): ?>
        <p style="text-align: center; padding: 40px; color: #666;">No pending tasks</p>
    <?php else: ?>
        <div style="display: grid; gap: 15px; padding: 20px;">
            <?php foreach ($myTasks as $task): ?>
                <div class="task-card" style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; background: white;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <div style="flex: 1;">
                            <h3 style="margin: 0 0 5px 0; color: #333;"><?php echo htmlspecialchars($task['title']); ?></h3>
                            <p style="margin: 0; color: #666; font-size: 14px;">Created by: <?php echo htmlspecialchars($task['created_by_name']); ?></p>
                        </div>
                        <div>
                            <?php
                            $statusBadges = [
                                'todo' => ['class' => 'badge-secondary', 'text' => 'Pending'],
                                'in_progress' => ['class' => 'badge-warning', 'text' => 'Processing'],
                                'done' => ['class' => 'badge-success', 'text' => 'Completed']
                            ];
                            $currentStatus = $task['status'] ?? 'todo';
                            $badge = $statusBadges[$currentStatus] ?? $statusBadges['todo'];
                            ?>
                            <span class="badge <?php echo $badge['class']; ?>"><?php echo $badge['text']; ?></span>
                        </div>
                    </div>
                    
                    <?php if ($task['description']): ?>
                        <p style="margin: 10px 0; color: #666;"><?php echo htmlspecialchars($task['description']); ?></p>
                    <?php endif; ?>
                    
                    <div style="margin-top: 15px; display: flex; gap: 10px; align-items: center;">
                        <label style="font-weight: 600; margin-right: 10px;">Status:</label>
                        <select onchange="changeTaskStatus(<?php echo $task['id']; ?>, this.value)" class="form-control" style="width: auto; display: inline-block;">
                            <option value="todo" <?php echo ($currentStatus === 'todo') ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo ($currentStatus === 'in_progress') ? 'selected' : ''; ?>>Processing</option>
                            <option value="done" <?php echo ($currentStatus === 'done') ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Processing Tab -->
<div id="processing-content" class="card" style="display: none;">
    <h2 class="card-title">Processing Tasks</h2>
    
    <?php if (count($processingTasks) === 0): ?>
        <p style="text-align: center; padding: 40px; color: #666;">No tasks in processing</p>
    <?php else: ?>
        <div style="display: grid; gap: 15px; padding: 20px;">
            <?php foreach ($processingTasks as $task): ?>
                <div class="task-card" style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; background: white;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <div style="flex: 1;">
                            <h3 style="margin: 0 0 5px 0; color: #333;"><?php echo htmlspecialchars($task['title']); ?></h3>
                            <p style="margin: 0; color: #666; font-size: 14px;">Created by: <?php echo htmlspecialchars($task['created_by_name']); ?></p>
                        </div>
                        <div>
                            <span class="badge badge-warning">Processing</span>
                        </div>
                    </div>
                    
                    <?php if ($task['description']): ?>
                        <p style="margin: 10px 0; color: #666;"><?php echo htmlspecialchars($task['description']); ?></p>
                    <?php endif; ?>
                    
                    <div style="margin-top: 15px; display: flex; gap: 10px; align-items: center;">
                        <label style="font-weight: 600; margin-right: 10px;">Status:</label>
                        <select onchange="changeTaskStatus(<?php echo $task['id']; ?>, this.value)" class="form-control" style="width: auto; display: inline-block;">
                            <option value="todo">Pending</option>
                            <option value="in_progress" selected>Processing</option>
                            <option value="done">Completed</option>
                        </select>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Complete Tab -->
<div id="complete-content" class="card" style="display: none;">
    <h2 class="card-title">Completed Tasks</h2>
    
    <?php if (count($completedTasks) === 0): ?>
        <p style="text-align: center; padding: 40px; color: #666;">No completed tasks</p>
    <?php else: ?>
        <div style="display: grid; gap: 15px; padding: 20px;">
            <?php foreach ($completedTasks as $task): ?>
                <div class="task-card" style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; background: white; opacity: 0.8;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <div style="flex: 1;">
                            <h3 style="margin: 0 0 5px 0; color: #333; text-decoration: line-through;"><?php echo htmlspecialchars($task['title']); ?></h3>
                            <p style="margin: 0; color: #666; font-size: 14px;">Created by: <?php echo htmlspecialchars($task['created_by_name']); ?></p>
                        </div>
                        <div>
                            <span class="badge badge-success">Completed</span>
                        </div>
                    </div>
                    
                    <?php if ($task['description']): ?>
                        <p style="margin: 10px 0; color: #666;"><?php echo htmlspecialchars($task['description']); ?></p>
                    <?php endif; ?>
                    
                    <div style="margin-top: 15px; display: flex; gap: 10px; align-items: center;">
                        <label style="font-weight: 600; margin-right: 10px;">Status:</label>
                        <select onchange="changeTaskStatus(<?php echo $task['id']; ?>, this.value)" class="form-control" style="width: auto; display: inline-block;">
                            <option value="todo">Pending</option>
                            <option value="in_progress">Processing</option>
                            <option value="done" selected>Completed</option>
                        </select>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    function showTab(tabName) {
        // Hide all tabs
        document.getElementById('my-tasks-content').style.display = 'none';
        document.getElementById('processing-content').style.display = 'none';
        document.getElementById('complete-content').style.display = 'none';
        
        // Reset button styles
        document.getElementById('tab-my-tasks').style.background = '';
        document.getElementById('tab-my-tasks').style.color = '';
        document.getElementById('tab-processing').style.background = '';
        document.getElementById('tab-processing').style.color = '';
        document.getElementById('tab-complete').style.background = '';
        document.getElementById('tab-complete').style.color = '';
        
        // Show selected tab
        if (tabName === 'my-tasks') {
            document.getElementById('my-tasks-content').style.display = 'block';
            document.getElementById('tab-my-tasks').style.background = 'var(--primary-blue)';
            document.getElementById('tab-my-tasks').style.color = 'white';
        } else if (tabName === 'processing') {
            document.getElementById('processing-content').style.display = 'block';
            document.getElementById('tab-processing').style.background = 'var(--primary-blue)';
            document.getElementById('tab-processing').style.color = 'white';
        } else if (tabName === 'complete') {
            document.getElementById('complete-content').style.display = 'block';
            document.getElementById('tab-complete').style.background = 'var(--primary-blue)';
            document.getElementById('tab-complete').style.color = 'white';
        }
    }
    
    function changeTaskStatus(id, status) {
        ajaxRequest(`/officepro/app/api/employee/tasks.php?action=update_status&id=${id}`, 'POST', { status }, (response) => {
            if (response.success) {
                showMessage('success', 'Task status updated!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage('error', response.message || 'Failed to update task status');
            }
        }, (error) => {
            console.error('Task status update failed:', error);
            showMessage('error', 'An error occurred while updating task status. Please try again.');
        });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

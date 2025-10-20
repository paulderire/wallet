<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }

include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

$user_id = $_SESSION['user_id'];

// Fetch all tasks across all projects
$all_tasks = [];
try {
  $stmt = $conn->prepare("
    SELECT pt.*, p.project_name, p.project_code, e.full_name as assigned_employee_name
    FROM project_tasks pt
    JOIN projects p ON pt.project_id = p.id
    LEFT JOIN employees e ON pt.assigned_to_employee = e.id
    WHERE p.user_id = ?
    ORDER BY 
      CASE pt.status
        WHEN 'in_progress' THEN 1
        WHEN 'review' THEN 2
        WHEN 'todo' THEN 3
        WHEN 'blocked' THEN 4
        WHEN 'completed' THEN 5
      END,
      pt.priority DESC,
      pt.due_date ASC
  ");
  $stmt->execute([$user_id]);
  $all_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Group tasks by status
$tasks_by_status = [
  'todo' => [],
  'in_progress' => [],
  'review' => [],
  'blocked' => [],
  'completed' => []
];

foreach ($all_tasks as $task) {
  $status = $task['status'] ?? 'todo';
  if (isset($tasks_by_status[$status])) {
    $tasks_by_status[$status][] = $task;
  }
}

// Count tasks
$total_tasks = count($all_tasks);
$completed_tasks = count($tasks_by_status['completed']);
$completion_rate = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;
?>

<style>
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:32px}
.stat-card{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;padding:20px;text-align:center}
.stat-card.primary{border-top:4px solid #667eea}
.stat-card.success{border-top:4px solid #2ed573}
.stat-card.warning{border-top:4px solid #f59e0b}
.stat-card.danger{border-top:4px solid #ef4444}
.stat-value{font-size:2.5rem;font-weight:700;color:var(--card-text);margin:8px 0}
.stat-label{font-size:0.85rem;color:var(--muted)}
.tasks-table{width:100%;background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;overflow:hidden}
.tasks-table th{background:rgba(139,92,246,0.1);padding:16px;text-align:left;font-weight:600;color:var(--card-text);border-bottom:1px solid var(--border-weak)}
.tasks-table td{padding:16px;border-bottom:1px solid var(--border-weak)}
.tasks-table tr:hover{background:rgba(139,92,246,0.05)}
.status-badge{padding:6px 12px;border-radius:20px;font-size:0.75rem;font-weight:600;text-transform:uppercase;display:inline-block}
.status-badge.todo{background:rgba(107,114,128,0.15);color:#6b7280}
.status-badge.in_progress{background:rgba(59,130,246,0.15);color:#3b82f6}
.status-badge.review{background:rgba(245,158,11,0.15);color:#f59e0b}
.status-badge.blocked{background:rgba(239,68,68,0.15);color:#ef4444}
.status-badge.completed{background:rgba(46,213,115,0.15);color:#2ed573}
.priority-badge{padding:4px 10px;border-radius:12px;font-size:0.7rem;font-weight:600;text-transform:uppercase}
.priority-badge.urgent{background:rgba(239,68,68,0.2);color:#ef4444}
.priority-badge.high{background:rgba(245,158,11,0.2);color:#f59e0b}
.priority-badge.medium{background:rgba(59,130,246,0.2);color:#3b82f6}
.priority-badge.low{background:rgba(107,114,128,0.2);color:#6b7280}
</style>

<div style="margin-bottom:24px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div>
      <h2>📋 All Project Tasks</h2>
      <p style="color:var(--muted)">Complete overview of all tasks across your projects</p>
    </div>
    <a href="/MY CASH/pages/projects.php" class="button ghost">← Back to Projects</a>
  </div>
</div>

<!-- Statistics -->
<div class="stats-grid">
  <div class="stat-card primary">
    <div class="stat-label">Total Tasks</div>
    <div class="stat-value"><?= $total_tasks ?></div>
  </div>
  <div class="stat-card success">
    <div class="stat-label">Completed</div>
    <div class="stat-value"><?= count($tasks_by_status['completed']) ?></div>
  </div>
  <div class="stat-card warning">
    <div class="stat-label">In Progress</div>
    <div class="stat-value"><?= count($tasks_by_status['in_progress']) + count($tasks_by_status['review']) ?></div>
  </div>
  <div class="stat-card danger">
    <div class="stat-label">Completion Rate</div>
    <div class="stat-value"><?= $completion_rate ?>%</div>
  </div>
</div>

<!-- Tasks Table -->
<?php if (empty($all_tasks)): ?>
  <div class="card" style="text-align:center;padding:48px 24px">
    <div style="font-size:3rem;margin-bottom:16px;opacity:0.3">📝</div>
    <h3>No tasks found</h3>
    <p style="color:var(--muted)">Create projects and add tasks to see them here</p>
  </div>
<?php else: ?>
  <table class="tasks-table">
    <thead>
      <tr>
        <th>Task</th>
        <th>Project</th>
        <th>Assigned To</th>
        <th>Status</th>
        <th>Priority</th>
        <th>Due Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($all_tasks as $task): ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($task['task_name']) ?></strong>
            <?php if ($task['description']): ?>
              <div style="font-size:0.85rem;color:var(--muted);margin-top:4px">
                <?= htmlspecialchars(substr($task['description'], 0, 80)) ?><?= strlen($task['description']) > 80 ? '...' : '' ?>
              </div>
            <?php endif; ?>
          </td>
          <td>
            <strong><?= htmlspecialchars($task['project_name']) ?></strong>
            <div style="font-size:0.75rem;color:var(--muted);font-family:monospace">
              <?= htmlspecialchars($task['project_code']) ?>
            </div>
          </td>
          <td>
            <?php if ($task['assigned_employee_name']): ?>
              <span style="padding:4px 8px;background:rgba(139,92,246,0.1);border-radius:6px;font-size:0.85rem">
                👤 <?= htmlspecialchars($task['assigned_employee_name']) ?>
              </span>
            <?php else: ?>
              <span style="color:var(--muted);font-style:italic">Unassigned</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="status-badge <?= $task['status'] ?>">
              <?= str_replace('_', ' ', $task['status']) ?>
            </span>
          </td>
          <td>
            <span class="priority-badge <?= $task['priority'] ?>">
              <?= $task['priority'] ?>
            </span>
          </td>
          <td>
            <?php if ($task['due_date']): ?>
              <?php 
                $due = strtotime($task['due_date']);
                $now = time();
                $is_overdue = $due < $now && $task['status'] !== 'completed';
              ?>
              <span style="<?= $is_overdue ? 'color:#ef4444;font-weight:600' : '' ?>">
                <?= date('M d, Y', $due) ?>
                <?php if ($is_overdue): ?>
                  ⚠️
                <?php endif; ?>
              </span>
            <?php else: ?>
              <span style="color:var(--muted);font-style:italic">—</span>
            <?php endif; ?>
          </td>
          <td>
            <a href="/MY CASH/pages/view_project.php?id=<?= $task['project_id'] ?>" class="button secondary small">View Project</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

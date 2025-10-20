<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }

include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

$user_id = $_SESSION['user_id'];

// Fetch project statistics
$stats = [
  'total_projects' => 0,
  'active_projects' => 0,
  'completed_projects' => 0,
  'total_tasks' => 0,
  'completed_tasks' => 0,
  'overdue_tasks' => 0,
  'total_budget' => 0,
  'total_spent' => 0
];

try {
  // Project counts
  $stmt = $conn->prepare("
    SELECT 
      COUNT(*) as total,
      SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
      SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
      SUM(budget) as total_budget,
      SUM(spent_amount) as total_spent
    FROM projects 
    WHERE user_id = ?
  ");
  $stmt->execute([$user_id]);
  $project_stats = $stmt->fetch(PDO::FETCH_ASSOC);
  
  $stats['total_projects'] = $project_stats['total'] ?? 0;
  $stats['active_projects'] = $project_stats['active'] ?? 0;
  $stats['completed_projects'] = $project_stats['completed'] ?? 0;
  $stats['total_budget'] = $project_stats['total_budget'] ?? 0;
  $stats['total_spent'] = $project_stats['total_spent'] ?? 0;
  
  // Task counts
  $stmt = $conn->prepare("
    SELECT 
      COUNT(*) as total,
      SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
      SUM(CASE WHEN status != 'completed' AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue
    FROM project_tasks pt
    JOIN projects p ON pt.project_id = p.id
    WHERE p.user_id = ?
  ");
  $stmt->execute([$user_id]);
  $task_stats = $stmt->fetch(PDO::FETCH_ASSOC);
  
  $stats['total_tasks'] = $task_stats['total'] ?? 0;
  $stats['completed_tasks'] = $task_stats['completed'] ?? 0;
  $stats['overdue_tasks'] = $task_stats['overdue'] ?? 0;
  
} catch (Exception $e) {}

// Calculate percentages
$project_completion = $stats['total_projects'] > 0 ? round(($stats['completed_projects'] / $stats['total_projects']) * 100) : 0;
$task_completion = $stats['total_tasks'] > 0 ? round(($stats['completed_tasks'] / $stats['total_tasks']) * 100) : 0;
$budget_used = $stats['total_budget'] > 0 ? round(($stats['total_spent'] / $stats['total_budget']) * 100) : 0;

// Fetch project performance
$project_performance = [];
try {
  $stmt = $conn->prepare("
    SELECT 
      p.id,
      p.project_name,
      p.project_code,
      p.status,
      p.priority,
      p.progress_percentage,
      p.budget,
      p.spent_amount,
      p.deadline,
      COUNT(pt.id) as total_tasks,
      SUM(CASE WHEN pt.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
    FROM projects p
    LEFT JOIN project_tasks pt ON p.id = pt.project_id
    WHERE p.user_id = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 10
  ");
  $stmt->execute([$user_id]);
  $project_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Team member performance
$team_performance = [];
try {
  $stmt = $conn->prepare("
    SELECT 
      e.id,
      e.full_name,
      e.position,
      COUNT(pt.id) as total_tasks,
      SUM(CASE WHEN pt.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
      SUM(CASE WHEN pt.status = 'in_progress' THEN 1 ELSE 0 END) as active_tasks
    FROM employees e
    LEFT JOIN project_tasks pt ON e.id = pt.assigned_to_employee
    LEFT JOIN projects p ON pt.project_id = p.id
    WHERE p.user_id = ? OR pt.id IS NULL
    GROUP BY e.id
    HAVING total_tasks > 0
    ORDER BY completed_tasks DESC
    LIMIT 10
  ");
  $stmt->execute([$user_id]);
  $team_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

?>

<style>
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:32px}
.stat-card{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:16px;padding:24px;position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px}
.stat-card.primary::before{background:linear-gradient(90deg,#667eea,#764ba2)}
.stat-card.success::before{background:linear-gradient(90deg,#2ed573,#26d07c)}
.stat-card.warning::before{background:linear-gradient(90deg,#f59e0b,#ff6b6b)}
.stat-card.danger::before{background:linear-gradient(90deg,#ef4444,#dc2626)}
.stat-card.info::before{background:linear-gradient(90deg,#3b82f6,#2563eb)}
.stat-icon{font-size:2rem;margin-bottom:12px}
.stat-value{font-size:2.5rem;font-weight:700;color:var(--card-text);margin:8px 0}
.stat-label{font-size:0.9rem;color:var(--muted);margin-bottom:8px}
.stat-subtitle{font-size:0.75rem;color:var(--muted)}
.report-table{width:100%;background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;overflow:hidden;margin-bottom:24px}
.report-table th{background:rgba(139,92,246,0.1);padding:16px;text-align:left;font-weight:600;color:var(--card-text);border-bottom:1px solid var(--border-weak)}
.report-table td{padding:16px;border-bottom:1px solid var(--border-weak)}
.report-table tr:hover{background:rgba(139,92,246,0.05)}
.progress-bar{width:100%;height:8px;background:rgba(var(--card-text-rgb),0.1);border-radius:4px;overflow:hidden;margin-top:8px}
.progress-fill{height:100%;background:linear-gradient(90deg,#667eea,#764ba2);transition:width 0.3s}
</style>

<div style="margin-bottom:32px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div>
      <h2>📊 Project Reports & Analytics</h2>
      <p style="color:var(--muted)">Comprehensive overview of your project performance</p>
    </div>
    <a href="/MY CASH/pages/projects.php" class="button ghost">← Back to Projects</a>
  </div>
</div>

<!-- Key Statistics -->
<div class="stats-grid">
  <div class="stat-card primary">
    <div class="stat-icon">📁</div>
    <div class="stat-label">Total Projects</div>
    <div class="stat-value"><?= $stats['total_projects'] ?></div>
    <div class="stat-subtitle"><?= $stats['active_projects'] ?> active, <?= $stats['completed_projects'] ?> completed</div>
    <div class="progress-bar">
      <div class="progress-fill" style="width:<?= $project_completion ?>%"></div>
    </div>
  </div>
  
  <div class="stat-card success">
    <div class="stat-icon">✅</div>
    <div class="stat-label">Task Completion</div>
    <div class="stat-value"><?= $task_completion ?>%</div>
    <div class="stat-subtitle"><?= $stats['completed_tasks'] ?> of <?= $stats['total_tasks'] ?> tasks done</div>
    <div class="progress-bar">
      <div class="progress-fill" style="width:<?= $task_completion ?>%"></div>
    </div>
  </div>
  
  <div class="stat-card warning">
    <div class="stat-icon">⚠️</div>
    <div class="stat-label">Overdue Tasks</div>
    <div class="stat-value"><?= $stats['overdue_tasks'] ?></div>
    <div class="stat-subtitle">Tasks past their due date</div>
  </div>
  
  <div class="stat-card info">
    <div class="stat-icon">💰</div>
    <div class="stat-label">Budget Usage</div>
    <div class="stat-value"><?= $budget_used ?>%</div>
    <div class="stat-subtitle">RWF <?= number_format($stats['total_spent']) ?> of <?= number_format($stats['total_budget']) ?></div>
    <div class="progress-bar">
      <div class="progress-fill" style="width:<?= min($budget_used, 100) ?>%"></div>
    </div>
  </div>
</div>

<!-- Project Performance -->
<div class="card" style="margin-bottom:24px">
  <h3 style="padding:24px 24px 0;margin:0">📈 Project Performance</h3>
  <?php if (empty($project_performance)): ?>
    <div style="padding:48px 24px;text-align:center">
      <div style="font-size:3rem;margin-bottom:16px;opacity:0.3">📊</div>
      <p style="color:var(--muted)">No projects to analyze yet</p>
    </div>
  <?php else: ?>
    <table class="report-table" style="border:none">
      <thead>
        <tr>
          <th>Project</th>
          <th>Status</th>
          <th>Priority</th>
          <th>Progress</th>
          <th>Tasks</th>
          <th>Budget</th>
          <th>Deadline</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($project_performance as $project): 
          $task_rate = $project['total_tasks'] > 0 ? round(($project['completed_tasks'] / $project['total_tasks']) * 100) : 0;
          $budget_rate = $project['budget'] > 0 ? round(($project['spent_amount'] / $project['budget']) * 100) : 0;
          $is_overdue = $project['deadline'] && strtotime($project['deadline']) < time() && $project['status'] !== 'completed';
        ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($project['project_name']) ?></strong>
              <div style="font-size:0.75rem;color:var(--muted);font-family:monospace;margin-top:2px">
                <?= htmlspecialchars($project['project_code']) ?>
              </div>
            </td>
            <td>
              <span style="padding:4px 10px;background:rgba(139,92,246,0.15);color:#8b5cf6;border-radius:12px;font-size:0.75rem;font-weight:600;text-transform:capitalize">
                <?= str_replace('_', ' ', $project['status']) ?>
              </span>
            </td>
            <td>
              <span style="padding:4px 10px;background:rgba(245,158,11,0.15);color:#f59e0b;border-radius:12px;font-size:0.75rem;font-weight:600;text-transform:capitalize">
                <?= $project['priority'] ?>
              </span>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div style="flex:1">
                  <div class="progress-bar">
                    <div class="progress-fill" style="width:<?= $project['progress_percentage'] ?>%"></div>
                  </div>
                </div>
                <span style="font-weight:600;font-size:0.85rem"><?= $project['progress_percentage'] ?>%</span>
              </div>
            </td>
            <td>
              <strong><?= $project['completed_tasks'] ?></strong> / <?= $project['total_tasks'] ?>
              <div style="font-size:0.75rem;color:var(--muted)"><?= $task_rate ?>% done</div>
            </td>
            <td>
              RWF <?= number_format($project['spent_amount']) ?>
              <div style="font-size:0.75rem;color:var(--muted)">of <?= number_format($project['budget']) ?></div>
              <div style="font-size:0.75rem;color:<?= $budget_rate > 100 ? '#ef4444' : '#2ed573' ?>">
                <?= $budget_rate ?>%
              </div>
            </td>
            <td>
              <?php if ($project['deadline']): ?>
                <span style="<?= $is_overdue ? 'color:#ef4444;font-weight:600' : '' ?>">
                  <?= date('M d, Y', strtotime($project['deadline'])) ?>
                  <?php if ($is_overdue): ?>
                    <div style="font-size:0.75rem">⚠️ Overdue</div>
                  <?php endif; ?>
                </span>
              <?php else: ?>
                <span style="color:var(--muted);font-style:italic">No deadline</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Team Performance -->
<?php if (!empty($team_performance)): ?>
<div class="card">
  <h3 style="padding:24px 24px 0;margin:0">👥 Team Member Performance</h3>
  <table class="report-table" style="border:none">
    <thead>
      <tr>
        <th>Team Member</th>
        <th>Position</th>
        <th>Total Tasks</th>
        <th>Completed</th>
        <th>Active</th>
        <th>Completion Rate</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($team_performance as $member): 
        $completion_rate = $member['total_tasks'] > 0 ? round(($member['completed_tasks'] / $member['total_tasks']) * 100) : 0;
      ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($member['full_name']) ?></strong>
          </td>
          <td><?= htmlspecialchars($member['position'] ?? 'N/A') ?></td>
          <td><strong><?= $member['total_tasks'] ?></strong></td>
          <td>
            <span style="color:#2ed573;font-weight:600"><?= $member['completed_tasks'] ?></span>
          </td>
          <td>
            <span style="color:#3b82f6;font-weight:600"><?= $member['active_tasks'] ?></span>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="flex:1">
                <div class="progress-bar">
                  <div class="progress-fill" style="width:<?= $completion_rate ?>%"></div>
                </div>
              </div>
              <span style="font-weight:600;font-size:0.85rem"><?= $completion_rate ?>%</span>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }

include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

$user_id = $_SESSION['user_id'];

// Initialize tables if they don't exist
$schema_file = __DIR__ . '/../db/projects_schema.sql';
if (file_exists($schema_file)) {
  try {
    $sql = file_get_contents($schema_file);
    $conn->exec($sql);
  } catch (Exception $e) {}
}

// Fetch project statistics
$stats = [
  'total' => 0,
  'active' => 0,
  'completed' => 0,
  'on_hold' => 0,
  'total_budget' => 0,
  'total_spent' => 0
];

try {
  $stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status='on_hold' THEN 1 ELSE 0 END) as on_hold,
    SUM(budget) as total_budget,
    SUM(spent_amount) as total_spent
    FROM projects WHERE user_id = ? AND archived = 0");
  $stmt->execute([$user_id]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($result) {
    $stats = $result;
  }
} catch (Exception $e) {}

// Fetch recent projects
$projects = [];
try {
  $stmt = $conn->prepare("SELECT p.*, 
    (SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id) as task_count,
    (SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id AND status='completed') as completed_tasks,
    (SELECT COUNT(*) FROM project_members WHERE project_id = p.id) as member_count
    FROM projects p 
    WHERE p.user_id = ? AND p.archived = 0
    ORDER BY p.created_at DESC
    LIMIT 20");
  $stmt->execute([$user_id]);
  $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<style>
.projects-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 40px;
  border-radius: 20px;
  margin-bottom: 32px;
  color: white;
  position: relative;
  overflow: hidden;
}
.projects-header::before {
  content: '🎯';
  position: absolute;
  right: 40px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 8rem;
  opacity: 0.1;
}
.projects-header h1 {
  font-size: 2.5rem;
  font-weight: 800;
  margin: 0 0 12px 0;
  color: white;
}
.projects-header p {
  font-size: 1.1rem;
  opacity: 0.9;
  margin: 0;
}
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}
.stat-card {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 16px;
  padding: 24px;
  position: relative;
  overflow: hidden;
  transition: all 0.3s;
}
.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
}
.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 30px rgba(var(--card-text-rgb), 0.12);
}
.stat-card.total::before { background: linear-gradient(90deg, #667eea, #764ba2); }
.stat-card.active::before { background: linear-gradient(90deg, #2ed573, #11998e); }
.stat-card.completed::before { background: linear-gradient(90deg, #3498db, #2980b9); }
.stat-card.budget::before { background: linear-gradient(90deg, #ffa500, #ff6348); }
.stat-icon {
  font-size: 2rem;
  opacity: 0.15;
  position: absolute;
  right: 16px;
  top: 16px;
}
.stat-label {
  font-size: 0.85rem;
  color: var(--muted);
  text-transform: uppercase;
  font-weight: 600;
  margin-bottom: 8px;
}
.stat-value {
  font-size: 2rem;
  font-weight: 800;
  color: var(--card-text);
  margin-bottom: 4px;
}
.stat-sub {
  font-size: 0.85rem;
  color: var(--muted);
}
.action-buttons {
  display: flex;
  gap: 12px;
  margin-bottom: 32px;
  flex-wrap: wrap;
}
.button {
  padding: 12px 28px;
  border: none;
  border-radius: 12px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  text-decoration: none;
  display: inline-block;
}
.button.primary {
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: white;
}
.button.primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}
.button.ghost {
  background: transparent;
  color: var(--card-text);
  border: 2px solid var(--border-weak);
}
.button.ghost:hover {
  border-color: #667eea;
  color: #667eea;
}
.projects-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 24px;
  margin-bottom: 32px;
}
.project-card {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 16px;
  padding: 24px;
  transition: all 0.3s;
  position: relative;
  overflow: hidden;
}
.project-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
}
.project-card.priority-urgent::before { background: #ef4444; }
.project-card.priority-high::before { background: #f59e0b; }
.project-card.priority-medium::before { background: #3b82f6; }
.project-card.priority-low::before { background: #6b7280; }
.project-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 16px 40px rgba(var(--card-text-rgb), 0.15);
}
.project-header {
  display: flex;
  justify-content: space-between;
  align-items: start;
  margin-bottom: 16px;
}
.project-title {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--card-text);
  margin: 0 0 8px 0;
}
.project-code {
  font-size: 0.85rem;
  color: var(--muted);
  font-family: monospace;
}
.status-badge {
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  white-space: nowrap;
}
.status-badge.planning { background: rgba(107, 114, 128, 0.15); color: #6b7280; }
.status-badge.active { background: rgba(46, 213, 115, 0.15); color: #2ed573; }
.status-badge.on_hold { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.status-badge.completed { background: rgba(52, 152, 219, 0.15); color: #3498db; }
.status-badge.cancelled { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.project-description {
  color: var(--muted);
  font-size: 0.9rem;
  margin-bottom: 16px;
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.project-meta {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  margin-bottom: 16px;
}
.meta-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.85rem;
  color: var(--card-text);
}
.meta-icon {
  font-size: 1.1rem;
}
.progress-bar {
  background: rgba(var(--card-text-rgb), 0.08);
  border-radius: 8px;
  height: 8px;
  overflow: hidden;
  margin-bottom: 8px;
}
.progress-fill {
  background: linear-gradient(90deg, #667eea, #764ba2);
  height: 100%;
  transition: width 0.3s;
}
.progress-text {
  font-size: 0.8rem;
  color: var(--muted);
  text-align: center;
}
.project-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--border-weak);
}
.team-avatars {
  display: flex;
  align-items: center;
}
.avatar-circle {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea, #764ba2);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 0.75rem;
  border: 2px solid var(--card-bg);
  margin-left: -8px;
}
.avatar-circle:first-child {
  margin-left: 0;
}
.project-actions {
  display: flex;
  gap: 8px;
}
.icon-btn {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: rgba(var(--card-text-rgb), 0.05);
  border: 1px solid var(--border-weak);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
  color: var(--card-text);
}
.icon-btn:hover {
  background: rgba(102, 126, 234, 0.1);
  border-color: #667eea;
  color: #667eea;
}
.empty-state {
  text-align: center;
  padding: 80px 20px;
  color: var(--muted);
}
.empty-state-icon {
  font-size: 5rem;
  margin-bottom: 20px;
  opacity: 0.3;
}
.empty-state h3 {
  font-size: 1.5rem;
  color: var(--card-text);
  margin-bottom: 12px;
}
</style>

<div class="projects-header">
  <h1>🎯 Project Management</h1>
  <p>Organize, track, and deliver your projects successfully</p>
</div>

<div class="stats-grid">
  <div class="stat-card total">
    <div class="stat-icon">📊</div>
    <div class="stat-label">Total Projects</div>
    <div class="stat-value"><?= $stats['total'] ?></div>
    <div class="stat-sub">All active projects</div>
  </div>
  
  <div class="stat-card active">
    <div class="stat-icon">🚀</div>
    <div class="stat-label">Active</div>
    <div class="stat-value"><?= $stats['active'] ?></div>
    <div class="stat-sub">In progress</div>
  </div>
  
  <div class="stat-card completed">
    <div class="stat-icon">✅</div>
    <div class="stat-label">Completed</div>
    <div class="stat-value"><?= $stats['completed'] ?></div>
    <div class="stat-sub">Successfully delivered</div>
  </div>
  
  <div class="stat-card budget">
    <div class="stat-icon">💰</div>
    <div class="stat-label">Total Budget</div>
    <div class="stat-value">RWF <?= number_format($stats['total_budget'] ?? 0) ?></div>
    <div class="stat-sub">Spent: RWF <?= number_format($stats['total_spent'] ?? 0) ?></div>
  </div>
</div>

<div class="action-buttons">
  <a href="/MY CASH/pages/add_project.php" class="button primary">+ New Project</a>
  <a href="/MY CASH/pages/project_tasks.php" class="button ghost">📋 All Tasks</a>
  <a href="/MY CASH/pages/project_timeline.php" class="button ghost">📅 Timeline</a>
  <a href="/MY CASH/pages/project_reports.php" class="button ghost">📊 Reports</a>
</div>

<?php if (empty($projects)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">🎯</div>
    <h3>No Projects Yet</h3>
    <p>Start by creating your first project to organize your work</p>
    <a href="/MY CASH/pages/add_project.php" class="button primary" style="margin-top:20px;">+ Create First Project</a>
  </div>
<?php else: ?>
  <div class="projects-grid">
    <?php foreach ($projects as $project): 
      $progress = intval($project['progress_percentage']);
      $task_progress = $project['task_count'] > 0 ? round(($project['completed_tasks'] / $project['task_count']) * 100) : 0;
      $deadline_class = '';
      if ($project['deadline']) {
        $days_left = (strtotime($project['deadline']) - time()) / 86400;
        if ($days_left < 0) $deadline_class = 'overdue';
        elseif ($days_left < 7) $deadline_class = 'urgent';
      }
    ?>
      <div class="project-card priority-<?= strtolower($project['priority']) ?>">
        <div class="project-header">
          <div>
            <h3 class="project-title"><?= htmlspecialchars($project['project_name']) ?></h3>
            <div class="project-code"><?= htmlspecialchars($project['project_code']) ?></div>
          </div>
          <span class="status-badge <?= $project['status'] ?>"><?= ucfirst($project['status']) ?></span>
        </div>
        
        <?php if ($project['description']): ?>
          <p class="project-description"><?= htmlspecialchars($project['description']) ?></p>
        <?php endif; ?>
        
        <div class="project-meta">
          <?php if ($project['deadline']): ?>
            <div class="meta-item <?= $deadline_class ?>">
              <span class="meta-icon">📅</span>
              <span><?= date('M d, Y', strtotime($project['deadline'])) ?></span>
            </div>
          <?php endif; ?>
          
          <div class="meta-item">
            <span class="meta-icon">✓</span>
            <span><?= $project['completed_tasks'] ?>/<?= $project['task_count'] ?> tasks</span>
          </div>
          
          <?php if ($project['budget'] > 0): ?>
            <div class="meta-item">
              <span class="meta-icon">💰</span>
              <span>RWF <?= number_format($project['budget']) ?></span>
            </div>
          <?php endif; ?>
          
          <div class="meta-item">
            <span class="meta-icon">👥</span>
            <span><?= $project['member_count'] ?> members</span>
          </div>
        </div>
        
        <div class="progress-bar">
          <div class="progress-fill" style="width: <?= max($progress, $task_progress) ?>%"></div>
        </div>
        <div class="progress-text"><?= max($progress, $task_progress) ?>% complete</div>
        
        <div class="project-footer">
          <div class="team-avatars">
            <div class="avatar-circle"><?= strtoupper(substr($project['project_name'], 0, 1)) ?></div>
            <?php if ($project['member_count'] > 1): ?>
              <div class="avatar-circle">+<?= $project['member_count'] - 1 ?></div>
            <?php endif; ?>
          </div>
          
          <div class="project-actions">
            <a href="/MY CASH/pages/view_project.php?id=<?= $project['id'] ?>" class="icon-btn" title="View project">👁</a>
            <a href="/MY CASH/pages/edit_project.php?id=<?= $project['id'] ?>" class="icon-btn" title="Edit project">✏</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

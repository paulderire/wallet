<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }
include __DIR__ . '/../includes/db.php';

// Check if user is admin
$is_admin = false;
try {
  $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
  $stmt->execute([$_SESSION['user_id']]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  $is_admin = !empty($user['is_admin']);
} catch (Exception $e) {}

if (!$is_admin) {
  header("Location: /MY CASH/pages/dashboard.php");
  exit;
}

include __DIR__ . '/../includes/header.php';
$user_id = $_SESSION['user_id'];

// Fetch projects
$projects = [];
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

try {
  $query = "SELECT * FROM projects WHERE user_id = ?";
  $params = [$user_id];
  
  if ($status_filter) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
  }
  
  if ($search) {
    $query .= " AND (name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
  }
  
  $query .= " ORDER BY created_at DESC";
  
  $stmt = $conn->prepare($query);
  $stmt->execute($params);
  $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $projects = [];
}
?>
<style>
  body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    font-family: 'Inter', sans-serif;
  }
  
  .page-header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    padding: 24px 32px;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    margin-bottom: 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .page-title {
    font-size: 28px;
    font-weight: 800;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 0;
  }
  
  .filters-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    margin-bottom: 24px;
  }
  
  .filter-grid {
    display: grid;
    grid-template-columns: 2fr 1fr auto;
    gap: 16px;
    align-items: end;
  }
  
  .filter-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #4a5568;
    font-size: 14px;
  }
  
  .filter-group input,
  .filter-group select {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
  }
  
  .filter-group input:focus,
  .filter-group select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }
  
  .projects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
  }
  
  .project-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s, box-shadow 0.2s;
    border-left: 4px solid;
  }
  
  .project-card.status-not-started {
    border-left-color: #718096;
  }
  
  .project-card.status-in-progress {
    border-left-color: #667eea;
  }
  
  .project-card.status-completed {
    border-left-color: #48bb78;
  }
  
  .project-card.status-on-hold {
    border-left-color: #ed8936;
  }
  
  .project-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 48px rgba(0, 0, 0, 0.15);
  }
  
  .project-header {
    margin-bottom: 16px;
  }
  
  .project-header h3 {
    margin: 0 0 8px 0;
    font-size: 20px;
    font-weight: 700;
    color: #2d3748;
  }
  
  .project-description {
    color: #718096;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 16px;
  }
  
  .project-meta {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 16px;
  }
  
  .meta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 14px;
  }
  
  .meta-label {
    color: #718096;
    font-weight: 500;
  }
  
  .meta-value {
    color: #2d3748;
    font-weight: 600;
  }
  
  .budget-value {
    color: #667eea;
  }
  
  .deadline-value {
    color: #ed8936;
  }
  
  .project-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
  }
  
  .status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
  }
  
  .status-not-started {
    background: #edf2f7;
    color: #4a5568;
  }
  
  .status-in-progress {
    background: #dae7ff;
    color: #2c5282;
  }
  
  .status-completed {
    background: #d4f4dd;
    color: #22543d;
  }
  
  .status-on-hold {
    background: #fed7d7;
    color: #742a2a;
  }
  
  .progress-bar {
    width: 100%;
    height: 8px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 16px;
  }
  
  .progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    transition: width 0.3s;
  }
  
  .btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
  }
  
  .btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
  }
  
  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
  }
  
  .empty-state {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    padding: 64px 32px;
    border-radius: 16px;
    text-align: center;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  }
  
  .empty-state-icon {
    font-size: 64px;
    margin-bottom: 16px;
  }
  
  .empty-state h3 {
    margin: 0 0 8px 0;
    font-size: 24px;
    color: #2d3748;
  }
  
  .empty-state p {
    color: #718096;
    margin-bottom: 24px;
  }
  
  @media (max-width: 768px) {
    .filter-grid {
      grid-template-columns: 1fr;
    }
    
    .projects-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="page-header">
  <h1 class="page-title">📁 Project Management</h1>
  <a href="/MY CASH/business/add_project.php" class="btn btn-primary">+ New Project</a>
</div>

<div class="filters-card">
  <form method="GET" action="">
    <div class="filter-grid">
      <div class="filter-group">
        <label>Search Projects</label>
        <input type="text" name="search" placeholder="Project name or description..." value="<?php echo htmlspecialchars($search); ?>">
      </div>
      
      <div class="filter-group">
        <label>Status</label>
        <select name="status">
          <option value="">All Status</option>
          <option value="not-started" <?php echo $status_filter === 'not-started' ? 'selected' : ''; ?>>Not Started</option>
          <option value="in-progress" <?php echo $status_filter === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
          <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
          <option value="on-hold" <?php echo $status_filter === 'on-hold' ? 'selected' : ''; ?>>On Hold</option>
        </select>
      </div>
      
      <div class="filter-group">
        <button type="submit" class="btn btn-primary">Apply Filters</button>
      </div>
    </div>
  </form>
</div>

<?php if (empty($projects)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">📁</div>
    <h3>No Projects Found</h3>
    <p>Start tracking your work by creating your first project</p>
    <a href="/MY CASH/business/add_project.php" class="btn btn-primary">Create First Project</a>
  </div>
<?php else: ?>
  <div class="projects-grid">
    <?php foreach ($projects as $project): 
      $progress = isset($project['progress']) ? intval($project['progress']) : 0;
      $status = $project['status'] ?? 'not-started';
      $daysLeft = null;
      if (!empty($project['deadline'])) {
        $deadline = new DateTime($project['deadline']);
        $now = new DateTime();
        $diff = $now->diff($deadline);
        $daysLeft = $diff->invert ? -$diff->days : $diff->days;
      }
    ?>
      <div class="project-card status-<?php echo $status; ?>">
        <div class="project-header">
          <h3><?php echo htmlspecialchars($project['name'] ?? 'Untitled Project'); ?></h3>
        </div>
        
        <?php if (!empty($project['description'])): ?>
          <div class="project-description">
            <?php echo htmlspecialchars(substr($project['description'], 0, 120)); ?>
            <?php echo strlen($project['description']) > 120 ? '...' : ''; ?>
          </div>
        <?php endif; ?>
        
        <div class="progress-bar">
          <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
        </div>
        
        <div class="project-meta">
          <div class="meta-row">
            <span class="meta-label">Progress</span>
            <span class="meta-value"><?php echo $progress; ?>%</span>
          </div>
          
          <?php if (!empty($project['budget'])): ?>
            <div class="meta-row">
              <span class="meta-label">Budget</span>
              <span class="meta-value budget-value">RWF <?php echo number_format($project['budget'], 0); ?></span>
            </div>
          <?php endif; ?>
          
          <?php if (!empty($project['deadline'])): ?>
            <div class="meta-row">
              <span class="meta-label">Deadline</span>
              <span class="meta-value deadline-value">
                <?php echo date('M d, Y', strtotime($project['deadline'])); ?>
                <?php if ($daysLeft !== null): ?>
                  (<?php echo $daysLeft > 0 ? "$daysLeft days left" : abs($daysLeft) . " days overdue"; ?>)
                <?php endif; ?>
              </span>
            </div>
          <?php endif; ?>
        </div>
        
        <div class="project-footer">
          <span class="status-badge status-<?php echo $status; ?>">
            <?php echo ucwords(str_replace('-', ' ', $status)); ?>
          </span>
          <span style="font-size: 12px; color: #a0aec0;">
            Created <?php echo date('M Y', strtotime($project['created_at'])); ?>
          </span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

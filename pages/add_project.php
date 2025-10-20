<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }

include __DIR__ . '/../includes/db.php';

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $project_name = trim($_POST['project_name'] ?? '');
    $project_code = trim($_POST['project_code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'planning';
    $priority = $_POST['priority'] ?? 'medium';
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $deadline = $_POST['deadline'] ?? null;
    $budget = floatval($_POST['budget'] ?? 0);
    $client_name = trim($_POST['client_name'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $client_phone = trim($_POST['client_phone'] ?? '');
    
    if (empty($project_name)) throw new Exception("Project name is required");
    if (empty($project_code)) throw new Exception("Project code is required");
    
    // Check duplicate code
    $stmt = $conn->prepare("SELECT id FROM projects WHERE project_code = ? AND user_id = ?");
    $stmt->execute([$project_code, $user_id]);
    if ($stmt->fetch()) {
      throw new Exception("Project code already exists");
    }
    
    // Insert project
    $stmt = $conn->prepare("INSERT INTO projects 
      (user_id, project_name, project_code, description, status, priority, start_date, end_date, deadline, budget, client_name, client_email, client_phone, created_by)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
      $user_id, $project_name, $project_code, $description, $status, $priority,
      $start_date ?: null, $end_date ?: null, $deadline ?: null,
      $budget, $client_name, $client_email, $client_phone, $user_id
    ]);
    
    $project_id = $conn->lastInsertId();
    
    // Add creator as team member
    $stmt = $conn->prepare("INSERT INTO project_members (project_id, user_id, role, can_edit, can_delete, added_by) VALUES (?, ?, 'Project Manager', 1, 1, ?)");
    $stmt->execute([$project_id, $user_id, $user_id]);
    
    header("Location: /MY CASH/pages/view_project.php?id=$project_id");
    exit;
    
  } catch (Exception $e) {
    $error_msg = "❌ Error: " . $e->getMessage();
  }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.page-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 40px;
  border-radius: 20px;
  margin-bottom: 32px;
  color: white;
}
.page-header h1 {
  font-size: 2rem;
  font-weight: 800;
  margin: 0 0 8px 0;
  color: white;
}
.page-header p {
  font-size: 1rem;
  opacity: 0.9;
  margin: 0;
}
.form-card {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 16px;
  padding: 32px;
  margin-bottom: 24px;
}
.form-card h3 {
  margin: 0 0 24px 0;
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--card-text);
}
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
  margin-bottom: 20px;
}
.form-group {
  margin-bottom: 20px;
}
.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: var(--card-text);
  font-size: 0.9rem;
}
.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid var(--border-weak);
  border-radius: 12px;
  background: var(--card-bg);
  color: var(--card-text);
  font-size: 1rem;
  transition: all 0.3s;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}
.form-group textarea {
  resize: vertical;
  min-height: 100px;
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
.alert {
  padding: 16px 20px;
  border-radius: 12px;
  margin-bottom: 24px;
  font-size: 0.95rem;
}
.alert.success {
  background: rgba(46, 213, 115, 0.1);
  border: 1px solid rgba(46, 213, 115, 0.3);
  color: #2ed573;
}
.alert.error {
  background: rgba(245, 87, 108, 0.1);
  border: 1px solid rgba(245, 87, 108, 0.3);
  color: #f5576c;
}
</style>

<div class="page-header">
  <h1>🎯 Create New Project</h1>
  <p>Set up a new project to start tracking tasks and progress</p>
</div>

<?php if ($success_msg): ?>
  <div class="alert success"><?= $success_msg ?></div>
<?php endif; ?>

<?php if ($error_msg): ?>
  <div class="alert error"><?= $error_msg ?></div>
<?php endif; ?>

<form method="POST" action="">
  <div class="form-card">
    <h3>📋 Project Information</h3>
    
    <div class="form-grid">
      <div class="form-group">
        <label>Project Name *</label>
        <input type="text" name="project_name" required placeholder="e.g., Website Redesign">
      </div>
      
      <div class="form-group">
        <label>Project Code *</label>
        <input type="text" name="project_code" required placeholder="e.g., PROJ-001">
      </div>
      
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <option value="planning">Planning</option>
          <option value="active" selected>Active</option>
          <option value="on_hold">On Hold</option>
          <option value="completed">Completed</option>
        </select>
      </div>
      
      <div class="form-group">
        <label>Priority</label>
        <select name="priority">
          <option value="low">Low</option>
          <option value="medium" selected>Medium</option>
          <option value="high">High</option>
          <option value="urgent">Urgent</option>
        </select>
      </div>
    </div>
    
    <div class="form-group">
      <label>Description</label>
      <textarea name="description" placeholder="Project description and goals..."></textarea>
    </div>
  </div>
  
  <div class="form-card">
    <h3>📅 Timeline & Budget</h3>
    
    <div class="form-grid">
      <div class="form-group">
        <label>Start Date</label>
        <input type="date" name="start_date">
      </div>
      
      <div class="form-group">
        <label>End Date</label>
        <input type="date" name="end_date">
      </div>
      
      <div class="form-group">
        <label>Deadline</label>
        <input type="date" name="deadline">
      </div>
      
      <div class="form-group">
        <label>Budget (RWF)</label>
        <input type="number" name="budget" min="0" step="0.01" placeholder="0">
      </div>
    </div>
  </div>
  
  <div class="form-card">
    <h3>👤 Client Information</h3>
    
    <div class="form-grid">
      <div class="form-group">
        <label>Client Name</label>
        <input type="text" name="client_name" placeholder="Optional">
      </div>
      
      <div class="form-group">
        <label>Client Email</label>
        <input type="email" name="client_email" placeholder="Optional">
      </div>
      
      <div class="form-group">
        <label>Client Phone</label>
        <input type="text" name="client_phone" placeholder="Optional">
      </div>
    </div>
  </div>
  
  <div style="margin-top:32px;">
    <button type="submit" class="button primary">✅ Create Project</button>
    <a href="/MY CASH/pages/projects.php" class="button ghost">Cancel</a>
  </div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>

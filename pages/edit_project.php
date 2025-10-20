<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }

include __DIR__ . '/../includes/db.php';

$user_id = $_SESSION['user_id'];
$project_id = intval($_GET['id'] ?? 0);
$success_msg = '';
$error_msg = '';

// Fetch project
$project = null;
try {
  $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
  $stmt->execute([$project_id, $user_id]);
  $project = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if (!$project) {
  header("Location: /MY CASH/pages/projects.php");
  exit;
}

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
    
    // Check duplicate code (excluding current project)
    $stmt = $conn->prepare("SELECT id FROM projects WHERE project_code = ? AND user_id = ? AND id != ?");
    $stmt->execute([$project_code, $user_id, $project_id]);
    if ($stmt->fetch()) {
      throw new Exception("Project code already exists");
    }
    
    // Update project
    $stmt = $conn->prepare("UPDATE projects SET 
      project_name = ?, project_code = ?, description = ?, status = ?, priority = ?, 
      start_date = ?, end_date = ?, deadline = ?, budget = ?, 
      client_name = ?, client_email = ?, client_phone = ?, updated_at = NOW()
      WHERE id = ? AND user_id = ?");
    
    $stmt->execute([
      $project_name, $project_code, $description, $status, $priority,
      $start_date ?: null, $end_date ?: null, $deadline ?: null,
      $budget, $client_name, $client_email, $client_phone,
      $project_id, $user_id
    ]);
    
    header("Location: /MY CASH/pages/view_project.php?id=$project_id");
    exit;
    
  } catch (Exception $e) {
    $error_msg = "❌ Error: " . $e->getMessage();
  }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
.form-group{margin-bottom:20px}
.form-group label{display:block;margin-bottom:8px;font-weight:600;color:var(--card-text)}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:12px;border:1px solid var(--border-weak);border-radius:8px;background:var(--card-bg);color:var(--card-text);font-family:inherit}
.form-group textarea{min-height:100px;resize:vertical}
.form-group small{display:block;margin-top:4px;color:var(--muted);font-size:.85rem}
.button-group{display:flex;gap:12px;justify-content:flex-end;margin-top:24px}
</style>

<div style="max-width:900px;margin:0 auto">
  <div style="margin-bottom:24px">
    <a href="/MY CASH/pages/view_project.php?id=<?= $project_id ?>" style="color:var(--accent-primary);text-decoration:none;display:inline-flex;align-items:center;gap:8px;margin-bottom:16px">
      ← Back to Project
    </a>
    <h2>✏️ Edit Project</h2>
    <p style="color:var(--muted)">Update project details</p>
  </div>

  <?php if ($error_msg): ?>
    <div class="alert danger" style="margin-bottom:24px"><?= htmlspecialchars($error_msg) ?></div>
  <?php endif; ?>

  <div class="card">
    <form method="POST" style="padding:24px">
      <div class="form-grid">
        <div class="form-group">
          <label for="project_name">Project Name *</label>
          <input type="text" id="project_name" name="project_name" value="<?= htmlspecialchars($project['project_name']) ?>" required>
        </div>

        <div class="form-group">
          <label for="project_code">Project Code *</label>
          <input type="text" id="project_code" name="project_code" value="<?= htmlspecialchars($project['project_code']) ?>" required>
          <small>Unique identifier (e.g., PROJ-001)</small>
        </div>

        <div class="form-group">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="planning" <?= $project['status'] === 'planning' ? 'selected' : '' ?>>Planning</option>
            <option value="active" <?= $project['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="on_hold" <?= $project['status'] === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
            <option value="completed" <?= $project['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="cancelled" <?= $project['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
          </select>
        </div>

        <div class="form-group">
          <label for="priority">Priority</label>
          <select id="priority" name="priority">
            <option value="low" <?= $project['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
            <option value="medium" <?= $project['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
            <option value="high" <?= $project['priority'] === 'high' ? 'selected' : '' ?>>High</option>
            <option value="urgent" <?= $project['priority'] === 'urgent' ? 'selected' : '' ?>>Urgent</option>
          </select>
        </div>

        <div class="form-group">
          <label for="start_date">Start Date</label>
          <input type="date" id="start_date" name="start_date" value="<?= $project['start_date'] ?>">
        </div>

        <div class="form-group">
          <label for="end_date">End Date</label>
          <input type="date" id="end_date" name="end_date" value="<?= $project['end_date'] ?>">
        </div>

        <div class="form-group">
          <label for="deadline">Deadline</label>
          <input type="date" id="deadline" name="deadline" value="<?= $project['deadline'] ?>">
        </div>

        <div class="form-group">
          <label for="budget">Budget (RWF)</label>
          <input type="number" id="budget" name="budget" step="0.01" value="<?= $project['budget'] ?>" placeholder="0.00">
        </div>
      </div>

      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4"><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
      </div>

      <h3 style="margin:32px 0 16px;font-size:1.1rem">Client Information</h3>

      <div class="form-grid">
        <div class="form-group">
          <label for="client_name">Client Name</label>
          <input type="text" id="client_name" name="client_name" value="<?= htmlspecialchars($project['client_name'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label for="client_email">Client Email</label>
          <input type="email" id="client_email" name="client_email" value="<?= htmlspecialchars($project['client_email'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label for="client_phone">Client Phone</label>
          <input type="tel" id="client_phone" name="client_phone" value="<?= htmlspecialchars($project['client_phone'] ?? '') ?>">
        </div>
      </div>

      <div class="button-group">
        <a href="/MY CASH/pages/view_project.php?id=<?= $project_id ?>" class="button ghost">Cancel</a>
        <button type="submit" class="button primary">💾 Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

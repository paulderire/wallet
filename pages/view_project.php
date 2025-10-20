<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }

include __DIR__ . '/../includes/db.php';

$user_id = $_SESSION['user_id'];
$project_id = intval($_GET['id'] ?? 0);
$success_msg = '';
$error_msg = '';

// Handle delete action
if (isset($_GET['delete']) && $_GET['delete'] == '1') {
  try {
    $stmt = $conn->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$project_id, $user_id]);
    header("Location: /MY CASH/pages/projects.php?deleted=1");
    exit;
  } catch (Exception $e) {
    $error_msg = "❌ Error deleting project: " . $e->getMessage();
  }
}

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

// Handle quick actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_task') {
      $task_name = trim($_POST['task_name'] ?? '');
      $priority = $_POST['priority'] ?? 'medium';
      $due_date = $_POST['due_date'] ?? null;
      $assigned_to_employee = !empty($_POST['assigned_to_employee']) ? intval($_POST['assigned_to_employee']) : null;
      
      if (empty($task_name)) throw new Exception("Task name is required");
      
      $stmt = $conn->prepare("INSERT INTO project_tasks (project_id, task_name, priority, due_date, assigned_to_employee, created_by) VALUES (?, ?, ?, ?, ?, ?)");
      $stmt->execute([$project_id, $task_name, $priority, $due_date ?: null, $assigned_to_employee, $user_id]);
      
      $success_msg = "✅ Task added successfully!";
      
    } elseif ($action === 'update_task_status') {
      $task_id = intval($_POST['task_id'] ?? 0);
      $status = $_POST['status'] ?? 'todo';
      
      $stmt = $conn->prepare("UPDATE project_tasks SET status = ?, updated_at = NOW() WHERE id = ? AND project_id = ?");
      $stmt->execute([$status, $task_id, $project_id]);
      
      if ($status === 'completed') {
        $stmt = $conn->prepare("UPDATE project_tasks SET completed_date = CURDATE() WHERE id = ?");
        $stmt->execute([$task_id]);
      }
      
      // Recalculate project progress
      $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed FROM project_tasks WHERE project_id = ?");
      $stmt->execute([$project_id]);
      $task_stats = $stmt->fetch(PDO::FETCH_ASSOC);
      $progress = $task_stats['total'] > 0 ? round(($task_stats['completed'] / $task_stats['total']) * 100) : 0;
      
      $stmt = $conn->prepare("UPDATE projects SET progress_percentage = ? WHERE id = ?");
      $stmt->execute([$progress, $project_id]);
      
      $success_msg = "✅ Task status updated!";
      
    } elseif ($action === 'add_member') {
      $employee_id = intval($_POST['employee_id'] ?? 0);
      $role = trim($_POST['role'] ?? 'Team Member');
      
      if (empty($employee_id)) throw new Exception("Please select an employee");
      
      // Check if already member
      $stmt = $conn->prepare("SELECT id FROM project_members WHERE project_id = ? AND employee_id = ?");
      $stmt->execute([$project_id, $employee_id]);
      if ($stmt->fetch()) {
        throw new Exception("This employee is already a team member");
      }
      
      $stmt = $conn->prepare("INSERT INTO project_members (project_id, employee_id, role, added_by) VALUES (?, ?, ?, ?)");
      $stmt->execute([$project_id, $employee_id, $role, $user_id]);
      
      $success_msg = "✅ Team member added!";
      
    } elseif ($action === 'remove_member') {
      $member_id = intval($_POST['member_id'] ?? 0);
      
      $stmt = $conn->prepare("DELETE FROM project_members WHERE id = ? AND project_id = ?");
      $stmt->execute([$member_id, $project_id]);
      
      $success_msg = "✅ Team member removed!";
      
    } elseif ($action === 'add_comment') {
      $comment_text = trim($_POST['comment_text'] ?? '');
      $comment_type = $_POST['comment_type'] ?? 'comment';
      
      if (empty($comment_text)) throw new Exception("Comment cannot be empty");
      
      $stmt = $conn->prepare("INSERT INTO project_comments (project_id, user_id, comment_text, comment_type) VALUES (?, ?, ?, ?)");
      $stmt->execute([$project_id, $user_id, $comment_text, $comment_type]);
      
      $success_msg = "✅ Comment added!";
    }
    
    // Refresh project data
    $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$project_id, $user_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
  } catch (Exception $e) {
    $error_msg = "❌ Error: " . $e->getMessage();
  }
}

include __DIR__ . '/../includes/header.php';

// Fetch all employees for assignment dropdown
$employees = [];
try {
  $stmt = $conn->query("SELECT id, full_name, position FROM employees WHERE status = 'active' ORDER BY full_name");
  $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch tasks grouped by status
$tasks_by_status = [
  'todo' => [],
  'in_progress' => [],
  'review' => [],
  'completed' => []
];

try {
  $stmt = $conn->prepare("SELECT pt.*, e.full_name as assigned_employee_name 
    FROM project_tasks pt 
    LEFT JOIN employees e ON pt.assigned_to_employee = e.id
    WHERE pt.project_id = ? 
    ORDER BY pt.order_position, pt.created_at");
  $stmt->execute([$project_id]);
  $all_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  foreach ($all_tasks as $task) {
    $status = $task['status'];
    if (isset($tasks_by_status[$status])) {
      $tasks_by_status[$status][] = $task;
    }
  }
} catch (Exception $e) {}

// Fetch comments
$comments = [];
try {
  $stmt = $conn->prepare("SELECT pc.*, u.username 
    FROM project_comments pc 
    LEFT JOIN users u ON pc.user_id = u.id 
    WHERE pc.project_id = ? 
    ORDER BY pc.created_at DESC 
    LIMIT 20");
  $stmt->execute([$project_id]);
  $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch team members
$members = [];
try {
  $stmt = $conn->prepare("SELECT pm.*, u.username, e.full_name as employee_name, e.position 
    FROM project_members pm 
    LEFT JOIN users u ON pm.user_id = u.id 
    LEFT JOIN employees e ON pm.employee_id = e.id
    WHERE pm.project_id = ?");
  $stmt->execute([$project_id]);
  $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<style>
.project-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 40px;
  border-radius: 20px;
  margin-bottom: 32px;
  color: white;
}
.project-header h1 {
  font-size: 2rem;
  font-weight: 800;
  margin: 0 0 8px 0;
  color: white;
}
.project-header .code {
  font-family: monospace;
  opacity: 0.8;
  font-size: 0.9rem;
}
.project-meta-row {
  display: flex;
  gap: 24px;
  margin-top: 20px;
  flex-wrap: wrap;
}
.meta-item {
  display: flex;
  align-items: center;
  gap: 8px;
}
.kanban-board {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}
.kanban-column {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 16px;
  padding: 20px;
  min-height: 400px;
}
.column-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 2px solid var(--border-weak);
}
.column-title {
  font-weight: 700;
  font-size: 1.1rem;
  color: var(--card-text);
}
.task-count {
  background: rgba(var(--card-text-rgb), 0.1);
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 0.85rem;
  font-weight: 600;
}
.task-card {
  background: rgba(var(--card-text-rgb), 0.03);
  border: 1px solid var(--border-weak);
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
  cursor: move;
  transition: all 0.2s;
}
.task-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--card-text-rgb), 0.1);
}
.task-card.priority-urgent { border-left: 4px solid #ef4444; }
.task-card.priority-high { border-left: 4px solid #f59e0b; }
.task-card.priority-medium { border-left: 4px solid #3b82f6; }
.task-card.priority-low { border-left: 4px solid #6b7280; }
.task-title {
  font-weight: 600;
  color: var(--card-text);
  margin-bottom: 8px;
}
.task-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.8rem;
  color: var(--muted);
}
.quick-actions {
  display: flex;
  gap: 8px;
  margin-top: 8px;
}
.mini-btn {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.75rem;
  border: 1px solid var(--border-weak);
  background: var(--card-bg);
  color: var(--card-text);
  cursor: pointer;
  transition: all 0.2s;
}
.mini-btn:hover {
  background: rgba(102, 126, 234, 0.1);
  border-color: #667eea;
}
.sidebar {
  position: sticky;
  top: 20px;
}
.info-card {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 20px;
}
.info-card h3 {
  margin: 0 0 16px 0;
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--card-text);
}
.progress-bar {
  background: rgba(var(--card-text-rgb), 0.08);
  border-radius: 8px;
  height: 8px;
  overflow: hidden;
  margin: 12px 0;
}
.progress-fill {
  background: linear-gradient(90deg, #667eea, #764ba2);
  height: 100%;
  transition: width 0.3s;
}
.comment-list {
  max-height: 400px;
  overflow-y: auto;
}
.comment {
  padding: 12px;
  margin-bottom: 12px;
  background: rgba(var(--card-text-rgb), 0.03);
  border-radius: 8px;
  border-left: 3px solid #667eea;
}
.comment-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
}
.comment-author {
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--card-text);
}
.comment-time {
  font-size: 0.75rem;
  color: var(--muted);
}
.comment-text {
  font-size: 0.9rem;
  color: var(--card-text);
  line-height: 1.5;
}
.status-badge {
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  display: inline-block;
}
.status-badge.planning { background: rgba(107, 114, 128, 0.15); color: #6b7280; }
.status-badge.active { background: rgba(46, 213, 115, 0.15); color: #2ed573; }
.status-badge.on_hold { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.status-badge.completed { background: rgba(52, 152, 219, 0.15); color: #3498db; }
</style>

<div class="project-header">
  <div style="display:flex;justify-content:space-between;align-items:start;">
    <div>
      <h1><?= htmlspecialchars($project['project_name']) ?></h1>
      <div class="code"><?= htmlspecialchars($project['project_code']) ?></div>
      <div class="project-meta-row">
        <div class="meta-item">
          <span>📊</span>
          <span class="status-badge <?= $project['status'] ?>"><?= ucfirst($project['status']) ?></span>
        </div>
        <div class="meta-item">
          <span>⚡</span>
          <span>Priority: <?= ucfirst($project['priority']) ?></span>
        </div>
        <?php if ($project['deadline']): ?>
          <div class="meta-item">
            <span>📅</span>
            <span>Due: <?= date('M d, Y', strtotime($project['deadline'])) ?></span>
          </div>
        <?php endif; ?>
        <div class="meta-item">
          <span>💰</span>
          <span>Budget: RWF <?= number_format($project['budget']) ?></span>
        </div>
      </div>
    </div>
    <div style="display:flex;gap:12px">
      <a href="/MY CASH/pages/edit_project.php?id=<?= $project_id ?>" class="button ghost" style="background:rgba(255,255,255,0.2);color:white;border-color:rgba(255,255,255,0.3);">✏ Edit</a>
      <a href="?id=<?= $project_id ?>&delete=1" onclick="return confirm('Are you sure you want to delete this project? This will also delete all tasks, comments, and related data.')" class="button danger" style="background:rgba(245,87,108,0.2);color:white;border-color:rgba(245,87,108,0.4);">🗑️ Delete</a>
    </div>
  </div>
</div>

<?php if ($success_msg): ?>
  <div class="alert success" style="background:rgba(46,213,115,0.1);border:1px solid rgba(46,213,115,0.3);color:#2ed573;padding:16px;border-radius:12px;margin-bottom:24px;"><?= $success_msg ?></div>
<?php endif; ?>

<?php if ($error_msg): ?>
  <div class="alert error" style="background:rgba(245,87,108,0.1);border:1px solid rgba(245,87,108,0.3);color:#f5576c;padding:16px;border-radius:12px;margin-bottom:24px;"><?= $error_msg ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 350px;gap:24px;">
  <div>
    <div style="margin-bottom:24px;">
      <h2 style="font-size:1.4rem;margin-bottom:16px;">📋 Task Board</h2>
      
      <!-- Quick Add Task -->
      <form method="POST" action="" style="background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;padding:16px;margin-bottom:20px;">
        <input type="hidden" name="action" value="add_task">
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:12px;align-items:end;">
          <div>
            <input type="text" name="task_name" placeholder="Add new task..." required style="width:100%;padding:10px;border:2px solid var(--border-weak);border-radius:8px;background:var(--card-bg);color:var(--card-text);">
          </div>
          <div>
            <select name="assigned_to_employee" style="padding:10px;border:2px solid var(--border-weak);border-radius:8px;background:var(--card-bg);color:var(--card-text);">
              <option value="">Unassigned</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <select name="priority" style="padding:10px;border:2px solid var(--border-weak);border-radius:8px;background:var(--card-bg);color:var(--card-text);">
              <option value="low">Low</option>
              <option value="medium" selected>Medium</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
          <div>
            <input type="date" name="due_date" style="padding:10px;border:2px solid var(--border-weak);border-radius:8px;background:var(--card-bg);color:var(--card-text);">
          </div>
          <button type="submit" class="button primary" style="padding:10px 20px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">+ Add</button>
        </div>
      </form>
      
      <!-- Kanban Board -->
      <div class="kanban-board">
        <?php 
        $columns = [
          'todo' => ['title' => '📝 To Do', 'color' => '#6b7280'],
          'in_progress' => ['title' => '🚀 In Progress', 'color' => '#3b82f6'],
          'review' => ['title' => '👀 Review', 'color' => '#f59e0b'],
          'completed' => ['title' => '✅ Completed', 'color' => '#2ed573']
        ];
        
        foreach ($columns as $status => $col): 
          $tasks = $tasks_by_status[$status];
        ?>
          <div class="kanban-column">
            <div class="column-header">
              <div class="column-title"><?= $col['title'] ?></div>
              <div class="task-count"><?= count($tasks) ?></div>
            </div>
            
            <?php foreach ($tasks as $task): ?>
              <div class="task-card priority-<?= $task['priority'] ?>">
                <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px">
                  <div class="task-title"><?= htmlspecialchars($task['task_name']) ?></div>
                  <?php if ($status !== 'completed'): ?>
                    <form method="POST" style="margin:0" onsubmit="return confirm('Mark this task as completed?')">
                      <input type="hidden" name="action" value="update_task_status">
                      <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                      <input type="hidden" name="status" value="completed">
                      <button type="submit" style="background:none;border:2px solid var(--border-weak);border-radius:6px;padding:6px 10px;cursor:pointer;font-size:0.9rem;transition:all 0.2s" title="Mark as complete">
                        ✓ Done
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
                
                <?php if ($task['assigned_employee_name']): ?>
                  <div style="margin-bottom:8px;padding:4px 8px;background:rgba(139,92,246,0.1);border-radius:6px;display:inline-block;font-size:0.75rem">
                    👤 <?= htmlspecialchars($task['assigned_employee_name']) ?>
                  </div>
                <?php endif; ?>
                
                <div class="task-meta">
                  <?php if ($task['due_date']): ?>
                    <span>📅 <?= date('M d', strtotime($task['due_date'])) ?></span>
                  <?php endif; ?>
                  <span style="text-transform:uppercase;font-size:0.7rem;font-weight:600;color:<?= $col['color'] ?>;"><?= $task['priority'] ?></span>
                </div>
                
                <div class="quick-actions">
                  <?php if ($status !== 'todo'): ?>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="action" value="update_task_status">
                      <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                      <input type="hidden" name="status" value="todo">
                      <button type="submit" class="mini-btn">← To Do</button>
                    </form>
                  <?php endif; ?>
                  
                  <?php if ($status === 'todo'): ?>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="action" value="update_task_status">
                      <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                      <input type="hidden" name="status" value="in_progress">
                      <button type="submit" class="mini-btn">Start →</button>
                    </form>
                  <?php elseif ($status === 'in_progress'): ?>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="action" value="update_task_status">
                      <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                      <input type="hidden" name="status" value="review">
                      <button type="submit" class="mini-btn">Review →</button>
                    </form>
                  <?php elseif ($status === 'review'): ?>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="action" value="update_task_status">
                      <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                      <input type="hidden" name="status" value="completed">
                      <button type="submit" class="mini-btn">Complete ✓</button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  
  <!-- Sidebar -->
  <div class="sidebar">
    <!-- Progress -->
    <div class="info-card">
      <h3>📊 Progress</h3>
      <div class="progress-bar">
        <div class="progress-fill" style="width:<?= $project['progress_percentage'] ?>%"></div>
      </div>
      <div style="text-align:center;font-size:1.5rem;font-weight:700;color:var(--card-text);margin-top:8px;">
        <?= $project['progress_percentage'] ?>%
      </div>
    </div>
    
    <!-- Team -->
    <div class="info-card">
      <h3>👥 Team Members</h3>
      
      <!-- Add Team Member Form -->
      <form method="POST" style="margin-bottom:16px;padding:12px;background:rgba(139,92,246,0.05);border-radius:8px;">
        <input type="hidden" name="action" value="add_member">
        <div style="margin-bottom:8px">
          <select name="employee_id" required style="width:100%;padding:8px;border:1px solid var(--border-weak);border-radius:6px;background:var(--card-bg);color:var(--card-text);font-size:0.85rem">
            <option value="">Select Employee...</option>
            <?php foreach ($employees as $emp): ?>
              <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['full_name']) ?> - <?= htmlspecialchars($emp['position'] ?? '') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="margin-bottom:8px">
          <input type="text" name="role" placeholder="Role (e.g., Developer)" style="width:100%;padding:8px;border:1px solid var(--border-weak);border-radius:6px;background:var(--card-bg);color:var(--card-text);font-size:0.85rem">
        </div>
        <button type="submit" style="width:100%;padding:8px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;border:none;border-radius:6px;font-weight:600;cursor:pointer;font-size:0.85rem">
          + Add Member
        </button>
      </form>
      
      <!-- Team Members List -->
      <?php if (empty($members)): ?>
        <p style="text-align:center;color:var(--muted);font-size:0.85rem;padding:16px 0">No team members yet</p>
      <?php else: ?>
        <?php foreach ($members as $member): 
          $display_name = $member['employee_name'] ?? $member['username'] ?? 'Unknown';
          $display_role = $member['role'] ?? 'Member';
          $position = $member['position'] ?? '';
        ?>
          <div style="padding:8px;margin-bottom:8px;background:rgba(var(--card-text-rgb),0.03);border-radius:8px;display:flex;justify-content:space-between;align-items:center">
            <div style="flex:1">
              <div style="font-weight:600;font-size:0.9rem;color:var(--card-text);"><?= htmlspecialchars($display_name) ?></div>
              <div style="font-size:0.75rem;color:var(--muted);"><?= htmlspecialchars($display_role) ?></div>
              <?php if ($position): ?>
                <div style="font-size:0.7rem;color:var(--muted);margin-top:2px">📋 <?= htmlspecialchars($position) ?></div>
              <?php endif; ?>
            </div>
            <form method="POST" style="margin:0" onsubmit="return confirm('Remove this member from the project?')">
              <input type="hidden" name="action" value="remove_member">
              <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
              <button type="submit" style="background:none;border:none;color:#f5576c;cursor:pointer;font-size:1.1rem;padding:4px" title="Remove">🗑️</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    
    <!-- Comments -->
    <div class="info-card">
      <h3>💬 Updates</h3>
      
      <form method="POST" style="margin-bottom:16px;">
        <input type="hidden" name="action" value="add_comment">
        <textarea name="comment_text" placeholder="Add update..." required style="width:100%;padding:10px;border:2px solid var(--border-weak);border-radius:8px;background:var(--card-bg);color:var(--card-text);min-height:60px;resize:vertical;"></textarea>
        <button type="submit" class="button primary" style="width:100%;margin-top:8px;padding:8px;font-size:0.9rem;">Post Update</button>
      </form>
      
      <div class="comment-list">
        <?php foreach ($comments as $comment): ?>
          <div class="comment">
            <div class="comment-header">
              <div class="comment-author"><?= htmlspecialchars($comment['username'] ?? 'User') ?></div>
              <div class="comment-time"><?= date('M d, h:i A', strtotime($comment['created_at'])) ?></div>
            </div>
            <div class="comment-text"><?= nl2br(htmlspecialchars($comment['comment_text'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['employee_id'])) {
  header("Location: /MY CASH/employee_login.php");
  exit;
}

include __DIR__ . '/../includes/db.php';

$employee_id = $_SESSION['employee_id'];
$user_id = $_SESSION['employee_user_id'];

$success = '';
$error = '';

// Fetch categories
$categories = [];
try {
  $stmt = $conn->prepare("SELECT * FROM task_categories WHERE user_id = ? ORDER BY category_name");
  $stmt->execute([$user_id]);
  $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $task_date = $_POST['task_date'] ?? date('Y-m-d');
  $task_time = $_POST['task_time'] ?? date('H:i');
  $title = trim($_POST['title'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $category = trim($_POST['category'] ?? '');
  $status = $_POST['status'] ?? 'pending';
  $duration = intval($_POST['duration_minutes'] ?? 0);
  $priority = $_POST['priority'] ?? 'medium';
  
  if (empty($title)) {
    $error = 'Please enter a task title';
  } else {
    try {
      $stmt = $conn->prepare("INSERT INTO employee_tasks 
        (employee_id, user_id, task_date, task_time, title, description, category, status, duration_minutes, priority)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
      
      $stmt->execute([
        $employee_id,
        $user_id,
        $task_date,
        $task_time,
        $title,
        $description,
        $category,
        $status,
        $duration > 0 ? $duration : null,
        $priority
      ]);
      
      $task_id = $conn->lastInsertId();
      
      // Handle file uploads
      if (!empty($_FILES['attachments']['name'][0])) {
        $upload_dir = __DIR__ . '/../assets/uploads/tasks/';
        if (!is_dir($upload_dir)) {
          mkdir($upload_dir, 0755, true);
        }
        
        $file_count = count($_FILES['attachments']['name']);
        for ($i = 0; $i < $file_count; $i++) {
          if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['attachments']['name'][$i];
            $file_tmp = $_FILES['attachments']['tmp_name'][$i];
            $file_size = $_FILES['attachments']['size'][$i];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Generate unique filename
            $new_filename = 'task_' . $task_id . '_' . time() . '_' . $i . '.' . $file_ext;
            $file_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $file_path)) {
              // Save to database
              $file_stmt = $conn->prepare("INSERT INTO task_attachments 
                (task_id, file_name, file_path, file_type, file_size) 
                VALUES (?, ?, ?, ?, ?)");
              $file_stmt->execute([
                $task_id,
                $file_name,
                '/MY CASH/assets/uploads/tasks/' . $new_filename,
                $file_ext,
                $file_size
              ]);
            }
          }
        }
      }
      
      $success = 'Task added successfully!';
      
      // Clear form
      $_POST = [];
      
    } catch (Exception $e) {
      $error = 'Failed to add task: ' . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Add Task - Employee Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 24px;
    }
    
    .header {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      padding: 20px 32px;
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
      margin-bottom: 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .page-title {
      font-size: 24px;
      font-weight: 800;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    
    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
      font-size: 14px;
      transition: all 0.2s;
    }
    
    .btn-secondary {
      background: white;
      color: #667eea;
      border: 2px solid #667eea;
    }
    
    .btn-secondary:hover {
      background: #667eea;
      color: white;
    }
    
    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }
    
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
    }
    
    .form-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      padding: 32px;
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
      max-width: 800px;
      margin: 0 auto;
    }
    
    .alert {
      padding: 12px 16px;
      border-radius: 12px;
      margin-bottom: 24px;
      font-size: 14px;
    }
    
    .alert-error {
      background: #fed7d7;
      color: #742a2a;
      border: 1px solid #fc8181;
    }
    
    .alert-success {
      background: #d4f4dd;
      color: #22543d;
      border: 1px solid #48bb78;
    }
    
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 20px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-group.full-width {
      grid-column: 1 / -1;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #2d3748;
      font-size: 14px;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 12px 14px;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-size: 14px;
      font-family: 'Inter', sans-serif;
      transition: all 0.2s;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .form-group textarea {
      resize: vertical;
      min-height: 100px;
    }
    
    .radio-group {
      display: flex;
      gap: 16px;
    }
    
    .radio-option {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .radio-option input[type="radio"] {
      width: auto;
    }
    
    .file-upload-zone {
      border: 2px dashed #cbd5e0;
      border-radius: 8px;
      padding: 24px;
      text-align: center;
      background: #f7fafc;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .file-upload-zone:hover {
      border-color: #667eea;
      background: rgba(102, 126, 234, 0.05);
    }
    
    .file-upload-zone input[type="file"] {
      display: none;
    }
    
    .file-icon {
      font-size: 48px;
      margin-bottom: 8px;
    }
    
    .form-actions {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      margin-top: 24px;
      padding-top: 24px;
      border-top: 1px solid #e2e8f0;
    }
    
    @media (max-width: 768px) {
      .form-grid {
        grid-template-columns: 1fr;
      }
      
      .header {
        flex-direction: column;
        gap: 12px;
      }
    }
  </style>
</head>
<body>
  <div class="header">
    <h1 class="page-title">📝 Add New Task</h1>
    <a href="/MY CASH/employee/dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
  </div>
  
  <div class="form-card">
    <?php if ($error): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
      <div class="alert alert-success">
        <?php echo htmlspecialchars($success); ?>
        <a href="/MY CASH/employee/add_task.php" style="color: inherit; font-weight: 700; text-decoration: underline; margin-left: 8px;">Add Another</a>
      </div>
    <?php endif; ?>
    
    <form method="POST" action="" enctype="multipart/form-data">
      <div class="form-grid">
        <div class="form-group">
          <label for="task_date">Date *</label>
          <input type="date" id="task_date" name="task_date" value="<?php echo $_POST['task_date'] ?? date('Y-m-d'); ?>" required>
        </div>
        
        <div class="form-group">
          <label for="task_time">Time *</label>
          <input type="time" id="task_time" name="task_time" value="<?php echo $_POST['task_time'] ?? date('H:i'); ?>" required>
        </div>
        
        <div class="form-group full-width">
          <label for="title">Task Title *</label>
          <input type="text" id="title" name="title" placeholder="What did you work on?" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group full-width">
          <label for="description">Description</label>
          <textarea id="description" name="description" placeholder="Provide details about the task..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-group">
          <label for="category">Category</label>
          <select id="category" name="category">
            <option value="">Select Category</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo htmlspecialchars($cat['category_name']); ?>" <?php echo (($_POST['category'] ?? '') === $cat['category_name']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($cat['icon'] . ' ' . $cat['category_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="form-group">
          <label for="duration_minutes">Duration (minutes)</label>
          <input type="number" id="duration_minutes" name="duration_minutes" placeholder="e.g., 30" min="0" value="<?php echo $_POST['duration_minutes'] ?? ''; ?>">
        </div>
        
        <div class="form-group">
          <label>Status *</label>
          <div class="radio-group">
            <div class="radio-option">
              <input type="radio" id="status_pending" name="status" value="pending" <?php echo (($_POST['status'] ?? 'pending') === 'pending') ? 'checked' : ''; ?>>
              <label for="status_pending" style="margin: 0;">Pending</label>
            </div>
            <div class="radio-option">
              <input type="radio" id="status_progress" name="status" value="in-progress" <?php echo (($_POST['status'] ?? '') === 'in-progress') ? 'checked' : ''; ?>>
              <label for="status_progress" style="margin: 0;">In Progress</label>
            </div>
            <div class="radio-option">
              <input type="radio" id="status_completed" name="status" value="completed" <?php echo (($_POST['status'] ?? '') === 'completed') ? 'checked' : ''; ?>>
              <label for="status_completed" style="margin: 0;">Completed</label>
            </div>
          </div>
        </div>
        
        <div class="form-group">
          <label>Priority</label>
          <div class="radio-group">
            <div class="radio-option">
              <input type="radio" id="priority_low" name="priority" value="low" <?php echo (($_POST['priority'] ?? '') === 'low') ? 'checked' : ''; ?>>
              <label for="priority_low" style="margin: 0;">Low</label>
            </div>
            <div class="radio-option">
              <input type="radio" id="priority_medium" name="priority" value="medium" <?php echo (($_POST['priority'] ?? 'medium') === 'medium') ? 'checked' : ''; ?>>
              <label for="priority_medium" style="margin: 0;">Medium</label>
            </div>
            <div class="radio-option">
              <input type="radio" id="priority_high" name="priority" value="high" <?php echo (($_POST['priority'] ?? '') === 'high') ? 'checked' : ''; ?>>
              <label for="priority_high" style="margin: 0;">High</label>
            </div>
          </div>
        </div>
        
        <div class="form-group full-width">
          <label>Attachments (Photos, Documents, Receipts)</label>
          <div class="file-upload-zone" onclick="document.getElementById('attachments').click()">
            <div class="file-icon">📎</div>
            <p style="color: #4a5568; margin-bottom: 4px;">Click to upload files</p>
            <p style="font-size: 12px; color: #a0aec0;">PDF, Images, Documents (Max 10MB each)</p>
            <input type="file" id="attachments" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
          </div>
        </div>
      </div>
      
      <div class="form-actions">
        <a href="/MY CASH/employee/dashboard.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Task</button>
      </div>
    </form>
  </div>
  
  <script>
    document.getElementById('attachments').addEventListener('change', function(e) {
      const files = e.target.files;
      if (files.length > 0) {
        const zone = document.querySelector('.file-upload-zone');
        zone.querySelector('p').textContent = files.length + ' file(s) selected';
      }
    });
  </script>
</body>
</html>

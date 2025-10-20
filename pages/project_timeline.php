<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }

include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

$user_id = $_SESSION['user_id'];

// Fetch all activities (tasks, milestones, comments)
$timeline = [];

try {
  // Get task activities
  $stmt = $conn->prepare("
    SELECT 'task' as type, pt.id, pt.task_name as title, p.project_name, p.project_code,
           pt.created_at as date, pt.status, e.full_name as actor
    FROM project_tasks pt
    JOIN projects p ON pt.project_id = p.id
    LEFT JOIN employees e ON pt.created_by = e.id
    WHERE p.user_id = ?
    
    UNION ALL
    
    SELECT 'comment' as type, pc.id, pc.comment_text as title, p.project_name, p.project_code,
           pc.created_at as date, pc.comment_type as status, u.username as actor
    FROM project_comments pc
    JOIN projects p ON pc.project_id = p.id
    LEFT JOIN users u ON pc.user_id = u.id
    WHERE p.user_id = ?
    
    UNION ALL
    
    SELECT 'milestone' as type, pm.id, pm.milestone_name as title, p.project_name, p.project_code,
           pm.created_at as date, pm.status, NULL as actor
    FROM project_milestones pm
    JOIN projects p ON pm.project_id = p.id
    WHERE p.user_id = ?
    
    ORDER BY date DESC
    LIMIT 100
  ");
  $stmt->execute([$user_id, $user_id, $user_id]);
  $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

?>

<style>
.timeline-container{max-width:900px;margin:0 auto}
.timeline{position:relative;padding:20px 0}
.timeline::before{content:'';position:absolute;left:40px;top:0;bottom:0;width:4px;background:linear-gradient(180deg,#667eea 0%,#764ba2 100%)}
.timeline-item{position:relative;margin-bottom:32px;padding-left:80px}
.timeline-icon{position:absolute;left:20px;width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.2rem;background:var(--card-bg);border:4px solid var(--border-weak);z-index:2}
.timeline-icon.task{background:linear-gradient(135deg,#3b82f6,#2563eb);border-color:#3b82f6}
.timeline-icon.comment{background:linear-gradient(135deg,#f59e0b,#d97706);border-color:#f59e0b}
.timeline-icon.milestone{background:linear-gradient(135deg,#8b5cf6,#7c3aed);border-color:#8b5cf6}
.timeline-content{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;padding:20px;box-shadow:var(--overlay-shadow)}
.timeline-content:hover{border-color:rgba(139,92,246,0.4)}
.timeline-header{display:flex;justify-content:space-between;align-items:start;margin-bottom:12px}
.timeline-title{font-size:1.1rem;font-weight:600;color:var(--card-text);margin:0 0 4px 0}
.timeline-project{font-size:0.85rem;color:var(--muted);font-family:monospace}
.timeline-meta{display:flex;gap:16px;font-size:0.85rem;color:var(--muted);margin-top:12px;padding-top:12px;border-top:1px solid var(--border-weak)}
.filter-pills{display:flex;gap:12px;margin-bottom:24px}
.filter-pill{padding:8px 16px;border-radius:20px;font-size:0.85rem;font-weight:600;cursor:pointer;border:2px solid var(--border-weak);background:var(--card-bg);color:var(--muted);transition:all 0.2s}
.filter-pill.active{background:linear-gradient(135deg,#667eea,#764ba2);color:white;border-color:#667eea}
</style>

<div style="margin-bottom:32px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div>
      <h2>📅 Project Timeline</h2>
      <p style="color:var(--muted)">Chronological view of all project activities</p>
    </div>
    <a href="/MY CASH/pages/projects.php" class="button ghost">← Back to Projects</a>
  </div>
</div>

<!-- Filter Pills -->
<div class="filter-pills">
  <button class="filter-pill active" onclick="filterTimeline('all')">All Activities</button>
  <button class="filter-pill" onclick="filterTimeline('task')">📋 Tasks</button>
  <button class="filter-pill" onclick="filterTimeline('comment')">💬 Comments</button>
  <button class="filter-pill" onclick="filterTimeline('milestone')">🎯 Milestones</button>
</div>

<!-- Timeline -->
<div class="timeline-container">
  <?php if (empty($timeline)): ?>
    <div class="card" style="text-align:center;padding:48px 24px">
      <div style="font-size:3rem;margin-bottom:16px;opacity:0.3">📅</div>
      <h3>No activities yet</h3>
      <p style="color:var(--muted)">Start adding tasks and milestones to see your project timeline</p>
    </div>
  <?php else: ?>
    <div class="timeline">
      <?php foreach ($timeline as $item): 
        $icon_map = [
          'task' => '📋',
          'comment' => '💬',
          'milestone' => '🎯'
        ];
        $icon = $icon_map[$item['type']] ?? '📌';
      ?>
        <div class="timeline-item" data-type="<?= $item['type'] ?>">
          <div class="timeline-icon <?= $item['type'] ?>"><?= $icon ?></div>
          <div class="timeline-content">
            <div class="timeline-header">
              <div>
                <h3 class="timeline-title"><?= htmlspecialchars($item['title']) ?></h3>
                <div class="timeline-project">
                  <?= htmlspecialchars($item['project_name']) ?> • <?= htmlspecialchars($item['project_code']) ?>
                </div>
              </div>
              <?php if ($item['status']): ?>
                <span style="padding:4px 12px;background:rgba(139,92,246,0.15);color:#8b5cf6;border-radius:12px;font-size:0.75rem;font-weight:600;text-transform:uppercase">
                  <?= str_replace('_', ' ', $item['status']) ?>
                </span>
              <?php endif; ?>
            </div>
            
            <div class="timeline-meta">
              <span>🕒 <?= date('M d, Y g:i A', strtotime($item['date'])) ?></span>
              <?php if ($item['actor']): ?>
                <span>👤 <?= htmlspecialchars($item['actor']) ?></span>
              <?php endif; ?>
              <span style="text-transform:uppercase;font-weight:600;color:#8b5cf6">
                <?= $item['type'] ?>
              </span>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
function filterTimeline(type) {
  // Update active pill
  document.querySelectorAll('.filter-pill').forEach(pill => {
    pill.classList.remove('active');
  });
  event.target.classList.add('active');
  
  // Filter timeline items
  document.querySelectorAll('.timeline-item').forEach(item => {
    if (type === 'all' || item.dataset.type === type) {
      item.style.display = 'block';
    } else {
      item.style.display = 'none';
    }
  });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

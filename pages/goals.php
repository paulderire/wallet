<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../includes/db.php';
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }
$user_id = $_SESSION['user_id'];

if(isset($_POST['add_goal'])){
  $title = trim($_POST['title']); $target_amount = floatval($_POST['target_amount'] ?? 0); $deadline = $_POST['deadline'] ?? null;
  $stmt = $conn->prepare("INSERT INTO goals (user_id,title,target_amount,deadline) VALUES (?,?,?,?)");
  $stmt->execute([$user_id,$title,$target_amount,$deadline]);
  header("Location: /MY CASH/pages/goals.php"); exit;
}

if(isset($_POST['update_saved'])){
  $goal_id = intval($_POST['goal_id']); $saved_amount = floatval($_POST['saved_amount'] ?? 0);
  $stmt = $conn->prepare("UPDATE goals SET saved_amount=? WHERE id=? AND user_id=?");
  $stmt->execute([$saved_amount,$goal_id,$user_id]);
  header("Location: /MY CASH/pages/goals.php"); exit;
}

if (isset($_POST['edit_goal'])) {
  $goal_id = intval($_POST['goal_id']); $title = trim($_POST['title'] ?? ''); $target_amount = floatval($_POST['target_amount'] ?? 0); $deadline = $_POST['deadline'] ?? null;
  $u = $conn->prepare("UPDATE goals SET title=?,target_amount=?,deadline=? WHERE id=? AND user_id=?");
  $u->execute([$title,$target_amount,$deadline,$goal_id,$user_id]);
  header("Location: /MY CASH/pages/goals.php"); exit;
}

if (isset($_GET['delete'])) {
  $did = intval($_GET['delete']); $chk = $conn->prepare("SELECT saved_amount,target_amount FROM goals WHERE id=? AND user_id=?");
  $chk->execute([$did,$user_id]); $row = $chk->fetch(PDO::FETCH_ASSOC);
  if ($row && floatval($row['target_amount']) > 0 && floatval($row['saved_amount']) >= floatval($row['target_amount'])) {
    $d = $conn->prepare("DELETE FROM goals WHERE id=? AND user_id=?"); $d->execute([$did,$user_id]);
  }
  header("Location: /MY CASH/pages/goals.php"); exit;
}

$stmt = $conn->prepare("SELECT * FROM goals WHERE user_id=? ORDER BY id DESC");
$stmt->execute([$user_id]); $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
include __DIR__ . '/../includes/header.php';
?>
<style>
.goals-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(380px,1fr));gap:16px;margin-top:16px}
.goal-card{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;padding:20px;box-shadow:var(--overlay-shadow);transition:transform .2s}
.goal-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(var(--card-text-rgb),.15)}
.goal-card.completed{border-color:#2ed573;background:linear-gradient(135deg,rgba(46,213,115,.08),rgba(46,213,115,.02))}
.goal-title{font-size:1.25rem;font-weight:700;margin-bottom:16px;color:var(--card-text)}
.goal-amounts{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px;text-align:center}
.goal-amount-label{font-size:.75rem;color:var(--muted);text-transform:uppercase}
.goal-amount-value{font-size:1.1rem;font-weight:700;margin-top:4px}
.goal-progress-bar{width:100%;height:10px;background:rgba(var(--card-text-rgb),.1);border-radius:8px;overflow:hidden;margin:8px 0}
.goal-progress-fill{height:100%;border-radius:8px;transition:width .3s}
.goal-progress-fill.low{background:linear-gradient(90deg,#f093fb,#f5576c)}
.goal-progress-fill.medium{background:linear-gradient(90deg,#ffa500,#ff6348)}
.goal-progress-fill.high{background:linear-gradient(90deg,#2ed573,#17c0eb)}
.goal-progress-fill.complete{background:linear-gradient(90deg,#11998e,#38ef7d)}
.goal-meta{display:flex;justify-content:space-between;padding:12px 0;border-top:1px solid var(--border-weak);margin-bottom:12px;font-size:.85rem;color:var(--muted)}
.goal-actions{display:flex;flex-wrap:wrap;gap:8px}
.goal-actions .button{flex:1;min-width:fit-content}
.goal-badge{display:inline-block;padding:4px 12px;border-radius:16px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.goal-badge.active{background:rgba(102,126,234,.15);color:#667eea;border:1px solid rgba(102,126,234,.3)}
.goal-badge.completed{background:rgba(46,213,115,.15);color:#2ed573;border:1px solid rgba(46,213,115,.3)}
@media (max-width:768px){.goals-grid{grid-template-columns:1fr}.goal-amounts{grid-template-columns:1fr}}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:16px">
<div><h2> Financial Goals</h2><p class="muted">Track your savings targets</p></div>
<button id="show-add-goal" class="button primary">+ Add Goal</button>
</div>

<?php if (empty($goals)): ?>
<div class="card" style="text-align:center;padding:48px 24px">
<div style="font-size:3rem;margin-bottom:16px;opacity:.3"></div>
<h3>No goals yet</h3>
<p class="muted">Click "Add Goal" to start saving toward your dreams</p>
</div>
<?php else: ?>
<div class="goals-grid">
<?php foreach($goals as $g): 
$saved = floatval($g['saved_amount'] ?? 0); $target = floatval($g['target_amount'] ?? 0); $remaining = max(0, $target - $saved);
$percent = $target > 0 ? min(100, round(($saved / $target) * 100, 2)) : 0;
$progressClass = $percent >= 100 ? 'complete' : ($percent >= 75 ? 'high' : ($percent >= 40 ? 'medium' : 'low'));
$isComplete = $percent >= 100;
?>
<div class="goal-card <?=$isComplete?'completed':''?>">
<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px">
<div class="goal-title"><?=htmlspecialchars($g['title'])?></div>
<span class="goal-badge <?=$isComplete?'completed':'active'?>"><?=$isComplete?' Complete':'In Progress'?></span>
</div>
<div class="goal-amounts">
<div><div class="goal-amount-label">Target</div><div class="goal-amount-value"><span class="amount" data-currency="USD" data-amount="<?=$target?>"><?=number_format($target,2)?></span></div></div>
<div><div class="goal-amount-label">Saved</div><div class="goal-amount-value" style="color:<?=$isComplete?'#2ed573':'#667eea'?>"><span class="amount" data-currency="USD" data-amount="<?=$saved?>"><?=number_format($saved,2)?></span></div></div>
<div><div class="goal-amount-label">Remaining</div><div class="goal-amount-value" style="color:<?=$isComplete?'#2ed573':'#ffa500'?>"><span class="amount" data-currency="USD" data-amount="<?=$remaining?>"><?=number_format($remaining,2)?></span></div></div>
</div>
<div style="display:flex;justify-content:space-between;margin-bottom:8px">
<span style="font-size:.85rem;color:var(--muted)">Progress</span>
<span style="font-size:.9rem;font-weight:700"><?=$percent?>%</span>
</div>
<div class="goal-progress-bar">
<div class="goal-progress-fill <?=$progressClass?>" style="width:<?=$percent?>%"></div>
</div>
<?php if (!empty($g['deadline'])): ?>
<div class="goal-meta">
<div>Deadline: <?=htmlspecialchars($g['deadline'])?></div>
<div><?php 
$deadline = new DateTime($g['deadline']); $now = new DateTime(); $diff = $now->diff($deadline);
if ($deadline < $now) echo '<span style="color:#f5576c">Overdue</span>';
elseif ($diff->days <= 30) echo '<span style="color:#ffa500">'.$diff->days.' days left</span>';
else echo $diff->days.' days left';
?></div>
</div>
<?php else: ?>
<div class="goal-meta"><div>No deadline set</div><div></div></div>
<?php endif; ?>
<div class="goal-actions">
<button class="button ghost small js-update-goal" data-goal='<?=json_encode($g)?>'> Update Saved</button>
<button class="button ghost small js-edit-goal" data-goal='<?=json_encode($g)?>'> Edit</button>
<?php if($isComplete): ?>
<button class="button danger small js-delete-goal" data-id="<?=$g['id']?>"> Delete</button>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<script>
document.getElementById('show-add-goal').addEventListener('click', function() {
  var html = '<div style="padding:24px"><h3 style="margin-bottom:24px"> Add New Goal</h3>' +
    '<form method="POST" style="display:flex;flex-direction:column;gap:16px">' +
    '<div><label>Goal Title</label><input type="text" name="title" placeholder="Emergency Fund, Vacation, etc." required style="width:100%"></div>' +
    '<div><label>Target Amount</label><input type="number" step="0.01" name="target_amount" placeholder="5000.00" required style="width:100%"></div>' +
    '<div><label>Deadline (optional)</label><input type="date" name="deadline" style="width:100%"></div>' +
    '<div style="display:flex;gap:12px;margin-top:16px">' +
    '<button type="submit" name="add_goal" class="button primary" style="flex:1">Add Goal</button>' +
    '<button type="button" class="button ghost" onclick="WCModal.close()" style="flex:1">Cancel</button>' +
    '</div></form></div>';
  window.WCModal.open(html);
});

document.querySelectorAll('.js-update-goal').forEach(btn => {
  btn.addEventListener('click', function() {
    var goal = JSON.parse(this.dataset.goal);
    var html = '<div style="padding:24px"><h3 style="margin-bottom:24px"> Update Saved Amount</h3>' +
      '<p style="margin-bottom:16px">Goal: <strong>' + goal.title + '</strong></p>' +
      '<p style="margin-bottom:24px;color:var(--muted)">Current: $' + parseFloat(goal.saved_amount||0).toFixed(2) + ' / $' + parseFloat(goal.target_amount||0).toFixed(2) + '</p>' +
      '<form method="POST" style="display:flex;flex-direction:column;gap:16px">' +
      '<input type="hidden" name="goal_id" value="' + goal.id + '">' +
      '<div><label>New Saved Amount</label><input type="number" step="0.01" name="saved_amount" value="' + (goal.saved_amount||0) + '" required style="width:100%"></div>' +
      '<div style="display:flex;gap:12px;margin-top:16px">' +
      '<button type="submit" name="update_saved" class="button primary" style="flex:1">Update</button>' +
      '<button type="button" class="button ghost" onclick="WCModal.close()" style="flex:1">Cancel</button>' +
      '</div></form></div>';
    window.WCModal.open(html);
  });
});

document.querySelectorAll('.js-edit-goal').forEach(btn => {
  btn.addEventListener('click', function() {
    var goal = JSON.parse(this.dataset.goal);
    var html = '<div style="padding:24px"><h3 style="margin-bottom:24px"> Edit Goal</h3>' +
      '<form method="POST" style="display:flex;flex-direction:column;gap:16px">' +
      '<input type="hidden" name="goal_id" value="' + goal.id + '">' +
      '<div><label>Goal Title</label><input type="text" name="title" value="' + goal.title + '" required style="width:100%"></div>' +
      '<div><label>Target Amount</label><input type="number" step="0.01" name="target_amount" value="' + (goal.target_amount||0) + '" required style="width:100%"></div>' +
      '<div><label>Deadline</label><input type="date" name="deadline" value="' + (goal.deadline||'') + '" style="width:100%"></div>' +
      '<div style="display:flex;gap:12px;margin-top:16px">' +
      '<button type="submit" name="edit_goal" class="button primary" style="flex:1">Save Changes</button>' +
      '<button type="button" class="button ghost" onclick="WCModal.close()" style="flex:1">Cancel</button>' +
      '</div></form></div>';
    window.WCModal.open(html);
  });
});

document.querySelectorAll('.js-delete-goal').forEach(btn => {
  btn.addEventListener('click', function() {
    var goalId = this.dataset.id;
    if (confirm('Are you sure you want to delete this completed goal? This cannot be undone.')) {
      window.location.href = 'goals.php?delete=' + goalId;
    }
  });
});
</script>

<?php include '../includes/footer.php'; ?>

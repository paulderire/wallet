<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../includes/db.php';
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }
$user_id = $_SESSION['user_id'];

if(isset($_POST['add_budget'])){
  $category = trim($_POST['category']); $amount = floatval($_POST['amount'] ?? 0);
  $period = $_POST['period'] ?? 'monthly'; $alert_threshold = intval($_POST['alert_threshold'] ?? 80);
  $stmt = $conn->prepare("INSERT INTO budgets (user_id,category,amount,period,alert_threshold) VALUES (?,?,?,?,?)");
  $stmt->execute([$user_id,$category,$amount,$period,$alert_threshold]);
  header("Location: /MY CASH/pages/budgets.php"); exit;
}

if(isset($_POST['edit_budget'])){
  $bid = intval($_POST['budget_id']); $category = trim($_POST['category']);
  $amount = floatval($_POST['amount'] ?? 0); $period = $_POST['period'] ?? 'monthly';
  $alert_threshold = intval($_POST['alert_threshold'] ?? 80);
  $stmt = $conn->prepare("UPDATE budgets SET category=?,amount=?,period=?,alert_threshold=? WHERE id=? AND user_id=?");
  $stmt->execute([$category,$amount,$period,$alert_threshold,$bid,$user_id]);
  header("Location: /MY CASH/pages/budgets.php"); exit;
}

if(isset($_GET['delete'])){
  $bid = intval($_GET['delete']);
  $stmt = $conn->prepare("DELETE FROM budgets WHERE id=? AND user_id=?");
  $stmt->execute([$bid,$user_id]);
  header("Location: /MY CASH/pages/budgets.php"); exit;
}

$stmt = $conn->prepare("SELECT * FROM budgets WHERE user_id=? ORDER BY id DESC");
$stmt->execute([$user_id]); $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalBudget = 0; 
$totalSpent = 0;
foreach($budgets as &$b){ 
  if($b['period'] === 'monthly') $totalBudget += floatval($b['amount']); 
  
  // Calculate spent amount for this budget (from transactions)
  $spent = 0;
  try {
    $category = $b['category'];
    $spentStmt = $conn->prepare("SELECT COALESCE(SUM(t.amount), 0) as spent 
      FROM transactions t 
      JOIN accounts a ON t.account_id = a.id 
      WHERE a.user_id = ? 
      AND t.type = 'withdraw' 
      AND t.notes LIKE ?
      AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $spentStmt->execute([$user_id, '%' . $category . '%']);
    $spentRow = $spentStmt->fetch(PDO::FETCH_ASSOC);
    $spent = floatval($spentRow['spent'] ?? 0);
  } catch (Exception $e) {}
  
  $b['spent'] = $spent;
  $b['remaining'] = max(0, floatval($b['amount']) - $spent);
  $b['percentage'] = floatval($b['amount']) > 0 ? min(100, ($spent / floatval($b['amount'])) * 100) : 0;
  $totalSpent += $spent;
}
unset($b);

include __DIR__ . '/../includes/header.php';
?>

<style>
.budgets-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:16px}
.budget-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:32px}
.budget-summary-card{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;padding:20px;text-align:center;box-shadow:var(--overlay-shadow)}
.budget-summary-card.primary{border-top:4px solid #667eea;background:linear-gradient(135deg,rgba(102,126,234,.08),rgba(102,126,234,.02))}
.budget-summary-card.success{border-top:4px solid #2ed573;background:linear-gradient(135deg,rgba(46,213,115,.08),rgba(46,213,115,.02))}
.budget-summary-card.warning{border-top:4px solid #ffa500;background:linear-gradient(135deg,rgba(255,165,0,.08),rgba(255,165,0,.02))}
.budget-summary-label{font-size:.85rem;color:var(--muted);text-transform:uppercase;margin-bottom:8px;font-weight:600}
.budget-summary-value{font-size:2rem;font-weight:700;color:var(--card-text)}
.budgets-table-wrapper{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;overflow:hidden;box-shadow:var(--overlay-shadow)}
.budgets-table{width:100%;border-collapse:collapse}
.budgets-table thead{background:rgba(var(--card-text-rgb),.05)}
.budgets-table th{padding:16px;text-align:left;font-weight:700;color:var(--card-text);border-bottom:2px solid var(--border-weak)}
.budgets-table td{padding:14px 16px;border-bottom:1px solid var(--border-weak);color:var(--card-text)}
.budgets-table tbody tr:hover{background:rgba(var(--card-text-rgb),.03)}
.period-badge{display:inline-block;padding:4px 10px;border-radius:12px;font-size:.8rem;font-weight:600;text-transform:uppercase}
.period-badge.weekly{background:rgba(102,126,234,.15);color:#667eea;border:1px solid rgba(102,126,234,.3)}
.period-badge.monthly{background:rgba(46,213,115,.15);color:#2ed573;border:1px solid rgba(46,213,115,.3)}
.period-badge.yearly{background:rgba(255,165,0,.15);color:#ffa500;border:1px solid rgba(255,165,0,.3)}
.alert-indicator{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:12px;font-size:.8rem;font-weight:600}
.alert-indicator.low{background:rgba(46,213,115,.15);color:#2ed573}
.alert-indicator.medium{background:rgba(255,165,0,.15);color:#ffa500}
.alert-indicator.high{background:rgba(245,87,108,.15);color:#f5576c}
@media (max-width:768px){.budgets-table{font-size:.9rem}.budgets-table th,.budgets-table td{padding:10px 8px}}
</style>

<div class="budgets-header">
<div><h2> Budgets</h2><p class="muted">Track and manage your spending limits</p></div>
<div style="display:flex;gap:12px;flex-wrap:wrap">
<a href="/MY CASH/pages/budget_settings.php" class="button ghost">⚙️ Settings</a>
<button id="show-add-budget" class="button primary">+ Add Budget</button>
</div>
</div>

<div class="budget-summary">
<div class="budget-summary-card primary">
<div class="budget-summary-label">Total Budgets</div>
<div class="budget-summary-value"><?=count($budgets)?></div>
</div>
<div class="budget-summary-card success">
<div class="budget-summary-label">Monthly Total</div>
<div class="budget-summary-value"><span class="amount" data-currency="USD" data-amount="<?=$totalBudget?>"><?=number_format($totalBudget,2)?></span></div>
</div>
<div class="budget-summary-card warning">
<div class="budget-summary-label">Categories</div>
<div class="budget-summary-value"><?=count(array_unique(array_column($budgets,'category')))?></div>
</div>
<div class="budget-summary-card" style="border-top:4px solid #f5576c;background:linear-gradient(135deg,rgba(245,87,108,.08),rgba(245,87,108,.02))">
<div class="budget-summary-label">Total Spent</div>
<div class="budget-summary-value"><span class="amount" data-currency="USD" data-amount="<?=$totalSpent?>"><?=number_format($totalSpent,2)?></span></div>
</div>
</div>

<?php if (empty($budgets)): ?>
<div class="card" style="text-align:center;padding:48px 24px">
<div style="font-size:3rem;margin-bottom:16px;opacity:.3"></div>
<h3>No budgets yet</h3>
<p class="muted">Create your first budget to start tracking spending</p>
</div>
<?php else: ?>
<div class="budgets-table-wrapper">
<table class="budgets-table">
<thead>
<tr>
<th style="width:50px">ID</th>
<th>Category</th>
<th style="width:140px">Budget</th>
<th style="width:140px">Spent</th>
<th style="width:200px">Progress</th>
<th style="width:120px">Period</th>
<th style="width:180px">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($budgets as $b): 
$percentage = $b['percentage'];
$spent = $b['spent'];
$remaining = $b['remaining'];
$isOverBudget = $percentage > 100;
$isNearLimit = $percentage >= floatval($b['alert_threshold'] ?? 80);
$progressColor = $isOverBudget ? '#f5576c' : ($isNearLimit ? '#ffa500' : '#2ed573');
?>
<tr>
<td>#<?=$b['id']?></td>
<td>
<div><strong><?=htmlspecialchars($b['category'])?></strong></div>
<?php if ($isOverBudget): ?>
<div style="font-size:.75rem;color:#f5576c;margin-top:4px">⚠️ Over budget</div>
<?php elseif ($isNearLimit): ?>
<div style="font-size:.75rem;color:#ffa500;margin-top:4px">⚡ Near limit</div>
<?php endif; ?>
</td>
<td><span class="amount" data-currency="USD" data-amount="<?=$b['amount']?>"><?=number_format($b['amount'],2)?></span></td>
<td>
<span class="amount" data-currency="USD" data-amount="<?=$spent?>" style="color:<?=$progressColor?>;font-weight:600"><?=number_format($spent,2)?></span>
</td>
<td>
<div style="margin-bottom:6px;font-size:.85rem;color:<?=$progressColor?>;font-weight:600"><?=number_format($percentage,1)?>%</div>
<div style="width:100%;height:8px;background:rgba(var(--card-text-rgb),.1);border-radius:4px;overflow:hidden">
<div style="width:<?=min(100,$percentage)?>%;height:100%;background:<?=$progressColor?>;transition:width .3s"></div>
</div>
<div style="font-size:.75rem;color:var(--muted);margin-top:4px">Remaining: $<?=number_format($remaining,2)?></div>
</td>
<td><span class="period-badge <?=htmlspecialchars($b['period'])?>"><?=ucfirst($b['period'])?></span></td>
<td>
<div style="display:flex;gap:6px;flex-wrap:wrap">
<button class="button ghost small js-edit-budget" data-budget='<?=json_encode($b)?>'> Edit</button>
<a href="/MY CASH/pages/budgets.php?delete=<?=$b['id']?>" onclick="return confirm('Delete this budget?')" class="button danger small"> Delete</a>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<script>
document.getElementById('show-add-budget').addEventListener('click', function() {
  var html = '<div style="padding:24px"><h3 style="margin-bottom:24px"> Add New Budget</h3>' +
    '<form method="POST" style="display:flex;flex-direction:column;gap:16px">' +
    '<div><label>Category</label><input type="text" name="category" placeholder="Food, Transportation, Entertainment..." required style="width:100%"></div>' +
    '<div><label>Budget Amount</label><input type="number" step="0.01" name="amount" placeholder="0.00" required style="width:100%"></div>' +
    '<div><label>Period</label><select name="period" style="width:100%">' +
    '<option value="weekly">Weekly</option>' +
    '<option value="monthly" selected>Monthly</option>' +
    '<option value="yearly">Yearly</option>' +
    '</select></div>' +
    '<div><label>Alert Threshold (%)</label><input type="number" step="1" min="0" max="100" name="alert_threshold" value="80" placeholder="80" style="width:100%">' +
    '<small style="color:var(--muted);display:block;margin-top:4px">Get notified when spending reaches this percentage</small></div>' +
    '<div style="display:flex;gap:12px;margin-top:16px">' +
    '<button type="submit" name="add_budget" class="button primary" style="flex:1">Add Budget</button>' +
    '<button type="button" class="button ghost" onclick="WCModal.close()" style="flex:1">Cancel</button>' +
    '</div></form></div>';
  window.WCModal.open(html);
});

document.querySelectorAll('.js-edit-budget').forEach(btn => {
  btn.addEventListener('click', function() {
    var budget = JSON.parse(this.dataset.budget);
    var html = '<div style="padding:24px"><h3 style="margin-bottom:24px"> Edit Budget</h3>' +
      '<form method="POST" style="display:flex;flex-direction:column;gap:16px">' +
      '<input type="hidden" name="budget_id" value="' + budget.id + '">' +
      '<div><label>Category</label><input type="text" name="category" value="' + (budget.category||'') + '" required style="width:100%"></div>' +
      '<div><label>Budget Amount</label><input type="number" step="0.01" name="amount" value="' + (budget.amount||0) + '" required style="width:100%"></div>' +
      '<div><label>Period</label><select name="period" id="edit-period" style="width:100%">' +
      '<option value="weekly">Weekly</option>' +
      '<option value="monthly">Monthly</option>' +
      '<option value="yearly">Yearly</option>' +
      '</select></div>' +
      '<div><label>Alert Threshold (%)</label><input type="number" step="1" min="0" max="100" name="alert_threshold" value="' + (budget.alert_threshold||80) + '" style="width:100%">' +
      '<small style="color:var(--muted);display:block;margin-top:4px">Get notified when spending reaches this percentage</small></div>' +
      '<div style="display:flex;gap:12px;margin-top:16px">' +
      '<button type="submit" name="edit_budget" class="button primary" style="flex:1">Save Changes</button>' +
      '<button type="button" class="button ghost" onclick="WCModal.close()" style="flex:1">Cancel</button>' +
      '</div></form></div>';
    window.WCModal.open(html);
    setTimeout(function() {
      var sel = document.getElementById('edit-period');
      if (sel) sel.value = budget.period || 'monthly';
    }, 50);
  });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

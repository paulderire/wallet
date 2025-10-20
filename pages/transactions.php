<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../includes/db.php';
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_transaction'])) {
  $account_id = intval($_POST['account_id']); $type = $_POST['type'] === 'withdraw' ? 'withdraw' : 'deposit';
  $amount = floatval($_POST['amount'] ?? 0); $notes = trim($_POST['notes'] ?? '');
  if ($amount > 0) {
    $verify = $conn->prepare('SELECT id FROM accounts WHERE id = ? AND user_id = ?');
    $verify->execute([$account_id, $user_id]); 
    if ($verify->fetch()) {
      $conn->beginTransaction();
      $ins = $conn->prepare('INSERT INTO transactions (account_id, type, amount, notes) VALUES (?, ?, ?, ?)');
      $ins->execute([$account_id, $type, $amount, $notes]);
      $hasBalance = false; $c = $conn->query("SHOW COLUMNS FROM accounts LIKE 'balance'");
      if ($c && $c->fetch()) $hasBalance = true;
      if ($hasBalance) {
        if ($type === 'deposit') { $upd = $conn->prepare('UPDATE accounts SET balance = balance + ? WHERE id = ?'); $upd->execute([$amount, $account_id]); }
        else { $upd = $conn->prepare('UPDATE accounts SET balance = balance - ? WHERE id = ?'); $upd->execute([$amount, $account_id]); }
      }
      $conn->commit(); header('Location: /MY CASH/pages/transactions.php'); exit;
    }
  }
}

if (isset($_GET['delete'])) {
  $tid = intval($_GET['delete']);
  $verify = $conn->prepare('SELECT t.id, t.account_id, t.type, t.amount FROM transactions t JOIN accounts a ON t.account_id = a.id WHERE t.id = ? AND a.user_id = ?');
  $verify->execute([$tid, $user_id]); $tx = $verify->fetch(PDO::FETCH_ASSOC);
  if ($tx) {
    $conn->beginTransaction();
    $del = $conn->prepare('DELETE FROM transactions WHERE id = ?'); $del->execute([$tid]);
    $hasBalance = false; $c = $conn->query("SHOW COLUMNS FROM accounts LIKE 'balance'");
    if ($c && $c->fetch()) $hasBalance = true;
    if ($hasBalance) {
      if ($tx['type'] === 'deposit') { $upd = $conn->prepare('UPDATE accounts SET balance = balance - ? WHERE id = ?'); $upd->execute([$tx['amount'], $tx['account_id']]); }
      else { $upd = $conn->prepare('UPDATE accounts SET balance = balance + ? WHERE id = ?'); $upd->execute([$tx['amount'], $tx['account_id']]); }
    }
    $conn->commit();
  }
  header('Location: /MY CASH/pages/transactions.php'); exit;
}

$accounts = []; try { $stmt = $conn->prepare("SELECT id, name FROM accounts WHERE user_id = ? ORDER BY name"); $stmt->execute([$user_id]); $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) { $accounts = []; }

$filterAccount = isset($_GET['account']) ? intval($_GET['account']) : null;
$filterType = isset($_GET['type']) && in_array($_GET['type'], ['deposit','withdraw']) ? $_GET['type'] : null;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

try {
  $sql = "SELECT t.*, a.name as account_name FROM transactions t JOIN accounts a ON t.account_id = a.id WHERE a.user_id = ?";
  $params = [$user_id];
  if ($filterAccount) { $sql .= " AND t.account_id = ?"; $params[] = $filterAccount; }
  if ($filterType) { $sql .= " AND t.type = ?"; $params[] = $filterType; }
  $sql .= " ORDER BY t.id DESC LIMIT ?"; $params[] = $limit;
  $stmt = $conn->prepare($sql); $stmt->execute($params);
  $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $transactions = []; }
include __DIR__ . '/../includes/header.php';
?>

<style>
.transactions-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:16px}
.transactions-filters{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;padding:16px;background:var(--card-bg);border:1px solid var(--border-weak);border-radius:8px}
.transactions-filters select{padding:8px 12px;border:1px solid var(--border-weak);border-radius:6px;background:var(--card-bg);color:var(--card-text)}
.transactions-table{width:100%;border-collapse:collapse;background:var(--card-bg);border-radius:8px;overflow:hidden}
.transactions-table thead{background:rgba(var(--card-text-rgb),.05)}
.transactions-table th{padding:16px;text-align:left;font-weight:700;color:var(--card-text);border-bottom:2px solid var(--border-weak)}
.transactions-table td{padding:14px 16px;border-bottom:1px solid var(--border-weak);color:var(--card-text)}
.transactions-table tbody tr:hover{background:rgba(var(--card-text-rgb),.03)}
.tx-type-badge{display:inline-block;padding:4px 10px;border-radius:12px;font-size:.8rem;font-weight:600;text-transform:uppercase}
.tx-type-badge.deposit{background:rgba(46,213,115,.15);color:#2ed573;border:1px solid rgba(46,213,115,.3)}
.tx-type-badge.withdraw{background:rgba(245,87,108,.15);color:#f5576c;border:1px solid rgba(245,87,108,.3)}
.tx-amount{font-weight:700;font-size:1.05rem}
.tx-amount.deposit{color:#2ed573}
.tx-amount.withdraw{color:#f5576c}
@media (max-width:768px){.transactions-table{font-size:.9rem}.transactions-table th,.transactions-table td{padding:10px 8px}}
</style>

<div class="transactions-header">
<div><h2> Transactions</h2><p class="muted">Quick deposit & withdraw</p></div>
<button id="show-add-transaction" class="button primary">+ Add Transaction</button>
</div>

<div class="transactions-filters">
<div><label style="display:block;margin-bottom:4px;font-size:.85rem;color:var(--muted)">Filter by Account</label>
<select onchange="updateFilters(this.value, '<?=$filterType?>')">
<option value="">All Accounts</option>
<?php foreach($accounts as $acc): ?>
<option value="<?=$acc['id']?>" <?=($filterAccount==$acc['id'])?'selected':''?>><?=htmlspecialchars($acc['name'])?></option>
<?php endforeach; ?>
</select>
</div>
<div><label style="display:block;margin-bottom:4px;font-size:.85rem;color:var(--muted)">Filter by Type</label>
<select onchange="updateFilters('<?=$filterAccount?>', this.value)">
<option value="">All Types</option>
<option value="deposit" <?=($filterType==='deposit')?'selected':''?>>Deposits</option>
<option value="withdraw" <?=($filterType==='withdraw')?'selected':''?>>Withdrawals</option>
</select>
</div>
<div><label style="display:block;margin-bottom:4px;font-size:.85rem;color:var(--muted)">Results</label>
<select onchange="updateLimit(this.value)">
<option value="50" <?=($limit===50)?'selected':''?>>50</option>
<option value="100" <?=($limit===100)?'selected':''?>>100</option>
<option value="200" <?=($limit===200)?'selected':''?>>200</option>
</select>
</div>
</div>

<?php if (empty($transactions)): ?>
<div class="card" style="text-align:center;padding:48px 24px">
<div style="font-size:3rem;margin-bottom:16px;opacity:.3"></div>
<h3>No transactions yet</h3>
<p class="muted">Record your first deposit or withdrawal</p>
</div>
<?php else: ?>
<div style="overflow-x:auto">
<table class="transactions-table">
<thead>
<tr><th style="width:100px">ID</th><th>Account</th><th style="width:120px">Type</th><th style="width:140px">Amount</th><th>Notes</th><th style="width:160px">Date</th><th style="width:90px">Actions</th></tr>
</thead>
<tbody>
<?php foreach($transactions as $t): 
$type = $t['type'] ?? 'deposit'; $amount = floatval($t['amount'] ?? 0);
?>
<tr>
<td>#<?=$t['id']?></td>
<td><?=htmlspecialchars($t['account_name'] ?? 'Unknown')?></td>
<td><span class="tx-type-badge <?=$type?>"><?=ucfirst($type)?></span></td>
<td><span class="tx-amount <?=$type?>"><?=($type==='deposit'?'+':'-')?><span class="amount" data-currency="USD" data-amount="<?=$amount?>"><?=number_format($amount,2)?></span></span></td>
<td><?=htmlspecialchars($t['notes'] ?? '')?></td>
<td><?=isset($t['created_at']) ? date('M j, Y g:i A', strtotime($t['created_at'])) : 'N/A'?></td>
<td><a href="/MY CASH/pages/transactions.php?delete=<?=$t['id']?>" onclick="return confirm('Delete this transaction?')" class="button danger small">Delete</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<script>
document.getElementById('show-add-transaction').addEventListener('click', function() {
  var html = '<div style="padding:24px"><h3 style="margin-bottom:24px"> Add Transaction</h3>' +
    '<form method="POST" style="display:flex;flex-direction:column;gap:16px">' +
    '<div><label>Account</label><select name="account_id" required style="width:100%">';
  <?php foreach($accounts as $acc): ?>
  html += '<option value="<?=$acc['id']?>"><?=htmlspecialchars(addslashes($acc['name']))?></option>';
  <?php endforeach; ?>
  html += '</select></div>' +
    '<div><label>Type</label><select name="type" required style="width:100%">' +
    '<option value="deposit">Deposit (+)</option>' +
    '<option value="withdraw">Withdrawal (-)</option>' +
    '</select></div>' +
    '<div><label>Amount</label><input type="number" step="0.01" name="amount" required style="width:100%"></div>' +
    '<div><label>Notes (optional)</label><input type="text" name="notes" placeholder="Salary, groceries, etc." style="width:100%"></div>' +
    '<div style="display:flex;gap:12px;margin-top:16px">' +
    '<button type="submit" name="add_transaction" class="button primary" style="flex:1">Add Transaction</button>' +
    '<button type="button" class="button ghost" onclick="WCModal.close()" style="flex:1">Cancel</button>' +
    '</div></form></div>';
  window.WCModal.open(html);
});

function updateFilters(account, type) {
  var url = '/MY CASH/pages/transactions.php?';
  if (account) url += 'account=' + account + '&';
  if (type) url += 'type=' + type + '&';
  url += 'limit=<?=$limit?>';
  window.location.href = url;
}

function updateLimit(limit) {
  var url = '/MY CASH/pages/transactions.php?';
  <?php if($filterAccount): ?>url += 'account=<?=$filterAccount?>&';<?php endif; ?>
  <?php if($filterType): ?>url += 'type=<?=$filterType?>&';<?php endif; ?>
  url += 'limit=' + limit;
  window.location.href = url;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

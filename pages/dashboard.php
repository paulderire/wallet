<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }
include __DIR__ . '/../includes/db.php';
$user_id = $_SESSION['user_id'];

$accounts = []; $total = 0.0;
try {
  $stmt = $conn->prepare("SELECT id,name,type,currency,COALESCE(balance,0) AS balance FROM accounts WHERE user_id=? ORDER BY id DESC");
  $stmt->execute([$user_id]); $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach($accounts as $a) { $total += floatval($a['balance'] ?? 0); }
} catch (Exception $e) {}

$loans = []; $loanTotal = 0.0; $loanCount = 0;
try {
  $chk = $conn->query("SHOW TABLES LIKE 'loan_payments'");
  $paymentsTableExists = $chk && $chk->fetch();
  if ($paymentsTableExists) {
    $stmt = $conn->prepare("SELECT l.*, COALESCE(p.total_paid,0) AS paid_amount FROM loans l LEFT JOIN (SELECT loan_id, SUM(amount) AS total_paid FROM loan_payments GROUP BY loan_id) p ON l.id = p.loan_id WHERE l.user_id=? AND l.repaid=0 ORDER BY l.due_date ASC LIMIT 5");
    $stmt->execute([$user_id]); $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $stmt = $conn->prepare("SELECT * FROM loans WHERE user_id=? AND repaid=0 ORDER BY due_date ASC LIMIT 5");
    $stmt->execute([$user_id]); $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) { $r['paid_amount'] = 0.0; $loans[] = $r; }
  }
  foreach($loans as $ln) {
    $amt = floatval($ln['amount'] ?? 0); $paid = floatval($ln['paid_amount'] ?? 0);
    $loanTotal += max(0, $amt - $paid); $loanCount++;
  }
} catch (Exception $e) {}

$goals = []; $goalTotal = 0.0; $goalSaved = 0.0; $goalCount = 0;
try {
  $stmt = $conn->prepare("SELECT * FROM goals WHERE user_id=? ORDER BY id DESC LIMIT 4");
  $stmt->execute([$user_id]); $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach($goals as $g){ $goalTotal += floatval($g['target_amount'] ?? 0); $goalSaved += floatval($g['saved_amount'] ?? 0); $goalCount++; }
} catch (Exception $e) {}

$budgets = []; $budgetTotal = 0.0;
try {
  $stmt = $conn->prepare("SELECT * FROM budgets WHERE user_id=? AND period='monthly' ORDER BY id DESC LIMIT 3");
  $stmt->execute([$user_id]); $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach($budgets as $b){ $budgetTotal += floatval($b['amount'] ?? 0); }
} catch (Exception $e) {}

$transactions = [];
try {
  $cols = $conn->query("SHOW COLUMNS FROM transactions")->fetchAll(PDO::FETCH_COLUMN,0);
  $dateCol = in_array('created_at', $cols) ? 'created_at' : 'id';
  $stmt = $conn->prepare("SELECT t.*, a.name AS account_name FROM transactions t JOIN accounts a ON t.account_id=a.id WHERE a.user_id=? ORDER BY t.$dateCol DESC LIMIT 8");
  $stmt->execute([$user_id]); $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$netWorth =  ($total - $loanTotal);

// Calculate total money needed to achieve all goals and cover everything
$goalsRemaining = max(0, $goalTotal - $goalSaved); // Amount still needed for goals
$totalMoneyNeeded = $goalsRemaining + $loanTotal; // Goals + outstanding loans

include __DIR__ . '/../includes/header.php';
?>

<style>
.dashboard-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;flex-wrap:wrap;gap:16px}
.dashboard-welcome{font-size:1.8rem;font-weight:700;color:var(--card-text);margin-bottom:8px}
.dashboard-subtitle{color:var(--muted);font-size:1rem}
.quick-actions{display:flex;gap:12px;flex-wrap:wrap}
.featured-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:20px;margin-bottom:32px}
.featured-card{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:20px;padding:40px;box-shadow:0 8px 32px rgba(var(--card-text-rgb),.12);transition:all .3s;position:relative;overflow:hidden}
.featured-card::before{content:'';position:absolute;top:0;left:0;right:0;height:6px}
.featured-card:hover{transform:translateY(-8px);box-shadow:0 20px 50px rgba(var(--card-text-rgb),.3)}
.featured-card.net-worth::before{background:linear-gradient(90deg,#667eea,#764ba2)}
.featured-card.target-income::before{background:linear-gradient(90deg,#ffa500,#ff6348)}
.featured-icon{font-size:3.5rem;opacity:.15;position:absolute;right:30px;top:30px}
.featured-label{font-size:1rem;color:var(--muted);text-transform:uppercase;font-weight:700;margin-bottom:16px;letter-spacing:1px}
.featured-value{font-size:3.2rem;font-weight:900;color:var(--card-text);margin-bottom:12px;line-height:1}
.featured-sub{font-size:1.05rem;color:var(--muted);margin-bottom:8px}
.featured-breakdown{margin-top:16px;padding-top:16px;border-top:1px solid var(--border-weak);display:grid;gap:8px}
.breakdown-item{display:flex;justify-content:space-between;align-items:center;font-size:.9rem;color:var(--muted)}
.breakdown-item strong{color:var(--card-text);font-weight:600}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;margin-bottom:32px}
.stat-card{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:16px;padding:28px;box-shadow:var(--overlay-shadow);transition:all .3s;position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px}
.stat-card:hover{transform:translateY(-6px);box-shadow:0 16px 40px rgba(var(--card-text-rgb),.25)}
.stat-card.net-worth::before{background:linear-gradient(90deg,#667eea,#764ba2)}
.stat-card.accounts::before{background:linear-gradient(90deg,#2ed573,#11998e)}
.stat-card.loans::before{background:linear-gradient(90deg,#f5576c,#fa709a)}
.stat-card.goals::before{background:linear-gradient(90deg,#ffa500,#ff6348)}
.stat-icon{font-size:2.5rem;opacity:.2;position:absolute;right:20px;top:20px}
.stat-label{font-size:.9rem;color:var(--muted);text-transform:uppercase;font-weight:600;margin-bottom:12px;letter-spacing:.5px}
.stat-value{font-size:2.4rem;font-weight:800;color:var(--card-text);margin-bottom:8px;line-height:1}
.stat-sub{font-size:.95rem;color:var(--muted);display:flex;align-items:center;gap:6px}
.dashboard-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:24px;margin-bottom:24px}
.dashboard-section{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;padding:24px;box-shadow:var(--overlay-shadow)}
.section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.section-title{font-size:1.3rem;font-weight:700;color:var(--card-text);display:flex;align-items:center;gap:10px}
.mini-card{background:rgba(var(--card-text-rgb),.03);border:1px solid var(--border-weak);border-radius:8px;padding:16px;margin-bottom:12px;transition:all .2s}
.mini-card:hover{background:rgba(var(--card-text-rgb),.06);transform:translateX(4px)}
.mini-card:last-child{margin-bottom:0}
.mini-card-title{font-weight:600;color:var(--card-text);margin-bottom:6px}
.mini-card-meta{font-size:.85rem;color:var(--muted);display:flex;justify-content:space-between;align-items:center}
.progress-mini{width:100%;height:6px;background:rgba(var(--card-text-rgb),.1);border-radius:3px;overflow:hidden;margin:8px 0 4px}
.progress-mini-fill{height:100%;border-radius:3px;transition:width .3s}
.tx-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border-weak)}
.tx-row:last-child{border-bottom:none}
.tx-info{flex:1}
.tx-account{font-weight:600;color:var(--card-text);margin-bottom:4px}
.tx-note{font-size:.85rem;color:var(--muted)}
.tx-amount{font-weight:700;font-size:1.1rem}
.tx-amount.deposit{color:#2ed573}
.tx-amount.withdraw{color:#f5576c}
@media (max-width:768px){.stats-row{grid-template-columns:1fr}.dashboard-grid{grid-template-columns:1fr}.featured-stats{grid-template-columns:1fr}}
</style>

<div class="dashboard-header">
<div>
<div class="dashboard-welcome">👋 Welcome back!</div>
<div class="dashboard-subtitle">Here's your financial overview</div>
</div>
<div class="quick-actions">
<a href="/MY CASH/pages/transactions.php" class="button primary">+ Transaction</a>
<a href="/MY CASH/pages/accounts.php" class="button ghost">Accounts</a>
</div>
</div>

<div class="featured-stats">
<!-- Net Worth Card - Shows total wealth -->
<div class="featured-card net-worth">
<div class="featured-icon">💎</div>
<div class="featured-label">Net Worth</div>
<div class="featured-value" style="<?=$netWorth < 0 ? 'color:#f5576c' : 'color:#2ed573'?>">
  <span class="amount" data-currency="RWF" data-amount="<?=$netWorth?>"><?=number_format($netWorth,0)?></span>
</div>
<div class="featured-sub">Your current financial position (Assets - Liabilities)</div>
<div class="featured-breakdown">
<div class="breakdown-item">
<span>🏦 Total Accounts</span>
<strong style="color:#2ed573"><span class="amount" data-currency="RWF" data-amount="<?=$total?>"><?=number_format($total,0)?></span></strong>
</div>
<div class="breakdown-item">
<span>💳 Outstanding Loans</span>
<strong style="color:#f5576c"><span class="amount" data-currency="RWF" data-amount="<?=$loanTotal?>"><?=number_format($loanTotal,0)?></span></strong>
</div>
<div class="breakdown-item" style="border-top:1px solid var(--border-weak);padding-top:8px;margin-top:8px">
<span>💎 Net Position</span>
<strong style="<?=$netWorth < 0 ? 'color:#f5576c' : 'color:#2ed573'?>;font-size:1.1rem">
  <span class="amount" data-currency="RWF" data-amount="<?=$netWorth?>"><?=number_format($netWorth,0)?></span>
</strong>
</div>
</div>
</div>

<!-- Total Money Needed Card -->
<div class="featured-card target-income">
<div class="featured-icon">🎯</div>
<div class="featured-label">Total Money Needed</div>
<div class="featured-value"><span class="amount" data-currency="RWF" data-amount="<?=$totalMoneyNeeded?>"><?=number_format($totalMoneyNeeded,0)?></span></div>
<div class="featured-sub">Total amount to achieve all goals & clear all debts</div>
<div class="featured-breakdown">
<div class="breakdown-item">
<span>🎯 Goals Remaining</span>
<strong><span class="amount" data-currency="RWF" data-amount="<?=$goalsRemaining?>"><?=number_format($goalsRemaining,0)?></span></strong>
</div>
<div class="breakdown-item">
<span>💳 Outstanding Loans</span>
<strong style="color:#f5576c"><span class="amount" data-currency="RWF" data-amount="<?=$loanTotal?>"><?=number_format($loanTotal,0)?></span></strong>
</div>
<div class="breakdown-item" style="border-top:1px solid var(--border-weak);padding-top:8px;margin-top:8px">
<span>✨ Financial Freedom Target</span>
<strong style="color:#2ed573;font-size:1.1rem"><span class="amount" data-currency="RWF" data-amount="<?=$totalMoneyNeeded?>"><?=number_format($totalMoneyNeeded,0)?></span></strong>
</div>
</div>
</div>
</div>

<div class="stats-row">
<div class="stat-card accounts">
<div class="stat-icon">🏦</div>
<div class="stat-label">Accounts</div>
<div class="stat-value"><?=count($accounts)?></div>
<div class="stat-sub">Balance: <span class="amount" data-currency="RWF" data-amount="<?=$total?>"><?=number_format($total,0)?></span></div>
</div>
<div class="stat-card loans">
<div class="stat-icon">💳</div>
<div class="stat-label">Active Loans</div>
<div class="stat-value"><?=$loanCount?></div>
<div class="stat-sub">Outstanding: <span class="amount" data-currency="RWF" data-amount="<?=$loanTotal?>"><?=number_format($loanTotal,0)?></span></div>
</div>
<div class="stat-card goals">
<div class="stat-icon">🎯</div>
<div class="stat-label">Goals Progress</div>
<div class="stat-value"><?=$goalCount?></div>
<div class="stat-sub">
<?php if($goalTotal > 0): ?>
<?=round(($goalSaved/$goalTotal)*100)?>% achieved
<?php else: ?>No goals set<?php endif; ?>
</div>
</div>
</div>

<div class="dashboard-grid">
<div class="dashboard-section">
<div class="section-header">
<div class="section-title"> Active Goals</div>
<a href="/MY CASH/pages/goals.php" class="button ghost small">View All</a>
</div>
<?php if(empty($goals)): ?>
<p class="muted" style="text-align:center;padding:20px 0">No goals yet</p>
<?php else: foreach($goals as $g):
$saved = floatval($g['saved_amount'] ?? 0); $target = floatval($g['target_amount'] ?? 0);
$pct = $target > 0 ? min(100, round(($saved/$target)*100,1)) : 0;
$progressClass = $pct >= 75 ? '#2ed573' : ($pct >= 40 ? '#ffa500' : '#f5576c');
?>
<div class="mini-card">
<div class="mini-card-title"><?=htmlspecialchars($g['title'])?></div>
<div class="progress-mini"><div class="progress-mini-fill" style="width:<?=$pct?>%;background:<?=$progressClass?>"></div></div>
<div class="mini-card-meta">
<span><span class="amount" data-currency="RWF" data-amount="<?=$saved?>"><?=number_format($saved,0)?></span> / <span class="amount" data-currency="RWF" data-amount="<?=$target?>"><?=number_format($target,0)?></span></span>
<span><?=$pct?>%</span>
</div>
</div>
<?php endforeach; endif; ?>
</div>

<div class="dashboard-section">
<div class="section-header">
<div class="section-title"> Active Loans</div>
<a href="/MY CASH/pages/loans.php" class="button ghost small">View All</a>
</div>
<?php if(empty($loans)): ?>
<p class="muted" style="text-align:center;padding:20px 0">No active loans</p>
<?php else: foreach($loans as $l):
$amt = floatval($l['amount'] ?? 0); $paid = floatval($l['paid_amount'] ?? 0);
$remaining = max(0, $amt - $paid); $pct = $amt > 0 ? min(100, round(($paid/$amt)*100,1)) : 0;
$progressClass = $pct >= 75 ? '#2ed573' : ($pct >= 40 ? '#ffa500' : '#f5576c');
?>
<div class="mini-card">
<div class="mini-card-title"><?=htmlspecialchars($l['lender'])?></div>
<div class="progress-mini"><div class="progress-mini-fill" style="width:<?=$pct?>%;background:<?=$progressClass?>"></div></div>
<div class="mini-card-meta">
<span>Due: <?=htmlspecialchars($l['due_date'])?></span>
<span><span class="amount" data-currency="RWF" data-amount="<?=$remaining?>"><?=number_format($remaining,0)?></span> left</span>
</div>
</div>
<?php endforeach; endif; ?>
</div>
</div>

<div class="dashboard-grid">
<div class="dashboard-section">
<div class="section-header">
<div class="section-title"> Monthly Budgets</div>
<a href="/MY CASH/pages/budgets.php" class="button ghost small">View All</a>
</div>
<?php if(empty($budgets)): ?>
<p class="muted" style="text-align:center;padding:20px 0">No budgets set</p>
<?php else: foreach($budgets as $b): ?>
<div class="mini-card">
<div class="mini-card-title"><?=htmlspecialchars($b['category'])?></div>
<div class="mini-card-meta">
<span>Alert at <?=$b['alert_threshold']?>%</span>
<span style="font-weight:700;color:#667eea"><span class="amount" data-currency="RWF" data-amount="<?=$b['amount']?>"><?=number_format($b['amount'],0)?></span></span>
</div>
</div>
<?php endforeach; endif; ?>
</div>

<div class="dashboard-section">
<div class="section-header">
<div class="section-title">📊 Recent Transactions</div>
<a href="/MY CASH/pages/transactions.php" class="button ghost small">View All</a>
</div>
<?php if(empty($transactions)): ?>
<p class="muted" style="text-align:center;padding:20px 0">No recent transactions</p>
<?php else: ?>
<table class="data-table">
<thead>
<tr>
<th>Account</th>
<th>Type</th>
<th>Notes</th>
<th style="text-align:right">Amount</th>
</tr>
</thead>
<tbody>
<?php foreach($transactions as $t): 
$type = strtolower($t['type'] ?? 'deposit');
$typeClass = $type === 'deposit' ? 'success' : 'danger';
?>
<tr>
<td><strong><?=htmlspecialchars($t['account_name'] ?? 'Unknown')?></strong></td>
<td><span class="badge <?=$typeClass?>"><?=ucfirst($type)?></span></td>
<td class="muted"><?=htmlspecialchars($t['notes'] ?? 'No notes')?></td>
<td style="text-align:right;font-weight:700;color:<?=$type==='deposit'?'#2ed573':'#f5576c'?>">
<?=($type==='deposit'?'+':'-')?><span class="amount" data-currency="RWF" data-amount="<?=$t['amount']?>"><?=number_format($t['amount'],0)?></span>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>




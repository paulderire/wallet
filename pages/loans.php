<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../includes/db.php';
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }
$user_id = $_SESSION['user_id'];

$tableMissing = false;
try { $chk = $conn->query("SHOW TABLES LIKE 'loans'"); $tableMissing = !$chk || !$chk->fetch(); } catch (Exception $e) { $tableMissing = true; }

$paymentsTableMissing = false;
try { $chk2 = $conn->query("SHOW TABLES LIKE 'loan_payments'"); $paymentsTableMissing = !$chk2 || !$chk2->fetch(); } catch (Exception $e) { $paymentsTableMissing = true; }

if (isset($_POST['add_loan']) && !$tableMissing){
  $lender = trim($_POST['lender']); $amount = floatval($_POST['amount'] ?? 0); $interest = floatval($_POST['interest'] ?? 0); $due_date = $_POST['due_date'] ?: null;
  $stmt = $conn->prepare("INSERT INTO loans (user_id,lender,amount,interest,due_date,created_at) VALUES (?,?,?,?,?,NOW())");
  $stmt->execute([$user_id,$lender,$amount,$interest,$due_date]);
  header('Location: /MY CASH/pages/loans.php'); exit;
}

if (isset($_POST['edit_loan']) && !$tableMissing){
  $loan_id = intval($_POST['loan_id']); $lender = trim($_POST['lender']); $amount = floatval($_POST['amount'] ?? 0); $interest = floatval($_POST['interest'] ?? 0); $due_date = $_POST['due_date'] ?: null;
  $stmt = $conn->prepare("UPDATE loans SET lender=?,amount=?,interest=?,due_date=? WHERE id=? AND user_id=?");
  $stmt->execute([$lender,$amount,$interest,$due_date,$loan_id,$user_id]);
  if (!$paymentsTableMissing) {
    $s = $conn->prepare("SELECT SUM(amount) as total_paid FROM loan_payments WHERE loan_id=? AND user_id=?");
    $s->execute([$loan_id,$user_id]); $row = $s->fetch(PDO::FETCH_ASSOC); $totalPaid = floatval($row['total_paid'] ?? 0); $repaid = $totalPaid >= $amount ? 1 : 0;
    $u = $conn->prepare("UPDATE loans SET repaid=? WHERE id=? AND user_id=?"); $u->execute([$repaid,$loan_id,$user_id]);
  }
  header('Location: /MY CASH/pages/loans.php'); exit;
}

if (isset($_GET['mark_repaid'])){ $lr = intval($_GET['mark_repaid']); $u = $conn->prepare("UPDATE loans SET repaid=1 WHERE id=? AND user_id=?"); $u->execute([$lr,$user_id]); header('Location: /MY CASH/pages/loans.php'); exit; }

if (isset($_GET['pay_remaining']) && !$tableMissing && !$paymentsTableMissing){
  $pid = intval($_GET['pay_remaining']); $q = $conn->prepare("SELECT amount FROM loans WHERE id=? AND user_id=?"); $q->execute([$pid,$user_id]); $loan = $q->fetch(PDO::FETCH_ASSOC);
  if ($loan) {
    $loanAmount = floatval($loan['amount']); $s = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total_paid FROM loan_payments WHERE loan_id=? AND user_id=?");
    $s->execute([$pid,$user_id]); $r = $s->fetch(PDO::FETCH_ASSOC); $paid = floatval($r['total_paid'] ?? 0); $remaining = max(0, $loanAmount - $paid);
    if ($remaining > 0) {
      $ins = $conn->prepare("INSERT INTO loan_payments (loan_id,user_id,amount,payment_date,created_at) VALUES (?,?,?,NOW(),NOW())");
      $ins->execute([$pid,$user_id,$remaining]); $u = $conn->prepare("UPDATE loans SET repaid=1 WHERE id=? AND user_id=?"); $u->execute([$pid,$user_id]);
    }
  }
  header('Location: /MY CASH/pages/loans.php'); exit;
}

if (isset($_POST['set_paid']) && !$tableMissing && !$paymentsTableMissing){
  $loan_id = intval($_POST['loan_id']); $new_paid = floatval($_POST['set_paid_amount'] ?? 0); $pdate = $_POST['set_paid_date'] ?: date('Y-m-d');
  $s = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total_paid FROM loan_payments WHERE loan_id=? AND user_id=?");
  $s->execute([$loan_id,$user_id]); $row = $s->fetch(PDO::FETCH_ASSOC); $currentPaid = floatval($row['total_paid'] ?? 0); $delta = $new_paid - $currentPaid;
  if (abs($delta) > 0.0001) {
    $note = 'Manual set paid adjustment'; $ins = $conn->prepare("INSERT INTO loan_payments (loan_id,user_id,amount,payment_date,note,created_at) VALUES (?,?,?,?,?,NOW())");
    $ins->execute([$loan_id,$user_id,$delta,$pdate,$note]);
  }
  $q = $conn->prepare("SELECT amount FROM loans WHERE id=? AND user_id=?"); $q->execute([$loan_id,$user_id]); $loan = $q->fetch(PDO::FETCH_ASSOC);
  if ($loan) {
    $loanAmount = floatval($loan['amount']); $s2 = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total_paid FROM loan_payments WHERE loan_id=? AND user_id=?");
    $s2->execute([$loan_id,$user_id]); $r2 = $s2->fetch(PDO::FETCH_ASSOC); $totalPaid = floatval($r2['total_paid'] ?? 0); $repaid = $totalPaid >= $loanAmount ? 1 : 0;
    $u = $conn->prepare("UPDATE loans SET repaid=? WHERE id=? AND user_id=?"); $u->execute([$repaid,$loan_id,$user_id]);
  }
  header('Location: /MY CASH/pages/loans.php'); exit;
}

if (isset($_POST['add_payment']) && !$tableMissing && !$paymentsTableMissing){
  $loan_id = intval($_POST['loan_id']); $amt = floatval($_POST['payment_amount'] ?? 0); $pdate = $_POST['payment_date'] ?: date('Y-m-d'); $note = trim($_POST['note'] ?? '');
  $stmt = $conn->prepare("INSERT INTO loan_payments (loan_id,user_id,amount,payment_date,note,created_at) VALUES (?,?,?,?,?,NOW())");
  $stmt->execute([$loan_id,$user_id,$amt,$pdate,$note]); $s = $conn->prepare("SELECT SUM(amount) as total_paid FROM loan_payments WHERE loan_id=? AND user_id=?");
  $s->execute([$loan_id,$user_id]); $row = $s->fetch(PDO::FETCH_ASSOC); $totalPaid = floatval($row['total_paid'] ?? 0); $q = $conn->prepare("SELECT amount FROM loans WHERE id=? AND user_id=?");
  $q->execute([$loan_id,$user_id]); $loan = $q->fetch(PDO::FETCH_ASSOC);
  if ($loan && $totalPaid >= floatval($loan['amount'])) { $u = $conn->prepare("UPDATE loans SET repaid=1 WHERE id=? AND user_id=?"); $u->execute([$loan_id,$user_id]); }
  header('Location: /MY CASH/pages/loans.php'); exit;
}

$loans = [];
if (!$tableMissing) {
  if (!$paymentsTableMissing) {
    $stmt = $conn->prepare("SELECT l.*, COALESCE(p.total_paid,0) AS paid_amount FROM loans l LEFT JOIN (SELECT loan_id, SUM(amount) as total_paid FROM loan_payments GROUP BY loan_id) p ON l.id=p.loan_id WHERE l.user_id=? ORDER BY l.created_at DESC");
    $stmt->execute([$user_id]); $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $stmt = $conn->prepare("SELECT * FROM loans WHERE user_id=? ORDER BY created_at DESC");
    $stmt->execute([$user_id]); $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) { $r['paid_amount'] = 0; $loans[] = $r; }
  }
}
include __DIR__ . '/../includes/header.php';
?>
<style>
.loans-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(380px,1fr));gap:16px;margin-top:16px}
.loan-card{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;padding:20px;box-shadow:var(--overlay-shadow);transition:transform .2s}
.loan-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(var(--card-text-rgb),.15)}
.loan-card.repaid{opacity:.7;background:linear-gradient(135deg,rgba(var(--card-text-rgb),.03),rgba(var(--card-text-rgb),.01))}
.loan-lender{font-size:1.25rem;font-weight:700;margin-bottom:16px}
.loan-amounts{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px;text-align:center}
.loan-amount-label{font-size:.75rem;color:var(--muted);text-transform:uppercase}
.loan-amount-value{font-size:1.1rem;font-weight:700;margin-top:4px}
.loan-progress-bar{width:100%;height:10px;background:rgba(var(--card-text-rgb),.1);border-radius:8px;overflow:hidden;margin:8px 0}
.loan-progress-fill{height:100%;border-radius:8px;transition:width .3s}
.loan-progress-fill.low{background:linear-gradient(90deg,#f093fb,#f5576c)}
.loan-progress-fill.medium{background:linear-gradient(90deg,#ffa500,#ff6348)}
.loan-progress-fill.high{background:linear-gradient(90deg,#2ed573,#17c0eb)}
.loan-meta{display:flex;justify-content:space-between;padding:12px 0;border-top:1px solid var(--border-weak);margin-bottom:12px;font-size:.85rem;color:var(--muted)}
.loan-actions{display:flex;flex-wrap:wrap;gap:8px}
.loan-actions .button{flex:1;min-width:fit-content}
@media (max-width:768px){.loans-grid{grid-template-columns:1fr}.loan-amounts{grid-template-columns:1fr}}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
<div><h2> Loans</h2><p class="muted">Track and manage your loans</p></div>
<?php if (!$tableMissing): ?><button id="show-add-loan" class="button primary">+ Add loan</button><?php endif; ?>
</div>

<?php if ($tableMissing): ?>
<div class="card"><h3> Setup Required</h3><p class="muted">Run this SQL:</p>
<pre style="background:var(--card-bg);padding:12px;border-radius:6px;color:var(--card-text);border:1px solid var(--border-weak);overflow-x:auto">CREATE TABLE loans (id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,lender VARCHAR(255),amount DECIMAL(12,2) DEFAULT 0,interest DECIMAL(5,2) DEFAULT 0,due_date DATE NULL,repaid TINYINT(1) DEFAULT 0,created_at DATETIME DEFAULT CURRENT_TIMESTAMP);</pre>
<pre style="background:var(--card-bg);padding:12px;border-radius:6px;color:var(--card-text);border:1px solid var(--border-weak);overflow-x:auto;margin-top:12px">CREATE TABLE loan_payments (id INT AUTO_INCREMENT PRIMARY KEY,loan_id INT NOT NULL,user_id INT NOT NULL,amount DECIMAL(12,2) DEFAULT 0,payment_date DATE DEFAULT CURRENT_DATE,note TEXT,created_at DATETIME DEFAULT CURRENT_TIMESTAMP);</pre>
</div>
<?php else: ?>
<?php if ($paymentsTableMissing): ?>
<div style="background:rgba(255,165,0,.1);border:1px solid rgba(255,165,0,.3);border-radius:8px;padding:16px;margin-bottom:16px"><strong> Tip:</strong> Create the <code>loan_payments</code> table to track payments.</div>
<?php endif; ?>

<?php if (empty($loans)): ?>
<div class="card" style="text-align:center;padding:48px 24px"><div style="font-size:3rem;margin-bottom:16px;opacity:.3"></div><h3>No loans yet</h3><p class="muted">Click "Add loan" to start tracking</p></div>
<?php else: ?>
<div class="loans-grid">
<?php foreach($loans as $l): 
$paid = floatval($l['paid_amount'] ?? 0); $amt = floatval($l['amount'] ?? 0); $remaining = max(0, $amt - $paid);
$percent = $amt > 0 ? min(100, round(($paid / $amt) * 100, 2)) : 0;
$progressClass = $percent >= 75 ? 'high' : ($percent >= 40 ? 'medium' : 'low');
$status = $l['repaid'] ? 'repaid' : 'outstanding';
?>
<div class="loan-card <?=$l['repaid']?'repaid':''?>">
<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px">
<div class="loan-lender"><?=htmlspecialchars($l['lender'])?></div>
<span style="padding:4px 12px;border-radius:16px;font-size:.75rem;font-weight:600;text-transform:uppercase;background:<?=$l['repaid']?'rgba(46,213,115,.15)':'rgba(255,165,0,.15)'?>;color:<?=$l['repaid']?'#2ed573':'#ffa500'?>;border:1px solid <?=$l['repaid']?'rgba(46,213,115,.3)':'rgba(255,165,0,.3)'?>"><?=$l['repaid']?' Repaid':'Outstanding'?></span>
</div>
<div class="loan-amounts">
<div><div class="loan-amount-label">Total</div><div class="loan-amount-value"><span class="amount" data-currency="USD" data-amount="<?=$amt?>"><?=number_format($amt,2)?></span></div></div>
<div><div class="loan-amount-label">Paid</div><div class="loan-amount-value" style="color:#2ed573"><span class="amount" data-currency="USD" data-amount="<?=$paid?>"><?=number_format($paid,2)?></span></div></div>
<div><div class="loan-amount-label">Remaining</div><div class="loan-amount-value" style="color:#ffa500"><span class="amount" data-currency="USD" data-amount="<?=$remaining?>"><?=number_format($remaining,2)?></span></div></div>
</div>
<?php if (!$l['repaid']): ?>
<div style="display:flex;justify-content:space-between;margin-bottom:8px"><span style="font-size:.85rem;color:var(--muted)">Progress</span><span style="font-size:.9rem;font-weight:700"><?=$percent?>%</span></div>
<div class="loan-progress-bar"><div class="loan-progress-fill <?=$progressClass?>" style="width:<?=$percent?>%"></div></div>
<?php endif; ?>
<div class="loan-meta">
<div><?php if ($l['interest'] > 0): ?>Interest: <?=number_format($l['interest'],2)?>%<?php endif; ?></div>
<div><?php if ($l['due_date']): ?>Due: <?=htmlspecialchars($l['due_date'])?><?php endif; ?></div>
</div>
<?php if (!$l['repaid']): ?>
<div class="loan-actions">
<button class="button ghost small edit-loan" data-loan='<?=json_encode($l)?>'> Edit</button>
<?php if (!$paymentsTableMissing): ?>
<button class="button ghost small add-payment" data-loan-id="<?=$l['id']?>"> Payment</button>
<button class="button ghost small update-paid" data-loan='<?=json_encode($l)?>'> Update</button>
<button class="button ghost small view-payments" data-loan-id="<?=$l['id']?>"> History</button>
<a href="/MY CASH/pages/loans.php?pay_remaining=<?=$l['id']?>" class="button primary small"> Pay off</a>
<?php else: ?>
<a href="/MY CASH/pages/loans.php?mark_repaid=<?=$l['id']?>" class="button primary small">Mark repaid</a>
<?php endif; ?>
</div>
<?php else: ?>
<div class="loan-actions"><?php if (!$paymentsTableMissing): ?><button class="button ghost small view-payments" data-loan-id="<?=$l['id']?>"> History</button><?php endif; ?></div>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
<?php if (!$tableMissing): ?>
document.getElementById('show-add-loan')?.addEventListener('click',function(){window.WCModal.open('<h3>Add loan</h3><form method="POST"><label>Lender</label><input name="lender" required><label>Amount</label><input name="amount" type="number" step="0.01" required><label>Interest %</label><input name="interest" type="number" step="0.01"><label>Due date</label><input name="due_date" type="date"><div style="margin-top:8px"><button class="button" name="add_loan">Add Loan</button></div></form>');});
document.querySelectorAll('.edit-loan').forEach(btn=>{btn.addEventListener('click',function(){const loan=JSON.parse(this.getAttribute('data-loan'));window.WCModal.open('<h3>Edit loan</h3><form method="POST"><input type="hidden" name="loan_id" value="'+loan.id+'"><label>Lender</label><input name="lender" required value="'+(loan.lender||'')+'"><label>Amount</label><input name="amount" type="number" step="0.01" required value="'+(loan.amount||0)+'"><label>Interest %</label><input name="interest" type="number" step="0.01" value="'+(loan.interest||0)+'"><label>Due date</label><input name="due_date" type="date" value="'+(loan.due_date||'')+'"><div style="margin-top:8px"><button class="button" name="edit_loan">Save</button></div></form>');});});
document.querySelectorAll('.add-payment').forEach(btn=>{btn.addEventListener('click',function(){const loanId=this.getAttribute('data-loan-id');window.WCModal.open('<h3>Add payment</h3><form method="POST"><input type="hidden" name="loan_id" value="'+loanId+'"><label>Amount</label><input name="payment_amount" type="number" step="0.01" required><label>Date</label><input name="payment_date" type="date" value="'+new Date().toISOString().slice(0,10)+'"><label>Note</label><input name="note"><div style="margin-top:8px"><button class="button" name="add_payment">Record</button></div></form>');});});
document.querySelectorAll('.update-paid').forEach(btn=>{btn.addEventListener('click',function(){const loan=JSON.parse(this.getAttribute('data-loan'));const loanAmount=parseFloat(loan.amount)||0;window.WCModal.open('<h3>Set total paid</h3><form method="POST"><input type="hidden" name="loan_id" value="'+loan.id+'"><label>Total paid</label><input id="set_paid_amount" name="set_paid_amount" type="number" step="0.01" value="'+(loan.paid_amount||0)+'" required><label>Or %</label><input id="set_paid_percent" type="number" step="0.01" min="0" max="100" value="'+(loanAmount?Math.min(100,((loan.paid_amount||0)/loanAmount*100)).toFixed(2):0)+'"><label>Date</label><input name="set_paid_date" type="date" value="'+new Date().toISOString().slice(0,10)+'"><div style="margin-top:8px"><button class="button" name="set_paid">Apply</button></div></form>');setTimeout(()=>{const amt=document.getElementById('set_paid_amount');const pct=document.getElementById('set_paid_percent');if(amt&&pct){amt.addEventListener('input',()=>pct.value=loanAmount>0?Math.min(100,(parseFloat(amt.value)||0)/loanAmount*100).toFixed(2):0);pct.addEventListener('input',()=>amt.value=((parseFloat(pct.value)||0)/100*loanAmount).toFixed(2));}},40);});});
document.querySelectorAll('.view-payments').forEach(btn=>{btn.addEventListener('click',function(){const loanId=this.getAttribute('data-loan-id');const url='/MY CASH/pages/loan_payments_ajax.php?loan_id='+loanId;window.WCModal.open('<h3>Payments</h3><p class="muted">Loading...</p>');fetch(url,{credentials:'same-origin'}).then(res=>res.json()).then(data=>{if(!data?.ok){window.WCModal.open('<h3>Payments</h3><p class="muted">'+(data?.error||'Error')+'</p>');return;}const rows=data.payments||[];if(!rows.length){window.WCModal.open('<h3>Payments</h3><p class="muted">No payments yet</p>');return;}let html='<h3>Payment History</h3><table style="width:100%;margin-top:12px"><thead><tr style="border-bottom:2px solid var(--border-weak)"><th style="text-align:left;padding:8px">Date</th><th style="text-align:right;padding:8px">Amount</th><th style="text-align:left;padding:8px">Note</th></tr></thead><tbody>';rows.forEach(r=>{const amt=parseFloat(r.amount)||0;const note=r.note||'';const pdate=r.payment_date||r.created_at||'';const color=amt>=0?'#2ed573':'#f5576c';html+='<tr style="border-bottom:1px solid var(--border-weak)"><td style="padding:8px;color:var(--muted)">'+pdate+'</td><td style="padding:8px;text-align:right;font-weight:700;color:'+color+'">'+amt.toFixed(2)+'</td><td style="padding:8px;color:var(--muted);font-size:0.9rem">'+note+'</td></tr>';});html+='</tbody></table>';window.WCModal.open(html);}).catch(()=>window.WCModal.open('<h3>Payments</h3><p class="muted">Error loading</p>'));});});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

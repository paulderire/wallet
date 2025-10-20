<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../includes/db.php';
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }
$user_id = $_SESSION['user_id'];

$netWorth = 0.0; $accountTotals = []; $totalAssets = 0.0;
try {
  // Get all accounts with balances
  $stmt = $conn->prepare("SELECT id, name, COALESCE(balance, 0) AS balance FROM accounts WHERE user_id = ? ORDER BY id DESC");
  $stmt->execute([$user_id]); 
  $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  foreach ($accounts as $a) { 
    $balance = floatval($a['balance'] ?? 0);
    $accountTotals[] = ['name' => $a['name'], 'balance' => $balance]; 
    $totalAssets += $balance;
  }
  
  $netWorth = $totalAssets; // Start with total assets
} catch (Exception $e) {
  // If there's an error, set defaults
  $netWorth = 0.0;
  $totalAssets = 0.0;
}

$totalLoans = 0.0; $loanCount = 0;
try {
  $chk = $conn->query("SHOW TABLES LIKE 'loans'");
  if ($chk && $chk->fetch()){
    // Check if loan_payments table exists
    $chkPay = $conn->query("SHOW TABLES LIKE 'loan_payments'");
    if ($chkPay && $chkPay->fetch()) {
      // Calculate remaining loan balance (amount - payments)
      $ls = $conn->prepare("SELECT COUNT(DISTINCT l.id) as cnt, COALESCE(SUM(l.amount - COALESCE(p.total_paid, 0)), 0) as total 
                            FROM loans l 
                            LEFT JOIN (SELECT loan_id, SUM(amount) as total_paid FROM loan_payments GROUP BY loan_id) p ON l.id = p.loan_id 
                            WHERE l.user_id = ? AND (l.repaid = 0 OR l.repaid IS NULL OR l.amount > COALESCE(p.total_paid, 0))");
      $ls->execute([$user_id]); 
      $r = $ls->fetch(PDO::FETCH_ASSOC);
      if($r){ 
        $loanCount = intval($r['cnt']); 
        $totalLoans = floatval($r['total']); 
        // Only subtract remaining balance
        $netWorth -= $totalLoans; 
      }
    } else {
      // No payments table, use full loan amount
      $ls = $conn->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM loans WHERE user_id=? AND (repaid=0 OR repaid IS NULL)");
      $ls->execute([$user_id]); 
      $r = $ls->fetch(PDO::FETCH_ASSOC);
      if($r){ 
        $loanCount = intval($r['cnt']); 
        $totalLoans = floatval($r['total']); 
        $netWorth -= $totalLoans; 
      }
    }
  }
} catch (Exception $e) {}

$goalCount = 0; $goalTotal = 0.0; $goalSaved = 0.0;
try {
  $gs = $conn->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(target_amount),0) as total, COALESCE(SUM(saved_amount),0) as saved FROM goals WHERE user_id=?");
  $gs->execute([$user_id]); $r = $gs->fetch(PDO::FETCH_ASSOC);
  if($r){ $goalCount = intval($r['cnt']); $goalTotal = floatval($r['total']); $goalSaved = floatval($r['saved']); }
} catch (Exception $e) {}

$budgetCount = 0; $budgetTotal = 0.0;
try {
  $bs = $conn->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM budgets WHERE user_id=? AND period='monthly'");
  $bs->execute([$user_id]); $r = $bs->fetch(PDO::FETCH_ASSOC);
  if($r){ $budgetCount = intval($r['cnt']); $budgetTotal = floatval($r['total']); }
} catch (Exception $e) {}

$months = []; $spending = []; $income = [];
$start = date('Y-m-01', strtotime('-5 months'));
for ($i=0;$i<6;$i++){
  $m = date('Y-m', strtotime($start . " +{$i} months"));
  $label = date('M Y', strtotime($start . " +{$i} months"));
  $months[] = $label; $spending[$m] = 0.0; $income[$m] = 0.0;
}
try {
  $sql = "SELECT DATE_FORMAT(COALESCE(created_at,NOW()),'%Y-%m') as ym, SUM(CASE WHEN type='withdraw' THEN amount ELSE 0 END) as spent, SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END) as earned FROM transactions t JOIN accounts a ON t.account_id=a.id WHERE a.user_id=? AND COALESCE(created_at,NOW()) >= ? GROUP BY DATE_FORMAT(COALESCE(created_at,NOW()),'%Y-%m')";
  $stmt = $conn->prepare($sql); $stmt->execute([$user_id, $start]);
  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)){
    if (isset($spending[$r['ym']])) { $spending[$r['ym']] = floatval($r['spent']); $income[$r['ym']] = floatval($r['earned']); }
  }
} catch (Exception $e) {}
$spendingVals = []; $incomeVals = [];
foreach ($months as $label){ $ym = date('Y-m', strtotime($label)); $spendingVals[] = $spending[$ym] ?? 0.0; $incomeVals[] = $income[$ym] ?? 0.0; }

$topCategories = [];
try {
  $stmt = $conn->prepare("SELECT category, COALESCE(SUM(amount),0) as total FROM budgets WHERE user_id=? GROUP BY category ORDER BY total DESC LIMIT 5");
  $stmt->execute([$user_id]); $topCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

include __DIR__ . '/../includes/header.php';
?>

<style>
.reports-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;flex-wrap:wrap;gap:16px}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-bottom:32px}
.stat-card{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;padding:24px;box-shadow:var(--overlay-shadow);transition:transform .2s}
.stat-card:hover{transform:translateY(-4px);box-shadow:0 12px 32px rgba(var(--card-text-rgb),.2)}
.stat-card.primary{border-top:4px solid #667eea;background:linear-gradient(135deg,rgba(102,126,234,.08),rgba(102,126,234,.02))}
.stat-card.success{border-top:4px solid #2ed573;background:linear-gradient(135deg,rgba(46,213,115,.08),rgba(46,213,115,.02))}
.stat-card.warning{border-top:4px solid #ffa500;background:linear-gradient(135deg,rgba(255,165,0,.08),rgba(255,165,0,.02))}
.stat-card.danger{border-top:4px solid #f5576c;background:linear-gradient(135deg,rgba(245,87,108,.08),rgba(245,87,108,.02))}
.stat-card.info{border-top:4px solid #17c0eb;background:linear-gradient(135deg,rgba(23,192,235,.08),rgba(23,192,235,.02))}
.stat-label{font-size:.85rem;color:var(--muted);text-transform:uppercase;margin-bottom:12px;font-weight:600;display:flex;align-items:center;gap:8px}
.stat-value{font-size:2rem;font-weight:700;color:var(--card-text);margin-bottom:4px}
.stat-subtext{font-size:.9rem;color:var(--muted);margin-top:8px}
.chart-container{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;padding:24px;box-shadow:var(--overlay-shadow);margin-bottom:20px;position:relative;overflow:hidden}
.chart-title{font-size:1.3rem;font-weight:700;margin-bottom:16px;color:var(--card-text)}
.chart-wrapper{position:relative;height:350px;width:100%;max-width:100%}
.categories-list{list-style:none;padding:0;margin:0}
.categories-list li{padding:12px 0;border-bottom:1px solid var(--border-weak);display:flex;justify-content:space-between;align-items:center}
.categories-list li:last-child{border-bottom:none}
.category-name{font-weight:600;color:var(--card-text)}
.category-amount{font-weight:700;color:#667eea}
@media (max-width:768px){.stats-grid{grid-template-columns:1fr}}
</style>

<div class="reports-header">
<div><h2> Financial Reports</h2><p class="muted">Comprehensive overview of your finances</p></div>
</div>

<div class="stats-grid">
<div class="stat-card primary">
<div class="stat-label">💰 Net Worth</div>
<div class="stat-value"><span class="amount" data-currency="RWF" data-amount="<?=$netWorth?>"><?=number_format($netWorth,0)?></span></div>
<div class="stat-subtext">Total assets minus liabilities</div>
</div>
<div class="stat-card success">
<div class="stat-label">🎯 Goals Progress</div>
<div class="stat-value"><?=$goalCount?></div>
<div class="stat-subtext">
<?php if($goalTotal > 0): ?>
<span class="amount" data-currency="RWF" data-amount="<?=$goalSaved?>"><?=number_format($goalSaved,0)?></span> / 
<span class="amount" data-currency="RWF" data-amount="<?=$goalTotal?>"><?=number_format($goalTotal,0)?></span>
(<?=round(($goalSaved/$goalTotal)*100)?>%)
<?php else: ?>No goals set<?php endif; ?>
</div>
</div>
<div class="stat-card warning">
<div class="stat-label">💼 Monthly Budgets</div>
<div class="stat-value"><?=$budgetCount?></div>
<div class="stat-subtext">Total: <span class="amount" data-currency="RWF" data-amount="<?=$budgetTotal?>"><?=number_format($budgetTotal,0)?></span></div>
</div>
<div class="stat-card danger">
<div class="stat-label">💳 Active Loans</div>
<div class="stat-value"><?=$loanCount?></div>
<div class="stat-subtext">Outstanding: <span class="amount" data-currency="RWF" data-amount="<?=$totalLoans?>"><?=number_format($totalLoans,0)?></span></div>
</div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:20px;margin-bottom:20px">
<div class="chart-container">
<div class="chart-title"> Income vs Spending (Last 6 Months)</div>
<div class="chart-wrapper">
<canvas id="incomeSpendingChart"></canvas>
</div>
</div>
<div class="chart-container">
<div class="chart-title"> Top Budget Categories</div>
<?php if(empty($topCategories)): ?>
<p class="muted" style="text-align:center;padding:40px 0">No budget categories yet</p>
<?php else: ?>
<ul class="categories-list">
<?php foreach($topCategories as $cat): ?>
<li>
<span class="category-name"><?=htmlspecialchars($cat['category'])?></span>
<span class="category-amount"><span class="amount" data-currency="RWF" data-amount="<?=$cat['total']?>"><?=number_format($cat['total'],0)?></span></span>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</div>
</div>

<div class="chart-container">
<div class="chart-title"> Account Balances Distribution</div>
<?php if(empty($accountTotals)): ?>
<p class="muted" style="text-align:center;padding:40px 0">No accounts found</p>
<?php else: ?>
<div class="chart-wrapper">
<canvas id="accountsChart"></canvas>
</div>
<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const months = <?=json_encode($months)?> || [];
const spending = <?=json_encode($spendingVals)?> || [];
const income = <?=json_encode($incomeVals)?> || [];
const accountNames = <?=json_encode(array_column($accountTotals,'name'))?> || [];
const accountBalances = <?=json_encode(array_column($accountTotals,'balance'))?> || [];

console.log('Chart data:', {months, spending, income, accountNames, accountBalances});

const rootStyles = getComputedStyle(document.documentElement);
const cardText = rootStyles.getPropertyValue('--card-text').trim() || '#333';
const borderWeak = rootStyles.getPropertyValue('--border-weak').trim() || '#e5e7eb';

const chartColors = {
  income: '#2ed573',
  spending: '#f5576c',
  accounts: ['#667eea','#2ed573','#ffa500','#17c0eb','#f093fb','#764ba2','#f5576c','#fa709a']
};

// Income vs Spending Chart
const ctx1 = document.getElementById('incomeSpendingChart');
if(ctx1 && months && months.length > 0){
  new Chart(ctx1, {
    type: 'bar',
    data: {
      labels: months,
      datasets: [
        {label: 'Income', data: income, backgroundColor: 'rgba(46,213,115,0.7)', borderColor: chartColors.income, borderWidth: 2, borderRadius: 4},
        {label: 'Spending', data: spending, backgroundColor: 'rgba(245,87,108,0.7)', borderColor: chartColors.spending, borderWidth: 2, borderRadius: 4}
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      aspectRatio: 2.5,
      plugins: {
        legend: {display: true, position: 'top', labels: {color: cardText, font: {size: 12}, usePointStyle: true, padding: 20}},
        tooltip: {
          mode: 'index', 
          intersect: false, 
          backgroundColor: 'rgba(0,0,0,0.8)',
          titleColor: '#fff',
          bodyColor: '#fff',
          borderColor: '#666',
          borderWidth: 1,
          callbacks: {
            label: function(ctx){
              return ctx.dataset.label + ': $' + ctx.parsed.y.toFixed(2);
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true, 
          ticks: {
            color: cardText, 
            callback: function(v){
              return '$' + (v>=1000 ? (v/1000).toFixed(1)+'k' : v.toFixed(0));
            }
          }, 
          grid: {color: borderWeak}
        },
        x: {ticks: {color: cardText}, grid: {display: false}}
      }
    }
  });
} else {
  console.error('Income vs Spending chart: Missing data or canvas element');
}

// Account Balances Doughnut Chart
const ctx2 = document.getElementById('accountsChart');
if(ctx2 && accountBalances && accountBalances.length > 0){
  const total = accountBalances.reduce((a,b)=>a+b,0);
  new Chart(ctx2, {
    type: 'doughnut',
    data: {
      labels: accountNames,
      datasets: [{
        data: accountBalances,
        backgroundColor: chartColors.accounts,
        borderColor: '#fff',
        borderWidth: 2,
        hoverOffset: 10
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      aspectRatio: 1.5,
      plugins: {
        legend: {
          display: true, 
          position: 'right', 
          labels: {
            color: cardText, 
            padding: 15, 
            font: {size: 12},
            usePointStyle: true,
            generateLabels: function(chart) {
              const data = chart.data;
              if (data.labels.length && data.datasets.length) {
                return data.labels.map((label, i) => {
                  const value = data.datasets[0].data[i];
                  const percent = ((value / total) * 100).toFixed(1);
                  return {
                    text: label + ' (' + percent + '%)',
                    fillStyle: data.datasets[0].backgroundColor[i],
                    hidden: false,
                    index: i
                  };
                });
              }
              return [];
            }
          }
        },
        tooltip: {
          backgroundColor: 'rgba(0,0,0,0.8)',
          titleColor: '#fff',
          bodyColor: '#fff',
          borderColor: '#666',
          borderWidth: 1,
          callbacks: {
            label: function(ctx){
              const value = ctx.parsed;
              const percent = ((value / total) * 100).toFixed(1);
              return ctx.label + ': $' + value.toFixed(2) + ' (' + percent + '%)';
            }
          }
        }
      }
    }
  });
} else {
  console.error('Account Balances chart: Missing data or canvas element');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

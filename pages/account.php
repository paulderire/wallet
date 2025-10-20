<?php
// ensure session before output so header() redirects work
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../includes/db.php';

// Basic validation
if (!isset($_GET['id'])) {
  // include header for consistent layout then show message
  include __DIR__ . '/../includes/header.php';
  echo '<div class="card card--center"><p class="u-center">Account not specified.</p></div>';
  include __DIR__ . '/../includes/footer.php';
  exit;
}

$accountId = intval($_GET['id']);

// fetch account
$stmt = $conn->prepare('SELECT * FROM accounts WHERE id = ?');
$stmt->execute([$accountId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$account) {
    echo '<div class="card card--center"><p class="u-center">Account not found.</p></div>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// handle transaction POST (deposit or withdraw)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tx_type']) && isset($_POST['amount'])) {
  $type = $_POST['tx_type'] === 'withdraw' ? 'withdraw' : 'deposit';
  $amount = floatval($_POST['amount']);
  // default notes to empty string to avoid INSERT errors if column is NOT NULL
  $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

  if ($amount <= 0) {
    $error = 'Amount must be greater than zero.';
  } else {
    try {
      $conn->beginTransaction();

      // discover transaction columns
      $txCols = [];
      try {
        $txColsStmt = $conn->query("SHOW COLUMNS FROM transactions");
        $txCols = $txColsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
      } catch (Exception $e) {
        // transactions table may not exist
        $txCols = [];
      }

      // build insert dynamically
      $insFields = [];
      $placeholders = [];
      $insValues = [];

      // required: account_id
      if (in_array('account_id', $txCols, true) || empty($txCols)) {
        $insFields[] = 'account_id'; $placeholders[] = '?'; $insValues[] = $accountId;
      }
      // type
      if (in_array('type', $txCols, true) || empty($txCols)) {
        $insFields[] = 'type'; $placeholders[] = '?'; $insValues[] = $type;
      }
      // amount
      if (in_array('amount', $txCols, true) || empty($txCols)) {
        $insFields[] = 'amount'; $placeholders[] = '?'; $insValues[] = $amount;
      }
      // notes (optional)
      if (in_array('notes', $txCols, true)) {
        $insFields[] = 'notes'; $placeholders[] = '?'; $insValues[] = $notes;
      }

      if (!empty($insFields)) {
        $sql = 'INSERT INTO transactions (' . implode(',', $insFields) . ') VALUES (' . implode(',', $placeholders) . ')';
        $ins = $conn->prepare($sql);
        $ins->execute($insValues);
        // add a notification about the new transaction
        try {
          $npath = __DIR__ . '/../assets/data/notifications.json';
          $n = [];
          if (file_exists($npath)) $n = json_decode(file_get_contents($npath), true) ?: [];
           $nid = uniqid('n_', true);
           $n[] = ['id'=>$nid,'title'=>($type==='deposit'?'Deposit recorded':'Withdrawal recorded'), 'body'=> "$amount USD on account {$accountId}", 'time'=>date('Y-m-d H:i'),'is_read'=>false];
          file_put_contents($npath, json_encode($n, JSON_PRETTY_PRINT));
        } catch (Exception $e) { /* ignore */ }
      }

      // update balance (if column exists)
      $hasBalance = false;
      $colStmt = $conn->query("SHOW COLUMNS FROM accounts LIKE 'balance'");
      if ($colStmt && $colStmt->fetch()) $hasBalance = true;

      if ($hasBalance) {
        if ($type === 'deposit') {
          $upd = $conn->prepare('UPDATE accounts SET balance = balance + ? WHERE id = ?');
          $upd->execute([$amount, $accountId]);
        } else {
          $upd = $conn->prepare('UPDATE accounts SET balance = balance - ? WHERE id = ?');
          $upd->execute([$amount, $accountId]);
        }
      }

      $conn->commit();
  header('Location: /MY CASH/pages/account.php?id=' . $accountId);
      exit;
    } catch (Exception $e) {
      $conn->rollBack();
      $error = 'Failed to record transaction: ' . $e->getMessage();
    }
  }
}

// refetch account to get updated balance
$stmt = $conn->prepare('SELECT * FROM accounts WHERE id = ?');
$stmt->execute([$accountId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

// compute balance from transactions if stored balance not available or to verify
$computedBalance = null;
try {
  $balStmt = $conn->prepare("SELECT
    COALESCE(SUM(CASE WHEN type='deposit' THEN amount WHEN type='withdraw' THEN -amount ELSE 0 END),0) AS computed
    FROM transactions WHERE account_id = ?");
  $balStmt->execute([$accountId]);
  $row = $balStmt->fetch(PDO::FETCH_ASSOC);
  if ($row && array_key_exists('computed', $row)) $computedBalance = floatval($row['computed']);
} catch (Exception $e) {
  $computedBalance = null;
}

// choose displayed balance: prefer stored balance if present, otherwise computed
$displayBalance = null;
if (isset($account['balance'])) {
  // if computed exists and differs, prefer computed to reflect latest transactions
  if ($computedBalance !== null) {
    $displayBalance = $computedBalance;
  } else {
    $displayBalance = floatval($account['balance']);
  }
} else {
  $displayBalance = $computedBalance;
}

// fetch transactions: prefer created_at if present, else fallback to id
$transactions = [];
try {
  $orderCol = 'id';
  try {
    $colCheck = $conn->query("SHOW COLUMNS FROM transactions LIKE 'created_at'");
    if ($colCheck && $colCheck->fetch()) $orderCol = 'created_at';
  } catch (Exception $inner) {
    // transactions table might not exist or we have no permissions; we'll fallback to id or empty
  }

  $txStmt = $conn->prepare("SELECT * FROM transactions WHERE account_id = ? ORDER BY $orderCol DESC LIMIT 200");
  $txStmt->execute([$accountId]);
  $transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  // transactions table missing or other DB error - show no transactions
  $transactions = [];
}

include __DIR__ . '/../includes/header.php';
?>

<style>
  /* Modern Account Page Styling */
  .account-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 32px;
    color: white;
    margin-bottom: 24px;
    box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
  }
  
  .account-header h1 {
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 8px 0;
  }
  
  .account-meta {
    opacity: 0.9;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
  }
  
  .account-meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
  }
  
  .balance-display {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
  }
  
  .balance-label {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-bottom: 8px;
  }
  
  .balance-amount {
    font-size: 2.5rem;
    font-weight: 800;
    display: flex;
    align-items: baseline;
    gap: 8px;
    flex-wrap: wrap;
  }
  
  .balance-currency {
    font-size: 1.2rem;
    opacity: 0.8;
  }
  
  .action-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
  }
  
  .action-card {
    background: var(--card-bg);
    border: 1.5px solid rgba(var(--card-text-rgb), 0.1);
    border-radius: 16px;
    padding: 24px;
    backdrop-filter: blur(20px);
    transition: all 0.3s ease;
  }
  
  .action-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(102, 126, 234, 0.15);
    border-color: rgba(102, 126, 234, 0.3);
  }
  
  .action-card h3 {
    font-size: 1.1rem;
    margin: 0 0 16px 0;
    color: var(--card-text);
    display: flex;
    align-items: center;
    gap: 8px;
  }
  
  .action-icon {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
  }
  
  .form-group {
    margin-bottom: 16px;
  }
  
  .form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: var(--card-text);
    font-size: 0.9rem;
  }
  
  .form-group select,
  .form-group input {
    width: 100%;
    padding: 12px 16px;
    border: 1.5px solid rgba(var(--card-text-rgb), 0.1);
    border-radius: 10px;
    background: rgba(var(--card-text-rgb), 0.02);
    color: var(--card-text);
    font-size: 0.95rem;
    transition: all 0.2s ease;
  }
  
  .form-group select:focus,
  .form-group input:focus {
    outline: none;
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.05);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }
  
  .btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    width: 100%;
  }
  
  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
  }
  
  .transactions-card {
    background: var(--card-bg);
    border: 1.5px solid rgba(var(--card-text-rgb), 0.1);
    border-radius: 16px;
    padding: 24px;
    backdrop-filter: blur(20px);
  }
  
  .transactions-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1.5px solid rgba(var(--card-text-rgb), 0.1);
  }
  
  .transactions-header h3 {
    margin: 0;
    font-size: 1.3rem;
    color: var(--card-text);
    display: flex;
    align-items: center;
    gap: 8px;
  }
  
  .transaction-table {
    width: 100%;
    border-collapse: collapse;
    overflow: hidden;
  }
  
  .transaction-table thead th {
    background: rgba(var(--card-text-rgb), 0.03);
    padding: 14px 16px;
    text-align: left;
    font-weight: 600;
    color: var(--card-text);
    font-size: 0.9rem;
    border-bottom: 2px solid rgba(var(--card-text-rgb), 0.1);
  }
  
  .transaction-table tbody td {
    padding: 16px;
    border-bottom: 1px solid rgba(var(--card-text-rgb), 0.05);
    color: var(--card-text);
  }
  
  .transaction-table tbody tr {
    transition: all 0.2s ease;
  }
  
  .transaction-table tbody tr:hover {
    background: rgba(102, 126, 234, 0.05);
  }
  
  .transaction-type {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
  }
  
  .transaction-type.deposit {
    background: rgba(34, 197, 94, 0.1);
    color: #16a34a;
  }
  
  .transaction-type.withdraw {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
  }
  
  .transaction-amount {
    font-weight: 700;
    font-size: 1rem;
  }
  
  .transaction-amount.positive {
    color: #16a34a;
  }
  
  .transaction-amount.negative {
    color: #dc2626;
  }
  
  .alert {
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
  }
  
  .alert.danger {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
    border: 1.5px solid rgba(239, 68, 68, 0.2);
  }
  
  @media (max-width: 768px) {
    .account-header {
      padding: 24px;
    }
    
    .balance-amount {
      font-size: 2rem;
    }
    
    .action-cards {
      grid-template-columns: 1fr;
    }
    
    .transaction-table {
      font-size: 0.9rem;
    }
    
    .transaction-table thead th,
    .transaction-table tbody td {
      padding: 12px 8px;
    }
  }
</style>

<div class="account-header">
  <h1><?=htmlspecialchars($account['name'])?></h1>
  <div class="account-meta">
    <div class="account-meta-item">
      <span>💳</span>
      <span>Account #<?=intval($account['id'])?></span>
    </div>
    <div class="account-meta-item">
      <span>📊</span>
      <span><?=htmlspecialchars($account['type'] ?? 'Checking')?></span>
    </div>
  </div>
  
  <div class="balance-display">
    <div class="balance-label">Current Balance</div>
    <div class="balance-amount">
      <span class="balance-currency">RWF</span>
      <span class="amount" data-currency="RWF" data-amount="<?=isset($account['balance']) ? floatval($account['balance']) : 0?>"><?=isset($account['balance']) ? number_format($account['balance'], 0) : '0'?></span>
    </div>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div class="alert danger">⚠️ <?=htmlspecialchars($error)?></div>
<?php endif; ?>

<div class="action-cards">
  <div class="action-card">
    <h3>
      <div class="action-icon">💰</div>
      Deposit Money
    </h3>
    <form method="POST">
      <input type="hidden" name="tx_type" value="deposit">
      <div class="form-group">
        <label>Amount (RWF)</label>
        <input name="amount" type="number" step="1" required placeholder="Enter amount">
      </div>
      <div class="form-group">
        <label>Notes (optional)</label>
        <input name="notes" type="text" placeholder="e.g., Salary, Gift">
      </div>
      <button class="btn-primary" type="submit">💳 Deposit</button>
    </form>
  </div>
  
  <div class="action-card">
    <h3>
      <div class="action-icon">💸</div>
      Withdraw Money
    </h3>
    <form method="POST">
      <input type="hidden" name="tx_type" value="withdraw">
      <div class="form-group">
        <label>Amount (RWF)</label>
        <input name="amount" type="number" step="1" required placeholder="Enter amount">
      </div>
      <div class="form-group">
        <label>Notes (optional)</label>
        <input name="notes" type="text" placeholder="e.g., Shopping, Bills">
      </div>
      <button class="btn-primary" type="submit">💸 Withdraw</button>
    </form>
  </div>
</div>

<div class="transactions-card">
  <div class="transactions-header">
    <h3>📜 Recent Transactions</h3>
    <span style="color: var(--muted-text); font-size: 0.9rem;"><?=count($transactions)?> total</span>
  </div>
  
  <?php if (empty($transactions)): ?>
    <div style="text-align: center; padding: 40px 20px; color: var(--muted-text);">
      <div style="font-size: 3rem; margin-bottom: 12px;">📋</div>
      <p>No transactions yet</p>
    </div>
  <?php else: ?>
    <div style="overflow-x: auto;">
      <table class="transaction-table">
        <thead>
          <tr>
            <th>Type</th>
            <th>Amount</th>
            <th>Notes</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($transactions as $t): 
              $type = isset($t['type']) ? strtolower($t['type']) : '';
              $amount = isset($t['amount']) && is_numeric($t['amount']) ? floatval($t['amount']) : 0;
              $notes = $t['notes'] ?? '';
              $date = $t['created_at'] ?? ($t['id'] ?? '');
          ?>
            <tr>
              <td>
                <span class="transaction-type <?=htmlspecialchars($type)?>">
                  <?=$type === 'deposit' ? '↓' : '↑'?>
                  <?=htmlspecialchars(ucfirst($type))?>
                </span>
              </td>
              <td>
                <span class="transaction-amount <?=$type === 'deposit' ? 'positive' : 'negative'?> amount" data-currency="RWF" data-amount="<?=$amount?>">
                  <?=$type === 'deposit' ? '+' : '-'?> RWF <?=number_format($amount, 0)?>
                </span>
              </td>
              <td><?=htmlspecialchars($notes)?></td>
              <td style="color: var(--muted-text); font-size: 0.9rem;"><?=htmlspecialchars($date)?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

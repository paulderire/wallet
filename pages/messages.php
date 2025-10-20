<?php
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$messages = [];

if ($user_id) {
  // 1. FINANCIAL INSIGHTS & SUMMARIES
  
  // Weekly spending summary
  try {
    $stmt = $conn->prepare("SELECT SUM(CASE WHEN LOWER(type)='withdraw' THEN amount ELSE 0 END) as spent, SUM(CASE WHEN LOWER(type)='deposit' THEN amount ELSE 0 END) as earned FROM transactions WHERE user_id=? AND COALESCE(date,created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $stmt->execute([$user_id]);
    $week = $stmt->fetch(PDO::FETCH_ASSOC);
    $weekSpent = (float)($week['spent'] ?? 0);
    $weekEarned = (float)($week['earned'] ?? 0);
    $weekNet = $weekEarned - $weekSpent;
    
    $messages[] = [
      'id' => 'weekly_summary',
      'from' => 'Financial Insights',
      'subject' => '📊 Weekly Financial Summary',
      'preview' => "This week: Earned $" . number_format($weekEarned, 2) . ", Spent $" . number_format($weekSpent, 2) . ", Net: " . ($weekNet >= 0 ? '+' : '') . "$" . number_format($weekNet, 2) . ". " . ($weekNet >= 0 ? "Great job saving!" : "Try to reduce expenses."),
      'time' => date('Y-m-d H:i')
    ];
  } catch (Exception $e) {}
  
  // Monthly spending summary with top categories
  try {
    $stmt = $conn->prepare("SELECT category, SUM(amount) as total FROM transactions WHERE user_id=? AND LOWER(type)='withdraw' AND COALESCE(date,created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY category ORDER BY total DESC LIMIT 3");
    $stmt->execute([$user_id]);
    $topCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->prepare("SELECT SUM(CASE WHEN LOWER(type)='withdraw' THEN amount ELSE 0 END) as spent FROM transactions WHERE user_id=? AND COALESCE(date,created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute([$user_id]);
    $monthTotal = (float)($stmt->fetch(PDO::FETCH_ASSOC)['spent'] ?? 0);
    
    $topCatText = '';
    foreach ($topCategories as $idx => $cat) {
      $topCatText .= ($idx > 0 ? ', ' : '') . ($cat['category'] ?? 'Unknown') . ' ($' . number_format($cat['total'], 2) . ')';
    }
    
    $messages[] = [
      'id' => 'monthly_summary',
      'from' => 'Financial Insights',
      'subject' => '📈 Monthly Spending Report',
      'preview' => "Last 30 days: Total spent $" . number_format($monthTotal, 2) . ". " . ($topCatText ? "Top categories: " . $topCatText : "Keep tracking your expenses!"),
      'time' => date('Y-m-d H:i')
    ];
  } catch (Exception $e) {}
  
  // 2. BUDGET ALERTS
  try {
    $stmt = $conn->prepare("SELECT * FROM budgets WHERE user_id=?");
    $stmt->execute([$user_id]);
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($budgets as $b) {
      $period = $b['period'] ?? 'monthly';
      $start = $period === 'weekly' ? date('Y-m-d', strtotime('monday this week')) : date('Y-m-01');
      
      $stmt = $conn->prepare("SELECT SUM(CASE WHEN LOWER(type)='withdraw' THEN amount ELSE 0 END) as spent FROM transactions WHERE user_id=? AND category = ? AND COALESCE(date,created_at) >= ?");
      $stmt->execute([$user_id, $b['category'], $start]);
      $spent = (float)($stmt->fetch(PDO::FETCH_ASSOC)['spent'] ?? 0);
      
      $budget_amount = (float)($b['amount'] ?? 0);
      if ($budget_amount > 0) {
        $pct = ($spent / $budget_amount) * 100;
        if ($pct >= 80) {
          $messages[] = [
            'id' => 'budget_alert_' . ($b['id'] ?? uniqid()),
            'from' => 'Budget Watch',
            'subject' => ($pct >= 100 ? '🚨 ' : '⚠️ ') . "Budget Alert: {$b['category']}",
            'preview' => "You've used " . round($pct) . "% of your " . ucfirst($period) . " {$b['category']} budget. Spent: $" . number_format($spent, 2) . " of $" . number_format($budget_amount, 2) . ". " . ($pct >= 100 ? "Budget exceeded!" : "Remaining: $" . number_format($budget_amount - $spent, 2)),
            'time' => date('Y-m-d H:i')
          ];
        }
      }
    }
  } catch (Exception $e) {}
  
  // 3. SAVINGS RECOMMENDATIONS
  try {
    $stmt = $conn->prepare("SELECT SUM(balance) as total FROM accounts WHERE user_id=?");
    $stmt->execute([$user_id]);
    $totalBalance = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $stmt = $conn->prepare("SELECT SUM(CASE WHEN LOWER(type)='withdraw' THEN amount ELSE 0 END) as avg_monthly FROM transactions WHERE user_id=? AND COALESCE(date,created_at) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
    $stmt->execute([$user_id]);
    $total90Days = (float)($stmt->fetch(PDO::FETCH_ASSOC)['avg_monthly'] ?? 0);
    $avgMonthly = $total90Days / 3;
    
    if ($avgMonthly > 0) {
      $monthsCovered = $totalBalance / $avgMonthly;
      
      if ($monthsCovered < 3) {
        $messages[] = [
          'id' => 'savings_recommendation',
          'from' => 'Financial Advisor',
          'subject' => '💡 Build Your Emergency Fund',
          'preview' => "Based on your spending ($" . number_format($avgMonthly, 2) . "/month), your current balance ($" . number_format($totalBalance, 2) . ") covers " . round($monthsCovered, 1) . " months. Financial experts recommend 3-6 months of expenses. Consider saving more!",
          'time' => date('Y-m-d H:i')
        ];
      } else {
        $messages[] = [
          'id' => 'savings_congratulations',
          'from' => 'Financial Advisor',
          'subject' => '🎉 Great Emergency Fund!',
          'preview' => "Excellent! Your savings ($" . number_format($totalBalance, 2) . ") cover " . round($monthsCovered, 1) . " months of expenses. You're financially secure!",
          'time' => date('Y-m-d H:i')
        ];
      }
    }
  } catch (Exception $e) {}
  
  // 4. GOAL PROGRESS UPDATES
  try {
    $stmt = $conn->prepare("SELECT * FROM goals WHERE user_id=? AND status != 'completed' ORDER BY deadline ASC LIMIT 3");
    $stmt->execute([$user_id]);
    $activeGoals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($activeGoals as $goal) {
      $target = (float)($goal['target_amount'] ?? 0);
      $saved = (float)($goal['saved_amount'] ?? 0);
      $percent = $target > 0 ? ($saved / $target) * 100 : 0;
      $remaining = $target - $saved;
      $deadline = $goal['deadline'] ?? null;
      
      if ($percent >= 75 && $percent < 100) {
        $messages[] = [
          'id' => 'goal_progress_' . ($goal['id'] ?? uniqid()),
          'from' => 'Goal Tracker',
          'subject' => '🎯 Goal Progress: ' . ($goal['name'] ?? 'Your Goal'),
          'preview' => "You're " . round($percent) . "% towards your goal! Saved: $" . number_format($saved, 2) . " of $" . number_format($target, 2) . ". Just $" . number_format($remaining, 2) . " more to go!" . ($deadline ? " Deadline: " . date('M d, Y', strtotime($deadline)) : ''),
          'time' => date('Y-m-d H:i')
        ];
      }
    }
  } catch (Exception $e) {}
  
  // 5. SPENDING PATTERN INSIGHTS
  try {
    $stmt = $conn->prepare("SELECT DAYNAME(COALESCE(date,created_at)) as day, COUNT(*) as txn_count, SUM(amount) as total FROM transactions WHERE user_id=? AND LOWER(type)='withdraw' AND COALESCE(date,created_at) >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) GROUP BY day ORDER BY txn_count DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $topDay = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($topDay && $topDay['txn_count'] > 5) {
      $messages[] = [
        'id' => 'spending_pattern',
        'from' => 'Spending Insights',
        'subject' => '📊 Your Spending Pattern',
        'preview' => "You tend to spend most on " . ($topDay['day'] ?? 'weekdays') . " (" . ($topDay['txn_count'] ?? 0) . " transactions, $" . number_format($topDay['total'] ?? 0, 2) . " total). Being aware of this can help you budget better!",
        'time' => date('Y-m-d H:i')
      ];
    }
  } catch (Exception $e) {}
  
  // 6. ACCOUNT PERFORMANCE
  try {
    $stmt = $conn->prepare("SELECT name, balance, (SELECT SUM(amount) FROM transactions WHERE account_id = accounts.id AND LOWER(type)='deposit' AND COALESCE(date,created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as monthly_deposits FROM accounts WHERE user_id=? ORDER BY balance DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $topAccount = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($topAccount && ($topAccount['balance'] ?? 0) > 0) {
      $messages[] = [
        'id' => 'account_performance',
        'from' => 'Account Manager',
        'subject' => '💰 Account Performance',
        'preview' => "Your '" . ($topAccount['name'] ?? 'account') . "' is performing well with a balance of $" . number_format($topAccount['balance'] ?? 0, 2) . ($topAccount['monthly_deposits'] > 0 ? ". This month's deposits: $" . number_format($topAccount['monthly_deposits'], 2) : ''),
        'time' => date('Y-m-d H:i')
      ];
    }
  } catch (Exception $e) {}
  
  // 7. TIPS & ADVICE
  $tips = [
    ['from' => 'Money Tips', 'subject' => '💡 Financial Tip: The 50/30/20 Rule', 'preview' => 'Try allocating 50% of income to needs, 30% to wants, and 20% to savings. This balanced approach helps build wealth while enjoying life!'],
    ['from' => 'Money Tips', 'subject' => '💡 Save Automatically', 'preview' => "Set up automatic transfers to savings on payday. You won't miss what you don't see! Even $50/month adds up to $600/year."],
    ['from' => 'Money Tips', 'subject' => '💡 Track Small Expenses', 'preview' => 'Small daily purchases add up! A $5 coffee daily = $1,825/year. Being mindful of small expenses can free up significant savings.'],
    ['from' => 'Money Tips', 'subject' => '💡 Review Subscriptions', 'preview' => 'Review your recurring subscriptions monthly. Cancel unused ones and you could save $50-200/month!']
  ];
  
  // Add a random tip
  $randomTip = $tips[array_rand($tips)];
  $randomTip['id'] = 'tip_' . md5($randomTip['subject']);
  $randomTip['time'] = date('Y-m-d H:i');
  $messages[] = $randomTip;
}

// Merge with saved messages from JSON
$path = __DIR__ . '/../assets/data/messages.json';
if (file_exists($path)) {
  $savedMessages = json_decode(file_get_contents($path), true) ?: [];
  $messages = array_merge($messages, $savedMessages);
}

// Sort by time (newest first)
usort($messages, function($a, $b) {
  return strtotime($b['time'] ?? 0) - strtotime($a['time'] ?? 0);
});

// Limit to 50 messages
$messages = array_slice($messages, 0, 50);
?>

<style>
  .messages-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 24px;
  }
  
  .messages-header {
    margin-bottom: 32px;
  }
  
  .messages-header h1 {
    font-size: 2rem;
    font-weight: 800;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0 0 8px 0;
  }
  
  .messages-header p {
    color: var(--muted);
    font-size: 0.95rem;
    margin: 0;
  }
  
  .messages-stats {
    display: flex;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
  }
  
  .stat-card {
    flex: 1;
    min-width: 200px;
    background: rgba(var(--card-text-rgb), 0.03);
    border: 1.5px solid rgba(var(--card-text-rgb), 0.08);
    border-radius: 14px;
    padding: 18px;
    transition: all 0.3s ease;
  }
  
  .stat-card:hover {
    border-color: #a78bfa;
    background: rgba(167, 139, 250, 0.08);
    transform: translateY(-2px);
  }
  
  .stat-card .stat-label {
    font-size: 0.85rem;
    color: var(--muted);
    margin-bottom: 4px;
  }
  
  .stat-card .stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--card-text);
  }
  
  .messages-actions {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    flex-wrap: wrap;
    align-items: center;
  }
  
  .action-btn {
    padding: 10px 18px;
    background: rgba(var(--card-text-rgb), 0.03);
    border: 1.5px solid rgba(var(--card-text-rgb), 0.1);
    border-radius: 10px;
    color: var(--card-text);
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.25s ease;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  
  .action-btn:hover {
    background: rgba(167, 139, 250, 0.12);
    border-color: #a78bfa;
    transform: translateY(-2px);
  }
  
  .action-btn.primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-color: transparent;
    color: white;
  }
  
  .action-btn.primary:hover {
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
  }
  
  .search-box {
    flex: 1;
    min-width: 250px;
    position: relative;
  }
  
  .search-box input {
    width: 100%;
    padding: 11px 18px 11px 44px;
    background: rgba(var(--card-text-rgb), 0.03);
    border: 1.5px solid rgba(var(--card-text-rgb), 0.1);
    border-radius: 10px;
    color: var(--card-text);
    font-size: 0.9rem;
    transition: all 0.3s ease;
  }
  
  .search-box input:focus {
    outline: none;
    border-color: #a78bfa;
    background: rgba(var(--card-text-rgb), 0.04);
    box-shadow: 0 0 0 4px rgba(167, 139, 250, 0.12);
  }
  
  .search-box svg {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--muted);
    opacity: 0.5;
  }
  
  .messages-list {
    list-style: none;
    padding: 0;
    margin: 0;
  }
  
  .message-item {
    background: var(--card-bg-solid);
    border: 1.5px solid rgba(var(--card-text-rgb), 0.08);
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 12px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
  }
  
  .message-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    opacity: 0;
    transition: opacity 0.3s ease;
  }
  
  .message-item.unread::before {
    opacity: 1;
  }
  
  .message-item.unread {
    background: rgba(167, 139, 250, 0.04);
    border-color: rgba(167, 139, 250, 0.2);
  }
  
  .message-item.viewed {
    opacity: 0.7;
  }
  
  .message-item.viewed .message-subject {
    opacity: 0.8;
  }
  
  .message-item.system {
    background: rgba(59, 130, 246, 0.04);
    border-color: rgba(59, 130, 246, 0.2);
  }
  
  .message-item.system::before {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
  }
  
  .message-item.budget-alert {
    background: rgba(251, 146, 60, 0.04);
    border-color: rgba(251, 146, 60, 0.2);
  }
  
  .message-item.budget-alert::before {
    background: linear-gradient(135deg, #fb923c, #f97316);
  }
  
  .message-item:hover {
    border-color: #a78bfa;
    transform: translateX(4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
  }
  
  .message-item:active {
    transform: translateX(2px);
    transition: all 0.1s ease;
  }
  
  .message-content {
    display: flex;
    gap: 16px;
    align-items: flex-start;
  }
  
  .message-icon {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
  }
  
  .message-item.system .message-icon {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
  }
  
  .message-item.budget-alert .message-icon {
    background: linear-gradient(135deg, #fb923c, #f97316);
    box-shadow: 0 4px 12px rgba(251, 146, 60, 0.3);
  }
  
  .message-icon svg {
    width: 22px;
    height: 22px;
    color: white;
  }
  
  .message-body {
    flex: 1;
  }
  
  .message-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
    gap: 12px;
  }
  
  .message-subject {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--card-text);
    margin: 0 0 4px 0;
  }
  
  .message-from {
    font-size: 0.85rem;
    color: var(--muted);
    font-weight: 600;
  }
  
  .message-time {
    font-size: 0.82rem;
    color: var(--muted);
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 4px;
  }
  
  .message-preview {
    font-size: 0.92rem;
    color: var(--muted);
    line-height: 1.5;
    margin: 0;
  }
  
  .message-badges {
    display: flex;
    gap: 8px;
    margin-top: 12px;
  }
  
  .message-badge {
    padding: 4px 10px;
    background: rgba(167, 139, 250, 0.1);
    border: 1px solid rgba(167, 139, 250, 0.2);
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    color: #a78bfa;
  }
  
  .message-badge.system {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
  }
  
  .message-badge.alert {
    background: rgba(251, 146, 60, 0.1);
    border-color: rgba(251, 146, 60, 0.2);
    color: #fb923c;
  }
  
  .message-badge.viewed {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.2);
    color: #10b981;
  }
  
  .empty-state {
    text-align: center;
    padding: 80px 20px;
  }
  
  .empty-state svg {
    width: 80px;
    height: 80px;
    color: var(--muted);
    opacity: 0.5;
    margin-bottom: 20px;
  }
  
  .empty-state h3 {
    font-size: 1.3rem;
    color: var(--card-text);
    margin: 0 0 8px 0;
  }
  
  .empty-state p {
    color: var(--muted);
    font-size: 0.95rem;
    margin: 0;
  }
  
  @media (max-width: 768px) {
    .messages-container {
      padding: 16px;
    }
    
    .messages-header h1 {
      font-size: 1.6rem;
    }
    
    .stat-card {
      min-width: 140px;
    }
    
    .message-item {
      padding: 16px;
    }
    
    .message-content {
      gap: 12px;
    }
    
    .message-icon {
      width: 38px;
      height: 38px;
    }
    
    .message-icon svg {
      width: 18px;
      height: 18px;
    }
    
    .search-box {
      min-width: 100%;
    }
  }
</style>

<div class="messages-container">
  <div class="messages-header">
    <h1>✉️ Messages</h1>
    <p>Stay informed with system updates and important alerts</p>
  </div>
  
  <div class="messages-stats">
    <div class="stat-card">
      <div class="stat-label">Total Messages</div>
      <div class="stat-value" id="total-count">0</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Unread</div>
      <div class="stat-value" id="unread-count" style="color: #a78bfa;">0</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">System Alerts</div>
      <div class="stat-value" id="system-count" style="color: #3b82f6;">0</div>
    </div>
  </div>
  
  <div class="messages-actions">
    <div class="search-box">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"></circle>
        <path d="m21 21-4.35-4.35"></path>
      </svg>
      <input type="text" id="search-messages" placeholder="Search messages...">
    </div>
    <button class="action-btn primary" id="mark-all-read">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="20 6 9 17 4 12"></polyline>
      </svg>
      Mark all read
    </button>
    <button class="action-btn" id="filter-unread">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
      </svg>
      Unread only
    </button>
    <button class="action-btn" id="refresh-messages">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="23 4 23 10 17 10"></polyline>
        <polyline points="1 20 1 14 7 14"></polyline>
        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
      </svg>
      Refresh
    </button>
  </div>
  
  <ul class="messages-list" id="messages-list"></ul>
  
  <div id="messages-empty" class="empty-state" style="display:none">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
      <polyline points="22,6 12,13 2,6"></polyline>
    </svg>
    <h3>No messages yet</h3>
    <p>When you receive messages, they'll appear here</p>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
  let allMessages = [];
  let filterUnreadOnly = false;
  let searchQuery = '';
  
  // Server-generated messages from PHP
  const serverMessages = <?php echo json_encode($messages); ?>;
  
  function renderMessages(data) {
    allMessages = data || [];
    const list = document.getElementById('messages-list');
    const empty = document.getElementById('messages-empty');
    
    // Update stats
    const total = allMessages.length;
    const unread = allMessages.filter(m => !m.is_read).length;
    const system = allMessages.filter(m => m.from === 'System' || m.from === 'Budget Watch').length;
    
    document.getElementById('total-count').textContent = total;
    document.getElementById('unread-count').textContent = unread;
    document.getElementById('system-count').textContent = system;
    
    // Filter messages
    let toShow = allMessages;
    
    // Apply unread filter
    if (filterUnreadOnly) {
      toShow = toShow.filter(m => !m.is_read);
    }
    
    // Apply search filter
    if (searchQuery) {
      const query = searchQuery.toLowerCase();
      toShow = toShow.filter(m => 
        (m.subject || '').toLowerCase().includes(query) ||
        (m.preview || '').toLowerCase().includes(query) ||
        (m.from || '').toLowerCase().includes(query)
      );
    }
    
    list.innerHTML = '';
    
    if (!toShow || !toShow.length) {
      empty.style.display = 'block';
      return;
    } else {
      empty.style.display = 'none';
    }
    
    // Sort: unread first, then by time
    toShow.sort((a, b) => {
      if (a.is_read !== b.is_read) return a.is_read ? 1 : -1;
      return new Date(b.time || 0) - new Date(a.time || 0);
    });
    
    toShow.forEach(msg => {
      const li = document.createElement('li');
      const isSystem = msg.from === 'System';
      const isBudgetAlert = msg.from === 'Budget Watch';
      
      let className = 'message-item';
      if (!msg.is_read) className += ' unread';
      if (msg.is_viewed) className += ' viewed';
      if (isSystem) className += ' system';
      if (isBudgetAlert) className += ' budget-alert';
      
      li.className = className;
      
      const icon = isSystem 
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>'
        : isBudgetAlert
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>'
        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>';
      
      const badges = [];
      if (!msg.is_read) badges.push('<span class="message-badge">New</span>');
      if (msg.is_viewed) badges.push('<span class="message-badge viewed">Viewed</span>');
      if (isSystem) badges.push('<span class="message-badge system">System</span>');
      if (isBudgetAlert) badges.push('<span class="message-badge alert">Alert</span>');
      
      li.innerHTML = `
        <div class="message-content">
          <div class="message-icon">
            ${icon}
          </div>
          <div class="message-body">
            <div class="message-header">
              <div>
                <h3 class="message-subject">${escapeHtml(msg.subject || 'Message')}</h3>
                <div class="message-from">${escapeHtml(msg.from || 'Unknown')}</div>
              </div>
              <div class="message-time">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"></circle>
                  <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                ${escapeHtml(formatTime(msg.time))}
              </div>
            </div>
            <p class="message-preview">${escapeHtml(msg.preview || '')}</p>
            ${badges.length ? '<div class="message-badges">' + badges.join('') + '</div>' : ''}
          </div>
        </div>
      `;
      
      li.addEventListener('click', function() {
        openMessageModal(msg);
      });
      
      list.appendChild(li);
    });
  }
  
  function openMessageModal(msg) {
    const isSystem = msg.from === 'System';
    const isBudgetAlert = msg.from === 'Budget Watch';
    
    const iconColor = isSystem ? '#3b82f6' : isBudgetAlert ? '#fb923c' : '#667eea';
    const icon = isSystem 
      ? '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>'
      : isBudgetAlert
      ? '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>'
      : '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>';
    
    const html = `
      <div style="padding:20px">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
          <div style="width:48px;height:48px;background:linear-gradient(135deg,${iconColor},${iconColor});border-radius:12px;display:flex;align-items:center;justify-content:center">
            ${icon}
          </div>
          <div style="flex:1">
            <h3 style="margin:0;font-size:1.2rem;color:var(--card-text)">${escapeHtml(msg.subject || '')}</h3>
            <div style="color:var(--muted);font-size:0.85rem;margin-top:4px">
              <strong>${escapeHtml(msg.from || '')}</strong> • ${escapeHtml(msg.time || '')}
              ${msg.is_viewed ? ' • <span style="color:#10b981">✓ Viewed</span>' : ''}
            </div>
          </div>
        </div>
        <div style="color:var(--card-text);line-height:1.6;font-size:0.95rem;padding:16px;background:rgba(var(--card-text-rgb),0.03);border-radius:10px">
          ${escapeHtml(msg.preview || '')}
        </div>
        <div style="margin-top:16px;padding-top:16px;border-top:1px solid rgba(var(--card-text-rgb),0.08);color:var(--muted);font-size:0.85rem;text-align:center">
          Click outside to close • This message has been marked as viewed
        </div>
      </div>
    `;
    
    window.WCModal.open(html);
    
    // Mark as viewed (not necessarily read, but opened)
    if (!msg.is_viewed) {
      msg.is_viewed = true;
      renderMessages(allMessages);
    }
    
    // Mark as read if it has an ID
    if (!msg.is_read && msg.id) {
      fetch('/MY CASH/pages/mark_message_read.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + encodeURIComponent(msg.id)
      }).then(() => {
        // Update badge in header
        const badge = document.querySelector('#msg-btn .icon-badge');
        if (badge) {
          const val = Math.max(0, parseInt(badge.textContent || '0') - 1);
          badge.textContent = val > 0 ? val : '';
          if (val === 0) badge.style.display = 'none';
        }
        
        // Update local data
        msg.is_read = true;
        renderMessages(allMessages);
      });
    }
  }
  
  function fetchMessages() {
    // Use server-generated messages
    const serverMessages = <?php echo json_encode($messages); ?>;
    renderMessages(serverMessages);
  }
  
  function markAllAsRead() {
    const unreadIds = allMessages.filter(m => !m.is_read).map(m => m.id).filter(Boolean);
    
    if (unreadIds.length === 0) {
      alert('No unread messages');
      return;
    }
    
    Promise.all(
      unreadIds.map(id => 
        fetch('/MY CASH/pages/mark_message_read.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: 'id=' + encodeURIComponent(id)
        })
      )
    ).then(() => {
      // Update badge
      const badge = document.querySelector('#msg-btn .icon-badge');
      if (badge) {
        badge.textContent = '';
        badge.style.display = 'none';
      }
      
      // Refresh
      fetchMessages();
    });
  }
  
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
  
  function formatTime(timeStr) {
    if (!timeStr) return '';
    
    const date = new Date(timeStr);
    const now = new Date();
    const diff = now - date;
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);
    
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return minutes + 'm ago';
    if (hours < 24) return hours + 'h ago';
    if (days < 7) return days + 'd ago';
    
    return date.toLocaleDateString();
  }
  
  // Event listeners
  document.getElementById('search-messages').addEventListener('input', function(e) {
    searchQuery = e.target.value;
    renderMessages(allMessages);
  });
  
  document.getElementById('mark-all-read').addEventListener('click', markAllAsRead);
  
  document.getElementById('filter-unread').addEventListener('click', function() {
    filterUnreadOnly = !filterUnreadOnly;
    this.innerHTML = filterUnreadOnly 
      ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> Show all'
      : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg> Unread only';
    renderMessages(allMessages);
  });
  
  document.getElementById('refresh-messages').addEventListener('click', fetchMessages);
  
  // Initial fetch and polling
  fetchMessages();
  setInterval(fetchMessages, 10000);
</script>

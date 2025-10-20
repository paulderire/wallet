<?php
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$notifications = [];

if ($user_id) {
    // 1. RECENT TRANSACTIONS (Last 5)
    try {
        $stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY COALESCE(date, created_at) DESC LIMIT 5");
        $stmt->execute([$user_id]);
        $recent_txns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($recent_txns as $txn) {
            $type = strtolower($txn['type'] ?? '');
            $amount = floatval($txn['amount'] ?? 0);
            $date = $txn['date'] ?? $txn['created_at'] ?? date('Y-m-d H:i');
            $category = $txn['category'] ?? 'General';
            
            if ($type === 'deposit') {
                $notifications[] = [
                    'id' => 'txn_' . ($txn['id'] ?? uniqid()),
                    'title' => '💰 Money Received',
                    'body' => "You received $" . number_format($amount, 2) . " in " . $category,
                    'time' => $date,
                    'type' => 'transaction',
                    'is_read' => false
                ];
            } elseif ($type === 'withdraw') {
                $notifications[] = [
                    'id' => 'txn_' . ($txn['id'] ?? uniqid()),
                    'title' => '💸 Money Spent',
                    'body' => "You spent $" . number_format($amount, 2) . " on " . $category,
                    'time' => $date,
                    'type' => 'transaction',
                    'is_read' => false
                ];
            }
        }
    } catch (Exception $e) {}
    
    // 2. GOAL ACHIEVEMENTS (Check if current balance can meet any goals)
    try {
        $stmt = $conn->prepare("SELECT SUM(balance) as total_balance FROM accounts WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $balance_row = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_balance = floatval($balance_row['total_balance'] ?? 0);
        
        $stmt = $conn->prepare("SELECT * FROM goals WHERE user_id = ? AND status != 'completed'");
        $stmt->execute([$user_id]);
        $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($goals as $goal) {
            $target = floatval($goal['target_amount'] ?? 0);
            $saved = floatval($goal['saved_amount'] ?? 0);
            $needed = $target - $saved;
            
            // Notify if they have enough to complete a goal
            if ($total_balance >= $needed && $needed > 0) {
                $notifications[] = [
                    'id' => 'goal_' . ($goal['id'] ?? uniqid()),
                    'title' => '🎯 Goal Achievement Possible!',
                    'body' => "You have $" . number_format($total_balance, 2) . " available! You can complete your '" . ($goal['name'] ?? 'goal') . "' which needs $" . number_format($needed, 2) . " more.",
                    'time' => date('Y-m-d H:i'),
                    'type' => 'goal_achievement',
                    'is_read' => false
                ];
            }
            
            // Notify if goal is 80%+ complete
            if ($target > 0 && ($saved / $target) >= 0.8 && ($saved / $target) < 1) {
                $percent = round(($saved / $target) * 100);
                $notifications[] = [
                    'id' => 'goal_progress_' . ($goal['id'] ?? uniqid()),
                    'title' => '🌟 Goal Almost Complete!',
                    'body' => "Your '" . ($goal['name'] ?? 'goal') . "' is " . $percent . "% complete! Only $" . number_format($needed, 2) . " more to go!",
                    'time' => date('Y-m-d H:i'),
                    'type' => 'goal_progress',
                    'is_read' => false
                ];
            }
        }
    } catch (Exception $e) {}
    
    // 3. LOAN DUE DATES (Next 30 days)
    try {
        $stmt = $conn->prepare("SELECT * FROM loans WHERE user_id = ? AND status != 'paid' AND due_date IS NOT NULL AND due_date >= CURDATE() AND due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
        $stmt->execute([$user_id]);
        $upcoming_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($upcoming_loans as $loan) {
            $amount = floatval($loan['amount'] ?? 0);
            $paid = floatval($loan['paid_amount'] ?? 0);
            $remaining = $amount - $paid;
            $due_date = $loan['due_date'] ?? '';
            $days_until = ceil((strtotime($due_date) - time()) / 86400);
            
            if ($days_until <= 7) {
                $urgency = $days_until <= 3 ? '🚨 URGENT: ' : '⚠️ ';
                $notifications[] = [
                    'id' => 'loan_due_' . ($loan['id'] ?? uniqid()),
                    'title' => $urgency . 'Loan Payment Due Soon',
                    'body' => "Loan to " . ($loan['lender'] ?? 'Unknown') . " is due in " . $days_until . " days. Remaining: $" . number_format($remaining, 2),
                    'time' => date('Y-m-d H:i'),
                    'type' => 'loan_due',
                    'is_read' => false
                ];
            } else {
                $notifications[] = [
                    'id' => 'loan_upcoming_' . ($loan['id'] ?? uniqid()),
                    'title' => '📅 Upcoming Loan Payment',
                    'body' => "Loan to " . ($loan['lender'] ?? 'Unknown') . " is due on " . date('M d, Y', strtotime($due_date)) . ". Remaining: $" . number_format($remaining, 2),
                    'time' => date('Y-m-d H:i'),
                    'type' => 'loan_reminder',
                    'is_read' => false
                ];
            }
        }
    } catch (Exception $e) {}
    
    // 4. BUDGET ALERTS (Over 80% of budget)
    try {
        $stmt = $conn->prepare("SELECT * FROM budgets WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($budgets as $budget) {
            $period = $budget['period'] ?? 'monthly';
            $start_date = $period === 'weekly' ? date('Y-m-d', strtotime('monday this week')) : date('Y-m-01');
            
            $stmt = $conn->prepare("SELECT SUM(CASE WHEN LOWER(type) = 'withdraw' THEN amount ELSE 0 END) as spent FROM transactions WHERE user_id = ? AND category = ? AND COALESCE(date, created_at) >= ?");
            $stmt->execute([$user_id, $budget['category'], $start_date]);
            $spent_row = $stmt->fetch(PDO::FETCH_ASSOC);
            $spent = floatval($spent_row['spent'] ?? 0);
            
            $budget_amount = floatval($budget['amount'] ?? 0);
            if ($budget_amount > 0) {
                $percent = ($spent / $budget_amount) * 100;
                
                if ($percent >= 100) {
                    $notifications[] = [
                        'id' => 'budget_exceeded_' . ($budget['id'] ?? uniqid()),
                        'title' => '🚫 Budget Exceeded!',
                        'body' => "You've spent $" . number_format($spent, 2) . " (" . round($percent) . "%) of your $" . number_format($budget_amount, 2) . " " . ($budget['category'] ?? '') . " budget!",
                        'time' => date('Y-m-d H:i'),
                        'type' => 'budget_exceeded',
                        'is_read' => false
                    ];
                } elseif ($percent >= 80) {
                    $notifications[] = [
                        'id' => 'budget_warning_' . ($budget['id'] ?? uniqid()),
                        'title' => '⚠️ Budget Alert',
                        'body' => "You've used " . round($percent) . "% of your " . ($budget['category'] ?? '') . " budget. $" . number_format($budget_amount - $spent, 2) . " remaining.",
                        'time' => date('Y-m-d H:i'),
                        'type' => 'budget_warning',
                        'is_read' => false
                    ];
                }
            }
        }
    } catch (Exception $e) {}
    
    // 5. EXPECTED INCOME (Recurring deposits pattern detection)
    try {
        $stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? AND LOWER(type) = 'deposit' AND COALESCE(date, created_at) >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) ORDER BY COALESCE(date, created_at) DESC");
        $stmt->execute([$user_id]);
        $deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Look for recurring patterns (same category, similar amounts)
        $patterns = [];
        foreach ($deposits as $dep) {
            $category = $dep['category'] ?? 'Uncategorized';
            $amount = floatval($dep['amount'] ?? 0);
            
            if (!isset($patterns[$category])) {
                $patterns[$category] = ['amounts' => [], 'count' => 0];
            }
            $patterns[$category]['amounts'][] = $amount;
            $patterns[$category]['count']++;
        }
        
        foreach ($patterns as $category => $data) {
            if ($data['count'] >= 2) {
                $avg_amount = array_sum($data['amounts']) / $data['count'];
                $notifications[] = [
                    'id' => 'expected_income_' . md5($category),
                    'title' => '💵 Expected Income Pattern',
                    'body' => "Based on your history, you typically receive ~$" . number_format($avg_amount, 2) . " from " . $category . ". Plan your expenses accordingly!",
                    'time' => date('Y-m-d H:i'),
                    'type' => 'expected_income',
                    'is_read' => false
                ];
                break; // Only show one pattern to avoid spam
            }
        }
    } catch (Exception $e) {}
    
    // 6. LOW BALANCE WARNING
    try {
        $stmt = $conn->prepare("SELECT * FROM accounts WHERE user_id = ? AND balance < 100 AND balance > 0");
        $stmt->execute([$user_id]);
        $low_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($low_accounts as $acc) {
            $notifications[] = [
                'id' => 'low_balance_' . ($acc['id'] ?? uniqid()),
                'title' => '⚠️ Low Balance Alert',
                'body' => "Your account '" . ($acc['name'] ?? 'Account') . "' has a low balance of $" . number_format($acc['balance'] ?? 0, 2),
                'time' => date('Y-m-d H:i'),
                'type' => 'low_balance',
                'is_read' => false
            ];
        }
    } catch (Exception $e) {}
}

// Merge with existing notifications from JSON file
$path = __DIR__ . '/../assets/data/notifications.json';
if (file_exists($path)) {
    $saved_notifications = json_decode(file_get_contents($path), true) ?: [];
    // Only keep manually created notifications, not auto-generated ones
    $saved_notifications = array_filter($saved_notifications, function($n) {
        return !isset($n['type']) || !in_array($n['type'], ['transaction', 'goal_achievement', 'goal_progress', 'loan_due', 'loan_reminder', 'budget_exceeded', 'budget_warning', 'expected_income', 'low_balance']);
    });
    $notifications = array_merge($notifications, $saved_notifications);
}

// Sort by time (newest first)
usort($notifications, function($a, $b) {
    return strtotime($b['time'] ?? 0) - strtotime($a['time'] ?? 0);
});

// Limit to 50 most recent
$notifications = array_slice($notifications, 0, 50);
?>

<style>
  .notifications-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 24px;
  }
  
  .notifications-header {
    margin-bottom: 32px;
  }
  
  .notifications-header h1 {
    font-size: 2rem;
    font-weight: 800;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0 0 8px 0;
  }
  
  .notifications-header p {
    color: var(--muted);
    font-size: 0.95rem;
    margin: 0;
  }
  
  .notifications-stats {
    display: flex;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
  }
  
  .stat-card {
    flex: 1;
    min-width: 180px;
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
  
  .stat-card .stat-icon {
    font-size: 1.8rem;
    margin-bottom: 8px;
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
  
  .notifications-actions {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    flex-wrap: wrap;
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
  
  .notifications-list {
    list-style: none;
    padding: 0;
    margin: 0;
  }
  
  .notification-item {
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
  
  .notification-item::before {
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
  
  .notification-item.unread::before {
    opacity: 1;
  }
  
  .notification-item.unread {
    background: rgba(167, 139, 250, 0.04);
    border-color: rgba(167, 139, 250, 0.2);
  }
  
  .notification-item.viewed {
    opacity: 0.7;
  }
  
  .notification-item.viewed .notification-title {
    opacity: 0.8;
  }
  
  .notification-item:hover {
    border-color: #a78bfa;
    transform: translateX(4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
  }
  
  .notification-item:active {
    transform: translateX(2px);
    transition: all 0.1s ease;
  }
  
  .notification-content {
    display: flex;
    gap: 16px;
    align-items: flex-start;
  }
  
  .notification-icon {
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
  
  .notification-icon svg {
    width: 22px;
    height: 22px;
    color: white;
  }
  
  .notification-body {
    flex: 1;
  }
  
  .notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
    gap: 12px;
  }
  
  .notification-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--card-text);
    margin: 0;
  }
  
  .notification-time {
    font-size: 0.82rem;
    color: var(--muted);
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 4px;
  }
  
  .notification-message {
    font-size: 0.92rem;
    color: var(--muted);
    line-height: 1.5;
    margin: 0;
  }
  
  .notification-badges {
    display: flex;
    gap: 8px;
    margin-top: 12px;
  }
  
  .notification-badge {
    padding: 4px 10px;
    background: rgba(167, 139, 250, 0.1);
    border: 1px solid rgba(167, 139, 250, 0.2);
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    color: #a78bfa;
  }
  
  .notification-badge.viewed {
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
    .notifications-container {
      padding: 16px;
    }
    
    .notifications-header h1 {
      font-size: 1.6rem;
    }
    
    .stat-card {
      min-width: 140px;
    }
    
    .notification-item {
      padding: 16px;
    }
    
    .notification-content {
      gap: 12px;
    }
    
    .notification-icon {
      width: 38px;
      height: 38px;
    }
    
    .notification-icon svg {
      width: 18px;
      height: 18px;
    }
  }
</style>

<div class="notifications-container">
  <div class="notifications-header">
    <h1>📬 Notifications</h1>
    <p>Stay updated with your latest activity and alerts</p>
  </div>
  
  <div class="notifications-stats">
    <div class="stat-card">
      <div class="stat-label">Total</div>
      <div class="stat-value" id="total-count">0</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Unread</div>
      <div class="stat-value" id="unread-count" style="color: #a78bfa;">0</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Today</div>
      <div class="stat-value" id="today-count">0</div>
    </div>
  </div>
  
  <div class="notifications-actions">
    <button class="action-btn primary" id="mark-all-read">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="20 6 9 17 4 12"></polyline>
      </svg>
      Mark all as read
    </button>
    <button class="action-btn" id="filter-unread">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
      </svg>
      Show unread only
    </button>
    <button class="action-btn" id="refresh-notifications">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="23 4 23 10 17 10"></polyline>
        <polyline points="1 20 1 14 7 14"></polyline>
        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
      </svg>
      Refresh
    </button>
  </div>
  
  <ul class="notifications-list" id="notifications-list"></ul>
  
  <div id="notifications-empty" class="empty-state" style="display:none">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
      <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
    </svg>
    <h3>No notifications yet</h3>
    <p>When you receive notifications, they'll appear here</p>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
  let allNotifications = [];
  let filterUnreadOnly = false;
  
  // Render notifications with modern design
  function renderNotifications(data) {
    allNotifications = data || [];
    const list = document.getElementById('notifications-list');
    const empty = document.getElementById('notifications-empty');
    
    // Update stats
    const total = allNotifications.length;
    const unread = allNotifications.filter(n => !n.is_read).length;
    const today = allNotifications.filter(n => isToday(n.time)).length;
    
    document.getElementById('total-count').textContent = total;
    document.getElementById('unread-count').textContent = unread;
    document.getElementById('today-count').textContent = today;
    
    // Filter notifications
    let toShow = filterUnreadOnly 
      ? allNotifications.filter(n => !n.is_read)
      : allNotifications;
    
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
    
    toShow.forEach(notif => {
      const li = document.createElement('li');
      let className = 'notification-item ';
      if (notif.is_read) className += 'read ';
      else className += 'unread ';
      if (notif.is_viewed) className += 'viewed';
      
      li.className = className.trim();
      
      const badges = [];
      if (!notif.is_read) badges.push('<span class="notification-badge">New</span>');
      if (notif.is_viewed) badges.push('<span class="notification-badge viewed">Viewed</span>');
      
      li.innerHTML = `
        <div class="notification-content">
          <div class="notification-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
          </div>
          <div class="notification-body">
            <div class="notification-header">
              <h3 class="notification-title">${escapeHtml(notif.title || 'Notification')}</h3>
              <div class="notification-time">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"></circle>
                  <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                ${escapeHtml(formatTime(notif.time))}
              </div>
            </div>
            <p class="notification-message">${escapeHtml(notif.body || '')}</p>
            ${badges.length ? '<div class="notification-badges">' + badges.join('') + '</div>' : ''}
          </div>
        </div>
      `;
      
      li.addEventListener('click', function() {
        openNotificationModal(notif);
      });
      
      list.appendChild(li);
    });
  }
  
  function openNotificationModal(notif) {
    const html = `
      <div style="padding:20px">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
          <div style="width:48px;height:48px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:12px;display:flex;align-items:center;justify-content:center">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
          </div>
          <div>
            <h3 style="margin:0;font-size:1.2rem;color:var(--card-text)">${escapeHtml(notif.title || '')}</h3>
            <div style="color:var(--muted);font-size:0.85rem;margin-top:4px">
              ${escapeHtml(notif.time || '')}
              ${notif.is_viewed ? ' • <span style="color:#10b981">✓ Viewed</span>' : ''}
            </div>
          </div>
        </div>
        <div style="color:var(--card-text);line-height:1.6;font-size:0.95rem;padding:16px;background:rgba(var(--card-text-rgb),0.03);border-radius:10px">
          ${escapeHtml(notif.body || '')}
        </div>
        <div style="margin-top:16px;padding-top:16px;border-top:1px solid rgba(var(--card-text-rgb),0.08);color:var(--muted);font-size:0.85rem;text-align:center">
          Click outside to close • This notification has been marked as viewed
        </div>
      </div>
    `;
    
    window.WCModal.open(html);
    
    // Mark as viewed (not necessarily read, but opened)
    if (!notif.is_viewed) {
      notif.is_viewed = true;
      renderNotifications(allNotifications);
    }
    
    // Mark as read if it has an ID
    if (!notif.is_read && notif.id) {
      fetch('/MY CASH/pages/mark_notification_read.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + encodeURIComponent(notif.id)
      }).then(() => {
        // Update badge in header
        const badge = document.querySelector('#notif-btn .icon-badge');
        if (badge) {
          const val = Math.max(0, parseInt(badge.textContent || '0') - 1);
          badge.textContent = val > 0 ? val : '';
          if (val === 0) badge.style.display = 'none';
        }
        
        // Update local data
        notif.is_read = true;
        renderNotifications(allNotifications);
      });
    }
  }
  
  function fetchNotifications() {
    // Use server-generated notifications
    const serverNotifications = <?php echo json_encode($notifications); ?>;
    renderNotifications(serverNotifications);
  }
  
  function markAllAsRead() {
    const unreadIds = allNotifications.filter(n => !n.is_read).map(n => n.id).filter(Boolean);
    
    if (unreadIds.length === 0) {
      alert('No unread notifications');
      return;
    }
    
    // Mark all as read via API (you'll need to create this endpoint)
    Promise.all(
      unreadIds.map(id => 
        fetch('/MY CASH/pages/mark_notification_read.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: 'id=' + encodeURIComponent(id)
        })
      )
    ).then(() => {
      // Update badge
      const badge = document.querySelector('#notif-btn .icon-badge');
      if (badge) {
        badge.textContent = '';
        badge.style.display = 'none';
      }
      
      // Refresh
      fetchNotifications();
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
  
  function isToday(timeStr) {
    if (!timeStr) return false;
    const date = new Date(timeStr);
    const today = new Date();
    return date.toDateString() === today.toDateString();
  }
  
  // Event listeners
  document.getElementById('mark-all-read').addEventListener('click', markAllAsRead);
  
  document.getElementById('filter-unread').addEventListener('click', function() {
    filterUnreadOnly = !filterUnreadOnly;
    this.textContent = filterUnreadOnly ? 'Show all' : 'Show unread only';
    this.innerHTML = filterUnreadOnly 
      ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> Show all'
      : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg> Show unread only';
    renderNotifications(allNotifications);
  });
  
  document.getElementById('refresh-notifications').addEventListener('click', fetchNotifications);
  
  // Initial fetch and polling
  fetchNotifications();
  setInterval(fetchNotifications, 10000);
</script>

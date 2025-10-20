<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../includes/db.php';
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }
$user_id = $_SESSION['user_id'];

// Create budget_settings table if it doesn't exist
try {
  $conn->exec("CREATE TABLE IF NOT EXISTS budget_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    default_period VARCHAR(20) DEFAULT 'monthly',
    default_alert_threshold INT DEFAULT 80,
    enable_notifications TINYINT(1) DEFAULT 1,
    enable_email_alerts TINYINT(1) DEFAULT 0,
    rollover_unused TINYINT(1) DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'USD',
    notification_days_before INT DEFAULT 5,
    auto_create_categories TINYINT(1) DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Handle settings update
if (isset($_POST['save_settings'])) {
  $default_period = $_POST['default_period'] ?? 'monthly';
  $default_alert_threshold = intval($_POST['default_alert_threshold'] ?? 80);
  $enable_notifications = isset($_POST['enable_notifications']) ? 1 : 0;
  $enable_email_alerts = isset($_POST['enable_email_alerts']) ? 1 : 0;
  $rollover_unused = isset($_POST['rollover_unused']) ? 1 : 0;
  $currency = $_POST['currency'] ?? 'USD';
  $notification_days_before = intval($_POST['notification_days_before'] ?? 5);
  $auto_create_categories = isset($_POST['auto_create_categories']) ? 1 : 0;
  
  try {
    // Check if settings exist
    $checkStmt = $conn->prepare("SELECT id FROM budget_settings WHERE user_id = ?");
    $checkStmt->execute([$user_id]);
    
    if ($checkStmt->fetch()) {
      // Update existing
      $stmt = $conn->prepare("UPDATE budget_settings SET 
        default_period = ?, 
        default_alert_threshold = ?, 
        enable_notifications = ?, 
        enable_email_alerts = ?, 
        rollover_unused = ?, 
        currency = ?, 
        notification_days_before = ?,
        auto_create_categories = ?
        WHERE user_id = ?");
      $stmt->execute([
        $default_period, $default_alert_threshold, $enable_notifications, 
        $enable_email_alerts, $rollover_unused, $currency, 
        $notification_days_before, $auto_create_categories, $user_id
      ]);
    } else {
      // Insert new
      $stmt = $conn->prepare("INSERT INTO budget_settings 
        (user_id, default_period, default_alert_threshold, enable_notifications, 
        enable_email_alerts, rollover_unused, currency, notification_days_before, auto_create_categories) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->execute([
        $user_id, $default_period, $default_alert_threshold, $enable_notifications, 
        $enable_email_alerts, $rollover_unused, $currency, 
        $notification_days_before, $auto_create_categories
      ]);
    }
    
    $successMsg = "Settings saved successfully!";
  } catch (Exception $e) {
    $errorMsg = "Failed to save settings: " . $e->getMessage();
  }
}

// Load current settings
$settings = [
  'default_period' => 'monthly',
  'default_alert_threshold' => 80,
  'enable_notifications' => 1,
  'enable_email_alerts' => 0,
  'rollover_unused' => 0,
  'currency' => 'USD',
  'notification_days_before' => 5,
  'auto_create_categories' => 0
];

try {
  $stmt = $conn->prepare("SELECT * FROM budget_settings WHERE user_id = ?");
  $stmt->execute([$user_id]);
  $userSettings = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($userSettings) {
    $settings = array_merge($settings, $userSettings);
  }
} catch (Exception $e) {}

// Get budget statistics
$totalBudgets = 0;
$activeCategories = 0;
$totalMonthlyBudget = 0;

try {
  $statsStmt = $conn->prepare("SELECT 
    COUNT(*) as total_budgets,
    COUNT(DISTINCT category) as active_categories,
    SUM(CASE WHEN period = 'monthly' THEN amount ELSE 0 END) as total_monthly
    FROM budgets WHERE user_id = ?");
  $statsStmt->execute([$user_id]);
  $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
  if ($stats) {
    $totalBudgets = $stats['total_budgets'];
    $activeCategories = $stats['active_categories'];
    $totalMonthlyBudget = floatval($stats['total_monthly']);
  }
} catch (Exception $e) {}

include __DIR__ . '/../includes/header.php';
?>

<style>
.settings-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;flex-wrap:wrap;gap:16px}
.settings-header h2{display:flex;align-items:center;gap:12px;font-size:1.8rem}
.settings-grid{display:grid;grid-template-columns:1fr;gap:24px;margin-bottom:32px}
.settings-section{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:16px;padding:32px;box-shadow:var(--overlay-shadow)}
.settings-section-header{display:flex;align-items:center;gap:12px;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid var(--border-weak)}
.settings-section-icon{font-size:2rem;opacity:.8}
.settings-section-title{font-size:1.4rem;font-weight:700;color:var(--card-text)}
.settings-section-subtitle{font-size:.9rem;color:var(--muted);margin-top:4px}
.setting-item{margin-bottom:24px;padding-bottom:24px;border-bottom:1px solid var(--border-weak)}
.setting-item:last-child{margin-bottom:0;padding-bottom:0;border-bottom:none}
.setting-label{font-size:1.05rem;font-weight:600;color:var(--card-text);margin-bottom:8px;display:block}
.setting-description{font-size:.9rem;color:var(--muted);margin-bottom:12px}
.setting-input{width:100%;padding:12px;border:1px solid var(--border-weak);border-radius:8px;background:var(--card-bg);color:var(--card-text);font-size:1rem}
.setting-input:focus{outline:none;border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,.1)}
.toggle-switch{position:relative;display:inline-block;width:52px;height:28px}
.toggle-switch input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;border-radius:28px;transition:.3s}
.toggle-slider:before{position:absolute;content:"";height:20px;width:20px;left:4px;bottom:4px;background:white;border-radius:50%;transition:.3s}
.toggle-switch input:checked + .toggle-slider{background:#667eea}
.toggle-switch input:checked + .toggle-slider:before{transform:translateX(24px)}
.setting-row{display:flex;justify-content:space-between;align-items:center;gap:16px}
.stats-overview{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:32px}
.stat-box{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;padding:24px;text-align:center;position:relative;overflow:hidden}
.stat-box::before{content:'';position:absolute;top:0;left:0;right:0;height:4px}
.stat-box.primary::before{background:linear-gradient(90deg,#667eea,#764ba2)}
.stat-box.success::before{background:linear-gradient(90deg,#2ed573,#11998e)}
.stat-box.warning::before{background:linear-gradient(90deg,#ffa500,#ff6348)}
.stat-label{font-size:.85rem;color:var(--muted);text-transform:uppercase;font-weight:600;margin-bottom:8px}
.stat-value{font-size:2rem;font-weight:800;color:var(--card-text)}
.alert-box{padding:16px 20px;border-radius:12px;margin-bottom:24px;display:flex;align-items:center;gap:12px}
.alert-box.success{background:rgba(46,213,115,.1);border:1px solid rgba(46,213,115,.3);color:#2ed573}
.alert-box.error{background:rgba(245,87,108,.1);border:1px solid rgba(245,87,108,.3);color:#f5576c}
.alert-icon{font-size:1.5rem}
@media (max-width:768px){.settings-grid{grid-template-columns:1fr}.stat-box{padding:16px}}
</style>

<div class="settings-header">
<div>
<h2>⚙️ Budget Settings</h2>
<p class="muted">Configure your budget preferences and default options</p>
</div>
<a href="/MY CASH/pages/budgets.php" class="button ghost">← Back to Budgets</a>
</div>

<?php if (isset($successMsg)): ?>
<div class="alert-box success">
<div class="alert-icon">✅</div>
<div><?= htmlspecialchars($successMsg) ?></div>
</div>
<?php endif; ?>

<?php if (isset($errorMsg)): ?>
<div class="alert-box error">
<div class="alert-icon">❌</div>
<div><?= htmlspecialchars($errorMsg) ?></div>
</div>
<?php endif; ?>

<div class="stats-overview">
<div class="stat-box primary">
<div class="stat-label">Total Budgets</div>
<div class="stat-value"><?= $totalBudgets ?></div>
</div>
<div class="stat-box success">
<div class="stat-label">Active Categories</div>
<div class="stat-value"><?= $activeCategories ?></div>
</div>
<div class="stat-box warning">
<div class="stat-label">Monthly Total</div>
<div class="stat-value">$<?= number_format($totalMonthlyBudget, 0) ?></div>
</div>
</div>

<form method="POST">
<div class="settings-grid">

<!-- General Settings -->
<div class="settings-section">
<div class="settings-section-header">
<div class="settings-section-icon">🎯</div>
<div>
<div class="settings-section-title">General Settings</div>
<div class="settings-section-subtitle">Configure default budget options</div>
</div>
</div>

<div class="setting-item">
<label class="setting-label">Default Budget Period</label>
<div class="setting-description">Choose the default time period when creating new budgets</div>
<select name="default_period" class="setting-input">
<option value="weekly" <?= $settings['default_period'] === 'weekly' ? 'selected' : '' ?>>Weekly</option>
<option value="monthly" <?= $settings['default_period'] === 'monthly' ? 'selected' : '' ?>>Monthly</option>
<option value="yearly" <?= $settings['default_period'] === 'yearly' ? 'selected' : '' ?>>Yearly</option>
</select>
</div>

<div class="setting-item">
<label class="setting-label">Default Alert Threshold</label>
<div class="setting-description">Default percentage at which you'll receive budget alerts (0-100%)</div>
<input type="number" name="default_alert_threshold" min="0" max="100" value="<?= $settings['default_alert_threshold'] ?>" class="setting-input">
</div>

<div class="setting-item">
<label class="setting-label">Budget Currency</label>
<div class="setting-description">Primary currency for budget tracking</div>
<select name="currency" class="setting-input">
<option value="USD" <?= $settings['currency'] === 'USD' ? 'selected' : '' ?>>USD ($)</option>
<option value="RWF" <?= $settings['currency'] === 'RWF' ? 'selected' : '' ?>>RWF (FRw)</option>
<option value="EUR" <?= $settings['currency'] === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
<option value="GBP" <?= $settings['currency'] === 'GBP' ? 'selected' : '' ?>>GBP (£)</option>
</select>
</div>
</div>

<!-- Notification Settings -->
<div class="settings-section">
<div class="settings-section-header">
<div class="settings-section-icon">🔔</div>
<div>
<div class="settings-section-title">Notification Settings</div>
<div class="settings-section-subtitle">Manage how you receive budget alerts</div>
</div>
</div>

<div class="setting-item">
<div class="setting-row">
<div>
<label class="setting-label">Enable Budget Notifications</label>
<div class="setting-description">Receive in-app notifications when approaching budget limits</div>
</div>
<label class="toggle-switch">
<input type="checkbox" name="enable_notifications" <?= $settings['enable_notifications'] ? 'checked' : '' ?>>
<span class="toggle-slider"></span>
</label>
</div>
</div>

<div class="setting-item">
<div class="setting-row">
<div>
<label class="setting-label">Enable Email Alerts</label>
<div class="setting-description">Send email notifications when budget thresholds are exceeded</div>
</div>
<label class="toggle-switch">
<input type="checkbox" name="enable_email_alerts" <?= $settings['enable_email_alerts'] ? 'checked' : '' ?>>
<span class="toggle-slider"></span>
</label>
</div>
</div>

<div class="setting-item">
<label class="setting-label">Early Warning Period</label>
<div class="setting-description">Notify me this many days before budget period ends</div>
<input type="number" name="notification_days_before" min="0" max="30" value="<?= $settings['notification_days_before'] ?>" class="setting-input">
</div>
</div>

<!-- Advanced Settings -->
<div class="settings-section">
<div class="settings-section-header">
<div class="settings-section-icon">🔧</div>
<div>
<div class="settings-section-title">Advanced Settings</div>
<div class="settings-section-subtitle">Additional budget configuration options</div>
</div>
</div>

<div class="setting-item">
<div class="setting-row">
<div>
<label class="setting-label">Rollover Unused Budget</label>
<div class="setting-description">Carry over unused budget amounts to the next period</div>
</div>
<label class="toggle-switch">
<input type="checkbox" name="rollover_unused" <?= $settings['rollover_unused'] ? 'checked' : '' ?>>
<span class="toggle-slider"></span>
</label>
</div>
</div>

<div class="setting-item">
<div class="setting-row">
<div>
<label class="setting-label">Auto-Create Categories</label>
<div class="setting-description">Automatically create budget categories based on transaction types</div>
</div>
<label class="toggle-switch">
<input type="checkbox" name="auto_create_categories" <?= $settings['auto_create_categories'] ? 'checked' : '' ?>>
<span class="toggle-slider"></span>
</label>
</div>
</div>
</div>

</div>

<div style="display:flex;gap:12px;justify-content:flex-end">
<a href="/MY CASH/pages/budgets.php" class="button ghost">Cancel</a>
<button type="submit" name="save_settings" class="button primary">💾 Save Settings</button>
</div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>

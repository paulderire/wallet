<?php
// Common header include - outputs the document head and top navigation
// Start output buffering so pages can still send header() redirects after including this file
if (session_status() === PHP_SESSION_NONE) session_start();
if (!ob_get_level()) ob_start();

// Load notification and message counts from database
$notifCount = 0; $msgCount = 0;
try {
  $dbPath = __DIR__ . '/db.php';
  if (file_exists($dbPath)) {
    include_once $dbPath; // provides $conn
    if (!empty($conn) && !empty($_SESSION['user_id'])) {
      // Count unread inventory alerts
      $alertStmt = $conn->prepare("SELECT COUNT(*) as count FROM inventory_alerts WHERE status = 'pending' OR status IS NULL");
      $alertStmt->execute();
      $alertRow = $alertStmt->fetch(PDO::FETCH_ASSOC);
      $notifCount = $alertRow ? intval($alertRow['count']) : 0;
      
      // Count unread messages (from JSON file as fallback)
      $mpath = __DIR__ . '/../assets/data/messages.json';
      if (file_exists($mpath)) {
        $m = json_decode(file_get_contents($mpath), true) ?: [];
        foreach ($m as $it) { if (empty($it['is_read'])) $msgCount++; }
      }
    }
  }
} catch (Exception $e) { 
  $notifCount = $msgCount = 0; 
}

// determine user profile info early so header can render cleanly
$profileName = 'Guest';
$avatarUrl = '';
$isAdmin = false;
if (!empty($_SESSION['user_id'])) {
    try {
        $dbPath = __DIR__ . '/db.php';
        if (file_exists($dbPath)) include_once $dbPath; // provides $conn
        if (!empty($conn)) {
            $uStmt = $conn->prepare('SELECT name, avatar, is_admin FROM users WHERE id = ?');
            $uStmt->execute([$_SESSION['user_id']]);
            $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
            if ($uRow) {
                $profileName = $uRow['name'] ?? $profileName;
                $avatarUrl = $uRow['avatar'] ?? '';
                $isAdmin = !empty($uRow['is_admin']);
            }
        }
    } catch (Exception $e) { /* ignore DB errors in header */ }
}
// fallback: if no avatar in DB, try JSON map
if (empty($avatarUrl) && !empty($_SESSION['user_id'])) {
  try {
    $mapPath = __DIR__ . '/../assets/data/user_avatars.json';
    if (file_exists($mapPath)) {
      $map = json_decode(file_get_contents($mapPath), true) ?: [];
      if (!empty($map[strval($_SESSION['user_id'])])) $avatarUrl = $map[strval($_SESSION['user_id'])];
    }
  } catch (Exception $e) { }
}

// Notification and message counts already loaded above
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Wallet App</title>
  <!-- Inter font for a modern UI -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/MY CASH/assets/css/style.min.css">
  <link rel="stylesheet" href="/MY CASH/assets/css/global-styles.min.css">
  <link rel="stylesheet" href="/MY CASH/assets/css/employee-theme.min.css">
  <style>
    /* Enhanced Modern Header Styling */
    .site-header {
      background: rgba(var(--card-text-rgb), 0.02) !important;
      backdrop-filter: blur(20px);
      border-bottom: 1px solid rgba(var(--card-text-rgb), 0.08);
      box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
    }
    
    .site-header .container {
      max-width: 100% !important;
      padding: 0 20px !important;
      gap: 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    
    /* Header Left Section - Matches Sidebar */
    .header-left {
      display: flex;
      align-items: center;
      gap: 20px;
      flex: 0 0 auto;
      padding: 8px 0;
    }
    
    /* Menu Toggle Button */
    .menu-toggle {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      background: rgba(102, 126, 234, 0.08);
      border: 1px solid rgba(102, 126, 234, 0.15);
      color: #667eea;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .menu-toggle:hover {
      background: rgba(102, 126, 234, 0.15);
      transform: scale(1.05);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
    }
    
    .menu-toggle svg {
      transition: transform 0.3s ease;
    }
    
    .menu-toggle:hover svg {
      transform: rotate(90deg);
    }
    
    /* Brand - Enhanced to Match Sidebar Profile */
    .brand {
      display: flex !important;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      padding: 8px 16px;
      border-radius: 12px;
      transition: all 0.3s ease;
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
      border: 1px solid rgba(102, 126, 234, 0.1);
    }
    
    .brand:hover {
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(102, 126, 234, 0.15);
    }
    
    .brand-icon {
      width: 40px;
      height: 40px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 800;
      font-size: 1.25rem;
      box-shadow: 0 4px 16px rgba(102, 126, 234, 0.4);
      flex-shrink: 0;
    }
    
    .brand-content {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    
    .brand-text {
      font-weight: 800 !important;
      font-size: 1.05rem !important;
      background: linear-gradient(135deg, #667eea, #764ba2);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      line-height: 1.2;
    }
    
    .brand-subtitle {
      font-size: 0.7rem;
      color: var(--muted-color);
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    /* Header Profile Preview - Matches Sidebar */
    .header-profile {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 6px 12px 6px 6px;
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
      border-radius: 12px;
      border: 1px solid rgba(102, 126, 234, 0.1);
      transition: all 0.3s ease;
    }
    
    .header-profile:hover {
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.12);
    }
    
    .header-profile-avatar {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      flex-shrink: 0;
      overflow: hidden;
      border: 2px solid rgba(102, 126, 234, 0.3);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
    }
    
    .header-profile-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .header-profile-info {
      display: flex;
      flex-direction: column;
      gap: 2px;
      min-width: 0;
    }
    
    .header-profile-name {
      font-size: 0.88rem;
      font-weight: 700;
      color: var(--card-text);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      background: linear-gradient(135deg, #667eea, #764ba2);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    
    .header-profile-role {
      font-size: 0.7rem;
      color: var(--muted-color);
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    
    /* Modern Search */
    .header-search {
      position: relative;
      flex: 1;
      max-width: 480px;
    }
    
    .header-search input {
      width: 100%;
      padding: 11px 18px 11px 44px;
      background: rgba(var(--card-text-rgb), 0.03);
      border: 1.5px solid rgba(var(--card-text-rgb), 0.1);
      border-radius: 12px;
      color: var(--card-text);
      font-size: 0.93rem;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .header-search input:focus {
      outline: none;
      border-color: #a78bfa;
      background: rgba(var(--card-text-rgb), 0.04);
      box-shadow: 0 0 0 4px rgba(167, 139, 250, 0.12);
    }
    
    .header-search input::placeholder {
      color: var(--muted);
      opacity: 0.6;
    }
    
    .header-search > .icon-button {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      background: none !important;
      border: none !important;
      padding: 0 !important;
      width: auto !important;
      height: auto !important;
      font-size: 1.15rem;
      opacity: 0.5;
      pointer-events: none;
    }
    
    /* Polished Icon Buttons */
    .header-actions .icon-button,
    .menu-toggle {
      position: relative;
      width: 42px;
      height: 42px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(var(--card-text-rgb), 0.02);
      border: 1.5px solid rgba(var(--card-text-rgb), 0.08);
      border-radius: 10px;
      color: var(--card-text);
      cursor: pointer;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      font-size: 1.15rem;
    }
    
    .header-actions .icon-button:hover,
    .menu-toggle:hover {
      background: rgba(167, 139, 250, 0.12);
      border-color: #a78bfa;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(167, 139, 250, 0.2);
    }
    
    .header-actions .icon-button:active,
    .menu-toggle:active {
      transform: translateY(0);
    }
    
    /* Enhanced Badge */
    .icon-badge {
      position: absolute;
      top: -6px;
      right: -6px;
      background: linear-gradient(135deg, #ef4444, #dc2626);
      color: white;
      font-size: 0.7rem;
      font-weight: 700;
      padding: 3px 6px;
      border-radius: 12px;
      min-width: 20px;
      text-align: center;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
      border: 2px solid var(--sidebar-bg);
      animation: badgePulse 2.5s ease-in-out infinite;
    }
    
    @keyframes badgePulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.1); }
    }
    
    /* Profile Button Special */
    .header-actions .icon-button .avatar {
      width: 30px;
      height: 30px;
      border-radius: 7px;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .header-actions .icon-button .avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .header-actions .icon-button {
      padding: 0 12px;
      width: auto;
      gap: 8px;
    }
    
    .profile-name {
      font-weight: 600;
      font-size: 0.88rem;
      color: var(--card-text);
      white-space: nowrap;
    }
    
    /* Premium Dropdowns */
    .dropdown-panel {
      position: absolute;
      top: calc(100% + 12px);
      right: 0;
      min-width: 320px;
      background: var(--card-bg-solid);
      border: 1.5px solid rgba(var(--card-text-rgb), 0.1);
      border-radius: 14px;
      box-shadow: 0 16px 48px rgba(0, 0, 0, 0.24), 0 0 1px rgba(0, 0, 0, 0.1);
      display: none;
      z-index: 10000;
      overflow: hidden;
      opacity: 0;
      transform: translateY(-8px);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .dropdown-panel[style*="display: block"],
    .dropdown-panel[style*="display:block"] {
      display: block !important;
      opacity: 1;
      transform: translateY(0);
    }
    
    .dropdown-panel::before {
      content: '';
      position: absolute;
      top: -6px;
      right: 16px;
      width: 12px;
      height: 12px;
      background: var(--card-bg-solid);
      border-left: 1.5px solid rgba(var(--card-text-rgb), 0.1);
      border-top: 1.5px solid rgba(var(--card-text-rgb), 0.1);
      transform: rotate(45deg);
    }
    
    .dropdown-panel h3,
    .dropdown-panel strong {
      color: var(--card-text);
      font-size: 0.95rem;
      margin-bottom: 12px;
    }
    
    .dropdown-panel ul {
      list-style: none;
      padding: 6px;
      margin: 0;
      max-height: 380px;
      overflow-y: auto;
    }
    
    .dropdown-panel ul::-webkit-scrollbar {
      width: 6px;
    }
    
    .dropdown-panel ul::-webkit-scrollbar-thumb {
      background: rgba(var(--card-text-rgb), 0.2);
      border-radius: 3px;
    }
    
    .dropdown-panel li {
      padding: 11px 14px;
      border-bottom: 1px solid rgba(var(--card-text-rgb), 0.05);
      cursor: pointer;
      transition: all 0.2s ease;
      border-radius: 8px;
      margin-bottom: 3px;
    }
    
    .dropdown-panel li:hover {
      background: rgba(167, 139, 250, 0.1);
      transform: translateX(3px);
    }
    
    .dropdown-panel li:last-child {
      border-bottom: none;
    }
    
    .dropdown-panel a {
      display: block;
      padding: 11px 14px;
      color: var(--card-text);
      text-decoration: none;
      border-radius: 8px;
      transition: all 0.2s ease;
      font-weight: 500;
    }
    
    .dropdown-panel a:hover {
      background: rgba(167, 139, 250, 0.1);
      padding-left: 18px;
      color: #a78bfa;
    }
    
    .dropdown-panel hr {
      margin: 10px 0;
      border: none;
      border-top: 1px solid rgba(var(--card-text-rgb), 0.08);
    }
    
    /* Search Suggestions Enhanced */
    .search-suggestions {
      position: absolute;
      top: calc(100% + 10px);
      left: 0;
      right: 0;
      background: var(--card-bg-solid);
      border: 1.5px solid rgba(var(--card-text-rgb), 0.1);
      border-radius: 12px;
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
      max-height: 420px;
      overflow-y: auto;
      z-index: 10001;
      display: none;
    }
    
    .search-suggestions[style*="display: block"],
    .search-suggestions[style*="display:block"] {
      display: block !important;
      animation: fadeInDown 0.3s ease;
    }
    
    @keyframes fadeInDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .search-suggestion-item {
      display: block;
      padding: 13px 16px;
      color: var(--card-text);
      text-decoration: none;
      border-bottom: 1px solid rgba(var(--card-text-rgb), 0.05);
      transition: all 0.2s ease;
      font-size: 0.92rem;
    }
    
    .search-suggestion-item:last-child {
      border-bottom: none;
    }
    
    .search-suggestion-item:hover {
      background: rgba(167, 139, 250, 0.1);
      padding-left: 20px;
      color: #a78bfa;
    }
    
    /* Theme Toggle Special */
    #theme-toggle {
      transition: transform 0.5s ease;
    }
    
    #theme-toggle:hover {
      transform: scale(1.1);
    }
    
    /* ========================================
       MODERN SIDEBAR STYLING
       ======================================== */
    .sidebar {
      background: linear-gradient(180deg, rgba(102, 126, 234, 0.03) 0%, rgba(118, 75, 162, 0.03) 100%),
                  var(--card-bg-solid) !important;
      backdrop-filter: blur(20px);
      border-right: 1px solid rgba(var(--card-text-rgb), 0.08);
      box-shadow: 4px 0 24px rgba(0, 0, 0, 0.04);
      display: flex;
      flex-direction: column;
      padding: 0 !important;
      overflow-y: auto;
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    /* Custom Scrollbar */
    .sidebar::-webkit-scrollbar {
      width: 6px;
    }
    
    .sidebar::-webkit-scrollbar-track {
      background: transparent;
    }
    
    .sidebar::-webkit-scrollbar-thumb {
      background: rgba(var(--card-text-rgb), 0.15);
      border-radius: 3px;
      transition: background 0.3s ease;
    }
    
    .sidebar::-webkit-scrollbar-thumb:hover {
      background: rgba(var(--card-text-rgb), 0.25);
    }
    
    /* Profile Section */
    .sidebar-profile {
      padding: 24px 20px;
      border-bottom: 1px solid rgba(var(--card-text-rgb), 0.08);
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
      display: flex;
      align-items: center;
      gap: 14px;
    }
    
    .profile-avatar-wrapper {
      position: relative;
      width: 48px;
      height: 48px;
      flex-shrink: 0;
    }
    
    .profile-avatar-img {
      width: 100%;
      height: 100%;
      border-radius: 12px;
      object-fit: cover;
      border: 2px solid rgba(102, 126, 234, 0.3);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
    }
    
    .profile-avatar-placeholder {
      width: 100%;
      height: 100%;
      border-radius: 12px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .profile-info {
      flex: 1;
      min-width: 0;
      display: flex;
      align-items: center;
    }
    
    .profile-username {
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--card-text);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      background: linear-gradient(135deg, #667eea, #764ba2);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    
    /* Navigation */
    .sidebar-nav {
      flex: 1;
      padding: 20px 12px;
      overflow-y: auto;
    }
    
    /* Section Grouping */
    .nav-section {
      margin-bottom: 28px;
    }
    
    .nav-section:last-child {
      margin-bottom: 0;
    }
    
    .nav-section-title {
      font-size: 0.7rem;
      font-weight: 700;
      color: var(--muted-color);
      letter-spacing: 1px;
      text-transform: uppercase;
      padding: 0 12px 10px;
      margin-bottom: 6px;
      opacity: 0.6;
    }
    
    /* Navigation Items */
    .nav-item {
      display: flex !important;
      align-items: center;
      gap: 12px;
      padding: 12px 14px !important;
      margin-bottom: 4px;
      border-radius: 10px;
      color: var(--card-text) !important;
      text-decoration: none !important;
      font-weight: 500;
      font-size: 0.92rem;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }
    
    .nav-item::before {
      content: '';
      position: absolute;
      left: 0;
      top: 0;
      bottom: 0;
      width: 3px;
      background: linear-gradient(135deg, #667eea, #764ba2);
      transform: scaleY(0);
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      border-radius: 0 3px 3px 0;
    }
    
    .nav-item:hover {
      background: rgba(102, 126, 234, 0.08);
      transform: translateX(4px);
      padding-left: 18px !important;
    }
    
    .nav-item:hover .nav-icon {
      transform: scale(1.1) rotate(5deg);
      color: #667eea;
    }
    
    .nav-item.active {
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
      color: #667eea !important;
      font-weight: 600;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
    }
    
    .nav-item.active::before {
      transform: scaleY(1);
    }
    
    .nav-item.active .nav-icon {
      color: #667eea;
    }
    
    .nav-icon {
      flex-shrink: 0;
      transition: all 0.3s ease;
      color: var(--card-text);
      opacity: 0.8;
    }
    
    .nav-item span {
      flex: 1;
    }
    
    /* Logout Section */
    .sidebar-logout {
      padding: 16px 12px;
      border-top: 1px solid rgba(var(--card-text-rgb), 0.08);
      background: linear-gradient(180deg, transparent 0%, rgba(var(--card-text-rgb), 0.02) 100%);
    }
    
    .logout-btn {
      display: flex !important;
      align-items: center;
      gap: 12px;
      padding: 12px 14px !important;
      border-radius: 10px;
      color: #ef4444 !important;
      text-decoration: none !important;
      font-weight: 600;
      font-size: 0.92rem;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      background: rgba(239, 68, 68, 0.05);
      border: 1px solid rgba(239, 68, 68, 0.15);
    }
    
    .logout-btn:hover {
      background: rgba(239, 68, 68, 0.12);
      transform: translateX(4px);
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
    }
    
    .logout-btn svg {
      flex-shrink: 0;
      transition: transform 0.3s ease;
    }
    
    .logout-btn:hover svg {
      transform: translateX(3px);
    }
    
    /* Responsive */
    @media (max-width: 900px) {
      .header-left {
        gap: 12px;
      }
      
      .header-profile {
        padding: 6px 10px 6px 6px;
      }
      
      .brand-subtitle {
        display: none;
      }
      
      .header-search {
        max-width: 240px;
      }
      
      .sidebar-profile {
        padding: 20px 16px;
      }
      
      .profile-avatar-wrapper {
        width: 42px;
        height: 42px;
      }
      
      .profile-username {
        font-size: 0.95rem;
      }
    }
    
    @media (max-width: 1024px) {
      .header-left {
        gap: 8px;
      }
      
      .brand-content {
        display: none;
      }
      
      .header-profile-info {
        display: none;
      }
      
      .header-profile {
        padding: 6px;
      }
      
      .profile-name {
        display: none;
      }
      
      .header-search {
        max-width: 180px;
      }
      
      .sidebar-profile {
        padding: 18px 14px;
      }
      
      .profile-greeting {
        font-size: 0.7rem;
      }
      
      .nav-section-title {
        font-size: 0.65rem;
      }
      
      .nav-item {
        padding: 10px 12px !important;
        font-size: 0.88rem;
      }
      
      /* Mobile/Tablet sidebar toggle */
      .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        bottom: 0;
        z-index: 9999;
        width: var(--sidebar-width, 240px);
        transform: translateX(-100%);
        transition: transform 0.3s ease;
      }
      
      .sidebar.sidebar-open {
        transform: translateX(0);
        box-shadow: 8px 0 32px rgba(0, 0, 0, 0.3);
      }
      
      .sidebar::after {
        content: '';
        position: fixed;
        top: 0;
        left: 100%;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
      }
      
      .sidebar.sidebar-open::after {
        opacity: 1;
        pointer-events: auto;
      }
    }
    
    @media (min-width: 1025px) {
      /* Hide menu toggle on desktop */
      .menu-toggle {
        display: none;
      }
    }
  </style>
  </head>
  <?php
  // If user is logged in we show the header; otherwise hide header/sidebar
  $bodyClasses = [];
  if (!empty($_SESSION['user_id'])) $bodyClasses[] = 'has-header';
  else { $bodyClasses[] = 'no-header'; $bodyClasses[] = 'no-sidebar'; }
  ?>
  <body class="<?php echo implode(' ', $bodyClasses); ?>">

  <?php if (!empty($_SESSION['user_id'])): ?>
  <header class="site-header">
    <div class="container">
      <!-- Left Section - Matches Sidebar Profile -->
      <div class="header-left">
        <button id="menu-toggle" class="menu-toggle" aria-label="Toggle menu" aria-expanded="false">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>
        
        <a class="brand" href="/MY CASH/index.php">
          <div class="brand-icon">$</div>
          <div class="brand-content">
            <span class="brand-text">MY CASH</span>
            <span class="brand-subtitle">Financial Manager</span>
          </div>
        </a>
        
        <!-- User Profile Preview (matches sidebar) -->
        <div class="header-profile">
          <div class="header-profile-avatar">
            <?php if (!empty($avatarUrl)): ?>
              <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Profile">
            <?php else: ?>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
            <?php endif; ?>
          </div>
          <div class="header-profile-info">
            <div class="header-profile-name"><?php echo htmlspecialchars($profileName); ?></div>
            <div class="header-profile-role">Account Holder</div>
          </div>
        </div>
      </div>

      <div class="header-search">
        <input id="header-search-input" placeholder="Search transactions, accounts, people...">
        <button class="icon-button" aria-label="Search" type="button">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
          </svg>
        </button>
        <div class="search-suggestions" id="search-suggestions"></div>
      </div>

        <div class="header-actions">
        
        <?php if ($isAdmin): ?>
        <!-- Admin Only: Forex Trading Journal -->
        <div style="position:relative;display:inline-block">
          <button id="forex-menu-btn" class="icon-button" title="Forex Trading Journal" style="font-size:13px;padding:6px 12px;font-weight:600;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;border-radius:8px">
            📈 Forex
          </button>
          <div id="forex-menu-panel" class="dropdown-panel" style="min-width:200px;padding:8px;display:none">
            <a href="/MY CASH/pages/forex_journal.php" style="display:block;padding:8px 12px;text-decoration:none;color:inherit;border-radius:6px;transition:background 0.2s" onmouseover="this.style.background='rgba(102,126,234,0.1)'" onmouseout="this.style.background='transparent'">
              <div style="font-weight:600">� Forex Journal</div>
              <div style="font-size:12px;color:var(--muted-color)">Trading journal & overview</div>
            </a>
            <a href="/MY CASH/forex/add_trade.php" style="display:block;padding:8px 12px;text-decoration:none;color:inherit;border-radius:6px;transition:background 0.2s" onmouseover="this.style.background='rgba(102,126,234,0.1)'" onmouseout="this.style.background='transparent'">
              <div style="font-weight:600">➕ Add Trade</div>
              <div style="font-size:12px;color:var(--muted-color)">Record new trade</div>
            </a>
            <a href="/MY CASH/pages/forex_journal.php" style="display:block;padding:8px 12px;text-decoration:none;color:inherit;border-radius:6px;transition:background 0.2s" onmouseover="this.style.background='rgba(102,126,234,0.1)'" onmouseout="this.style.background='transparent'">
              <div style="font-weight:600">📝 Trade History</div>
              <div style="font-size:12px;color:var(--muted-color)">View all trades</div>
            </a>
            <a href="/MY CASH/forex/analytics.php" style="display:block;padding:8px 12px;text-decoration:none;color:inherit;border-radius:6px;transition:background 0.2s" onmouseover="this.style.background='rgba(102,126,234,0.1)'" onmouseout="this.style.background='transparent'">
              <div style="font-weight:600">📈 Analytics</div>
              <div style="font-size:12px;color:var(--muted-color)">Performance insights</div>
            </a>
          </div>
        </div>

        <!-- Admin Only: Business Management -->
        <div style="position:relative;display:inline-block;margin-left:8px">
          <button id="business-menu-btn" class="icon-button" title="Business Management" style="font-size:13px;padding:6px 12px;font-weight:600;background:linear-gradient(135deg,#764ba2 0%,#f093fb 100%);color:#fff;border-radius:8px">
            💼 Business
          </button>
          <div id="business-menu-panel" class="dropdown-panel" style="min-width:200px;padding:8px;display:none">
            <a href="/MY CASH/business/dashboard.php" style="display:block;padding:8px 12px;text-decoration:none;color:inherit;border-radius:6px;transition:background 0.2s" onmouseover="this.style.background='rgba(118,75,162,0.1)'" onmouseout="this.style.background='transparent'">
              <div style="font-weight:600">📊 Business Dashboard</div>
              <div style="font-size:12px;color:var(--muted-color)">Business overview</div>
            </a>
            <a href="/MY CASH/business/employees.php" style="display:block;padding:8px 12px;text-decoration:none;color:inherit;border-radius:6px;transition:background 0.2s" onmouseover="this.style.background='rgba(118,75,162,0.1)'" onmouseout="this.style.background='transparent'">
              <div style="font-weight:600">� Employee Hub</div>
              <div style="font-size:12px;color:var(--muted-color)">Payroll, attendance, inventory</div>
            </a>
            <a href="/MY CASH/business/projects.php" style="display:block;padding:8px 12px;text-decoration:none;color:inherit;border-radius:6px;transition:background 0.2s" onmouseover="this.style.background='rgba(118,75,162,0.1)'" onmouseout="this.style.background='transparent'">
              <div style="font-weight:600">📁 Projects</div>
              <div style="font-size:12px;color:var(--muted-color)">Project management</div>
            </a>
            <div style="height:1px;background:rgba(118,75,162,0.2);margin:8px 0"></div>
            <a href="/MY CASH/pages/chat.php" style="display:block;padding:8px 12px;text-decoration:none;color:inherit;border-radius:6px;transition:background 0.2s" onmouseover="this.style.background='rgba(118,75,162,0.1)'" onmouseout="this.style.background='transparent'">
              <div style="font-weight:600">💬 Team Chat</div>
              <div style="font-size:12px;color:var(--muted-color)">Employee messaging</div>
            </a>
          </div>
        </div>
        <?php endif; ?>
        
        <!-- Currency switcher: toggles between USD and RWF and converts amounts on the page -->
        <div style="position:relative;display:inline-block;margin-left:8px">
          <button id="currency-switcher" class="icon-button" title="Switch currency">USD ↔ RWF</button>
          <div id="currency-panel" class="dropdown-panel" style="min-width:220px;padding:12px;display:none">
            <div style="margin-bottom:8px"><strong>Convert amounts on page</strong></div>
            <label style="display:block;margin-bottom:6px">Target currency</label>
            <select id="currency-target"><option value="USD">USD</option><option value="RWF">RWF</option></select>
            <div style="margin-top:8px"><button id="currency-convert-visible" class="button">Convert visible amounts</button></div>
            <hr style="margin:8px 0;border:none;border-top:1px solid var(--border-weak)">
            <div style="font-size:13px">Quick convert</div>
            <input id="currency-quick-amount" placeholder="Amount" style="width:100%;margin-top:6px;padding:6px">
            <div style="margin-top:8px"><button id="currency-quick-convert" class="button">Convert</button></div>
            <div id="currency-quick-result" style="margin-top:8px;color:var(--muted-color)"></div>
          </div>
        </div>
        
        <!-- Global Notification Widget -->
        <?php if (!empty($_SESSION['user_id']) || !empty($_SESSION['employee_id'])): ?>
          <?php include __DIR__ . '/notification_widget.php'; ?>
        <?php endif; ?>
        
        <div style="position:relative;display:none">
          <!-- OLD notification button - hidden, replaced by widget above -->
          <button type="button" class="icon-button" id="notif-btn" aria-label="Notifications">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <?php if (!empty($notifCount)): ?><span class="icon-badge"><?php echo intval($notifCount); ?></span><?php endif; ?>
          </button>
          <div class="dropdown-panel" id="notif-panel">
            <?php
              // Fetch recent inventory alerts (up to 5 most recent)
              $alertPreview = [];
              try {
                if (!empty($conn)) {
                  $alertStmt = $conn->prepare("
                    SELECT ia.id, ia.item_name, ia.alert_date, ia.alert_time, ia.urgency, ia.status, ia.notes,
                           e.first_name, e.last_name
                    FROM inventory_alerts ia
                    LEFT JOIN employees e ON ia.employee_id = e.id
                    ORDER BY ia.alert_date DESC, ia.alert_time DESC
                    LIMIT 5
                  ");
                  $alertStmt->execute();
                  $alertPreview = $alertStmt->fetchAll(PDO::FETCH_ASSOC);
                }
              } catch (Exception $e) { 
                $alertPreview = []; 
              }
            ?>
            <?php if (empty($alertPreview)): ?>
              <div class="muted small" style="padding:8px">No recent notifications</div>
            <?php else: ?>
              <ul style="list-style:none;padding:8px;margin:0;max-height:400px;overflow-y:auto"> 
                <?php foreach($alertPreview as $alert): 
                  $isUnread = empty($alert['status']) || $alert['status'] === 'pending';
                  $urgencyColor = $alert['urgency'] === 'critical' ? '#ef4444' : ($alert['urgency'] === 'high' ? '#f59e0b' : '#10b981');
                  $employeeName = trim(($alert['first_name'] ?? '') . ' ' . ($alert['last_name'] ?? ''));
                  $timeStr = date('M j, g:i A', strtotime($alert['alert_date'] . ' ' . $alert['alert_time']));
                ?>
                  <li style="padding:10px;border-bottom:1px solid var(--border-weak);cursor:pointer;<?=$isUnread ? 'background:rgba(102,126,234,0.05);' : ''?>" 
                      onclick="window.location.href='/MY CASH/business/inventory_alerts.php?id=<?=$alert['id']?>'">
                    <div style="display:flex;gap:10px;align-items:start">
                      <span style="font-size:20px;margin-top:2px">⚠️</span>
                      <div style="flex:1">
                        <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                          <div style="font-weight:600;font-size:14px"><?=htmlspecialchars($alert['item_name'])?></div>
                          <span style="padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;background:<?=$urgencyColor?>;color:white">
                            <?=strtoupper($alert['urgency'])?>
                          </span>
                        </div>
                        <div class="muted small" style="margin-bottom:4px"><?=htmlspecialchars($alert['notes'] ?: 'Stock alert')?></div>
                        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted-color)">
                          <span>Reported by: <?=htmlspecialchars($employeeName ?: 'Employee')?></span>
                          <span><?=$timeStr?></span>
                        </div>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <hr style="border:none;border-top:1px solid var(--border-weak);margin:8px 0">
            <a href="/MY CASH/business/inventory_alerts.php" style="display:block;padding:8px;text-align:center;font-weight:600;color:#667eea;text-decoration:none">View All Alerts →</a>
          </div>
        </div>

        <div style="position:relative">
          <button type="button" class="icon-button" id="msg-btn" aria-label="Messages">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
              <polyline points="22,6 12,13 2,6"></polyline>
            </svg>
            <?php if (!empty($msgCount)): ?><span class="icon-badge"><?php echo intval($msgCount); ?></span><?php endif; ?>
          </button>
          <div class="dropdown-panel" id="msg-panel">
            <?php
              $mpreview = [];
              try {
                if (file_exists($mpath)) {
                  $mdata = json_decode(file_get_contents($mpath), true) ?: [];
                  $munread = array_values(array_filter($mdata, function($i){ return empty($i['is_read']); }));
                  $mread = array_values(array_filter($mdata, function($i){ return !empty($i['is_read']); }));
                  $mslice = array_slice(array_reverse($munread), 0, 3);
                  if (count($mslice) < 3) {
                    $morem = array_slice(array_reverse($mread), 0, 3 - count($mslice));
                    $mslice = array_merge($mslice, $morem);
                  }
                  $mpreview = $mslice;
                }
              } catch (Exception $e) { $mpreview = []; }
            ?>
            <?php if (empty($mpreview)): ?>
              <div class="muted small" style="padding:8px">No recent messages</div>
            <?php else: ?>
              <ul style="list-style:none;padding:8px;margin:0;"> 
                <?php foreach($mpreview as $pm): ?>
                  <li style="padding:6px;border-bottom:1px solid var(--border-weak);cursor:pointer" data-id="<?=htmlspecialchars($pm['id'] ?? '')?>" data-read="<?=empty($pm['is_read'])?0:1?>">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                      <div style="display:flex;gap:8px;align-items:center">
                        <span class="message-icon">✉️</span>
                        <div>
                          <div style="font-weight:600"><?=htmlspecialchars($pm['subject'] ?? '')?></div>
                          <div class="muted small"><?=htmlspecialchars($pm['preview'] ?? '')?></div>
                        </div>
                      </div>
                      <div class="muted small"><?=htmlspecialchars($pm['time'] ?? '')?></div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <hr style="border:none;border-top:1px solid var(--border-weak);margin:8px 0">
            <a href="/MY CASH/pages/messages.php">View all messages</a>
          </div>
        </div>

      </div>
      <!-- Theme toggle moved to the far right for easier access -->
      <div style="margin-left:auto">
        <button id="theme-toggle" class="icon-button" title="Toggle theme">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
          </svg>
        </button>
      </div>
    </div>
  </header>
  <?php endif; ?>

  <script>
    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
    // Header live search: suggestions and navigation
    (function(){
      var input = document.getElementById('header-search-input');
      var btn = document.querySelector('.header-search .icon-button');
      var sug = document.getElementById('search-suggestions');
      var timer = null;
      function render(items){
        sug.innerHTML = '';
        if (!items || !items.length) { sug.style.display = 'none'; return; }
        items.forEach(function(it){
          var a = document.createElement('a');
          a.className = 'search-suggestion-item';
          var text = (it.type || '') + ' — ' + (it.title || '');
          a.textContent = text;
          a.href = it.url || ('/MY CASH/pages/search.php?q='+encodeURIComponent(input.value));
          sug.appendChild(a);
        });
        sug.style.display = 'block';
      }
      function fetchSuggestions(q){
        if (!q) { render([]); return; }
  fetch('/MY CASH/pages/search.php?ajax=1&q='+encodeURIComponent(q), {cache:'no-store'}).then(function(r){ return r.json(); }).then(function(j){ render(j || []); }).catch(function(){ render([]); });
      }
      input.addEventListener('input', function(){ clearTimeout(timer); timer = setTimeout(function(){ fetchSuggestions(input.value.trim()); }, 250); });
  input.addEventListener('keydown', function(e){ if (e.key === 'Enter'){ window.location = '/MY CASH/pages/search.php?q='+encodeURIComponent(input.value.trim()); } });
  if (btn) btn.addEventListener('click', function(){ window.location = '/MY CASH/pages/search.php?q='+encodeURIComponent(input.value.trim()); });
      document.addEventListener('click', function(e){ if (!sug.contains(e.target) && e.target !== input) sug.style.display = 'none'; });
    })();
    // Header notification interactions: toggle dropdown and mark preview items read
    (function(){
      var notifBtn = document.getElementById('notif-btn');
      var notifPanel = document.getElementById('notif-panel');
      var msgBtn = document.getElementById('msg-btn');
      var msgPanel = document.getElementById('msg-panel');
      // notification handlers
      if (notifBtn && notifPanel) {
        notifBtn.addEventListener('click', function(e){ e.stopPropagation(); notifPanel.style.display = notifPanel.style.display === 'block' ? 'none' : 'block'; });
        document.addEventListener('click', function(){ if (notifPanel) notifPanel.style.display = 'none'; });
        notifPanel.querySelectorAll('li[data-id]').forEach(function(li){
          li.addEventListener('click', function(ev){
            ev.stopPropagation();
            var id = li.getAttribute('data-id');
            var isRead = li.getAttribute('data-read') === '1';
            if (!id || isRead) return;
            fetch('/MY CASH/pages/mark_notification_read.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'id='+encodeURIComponent(id)}).then(function(resp){ return resp.json(); }).then(function(j){
              if (j && j.ok) {
                var b = notifBtn.querySelector('.icon-badge');
                if (b) { var v = parseInt(b.textContent||'0') - 1; if (v<=0){ b.parentNode.removeChild(b); } else b.textContent = v; }
                li.setAttribute('data-read','1'); li.style.opacity = 0.6;
              }
            }).catch(function(){});
          });
        });
      }
      // message handlers
      if (msgBtn && msgPanel) {
        msgBtn.addEventListener('click', function(e){ e.stopPropagation(); msgPanel.style.display = msgPanel.style.display === 'block' ? 'none' : 'block'; });
        document.addEventListener('click', function(){ if (msgPanel) msgPanel.style.display = 'none'; });
        msgPanel.querySelectorAll('li[data-id]').forEach(function(li){
          li.addEventListener('click', function(ev){
            ev.stopPropagation();
            var id = li.getAttribute('data-id');
            var isRead = li.getAttribute('data-read') === '1';
            if (!id || isRead) return;
            fetch('/MY CASH/pages/mark_message_read.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'id='+encodeURIComponent(id)}).then(function(resp){ return resp.json(); }).then(function(j){
              if (j && j.ok) {
                var b = msgBtn.querySelector('.icon-badge');
                if (b) { var v = parseInt(b.textContent||'0') - 1; if (v<=0){ b.parentNode.removeChild(b); } else b.textContent = v; }
                li.setAttribute('data-read','1'); li.style.opacity = 0.6;
              }
            }).catch(function(){});
          });
        });
      }
      // Forex and Business menu handlers (admin only)
      var forexBtn = document.getElementById('forex-menu-btn');
      var forexPanel = document.getElementById('forex-menu-panel');
      var businessBtn = document.getElementById('business-menu-btn');
      var businessPanel = document.getElementById('business-menu-panel');
      if (forexBtn && forexPanel) {
        forexBtn.addEventListener('click', function(e){ 
          e.stopPropagation(); 
          forexPanel.style.display = forexPanel.style.display === 'block' ? 'none' : 'block'; 
          if (businessPanel) businessPanel.style.display = 'none'; 
        });
        document.addEventListener('click', function(){ if (forexPanel) forexPanel.style.display = 'none'; });
      }
      if (businessBtn && businessPanel) {
        businessBtn.addEventListener('click', function(e){ 
          e.stopPropagation(); 
          businessPanel.style.display = businessPanel.style.display === 'block' ? 'none' : 'block'; 
          if (forexPanel) forexPanel.style.display = 'none'; 
        });
        document.addEventListener('click', function(){ if (businessPanel) businessPanel.style.display = 'none'; });
      }
    })();
    
    // Menu toggle functionality
    (function(){
      var menuToggle = document.getElementById('menu-toggle');
      var sidebar = document.getElementById('app-sidebar');
      
      console.log('Menu toggle:', menuToggle);
      console.log('Sidebar:', sidebar);
      
      if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function(e){
          e.stopPropagation();
          var isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
          
          console.log('Menu clicked, isExpanded:', isExpanded);
          
          if (isExpanded) {
            sidebar.classList.remove('sidebar-open');
            menuToggle.setAttribute('aria-expanded', 'false');
          } else {
            sidebar.classList.add('sidebar-open');
            menuToggle.setAttribute('aria-expanded', 'true');
          }
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e){
          if (window.innerWidth <= 1024 && 
              sidebar.classList.contains('sidebar-open') && 
              !sidebar.contains(e.target) && 
              e.target !== menuToggle && 
              !menuToggle.contains(e.target)) {
            sidebar.classList.remove('sidebar-open');
            menuToggle.setAttribute('aria-expanded', 'false');
          }
        });
      } else {
        console.error('Menu toggle or sidebar not found!');
      }
    })();
    
    // Theme toggle functionality
    (function(){
      var themeToggle = document.getElementById('theme-toggle');
      var sunIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>';
      var moonIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>';
      
      console.log('Theme toggle button:', themeToggle);
      
      if (themeToggle) {
        // Load saved theme or default to light
        var currentTheme = localStorage.getItem('theme') || 'light';
        document.body.setAttribute('data-theme', currentTheme);
        themeToggle.innerHTML = currentTheme === 'dark' ? moonIcon : sunIcon;
        
        console.log('Initial theme loaded:', currentTheme);
        
        themeToggle.addEventListener('click', function(e){
          e.preventDefault();
          e.stopPropagation();
          
          var currentAttr = document.body.getAttribute('data-theme');
          var newTheme = currentAttr === 'dark' ? 'light' : 'dark';
          
          console.log('Switching theme from', currentAttr, 'to', newTheme);
          
          document.body.setAttribute('data-theme', newTheme);
          localStorage.setItem('theme', newTheme);
          themeToggle.innerHTML = newTheme === 'dark' ? moonIcon : sunIcon;
          
          // Add rotation animation
          themeToggle.style.transition = 'transform 0.5s ease';
          themeToggle.style.transform = 'rotate(360deg)';
          setTimeout(function(){ 
            themeToggle.style.transform = 'rotate(0deg)'; 
          }, 500);
        });
        
        console.log('Theme toggle initialized successfully');
      } else {
        console.error('Theme toggle button not found!');
      }
    })();
    
    // Sidebar scroll position persistence
    (function(){
      var sidebar = document.getElementById('app-sidebar');
      
      if (sidebar) {
        // Restore scroll position on page load
        var savedScrollPos = sessionStorage.getItem('sidebarScrollPos');
        if (savedScrollPos !== null) {
          sidebar.scrollTop = parseInt(savedScrollPos);
        }
        
        // Save scroll position before navigation
        var navLinks = sidebar.querySelectorAll('.nav-item');
        navLinks.forEach(function(link) {
          link.addEventListener('click', function(e) {
            // Save current scroll position
            sessionStorage.setItem('sidebarScrollPos', sidebar.scrollTop);
          });
        });
        
        // Also save on any scroll to catch manual scrolling
        var scrollTimeout;
        sidebar.addEventListener('scroll', function() {
          clearTimeout(scrollTimeout);
          scrollTimeout = setTimeout(function() {
            sessionStorage.setItem('sidebarScrollPos', sidebar.scrollTop);
          }, 100);
        });
        
        console.log('Sidebar scroll persistence initialized');
      }
    })();
    
    }); // End DOMContentLoaded
  </script>

  <div class="app">
    <?php if(!empty($_SESSION['user_id'])): ?>
    <aside class="sidebar" id="app-sidebar">
      <!-- Navigation Sections -->
      <nav class="sidebar-nav">
        <!-- Main Section -->
        <div class="nav-section">
          <div class="nav-section-title">MAIN</div>
          <a href="/MY CASH/pages/dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' && strpos($_SERVER['PHP_SELF'], '/business/') === false && strpos($_SERVER['PHP_SELF'], '/forex/') === false ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="7" height="7"></rect>
              <rect x="14" y="3" width="7" height="7"></rect>
              <rect x="14" y="14" width="7" height="7"></rect>
              <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span>Dashboard</span>
          </a>
          <a href="/MY CASH/pages/reports.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="20" x2="12" y2="10"></line>
              <line x1="18" y1="20" x2="18" y2="4"></line>
              <line x1="6" y1="20" x2="6" y2="16"></line>
            </svg>
            <span>Reports</span>
          </a>
          <a href="/MY CASH/pages/search.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'search.php' ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <span>Search</span>
          </a>
        </div>

        <!-- Finance Section -->
        <div class="nav-section">
          <div class="nav-section-title">FINANCE</div>
          <a href="/MY CASH/pages/accounts.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'accounts.php' || basename($_SERVER['PHP_SELF']) === 'account.php' ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="5" width="20" height="14" rx="2"></rect>
              <line x1="2" y1="10" x2="22" y2="10"></line>
            </svg>
            <span>Accounts</span>
          </a>
          <a href="/MY CASH/pages/budgets.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'budgets.php' || basename($_SERVER['PHP_SELF']) === 'budget_settings.php' ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"></path>
              <path d="M12 18V6"></path>
            </svg>
            <span>Budgets</span>
          </a>
          <a href="/MY CASH/pages/loans.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'loans.php' ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
              <line x1="1" y1="10" x2="23" y2="10"></line>
            </svg>
            <span>Loans</span>
          </a>
          <a href="/MY CASH/pages/goals.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'goals.php' ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <circle cx="12" cy="12" r="6"></circle>
              <circle cx="12" cy="12" r="2"></circle>
            </svg>
            <span>Goals</span>
          </a>
        </div>

        <!-- Planning Section -->
        <div class="nav-section">
          <div class="nav-section-title">PLANNING</div>
          <a href="/MY CASH/pages/projects.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'projects.php' || basename($_SERVER['PHP_SELF']) === 'add_project.php' || basename($_SERVER['PHP_SELF']) === 'view_project.php' ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
              <line x1="12" y1="11" x2="12" y2="17"></line>
              <line x1="9" y1="14" x2="15" y2="14"></line>
            </svg>
            <span>Projects</span>
          </a>
        </div>

        <?php if ($isAdmin): ?>
        <!-- Forex Trading Section (Admin Only) -->
        <div class="nav-section">
          <div class="nav-section-title">FOREX TRADING</div>
          <a href="/MY CASH/pages/forex_journal.php" class="nav-item <?php echo strpos($_SERVER['PHP_SELF'], '/pages/forex_journal.php') !== false ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="1" x2="12" y2="23"></line>
              <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
            <span>Forex Journal</span>
          </a>
          <a href="/MY CASH/forex/analytics.php" class="nav-item <?php echo strpos($_SERVER['PHP_SELF'], '/forex/analytics.php') !== false ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="20" x2="18" y2="10"></line>
              <line x1="12" y1="20" x2="12" y2="4"></line>
              <line x1="6" y1="20" x2="6" y2="14"></line>
            </svg>
            <span>Analytics</span>
          </a>
        </div>

      <!-- Business Management Section (Admin Only) -->
      <div class="nav-section">
        <div class="nav-section-title">BUSINESS</div>
        <a href="/MY CASH/business/dashboard.php" class="nav-item <?php echo strpos($_SERVER['PHP_SELF'], '/business/dashboard.php') !== false || strpos($_SERVER['PHP_SELF'], '/business/financial_dashboard.php') !== false ? 'active' : ''; ?>">
          <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
            <path d="M3 9h18"></path>
            <path d="M9 21V9"></path>
          </svg>
          <span>Business Dashboard</span>
        </a>
        <a href="/MY CASH/business/employees.php" class="nav-item <?php echo strpos($_SERVER['PHP_SELF'], '/business/employees.php') !== false || strpos($_SERVER['PHP_SELF'], '/business/add_employee.php') !== false || strpos($_SERVER['PHP_SELF'], '/pages/employee_') !== false || strpos($_SERVER['PHP_SELF'], '/business/payroll.php') !== false || strpos($_SERVER['PHP_SELF'], '/business/inventory_alerts.php') !== false || strpos($_SERVER['PHP_SELF'], '/business/manage_products.php') !== false ? 'active' : ''; ?>">
          <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          <span>Employees</span>
        </a>
      </div>
      <?php endif; ?>        <!-- Communication Section -->
        <div class="nav-section">
          <div class="nav-section-title">COMMUNICATION</div>
          <a href="/MY CASH/pages/chat.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'chat.php' ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <span>Team Chat</span>
          </a>
          <a href="/MY CASH/pages/messages.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'messages.php' ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
              <polyline points="22,6 12,13 2,6"></polyline>
            </svg>
            <span>Messages</span>
          </a>
          <a href="/MY CASH/pages/notifications.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'notifications.php' ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <span>Notifications</span>
          </a>
        </div>

        <!-- AI & Tools Section -->
        <div class="nav-section">
          <div class="nav-section-title">AI & TOOLS</div>
          <a href="/MY CASH/pages/ai.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'ai.php' ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <circle cx="12" cy="12" r="3"></circle>
              <line x1="12" y1="2" x2="12" y2="9"></line>
              <line x1="12" y1="15" x2="12" y2="22"></line>
              <line x1="4.93" y1="4.93" x2="9.17" y2="9.17"></line>
              <line x1="14.83" y1="14.83" x2="19.07" y2="19.07"></line>
            </svg>
            <span>AI Assistant</span>
          </a>
          <?php if ($isAdmin): ?>
          <a href="/MY CASH/pages/ai_settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'ai_settings.php' ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="3"></circle>
              <path d="M12 1v6m0 6v6m5.656-13.656l-4.243 4.243m-2.828 2.828l-4.243 4.243m16.97-1.414l-6-6m-6-6l-6 6m13.656 5.656l-4.243-4.243m-2.828-2.828l-4.243-4.243"></path>
            </svg>
            <span>AI Settings</span>
          </a>
          <?php endif; ?>
        </div>

        <!-- Settings Section -->
        <div class="nav-section">
          <div class="nav-section-title">ACCOUNT</div>
          <a href="/MY CASH/pages/profile.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <span>Profile</span>
          </a>
          <a href="/MY CASH/pages/settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
            <svg class="nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="3"></circle>
              <path d="M12 1v6m0 6v6"></path>
              <path d="m4.93 4.93 4.24 4.24m5.66 5.66 4.24 4.24m-4.24-14.14 4.24-4.24m-9.9 9.9-4.24-4.24"></path>
            </svg>
            <span>Settings</span>
          </a>
        </div>
      </nav>

      <!-- Logout Section -->
      <div class="sidebar-logout">
        <a href="/MY CASH/pages/logout.php" class="logout-btn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
            <polyline points="16 17 21 12 16 7"></polyline>
            <line x1="21" y1="12" x2="9" y2="12"></line>
          </svg>
          <span>Logout</span>
        </a>
      </div>
    </aside>
    <?php endif; ?>

    <!-- Floating Chat Button -->
    <?php
    // Determine correct chat URL based on user type
    $chatUrl = '/MY CASH/pages/chat.php'; // Default for admin
    if (!empty($_SESSION['employee_id'])) {
      // Employee logged in
      $chatUrl = '/MY CASH/employee/chat.php';
    } elseif (!empty($_SESSION['user_id'])) {
      // Check if admin user
      if (!empty($isAdmin)) {
        $chatUrl = '/MY CASH/pages/chat.php';
      } else {
        // Regular user (non-admin) - could redirect to messages or a general chat
        $chatUrl = '/MY CASH/pages/messages.php'; // or create a general chat page
      }
    }
    ?>
    <a href="<?php echo htmlspecialchars($chatUrl); ?>" class="floating-chat-btn" title="Team Chat" aria-label="Open Team Chat">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
      </svg>
      <span class="chat-badge" id="chat-badge" style="display: none;">0</span>
    </a>

    <style>
      .floating-chat-btn {
        position: fixed;
        bottom: 24px;
        right: 24px;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        cursor: pointer;
        transition: all 0.3s ease;
        z-index: 1000;
        text-decoration: none;
        color: white;
      }

      .floating-chat-btn:hover {
        transform: translateY(-4px) scale(1.05);
        box-shadow: 0 12px 32px rgba(102, 126, 234, 0.6);
      }

      .floating-chat-btn:active {
        transform: translateY(-2px) scale(1.02);
      }

      .floating-chat-btn svg {
        width: 28px;
        height: 28px;
        stroke: white;
      }

      .chat-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: #ef4444;
        color: white;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 10px;
        min-width: 20px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
      }

      /* Responsive adjustments */
      @media (max-width: 768px) {
        .floating-chat-btn {
          bottom: 16px;
          right: 16px;
          width: 56px;
          height: 56px;
        }

        .floating-chat-btn svg {
          width: 24px;
          height: 24px;
        }
      }

      /* Adjust for sidebar */
      body.has-sidebar .floating-chat-btn {
        right: 24px;
      }

      @media (min-width: 769px) {
        body.has-sidebar .floating-chat-btn {
          right: 24px;
        }
      }

      /* Animation */
      @keyframes pulse-chat {
        0%, 100% {
          box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        }
        50% {
          box-shadow: 0 8px 32px rgba(102, 126, 234, 0.7);
        }
      }

      .floating-chat-btn.has-messages {
        animation: pulse-chat 2s ease-in-out infinite;
      }
    </style>

    <div>
      <main class="container" role="main" id="app-main">

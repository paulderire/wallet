<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Check if user is logged in (either as user or employee)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['employee_id'])) {
  header("Location: /MY CASH/pages/login.php");
  exit;
}

// If employee, redirect to employee chat page
if (isset($_SESSION['employee_id']) && !isset($_SESSION['user_id'])) {
  header("Location: /MY CASH/employee/chat.php");
  exit;
}

include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/header.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$isAdmin = $_SESSION['is_admin'] ?? 0;

// Auto-create tables
try {
  $schema = file_get_contents(__DIR__ . '/../db/chat_schema.sql');
  $statements = array_filter(array_map('trim', explode(';', $schema)));
  foreach ($statements as $statement) {
    if (!empty($statement)) $conn->exec($statement);
  }
} catch (Exception $e) {}

// Get employees AND users/admins for new chat
$employees = [];
try {
  // Combine employees and users in one query with UNION
  $query = "
    SELECT id, CONCAT(first_name, ' ', last_name) as full_name, email, role as position, 'employee' as participant_type
    FROM employees 
    WHERE status = 'active'
    UNION
    SELECT id, name as full_name, email, 
           CASE WHEN is_admin = 1 THEN 'Admin' ELSE 'User' END as position,
           'user' as participant_type
    FROM users 
    WHERE id != " . intval($user_id) . "
    ORDER BY full_name ASC
  ";
  
  $stmt = $conn->query($query);
  $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  // Fallback to just employees if UNION fails
  try {
    $stmt = $conn->query("SELECT id, CONCAT(first_name, ' ', last_name) as full_name, email, role as position, 'employee' as participant_type FROM employees WHERE status = 'active'");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e2) {}
}
?>

<style>
  /* Dark Mode Support */
  :root {
    --chat-bg-gradient-start: #667eea;
    --chat-bg-gradient-end: #764ba2;
    --chat-card-bg: rgba(255, 255, 255, 0.98);
    --chat-text-primary: #1e293b;
    --chat-text-secondary: #64748b;
    --chat-border: #e2e8f0;
    --chat-message-bg: #f8fafc;
    --chat-hover-bg: rgba(248, 250, 252, 0.8);
    --chat-input-bg: #ffffff;
  }

  [data-theme="dark"] {
    --chat-bg-gradient-start: #1a1a2e;
    --chat-bg-gradient-end: #16213e;
    --chat-card-bg: rgba(30, 30, 46, 0.95);
    --chat-text-primary: #e2e8f0;
    --chat-text-secondary: #94a3b8;
    --chat-border: #334155;
    --chat-message-bg: #1e293b;
    --chat-hover-bg: rgba(148, 163, 184, 0.1);
    --chat-input-bg: rgba(30, 30, 46, 0.8);
  }

  /* Main Layout */
  #app-main {
    background: linear-gradient(135deg, var(--chat-bg-gradient-start) 0%, var(--chat-bg-gradient-end) 100%);
    min-height: calc(100vh - 64px);
    padding: 32px;
    margin: 0;
    width: 100%;
    max-width: none;
  }

  .chat-wrapper { 
    max-width: 1600px;
    margin: 0 auto;
  }

  .page-header { 
    background: var(--chat-card-bg);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 32px 40px;
    margin-bottom: 32px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    border: 1px solid var(--chat-border);
  }

  .page-title { 
    font-size: 2.5rem;
    font-weight: 900;
    background: linear-gradient(135deg, var(--chat-bg-gradient-start) 0%, var(--chat-bg-gradient-end) 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 8px;
    letter-spacing: -0.5px;
  }

  [data-theme="dark"] .page-title {
    background: linear-gradient(135deg, #667eea 0%, #a78bfa 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .page-subtitle {
    color: var(--chat-text-secondary);
    font-size: 1.1rem;
    font-weight: 500;
  }

  /* Chat Container */
  .chat-container { 
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 24px;
    height: calc(100vh - 280px);
    min-height: 600px;
  }

  /* Sidebar */
  .chat-sidebar { 
    background: var(--chat-card-bg);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 24px;
    overflow-y: auto;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    border: 1px solid var(--chat-border);
  }

  .chat-sidebar::-webkit-scrollbar {
    width: 8px;
  }

  .chat-sidebar::-webkit-scrollbar-track {
    background: transparent;
  }

  .chat-sidebar::-webkit-scrollbar-thumb {
    background: rgba(102, 126, 234, 0.3);
    border-radius: 4px;
  }

  .chat-sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(102, 126, 234, 0.5);
  }

  /* Main Chat Area */
  .chat-main { 
    background: var(--chat-card-bg);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    border: 1px solid var(--chat-border);
    overflow: hidden;
  }

  /* Room Items */
  .room-item { 
    padding: 16px 18px;
    border-radius: 14px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    border: 2px solid transparent;
    background: var(--chat-hover-bg);
  }

  .room-item:hover { 
    background: var(--chat-hover-bg);
    border-color: rgba(102, 126, 234, 0.3);
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
  }

  .room-item.active { 
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.15), rgba(118, 75, 162, 0.15));
    border-color: #667eea;
    box-shadow: 0 4px 16px rgba(102, 126, 234, 0.25);
    transform: translateX(4px);
  }

  .room-name { 
    font-weight: 700;
    color: var(--chat-text-primary);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.05rem;
  }

  .room-last-message { 
    font-size: 0.9rem;
    color: var(--chat-text-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    padding-right: 40px;
  }

  .room-unread { 
    position: absolute;
    top: 16px;
    right: 16px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    border-radius: 12px;
    min-width: 26px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0 8px;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
  }

  /* Chat Header */
  .chat-header { 
    padding: 28px 32px;
    border-bottom: 2px solid var(--chat-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--chat-hover-bg);
  }

  .chat-header-title { 
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--chat-text-primary);
    display: flex;
    align-items: center;
    gap: 12px;
  }

  /* Messages Container */
  .chat-messages { 
    flex: 1;
    padding: 28px 32px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 16px;
    background: rgba(248, 250, 252, 0.5);
  }

  [data-theme="dark"] .chat-messages {
    background: rgba(15, 23, 42, 0.3);
  }

  .chat-messages::-webkit-scrollbar {
    width: 10px;
  }

  .chat-messages::-webkit-scrollbar-track {
    background: transparent;
  }

  .chat-messages::-webkit-scrollbar-thumb {
    background: rgba(102, 126, 234, 0.2);
    border-radius: 5px;
  }

  .chat-messages::-webkit-scrollbar-thumb:hover {
    background: rgba(102, 126, 234, 0.4);
  }

  /* Messages */
  .message { 
    max-width: 70%;
    padding: 16px 20px;
    border-radius: 18px;
    position: relative;
    animation: fadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  }

  @keyframes fadeIn { 
    from { 
      opacity: 0;
      transform: translateY(15px) scale(0.95);
    }
    to { 
      opacity: 1;
      transform: translateY(0) scale(1);
    }
  }

  .message.sent { 
    align-self: flex-end;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-bottom-right-radius: 6px;
    box-shadow: 0 4px 16px rgba(102, 126, 234, 0.35);
  }

  .message.received { 
    align-self: flex-start;
    background: #ffffff;
    color: var(--chat-text-primary);
    border-bottom-left-radius: 6px;
    border: 2px solid var(--chat-border);
  }

  [data-theme="dark"] .message.received {
    background: var(--chat-message-bg);
  }

  .message-sender { 
    font-size: 0.8rem;
    font-weight: 700;
    margin-bottom: 8px;
    opacity: 0.9;
  }

  .message.received .message-sender {
    color: #667eea;
  }

  [data-theme="dark"] .message.received .message-sender {
    color: #a78bfa;
  }

  .message-text { 
    word-wrap: break-word;
    line-height: 1.6;
    font-size: 1rem;
  }

  .message-time { 
    font-size: 0.75rem;
    opacity: 0.7;
    margin-top: 8px;
    text-align: right;
  }

  /* Chat Input */
  .chat-input-container { 
    padding: 24px 32px;
    border-top: 2px solid var(--chat-border);
    background: var(--chat-hover-bg);
  }

  .chat-input-form { 
    display: flex;
    gap: 14px;
    align-items: center;
  }

  .chat-input { 
    flex: 1;
    padding: 16px 20px;
    border: 2px solid var(--chat-border);
    border-radius: 14px;
    font-size: 1rem;
    transition: all 0.3s;
    background: var(--chat-input-bg);
    color: var(--chat-text-primary);
  }

  .chat-input:focus { 
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.12);
    transform: translateY(-1px);
  }

  .btn-send { 
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 16px 32px;
    border: none;
    border-radius: 14px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
  }

  .btn-send:hover { 
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.45);
  }

  .btn-send:active {
    transform: translateY(-1px);
  }

  /* New Chat Button */
  .btn-new-chat { 
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 14px 24px;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    width: 100%;
    margin-bottom: 24px;
    transition: all 0.3s;
    box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
  }

  .btn-new-chat:hover { 
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.45);
  }

  /* Typing Indicator */
  .typing-indicator { 
    padding: 0 32px 16px;
    font-size: 0.9rem;
    color: #667eea;
    font-style: italic;
    min-height: 32px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .typing-indicator::before {
    content: '●';
    animation: typingPulse 1.5s ease-in-out infinite;
    font-size: 1.2rem;
  }

  @keyframes typingPulse {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 1; }
  }

  /* Empty State */
  .empty-state { 
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--chat-text-secondary);
  }

  .empty-state-icon { 
    font-size: 6rem;
    margin-bottom: 24px;
    opacity: 0.4;
    animation: float 3s ease-in-out infinite;
  }

  @keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
  }

  .empty-state h3 {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--chat-text-secondary);
    margin-bottom: 12px;
  }

  .empty-state p {
    font-size: 1.1rem;
    color: var(--chat-text-secondary);
    opacity: 0.8;
  }

  /* Modal */
  .modal { 
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(8px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    animation: fadeInModal 0.3s;
  }

  @keyframes fadeInModal {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  .modal-content { 
    background: var(--chat-card-bg);
    border-radius: 24px;
    padding: 40px;
    max-width: 520px;
    width: 90%;
    box-shadow: 0 24px 80px rgba(0, 0, 0, 0.4);
    animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid var(--chat-border);
  }

  @keyframes slideUp {
    from { 
      transform: translateY(30px);
      opacity: 0;
    }
    to { 
      transform: translateY(0);
      opacity: 1;
    }
  }

  .modal-title { 
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 28px;
    color: var(--chat-text-primary);
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .modal-title svg {
    stroke: #667eea;
  }

  [data-theme="dark"] .modal-title svg {
    stroke: #a78bfa;
  }

  .form-group { 
    margin-bottom: 24px;
  }

  .form-label { 
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: var(--chat-text-primary);
    font-size: 1rem;
  }

  .form-select { 
    width: 100%;
    padding: 14px 18px;
    border: 2px solid var(--chat-border);
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s;
    background: var(--chat-input-bg);
    color: var(--chat-text-primary);
    cursor: pointer;
  }

  .form-select:focus { 
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.12);
  }

  .modal-actions { 
    display: flex;
    gap: 14px;
    margin-top: 32px;
  }

  .btn-cancel { 
    background: #e2e8f0;
    color: #475569;
    padding: 14px 28px;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    flex: 1;
    transition: all 0.2s;
  }

  .btn-cancel:hover {
    background: #cbd5e1;
    transform: translateY(-2px);
  }

  /* Responsive */
  @media (max-width: 1024px) { 
    .chat-container { 
      grid-template-columns: 1fr;
      height: auto;
    }
    
    .chat-main { 
      height: 650px;
    }

    .page-title {
      font-size: 2rem;
    }

    #app-main {
      padding: 20px;
    }
  }

  @media (max-width: 768px) {
    .page-header {
      padding: 24px 28px;
    }

    .page-title {
      font-size: 1.75rem;
    }

    .chat-messages {
      padding: 20px 16px;
    }

    .chat-input-container {
      padding: 16px;
    }

    .message {
      max-width: 85%;
    }
  }
</style>

<div class="chat-wrapper">
  <div class="page-header">
    <h1 class="page-title">
      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display:inline-block;vertical-align:middle;margin-right:8px">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
      </svg>
      Team Chat
    </h1>
    <p class="page-subtitle">Communicate with your team in real-time</p>
  </div>

  <div class="chat-container">
    <!-- Sidebar -->
    <div class="chat-sidebar">
      <button class="btn-new-chat" onclick="showNewChatModal()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display:inline-block;vertical-align:middle;margin-right:6px">
          <path d="M12 5v14M5 12h14"/>
        </svg>
        Start New Chat
      </button>
      <div id="rooms-list">
        <div style="text-align:center;padding:40px 20px;color:#cbd5e0">
          <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.4;margin:0 auto">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
          </svg>
          <p style="margin-top:16px;font-weight:500">Loading conversations...</p>
        </div>
      </div>
    </div>

    <!-- Main Chat Area -->
    <div class="chat-main">
      <div id="no-chat-selected" class="empty-state">
        <div class="empty-state-icon">
          <svg width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            <line x1="9" y1="10" x2="15" y2="10"></line>
            <line x1="9" y1="14" x2="13" y2="14"></line>
          </svg>
        </div>
        <h3 style="font-size:1.5rem;margin-bottom:12px">Select a conversation</h3>
        <p style="font-size:1rem">Choose from the sidebar or start a new chat</p>
      </div>

      <div id="chat-area" style="display:none;flex-direction:column;height:100%">
        <div class="chat-header">
          <div class="chat-header-title" id="chat-title">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <span>Chat</span>
          </div>
          <div style="color:#718096;font-size:0.85rem" id="chat-subtitle"></div>
        </div>
        <div class="chat-messages" id="messages-container"></div>
        <div class="typing-indicator" id="typing-indicator"></div>
        <div class="chat-input-container">
          <form class="chat-input-form" id="message-form">
            <input type="text" class="chat-input" id="message-input" placeholder="Type your message..." autocomplete="off">
            <button type="submit" class="btn-send">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-right:6px">
                <line x1="22" y1="2" x2="11" y2="13"></line>
                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
              </svg>
              Send
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- New Chat Modal -->
<div id="new-chat-modal" class="modal">
  <div class="modal-content">
    <h3 class="modal-title">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M12 5v14M5 12h14"/>
      </svg>
      Start New Chat
    </h3>
    <div class="form-group">
      <label class="form-label">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-right:6px">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
        Select Person
      </label>
      <select id="new-chat-target" class="form-select">
        <option value="">Choose a person...</option>
        <?php foreach ($employees as $emp): ?>
          <option value="<?= htmlspecialchars($emp['participant_type']) ?>-<?= $emp['id'] ?>" 
                  data-type="<?= htmlspecialchars($emp['participant_type']) ?>" 
                  data-id="<?= $emp['id'] ?>">
            <?= htmlspecialchars($emp['full_name']) ?> 
            <?= $emp['position'] ? '(' . htmlspecialchars($emp['position']) . ')' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeNewChatModal()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-right:6px">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
        Cancel
      </button>
      <button class="btn-send" style="flex:1" onclick="createNewChat()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;margin-right:6px">
          <path d="M12 5v14M5 12h14"/>
        </svg>
        Create Chat
      </button>
    </div>
  </div>
</div>

<script>
  let currentRoomId = null;
  let pollInterval = null;
  let typingTimeout = null;

  // Load rooms
  function loadRooms() {
    fetch('/MY CASH/includes/chat_api.php?action=get_rooms')
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          const container = document.getElementById('rooms-list');
          if (data.data.length === 0) {
            container.innerHTML = `
              <div style="text-align:center;padding:60px 20px;color:#cbd5e0">
                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.4;margin:0 auto">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                  <line x1="12" y1="12" x2="12" y2="12"></line>
                </svg>
                <p style="margin-top:16px;font-weight:500">No conversations yet</p>
                <p style="margin-top:8px;font-size:0.85rem;opacity:0.7">Start a new chat above</p>
              </div>
            `;
          } else {
            container.innerHTML = data.data.map(room => `
              <div class="room-item ${room.id == currentRoomId ? 'active' : ''}" onclick="selectRoom(${room.id}, '${escapeHtml(room.name || 'Chat')}')">
                <div class="room-name">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                  <span>${escapeHtml(room.name || 'Chat')}</span>
                </div>
                <div class="room-last-message">${escapeHtml(room.last_message || 'No messages yet')}</div>
                ${room.unread_count > 0 ? `<div class="room-unread">${room.unread_count}</div>` : ''}
              </div>
            `).join('');
          }
        }
      });
  }

  // Select room
  function selectRoom(roomId, roomName) {
    currentRoomId = roomId;
    const titleElement = document.getElementById('chat-title');
    titleElement.innerHTML = `
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
      </svg>
      <span>${escapeHtml(roomName)}</span>
    `;
    document.getElementById('no-chat-selected').style.display = 'none';
    document.getElementById('chat-area').style.display = 'flex';
    loadMessages();
    loadRooms();
    
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(() => {
      loadMessages();
      checkTyping();
    }, 2000);
  }

  // Load messages
  function loadMessages() {
    if (!currentRoomId) return;
    
    fetch(`/MY CASH/includes/chat_api.php?action=get_messages&room_id=${currentRoomId}`)
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          const container = document.getElementById('messages-container');
          const wasAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;
          
          container.innerHTML = data.data.map(msg => {
            const isSent = msg.sender_type === 'user' && msg.sender_id == <?= $user_id ?>;
            return `
              <div class="message ${isSent ? 'sent' : 'received'}">
                ${!isSent ? `<div class="message-sender">${escapeHtml(msg.sender_name)}</div>` : ''}
                <div class="message-text">${escapeHtml(msg.message)}</div>
                <div class="message-time">${formatTime(msg.created_at)}</div>
              </div>
            `;
          }).join('');
          
          if (wasAtBottom || data.data.length > 0) {
            container.scrollTop = container.scrollHeight;
          }
        }
      });
  }

  // Send message
  document.getElementById('message-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const input = document.getElementById('message-input');
    const message = input.value.trim();
    
    if (!message || !currentRoomId) return;
    
    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('room_id', currentRoomId);
    formData.append('message', message);
    
    fetch('/MY CASH/includes/chat_api.php', {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        input.value = '';
        loadMessages();
        loadRooms();
      }
    });
  });

  // Typing indicator
  document.getElementById('message-input').addEventListener('input', () => {
    if (!currentRoomId) return;
    
    if (typingTimeout) clearTimeout(typingTimeout);
    
    const formData = new FormData();
    formData.append('action', 'typing');
    formData.append('room_id', currentRoomId);
    fetch('/MY CASH/includes/chat_api.php', { method: 'POST', body: formData });
    
    typingTimeout = setTimeout(() => {}, 3000);
  });

  function checkTyping() {
    if (!currentRoomId) return;
    
    fetch(`/MY CASH/includes/chat_api.php?action=get_typing&room_id=${currentRoomId}`)
      .then(r => r.json())
      .then(data => {
        const indicator = document.getElementById('typing-indicator');
        if (data.success && data.data.length > 0) {
          indicator.textContent = `${data.data[0].sender_name} is typing...`;
        } else {
          indicator.textContent = '';
        }
      });
  }

  // New chat modal
  function showNewChatModal() {
    document.getElementById('new-chat-modal').style.display = 'flex';
  }

  function closeNewChatModal() {
    document.getElementById('new-chat-modal').style.display = 'none';
  }

  function createNewChat() {
    const target = document.getElementById('new-chat-target').value;
    if (!target) return alert('Please select an employee');
    
    const [type, id] = target.split('-');
    const formData = new FormData();
    formData.append('action', 'create_room');
    formData.append('target_type', type);
    formData.append('target_id', id);
    
    fetch('/MY CASH/includes/chat_api.php', {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        closeNewChatModal();
        loadRooms();
        setTimeout(() => {
          const roomName = document.getElementById('new-chat-target').selectedOptions[0].text;
          selectRoom(data.data.room_id, roomName);
        }, 300);
      }
    });
  }

  // Utilities
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function formatTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
    if (diff < 86400000) return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
  }

  // Initialize
  loadRooms();
  setInterval(loadRooms, 10000);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

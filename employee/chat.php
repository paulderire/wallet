  <?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['employee_id'])) {
  header("Location: /MY CASH/employee_login.php");
  exit;
}

include __DIR__ . '/../includes/db.php';

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'Employee';

// Auto-create tables
try {
  $schema = file_get_contents(__DIR__ . '/../db/chat_schema.sql');
  $statements = array_filter(array_map('trim', explode(';', $schema)));
  foreach ($statements as $statement) {
    if (!empty($statement)) $conn->exec($statement);
  }
} catch (Exception $e) {}

// Get admin users AND other employees for new chat
$admins = [];
try {
  // Combine admin users and other employees with UNION
  $query = "
    SELECT id, name as full_name, email, 'Admin' as position, 'user' as participant_type
    FROM users 
    WHERE is_admin = 1
    UNION
    SELECT id, CONCAT(first_name, ' ', last_name) as full_name, email, role as position, 'employee' as participant_type
    FROM employees 
    WHERE status = 'active' AND id != " . intval($employee_id) . "
    ORDER BY full_name ASC
  ";
  
  $stmt = $conn->query($query);
  $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  // Fallback to just admins if UNION fails
  try {
    $stmt = $conn->query("SELECT id, name as full_name, email, 'Admin' as position, 'user' as participant_type FROM users WHERE is_admin = 1");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e2) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Team Chat - Employee Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-gradient-start: #667eea;
      --bg-gradient-end: #764ba2;
      --card-bg: rgba(255, 255, 255, 0.95);
      --text-primary: #1a202c;
      --text-secondary: #718096;
      --border-color: #e2e8f0;
      --hover-bg: rgba(102, 126, 234, 0.1);
      --active-bg: linear-gradient(135deg, rgba(102, 126, 234, 0.15), rgba(118, 75, 162, 0.15));
      --message-received-bg: #f7fafc;
      --message-sent-bg: linear-gradient(135deg, #667eea, #764ba2);
      --shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
      --shadow-hover: 0 15px 50px rgba(0, 0, 0, 0.18);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: linear-gradient(135deg, var(--bg-gradient-start) 0%, var(--bg-gradient-end) 100%);
      min-height: 100vh;
      padding: 24px;
      overflow-x: hidden;
    }

    .container {
      max-width: 1400px;
      margin: 0 auto;
      animation: fadeIn 0.5s ease-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .header {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border-radius: 20px;
      padding: 28px 32px;
      margin-bottom: 24px;
      box-shadow: var(--shadow);
      transition: all 0.3s ease;
    }

    .header:hover {
      box-shadow: var(--shadow-hover);
      transform: translateY(-2px);
    }

    .header-content {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 20px;
    }

    .header-title {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 2rem;
      font-weight: 800;
      background: linear-gradient(135deg, var(--bg-gradient-start), var(--bg-gradient-end));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .header-subtitle {
      color: var(--text-secondary);
      margin-top: 6px;
      font-size: 0.95rem;
      font-weight: 500;
    }

    .btn {
      padding: 12px 24px;
      border: none;
      border-radius: 12px;
      font-weight: 600;
      font-size: 0.95rem;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      font-family: 'Inter', sans-serif;
    }

    .btn svg {
      width: 20px;
      height: 20px;
      stroke-width: 2.5;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--bg-gradient-start), var(--bg-gradient-end));
      color: white;
      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .btn-primary:hover {
      box-shadow: 0 6px 25px rgba(102, 126, 234, 0.5);
      transform: translateY(-2px);
    }

    .btn-secondary {
      background: rgba(0, 0, 0, 0.05);
      color: var(--text-primary);
    }

    .btn-secondary:hover {
      background: rgba(0, 0, 0, 0.08);
      transform: translateY(-2px);
    }

    .chat-container {
      display: grid;
      grid-template-columns: 360px 1fr;
      gap: 24px;
      height: calc(100vh - 220px);
      min-height: 500px;
    }

    .chat-sidebar {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border-radius: 20px;
      padding: 24px;
      overflow-y: auto;
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .chat-sidebar::-webkit-scrollbar {
      width: 8px;
    }

    .chat-sidebar::-webkit-scrollbar-track {
      background: rgba(0, 0, 0, 0.05);
      border-radius: 10px;
    }

    .chat-sidebar::-webkit-scrollbar-thumb {
      background: linear-gradient(135deg, var(--bg-gradient-start), var(--bg-gradient-end));
      border-radius: 10px;
    }

    .sidebar-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 8px;
      padding-bottom: 16px;
      border-bottom: 2px solid var(--border-color);
    }

    .sidebar-header svg {
      width: 24px;
      height: 24px;
      stroke: var(--bg-gradient-start);
    }

    .sidebar-title {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--text-primary);
    }

    .chat-main {
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      border-radius: 20px;
      display: flex;
      flex-direction: column;
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .room-item {
      padding: 18px;
      border-radius: 14px;
      margin-bottom: 10px;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      border: 2px solid transparent;
    }

    .room-item:hover {
      background: var(--hover-bg);
      transform: translateX(4px);
      border-color: rgba(102, 126, 234, 0.2);
    }

    .room-item.active {
      background: var(--active-bg);
      border-left: 4px solid var(--bg-gradient-start);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
    }

    .room-name {
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 6px;
      font-size: 1rem;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .room-name svg {
      width: 18px;
      height: 18px;
      stroke: var(--bg-gradient-start);
    }

    .room-last-message {
      font-size: 0.875rem;
      color: var(--text-secondary);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      padding-left: 26px;
    }

    .room-unread {
      position: absolute;
      top: 18px;
      right: 18px;
      background: linear-gradient(135deg, #ef4444, #dc2626);
      color: white;
      border-radius: 50%;
      min-width: 24px;
      height: 24px;
      padding: 0 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
      font-weight: 700;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.8; }
    }

    .chat-header {
      padding: 24px 28px;
      border-bottom: 2px solid var(--border-color);
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
    }

    .chat-header-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--text-primary);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .chat-header-title svg {
      width: 24px;
      height: 24px;
      stroke: var(--bg-gradient-start);
    }

    .chat-messages {
      flex: 1;
      padding: 24px 28px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .chat-messages::-webkit-scrollbar {
      width: 10px;
    }

    .chat-messages::-webkit-scrollbar-track {
      background: rgba(0, 0, 0, 0.02);
    }

    .chat-messages::-webkit-scrollbar-thumb {
      background: linear-gradient(135deg, var(--bg-gradient-start), var(--bg-gradient-end));
      border-radius: 10px;
    }

    .message {
      max-width: 70%;
      padding: 14px 18px;
      border-radius: 18px;
      position: relative;
      animation: messageSlide 0.3s ease-out;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    @keyframes messageSlide {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .message.sent {
      align-self: flex-end;
      background: var(--message-sent-bg);
      color: white;
      border-bottom-right-radius: 6px;
    }

    .message.received {
      align-self: flex-start;
      background: var(--message-received-bg);
      color: var(--text-primary);
      border-bottom-left-radius: 6px;
      border: 1px solid var(--border-color);
    }

    .message-sender {
      font-size: 0.75rem;
      font-weight: 700;
      margin-bottom: 6px;
      opacity: 0.85;
      letter-spacing: 0.3px;
    }

    .message-text {
      word-wrap: break-word;
      line-height: 1.5;
    }

    .message-time {
      font-size: 0.7rem;
      opacity: 0.7;
      margin-top: 6px;
      font-weight: 500;
    }

    .chat-input-container {
      padding: 20px 28px 24px;
      border-top: 2px solid var(--border-color);
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.02), rgba(118, 75, 162, 0.02));
    }

    .chat-input-form {
      display: flex;
      gap: 12px;
      align-items: center;
    }

    .chat-input {
      flex: 1;
      padding: 14px 18px;
      border: 2px solid var(--border-color);
      border-radius: 14px;
      font-size: 1rem;
      font-family: 'Inter', sans-serif;
      transition: all 0.3s ease;
      background: white;
    }

    .chat-input:focus {
      outline: none;
      border-color: var(--bg-gradient-start);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .typing-indicator {
      padding: 0 28px 12px;
      font-size: 0.875rem;
      color: var(--text-secondary);
      font-style: italic;
      min-height: 28px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .typing-indicator::before {
      content: '●';
      animation: typing 1.5s infinite;
      color: var(--bg-gradient-start);
    }

    @keyframes typing {
      0%, 100% { opacity: 0.3; }
      50% { opacity: 1; }
    }

    .empty-state {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100%;
      color: var(--text-secondary);
      padding: 40px;
      text-align: center;
    }

    .empty-state-icon {
      font-size: 5rem;
      margin-bottom: 20px;
      animation: float 3s ease-in-out infinite;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }

    .empty-state h3 {
      color: var(--text-primary);
      font-size: 1.5rem;
      margin-bottom: 10px;
      font-weight: 700;
    }

    .empty-state p {
      font-size: 1rem;
      opacity: 0.8;
    }

    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.7);
      backdrop-filter: blur(4px);
      z-index: 9999;
      align-items: center;
      justify-content: center;
      animation: fadeIn 0.3s ease;
    }

    .modal-content {
      background: white;
      border-radius: 20px;
      padding: 36px;
      max-width: 460px;
      width: 90%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      animation: slideUp 0.3s ease-out;
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .modal-content h3 {
      margin-bottom: 24px;
      font-size: 1.5rem;
      color: var(--text-primary);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .modal-content h3 svg {
      width: 28px;
      height: 28px;
      stroke: var(--bg-gradient-start);
    }

    .form-group {
      margin-bottom: 24px;
    }

    .form-group label {
      display: block;
      margin-bottom: 10px;
      font-weight: 600;
      color: var(--text-primary);
      font-size: 0.95rem;
    }

    .form-group select {
      width: 100%;
      padding: 14px 16px;
      border: 2px solid var(--border-color);
      border-radius: 12px;
      font-size: 1rem;
      font-family: 'Inter', sans-serif;
      transition: all 0.3s ease;
      background: white;
    }

    .form-group select:focus {
      outline: none;
      border-color: var(--bg-gradient-start);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .modal-actions {
      display: flex;
      gap: 12px;
      margin-top: 28px;
    }

    @media (max-width: 1024px) {
      .chat-container {
        grid-template-columns: 1fr;
        height: auto;
      }

      .chat-main {
        height: 600px;
      }

      .header-title {
        font-size: 1.5rem;
      }
    }

    @media (max-width: 640px) {
      body {
        padding: 12px;
      }

      .header {
        padding: 20px;
      }

      .chat-sidebar {
        padding: 16px;
      }

      .chat-messages {
        padding: 16px;
      }

      .message {
        max-width: 85%;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="header-content">
        <div>
          <h1 class="header-title">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            Team Chat
          </h1>
          <p class="header-subtitle">Communicate with your team in real-time</p>
        </div>
        <a href="/MY CASH/employee/dashboard.php" class="btn btn-secondary">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
          </svg>
          Back to Dashboard
        </a>
      </div>
    </div>

    <div class="chat-container">
      <!-- Sidebar -->
      <div class="chat-sidebar">
        <div class="sidebar-header">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          <span class="sidebar-title">Conversations</span>
        </div>
        
        <button class="btn btn-primary" style="width:100%" onclick="showNewChatModal()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M12 5v14M5 12h14"/>
          </svg>
          New Chat
        </button>
        
        <div id="rooms-list">
          <div class="empty-state" style="padding: 60px 20px">
            <div class="empty-state-icon">💬</div>
            <p style="margin-top: 12px; font-size: 0.9rem">Loading chats...</p>
          </div>
        </div>
      </div>

      <!-- Main Chat Area -->
      <div class="chat-main">
        <div id="no-chat-selected" class="empty-state">
          <div class="empty-state-icon">
            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
              <line x1="9" y1="10" x2="15" y2="10"></line>
              <line x1="9" y1="14" x2="13" y2="14"></line>
            </svg>
          </div>
          <h3>Select a chat to start messaging</h3>
          <p>Choose a conversation from the sidebar or start a new one</p>
        </div>

        <div id="chat-area" style="display:none;flex-direction:column;height:100%">
          <div class="chat-header">
            <div class="chat-header-title" id="chat-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
              </svg>
              <span>Chat</span>
            </div>
          </div>
          <div class="chat-messages" id="messages-container"></div>
          <div class="typing-indicator" id="typing-indicator"></div>
          <div class="chat-input-container">
            <form class="chat-input-form" id="message-form">
              <input type="text" class="chat-input" id="message-input" placeholder="Type a message..." autocomplete="off">
              <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
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
  <div id="new-chat-modal" class="modal-overlay">
    <div class="modal-content">
      <h3>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M12 5v14M5 12h14"/>
        </svg>
        Start New Chat
      </h3>
      <div class="form-group">
        <label>
          <svg style="width:16px;height:16px;display:inline;vertical-align:middle;margin-right:4px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          Select Person
        </label>
        <select id="new-chat-target">
          <option value="">Choose a person...</option>
          <?php foreach ($admins as $admin): ?>
            <option value="<?= htmlspecialchars($admin['participant_type']) ?>-<?= $admin['id'] ?>" 
                    data-type="<?= htmlspecialchars($admin['participant_type']) ?>" 
                    data-id="<?= $admin['id'] ?>">
              <?= htmlspecialchars($admin['full_name']) ?> 
              <?= isset($admin['position']) ? '(' . htmlspecialchars($admin['position']) . ')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-actions">
        <button class="btn btn-primary" onclick="createNewChat()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M12 5v14M5 12h14"/>
          </svg>
          Create Chat
        </button>
        <button class="btn btn-secondary" onclick="closeNewChatModal()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
          Cancel
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
                <div class="empty-state" style="padding: 60px 20px">
                  <div class="empty-state-icon">
                    <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                      <line x1="12" y1="12" x2="12" y2="12"></line>
                    </svg>
                  </div>
                  <p style="margin-top: 16px; font-size: 0.9rem">No chats yet</p>
                  <p style="margin-top: 6px; font-size: 0.85rem; opacity: 0.7">Click "New Chat" to start</p>
                </div>
              `;
            } else {
              container.innerHTML = data.data.map(room => `
                <div class="room-item ${room.id == currentRoomId ? 'active' : ''}" onclick="selectRoom(${room.id})">
                  <div class="room-name">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                      <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    ${room.name || 'Chat'}
                  </div>
                  <div class="room-last-message">${room.last_message || 'No messages yet'}</div>
                  ${room.unread_count > 0 ? `<div class="room-unread">${room.unread_count}</div>` : ''}
                </div>
              `).join('');
            }
          }
        });
    }

    // Select room
    function selectRoom(roomId) {
      currentRoomId = roomId;
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
            container.innerHTML = data.data.map(msg => {
              const isSent = msg.sender_type === 'employee' && msg.sender_id == <?= $employee_id ?>;
              return `
                <div class="message ${isSent ? 'sent' : 'received'}">
                  ${!isSent ? `<div class="message-sender">${msg.sender_name}</div>` : ''}
                  <div class="message-text">${escapeHtml(msg.message)}</div>
                  <div class="message-time">${formatTime(msg.created_at)}</div>
                </div>
              `;
            }).join('');
            container.scrollTop = container.scrollHeight;
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
      if (!target) return alert('Please select a recipient');
      
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
          selectRoom(data.data.room_id);
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
      return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    // Initialize
    loadRooms();
    setInterval(loadRooms, 10000);
  </script>
</body>
</html>

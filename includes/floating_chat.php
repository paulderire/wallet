<!-- Floating Chat Widget -->
<style>
/* Floating Chat Button */
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
  z-index: 9998;
  transition: all 0.3s ease;
  border: none;
  color: white;
  font-size: 28px;
}

.floating-chat-btn:hover {
  transform: scale(1.1);
  box-shadow: 0 12px 32px rgba(102, 126, 234, 0.5);
}

.floating-chat-btn.active {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.chat-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #ef4444;
  color: white;
  border-radius: 50%;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  border: 2px solid white;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.1); }
}

/* Floating Chat Widget */
.floating-chat-widget {
  position: fixed;
  bottom: 100px;
  right: 24px;
  width: 380px;
  max-height: calc(100vh - 140px);
  height: 600px;
  background: var(--card-bg);
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  z-index: 9999;
  display: none;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid var(--border-medium);
  transition: all 0.3s ease;
}

.floating-chat-widget.active {
  display: flex;
}

[data-theme="dark"] .floating-chat-widget {
  background: #1e293b;
  border-color: #475569;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
}

/* Chat Widget Header */
.chat-widget-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.chat-widget-title {
  font-weight: 700;
  font-size: 1.1rem;
  display: flex;
  align-items: center;
  gap: 8px;
}

.chat-close-btn {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  transition: all 0.3s ease;
}

.chat-close-btn:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: rotate(90deg);
}

/* Chat Rooms List */
.chat-rooms-list {
  flex: 1;
  overflow-y: auto;
  padding: 12px;
  background: var(--bg-secondary);
}

.chat-room-item {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 12px;
  padding: 12px;
  margin-bottom: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 12px;
}

.chat-room-item:hover {
  background: var(--bg-secondary);
  border-color: var(--border-medium);
  transform: translateX(4px);
}

.chat-room-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea, #764ba2);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 700;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.chat-room-info {
  flex: 1;
  min-width: 0;
}

.chat-room-name {
  font-weight: 700;
  color: var(--card-text);
  margin-bottom: 4px;
  font-size: 0.95rem;
}

.chat-room-last-msg {
  font-size: 0.85rem;
  color: var(--muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.chat-room-unread {
  background: #ef4444;
  color: white;
  border-radius: 12px;
  padding: 2px 8px;
  font-size: 0.75rem;
  font-weight: 700;
  flex-shrink: 0;
}

/* Chat Messages View */
.chat-messages-view {
  display: none;
  flex-direction: column;
  height: 100%;
}

.chat-messages-view.active {
  display: flex;
}

.chat-messages-header {
  background: var(--card-bg);
  border-bottom: 1px solid var(--border-weak);
  padding: 12px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.chat-back-btn {
  background: none;
  border: none;
  color: var(--card-text);
  font-size: 20px;
  cursor: pointer;
  padding: 4px;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.chat-back-btn:hover {
  background: var(--bg-secondary);
}

.chat-messages-container {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  background: var(--bg-secondary);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.chat-message {
  display: flex;
  gap: 8px;
  max-width: 80%;
}

.chat-message.own {
  align-self: flex-end;
  flex-direction: row-reverse;
}

.chat-message-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea, #764ba2);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 700;
  font-size: 0.85rem;
  flex-shrink: 0;
}

.chat-message.own .chat-message-avatar {
  background: linear-gradient(135deg, #10b981, #059669);
}

.chat-message-content {
  flex: 1;
}

.chat-message-bubble {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 12px;
  padding: 10px 14px;
  color: var(--card-text);
  word-wrap: break-word;
}

.chat-message.own .chat-message-bubble {
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: white;
  border: none;
}

.chat-message-time {
  font-size: 0.7rem;
  color: var(--muted);
  margin-top: 4px;
  padding: 0 4px;
}

/* Chat Input */
.chat-input-area {
  background: var(--card-bg);
  border-top: 1px solid var(--border-weak);
  padding: 12px;
  display: flex;
  gap: 8px;
}

.chat-input {
  flex: 1;
  background: var(--bg-secondary);
  border: 1px solid var(--border-medium);
  border-radius: 20px;
  padding: 10px 16px;
  color: var(--card-text);
  font-size: 0.9rem;
  outline: none;
  transition: all 0.3s ease;
}

.chat-input:focus {
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.chat-send-btn {
  background: linear-gradient(135deg, #667eea, #764ba2);
  border: none;
  color: white;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  transition: all 0.3s ease;
}

.chat-send-btn:hover {
  transform: scale(1.1);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.chat-send-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* New Chat Section */
.chat-new-section {
  padding: 12px;
  border-top: 1px solid var(--border-weak);
  background: var(--card-bg);
}

.chat-new-btn {
  width: 100%;
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
  border: none;
  border-radius: 10px;
  padding: 10px;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.3s ease;
}

.chat-new-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

/* New Chat Form */
.chat-new-chat-view {
  display: none;
  flex-direction: column;
  height: 100%;
  background: var(--bg-secondary);
}

.chat-new-chat-view.active {
  display: flex;
}

.chat-new-chat-form {
  flex: 1;
  padding: 20px;
  overflow-y: auto;
}

.chat-form-group {
  margin-bottom: 20px;
}

.chat-form-label {
  display: block;
  font-weight: 600;
  color: var(--card-text);
  margin-bottom: 8px;
  font-size: 0.9rem;
}

.chat-form-select {
  width: 100%;
  background: var(--card-bg);
  border: 1px solid var(--border-medium);
  border-radius: 8px;
  padding: 10px 12px;
  color: var(--card-text);
  font-size: 0.9rem;
  outline: none;
  transition: all 0.3s ease;
}

.chat-form-select:focus {
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.chat-form-actions {
  display: flex;
  gap: 8px;
  padding: 12px 20px;
  background: var(--card-bg);
  border-top: 1px solid var(--border-weak);
}

.chat-form-btn {
  flex: 1;
  padding: 10px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.chat-form-btn-cancel {
  background: var(--bg-secondary);
  color: var(--card-text);
  border: 1px solid var(--border-medium);
}

.chat-form-btn-cancel:hover {
  background: var(--border-weak);
}

.chat-form-btn-submit {
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: white;
}

.chat-form-btn-submit:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.chat-form-btn-submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

/* Empty State */
.chat-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  text-align: center;
  color: var(--muted);
}

.chat-empty-icon {
  font-size: 3rem;
  margin-bottom: 16px;
  opacity: 0.5;
}

/* Scrollbar */
.chat-rooms-list::-webkit-scrollbar,
.chat-messages-container::-webkit-scrollbar {
  width: 6px;
}

.chat-rooms-list::-webkit-scrollbar-track,
.chat-messages-container::-webkit-scrollbar-track {
  background: var(--bg-secondary);
}

.chat-rooms-list::-webkit-scrollbar-thumb,
.chat-messages-container::-webkit-scrollbar-thumb {
  background: var(--border-medium);
  border-radius: 3px;
}

/* Mobile Responsive */
@media (max-width: 480px) {
  .floating-chat-widget {
    width: calc(100% - 32px);
    max-height: calc(100vh - 140px);
    height: auto;
    min-height: 400px;
    right: 16px;
    bottom: 92px;
  }
  
  .floating-chat-btn {
    bottom: 16px;
    right: 16px;
  }
}
</style>

<!-- Floating Chat Button -->
<button class="floating-chat-btn" id="floatingChatBtn" aria-label="Open Chat">
  💬
  <span class="chat-badge" id="chatBadge" style="display: none;">0</span>
</button>

<!-- Floating Chat Widget -->
<div class="floating-chat-widget" id="floatingChatWidget">
  <!-- Widget Header -->
  <div class="chat-widget-header">
    <div class="chat-widget-title">
      <span>💬</span>
      <span>Messages</span>
    </div>
    <button class="chat-close-btn" id="chatCloseBtn">×</button>
  </div>
  
  <!-- Chat Rooms List View -->
  <div id="chatRoomsView">
    <div class="chat-rooms-list" id="chatRoomsList">
      <!-- Rooms will be loaded here -->
    </div>
    <div class="chat-new-section">
      <button class="chat-new-btn" id="newChatBtn">
        <span>➕</span>
        <span>New Chat</span>
      </button>
    </div>
  </div>
  
  <!-- Chat Messages View -->
  <div class="chat-messages-view" id="chatMessagesView">
    <div class="chat-messages-header">
      <button class="chat-back-btn" id="chatBackBtn">←</button>
      <div class="chat-room-avatar" id="currentChatAvatar">U</div>
      <div class="chat-room-info">
        <div class="chat-room-name" id="currentChatName">User</div>
      </div>
    </div>
    <div class="chat-messages-container" id="chatMessagesContainer">
      <!-- Messages will be loaded here -->
    </div>
    <div class="chat-input-area">
      <input type="text" class="chat-input" id="chatInput" placeholder="Type a message...">
      <button class="chat-send-btn" id="chatSendBtn">➤</button>
    </div>
  </div>
  
  <!-- New Chat View -->
  <div class="chat-new-chat-view" id="chatNewChatView">
    <div class="chat-messages-header">
      <button class="chat-back-btn" id="chatNewBackBtn">←</button>
      <div class="chat-room-info">
        <div class="chat-room-name">New Chat</div>
      </div>
    </div>
    <div class="chat-new-chat-form">
      <div class="chat-form-group">
        <label class="chat-form-label">Select Person</label>
        <select class="chat-form-select" id="chatNewPersonSelect">
          <option value="">Loading...</option>
        </select>
      </div>
    </div>
    <div class="chat-form-actions">
      <button class="chat-form-btn chat-form-btn-cancel" id="chatNewCancelBtn">Cancel</button>
      <button class="chat-form-btn chat-form-btn-submit" id="chatNewSubmitBtn">Start Chat</button>
    </div>
  </div>
</div>

<script>
// Floating Chat Widget JavaScript
(function() {
  const chatBtn = document.getElementById('floatingChatBtn');
  const chatWidget = document.getElementById('floatingChatWidget');
  const chatCloseBtn = document.getElementById('chatCloseBtn');
  const chatBadge = document.getElementById('chatBadge');
  const chatRoomsList = document.getElementById('chatRoomsList');
  const chatRoomsView = document.getElementById('chatRoomsView');
  const chatMessagesView = document.getElementById('chatMessagesView');
  const chatBackBtn = document.getElementById('chatBackBtn');
  const chatInput = document.getElementById('chatInput');
  const chatSendBtn = document.getElementById('chatSendBtn');
  const chatMessagesContainer = document.getElementById('chatMessagesContainer');
  const currentChatName = document.getElementById('currentChatName');
  const currentChatAvatar = document.getElementById('currentChatAvatar');
  const newChatBtn = document.getElementById('newChatBtn');
  const chatNewChatView = document.getElementById('chatNewChatView');
  const chatNewBackBtn = document.getElementById('chatNewBackBtn');
  const chatNewCancelBtn = document.getElementById('chatNewCancelBtn');
  const chatNewSubmitBtn = document.getElementById('chatNewSubmitBtn');
  const chatNewPersonSelect = document.getElementById('chatNewPersonSelect');
  
  let currentRoomId = null;
  let unreadCount = 0;
  let messageCheckInterval = null;
  let availablePeople = [];
  
  // Toggle chat widget
  chatBtn.addEventListener('click', () => {
    console.log('Chat button clicked');
    console.log('Session check - Employee ID:', '<?php echo $_SESSION['employee_id'] ?? 'none'; ?>', 'User ID:', '<?php echo $_SESSION['user_id'] ?? 'none'; ?>');
    
    chatWidget.classList.toggle('active');
    chatBtn.classList.toggle('active');
    if (chatWidget.classList.contains('active')) {
      console.log('Loading chat rooms...');
      loadChatRooms();
      startMessageCheck();
    } else {
      stopMessageCheck();
    }
  });
  
  chatCloseBtn.addEventListener('click', () => {
    chatWidget.classList.remove('active');
    chatBtn.classList.remove('active');
    stopMessageCheck();
  });
  
  // Load chat rooms
  async function loadChatRooms() {
    try {
      const response = await fetch('/MY CASH/includes/chat_api.php?action=get_rooms');
      const result = await response.json();
      
      if (result.error === 'Unauthorized') {
        // User not logged in - hide widget
        chatWidget.classList.remove('active');
        chatBtn.style.display = 'none';
        return;
      }
      
      if (result.success && result.data) {
        displayChatRooms(result.data);
        updateUnreadBadge(result.data);
      } else {
        chatRoomsList.innerHTML = `
          <div class="chat-empty-state">
            <div class="chat-empty-icon">💬</div>
            <p>No conversations yet</p>
            <p style="font-size: 0.85rem; margin-top: 8px;">Start a new chat to begin</p>
          </div>
        `;
      }
    } catch (error) {
      console.error('Error loading chat rooms:', error);
      chatRoomsList.innerHTML = '<div class="chat-empty-state"><p>Error loading chats</p></div>';
    }
  }
  
  // Display chat rooms
  function displayChatRooms(rooms) {
    if (rooms.length === 0) {
      chatRoomsList.innerHTML = `
        <div class="chat-empty-state">
          <div class="chat-empty-icon">💬</div>
          <p>No conversations yet</p>
          <p style="font-size: 0.85rem; margin-top: 8px;">Start a new chat to begin</p>
        </div>
      `;
      return;
    }
    
    chatRoomsList.innerHTML = rooms.map(room => {
      const initial = (room.name || 'U').charAt(0).toUpperCase();
      const unreadBadge = room.unread_count > 0 ? `<span class="chat-room-unread">${room.unread_count}</span>` : '';
      const lastMsg = room.last_message || 'No messages yet';
      
      return `
        <div class="chat-room-item" data-room-id="${room.id}" data-room-name="${room.name || 'Chat'}">
          <div class="chat-room-avatar">${initial}</div>
          <div class="chat-room-info">
            <div class="chat-room-name">${room.name || 'Chat'}</div>
            <div class="chat-room-last-msg">${lastMsg.substring(0, 40)}${lastMsg.length > 40 ? '...' : ''}</div>
          </div>
          ${unreadBadge}
        </div>
      `;
    }).join('');
    
    // Attach click handlers
    document.querySelectorAll('.chat-room-item').forEach(item => {
      item.addEventListener('click', () => {
        const roomId = parseInt(item.dataset.roomId);
        const roomName = item.dataset.roomName;
        openChatRoom(roomId, roomName);
      });
    });
  }
  
  // Update unread badge
  function updateUnreadBadge(rooms) {
    const total = rooms.reduce((sum, room) => sum + parseInt(room.unread_count || 0), 0);
    unreadCount = total;
    
    if (total > 0) {
      chatBadge.textContent = total > 99 ? '99+' : total;
      chatBadge.style.display = 'flex';
    } else {
      chatBadge.style.display = 'none';
    }
  }
  
  // Open chat room
  function openChatRoom(roomId, roomName) {
    currentRoomId = roomId;
    currentChatName.textContent = roomName;
    currentChatAvatar.textContent = (roomName || 'U').charAt(0).toUpperCase();
    
    chatRoomsView.style.display = 'none';
    chatMessagesView.classList.add('active');
    
    loadMessages(roomId);
  }
  
  // Back to rooms list
  chatBackBtn.addEventListener('click', () => {
    chatMessagesView.classList.remove('active');
    chatRoomsView.style.display = 'block';
    currentRoomId = null;
    loadChatRooms();
  });
  
  // Load messages
  async function loadMessages(roomId) {
    try {
      const response = await fetch(`/MY CASH/includes/chat_api.php?action=get_messages&room_id=${roomId}`);
      const result = await response.json();
      
      if (result.error === 'Unauthorized') {
        alert('Session expired. Please login again.');
        window.location.reload();
        return;
      }
      
      if (result.success && result.data) {
        displayMessages(result.data);
      }
    } catch (error) {
      console.error('Error loading messages:', error);
    }
  }
  
  // Display messages
  function displayMessages(messages) {
    chatMessagesContainer.innerHTML = messages.map(msg => {
      const isOwn = msg.sender_id == <?php echo $_SESSION['user_id'] ?? $_SESSION['employee_id'] ?? 0; ?> && msg.sender_type == '<?php echo !empty($_SESSION['employee_id']) ? 'employee' : 'user'; ?>';
      const initial = (msg.sender_name || 'U').charAt(0).toUpperCase();
      const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      
      return `
        <div class="chat-message ${isOwn ? 'own' : ''}">
          <div class="chat-message-avatar">${initial}</div>
          <div class="chat-message-content">
            <div class="chat-message-bubble">${escapeHtml(msg.message)}</div>
            <div class="chat-message-time">${time}</div>
          </div>
        </div>
      `;
    }).join('');
    
    // Scroll to bottom
    chatMessagesContainer.scrollTop = chatMessagesContainer.scrollHeight;
  }
  
  // Send message
  async function sendMessage() {
    const message = chatInput.value.trim();
    if (!message || !currentRoomId) return;
    
    chatSendBtn.disabled = true;
    
    try {
      const formData = new FormData();
      formData.append('action', 'send_message');
      formData.append('room_id', currentRoomId);
      formData.append('message', message);
      
      const response = await fetch('/MY CASH/includes/chat_api.php', {
        method: 'POST',
        body: formData
      });
      
      const result = await response.json();
      
      if (result.success) {
        chatInput.value = '';
        loadMessages(currentRoomId);
      }
    } catch (error) {
      console.error('Error sending message:', error);
    } finally {
      chatSendBtn.disabled = false;
    }
  }
  
  chatSendBtn.addEventListener('click', sendMessage);
  chatInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') sendMessage();
  });
  
  // New chat button - show new chat form
  newChatBtn.addEventListener('click', () => {
    showNewChatForm();
  });
  
  // New chat form handlers
  chatNewBackBtn.addEventListener('click', () => {
    chatNewChatView.classList.remove('active');
    chatRoomsView.style.display = 'block';
  });
  
  chatNewCancelBtn.addEventListener('click', () => {
    chatNewChatView.classList.remove('active');
    chatRoomsView.style.display = 'block';
  });
  
  chatNewSubmitBtn.addEventListener('click', () => {
    createNewChatFromWidget();
  });
  
  // Show new chat form
  async function showNewChatForm() {
    // Load available people first
    await loadAvailablePeople();
    
    // Hide rooms view, show new chat view
    chatRoomsView.style.display = 'none';
    chatNewChatView.classList.add('active');
  }
  
  // Load available people for new chat
  async function loadAvailablePeople() {
    try {
      chatNewPersonSelect.innerHTML = '<option value="">Loading...</option>';
      
      let apiUrl;
      <?php if (!empty($_SESSION['employee_id'])): ?>
        // Employee - fetch admins and other employees
        apiUrl = '/MY CASH/includes/chat_api.php?action=get_available_people&type=employee';
      <?php else: ?>
        // User/Admin - fetch employees and users
        apiUrl = '/MY CASH/includes/chat_api.php?action=get_available_people&type=user';
      <?php endif; ?>
      
      console.log('Loading people from:', apiUrl);
      const response = await fetch(apiUrl);
      const result = await response.json();
      
      console.log('People result:', result);
      
      if (result.success && result.data && result.data.length > 0) {
        availablePeople = result.data;
        chatNewPersonSelect.innerHTML = '<option value="">Choose a person...</option>' +
          result.data.map(person => 
            `<option value="${person.participant_type}-${person.id}">${escapeHtml(person.full_name)}${person.position ? ' (' + escapeHtml(person.position) + ')' : ''}</option>`
          ).join('');
      } else if (result.success && result.data && result.data.length === 0) {
        chatNewPersonSelect.innerHTML = '<option value="">No people found. Add employees or users first.</option>';
        console.warn('No people available for chat');
      } else {
        chatNewPersonSelect.innerHTML = '<option value="">Error: ' + (result.error || 'Unknown error') + '</option>';
        console.error('API error:', result.error);
      }
    } catch (error) {
      console.error('Error loading people:', error);
      chatNewPersonSelect.innerHTML = '<option value="">Error: Could not load people. Check console.</option>';
    }
  }
  
  // Create new chat from widget
  async function createNewChatFromWidget() {
    const target = chatNewPersonSelect.value;
    if (!target) {
      alert('Please select a person');
      return;
    }
    
    chatNewSubmitBtn.disabled = true;
    chatNewSubmitBtn.textContent = 'Creating...';
    
    try {
      const [type, id] = target.split('-');
      const formData = new FormData();
      formData.append('action', 'create_room');
      formData.append('target_type', type);
      formData.append('target_id', id);
      
      const response = await fetch('/MY CASH/includes/chat_api.php', {
        method: 'POST',
        body: formData
      });
      
      const result = await response.json();
      
      if (result.success) {
        // Hide new chat form
        chatNewChatView.classList.remove('active');
        
        // Reload rooms
        await loadChatRooms();
        
        // Open the new room
        const roomName = chatNewPersonSelect.options[chatNewPersonSelect.selectedIndex].text;
        setTimeout(() => {
          openChatRoom(result.data.room_id, roomName);
        }, 300);
      } else {
        alert('Error creating chat: ' + (result.error || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error creating chat:', error);
      alert('Error creating chat');
    } finally {
      chatNewSubmitBtn.disabled = false;
      chatNewSubmitBtn.textContent = 'Start Chat';
    }
  }
  
  // Start periodic message check
  function startMessageCheck() {
    messageCheckInterval = setInterval(() => {
      if (currentRoomId) {
        loadMessages(currentRoomId);
      } else {
        loadChatRooms();
      }
    }, 5000); // Check every 5 seconds
  }
  
  function stopMessageCheck() {
    if (messageCheckInterval) {
      clearInterval(messageCheckInterval);
      messageCheckInterval = null;
    }
  }
  
  // Escape HTML
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
  
  // Only load chats initially if widget starts open (it doesn't)
  // This prevents unnecessary API calls on every page load
  
  // Poll for new messages every 30 seconds when widget is closed
  setInterval(() => {
    if (!chatWidget.classList.contains('active')) {
      // Only load if user is logged in (check if button is visible)
      if (chatBtn.style.display !== 'none') {
        loadChatRooms();
      }
    }
  }, 30000);
})();
</script>

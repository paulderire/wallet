<!-- Global Notifications Bell Widget -->
<style>
.notification-bell {
  position: relative;
  display: inline-block;
  cursor: pointer;
  padding: 8px 12px;
  border-radius: 8px;
  transition: background-color 0.2s;
}

.notification-bell:hover {
  background: var(--hover-bg, rgba(0,0,0,0.05));
}

[data-theme="dark"] .notification-bell:hover {
  background: rgba(255,255,255,0.1);
}

.notification-bell-icon {
  font-size: 20px;
  position: relative;
  display: inline-block;
}

.notification-badge {
  position: absolute;
  top: -6px;
  right: -6px;
  background: #EF4444;
  color: white;
  border-radius: 10px;
  padding: 2px 6px;
  font-size: 11px;
  font-weight: 600;
  min-width: 18px;
  text-align: center;
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
  display: none;
}

.notification-badge.active {
  display: block;
  animation: notificationPulse 2s infinite;
}

@keyframes notificationPulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.1); }
}

.notification-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 380px;
  max-width: 95vw;
  background: var(--card-bg, white);
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.1);
  display: none;
  z-index: 1000;
  max-height: 500px;
  overflow: hidden;
  flex-direction: column;
}

.notification-dropdown.active {
  display: flex;
}

.notification-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-color, #e5e7eb);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--card-bg, white);
}

.notification-header h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: var(--text-color, #111827);
}

.notification-mark-all {
  font-size: 12px;
  color: var(--primary-color, #4F46E5);
  cursor: pointer;
  text-decoration: none;
}

.notification-mark-all:hover {
  text-decoration: underline;
}

.notification-list {
  overflow-y: auto;
  max-height: 400px;
  flex: 1;
}

.notification-item {
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-color, #e5e7eb);
  cursor: pointer;
  transition: background-color 0.2s;
  display: flex;
  gap: 12px;
  align-items: start;
}

.notification-item:hover {
  background: var(--hover-bg, #f9fafb);
}

[data-theme="dark"] .notification-item:hover {
  background: rgba(255,255,255,0.05);
}

.notification-item.unread {
  background: var(--unread-bg, #EEF2FF);
}

[data-theme="dark"] .notification-item.unread {
  background: rgba(99, 102, 241, 0.1);
}

.notification-icon {
  font-size: 24px;
  flex-shrink: 0;
}

.notification-content {
  flex: 1;
  min-width: 0;
}

.notification-title {
  font-weight: 600;
  font-size: 14px;
  color: var(--text-color, #111827);
  margin-bottom: 4px;
}

.notification-message {
  font-size: 13px;
  color: var(--muted, #6b7280);
  line-height: 1.4;
  margin-bottom: 4px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.notification-time {
  font-size: 11px;
  color: var(--muted, #9ca3af);
}

.notification-empty {
  padding: 40px 20px;
  text-align: center;
  color: var(--muted, #6b7280);
}

.notification-empty-icon {
  font-size: 48px;
  margin-bottom: 12px;
  opacity: 0.5;
}
</style>

<div id="notificationBell" class="notification-bell">
  <span class="notification-bell-icon">
    🔔
    <span class="notification-badge" id="notificationBadge">0</span>
  </span>
  
  <div class="notification-dropdown" id="notificationDropdown">
    <div class="notification-header">
      <h3>Notifications</h3>
      <a href="#" class="notification-mark-all" id="markAllRead">Mark all read</a>
    </div>
    <div class="notification-list" id="notificationList">
      <div class="notification-empty">
        <div class="notification-empty-icon">🔔</div>
        <div>No notifications</div>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  const bell = document.getElementById('notificationBell');
  const dropdown = document.getElementById('notificationDropdown');
  const badge = document.getElementById('notificationBadge');
  const list = document.getElementById('notificationList');
  const markAllBtn = document.getElementById('markAllRead');
  
  let notifications = [];
  
  // Toggle dropdown
  bell.addEventListener('click', (e) => {
    e.stopPropagation();
    dropdown.classList.toggle('active');
    if (dropdown.classList.contains('active')) {
      loadNotifications();
    }
  });
  
  // Close dropdown when clicking outside
  document.addEventListener('click', (e) => {
    if (!bell.contains(e.target)) {
      dropdown.classList.remove('active');
    }
  });
  
  // Load notifications
  async function loadNotifications() {
    try {
      const response = await fetch('/MY CASH/includes/notifications_api.php?action=get_notifications&limit=20');
      const result = await response.json();
      
      if (result.success) {
        notifications = result.data;
        renderNotifications();
      }
    } catch (error) {
      console.error('Error loading notifications:', error);
    }
  }
  
  // Render notifications
  function renderNotifications() {
    if (notifications.length === 0) {
      list.innerHTML = `
        <div class="notification-empty">
          <div class="notification-empty-icon">🔔</div>
          <div>No notifications</div>
        </div>
      `;
      return;
    }
    
    list.innerHTML = notifications.map(n => {
      const icon = n.icon || getIconForType(n.type);
      const timeAgo = getTimeAgo(n.created_at);
      const unreadClass = n.is_read === '0' || n.is_read === 0 ? 'unread' : '';
      
      return `
        <div class="notification-item ${unreadClass}" data-id="${n.id}" data-url="${n.action_url || ''}">
          <div class="notification-icon">${icon}</div>
          <div class="notification-content">
            <div class="notification-title">${escapeHtml(n.title)}</div>
            <div class="notification-message">${escapeHtml(n.message)}</div>
            <div class="notification-time">${timeAgo}</div>
          </div>
        </div>
      `;
    }).join('');
    
    // Add click handlers
    list.querySelectorAll('.notification-item').forEach(item => {
      item.addEventListener('click', () => handleNotificationClick(item));
    });
  }
  
  // Handle notification click
  async function handleNotificationClick(item) {
    const id = item.dataset.id;
    const url = item.dataset.url;
    
    // Mark as read
    try {
      const formData = new FormData();
      formData.append('action', 'mark_notification_read');
      formData.append('notification_id', id);
      
      await fetch('/MY CASH/includes/notifications_api.php', {
        method: 'POST',
        body: formData
      });
      
      item.classList.remove('unread');
      updateBadge();
    } catch (error) {
      console.error('Error marking notification as read:', error);
    }
    
    // Navigate to URL if provided
    if (url) {
      dropdown.classList.remove('active');
      window.location.href = url;
    }
  }
  
  // Mark all as read
  markAllBtn.addEventListener('click', async (e) => {
    e.preventDefault();
    
    try {
      const formData = new FormData();
      formData.append('action', 'mark_all_read');
      
      const response = await fetch('/MY CASH/includes/notifications_api.php', {
        method: 'POST',
        body: formData
      });
      const result = await response.json();
      
      if (result.success) {
        notifications.forEach(n => n.is_read = 1);
        renderNotifications();
        updateBadge();
      }
    } catch (error) {
      console.error('Error marking all as read:', error);
    }
  });
  
  // Update badge count
  async function updateBadge() {
    try {
      const response = await fetch('/MY CASH/includes/notifications_api.php?action=get_unread_count');
      const result = await response.json();
      
      if (result.success && result.data) {
        const count = result.data.count;
        badge.textContent = count;
        
        if (count > 0) {
          badge.classList.add('active');
        } else {
          badge.classList.remove('active');
        }
      }
    } catch (error) {
      console.error('Error updating badge:', error);
    }
  }
  
  // Helper functions
  function getIconForType(type) {
    const icons = {
      success: '✅',
      info: 'ℹ️',
      warning: '⚠️',
      error: '❌',
      task: '📋',
      payment: '💰',
      message: '💬',
      alert: '🔔'
    };
    return icons[type] || 'ℹ️';
  }
  
  function getTimeAgo(datetime) {
    const now = new Date();
    const past = new Date(datetime);
    const diff = Math.floor((now - past) / 1000); // seconds
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    return past.toLocaleDateString();
  }
  
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
  
  // Initial load
  updateBadge();
  
  // Poll for new notifications every 30 seconds
  setInterval(updateBadge, 30000);
})();
</script>

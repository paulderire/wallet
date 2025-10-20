NOTIFICATIONS.JSON - System Notifications Storage

This file stores manually created notifications that are NOT auto-generated.
The system will automatically generate the following notifications:

AUTO-GENERATED NOTIFICATIONS (from notifications.php):
══════════════════════════════════════════════════════

1. 💰 RECENT TRANSACTIONS (Last 5)
   - Money Received (deposits)
   - Money Spent (withdrawals)
   Shows: Amount, category, timestamp

2. 🎯 GOAL ACHIEVEMENTS
   - Alerts when your current balance can complete a goal
   - Progress alerts when goal is 80%+ complete
   Shows: Goal name, amount needed, percentage complete

3. 📅 LOAN DUE DATES (Next 30 days)
   - 🚨 URGENT: 1-3 days before due date
   - ⚠️ WARNING: 4-7 days before due date
   - 📅 REMINDER: 8-30 days before due date
   Shows: Lender, remaining amount, days until due

4. 💳 BUDGET ALERTS
   - 🚫 EXCEEDED: Over 100% of budget
   - ⚠️ WARNING: 80-99% of budget used
   Shows: Category, spent amount, percentage, remaining

5. 💵 EXPECTED INCOME
   - Detects recurring deposit patterns
   - Predicts expected income based on history
   Shows: Average amount, category pattern

6. ⚠️ LOW BALANCE WARNINGS
   - Alerts when account balance < $100
   Shows: Account name, current balance


MANUAL NOTIFICATIONS (stored in notifications.json):
═══════════════════════════════════════════════════

You can add custom notifications here in this format:
{
    "id": "unique_id",
    "title": "Notification Title",
    "body": "Full notification message",
    "time": "2025-10-11 10:00",
    "is_read": false
}

Example use cases for manual notifications:
- System maintenance alerts
- New feature announcements
- Security updates
- Special events
- Account verifications
- Admin messages

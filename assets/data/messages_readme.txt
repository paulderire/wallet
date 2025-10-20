MESSAGES.JSON - User Manual Messages Storage

This file stores user-created or admin-sent messages that are NOT auto-generated.
The system will automatically generate the following messages:

AUTO-GENERATED (from messages.php):
- Weekly/Monthly Financial Summaries
- Budget Alerts
- Savings Recommendations  
- Goal Progress Updates
- Spending Pattern Insights
- Account Performance Reports
- Financial Tips & Advice

MANUAL MESSAGES (stored in messages.json):
You can add custom messages here in this format:
{
    "id": "unique_id",
    "from": "Sender Name",
    "subject": "Message Subject",
    "preview": "Full message content here",
    "time": "2025-10-11 10:00",
    "is_read": false
}

Example use cases for manual messages:
- Welcome messages
- Feature announcements
- Special promotions
- Security alerts
- Account notifications
- Admin communications

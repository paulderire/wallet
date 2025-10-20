# 🚀 Quick Deployment Guide - MY CASH App

## Step-by-Step Production Deployment

### Step 1: Prepare Files (10 minutes)

1. **Remove test files:**
```bash
rm health_check.php
rm cleanup_test_data.php
rm setup_notifications.php
rm HEALTH_CHECK_REPORT.md
rm NOTIFICATIONS_SETUP_GUIDE.md
rm dev_tools/test_ai_call.php
```

2. **Create .htaccess in root:**
```apache
# MY CASH - Production .htaccess

# Disable directory browsing
Options -Indexes

# Enable RewriteEngine
RewriteEngine On

# Force HTTPS (uncomment after SSL is installed)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Protect sensitive files
<FilesMatch "^(db\.php|\.env)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Security headers
<IfModule mod_headers.c>
    Header set X-XSS-Protection "1; mode=block"
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Disable PHP execution in uploads directory
<Directory "assets/uploads">
    php_flag engine off
</Directory>
```

3. **Update includes/db.php:**
```php
<?php
// PRODUCTION DATABASE CONFIGURATION
$host = "YOUR_PRODUCTION_HOST";      // e.g., "localhost" or "mysql.example.com"
$dbname = "YOUR_DATABASE_NAME";      // e.g., "mycash_prod"
$username = "YOUR_DATABASE_USER";    // NOT "root" in production!
$password = "YOUR_STRONG_PASSWORD";  // Use a strong password!

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // In production, don't show error details
    error_log("Database connection failed: " . $e->getMessage());
    die("Service temporarily unavailable. Please try again later.");
}
```

4. **Create includes/production_config.php:**
```php
<?php
// PRODUCTION PHP SETTINGS

// Hide errors from users
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Log errors instead
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../assets/logs/php_errors.log');

// Session security
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);      // Requires HTTPS
ini_set('session.cookie_samesite', 'Strict');

// File upload limits
ini_set('upload_max_filesize', '5M');
ini_set('post_max_size', '10M');

// Timezone
date_default_timezone_set('UTC'); // Change to your timezone
```

5. **Update index.php (add at the very top):**
```php
<?php
// Load production configuration
require_once __DIR__ . '/includes/production_config.php';

// Rest of your existing code...
```

---

### Step 2: Upload to Server (15 minutes)

**Method 1: FTP/SFTP**
1. Use FileZilla, WinSCP, or similar
2. Connect to your hosting server
3. Upload ALL files to `public_html` or `www` directory
4. Set file permissions:
   - Files: 644
   - Directories: 755
   - **IMPORTANT:** Make `assets/uploads` writable (755)

### Step 2.5: Schedule Daily Backups (Windows)

If you are hosting on Windows (XAMPP), use Task Scheduler. Example PowerShell helper is provided at `MY CASH-dev_tools/schedule_backup.ps1` (moved outside web root).

Run PowerShell as Administrator and execute:

```powershell
& 'C:\xampp\php\php.exe' 'C:\xampp\htdocs\MY CASH-dev_tools\schedule_backup.ps1'
```

Security note: The `dev_tools/` directory in the webroot has been replaced with a shim that denies access. The real tooling is located at `C:\xampp\htdocs\MY CASH-dev_tools\`.

This creates a task `MYCASH_Daily_DB_Backup` that runs daily at 2:00 AM and executes the `backup_database.php` script.

Security note: The `dev_tools/` directory contains archived test scripts. Protect it with an `.htaccess` that denies public access or move it outside the web root.

### Enabling HTTPS Redirect

After installing a valid SSL certificate, uncomment the HTTPS redirect rules in `.htaccess` to force HTTPS site-wide. Do NOT enable the redirect until your certificate is installed and verified.

**Method 2: Git Deployment (Recommended)**
```bash
# On your local machine
git init
git add .
git commit -m "Initial production release"
git remote add origin YOUR_GIT_REPO
git push -u origin main

# On server (via SSH)
cd /var/www/html  # or your web root
git clone YOUR_GIT_REPO .
```

---

### Step 3: Database Setup (10 minutes)

**Via cPanel/PHPMyAdmin:**
1. Login to cPanel
2. Go to MySQL Databases
3. Create new database (e.g., `mycash_prod`)
4. Create new MySQL user with strong password
5. Add user to database with ALL PRIVILEGES
6. Note down: database name, username, password

**Import Database:**
1. Go to PHPMyAdmin
2. Select your new database
3. Click Import
4. Upload your `db/schema.sql` file
5. Execute

**Via Command Line (SSH):**
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE mycash_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Create user
mysql -u root -p -e "CREATE USER 'mycash_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD';"

# Grant privileges
mysql -u root -p -e "GRANT ALL PRIVILEGES ON mycash_prod.* TO 'mycash_user'@'localhost';"
mysql -u root -p -e "FLUSH PRIVILEGES;"

# Import schema
mysql -u mycash_user -p mycash_prod < db/schema.sql
```

---

### Step 4: Setup Notifications Tables (5 minutes)

**Option 1: Via PHPMyAdmin**
1. Open `db/notifications_messages_schema.sql` in text editor
2. Copy the SQL content
3. Go to PHPMyAdmin → Your Database → SQL
4. Paste and Execute

**Option 2: Via SSH**
```bash
mysql -u YOUR_USER -p YOUR_DATABASE < db/notifications_messages_schema.sql
```

---

### Step 5: Configure SSL/HTTPS (30 minutes)

**Using Let's Encrypt (Free):**

**Via cPanel:**
1. Go to SSL/TLS Status
2. Click "Run AutoSSL"
3. Wait for certificate installation
4. Enable "Force HTTPS Redirect"

**Via Certbot (Command Line):**
```bash
# Install Certbot
sudo apt-get update
sudo apt-get install certbot python3-certbot-apache

# Get certificate
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com

# Certbot will auto-configure Apache/Nginx
```

**Manual Setup:**
1. Purchase SSL certificate from provider
2. Upload certificate files to server
3. Configure in Apache/Nginx
4. Enable HTTPS redirect in .htaccess

---

### Step 6: Test Everything (20 minutes)

**Critical Tests:**
- [ ] Visit https://yourdomain.com
- [ ] Test admin login
- [ ] Test employee login
- [ ] Test user registration
- [ ] Upload a profile picture
- [ ] Create a transaction
- [ ] Send a message
- [ ] Toggle dark/light mode
- [ ] Test on mobile device
- [ ] Check all navigation links

**Check Logs:**
```bash
# View PHP errors
tail -f assets/logs/php_errors.log

# View Apache errors (if accessible)
tail -f /var/log/apache2/error.log
```

---

### Step 7: Performance Optimization (15 minutes)

**1. Enable OPcache (if not enabled):**
Add to `php.ini` or create `.user.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
```

**2. Enable GZIP Compression:**
Add to `.htaccess`:
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
</IfModule>
```

**3. Browser Caching:**
Add to `.htaccess`:
```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType text/javascript "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

---

### Step 8: Setup Monitoring (10 minutes)

**1. Uptime Monitoring (Free):**
- Sign up at https://uptimerobot.com
- Add monitor for your domain
- Set alert email

**2. Error Monitoring:**
Create a cron job to email errors:
```bash
# Add to crontab
0 */6 * * * /usr/bin/find /path/to/assets/logs/php_errors.log -mmin -360 -exec mail -s "PHP Errors" your@email.com < {} \;
```

**3. Backup Setup:**
Daily database backup cron:
```bash
# Add to crontab (runs daily at 2 AM)
0 2 * * * mysqldump -u YOUR_USER -pYOUR_PASSWORD YOUR_DATABASE > /path/to/backups/backup_$(date +\%Y\%m\%d).sql
```

---

### Step 9: Security Hardening (15 minutes)

**1. File Permissions:**
```bash
# Set correct permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Make uploads writable
chmod 755 assets/uploads
chmod 755 assets/uploads/avatars
chmod 755 assets/logs
chmod 755 assets/data
```

**2. Remove Development Files:**
```bash
rm -f health_check.php
rm -f cleanup_test_data.php
rm -f setup_notifications.php
rm -rf assets/tmp/test_*.php
```

**3. Secure includes folder:**
Add `.htaccess` in `includes/` directory:
```apache
Order deny,allow
Deny from all
```

---

### Step 10: Final Verification (10 minutes)

**Security Checklist:**
- [ ] HTTPS is enabled and working
- [ ] Database credentials changed from defaults
- [ ] Error display is OFF
- [ ] Error logging is ON
- [ ] File upload directory is protected
- [ ] Sensitive files are protected (.htaccess)
- [ ] Session security is enabled

**Performance Checklist:**
- [ ] OPcache is enabled
- [ ] GZIP compression is working
- [ ] Browser caching is configured
- [ ] Images are optimized

**Functionality Checklist:**
- [ ] All login systems work
- [ ] Database operations work
- [ ] File uploads work
- [ ] Email sending works (if implemented)
- [ ] All pages load correctly
- [ ] No console errors

---

## 🎉 CONGRATULATIONS!

Your MY CASH app is now live in production!

### Post-Launch Tasks:

**Day 1:**
- Monitor error logs closely
- Check user feedback
- Test all critical paths

**Week 1:**
- Review performance metrics
- Check backup integrity
- Monitor uptime

**Month 1:**
- Analyze usage patterns
- Optimize slow queries
- Plan feature updates

---

## 🆘 Troubleshooting

### White Screen of Death
```bash
# Enable error display temporarily
# Edit index.php, add at top:
ini_set('display_errors', 1);
error_reporting(E_ALL);
# Check what error shows, then disable again
```

### Database Connection Failed
1. Check credentials in `includes/db.php`
2. Verify database exists
3. Check user has correct permissions
4. Verify host is correct (often "localhost")

### 500 Internal Server Error
1. Check PHP version (needs 7.4+)
2. Review Apache error logs
3. Check .htaccess syntax
4. Verify file permissions

### File Upload Not Working
1. Check directory permissions (755)
2. Verify PHP upload settings
3. Check max file size limits
4. Ensure upload directory exists

---

## 📞 Support Resources

- **Hosting Support:** Contact your hosting provider
- **PHP Documentation:** https://www.php.net/docs.php
- **MySQL Documentation:** https://dev.mysql.com/doc/
- **Stack Overflow:** For specific coding issues

---

**Total Deployment Time: 2-3 hours**

Good luck with your launch! 🚀

#!/bin/bash
# bootstrap_mycash.sh
# Usage: run as root on Ubuntu 22.04+ (cloud VM)
# This script installs Apache, PHP 8.2, MariaDB, certbot, and prepares the app folder at /var/www/mycash
# IMPORTANT: review the file and adjust variables below before running in production.

set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

SITE_DIR="/var/www/mycash"
WWW_USER="www-data"
DB_NAME="mycash_prod"
DB_USER="mycash_user"
DB_PASS="ChangeMeStrong!"
ADMIN_EMAIL="admin@example.com"
DOMAIN="example.com" # change to your domain or leave blank for IP-based access

echo "Starting bootstrap for MY CASH..."

# Update + install prerequisites
apt update && apt upgrade -y
apt install -y software-properties-common curl gnupg2 ca-certificates lsb-release apt-transport-https unzip rsync git

# Add PHP PPA for PHP 8.2 (Ondrej)
add-apt-repository ppa:ondrej/php -y
apt update

# Install Apache, MariaDB, PHP and extensions
apt install -y apache2 mariadb-server php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip php8.2-gd php8.2-intl libapache2-mod-php8.2

# Install certbot
apt install -y certbot python3-certbot-apache

# Enable Apache modules
a2enmod rewrite ssl headers expires setenvif
systemctl restart apache2

# Secure MariaDB (non-interactive basic)
# Set a temporary root password if none exists and secure some defaults
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${DB_PASS}'; FLUSH PRIVILEGES;" || true
mysql -e "DELETE FROM mysql.user WHERE User='';" || true
mysql -e "DROP DATABASE IF EXISTS test;" || true
mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\_%';" || true
mysql -e "FLUSH PRIVILEGES;" || true

# Create database and user
mysql -u root -p"${DB_PASS}" -e "CREATE DATABASE IF NOT EXISTS \\`${DB_NAME}\\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}'; GRANT ALL PRIVILEGES ON \\`${DB_NAME}\\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"

# Create site dir
mkdir -p "${SITE_DIR}"
chown -R ${WWW_USER}:${WWW_USER} "${SITE_DIR}"
chmod -R 755 "${SITE_DIR}"

# Create Apache vhost
VHOST_FILE="/etc/apache2/sites-available/mycash.conf"
cat > "${VHOST_FILE}" <<EOF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    ServerAlias www.${DOMAIN}
    DocumentRoot ${SITE_DIR}

    <Directory ${SITE_DIR}>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \\${APACHE_LOG_DIR}/mycash_error.log
    CustomLog \\${APACHE_LOG_DIR}/mycash_access.log combined
</VirtualHost>
EOF

a2ensite mycash.conf
systemctl reload apache2

# Placeholders: create a .env or set Apache SetEnv values
ENVCONF="/etc/apache2/conf-available/mycash-env.conf"
cat > "${ENVCONF}" <<EOF
# MY CASH environment variables (do NOT commit to VCS)
SetEnv DB_HOST localhost
SetEnv DB_NAME ${DB_NAME}
SetEnv DB_USER ${DB_USER}
SetEnv DB_PASS ${DB_PASS}
EOF

a2enconf mycash-env
systemctl reload apache2

# Instructions for app deployment: copy files into ${SITE_DIR} (this script does NOT pull your app)
cat > /root/README_MY_CASH_DEPLOY.txt <<EOF
Bootstrap completed.
Next steps:
1. Copy your application files into ${SITE_DIR} (scp/rsync suggested).
2. Ensure includes/db.php reads env vars (the repo already supports getenv).
   If using .env style, store outside webroot and update includes/db.php.
3. Import schema: mysql -u ${DB_USER} -p'${DB_PASS}' ${DB_NAME} < /path/to/schema.sql
4. Run: sudo chown -R ${WWW_USER}:${WWW_USER} ${SITE_DIR}
5. Obtain Let's Encrypt cert (if you have DNS pointed): sudo certbot --apache -d ${DOMAIN} -d www.${DOMAIN}
6. Configure cron: sudo crontab -e and add: 0 2 * * * /usr/bin/php ${SITE_DIR}/backup_database.php >> /var/log/mycash_backup.log 2>&1

EOF

echo "Bootstrap completed. See /root/README_MY_CASH_DEPLOY.txt for next steps."
exit 0

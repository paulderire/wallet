Oracle Cloud Free Tier deployment guide for MY CASH

Overview
--------
This guide helps you provision an Always Free Oracle Cloud compute instance and bootstrap MY CASH using the provided `bootstrap_mycash.sh` script.

Steps
-----
1) Create an Oracle Cloud account and sign in. Ensure you have enabled the Always Free resources.
2) In the OCI console, create a new Compute instance:
   - Image: Canonical Ubuntu 22.04
   - Shape: always-free VM.Standard.E2.1.Micro (choose available)
   - Add your SSH public key for access
   - In advanced options, place the `cloud-init-oracle.yml` contents into the user-data field (optional)
3) SSH into the instance:
   ```bash
   ssh ubuntu@<YOUR_PUBLIC_IP>
   sudo su -
   # If you didn't use cloud-init, download and run bootstrap:
   curl -fsSL https://your-repo-url/scripts/bootstrap_mycash.sh -o /tmp/bootstrap_mycash.sh
   chmod +x /tmp/bootstrap_mycash.sh
   /tmp/bootstrap_mycash.sh
   ```
4) Copy your application files from local to `/var/www/mycash` (rsync recommended):
   ```bash
   rsync -avz --delete "C:/xampp/htdocs/MY CASH/" ubuntu@<YOUR_PUBLIC_IP>:/home/ubuntu/
   ssh ubuntu@<YOUR_PUBLIC_IP>
   sudo mv '/home/ubuntu/MY CASH' /var/www/mycash
   sudo chown -R www-data:www-data /var/www/mycash
   ```
5) Import the database schema:
   ```bash
   mysql -u mycash_user -p mycash_prod < /var/www/mycash/db/schema.sql
   ```
6) Obtain Let's Encrypt certificate (when DNS points to server):
   ```bash
   sudo certbot --apache -d example.com -d www.example.com
   ```
7) Configure daily backup cron (run as root):
   ```bash
   sudo crontab -e
   # add
   0 2 * * * /usr/bin/php /var/www/mycash/backup_database.php >> /var/log/mycash_backup.log 2>&1
   ```

Notes
-----
- Be sure to replace placeholder values (DB password, domain) in the bootstrap script before running in production.
- For security, consider storing DB_PASS in a secrets manager or set a unique strong password.

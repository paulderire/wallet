# PowerShell helper to register a scheduled task that runs the backup script daily at 2:00 AM
# Usage: Run PowerShell as Administrator and execute this script

$php = 'C:\xampp\php\php.exe'
$script = 'C:\xampp\htdocs\MY CASH\backup_database.php'
$action = New-ScheduledTaskAction -Execute $php -Argument "'" + $script + "'"
$trigger = New-ScheduledTaskTrigger -Daily -At 2am
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
Register-ScheduledTask -TaskName 'MYCASH_Daily_DB_Backup' -Action $action -Trigger $trigger -Principal $principal -Description 'Daily backup of MY CASH database at 02:00' -Force

Write-Host "Scheduled task 'MYCASH_Daily_DB_Backup' registered."

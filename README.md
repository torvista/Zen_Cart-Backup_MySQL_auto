# backup_mysql_auto
Script to backup Zen Cart database via a cron job
Tested to Zen Cart 1.56a, PHP 7.3

cron examples
normal:
/usr/local/bin/php -q /home/USERNAME/public_html/SHOP/ADMINFOLDER/backup_mysql_cron.php

if exec is disabled by default, but can be enabled by a php.ini in the script directory:
/usr/local/bin/ea-php71 -c /home/USERNAME/public_html/SHOP/ADMINFOLDER/php.ini -q /home/USERNAME/public_html/SHOP/ADMINFOLDERS/backup_mysql_cron.php

Changelog
2019 02 27: extra debugging/error messages. script should be run from admin directory.
2016 08 28: updated to cope with special chars in the database password 
2008 - based on  http://www.zen-cart.com/forum/showthread.php?t=106666


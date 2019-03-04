# zen-cart_backup_MySQL_auto
Script to backup a Zen Cart database via a cron job
Tested with Zen Cart 1.56a on PHP 7.3.
Uses only the admin configure.php (local or normal) from the Zen Cart fileset so should be compatible with any version.
Install in /YOURSHOPFOLDER/YOURADMINFOLDER/cgi-bin/

cron examples
check with your host for the correct php path to use
1) normal:
/usr/local/bin/php -q /home/USERNAME/public_html/SHOP/ADMINFOLDER/cgi-bin/backup_mysql_cron.php

2) if exec is disabled by default, but can be enabled by a php.ini in the script directory:
/usr/local/bin/php -c /home/USERNAME/public_html/SHOP/ADMINFOLDER//cgi-bin/php.ini -q /home/USERNAME/public_html/SHOP/ADMINFOLDERS//cgi-bin/backup_mysql_cron.php

Changelog
1.3 2019 03 04: modified to require less configuration
1.2 2019 02 27: extra debugging/error messages. script should be run from admin directory. Uploaded to plugins
1.1 2016 08 28: updated to cope with special chars in the database password 
1.0 2008 - based on  http://www.zen-cart.com/forum/showthread.php?t=106666


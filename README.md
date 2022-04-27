# Zen Cart - Backup MySQL auto/cron
Script to backup a Zen Cart database via a cron job.

Compatible with all versions Zen Cart. Requires minimum php 7.4.  
Since it uses only the admin version configure.php (local or production) from the Zen Cart fileset it should be compatible with any version.

Install in /YOURSHOPFOLDER/YOURADMINFOLDER/cgi-bin/

You may test it from the browser:  

www.yourshopaddress/YOURADMINFOLDER/cgi-bin/backup_mysql_cron.php

Maybe it will work out of the box, otherwise if errors occur, set $debug = true near the start of the script to debug.

Read the info in the script to help with possible changes you may have to make.

Probably the only thing you may have to modify will be the path to the mysqldump.exe, which creates the backup file.

You may report issues in Github.

https://github.com/torvista/Zen_Cart-Backup_MySQL_auto/issues


### cron examples

Often a lot of trial and error is necessary. Check with your host for the correct php path to use
1) normal:
/usr/local/bin/php -q /home/USERNAME/public_html/SHOP/ADMINFOLDER/cgi-bin/backup_mysql_cron.php

2) if exec is disabled by default, but can be enabled by a php.ini in the script directory:
/usr/local/bin/php -c /home/USERNAME/public_html/SHOP/ADMINFOLDER//cgi-bin/php.ini -q /home/USERNAME/public_html/SHOP/ADMINFOLDERS//cgi-bin/backup_mysql_cron.php

## Changelog
1.4 - 2022 04 27: fix for mysql 8 --column-statistics=0, fix for gz file not being created/dumpfile .sql not being deleted, strict mode, compatible with php 8, more debugging info.

1.3 - 2019 03 04: modified to require less configuration

1.2 - 2019 02 27: extra debugging/error messages. script should be run from admin directory. Uploaded to plugins

1.1 - 2016 08 28: updated to cope with special chars in the database password

1.0 - 2008: based on  http://www.zen-cart.com/forum/showthread.php?t=106666


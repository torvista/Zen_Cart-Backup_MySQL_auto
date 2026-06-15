# Zen Cart - Backup MySQL auto/cron v1.6
Script to backup a Zen Cart database via a cron job.

Requires minimum php 8.0.  
Since it uses only the admin configure.php (local or production) and database_tables.php from the Zen Cart fileset it should be compatible with any version of Zen Cart.

Install in /YOURSHOPFOLDER/YOURADMINFOLDER/cgi-bin/

You may test it from the browser:  

www.yourshopaddress/YOURADMINFOLDER/cgi-bin/backup_mysql_cron.php

Maybe it will work out of the box, but probably you will have to modify/add the path to the mysqldump.exe which creates the backup file.

If errors occur, add ?debug=1 to the url to show debug output.

To speed up debugging, an option has been added to not create the backup file: add to the url ?no_dump=1

READ THE INFO IN THE SCRIPT to help with possible changes you may have to make.

Note that the browser may not show anything while it is processing a backup file, so appears to be hung: check the output folder to see if the .sql file has been created...

## Sessions table
This may be huge and is not of value for a restore, so an option has been added to not backup the data from that table: add to the url ?no_sessions=1  
Or, use the override in the script to avoid having a parameter in the cron command.


## Problems
You may report issues in Github.

https://github.com/torvista/Zen_Cart-Backup_MySQL_auto/issues

## cron examples

Often a lot of trial and error is necessary to formulate the correct path/command to use on your server. Check with your host for the correct php path to use
1) normal:
/usr/local/bin/php -q /home/USERNAME/public_html/SHOP/ADMINFOLDER/cgi-bin/backup_mysql_cron.php

2) if exec is disabled by default, but can be enabled by a php.ini in the script directory:
/usr/local/bin/php -c /home/USERNAME/public_html/SHOP/ADMINFOLDER//cgi-bin/php.ini -q /home/USERNAME/public_html/SHOP/ADMINFOLDERS//cgi-bin/backup_mysql_cron.php

## Changelog

1.6 - 2026 06 15: use catalog configure.php (admin configure.php was dropped in ZC3+)

1.6 - 2025 12 31: add option to not backup the sessions table, which can get bloated. Added parameters to urls.

1.5 - 2025 12 08: better fix for mysql >8 --column-statistics=0 and maria, and a general spring-clean.

1.4 - 2022 04 27: fix for mysql 8 --column-statistics=0, fix for gz file not being created/dumpfile .sql not being deleted, strict mode, compatible with php 8, more debugging info.

1.3 - 2019 03 04: modified to require less configuration

1.2 - 2019 02 27: extra debugging/error messages. script should be run from admin directory. Uploaded to plugins

1.1 - 2016 08 28: updated to cope with special chars in the database password

1.0 - 2008: based on  http://www.zen-cart.com/forum/showthread.php?t=106666


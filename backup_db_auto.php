<?php
//backup database script for Zen Cart
//torvista
//based on http://www.zen-cart.com/forum/showthread.php?t=106666

//Configuration
//Edit this to YOUR admin folder name. This script uses the default /backups folder which is located in the admin folder.
$admin_folder_name = 'YOUR FOLDER NAME';//put your admin folder name here, no delimiters

/*This file can be located anywhere, recommend locating it above the public_html folder to prevent any non-cron access.
 * adjust the following path accordingly to allow it to find the admin folder named above
 * if running from a browser for debugging, a relative path works: "$admin_path = "../$admin_folder_name/";
 * if running from a cron job, needs an absolute path: $admin_path = '/usr/home/XXXXXX/www/htdocs/XXXXadmin/';
 * the correct cron command can be a headache, ask your hosting for help.
 * an example that works for me: "/usr/local/bin/php -q / FULL PATH /backup_db_auto.php"
 */

//Edit this for your server
$admin_path = "/home/FULL PATH TO YOUR SHOP/$admin_folder_name/";//full path needed for a cron job.

//Edit these to suit your hosting and your local development server
$mysqltool_remote = '/usr/bin/mysqldump';
$mysqltool_local = 'c:/xampp7080/mysql/bin/mysqldump.exe';

$debug = '';//'1' or '': shows extra info if 1 otherwise bare text output for cron result email

/***********************************************************************************/

define('OS_DELIM_WIN', '"');
define('OS_DELIM_NIX', "'");
(stristr(PHP_OS,"win") ? $os_delim = OS_DELIM_WIN : $os_delim = OS_DELIM_NIX);//when password has special chars, windows and nix need different delimiters or get mysqldump error 2 when access is refused
$lf = ($debug ? "<br />\n" : "\n");//to avoid cron email status not being littered with html tags
$redirect = '';//sends to other place IF debug not set also

if (!$debug && $redirect) ob_start();
/**
 * GZIPs a file on disk (appending .gz to the name)
 *
 * From http://stackoverflow.com/questions/6073397/how-do-you-create-a-gz-file-using-php
 * Based on function by Kioob at:
 * http://www.php.net/manual/en/function.gzwrite.php#34955
 *
 * @param string $source Path to file that should be compressed
 * @param integer $level GZIP compression level (default: 9)
 * @return string New filename (with .gz appended) if success, or false if operation fails
 */
function gzCompressFile($source, $level = 9)
{
    $dest = $source . '.gz';
    $mode = 'wb' . $level;
    $error = false;
    if ($fp_out = gzopen($dest, $mode)) {
        if ($fp_in = fopen($source, 'rb')) {
            while (!feof($fp_in))
                gzwrite($fp_out, fread($fp_in, 1024 * 512));
            fclose($fp_in);
        } else {
            $error = true;
        }
        gzclose($fp_out);
    } else {
        $error = true;
    }
    if ($error)
        return false;
    else
        unlink($source);
    return $dest;
}

if ($debug) { ?>
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <title>Automated Mysql Backup Tool</title>

        <style type="text/css">
            body, td, th {
                font-family: Arial, Helvetica, sans-serif;
                font-size: 12px;
            }

            h1 {
                font-size: 14px;
            }
        </style>
    </head>
    <body>
    <h1>Backup database script</h1>
<?php }

if (file_exists($admin_path . 'includes/local/configure.php')) {
    $local = true;
    $error = false;
    //load any local(user created) configure file.
    require($admin_path . 'includes/local/configure.php');
    $mysqltool = $mysqltool_local;
    //unset ($gzipfilename);
    if ($debug) echo "Using /local/configure.php $lf";
} elseif (file_exists($admin_path . 'includes/configure.php')) {
    $error = false;
    require($admin_path . 'includes/configure.php');
    $mysqltool = $mysqltool_remote;
    if ($debug) echo " Using /includes/configure.php $lf";
} else {
    echo ($debug ? "$admin_path" : '') . "includes/configure.php NOT FOUND $lf";
    $error = true;
}

if ( !file_exists($mysqltool) ) {
    echo 'mysqldump NOT FOUND' . ($debug ? " at :$mysqltool" : '') . $lf;
    $error = true;
}

if (!$error) {
    $output = $admin_path . 'backups/backup.log';
    $dump_results = $admin_path . 'backups/backup-dump.log';
    $backup_file = 'db_' . DB_DATABASE . '-' . date('Y-m-d_H-i-s') . '_auto.sql';
    $dump_params = ' "--host=' . DB_SERVER . '"';
    $dump_params .= ' "--user=' . DB_SERVER_USERNAME . '"';
    //$dump_params .= ' "--password=' . DB_SERVER_PASSWORD . '"';
    $dump_params .= ' --password=' . $os_delim . DB_SERVER_PASSWORD . $os_delim;//NIX DEFINITELY needs single quotes around the filename when shell metacharacters *%&$& etc. are in the password
    $dump_params .= ' --opt';
    $dump_params .= ' --complete-insert';
    $dump_params .= ' "--result-file=' . $admin_path . 'backups/' . $backup_file . '"';
    $dump_params .= ' ' . DB_DATABASE;
    $dump_params .= " 2>&1";

    exec($mysqltool . $dump_params, $output, $return_dump);

    if ($debug) echo "<p>SQL Backup file: $lf";
    if (!$return_dump) {
        echo ($debug ? $admin_path . 'backups/' : '') . $backup_file . $lf . "created $lf";
    } else {
        echo "SQL FILE NOT CREATED" . ($debug ? ": $return_dump<br />$dump_params" : '') . $lf;
        $error = true;
    }
    if ($debug) echo "</p>";

    if (!$error) {
        $backup_file = gzCompressFile($admin_path . 'backups/' . $backup_file);
        if ($debug) {
            echo '<p>SQL GZIP Backup file:<br />' . $backup_file . '<br />created</p>';
        } elseif ($backup_file != false) {//gzip created
            echo str_replace($admin_path . 'backups/', '', $backup_file) . "$lf created $lf";
        }

        if ($backup_file == false) {//gzip NOT created
            echo "ERROR gzip NOT created $lf";
        }
    }

}
?>
<?php if (!$debug && $redirect) {
// clear out the output buffer
    while (ob_get_status()) {
        ob_end_clean();
    }
//header("Location: http://www.motorvista.es/tienda/index.php");
}
if ($debug) echo '</body></html>';
<?php

declare(strict_types=1);
/**
 * Backup MySQL with a cron job
 * place this file in /YOURSHOPFOLDER/YOURADMIN/FOLDER/cgi-bin/
 *
 * @link https://github.com/torvista/Zen_Cart-Backup_MySQL_auto
 * forum thread: https://www.zen-cart.com/showthread.php/225138-Backup-MySQL-automatically-via-a-Cron-job
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @author torvista
 * @version 1.5
 * @updated 08 12 2025 torvista
 */

// DEBUGGING
// false: default setting. Displays minimum confirmation text for cron result email.
// true: for debugging only. Note that it will show the MySQL database PASSWORD.
$debug = false;

if ($debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// CONFIGURATION

// Local/Development Server
// enter the COMPLETE path to the mysqldump executable to this array.
// There are multiple values so the same script will work on your production and test environment
$mysqltool_locations = [
    '/usr/bin/mysqldump',
    'c:/xampp.7.3.3/mysql/bin/mysqldump.exe',
    'c:/laragon/bin/mysql/mysql-8.0.27-winx64/bin/mysqldump.exe',
    'c:/laragon/bin/mysql/mariadb-11.1.2-winx64/bin/mysqldump.exe',
];

/*****************************************************************************/

// The script needs the timezone set to use the correct date in backup filename
if (date_default_timezone_get()) {
    date_default_timezone_set(date_default_timezone_get());
} elseif (ini_get('date.timezone')) {
    date_default_timezone_set(ini_get('date.timezone'));
}

// Initialise variables
$error = false;
$mysqltool = '';
$mysql_version = 0;
const OS_DELIM_WIN = '"';
const OS_DELIM_NIX = "'";
$slash = DIRECTORY_SEPARATOR;
$path_to_admin = str_replace($slash . 'cgi-bin', '', __DIR__);

// If password has special chars, windows and *nix need different delimiters, or you get a mysqldump error 2 when access is refused for the bad password
$os_delim = stripos(PHP_OS_FAMILY, "win") !== false ? OS_DELIM_WIN : OS_DELIM_NIX;

// Is script being run from the browser (then use html for display) or via cron (don't use html tags, for more readable confirmation email)
$cron_shell = !isset($_SERVER['SERVER_NAME']);
// Clean html tags from the cron result status email
$lf = ($cron_shell ? "\n" : "<br>\n");

$redirect = false;//sends to another place IF debug not set also

if (!$debug && $redirect) {
    ob_start();
}

/**
 * GZIPs a file on disk (appending .gz to the name)
 *
 * From http://stackoverflow.com/questions/6073397/how-do-you-create-a-gz-file-using-php
 * Based on function by Kioob at:
 * http://www.php.net/manual/en/function.gzwrite.php#34955
 *
 * @param  string  $source  Path to file that should be compressed
 * @param  int  $level  GZIP compression level (default: 9)
 *
 * @return bool|string New filename (with .gz appended) if success, or false if operation fails
 */
function gzCompressFile(string $source, int $level = 9): bool|string
{
    global $debug, $lf;
    $dest = $source . '.gz';
    $mode = 'wb' . $level;
    $error = false;
    if ($fp_out = gzopen($dest, $mode)) {
        if ($fp_in = fopen($source, 'rb')) {
            while (!feof($fp_in)) {
                gzwrite($fp_out, fread($fp_in, 1024 * 512));
            }
            fclose($fp_in);
        } else {
            $error = true;
        }
        gzclose($fp_out);
    } else {
        $error = true;
    }
    if ($error) {
        if ($debug) {
            echo "gzCompressFile: ERROR$lf";
        }
        return false;
    }

    if ($debug) {
        echo "gzCompressFile: gz file created:$lf $dest $lf";
    }

    if (unlink($source)) {
        if ($debug) {
            echo "gzCompressFile: source .sql dump file deleted$lf";
        }
    } else {
        echo "gzCompressFile: ERROR gz compressed file NOT deleted$lf";
        if ($debug) {
            echo "$dest $lf";
        }
    }
    return $dest;
}

if ($debug && !$cron_shell) { ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <title>MySQL Backup Tool</title>
        <style>
            body {
                font-family: Arial, Helvetica, sans-serif;
                font-size: 12px;
            }

            h1 {
                font-size: 14px;
            }

            h2 {
                font-size: 13px;
            }
        </style>
    </head>
    <body>
    <h1>MySQL Backup Tool</h1>
    <h2>Debug ON</h2>
    <?php
}
if ($debug) {
    echo 'Script called from a ' . ($cron_shell ? 'cron/shell' : 'browser') . ".$lf";
}

// check to see if "exec()" is disabled in PHP -- if so, won't be able to use this tool.
$php_disabled_functions = @ini_get("disable_functions");

// check environment
if (in_array('exec', explode(",", str_replace(' ', '', $php_disabled_functions)), true)) {
    echo " $lf ERROR! exec not available: this script cannot run. $lf";
    echo "PHP directive: disable_functions=$php_disabled_functions $lf";
    echo "Contact your host for possible fixes.$lf";
    echo "eg. php.ini with disable_functions omitting exec in script directory. $lf";
    echo "For cron add php.ini parameter, eg:$lf";
    echo "/usr/local/bin/php -c /home/YOURUSER/public_html/YOURSHOP/YOURSHOPADMIN/php.ini -q /home/YOURUSER/public_html/YOURSHOP/YOURSHOPADMIN/backup_mysql_cron.php";
    die;
}

// use correct configure.php
if (file_exists($configure_file = $path_to_admin . $slash . 'includes' . $slash . 'local' . $slash . 'configure.php')) {
    require($configure_file);
} elseif (file_exists($configure_file = $path_to_admin . $slash . 'includes' . $slash . 'configure.php')) {//on hosting, using cron, needs full path
    require($configure_file);
}
if ($debug) {
    echo "Using $configure_file $lf";
}
foreach ($mysqltool_locations as $value) {
    if (file_exists($value)) {
        $mysqltool = $value;
        break;
    }
}
if (empty($mysqltool)) {
    die('ERROR: mysqldump.exe NOT FOUND.' . (!$debug ? ' Set debug=true in script for details.' : '') . $lf);
}

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);;
$version = $mysqli->server_info;
$db_type = stripos($version, "MariaDB") !== false ? 'maria' : 'mysql';
if ($db_type === 'mysql') {
    $mysql_version = mysqli_get_client_version();
}
if ($debug) {
    echo "mysqldump found at: $mysqltool $lf";
    echo "Database server=$version $lf";
    echo($mysql_version > 0 ? "mysql version=$mysql_version . $lf" : '');
}

$backup_path = $path_to_admin . $slash . 'backups' . $slash;
$backup_filename = 'db_' . DB_DATABASE . '-' . date('Y-m-d_H-i-s') . '_auto.sql';//name of the backup file
$backup_dump = $backup_path . $backup_filename;

$dump_params = '';
//default parameter in mysqldump 8 onwards https://serverfault.com/questions/912162/mysqldump-throws-unknown-table-column-statistics-in-information-schema-1109
// maria does not have this parameter at all
if ($db_type === 'mysql' && !str_starts_with((string)$mysql_version, '8')) {
    $dump_params .= $mysql_version >= 8 ? ' --column-statistics=0 ' : '';
}

$dump_params .= ' "--host=' . DB_SERVER . '"';
$dump_params .= ' "--user=' . DB_SERVER_USERNAME . '"';
$dump_params .= ' --password=' . $os_delim . DB_SERVER_PASSWORD . $os_delim;//NIX DEFINITELY needs single quotes around the filename when shell metacharacters *%&$& etc. are in the password
$dump_params .= ' --opt';
$dump_params .= ' --complete-insert';
$dump_params .= ' "--result-file=' . $backup_dump . '"';
$dump_params .= ' ' . DB_DATABASE;
$dump_params .= " 2>&1";
$command = $mysqltool . $dump_params;

$output = '';
$return_dump = '';

exec($command, $output, $return_dump);

if ($return_dump == 0) {//success on 0
    if ($debug) {
        echo "mysqdump executed$lf command=$lf" . ($cron_shell ? $command : htmlspecialchars($command)) . $lf;
        echo ".sql dumpfile created ok$lf";
    }

    if (!file_exists($backup_dump)) {
        $error = true;
        echo "ERROR! .sql dumpfile NOT FOUND$lf";
        if ($debug) {
            echo "$backup_filename $lf";
        }
    } else {
        if ($debug) {
            echo "dumpfile exists:$lf $backup_dump $lf";
        }
    }
} else {//mysqldump returned an error
    $error = true;
    echo "ERROR: .sql dump file NOT CREATED" . (!$debug ? ': set debug=true in cron script for details' : '') . $lf;
    if ($debug) {
        echo "command=$lf" . ($cron_shell ? $command : htmlspecialchars($command)) . $lf;
        echo "exec return value ==$lf";
        var_dump($return_dump);
        echo $lf;
        echo "error messages=$lf";
        if (!$cron_shell) {
            echo '<pre>';
        }
        print_r($output);//show any console error messages
        if (!$cron_shell) {
            echo '</pre>';
        }
    }
}

//compress file
if ($error) {
    echo "gz file NOT created due to previous error$lf";
} else {
    $backup_dump = gzCompressFile($backup_dump);
    if ($backup_dump === false) {//gzip not created
        echo "ERROR: .sql.gz file was NOT created from .sql dumpfile$lf";
    } else { //all OK
        echo "gz file created ok: " . str_replace($backup_path, '', $backup_dump) . $lf . $lf;
        if ($debug) {
            echo "Script Completed ok";
        }
    }
}

if (!$debug && $redirect) {
// clear out the output buffer
    while (ob_get_status()) {
        ob_end_clean();
    }
}
if ($debug && !$cron_shell) {
    echo '</body></html>';
}

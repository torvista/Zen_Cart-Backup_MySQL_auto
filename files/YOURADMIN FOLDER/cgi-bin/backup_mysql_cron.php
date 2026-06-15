<?php

declare(strict_types=1);
/**
 * Script to back up MySQL with a cron job
 * place this file in /YOURSHOPADMINFOLDER/cgi-bin/
 *
 * @link https://github.com/torvista/Zen_Cart-Backup_MySQL_auto
 * @link https://www.zen-cart.com/showthread.php/225138-Backup-MySQL-automatically-via-a-Cron-job
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @author torvista
 * @version 1.6
 * @updated 31 Dec 2025 torvista
 *
 */
// CONFIGURATION
// Paths to mysqldump
// Enter the COMPLETE path to the mysqldump executable in this array.
// Use multiple values so the same script will work on both your production and test environments.
$mysqltool_locations = [
    '/usr/bin/mysqldump',
    'c:/xampp.7.3.3/mysql/bin/mysqldump.exe',
    'c:/laragon/bin/mysql/mysql-8.0.27-winx64/bin/mysqldump.exe',
    'c:/laragon/bin/mysql/mariadb-11.1.2-winx64/bin/mysqldump.exe',
];

// The sessions table can grow very large. This data can be optionally omitted from the backup file.
// use a url parameter: ...backup_mysql_cron.php?no_sessions=1
$no_sessions = !empty($_GET['no_sessions']);
// or override the url parameter
//$no_sessions = true;

// for the backup filename suffix...season to your taste
$time_format = 'Y_m_d-T-H_i_s'; // 2025_12_31-CET-14_02_21

// DEBUGGING:
// debug = 0; default/live setting. Displays minimum confirmation text for the cron result email.
// debug = 1/true/whatever; displays debugging info at each step, when executed from a browser. Note that it will show the MySQL database PASSWORD.
// use a url parameter: ...backup_mysql_cron.php?debug=1
$debug = !empty($_GET['debug']);
// or override the url parameter
//$debug = true;

// For faster debugging, don't create the backup file
// Use a url parameter: ...backup_mysql_cron.php?no_dump=1
$no_dump = !empty($_GET['no_dump']);
// or override the url parameter
//$no_dump = true;

/*******************************/

if ($debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// The script needs the timezone set to use the correct date in backup filename
if (date_default_timezone_get()) {
    date_default_timezone_set(date_default_timezone_get());
    if ($debug) {
        echo 'date_default_timezone_get()=' . date_default_timezone_get() . PHP_EOL;
    }
} elseif (ini_get('date.timezone')) {
    date_default_timezone_set(ini_get('date.timezone'));
    if ($debug) {
        echo ': ini_get("date.timezone"=' . ini_get('date.timezone') . PHP_EOL;
    }
}

//override server timezone
date_default_timezone_set('Europe/Madrid');
$current_time = date($time_format);

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

// Is script being run from the browser (then use HTML for display) or via cron (don't use HTML tags, for a more readable confirmation email)
$cron_shell = !isset($_SERVER['SERVER_NAME']);

// Clean HTML tags from the cron result status email
$lf = ($cron_shell ? "\n" : "<br>\n");

$redirect = false;//sends to another place IF debug not set also

/**
 * @return void
 */
function execute_dump(): void
{
    global $backup_dump_file, $backup_filename, $no_dump, $cron_shell, $debug, $dump_params, $error, $lf, $mysqltool;
    if ($debug) {
        echo __FUNCTION__ . $lf;
    }
    $command = $mysqltool . $dump_params;

    $output = '';
    $return_dump = '';

    if ($debug) {
        echo "mysqdump command=$lf" . ($cron_shell ? $command : htmlspecialchars($command)) . $lf;
    }
    if ($no_dump) {
        echo '*.sql dumpfile NOT created ($no_dump=true)' . $lf;
    } else {
        exec($command, $output, $return_dump);

        if ($return_dump == 0) {//success on 0
            if ($debug) {
                echo "*.sql dumpfile created$lf";
            }

            if (!file_exists($backup_dump_file)) {
                $error = true;
                echo "ERROR! .sql dumpfile NOT FOUND$lf";
                if ($debug) {
                    echo "$backup_filename $lf";
                }
            } elseif ($debug) {
                echo "dumpfile exists:$lf $backup_dump_file $lf";
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
    }
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

if (!$debug && $redirect) {
    ob_start();
}

if ($debug && !$cron_shell) { ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta http-equiv="content-type" content="no-cache, no-store, must-revalidate"/>
        <meta http-equiv="refresh" content="no-cache"/>
        <!--<meta http-equiv="refresh" content="0"/>-->
        <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
        <title>MySQL Backup Tool</title>
        <style>
            body {
                font-family: Verdana, Arial, Helvetica, sans-serif;
                font-size: 13px;
                line-height: 18px;
            }

            h1 {
                font-size: 15px;
            }

            h2 {
                font-size: 14px;
            }
        </style>
    </head>
    <body>
    <h1>MySQL Backup Tool</h1>
    <h2>Debug ON</h2>
    <?php
    if ($debug) {
        echo 'Script called from a ' . ($cron_shell ? 'cron/shell' : 'browser') . $lf;
    }
}

// check to see if 'exec()" is disabled in PHP -- if so, won't be able to use this tool.
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
} else {
    die('configure.php NOT found');
}
if (file_exists(DIR_FS_CATALOG . 'includes/database_tables.php')) {
    require(DIR_FS_CATALOG . 'includes/database_tables.php');
} else {
    die('database_tables.php not found at ' . DIR_FS_CATALOG . 'includes/database_tables.php');
}

if ($debug) {
    echo "configure.php=$configure_file $lf";
}
// Parse mysqldump paths to find the one on this server.
foreach ($mysqltool_locations as $value) {
    if (file_exists($value)) {
        $mysqltool = $value;
        break;
    }
}
if (empty($mysqltool)) {
    die('ERROR: mysqldump.exe NOT FOUND.' . (!$debug ? ' Set debug=true in script for more detail.' : '') . $lf);
}

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
$version = $mysqli->server_info;
$db_type = stripos($version, "MariaDB") !== false ? 'maria' : 'mysql';
if ($db_type === 'mysql') {
    $mysql_version = mysqli_get_client_version();
}

$backup_path = $path_to_admin . $slash . 'backups' . $slash;
$backup_filename = 'db_' . DB_DATABASE . '-' . $current_time . ($no_sessions ? '-no-sessions' : '') . '_auto.sql';
$backup_dump_file = $backup_path . $backup_filename;

if ($debug) {
    echo 'Timezone=' . date_default_timezone_get() . ', ' . $current_time . ' (for dumpfile name)' . $lf;
    echo "mysqldump exe=$mysqltool $lf";
    echo "Database server=$version $lf";
    echo ($mysql_version > 0 ? "mysql version=$mysql_version . $lf" : '');
    echo '$backup_dump_file name =' . $backup_dump_file . $lf;
    echo ($no_sessions ? '$no_sessions=true: data from table "' . TABLE_SESSIONS . '" will be omitted from the backup' . $lf : '');
    echo ($no_dump ? '$no_dump=true: the backup file will not be created' . $lf : '');
    echo '<hr>' . PHP_EOL;
}

$dump_params_base = ' "--host=' . DB_SERVER . '"';
$dump_params_base .= ' ' . DB_DATABASE;
$dump_params_base .= ' "--user=' . DB_SERVER_USERNAME . '"';
$dump_params_base .= ' --password=' . $os_delim . DB_SERVER_PASSWORD . $os_delim;//NIX DEFINITELY needs single quotes around the filename when shell metacharacters *%&$& etc. are in the password
$dump_params_base .= ' --complete-insert';
$dump_params_base .= ' --opt';
//$dump_params_base .= ' "--result-file=' . $backup_dump_file . '"';

// Mysql: add default parameter for mysqldump 8 onwards https://serverfault.com/questions/912162/mysqldump-throws-unknown-table-column-statistics-in-information-schema-1109
// maria does not have this parameter at all
if ($db_type === 'mysql' && !str_starts_with((string)$mysql_version, '8')) {
    $dump_params_base .= $mysql_version >= 8 ? ' --column-statistics=0 ' : '';
}

if ($no_sessions) {
// First pass creates schema and data without the sessions table
// Second pass adds the schema for the sessions table
// Example code
// mysqldump -u user -p db_name --ignore-table=db_name.table_to_omit > dump.sql
// mysqldump -u user -p db_name table_to_omit --no-data >> dump.sql
    if ($debug) {
        echo "no sessions: first pass$lf";
    }
    $dump_params = $dump_params_base . ' "--ignore-table=' . DB_DATABASE . '.' . TABLE_SESSIONS . '"';
    $dump_params .= ' > "' . $backup_dump_file . '"';
    execute_dump();
// Second pass
    if ($debug) {
        echo "no sessions: second pass$lf";
    }
    $dump_params = $dump_params_base . ' "' . TABLE_SESSIONS . '" --no-data';
    $dump_params .= ' >> "' . $backup_dump_file . '"';
    execute_dump();
} else {
    $dump_params = $dump_params_base . ' > "' . $backup_dump_file . '"';
    execute_dump();
}
if (!$no_dump) {
// Compress file
    if ($error) {
        echo "gz file NOT created due to previous error$lf";
    } else {
        $backup_dump_file = gzCompressFile($backup_dump_file);
        if ($backup_dump_file === false) {//gzip not created
            echo "ERROR: .sql.gz file was NOT created from .sql dumpfile$lf";
        } else { //all OK
            echo "gz file created ok: " . str_replace($backup_path, '', $backup_dump_file) . $lf . $lf;
            if ($debug) {
                echo "Script Completed OK<br><br>";
            }
        }
    }
} elseif ($debug) {
    echo 'Script Completed with no dumpfile ($no_dump=true)<br><br>';
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

<?php
//place in /YOURSHOPFOLDER/YOURADMIN/FOLDER/cgi-bin/
//backup database script for Zen Cart
//torvista 04/03/2019
//based on http://www.zen-cart.com/forum/showthread.php?t=106666

//INSTALLATION
//Put this file in the shop ADMIN directory and rename it/add some random suffix for extra security

//USE
//The script can be run from the browser (on demand/cron) or from the command line (cron).

//DEBUGGING
//false: default setting. Displays minimum confirmation text for cron result email.
//true: for debugging. Note that it will show the MySQL database PASSWORD.
$debug = false;

//CONFIGURATION

//HOSTING - Production Server
//enter the COMPLETE path to the mysqldump executable
$mysqltool_remote = '/usr/bin/mysqldump';

//Local/Development Server
//If you do not have a development server where you test modifications like this script, you are very silly indeed!
//add the COMPLETE path to the mysqldump executable to this array. I used an array so it will work in different test environments
$mysqltool_local =
    array(
        'c:/xampp.5.6.31/mysql/bin/mysqldump.exe',
        'c:/xampp.7.1.26/mysql/bin/mysqldump.exe',
        'c:/xampp.7.2.11/mysql/bin/mysqldump.exe',
        'c:/xampp.7.3.0/mysql/bin/mysqldump.exe'
    );
/*****************************************************************************/
//script needs timezone set for correct date in backup filename
if (date_default_timezone_get()) {
    date_default_timezone_set(date_default_timezone_get());
} elseif (ini_get('date.timezone')) {
    date_default_timezone_set(ini_get('date.timezone'));
}
//initialise variables
$error = false;
$mysqltool = '';
define('OS_DELIM_WIN', '"');
define('OS_DELIM_NIX', "'");
$slash = DIRECTORY_SEPARATOR;
$path_to_admin = str_replace($slash . 'cgi-bin', '', __DIR__);
(stristr(PHP_OS,
    "win") ? $os_delim = OS_DELIM_WIN : $os_delim = OS_DELIM_NIX);//when password has special chars, windows and *nix need different delimiters or you get a mysqldump error 2 when access is refused for the bad password
//is script being run from the browser (so use html for display) or via cron (don't use html tags for more readable confirmation email)
$cron_shell = isset($_SERVER['SERVER_NAME']) ? false : true;
$lf = ($cron_shell ? "\n" : "<br>\n");//to avoid cron result email status being littered with html tags

$redirect = '';//sends to other place IF debug not set also

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
 * @param string $source Path to file that should be compressed
 * @param integer $level GZIP compression level (default: 9)
 * @return string New filename (with .gz appended) if success, or false if operation fails
 */
function gzCompressFile($source, $level = 9, $debug)
{
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
        return false;
    } else {
        unlink($source);
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
                line-height: 1.5;
            }

            h1 {
                font-size: 16px;
            }

            h2 {
                font-size: 14px;
            }
        </style>
    </head>
    <body>
    <h1>MySQL Backup Tool</h1>
    <h2>Debug ON</h2>
<?php }

if ($debug) {
    echo 'Script called from a ' . ($cron_shell ? 'cron/shell' : 'browser') . ".$lf";
}

// check to see if "exec()" is disabled in PHP -- if so, won't be able to use this tool.
$php_disabled_functions = @ini_get("disable_functions");
if (in_array('exec', preg_split('/,/', str_replace(' ', '', $php_disabled_functions)))) {
    echo " $lf ERROR! exec not available: this script cannot run. $lf";
    echo "PHP directive: disable_functions=$php_disabled_functions $lf";
    echo "Contact your host for possible fixes.$lf";
    echo "eg. php.ini with disable_functions omitting exec in script directory. $lf";
    echo "For cron add php.ini parameter, eg:$lf";
    echo "/usr/local/bin/php -c /home/YOURUSER/public_html/YOURSHOP/YOURSHOPADMIN/php.ini -q /home/YOURUSER/public_html/YOURSHOP/YOURSHOPADMIN/backup_mysql_cron.php";
    die;
}

//check if this is a local server
if (file_exists($configure_file = $path_to_admin . $slash . 'includes' . $slash . 'local' . $slash . 'configure.php')) {
    //local server
    require($configure_file);
    if ($debug) {
        echo "LOCAL: Using $configure_file $lf";
    }

    foreach ($mysqltool_local as $value) {
        if (file_exists($value)) {
            $mysqltool = $value;
            if ($debug) {
                echo "mysqldump found at:$mysqltool $lf";
            }
        }
        if ($mysqltool != '') {
            break;
        }
    }

//check if this is the hosting server
} elseif (file_exists($configure_file = $path_to_admin . $slash . 'includes' . $slash . 'configure.php')) {//on hosting, using cron, needs full path
//REMOTE
    require($configure_file);
    if ($debug) {
        echo "REMOTE: Using $configure_file $lf";
    }

    $mysqltool = $mysqltool_remote;

    if (!file_exists($mysqltool)) {
        echo 'mysqldump NOT FOUND' . ($debug ? " at :$mysqltool" : '') . $lf;
        $error = true;
    } else {
        if ($debug) echo "mysqldump found at:$mysqltool $lf";
    }
} else {
    echo "ERROR! NO configuration file found $lf";
    $error = true;
}

if (!$error) {

    $backup_path = $path_to_admin . $slash . 'backups' . $slash;
    $backup_filename = 'db_' . DB_DATABASE . '-' . date('Y-m-d_H-i-s') . '_auto.sql';//name of the backup file
    $backup_dump = $backup_path . $backup_filename;

    $dump_params = ' "--host=' . DB_SERVER . '"';
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

    if ($return_dump === 0) {//success on 0
        echo "SUCCESS!$lf";
        if ($debug) {
            echo "mysqdump executed$lf command=$lf" . ($cron_shell ? $command : htmlspecialchars($command)) . $lf;
        }

        if (!file_exists($backup_dump)) {
            echo "ERROR! Backup dumpfile does not exist:$lf $backup_filename $lf";
        } elseif ($debug) {
            echo "dumpfile exists:$lf $backup_dump $lf";
        }

    } else {//mysqldump returned an error
        echo "ERROR! BACKUP FILE NOT CREATED" . (!$debug ? ': set debug=true in cron script for details' : '') . $lf;
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
        $error = true;
    }

//compress file
    if (!$error) {
        $backup_dump = gzCompressFile($backup_dump, '', $debug);

        if ($backup_dump != false) {//gzip created
            if ($debug) {
                echo "GZipped file created:$lf $backup_dump $lf Script completed";
            } else {//function returned false = fail
                echo "...GZipped file created: " . str_replace($backup_path, '', $backup_dump) . $lf;
            }
        } else {//gzip NOT created
            echo "ERROR! .sql.gz file NOT created from .sql file $lf";
        }
    }
}

?>
<?php if (!$debug && $redirect) {
// clear out the output buffer
    while (ob_get_status()) {
        ob_end_clean();
    }
}
if ($debug && !$cron_shell) {
    echo '</body></html>';
}
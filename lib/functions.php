<?php

// Initialization and basic functions

// application constants
define('KADM_START', microtime(true));
define('KADM_VERSION', '0.1');
define('KADM_CHARSET', 'utf-8');
define('INSTALL_PATH', dirname(__FILE__));

// Check critical PHP settings here.
$crit_opts = array(
    'mbstring.func_overload' => 0,
    'magic_quotes_runtime' => 0,
);

foreach ($crit_opts as $optname => $optval) {
    if ($optval != ini_get($optname)) {
        die("ERROR: Wrong '$optname' option value!");
    }
}

$include_path = INSTALL_PATH . PATH_SEPARATOR;
$include_path .= INSTALL_PATH . '/client' . PATH_SEPARATOR;
$include_path .= INSTALL_PATH . '/api' . PATH_SEPARATOR;
$include_path .= INSTALL_PATH . '/ext' . PATH_SEPARATOR;
$include_path .= ini_get('include_path');

if (set_include_path($include_path) === false) {
    die("Fatal error: ini_set/set_include_path does not work.");
}

ini_set('error_reporting', E_ALL&~E_NOTICE);
ini_set('error_log', INSTALL_PATH . '/../logs/errors');

// Set internal charset
mb_internal_encoding(KADM_CHARSET);
@mb_regex_encoding(KADM_CHARSET);

// register autoloader
function class_autoloader($classname) {
    $classname = preg_replace('/(Net|MDB2|HTTP)_(.+)/', "\\1/\\2", $classname);

    if ($fp = @fopen("$classname.php", 'r', true)) {
        include_once("$classname.php");
        fclose($fp);
        return true;
    }

    return false;
}

spl_autoload_register('class_autoloader');

function query($query, $_conn = 'kolab_wap') {
    require_once('SQL.php');

    $sql = SQL::get_instance($_conn);

    return $sql->query($query);
}

function need_login() {
    print "You are not logged in<br/>";
    print '<form method="post">';
    print '<input type="text" name="username" /><br/>';
    print '<input type="password" name="password" /><br/>';
    print '<input type="submit" name="submit" value="Log in"/></form>';
    echo "<pre>"; print_r($_SESSION); echo "</pre>";
    exit;
}

function valid_login() {
    // The $_SESSION variable is controlled through lib/User.php's
    // _authenticate()
    //
    return $_SESSION['user']->authenticated();
}

/**
 * Prints debug info into the 'console' log
 */
function console() {
    $args = func_get_args();

    $msg = array();
    foreach ($args as $arg) {
        $msg[] = !is_string($arg) ? var_export($arg, true) : $arg;
    }

    write_log('console', join(";\n", $msg));
}

/**
 * Appends a line to a log file in the logs directory.
 * Date will be added automatically to the line.
 *
 * @param string $name  Name of the log file
 * @param mixed  $line  Line to append
 */
function write_log($name, $line) {
    if (!is_string($line)) {
        $line = var_export($line, true);
    }

    $log_dir = dirname(__FILE__) . '/../logs';
    $logfile = $log_dir . '/' . $name;
    $date    = date('d-M-Y H:i:s O');
    $line    = sprintf("[%s](%s): %s\n", $date, session_id(), $line);

    if ($fp = @fopen($logfile, 'a')) {
        fwrite($fp, $line);
        fflush($fp);
        fclose($fp);
    }
}

<?php

/*
 * Kolab Admin Panel
 *
 * (C) Copyright 2011 Kolab Systems AG
 *
 */

// Initialisation and basic functions

// Check critical PHP settings here.
$crit_opts = array(
    'mbstring.func_overload' => 0,
    'magic_quotes_runtime' => 0,
    'session.use_cookies' => 1,
);
foreach ($crit_opts as $optname => $optval) {
    if ($optval != ini_get($optname)) {
        die("ERROR: Wrong '$optname' option value!");
    }
}

$include_path = INSTALL_PATH . '/lib' . PATH_SEPARATOR;
$include_path.= ini_get('include_path');

if (set_include_path($include_path) === false) {
    die("Fatal error: ini_set/set_include_path does not work.");
}

ini_set('error_reporting', E_ALL&~E_NOTICE);
ini_set('error_log', 'logs/errors');

// Set internal charset
mb_internal_encoding(KADM_CHARSET);
@mb_regex_encoding(KADM_CHARSET);

/**
 * Kolab Admin Classes Autoloader
 */
function kolab_admin_autoload($classname)
{
    if (preg_match('/^kolab_/', $classname)) {
        if (preg_match('/^kolab_admin_task_([a-z]+)$/', $classname, $m)) {
            $filename = INSTALL_PATH . '/include/tasks/' . $m[1] . '.php';
        }
        else {
            $filename = INSTALL_PATH . "/include/$classname.php";
        }

        if ($fp = @fopen($filename, 'r')) {
            fclose($fp);
            include_once($filename);
            return true;
        }
    }

    return false;
}

spl_autoload_register('kolab_admin_autoload');

/**
 * Prints debug info into the 'console' log
 */
function console()
{
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
function write_log($name, $line)
{
    if (!is_string($line)) {
        $line = var_export($line, true);
    }

    $log_dir = INSTALL_PATH . '/logs';
    $logfile = $log_dir . '/' . $name;
    $date    = date('d-M-Y H:i:s O');
    $line    = sprintf("[%s]: %s\n", $date, $line);

    if ($fp = @fopen($logfile, 'a')) {
        fwrite($fp, $line);
        fflush($fp);
        fclose($fp);
    }
}

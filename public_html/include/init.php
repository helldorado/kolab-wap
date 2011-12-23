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


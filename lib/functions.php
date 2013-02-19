<?php
/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab Web Admin Panel                           |
 |                                                                          |
 | Copyright (C) 2011-2012, Kolab Systems AG                                |
 |                                                                          |
 | This program is free software: you can redistribute it and/or modify     |
 | it under the terms of the GNU Affero General Public License as published |
 | by the Free Software Foundation, either version 3 of the License, or     |
 | (at your option) any later version.                                      |
 |                                                                          |
 | This program is distributed in the hope that it will be useful,          |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the             |
 | GNU Affero General Public License for more details.                      |
 |                                                                          |
 | You should have received a copy of the GNU Affero General Public License |
 | along with this program. If not, see <http://www.gnu.org/licenses/>      |
 +--------------------------------------------------------------------------+
 | Author: Aleksander Machniak <machniak@kolabsys.com>                      |
 | Author: Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>                     |
 +--------------------------------------------------------------------------+
*/

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

ini_set('error_reporting', E_ALL &~ E_NOTICE &~ E_STRICT);
ini_set('error_log', INSTALL_PATH . '/../logs/errors');

// Set internal charset
mb_internal_encoding(KADM_CHARSET);
@mb_regex_encoding(KADM_CHARSET);

// register autoloader
function class_autoloader($classname)
{
    $classname = preg_replace('/(Net|MDB2|HTTP)_(.+)/', "\\1/\\2", $classname);

    if ($fp = @fopen("$classname.php", 'r', true)) {
        include_once "$classname.php";
        fclose($fp);
        return true;
    }

    return false;
}

spl_autoload_register('class_autoloader');

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

    $log_dir = dirname(__FILE__) . '/../logs';
    $logfile = $log_dir . '/' . $name;
    $date    = date('d-M-Y H:i:s O');
    $sess_id = session_id();
    $logline = sprintf("[%s]%s: %s\n", $date, $sess_id ? "($sess_id)" : '', $line);

    if ($fp = @fopen($logfile, 'a')) {
        fwrite($fp, $logline);
        fflush($fp);
        fclose($fp);
        return;
    }

    if ($name == 'errors') {
        // send error to PHPs error handler if write to file didn't succeed
        trigger_error($line, E_USER_ERROR);
    }
}

function timer($time = null, $label = '')
{
    $now = microtime(true);
    if ($time) {
        console(($label ? $label.' ' : '') . sprintf('%.4f', $now - $time));
    }
    return $now;
}

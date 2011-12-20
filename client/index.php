<?php

/*
 * Kolab Admin Panel
 *
 * (C) Copyright 2011 Kolab Systems AG
 *
 */

// application constants
define('KADM_START', microtime(true));
define('KADM_VERSION', '0.1');
define('KADM_CHARSET', 'utf-8');
define('INSTALL_PATH', dirname($_SERVER['SCRIPT_FILENAME']).'/');

// environment initialization
require_once INSTALL_PATH . '/include/init.php';

// starting task
$task = kolab_utils::get_input('task', 'GET');

if (!$task) {
    $task = 'main';
}

$class = "kolab_admin_task_$task";

$KADM = new $class;

// run actions and send output
$KADM->run();
$KADM->send();

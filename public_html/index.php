<?php

/*
 * Kolab Admin Panel
 *
 * (C) Copyright 2011 Kolab Systems AG
 *
 */

// environment initialization
require_once '../lib/functions.php';

// starting task
$task = kolab_utils::get_input('task', kolab_utils::REQUEST_GET);

if (!$task) {
    $task = 'main';
}

$class = "kolab_admin_client_task_$task";

$KADM = new $class;

// run actions and send output
$KADM->run();
$KADM->send();

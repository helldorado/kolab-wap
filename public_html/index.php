<?php

    require_once(dirname(__FILE__) . "/../lib/functions.php");

    // starting task
    $task = kolab_utils::get_input('task', 'GET');

    console(__FILE__.":".__LINE__.": " . $task);

    if (!$task) {
        $task = 'main';
    }

    console(__FILE__.":".__LINE__.": " . $task);

    $class = "kolab_admin_client_task_$task";

    $KADM = new $class;

    // run actions and send output
    $KADM->run();
    $KADM->send();
?>

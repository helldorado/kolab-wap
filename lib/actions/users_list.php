<?php
    require_once(dirname(__FILE__) . "/../functions.php");

    require_once('Auth.php');

    $auth = Auth::get_instance();
    $users = $auth->list_users();
    $users = $auth->normalize_result($users);

    print json_encode($users);
?>

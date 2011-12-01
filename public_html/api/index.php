<?php
    require_once( dirname(__FILE__) . "/../../lib/functions.php");

    if (!valid_login()) {
        need_login();
    }

    if (!empty($_GET['object']) && !empty($_GET['action'])) {
        if (function_exists($_GET['object'] . '_' . $_GET['action'])) {
            call_user_func_array($_GET['object'] . '_' . $_GET['action']);
        } elseif (file_exists(dirname(__FILE__) . "/../../lib/actions/" . $_GET['object'] . '_' . $_GET['action'] . ".php")) {
            require_once(dirname(__FILE__) . "/../../lib/actions/" . $_GET['object'] . '_' . $_GET['action'] . ".php");
        }
    }

?>

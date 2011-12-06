<?php
    require_once( dirname(__FILE__) . "/../../lib/functions.php");

    // init frontend controller
    $controller = new kolab_admin_controller;

    try {
        $postdata = $_SERVER['REQUEST_METHOD'] == 'POST' ? @json_decode(file_get_contents('php://input'), true) : null;
        $controller->dispatch($postdata);
    } catch(Exception $e) {
        error_log($e->getMessage());
        $controller->output->error($e->getMessage(), $e->getCode());
    }

    // if we arrive here the controller didn't generate output
    $controller->output->error("Invalid request");

?>

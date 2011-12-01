<?php

    require_once("User.php");

    session_start();

    if ( isset($_COOKIE['PHPSESSID']) )
    {
        define("PHPSESSID",$_COOKIE['PHPSESSID']);
    }
    elseif ( session_id() )
    {
        $sid = explode("=",SID);
        define("PHPSESSID",$sid[1]);
    }

    if ( !isset($_SESSION['session_id']) )
        $_SESSION['session_id'] = session_id();

    // We attempt to find the user credentials and use them for authentication.
    // TODO: Attempt to figure out the authentication tech. and realm from;
    //
    // - the configuration,
    // - the credentials supplied,
    // - etc.
    require_once('User.php');

    if ( $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username']) && isset($_POST['password']) )
    {
        $_SESSION['user'] = new User();
        return $_SESSION['user']->authenticate($_POST['username'], $_POST['password']);
    }

    function append_sid($url)
    {
        if ( defined('PHPSESSID') && !preg_match('#sid=#', $url) )
        {
            $url .= ( strpos($url,'?') != FALSE ) ? '&sid=' . PHPSESSID : '?sid=' . PHPSESSID;
        }

        return $url;
    }

    if ( isset($_GET['new_template']) )
    {
        $_SESSION['template'] = $_GET['new_template'];
    }

    output_add_rewrite_var('sid',PHPSESSID);
?>


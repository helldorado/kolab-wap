<?php
    ini_set('include_path', dirname(__FILE__) . PATH_SEPARATOR . ini_get('include_path'));

    // These are just here for some statistics.
    list($usec, $sec) = explode(' ',microtime());

    $GLOBALS['parse_start'] = ((float)$usec + (float)$sec);

    // Initialize some runtime variables
    $ldap = Array();
    $sql = Array();
    $messages = Array();

    $sql_stats = Array(
            'queries' => 0,
            'query_time' => 0,
            'connections' => 0
        );

    require_once('Session.php');
    require_once('Conf.php');

    function query($query, $_conn = 'kolab_wap') {
        require_once('SQL.php');

        $sql = SQL::get_instance($_conn);

        return $sql->query($query);
    }

    function valid_login() {
        // The $_SESSION variable is controlled through lib/User.php's
        // _authenticate()
        //
        if ( isset($_SESSION['user']->_authenticated) ) {
            error_log("Logged in proper");
            return $_SESSION['user']->_authenticated;
        } else {
            error_log("Not logged in!");
            return FALSE;
        }

    }

    function need_login() {
        print "You are not logged in<br/>";
        print '<form method="post">';
        print '<input type="text" name="username" /><br/>';
        print '<input type="password" name="password" /><br/>';
        print '<input type="submit" name="submit" value="Log in"/></form>';
        echo "<pre>"; print_r($_SESSION); echo "</pre>";
        exit;
    }


?>

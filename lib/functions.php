<?php
    ini_set('include_path', dirname(__FILE__) . PATH_SEPARATOR . ini_get('include_path'));

    // These are just here for some statistics.
    list($usec, $sec) = explode(' ',microtime());

    $GLOBALS['parse_start'] = ((float)$usec + (float)$sec);

    // Initialize some runtime variables
    $messages = Array();

    require_once('Conf.php');

    // register autoloader
    function class_autoloader($classname) {
        $classname = preg_replace('/(Net|MDB2)_(.+)/', "\\1/\\2", $classname);

        if ($fp = @fopen("$classname.php", 'r', true)) {
            include_once("$classname.php");
            fclose($fp);
            return true;
        }

        return false;
    }

    spl_autoload_register('class_autoloader');

    function query($query, $_conn = 'kolab_wap') {
        require_once('SQL.php');

        $sql = SQL::get_instance($_conn);

        return $sql->query($query);
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

    function valid_login() {
        // The $_SESSION variable is controlled through lib/User.php's
        // _authenticate()
        //
        return $_SESSION['user']->authenticated();
    }

?>

<?php
    ini_set('include_path', dirname(__FILE__) . PATH_SEPARATOR . ini_get('include_path'));
    ini_set('include_path', dirname(__FILE__) . "/ext/" . PATH_SEPARATOR . ini_get('include_path'));

    // These are just here for some statistics.
    list($usec, $sec) = explode(' ',microtime());

    $GLOBALS['parse_start'] = ((float)$usec + (float)$sec);

    // Initialize some runtime variables
    $messages = Array();

    require_once('Conf.php');

    // register autoloader
    function class_autoloader($classname) {
        $classname = preg_replace('/(Net|MDB2|HTTP)_(.+)/', "\\1/\\2", $classname);

        if ($fp = @fopen("$classname.php", 'r', true)) {
            include_once("$classname.php");
            fclose($fp);
            return true;
        } elseif ($fp = @fopen("api/$classname.php", 'r', true)) {
            include_once("api/$classname.php");
            fclose($fp);
            return true;
        } elseif ($fp = @fopen("client/$classname.php", 'r', true)) {
            include_once("client/$classname.php");
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

    /**
     * Prints debug info into the 'console' log
     */
    function console() {
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
    function write_log($name, $line) {
        if (!is_string($line)) {
            $line = var_export($line, true);
        }

        $log_dir = dirname(__FILE__) . '/../logs';
        $logfile = $log_dir . '/' . $name;
        $date    = date('d-M-Y H:i:s O');
        $line    = sprintf("[%s](%s): %s\n", $date, session_id(), $line);

        if ($fp = @fopen($logfile, 'a')) {
            fwrite($fp, $line);
            fflush($fp);
            fclose($fp);
        }
    }


?>

<?php
/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab Web Admin Panel                           |
 |                                                                          |
 | Copyright (C) 2011-2012, Kolab Systems AG                                |
 |                                                                          |
 | This program is free software: you can redistribute it and/or modify     |
 | it under the terms of the GNU Affero General Public License as published |
 | by the Free Software Foundation, either version 3 of the License, or     |
 | (at your option) any later version.                                      |
 |                                                                          |
 | This program is distributed in the hope that it will be useful,          |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the             |
 | GNU Affero General Public License for more details.                      |
 |                                                                          |
 | You should have received a copy of the GNU Affero General Public License |
 | along with this program. If not, see <http://www.gnu.org/licenses/>      |
 +--------------------------------------------------------------------------+
 | Author: Aleksander Machniak <machniak@kolabsys.com>                      |
 +--------------------------------------------------------------------------+
*/

/**
 * Class for logging debug/info/warning/error messages into log file(s)
 */
class Log
{
    const DEBUG   = 8;
    const INFO    = 4;
    const WARNING = 2;
    const ERROR   = 0;

    private static $mode;


    /**
     * Logs debug message
     *
     * @param string $message Log message
     * @param array  $args    Additional arguments ('file', 'line')
     */
    static function debug($message, $args = array())
    {
        if (self::mode() >= self::DEBUG) {
            self::log_message(self::DEBUG, $message, $args);    
        }
    }

    /**
     * Logs information message
     *
     * @param string $message Log message
     * @param array  $args    Additional arguments ('file', 'line')
     */
    static function info($message, $args = array())
    {
        if (self::mode() >= self::INFO) {
            self::log_message(self::INFO, $message, $args);
        }
    }

    /**
     * Logs warning message
     *
     * @param string $message Log message
     * @param array  $args    Additional arguments ('file', 'line')
     */
    static function warning($message, $args = array())
    {
        if (self::mode() >= self::WARNING) {
            self::log_message(self::WARNING, $message, $args);
        }
    }
    
    /**
     * Logs error message
     *
     * @param string $message Log message
     * @param array  $args    Additional arguments ('file', 'line')
     */
    static function error($message, $args = array())
    {
        if (self::mode() >= self::ERROR) {
            self::log_message(self::ERROR, $message, $args);
        }
    }

    /**
     * Message logger
     *
     * @param int    $mode    Message severity
     * @param string $message Log message
     * @param array  $args    Additional arguments ('file', 'line')
     */
    private static function log_message($mode, $message, $args = array())
    {
        $conf    = Conf::get_instance();
        $logfile = $conf->get('kolab_wap', 'log_file');

        // if log_file is configured all logs will go to it
        // otherwise use separate file for info/debug and warning/error
        if (!$logfile) {
            switch ($mode) {
            case self::DEBUG:
            case self::INFO:
                $file = 'console';
                break;
            case self::WARNING:
            case self::ERROR:
                $file = 'errors';
                break;
            }

            $logfile = dirname(__FILE__) . '/../logs/' . $file;
        }

        switch ($mode) {
        case self::DEBUG:
            $prefix = 'DEBUG';
            break;
        case self::INFO:
            $prefix = 'INFO';
            break;
        case self::WARNING:
            $prefix = 'WARNING';
            break;
        case self::ERROR:
            $prefix = 'ERROR';
            break;
        }

        if (!is_string($message)) {
            $message = var_export($message, true);
        }

        $date    = date('d-M-Y H:i:s O');
        $sess_id = session_id();
        $logline = sprintf("[%s]%s: [%s] %s\n",
            $date, $sess_id ? "($sess_id)" : '', $prefix, $message);

        if (!empty($args)) {
            if ($args['file']) {
                $logline .= ' in ' . $args['file'];
            }
            if ($args['line']) {
                $logline .= ' on line ' . intval($args['line']);
            }
        }

        if ($fp = @fopen($logfile, 'a')) {
            fwrite($fp, $logline);
            fflush($fp);
            fclose($fp);
            return;
        }

        if ($mode == self::ERROR) {
            // send error to PHPs error handler if write to file didn't succeed
            trigger_error($message, E_USER_ERROR);
        }
    }

    /**
     * Returns configured logging mode
     *
     * @return int Logging mode
     */
    static function mode()
    {
        if (isset(self::$mode)) {
            return self::$mode;
        }

        $conf = Conf::get_instance();
        $mode = $conf->get('kolab_wap', 'debug_mode');

        switch ($mode) {
        case self::DEBUG:
        case 'debug':
        case 'DEBUG':
            self::$mode = self::DEBUG;
            break;

        case self::INFO:
        case 'info':
        case 'INFO':
            self::$mode = self::INFO;
            break;

        case self::WARNING:
        case 'warning':
        case 'WARNING':
            self::$mode = self::WARNING;
            break;
        
        case self::ERROR:
        default:
            self::$mode = self::ERROR;
        }

        return self::$mode;
    }
}

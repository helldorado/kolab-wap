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
 | Author: Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>                     |
 +--------------------------------------------------------------------------+
*/

class kolab_utils
{
    const REQUEST_ANY  = 0;
    const REQUEST_GET  = 1;
    const REQUEST_POST = 2;

    /**
     * Read a specific HTTP request header
     *
     * @param string $name  Header name
     *
     * @return mixed  Header value or null if not available
     */
    public static function get_request_header($name)
    {
        if (function_exists('getallheaders')) {
            $hdrs = array_change_key_case(getallheaders(), CASE_UPPER);
            $key  = strtoupper($name);
        }
        else {
            $key  = 'HTTP_' . strtoupper(strtr($name, '-', '_'));
            $hdrs = array_change_key_case($_SERVER, CASE_UPPER);
        }

        return $hdrs[$key];
    }

    /**
     * Returns input parameter value.
     *
     * @param string $name       Parameter name
     * @param int    $type       Parameter type
     * @param bool   $allow_html Enable to strip invalid/unsecure content
     *
     * @return mixed Input value
     */
    public static function get_input($name, $type = null, $allow_html = false)
    {
        if ($type == self::REQUEST_GET) {
            $value = isset($_GET[$name]) ? $_GET[$name] : null;
        }
        else if ($type == self::REQUEST_POST) {
            $value = isset($_POST[$name]) ? $_POST[$name] : null;
        }
        else {
            $value = isset($_REQUEST[$name]) ? $_REQUEST[$name] : null;
        }

        return self::parse_input($value, $allow_html);
    }

    /**
     * Input parsing.
     *
     * @param mixed  $value      Input value
     * @param bool   $allow_html Enable to strip invalid/unsecure content
     *
     * @return mixed Input value
     */
    public static function parse_input($value, $allow_html = false)
    {
        if (empty($value)) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $idx => $val) {
                $value[$idx] = self::parse_input($val, $allow_html);
            }
        }
        // remove HTML tags if not allowed
        else if (!$allow_html) {
            $value = strip_tags($value);
        }

        return $value;
    }

    /**
     * Make sure the string ends with a slash
     */
    public static function slashify($str)
    {
        return self::unslashify($str).'/';
    }

    /**
     * Remove slash at the end of the string
     */
    public static function unslashify($str)
    {
        return preg_replace('/\/$/', '', $str);
    }

}

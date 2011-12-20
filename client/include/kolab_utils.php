<?php

class kolab_utils
{


    /**
     * Read a specific HTTP request header
     *
     * @param string $name  Header name
     *
     * @return mixed  Header value or null if not available
     */
    public static function request_header($name)
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


    public static function get_input($name, $type = null, $allow_html = false)
    {
        if ($type == 'GET') {
            $value = isset($_GET[$name]) ? $_GET[$name] : null;
        }
        else if ($type == 'POST') {
            $value = isset($_POST[$name]) ? $_POST[$name] : null;
        }
        else {
            $value = isset($_REQUEST[$name]) ? $_REQUEST[$name] : null;
        }

        return self::parse_input($value, $allow_html);
    }

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
}

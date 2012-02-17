<?php

class Conf {
    static private $instance;

    const CONFIG_FILE = '/etc/kolab/kolab.conf';

    /**
     * This implements the 'singleton' design pattern
     *
     * @return Conf The one and only instance
     */
    static function get_instance()
    {
        if (!self::$instance) {
            self::$instance = new Conf();
        }

        return self::$instance;
    }

    public function __construct()
    {
        // Do some magic configuration loading here.
        if (!file_exists(self::CONFIG_FILE)) {
            return;
        }

        $_ini_raw = file(self::CONFIG_FILE);

        $this->_conf = array();

        foreach ($_ini_raw as $_line) {
            if (preg_match('/^\[([a-z0-9-_\.]+)\]/', $_line, $matches)) {
                $_cur_section = $matches[1];
                $this->_conf[$_cur_section] = array();
                unset($_cur_key);
            }

            if (preg_match('/^;/', $_line, $matches)) {
            }

            if (preg_match('/^([a-z0-9\.-_]+)\s*=\s*(.*)/', $_line, $matches)) {
                if (isset($_cur_section) && !empty($_cur_section)) {
                    $_cur_key = $matches[1];
                    $this->_conf[$_cur_section][$matches[1]] = isset($matches[2]) ? $matches[2] : '';
                }
            }

            if (preg_match('/^\s+(.*)$/', $_line, $matches)) {
                if (isset($_cur_key) && !empty($_cur_key)) {
                    $this->_conf[$_cur_section][$_cur_key] .= $matches[1];
                }
            }
        }
    }

    public function get($key1, $key2 = NULL)
    {
        return $this->expand($this->get_raw($key1, $key2));
    }

    public function get_raw($key1, $key2 = NULL)
    {
        if (isset($this->_conf[$key1])) {
            if ($key2) {
                if (isset($this->_conf[$key1][$key2])) {
                    return $this->_conf[$key1][$key2];
                }
                else if (isset($this->_conf['kolab'][$key2])) {
                    return $this->_conf['kolab'][$key2];
                }
            }
            else {
                return $this->_conf[$key1];
            }
        }

        // Simple (global) settings may be obtained by calling the key and omitting
        // the section. This goes for sections 'kolab', and whatever is the equivalent
        // of 'kolab', 'auth_mechanism'.
//        echo "<pre>";
//        print_r($this->_conf);
//        echo "</pre>";

        if (isset($this->_conf['kolab'][$key1])) {
            return $this->_conf['kolab'][$key1];
        }
        else if (isset($this->_conf[$this->_conf['kolab']['auth_mechanism']][$key1])) {
            return $this->_conf[$this->_conf['kolab']['auth_mechanism']][$key1];
        }
    }

    public function expand($str, $custom = FALSE)
    {
        if (preg_match_all('/%\((?P<variable>\w+)\)s/', $str, $matches)) {
            if (isset($matches['variable']) && !empty($matches['variable'])) {
                if (is_array($matches['variable'])) {
                    foreach ($matches['variable'] as $key => $value) {
                        if (is_array($custom) && array_key_exists($value, $custom)) {
                            $str = str_replace("%(" . $value . ")s", $custom[$value], $str);
                        }

                        $str = str_replace("%(" . $value . ")s", $this->get($value), $str);
                    }

                    return $str;
                }
                else {
                    return str_replace("%(" . $matches['variable'] . ")s", $this->get($matches['variable']), $str);
                }
            }

            return $str;
        }
        else {
            return $str;
        }
    }
}

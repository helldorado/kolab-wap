<?php

    class Conf {
        static private $instance;

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
            if (file_exists('/etc/kolab/kolab.conf'))
            {
                $_ini_raw = file('/etc/kolab/kolab.conf');

                $this->_conf = Array();

                foreach ($_ini_raw as $_line) {
                    if (preg_match('/^\[([a-z0-9-_\.]+)\]/', $_line, $matches)) {
                        $_cur_section = $matches[1];
                        $this->_conf[$_cur_section] = Array();
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
        }

        public function get($key1, $key2 = NULL) {
            if (isset($this->_conf[$key1])) {
                if ($key2) {
                    if (isset($this->_conf[$key1][$key2])) {
                        return $this->_conf[$key1][$key2];
                    } elseif (isset($this->_conf['kolab'][$key2])) {
                        return $this->_conf['kolab'][$key2];
                    }
                } else {
                    return $this->_conf[$key1];
                }
            }

            // Simple (global) settings may be obtained by calling the key and omitting
            // the section. This goes for sections 'kolab', and whatever is the equivalent
            // of 'kolab', 'auth_mechanism'.
#            echo "<pre>";
#            print_r($this->_conf);
#            echo "</pre>";

            if (isset($this->_conf['kolab'][$key1])) {
                return $this->_conf['kolab'][$key1];
            } elseif (isset($this->_conf[$this->_conf['kolab']['auth_mechanism']][$key1])) {
                return $this->_conf[$this->_conf['kolab']['auth_mechanism']][$key1];
            }

        }

    }

?>

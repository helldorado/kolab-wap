<?php
    require_once('Conf.php');

    class Auth {
        static private $instance = Array();

        private $_auth = Array();
        private $conf;
        private $domains = Array();

        static function get_instance($domain = NULL)
        {
            $conf = Conf::get_instance();

            if ($domain === NULL) {
                $domain = $conf->get('primary_domain');
            }

            if (!isset(self::$instance[$domain])) {
                self::$instance[$domain] = new Auth($domain);
            }

            return self::$instance[$domain];
        }

        public function __construct($domain = NULL) {
            if (!$this->conf)
                $this->conf = Conf::get_instance();

            if ($domain === NULL) {
                $domain = $conf->get('primary_domain');
            }

            $this->conf = Conf::get_instance();
            $this->domain = $domain;

            $this->connect($domain);
        }

        public function authenticate($username, $password) {
            error_log("Authentication request for $username");
            if (strpos($username, '@')) {
                $user_domain = explode('@', $username);
                $user_domain = $user_domain[1];

                if (isset($this->_auth[$user_domain])) {
                    $domain = $user_domain;
                } else {
                    $associated_domain = $this->primary_for_valid_domain($user_domain);
                    if ($associated_domain) {
                        $domain = $user_domain;
                    } else {
                        $domain = FALSE;
                    }
                }
            } else {
                $domain = $this->conf->get('primary_domain');
            }

            if ($this->domain == $domain) {
                error_log("using the current $domain auth thingy");
                $result = $this->_auth[$domain]->authenticate($username, $password);
            } else {
                error_log("creating a new $domain auth thingy");
                $result = Auth::get_instance($domain)->authenticate($username, $password);
            }
            return $result;
        }

        public function connect($domain = NULL) {
            $auth_method = strtoupper($this->conf->get('kolab', 'auth_mechanism'));

            if ($domain === NULL) {
                $domain = $this->conf->get('primary_domain');
            }

            if (!isset($this->_auth[$domain])) {
                require_once('Auth/' . $auth_method . '.php');
                $this->_auth[$domain] = new $auth_method($domain);
            }
            
        }

        public function list_domains() {
            $this->connect();
            return $this->_auth[$this->domain]->list_domains();
        }

        public function list_users($domain = NULL) {
            $this->connect($domain);
            if ($domain === NULL) {
                $domain = $this->conf->get('primary_domain');
            }

            $users = $this->_auth[$domain]->list_users();

            return $users;
        }

        public function normalize_result($results) {
            return LDAP::normalize_result($results);
        }

        public function primary_for_valid_domain($domain) {
            $this->domains = $this->list_domains();

            if (array_key_exists($domain, $this->domains)) {
                return $domain;
            } elseif (in_array($domain, $this->domains)) {
                // We know it's not a key!
                foreach ($this->domains as $parent_domain => $child_domains) {
                    if (in_array($domain, $child_domains)) {
                        return $parent_domain;
                    }
                }
                return FALSE;
            } else {
                return FALSE;
            }
        }

        public function user_add($attributes, $type=NULL) {
            return $this->_auth[$_SESSION['user']->get_domain()]->user_add($attributes, $type);
        }
    }
?>

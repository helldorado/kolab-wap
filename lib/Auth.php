<?php
    class Auth {
        static private $instance = Array();

        private $_auth = Array();
        private $conf;
        private $domains = Array();


        /**
         * Return an instance of Auth, associated with $domain.
         *
         * If $domain is not specified, the 'kolab' 'primary_domain' is used.
         */
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

        /**
         * Authenticate $username with $password.
         *
         * The following forms for a username exist:
         *
         *  - "cn=Directory Manager"
         *
         *      This is considered a DN, as it succeeds to parse as such. The
         *      very name of this user may have already caused you to suspect
         *      that the user is not associated with one domain per-se. It is
         *      our intention this user does therefor not have a
         *      $_SESSION['user']->domain, but instead a
         *      $_SESSION['user']->working_domain. In any case, obtain the
         *      current domain for any user through
         *      $_SESSION['user']->get_domain().
         *
         *      NOTE/TODO: For now, even cn=Directory Manager is set to the
         *      default domain. I wish there was more time...
         *
         *  - "user@domain.tld"
         *
         *      While it may seem obvious, this user is to be authenticated
         *      against the 'domain.tld' realm.
         *
         *  - "user"
         *
         *      This user is to be authenticated against the 'kolab'
         *      'primary_domain'.
         */
        public function authenticate($username, $password) {
            // TODO: Log authentication request.
//             error_log("Authentication request for $username");

            if (strpos($username, '@')) {
                // Case-sensitivity does not matter for strstr() on '@', which
                // has no case.
                $user_domain = strstr($username, '@');

                if (isset($this->_auth[$user_domain])) {
                    // We know this domain
                    $domain = $user_domain;
                } else {
                    // Attempt to find the primary domain name space for the
                    // domain used in the authentication request.
                    //
                    // This will enable john@example.org to login using 'alias'
                    // domains as well, such as 'john@example.ch'.
                    $associated_domain = $this->primary_for_valid_domain($user_domain);

                    if ($associated_domain) {
                        $domain = $user_domain;
                    } else {
                        // It seems we do not know about this domain.
                        $domain = FALSE;
                    }
                }
            } else {
                $domain = $this->conf->get('primary_domain');
            }

            // TODO: Debug logging for the use of a current or the creation of
            // a new authentication class instance.
            if ($this->domain == $domain) {
                $result = $this->_auth[$domain]->authenticate($username, $password);
            } else {
                $result = Auth::get_instance($domain)->authenticate($username, $password);
            }

            return $result;
        }

        public function connect($domain = NULL) {
            if ($domain === NULL) {
                $domain = $this->conf->get('primary_domain');
            }

            $auth_method = strtoupper($this->conf->get($domain, 'auth_mechanism'));

            if (!$auth_method) {
                // Use the default authentication technology
                $auth_method = strtoupper($this->conf->get('kolab', 'auth_mechanism'));
            }

            if (!isset($this->_auth[$domain])) {
                require_once('Auth/' . $auth_method . '.php');
                $this->_auth[$domain] = new $auth_method($domain);
            }
        }

        public function list_domains() {
            // TODO: Consider a normal user does not have privileges on
            // the base_dn where domain names and configuration is stored.
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

        public function user_delete($userdata) {
            return $this->_auth[$_SESSION['user']->get_domain()]->user_delete($userdata);
        }

        public function user_find_by_attribute($userdata) {
            return $this->_auth[$_SESSION['user']->get_domain()]->user_find_by_attribute($userdata);
        }

        public function user_info($userdata) {
            return $this->normalize_result($this->_auth[$_SESSION['user']->get_domain()]->user_info($userdata));
        }
    }
?>

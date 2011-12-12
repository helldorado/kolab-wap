<?php
//     @require_once($_SERVER["DOCUMENT_ROOT"] . "../bin/lib/User/Type.php");
//     @require_once($_SERVER["DOCUMENT_ROOT"] . "../bin/lib/User/LDAP.php");
//     @require_once($_SERVER["DOCUMENT_ROOT"] . "../bin/lib/User/SQL.php");

    require_once('Auth.php');

    class User
    {

        private $_authenticated = FALSE;

        private $auth;

        private $username = NULL;
        private $password = NULL;

        private $_groups = FALSE;

        private $domain;
        private $working_domain;

        public function get_username()
        {
            // Who's asking?
            return $this->username;
        }

        public function _get_information()
        {
            // Return an array of information about this user. For one, the auth method.
            $user['information'] = Array(
                    'email_address' => $this->_auth_method->_get_email_address(),
                    'username' => $this->username,
                    'password' => $this->password,
                );
        }

        public function authenticate($username, $password, $method = FALSE)
        {
            $this->auth = Auth::get_instance();

            $result = $this->auth->authenticate($username, $password);

            if ($result) {
                $this->_authenticated = TRUE;
                $this->username = $username;
                $this->password = $password;
                $this->domain = $this->auth->domain;
#                $this->_groups = $this->groups();
            }

            return $this->_authenticated;
        }

        public function authenticated() {
            return $this->_authenticated;
        }

        public function get_domain() {
            if ($this->working_domain) {
                return $this->working_domain;
            } elseif ($this->domain) {
                return $this->domain;
            } else {
                throw new Exception("No domain selected to work on", 1024);
            }
        }

        public function groups() {
            #error_log("Called " . __FUNCTION__ . " on line " . __LINE__ . " of " . __FILE__);
            #debug_print_backtrace();

            if ($this->_groups || (is_array($this->_groups) && count($this->_groups) >= 1))
                return $this->_groups;

            $this->_groups = Array();
            $this->auth = Auth::get_instance();

            $entry = $this->auth->user_find_by_attribute(Array('mail' => $this->username));

            if ($entry) {
                foreach ($entry as $dn => $attributes) {
                    if (array_key_exists('memberof', $attributes)) {
                        $this->_groups = (array)($attributes['memberof']);
                    } else {
                        $this->_groups = $this->auth->find_user_groups($dn);
                    }
                }
            } else {
                $this->_groups = Array();
            }

            return $this->_groups;
        }

        public function reset_domain() {
            // Validate domain
            // Validate access to domain
            // Set $this->working_domain
            $this->working_domain = $this->domain;
            return TRUE;
        }

        public function set_domain($domain) {
            // Validate domain
            // Validate access to domain
            // Set $this->working_domain
            $this->working_domain = $domain;
            return TRUE;
        }

    }

?>


<?php
//     @require_once($_SERVER["DOCUMENT_ROOT"] . "../bin/lib/User/Type.php");
//     @require_once($_SERVER["DOCUMENT_ROOT"] . "../bin/lib/User/LDAP.php");
//     @require_once($_SERVER["DOCUMENT_ROOT"] . "../bin/lib/User/SQL.php");

    require_once('Auth.php');

    class User
    {

        public $_authenticated = FALSE;

        private $username = NULL;
        private $password = NULL;

        private $domain;
        private $working_domain;

        public function _get_username()
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
            $auth = Auth::get_instance();

            $result = $auth->authenticate($username, $password);

            if ($result) {
                $this->_authenticated = TRUE;
                $this->username = $username;
                $this->password = $password;
                $this->domain = $auth->domain;
            }

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

        public function reset_domain() {
            // Validate domain
            // Validate access to domain
            // Set $this->working_domain
            $this->working_domain = $this->domain;
        }

        public function set_domain($domain) {
            // Validate domain
            // Validate access to domain
            // Set $this->working_domain
            $this->working_domain = $domain;
        }

    }

?>


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

        private $bind_dn = NULL;
        private $bind_pw = NULL;

        public $user_bind_dn = NULL;
        public $user_bind_pw = NULL;

        function User($username = NULL, $password = NULL)
        {
            if ( ( $username ) == FALSE || ( $password ) == FALSE )
            {
                $result = FALSE;
            }
            else
            {
                $this->username = $username;
                $this->password = $password;

                $result = $this->authenticate($username, $password);
            }

            return $result;
        }

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

            if ( $result )
            {
                $this->_authenticated = TRUE;
                $this->username = $username;
                $this->password = $password;
            }

            return $this->_authenticated;
        }

    }

?>


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

class User
{
    private $_authenticated = FALSE;
    private $auth;

    private $userid;
    private $username;
    private $password;

    private $_groups = FALSE;

    private $domain;
    private $working_domain;


    public function _get_information()
    {
        // Return an array of information about this user. For one, the auth method.
        $user['information'] = array(
            'email_address' => $this->_auth_method->_get_email_address(),
            'username' => $this->username,
            'password' => $this->password,
            'domain' => $this->get_domain()
        );
    }

    public function authenticate($username, $password, $domain = null, $method = FALSE)
    {
        //console("Running with domain", $domain);

        if (empty($domain)) {
            $this->auth = Auth::get_instance();
        } else {
            $this->auth = Auth::get_instance($domain);
        }

        $result = $this->auth->authenticate($username, $password);

        if ($result) {
            $this->_authenticated = TRUE;
            $this->username = $username;
            $this->password = $password;
            $this->userid   = $result;

            if (empty($domain)) {
                $this->domain   = $this->auth->domain;
            } else {
                $this->domain = $domain;
            }

            //$this->_groups = $this->groups();
        }

        return $this->_authenticated;
    }

    public function authenticated()
    {
        return $this->_authenticated;
    }

    public function get_username()
    {
        // Who's asking?
        return $this->username;
    }

    public function get_userid()
    {
        return $this->userid;
    }

    public function get_domain()
    {
        if ($this->working_domain) {
            return $this->working_domain;
        }
        else if ($this->domain) {
            return $this->domain;
        }
        else {
            throw new Exception("No domain selected to work on", 1024);
        }
    }

    public function groups()
    {
        //console("Called " . __FUNCTION__ . " on line " . __LINE__ . " of " . __FILE__);
        //debug_print_backtrace();

        if ($this->_groups || (is_array($this->_groups) && count($this->_groups) >= 1)) {
            return $this->_groups;
        }

        $this->_groups = array();
        $this->auth = Auth::get_instance();

        $entry = $this->auth->user_find_by_attribute(array('mail' => $this->username));

        if ($entry) {
            foreach ($entry as $dn => $attributes) {
                if (array_key_exists('memberof', $attributes)) {
                    $this->_groups = (array)($attributes['memberof']);
                }
                else {
                    $this->_groups = $this->auth->find_user_groups($dn);
                }
            }
        }
        else {
            $this->_groups = array();
        }

        return $this->_groups;
    }

    public function reset_domain()
    {
        // Validate domain
        // Validate access to domain
        // Set $this->working_domain
        $this->working_domain = $this->domain;
        return TRUE;
    }

    public function set_domain($domain)
    {
        // Validate domain
        // Validate access to domain
        // Set $this->working_domain
        $this->working_domain = $domain;
        return TRUE;
    }

}

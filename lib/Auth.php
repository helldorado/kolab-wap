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

class Auth {
    static private $instance = array();

    private $_auth = array();
    private $conf;
    private $domains = array();


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

    public function __construct($domain = NULL)
    {
        if (!$this->conf) {
            $this->conf = Conf::get_instance();
        }

        if ($domain === NULL) {
            $domain = $this->conf->get('primary_domain');
        }

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
     *
     * @param string $username User name (DN or mail)
     * @param string $password User password
     *
     * @return bool|string User ID or False on failure
     */
    public function authenticate($username, $password)
    {
        // TODO: Log authentication request.
//         error_log("Authentication request for $username");

        if (strpos($username, '@')) {
            // Case-sensitivity does not matter for strstr() on '@', which
            // has no case.
            $user_domain = strstr($username, '@');

            if (isset($this->_auth[$user_domain])) {
                // We know this domain
                $domain = $user_domain;
            }
            else {
                // Attempt to find the primary domain name space for the
                // domain used in the authentication request.
                //
                // This will enable john@example.org to login using 'alias'
                // domains as well, such as 'john@example.ch'.
                $associated_domain = $this->primary_for_valid_domain($user_domain);

                if ($associated_domain) {
                    $domain = $user_domain;
                }
                else {
                    // It seems we do not know about this domain.
                    $domain = FALSE;
                }
            }
        }
        else {
            $domain = $this->conf->get('primary_domain');
        }

        // TODO: Debug logging for the use of a current or the creation of
        // a new authentication class instance.
        if ($this->domain == $domain) {
            $result = $this->_auth[$domain]->authenticate($username, $password);
        }
        else {
            $result = Auth::get_instance($domain)->authenticate($username, $password);
        }

        return $result;
    }

    public function connect($domain = NULL)
    {
        if ($domain === NULL) {
            $domain = $this->conf->get('primary_domain');
        }

        if ($domain) {
            $auth_method = strtoupper($this->conf->get($domain, 'auth_mechanism'));
        }

        if (empty($auth_method)) {
            // Use the default authentication technology
            $auth_method = strtoupper($this->conf->get('kolab', 'auth_mechanism'));
        }

        if (!$auth_method) {
            // Use LDAP by default
            $auth_method = 'LDAP';
        }

        if (!isset($this->_auth[$domain])) {
            require_once 'Auth/' . $auth_method . '.php';
            $this->_auth[$domain] = new $auth_method($domain);
        }
    }

    // TODO: Dummy function to be removed
    public function attr_details($attribute)
    {
        $conf = Conf::get_instance();
        return $this->_auth[$conf->get('kolab', 'primary_domain')]->attribute_details((array)($attribute));
    }

    // TODO: Dummy function to be removed
    public function attrs_allowed($objectclasses = array())
    {
        $conf = Conf::get_instance();
        return $this->_auth[$conf->get('kolab', 'primary_domain')]->allowed_attributes($objectclasses);
    }

    public function allowed_attributes($objectclasses = array())
    {
        if (!is_array($objectclasses)) {
            $objectclasses = (array)($objectclasses);
        }

        return $this->_auth[$_SESSION['user']->get_domain()]->allowed_attributes($objectclasses);
    }

    public function attribute_details($attributes = array())
    {
        if (!is_array($attributes)) {
            $attributes = (array)($attributes);
        }

        return $this->_auth[$_SESSION['user']->get_domain()]->attribute_details($attributes);
    }

    public function find_user_groups($member_dn)
    {
        return $this->_auth[$_SESSION['user']->get_domain()]->find_user_groups($member_dn);
    }

    public function get_attribute($subject, $attribute)
    {
        return $this->_auth[$_SESSION['user']->get_domain()]->get_attribute($subject, $attribute);
    }

    public function get_attributes($subject, $attributes)
    {
        return $this->_auth[$_SESSION['user']->get_domain()]->get_attributes($subject, $attributes);
    }

    public function group_add($attributes, $typeid = null)
    {
        return $this->_auth[$_SESSION['user']->get_domain()]->group_add($attributes, $typeid);
    }

    public function group_edit($group, $attributes, $typeid = null)
    {
        return $this->_auth[$_SESSION['user']->get_domain()]->group_edit($group, $attributes, $typeid);
    }

    public function group_delete($subject)
    {
        return $this->_auth[$_SESSION['user']->get_domain()]->group_delete($subject);
    }

    public function group_find_by_attribute($attribute)
    {
        return $this->_auth[$_SESSION['user']->get_domain()]->group_find_by_attribute($attribute);
    }

    public function group_info($groupdata)
    {
        return $this->_auth[$_SESSION['user']->get_domain()]->group_info($groupdata);
    }

    public function group_members_list($groupdata)
    {
        return $this->_auth[$_SESSION['user']->get_domain()]->group_members_list($groupdata);
    }

    public function list_domains()
    {
        // TODO: Consider a normal user does not have privileges on
        // the base_dn where domain names and configuration is stored.
        return $this->_auth[$this->domain]->list_domains();
    }

    public function list_rights($subject)
    {
        return $this->_auth[$this->domain]->effective_rights($subject);
    }

    public function list_users($domain = NULL, $attributes = array(), $search = array(), $params = array())
    {
        $this->connect($domain);
        if ($domain === NULL) {
            $domain = $this->conf->get('primary_domain');
        }

        $users = $this->_auth[$domain]->list_users($attributes, $search, $params);

        return $users;
    }

    public function list_groups($domain = NULL, $attributes = array(), $search = array(), $params = array())
    {
        $this->connect($domain);
        if ($domain === NULL) {
            $domain = $this->conf->get('primary_domain');
        }

        $groups = $this->_auth[$domain]->list_groups($attributes, $search, $params);

        return $groups;
    }

    public function list_roles($domain = NULL, $attributes = array(), $search = array(), $params = array())
    {
        $this->connect($domain);
        if ($domain === NULL) {
            $domain = $this->conf->get('primary_domain');
        }

        $roles = $this->_auth[$domain]->list_roles($attributes, $search, $params);

        return $roles;
    }

    public function primary_for_valid_domain($domain)
    {
        $this->domains = $this->list_domains();

        if (array_key_exists($domain, $this->domains)) {
            return $domain;
        }
        else if (in_array($domain, $this->domains)) {
            // We know it's not a key!
            foreach ($this->domains as $parent_domain => $child_domains) {
                if (in_array($domain, $child_domains)) {
                    return $parent_domain;
                }
            }

            return FALSE;
        }
        else {
            return FALSE;
        }
    }

    public function user_add($attributes, $typeid = null)
    {
        return $this->_auth[$_SESSION['user']->get_domain()]->user_add($attributes, $typeid);
    }

    public function user_edit($user, $attributes, $typeid = null)
    {
        return $this->_auth[$_SESSION['user']->get_domain()]->user_edit($user, $attributes, $typeid);
    }

    public function user_delete($userdata)
    {
        return $this->_auth[$_SESSION['user']->get_domain()]->user_delete($userdata);
    }

    public function user_find_by_attribute($attribute)
    {
        return $this->_auth[$_SESSION['user']->get_domain()]->user_find_by_attribute($attribute);
    }

    public function user_info($userdata)
    {
        return $this->_auth[$_SESSION['user']->get_domain()]->user_info($userdata);
    }
}

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
        Log::trace("Auth::get_instance(\$domain = " . var_export($domain, TRUE) . ")");

        $conf = Conf::get_instance();

        if (empty($domain)) {
            if (!empty($_SESSION['user'])) {
                $domain = $_SESSION['user']->get_domain();
            } else {
                $domain = $conf->get('primary_domain');
            }
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
    public function authenticate($username, $password, $domain = null)
    {
        Log::info("Authentication request for $username against " . $this->domain);

        if ($domain == NULL) {
            $domain = $this->domain;
        }

        // TODO: Debug logging for the use of a current or the creation of
        // a new authentication class instance.
        $result = $this->_auth[$this->domain]->authenticate($username, $password, $domain);

        return $result;
    }

    public function connect($domain = NULL)
    {
        if (empty($domain)) {
            if (!empty($_SESSION['user'])) {
                $domain = $_SESSION['user']->get_domain();
                Log::trace("Using domain from session: $domain");
            } else {
                $domain = $this->conf->get('primary_domain');
                Log::trace("Using primary_domain: " . $domain);
            }
            Log::trace("Domain to connect to not specified, connecting to $domain");
        } else {
            Log::trace("Domain to connect to set to $domain");
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
            Log::trace("Creating $auth_method for domain $domain");
            require_once 'Auth/' . $auth_method . '.php';
            $this->_auth[$domain] = new $auth_method($domain);
        } else {
            Log::trace("Auth for $domain already available");
        }
    }

    /**
     * Return Auth instance for specified domain
     */
    private function auth_instance($domain = null)
    {
        if (empty($domain)) {
            if (!empty($_SESSION['user'])) {
                $domain = $_SESSION['user']->get_domain();
                Log::trace("Using domain from session: " . $domain);
            } else {
                $domain = $this->conf->get('primary_domain');
                Log::trace("Using primary_domain: " . $domain);
            }
        }

        if (!isset($this->_auth[$domain])) {
            $this->connect($domain);
        }

        return $this->_auth[$domain];
    }

    // TODO: Dummy function to be removed
    public function attr_details($attribute)
    {
        $conf   = Conf::get_instance();
        $domain = $conf->get('kolab', 'primary_domain');

        return $this->auth_instance($domain)->attribute_details((array)$attribute);
    }

    // TODO: Dummy function to be removed
    public function attrs_allowed($objectclasses = array())
    {
        $conf   = Conf::get_instance();
        $domain = $conf->get('kolab', 'primary_domain');

        return $this->auth_instance($domain)->allowed_attributes($objectclasses);
    }

    public function allowed_attributes($objectclasses = array())
    {
        return $this->auth_instance()->allowed_attributes((array)$objectclasses);
    }

    public function attribute_details($attributes = array())
    {
        return $this->auth_instance()->attribute_details((array)$attributes);
    }

    public function domain_add($domain, $domain_attrs)
    {
        return $this->auth_instance()->domain_add($domain, $domain_attrs);
    }

    public function domain_edit($domain, $attributes, $typeid = null)
    {
        return $this->auth_instance()->domain_edit($domain, $attributes, $typeid);
    }

    public function domain_delete($domain)
    {
        return $this->auth_instance()->domain_delete($domain);
    }

    public function domain_find_by_attribute($attribute)
    {
        return $this->auth_instance()->domain_find_by_attribute($attribute);
    }

    public function domain_info($domaindata)
    {
        return $this->auth_instance()->domain_info($domaindata);
    }

    public function find_recipient($address)
    {
        return $this->auth_instance()->find_recipient($address);
    }

    public function find_user_groups($member_dn)
    {
        return $this->auth_instance()->find_user_groups($member_dn);
    }

    public function get_entry_attribute($subject, $attribute)
    {
        $entry = $this->auth_instance()->get_attributes($subject, (array)$attribute);
        return $entry[$attribute];
    }

    public function get_entry_attributes($subject, $attributes)
    {
        return $this->auth_instance()->get_attributes($subject, $attributes);
    }

    public function group_add($attributes, $typeid = null)
    {
        return $this->auth_instance()->group_add($attributes, $typeid);
    }

    public function group_edit($group, $attributes, $typeid = null)
    {
        return $this->auth_instance()->group_edit($group, $attributes, $typeid);
    }

    public function group_delete($subject)
    {
        return $this->auth_instance()->group_delete($subject);
    }

    public function group_find_by_attribute($attribute)
    {
        return $this->auth_instance()->group_find_by_attribute($attribute);
    }

    public function group_info($groupdata)
    {
        return $this->auth_instance()->group_info($groupdata);
    }

    public function group_members_list($groupdata, $recurse = true)
    {
        return $this->auth_instance()->group_members_list($groupdata, $recurse);
    }

    public function list_domains($attributes = array(), $search = array(), $params = array())
    {
        return $this->auth_instance()->list_domains($attributes, $search, $params);
    }

    public function list_rights($subject)
    {
        return $this->auth_instance()->effective_rights($subject);
    }

    public function list_users($domain = NULL, $attributes = array(), $search = array(), $params = array())
    {
        return $this->auth_instance()->list_users($attributes, $search, $params);
    }

    public function list_groups($domain = NULL, $attributes = array(), $search = array(), $params = array())
    {
        return $this->auth_instance($domain)->list_groups($attributes, $search, $params);
    }

    public function list_resources($domain = NULL, $attributes = array(), $search = array(), $params = array())
    {
        return $this->auth_instance($domain)->list_resources($attributes, $search, $params);
    }

    public function list_roles($domain = NULL, $attributes = array(), $search = array(), $params = array())
    {
        return $this->auth_instance($domain)->list_roles($attributes, $search, $params);
    }

    public function list_sharedfolders($domain = NULL, $attributes = array(), $search = array(), $params = array())
    {
        return $this->auth_instance($domain)->list_sharedfolders($attributes, $search, $params);
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

    public function resource_add($attributes, $typeid = null)
    {
        return $this->auth_instance()->resource_add($attributes, $typeid);
    }

    public function resource_edit($resource, $attributes, $typeid = null)
    {
        return $this->auth_instance()->resource_edit($resource, $attributes, $typeid);
    }

    public function resource_delete($subject)
    {
        return $this->auth_instance()->resource_delete($subject);
    }

    public function resource_find_by_attribute($attribute)
    {
        return $this->auth_instance()->resource_find_by_attribute($attribute);
    }

    public function resource_info($resourcedata)
    {
        return $this->auth_instance()->resource_info($resourcedata);
    }

    public function resource_members_list($resourcedata, $recurse = true)
    {
        return $this->auth_instance()->resource_members_list($resourcedata, $recurse);
    }

    public function role_add($role)
    {
        return $this->auth_instance()->role_add($role);
    }

    public function role_edit($role, $attributes, $typeid = null)
    {
        return $this->auth_instance()->role_edit($role, $attributes, $typeid);
    }

    public function role_delete($role)
    {
        return $this->auth_instance()->role_delete($role);
    }

    public function role_find_by_attribute($attribute)
    {
        return $this->auth_instance()->role_find_by_attribute($attribute);
    }

    public function role_info($roledata)
    {
        return $this->auth_instance()->role_info($roledata);
    }

    public function sharedfolder_add($attributes, $typeid = null)
    {
        return $this->auth_instance()->sharedfolder_add($attributes, $typeid);
    }

    public function sharedfolder_edit($sharedfolder, $attributes, $typeid = null)
    {
        return $this->auth_instance()->sharedfolder_edit($sharedfolder, $attributes, $typeid);
    }

    public function sharedfolder_delete($subject)
    {
        return $this->auth_instance()->sharedfolder_delete($subject);
    }

    public function sharedfolder_find_by_attribute($attribute)
    {
        return $this->auth_instance()->sharedfolder_find_by_attribute($attribute);
    }

    public function sharedfolder_info($sharedfolderdata)
    {
        return $this->auth_instance()->sharedfolder_info($sharedfolderdata);
    }

    public function search()
    {
        return call_user_func_array(Array($this->auth_instance(), 'search'), func_get_args());
    }

    public function subject_base_dn($key, $type)
    {
        // first try strict match
        $base_dn = $this->auth_instance()->subject_base_dn($key . '_' . $type, true);

        if (!$base_dn) {
            $base_dn = $this->auth_instance()->subject_base_dn($type);
        }

        return $base_dn;
    }

    public function user_add($attributes, $typeid = null)
    {
        return $this->auth_instance()->user_add($attributes, $typeid);
    }

    public function user_edit($user, $attributes, $typeid = null)
    {
        return $this->auth_instance()->user_edit($user, $attributes, $typeid);
    }

    public function user_delete($userdata)
    {
        return $this->auth_instance()->user_delete($userdata);
    }

    public function user_find_by_attribute($attribute)
    {
        return $this->auth_instance()->user_find_by_attribute($attribute);
    }

    public function user_info($userdata)
    {
        return $this->auth_instance()->user_info($userdata);
    }

    public function schema_attributes($object_classes)
    {
        return $this->auth_instance()->attributes_allowed($object_classes);
    }

    public function schema_classes()
    {
        return $this->auth_instance()->classes_allowed();
    }
}

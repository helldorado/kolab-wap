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

require_once "Net/LDAP3.php";

/**
 * Kolab LDAP handling abstraction class.
 */
class LDAP extends Net_LDAP3 {
    private $conf;

    /**
     * Class constructor
     */
    public function __construct($domain = null)
    {
        parent::__construct();

        $this->conf = Conf::get_instance();

        // Causes nesting levels to be too deep...?
        //$this->config_set('config_get_hook', array($this, "_config_get"));

        $this->config_set("debug", true);
        $this->config_set("log_hook", array($this, "_log"));

        //$this->config_set("vlv", false);
        $this->config_set("config_root_dn", "cn=config");

        $this->config_set("service_bind_dn", $this->conf->get("service_bind_dn"));
        $this->config_set("service_bind_pw", $this->conf->get("service_bind_pw"));

        // See if we are to connect to any domain explicitly defined.
        if (empty($domain)) {
            // If not, attempt to get the domain from the session.
            if (isset($_SESSION['user'])) {
                try {
                    $domain = $_SESSION['user']->get_domain();
                } catch (Exception $e) {
                    Log::warning("LDAP: User not authenticated yet");
                }
            }
        } else {
            Log::debug("LDAP: __construct() using domain $domain");
        }

        // Continue and default to the primary domain.
        $this->domain       = $domain ? $domain : $this->conf->get('primary_domain');

        $this->_ldap_uri    = $this->conf->get('ldap_uri');
        $this->_ldap_server = parse_url($this->_ldap_uri, PHP_URL_HOST);
        $this->_ldap_port   = parse_url($this->_ldap_uri, PHP_URL_PORT);
        $this->_ldap_scheme = parse_url($this->_ldap_uri, PHP_URL_SCHEME);

        // Catch cases in which the ldap server port has not been explicitely defined
        if (!$this->_ldap_port) {
            if ($this->_ldap_scheme == "ldaps") {
                $this->_ldap_port = 636;
            }
            else {
                $this->_ldap_port = 389;
            }
        }

        $this->config_set("host", $this->_ldap_server);
        $this->config_set("port", $this->_ldap_port);

        parent::connect();

        // Attempt to get the root dn from the configuration file.
        $root_dn = $this->conf->get($this->domain, "base_dn");
        if (empty($root_dn)) {
            // Fall back to a root dn from LDAP, or the standard root dn
            $root_dn = $this->domain_root_dn($this->domain);
        }

        $this->config_set("root_dn", $root_dn);
    }

    /**********************************************************
     ***********          Public methods           ************
     **********************************************************/

    /**
     * Authentication
     *
     * @param string $username User name (DN or mail)
     * @param string $password User password
     *
     * @return bool|string User ID or False on failure
     */
    public function authenticate($username, $password, $domain = NULL)
    {
        Log::debug("Auth::LDAP: authentication request for $username against domain $domain");

        if (!$this->connect()) {
            return false;
        }

        if ($domain == NULL) {
            $domain = $this->domain;
        }

        $result = $this->login($username, $password, $domain);

        if (!$result) {
            return false;
        }

        $_SESSION['user']->user_bind_dn = $result;
        $_SESSION['user']->user_bind_pw = $password;

        return $result;
    }

    public function domain_add($domain, $parent_domain = false, $prepopulate = true)
    {
        // Apply some routines for access control to this function here.
        if (!empty($parent_domain)) {
            $domain_info = $this->domain_info($parent_domain);
            if ($domain_info === false) {
                $this->_domain_add_new($parent_domain, $prepopulate);
            }

            return $this->_domain_add_alias($domain, $parent_domain);
        }
        else {
            return $this->_domain_add_new($domain, $prepopulate);
        }
    }

    public function domain_edit($domain, $attributes, $typeid = null)
    {
        $domain = $this->domain_info($domain, array_keys($attributes));

        if (empty($domain)) {
            return false;
        }

        $domain_dn = key($domain);

        // We should start throwing stuff over the fence here.
        return $this->modify_entry($domain_dn, $domain[$domain_dn], $attributes);
    }

    public function domain_delete($domain)
    {
        $base_dn = $this->conf->get('ldap', 'domain_base_dn');

        return $this->entry_delete($domain, array(), $base_dn);
    }

    public function domain_find_by_attribute($attribute)
    {
        $base_dn = $this->conf->get('ldap', 'domain_base_dn');

        return $this->entry_find_by_attribute($attribute, $base_dn);
    }

    public function domain_info($domain, $attributes = array('*'))
    {
        $this->_log(LOG_DEBUG, "Auth::LDAP::domain_info() for domain " . var_export($domain, true));
        $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        $domain_base_dn = $this->conf->get('ldap', 'domain_base_dn');
        $domain_dn      = $this->entry_dn($domain, array(), $domain_base_dn);

        if (!$domain_dn) {
            $domain_filter         = $this->conf->get('ldap', 'domain_filter');
            $domain_name_attribute = $this->conf->get('ldap', 'domain_name_attribute');
            $domain_filter         = "(&" . $domain_filter . "(" . $domain_name_attribute . "=" . $domain . "))";

            $this->_log(LOG_DEBUG, "Auth::LDAP::domain_info() uses _search()");
            $result = $this->_search($domain_base_dn, $domain_filter, $attributes);
            $result = $result->entries(true);
        }
        else {
            $this->_log(LOG_DEBUG, "Auth::LDAP::domain_info() uses _read()");
            $result = $this->_read($domain_dn, $attributes);
        }

        if (!$result) {
            return false;
        }

        $this->_log(LOG_DEBUG, "Auth::LDAP::domain_info() result: " . var_export($result, true));

        return $result;
    }

    /**
     * Proxy to parent function in order to enable us to insert our
     * configuration.
     */
    public function effective_rights($subject)
    {
        // Ensure we are bound with the user's credentials
        $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        $this->_log(LOG_DEBUG, "Auth::LDAP::effective_rights(\$subject = '" . $subject . "')");

        switch ($subject) {
            case "domain":
                return parent::effective_rights($this->conf->get("ldap", "domain_base_dn"));

            case "group":
            case "resource":
            case "role":
            case "sharedfolder":
            case "user":
                return parent::effective_rights($this->_subject_base_dn($subject));

            default:
                return parent::effective_rights($subject);
        }
    }

    public function find_recipient($address)
    {
        $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        $mail_attrs = $this->conf->get_list('mail_attributes', array('mail', 'alias'));
        $search     = array('operator' => 'OR');

        foreach ($mail_attrs as $num => $attr) {
            $search['params'][$attr] = array(
                'type'  => 'exact',
                'value' => $address,
            );
        }

        $result = $this->search_entries($this->config_get('root_dn'), '(objectclass=*)', 'sub', null, $search);

        if ($result && $result->count() > 0) {
            return $result->entries(TRUE);
        }

        return FALSE;
    }

    public function get_attributes($subject_dn, $attributes)
    {
        $this->_log(LOG_DEBUG, "Auth::LDAP::get_attributes() for $subject_dn");
        $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        return $this->get_entry_attributes($subject_dn, $attributes);
    }

    public function group_add($attrs, $typeid = null)
    {
        $base_dn = $this->entry_base_dn('group', $typeid);

        // TODO: The rdn is configurable as well.
        // Use [$type_str . "_"]user_rdn_attr
        $dn = "cn=" . $attrs['cn'] . "," . $base_dn;

        return $this->entry_add($dn, $attrs);
    }

    public function group_delete($group)
    {
        return $this->entry_delete($group);
    }

    public function group_edit($group, $attributes, $typeid = null)
    {
        $group = $this->group_info($group, array_keys($attributes));

        if (empty($group)) {
            return false;
        }

        $group_dn = key($group);

        // We should start throwing stuff over the fence here.
        return $this->modify_entry($group_dn, $group[$group_dn], $attributes);
    }

    public function group_find_by_attribute($attribute)
    {
        return $this->entry_find_by_attribute($attribute);
    }

    public function group_info($group, $attributes = array('*'))
    {
        $this->_log(LOG_DEBUG, "Auth::LDAP::group_info() for group " . var_export($group, true));
        $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        $group_dn = $this->entry_dn($group);

        if (!$group_dn) {
            return false;
        }

        $this->read_prepare($attributes);

        return $this->_read($group_dn, $attributes);
    }

    public function group_members_list($group, $recurse = true)
    {
        $group_dn = $this->entry_dn($group);

        if (!$group_dn) {
            return false;
        }

        return $this->_list_group_members($group_dn, null, $recurse);
    }

    public function list_domains($attributes = array(), $search = array(), $params = array())
    {
        $this->_log(LOG_DEBUG, "Auth::LDAP::list_domains(" . var_export($attributes, true) . ", " . var_export($search, true) . ", " . var_export($params, true));

        $section = $this->conf->get('kolab', 'auth_mechanism');
        $base_dn = $this->conf->get($section, 'domain_base_dn');
        $filter  = $this->conf->get($section, 'domain_filter');

        $kolab_filter = $this->conf->get($section, 'kolab_domain_filter');
        if (empty($filter) && !empty($kolab_filter)) {
            $filter = $kolab_filter;
        }

        if (!$filter) {
            $filter = "(associateddomain=*)";
        }

        return $this->_list($base_dn, $filter, 'sub', $attributes, $search, $params);
    }

    public function list_groups($attributes = array(), $search = array(), $params = array())
    {
        $this->_log(LOG_DEBUG, "Auth::LDAP::list_groups(" . var_export($attributes, true) . ", " . var_export($search, true) . ", " . var_export($params, true));

        $base_dn = $this->_subject_base_dn('group');
        $filter  = $this->conf->get('group_filter');

        if (!$filter) {
            $filter = "(|(objectclass=groupofuniquenames)(objectclass=groupofurls))";
        }

        return $this->_list($base_dn, $filter, 'sub', $attributes, $search, $params);
    }

    public function list_resources($attributes = array(), $search = array(), $params = array())
    {
        $this->_log(LOG_DEBUG, "Auth::LDAP::list_resources(" . var_export($attributes, true) . ", " . var_export($search, true) . ", " . var_export($params, true));

        $base_dn = $this->_subject_base_dn('resource');
        $filter  = $this->conf->get('resource_filter');

        if (!$filter) {
            $filter = "(&(objectclass=*)(!(objectclass=organizationalunit)))";
        }

        return $this->_list($base_dn, $filter, 'sub', $attributes, $search, $params);
    }

    public function list_roles($attributes = array(), $search = array(), $params = array())
    {
        $this->_log(LOG_DEBUG, "Auth::LDAP::list_roles(" . var_export($attributes, true) . ", " . var_export($search, true) . ", " . var_export($params, true));

        $base_dn = $this->_subject_base_dn('role');
        $filter  = $this->conf->get('role_filter');

        if (empty($filter)) {
            $filter  = "(&(objectclass=ldapsubentry)(objectclass=nsroledefinition))";
        }

        return $this->_list($base_dn, $filter, 'sub', $attributes, $search, $params);
    }

    public function list_sharedfolders($attributes = array(), $search = array(), $params = array())
    {
        $this->_log(LOG_DEBUG, "Auth::LDAP::list_sharedfolders(" . var_export($attributes, true) . ", " . var_export($search, true) . ", " . var_export($params, true));

        $base_dn = $this->_subject_base_dn('sharedfolder');
        $filter  = $this->conf->get('sharedfolder_filter');

        if (!$filter) {
            $filter = "(&(objectclass=*)(!(objectclass=organizationalunit)))";
        }

        return $this->_list($base_dn, $filter, 'sub', $attributes, $search, $params);
    }

    public function list_users($attributes = array(), $search = array(), $params = array())
    {
        $this->_log(LOG_DEBUG, "Auth::LDAP::list_users(" . var_export($attributes, true) . ", " . var_export($search, true) . ", " . var_export($params, true));

        $base_dn = $this->_subject_base_dn('user');
        $filter  = $this->conf->get('user_filter');

        if (empty($filter)) {
            $filter  = "(objectclass=kolabinetorgperson)";
        }

        return $this->_list($base_dn, $filter, 'sub', $attributes, $search, $params);
    }

    public function resource_add($attrs, $typeid = null)
    {
        $base_dn = $this->entry_base_dn('resource', $typeid);

        // TODO: The rdn is configurable as well.
        // Use [$type_str . "_"]user_rdn_attr
        $dn = "cn=" . $attrs['cn'] . "," . $base_dn;

        return $this->entry_add($dn, $attrs);
    }

    public function resource_delete($resource)
    {
        return $this->entry_delete($resource);
    }

    public function resource_edit($resource, $attributes, $typeid = null)
    {
        $resource = $this->resource_info($resource, array_keys($attributes));

        if (empty($resource)) {
            return false;
        }

        $resource_dn = key($resource);

        // We should start throwing stuff over the fence here.
        return $this->modify_entry($resource_dn, $resource[$resource_dn], $attributes);
    }

    public function resource_find_by_attribute($attribute)
    {
        return $this->entry_find_by_attribute($attribute);
    }

    public function resource_info($resource, $attributes = array('*'))
    {
        $this->_log(LOG_DEBUG, "Auth::LDAP::resource_info() for resource " . var_export($resource, true));
        $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        $resource_dn = $this->entry_dn($resource);

        if (!$resource_dn) {
            return false;
        }

        $this->read_prepare($attributes);

        return $this->_read($resource_dn, $attributes);
    }

    public function resource_members_list($resource, $recurse = true)
    {
        $resource_dn = $this->entry_dn($resource);

        if (!$resource_dn) {
            return false;
        }

        return $this->_list_resource_members($resource_dn, null, $recurse);
    }

    public function role_add($attrs)
    {
        $base_dn = $this->entry_base_dn('role', $typeid);

        // TODO: The rdn is configurable as well.
        // Use [$type_str . "_"]user_rdn_attr
        $dn = "cn=" . $attrs['cn'] . "," . $base_dn;

        return $this->entry_add($dn, $attrs);
    }

    public function role_edit($role, $attributes, $typeid = null)
    {
        $role = $this->role_info($role, array_keys($attributes));

        if (empty($role)) {
            return false;
        }

        $role_dn = key($role);

        // We should start throwing stuff over the fence here.
        return $this->modify_entry($role_dn, $role[$role_dn], $attributes);
    }

    public function role_delete($role)
    {
        return $this->entry_delete($role, array('objectclass' => 'ldapsubentry'));
    }

    public function role_find_by_attribute($attribute)
    {
        $attribute['objectclass'] = 'ldapsubentry';
        return $this->entry_find_by_attribute($attribute);
    }

    public function role_info($role, $attributes = array('*'))
    {
        $this->_log(LOG_DEBUG, "Auth::LDAP::role_info() for role " . var_export($role, true));
        $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        $role_dn = $this->entry_dn($role, array('objectclass' => 'ldapsubentry'));

        if (!$role_dn) {
            return false;
        }

        $this->read_prepare($attributes);

        return $this->_read($role_dn, $attributes);
    }

    public function sharedfolder_add($attrs, $typeid = null)
    {
        $base_dn = $this->entry_base_dn('sharedfolder', $typeid);

        // TODO: The rdn is configurable as well.
        // Use [$type_str . "_"]user_rdn_attr
        $dn = "cn=" . $attrs['cn'] . "," . $base_dn;

        return $this->entry_add($dn, $attrs);
    }

    public function sharedfolder_delete($sharedfolder)
    {
        return $this->entry_delete($sharedfolder);
    }

    public function sharedfolder_edit($sharedfolder, $attributes, $typeid = null)
    {
        $sharedfolder = $this->sharedfolder_info($sharedfolder, array_keys($attributes));

        if (empty($sharedfolder)) {
            return false;
        }

        $sharedfolder_dn = key($sharedfolder);

        // We should start throwing stuff over the fence here.
        return $this->modify_entry($sharedfolder_dn, $sharedfolder[$sharedfolder_dn], $attributes);
    }

    public function sharedfolder_find_by_attribute($attribute)
    {
        return $this->entry_find_by_attribute($attribute);
    }

    public function sharedfolder_info($sharedfolder, $attributes = array('*'))
    {
        $this->_log(LOG_DEBUG, "Auth::LDAP::sharedfolder_info() for sharedfolder " . var_export($sharedfolder, true));
        $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        $sharedfolder_dn = $this->entry_dn($sharedfolder);

        if (!$sharedfolder_dn) {
            return false;
        }

        $this->read_prepare($attributes);

        return $this->_read($sharedfolder_dn, $attributes);
    }


    public function search($base_dn, $filter = '(objectclass=*)', $scope = 'sub', $sort = NULL, $search = array())
    {
        if (isset($_SESSION['user']->user_bind_dn) && !empty($_SESSION['user']->user_bind_dn)) {
            $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);
        }

        $this->_log(LOG_DEBUG, "Relaying search to parent:" . var_export(func_get_args(), true));
        return parent::search($base_dn, $filter, $scope, $sort, $search);
    }

    public function subject_base_dn($subject, $strict = false)
    {
        return $this->_subject_base_dn($subject, $strict);
    }

    public function user_add($attrs, $typeid = null)
    {
        $base_dn = $this->entry_base_dn('user', $typeid);

        if (!empty($attrs['ou'])) {
            $base_dn = $attrs['ou'];
        }

        // TODO: The rdn is configurable as well.
        // Use [$type_str . "_"]user_rdn_attr
        $dn = "uid=" . $attrs['uid'] . "," . $base_dn;

        return $this->entry_add($dn, $attrs);
    }

    public function user_edit($user, $attributes, $typeid = null)
    {
        $user = $this->user_info($user, array_keys($attributes));

        if (empty($user)) {
            return false;
        }

        $user_dn = key($user);

        // We should start throwing stuff over the fence here.
        $result = $this->modify_entry($user_dn, $user[$user_dn], $attributes);

        // Handle modification of current user data
        if (!empty($result) && $user_dn == $_SESSION['user']->user_bind_dn) {
            // update session password
            if (!empty($result['replace']) && !empty($result['replace']['userpassword'])) {
                $pass = $result['replace']['userpassword'];
                $_SESSION['user']->user_bind_pw = is_array($pass) ? implode($pass) : $pass;
            }
        }

        return $result;
    }

    public function user_delete($user)
    {
        return $this->entry_delete($user);
    }

    public function user_info($user, $attributes = array('*'))
    {
        $this->_log(LOG_DEBUG, "Auth::LDAP::user_info() for user " . var_export($user, true));
        $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        $user_dn = $this->entry_dn($user);

        if (!$user_dn) {
            return false;
        }

        $this->read_prepare($attributes);

        return $this->_read($user_dn, $attributes);
    }

    public function user_find_by_attribute($attribute)
    {
        return $this->entry_find_by_attribute($attribute);
    }

    /**
     * Wrapper for search_entries()
     */
    protected function _list($base_dn, $filter, $scope, $attributes, $search, $params)
    {
        $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        if (!empty($params['sort_by'])) {
            if (is_array($params['sort_by'])) {
                foreach ($params['sort_by'] as $attrib) {
                    if (!in_array($attrib, $attributes)) {
                        $attributes[] = $attrib;
                    }
                }
            } else {
                if (!in_array($params['sort_by'], $attributes)) {
                    $attributes[] = $params['sort_by'];
                }
            }
        }

        if (!empty($params['page_size'])) {
            $this->config_set('page_size', $params['page_size']);
        } else {
            $this->config_get('page_size', 15);
        }

        if (!empty($params['page'])) {
            $this->config_set('list_page', $params['page']);
        } else {
            $this->config_set('list_page', 1);
        }

        if (empty($attributes) || !is_array($attributes)) {
            $attributes = array('*');
        }

        $this->config_set('return_attributes', $attributes);

        $result  = $this->search_entries($base_dn, $filter, $scope, null, $search);
        $entries = $this->sort_and_slice($result, $params);

        return array(
            'list' => $entries,
            'count' => is_object($result) ? $result->count() : 0,
        );
    }

    /**
     * Prepare environment before _read() call
     */
    protected function read_prepare(&$attributes)
    {
        // always return unique attribute
        $unique_attr = $this->conf->get('unique_attribute');
        if (empty($unique_attr)) {
            $unique_attr = 'nsuniqueid';
        }

        if (!in_array($unique_attr, $attributes)) {
            $attributes[] = $unique_attr;
        }
    }

    /**
     * delete_entry() wrapper with binding and DN resolving
     */
    protected function entry_delete($entry, $attributes = array(), $base_dn = null)
    {
        $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        $entry_dn = $this->entry_dn($entry, $attributes, $base_dn);

        if (!$entry_dn) {
            return false;
        }

        return $this->delete_entry($entry_dn);
    }

    /**
     * add_entry() wrapper with binding
     */
    protected function entry_add($entry_dn, $attrs)
    {
        $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        return $this->add_entry($entry_dn, $attrs);
    }

    /**
     * Return base DN for specified object type
     */
    protected function entry_base_dn($type, $typeid = null)
    {
        if ($typeid) {
            $db  = SQL::get_instance();
            $sql = $db->fetch_assoc($db->query("SELECT `key` FROM {$type}_types WHERE id = ?", $typeid));

            // Check if the type has a specific base DN specified.
            $base_dn = $this->_subject_base_dn($sql['key'] . '_' . $type, true);
        }

        if (empty($base_dn)) {
            $base_dn = $this->_subject_base_dn($type);
        }

        return $base_dn;
    }

    public function _config_get($key, $default = NULL)
    {
        $key_parts = explode("_", $key);
        $this->_log(LOG_DEBUG, var_export($key_parts));

        while (!empty($key_parts)) {
            $value = $this->conf->get(implode("_", $key_parts));
            if (empty($value)) {
                $_discard = array_shift($key_parts);
            } else {
                break;
            }
        }

        if (empty($value)) {
            return $default;
        } else {
            return $value;
        }

    }

    public function _log($level, $msg)
    {
        if (strstr($_SERVER["REQUEST_URI"], "/api/")) {
            $str = "(api) ";
        } else {
            $str = "";
        }

        if (is_array($msg)) {
            $msg = implode("\n", $msg);
        }

        switch ($level) {
            case LOG_DEBUG:
                Log::debug($str . $msg);
                break;
            case LOG_ERR:
                Log::error($str . $msg);
                break;
            case LOG_INFO:
                Log::info($str . $msg);
                break;
            case LOG_WARNING:
                Log::warning($str . $msg);
                break;
            case LOG_ALERT:
            case LOG_CRIT:
            case LOG_EMERG:
            case LOG_NOTICE:
            default:
                Log::trace($str . $msg);
                break;
        }
    }

    private function _subject_base_dn($subject, $strict = false)
    {
        $subject_base_dn = $this->conf->get_raw($this->domain, $subject . "_base_dn");

        if (empty($subject_base_dn)) {
            $subject_base_dn = $this->conf->get_raw("ldap", $subject . "_base_dn");
        }

        if (empty($subject_base_dn) && $strict) {
            $this->_log(LOG_DEBUG, "subject_base_dn for subject $subject not found");
            return null;
        }

        // Attempt to get a configured base_dn
        $base_dn = $this->conf->get($this->domain, "base_dn");

        if (empty($base_dn)) {
            $base_dn = $this->domain_root_dn($this->domain);
        }

        if (!empty($subject_base_dn)) {
            $base_dn = $this->conf->expand($subject_base_dn, array("base_dn" => $base_dn));
        }

        $this->_log(LOG_DEBUG, "subject_base_dn for subject $subject is $base_dn");

        return $base_dn;
    }

    private function legacy_rights($subject)
    {
        $subject_dn    = $this->entry_dn($subject);
        $user_is_admin = false;
        $user_is_self  = false;

        // List group memberships
        $user_groups = $this->find_user_groups($_SESSION['user']->user_bind_dn);
        console("User's groups", $user_groups);

        foreach ($user_groups as $user_group_dn) {
            if ($user_is_admin)
                continue;

            $user_group_dn_components = ldap_explode_dn($user_group_dn, 1);
            unset($user_group_dn_components["count"]);
            $user_group_cn = array_shift($user_group_dn_components);
            if (in_array($user_group_cn, array('admin', 'maintainer', 'domain-maintainer'))) {
                // All rights default to write.
                $user_is_admin = true;
            } else {
                // The user is a regular user, see if the subject is the same has the
                // user session's bind_dn.
                if ($subject_dn == $_SESSION['user']->user_bind_dn) {
                    $user_is_self = true;
                }
            }
        }

        if ($user_is_admin) {
            $standard_rights = array("add", "delete", "read", "write");
        } elseif ($user_is_self) {
            $standard_rights = array("read", "write");
        } else {
            $standard_rights = array("read");
        }

        $rights = array(
            'entryLevelRights' => $standard_rights,
            'attributeLevelRights' => array(),
        );

        $subject    = $this->_search($subject_dn);
        $subject    = $subject->entries(true);
        $attributes = $this->allowed_attributes($subject[$subject_dn]['objectclass']);
        $attributes = array_merge($attributes['may'], $attributes['must']);

        foreach ($attributes as $attribute) {
            $rights['attributeLevelRights'][$attribute] = $standard_rights;
        }

        return $rights;
    }

    private function sort_and_slice(&$result, &$params)
    {
        if (!is_object($result)) {
            return array();
        }

        $entries = $result->entries(true);

        if ($this->vlv_active) {
            return $entries;
        }

        if (!empty($params) && is_array($params)) {
            if (array_key_exists('sort_by', $params)) {
                $this->sort_result_key = $params['sort_by'];
                uasort($entries, array($this, 'sort_result'));
            }

            if (array_key_exists('page_size', $params) && array_key_exists('page', $params)) {
                if ($result->count() > $params['page_size']) {
                    $entries = array_slice($entries, (($params['page'] - 1) * $params['page_size']), $params['page_size'], true);
                }

            }

            if (array_key_exists('sort_order', $params) && !empty($params['sort_order'])) {
                if ($params['sort_order'] == "DESC") {
                    $entries = array_reverse($entries, true);
                }
            }
        }

        return $entries;
    }

    /**
     * Result sorting callback for uasort()
     */
    private function sort_result($a, $b)
    {
        if (is_array($this->sort_result_key)) {
            foreach ($this->sort_result_key as $attrib) {
                if (array_key_exists($attrib, $a) && !$str1) {
                    $str1 = $a[$attrib];
                }
                if (array_key_exists($attrib, $b) && !$str2) {
                    $str2 = $b[$attrib];
                }
            }
        } else {
            $str1 = $a[$this->sort_result_key];
            $str2 = $b[$this->sort_result_key];
        }

        return strcmp(mb_strtoupper($str1), mb_strtoupper($str2));
    }

    /**
     * Qualify a username.
     *
     * Where username is 'kanarip@kanarip.com', the function will return an
     * array containing 'kanarip' and 'kanarip.com'. However, where the
     * username is 'kanarip', the domain name is to be assumed the
     * management domain name.
     */
    private function _qualify_id($username)
    {
        $username_parts = explode('@', $username);
        if (count($username_parts) == 1) {
            $domain_name = $this->conf->get('primary_domain');
        }
        else {
            $domain_name = array_pop($username_parts);
        }

        return array(implode('@', $username_parts), $domain_name);
    }

    /***********************************************************
     ************      Shortcut functions       ****************
     ***********************************************************/

    private function _domain_add_alias($domain, $parent)
    {
        $domain_base_dn = $this->conf->get('ldap', 'domain_base_dn');
        $domain_filter  = $this->conf->get('ldap', 'domain_filter');

        $domain_name_attribute = $this->conf->get('ldap', 'domain_name_attribute');

        // Get the parent
        $domain_filter = '(&(' . $domain_name_attribute . '=' . $parent . ')' . $domain_filter . ')';

        $result = $this->_search($domain_base_dn, $domain_filter);

        if ($result->count() < 1) {
            Log::error("Attempt to add a domain alias for a non-existent parent domain.");
            return false;
        } else if ($result->count() > 1) {
            Log::error("Attempt to add a domain alias for a parent domain which is found to have multiple entries.");
            return false;
        }

        $entries = $result->entries(true);

        $domain_dn    = key($entries);
        $domain_entry = $entries[$domain_dn];

        $_old_attr = array($domain_name_attribute => $domain_entry[$domain_name_attribute]);

        if (is_array($domain)) {
            $_new_attr = array($domain_name_attribute => array_unique(array_merge((array)($domain_entry[$domain_name_attribute]), $domain)));
        } else {
            $_new_attr = array($domain_name_attribute => array($domain_entry[$domain_name_attribute], $domain));
        }

        return $this->modify_entry($domain_dn, $_old_attr, $_new_attr);
    }

    private function _domain_add_new($domain)
    {
        console("Auth::LDAP::_domain_add_new()", $domain);

        $auth = Auth::get_instance();

        $domain_base_dn        = $this->conf->get('ldap', 'domain_base_dn');
        $domain_name_attribute = $this->conf->get('ldap', 'domain_name_attribute');

        if (is_array($domain)) {
            $domain_name = array_shift($domain);
        } else {
            $domain_name = $domain;
            $domain = (array)$domain;
        }

        $dn = $domain_name_attribute . '=' . $domain_name . ',' . $domain_base_dn;
        $attrs = array(
            'objectclass' => array(
                'top',
                'domainrelatedobject'
            ),
            $domain_name_attribute => array_unique(array_merge((array)($domain_name), $domain)),
        );

        $this->add_entry($dn, $attrs);

        $inetdomainbasedn = $this->_standard_root_dn($domain_name);
        $cn = str_replace(array(',', '='), array('\2C', '\3D'), $inetdomainbasedn);

        $dn = "cn=" . $cn . ",cn=mapping tree,cn=config";
        $attrs = array(
            'objectclass' => array(
                'top',
                'extensibleObject',
                'nsMappingTree',
            ),
            'nsslapd-state' => 'backend',
            'cn' => $inetdomainbasedn,
            'nsslapd-backend' => str_replace('.', '_', $domain_name),
        );

        $this->add_entry($dn, $attrs);

        //
        // Use the information we find on the primary domain configuration for
        // the new domain configuration.
        //
        $domain_filter = $this->conf->get('ldap', 'domain_filter');
        $domain_filter = '(&(' . $domain_name_attribute . '=' . $this->conf->get('kolab', 'primary_domain') . ')' . $domain_filter . ')';
        $results  = $this->_search($domain_base_dn, $domain_filter);
        $entries = $results->entries(true);
        $domain_entry = array_shift($entries);

        // The root_dn for the parent domain is needed to find the ldbm
        // database.
        if (in_array('inetdomainbasedn', $domain_entry)) {
            $_base_dn = $domain_entry['inetdomainbasedn'];
        } else {
            $_base_dn = $this->_standard_root_dn($this->conf->get('kolab', 'primary_domain'));
        }

        $result = $this->_read("cn=" . str_replace('.', '_', $this->conf->get('kolab', 'primary_domain') . ",cn=ldbm database,cn=plugins,cn=config"), array('nsslapd-directory'));
        if (!$result) {
            $result = $this->_read("cn=" . $this->conf->get('kolab', 'primary_domain') . ",cn=ldbm database,cn=plugins,cn=config", array('nsslapd-directory'));
        }

        if (!$result) {
            $result = $this->_read("cn=userRoot,cn=ldbm database,cn=plugins,cn=config", array('nsslapd-directory'));
        }

        $this->_log(LOG_DEBUG, "Primary domain ldbm database configuration entry: " . var_export($result, true));

        $result = $result[key($result)];

        $orig_directory = $result['nsslapd-directory'];

        $directory = str_replace(str_replace('.', '_', $this->conf->get('kolab', 'primary_domain')), str_replace('.','_',$domain_name), $result['nsslapd-directory']);

        if ($directory == $orig_directory) {
            $directory = str_replace($this->conf->get('kolab', 'primary_domain'), str_replace('.','_',$domain_name), $result['nsslapd-directory']);
        }

        if ($directory == $orig_directory) {
            $directory = str_replace("userRoot", str_replace('.','_',$domain_name), $result['nsslapd-directory']);
        }

        $dn = "cn=" . str_replace('.', '_', $domain_name) . ",cn=ldbm database,cn=plugins,cn=config";
        $attrs = array(
            'objectclass' => array(
                'top',
                'extensibleobject',
                'nsbackendinstance',
             ),
            'cn' => str_replace('.', '_', $domain_name),
            'nsslapd-suffix' => $inetdomainbasedn,
            'nsslapd-cachesize' => '-1',
            'nsslapd-cachememsize' => '10485760',
            'nsslapd-readonly' => 'off',
            'nsslapd-require-index' => 'off',
            'nsslapd-directory' => $directory,
            'nsslapd-dncachememsize' => '10485760'
        );

        $this->add_entry($dn, $attrs);

        // Query the ACI for the primary domain
        $domain_filter = $this->conf->get('ldap', 'domain_filter');
        $domain_filter = '(&(' . $domain_name_attribute . '=' . $this->conf->get('kolab', 'primary_domain') . ')' . $domain_filter . ')';
        $results  = $this->_search($domain_base_dn, $domain_filter);
        $entries = $results->entries(true);
        $domain_entry = array_shift($entries);

        if (in_array('inetdomainbasedn', $domain_entry)) {
            $_base_dn = $domain_entry['inetdomainbasedn'];
        } else {
            $_base_dn = $this->_standard_root_dn($this->conf->get('kolab', 'primary_domain'));
        }

        $result = $this->_read($_base_dn, array('aci'));
        $result = $result[key($result)];
        $acis   = $result['aci'];

        foreach ($acis as $aci) {
            if (stristr($aci, "SIE Group") === false) {
                continue;
            }
            $_aci = $aci;
        }

        $service_bind_dn = $this->conf->get('ldap', 'service_bind_dn');
        if (empty($service_bind_dn)) {
            $service_bind_dn = $this->conf->get('ldap', 'bind_dn');
        }

        $dn = $inetdomainbasedn;
        $attrs = array(
                // @TODO: Probably just use ldap_explode_dn()
                'dc' => substr($dn, (strpos($dn, '=')+1), ((strpos($dn, ',')-strpos($dn, '='))-1)),
                'objectclass' => array(
                        'top',
                        'domain',
                    ),
                'aci' => array(
                        // Self-modification
                        "(targetattr=\"carLicense || description || displayName || facsimileTelephoneNumber || homePhone || homePostalAddress || initials || jpegPhoto || labeledURI || mobile || pager || photo || postOfficeBox || postalAddress || postalCode || preferredDeliveryMethod || preferredLanguage || registeredAddress || roomNumber || secretary || seeAlso || st || street || telephoneNumber || telexNumber || title || userCertificate || userPassword || userSMIMECertificate || x500UniqueIdentifier\")(version 3.0; acl \"Enable self write for common attributes\"; allow (write) userdn=\"ldap:///self\";)",

                        // Directory Administrators
                        "(targetattr =\"*\")(version 3.0;acl \"Directory Administrators Group\";allow (all) (groupdn=\"ldap:///cn=Directory Administrators," . $inetdomainbasedn . "\" or roledn=\"ldap:///cn=kolab-admin," . $inetdomainbasedn . "\");)",

                        // Configuration Administrators
                        "(targetattr=\"*\")(version 3.0; acl \"Configuration Administrators Group\"; allow (all) groupdn=\"ldap:///cn=Configuration Administrators,ou=Groups,ou=TopologyManagement,o=NetscapeRoot\";)",

                        // Administrator users
                        "(targetattr=\"*\")(version 3.0; acl \"Configuration Administrator\"; allow (all) userdn=\"ldap:///uid=admin,ou=Administrators,ou=TopologyManagement,o=NetscapeRoot\";)",

                        // SIE Group
                        $_aci,

                        // Search Access,
                        "(targetattr = \"*\") (version 3.0;acl \"Search Access\";allow (read,compare,search)(userdn = \"ldap:///" . $inetdomainbasedn . "??sub?(objectclass=*)\");)",

                        // Service Search Access
                        "(targetattr = \"*\") (version 3.0;acl \"Service Search Access\";allow (read,compare,search)(userdn = \"ldap:///" . $service_bind_dn . "\");)",
                    ),
            );

        $this->add_entry($dn, $attrs);

        $dn = "cn=Directory Administrators," . $inetdomainbasedn;
        $attrs = array(
            'objectclass' => array(
                'top',
                'groupofuniquenames',
            ),
            'cn' => 'Directory Administrators',
            'uniquemember' => array(
                'cn=Directory Manager'
            ),
        );

        $this->add_entry($dn, $attrs);

        $dn = "ou=Groups," . $inetdomainbasedn;
        $attrs = array(
            'objectclass' => array('top', 'organizationalunit'),
            'ou' => 'Groups',
        );

        $this->add_entry($dn, $attrs);

        $dn = "ou=People," . $inetdomainbasedn;
        $attrs = array(
            'objectclass' => array('top', 'organizationalunit'),
            'ou' => 'People',
        );

        $this->add_entry($dn, $attrs);

        $dn = "ou=Special Users," . $inetdomainbasedn;
        $attrs = array(
            'objectclass' => array('top', 'organizationalunit'),
            'ou' => 'Special Users',
        );

        $this->add_entry($dn, $attrs);

        $dn = "ou=Resources," . $inetdomainbasedn;
        $attrs = array(
            'objectclass' => array('top', 'organizationalunit'),
            'ou' => 'Resources',
        );

        $this->add_entry($dn, $attrs);

        $dn = "ou=Shared Folders," . $inetdomainbasedn;
        $attrs = array(
            'objectclass' => array('top', 'organizationalunit'),
            'ou' => 'Shared Folders',
        );

        $this->add_entry($dn, $attrs);

        $dn = 'cn=kolab-admin,' . $inetdomainbasedn;
        $attrs = array(
            'objectclass' => array(
                'top',
                'ldapsubentry',
                'nsroledefinition',
                'nssimpleroledefinition',
                'nsmanagedroledefinition',
            ),
            'cn' => 'kolab-admin'
        );

        $this->add_entry($dn, $attrs);

        return true;
    }

    /**
     * Translate a domain name into it's corresponding root dn.
     */
    private function domain_root_dn($domain)
    {
        if (!$this->connect()) {
            $this->_log(LOG_DEBUG, "Could not connect");
            return false;
        }

        $bind_dn = $this->config_get("service_bind_dn", $this->conf->get("service_bind_dn"));
        $bind_pw = $this->config_get("service_bind_pw", $this->conf->get("service_bind_pw"));

        if (!$this->bind($bind_dn, $bind_pw)) {
            $this->_log(LOG_DEBUG, "Could not connect");
            return false;
        }

        $this->_log(LOG_DEBUG, "Auth::LDAP::domain_root_dn(\$domain = $domain) called");
        if (empty($domain)) {
            return false;
        }

        $domain_base_dn        = $this->conf->get('ldap', 'domain_base_dn');
        $domain_filter         = $this->conf->get('ldap', 'domain_filter');
        $domain_name_attribute = $this->conf->get('ldap', 'domain_name_attribute');

        if (empty($domain_name_attribute)) {
            $domain_name_attribute = 'associateddomain';
        }

        $domain_filter         = "(&" . $domain_filter . "(" . $domain_name_attribute . "=" . $domain . "))";

        $result = $this->_search($domain_base_dn, $domain_filter);

        $entries = $result->entries(true);
        $entry_dn = key($entries);
        $entry_attrs = $entries[$entry_dn];

        if (is_array($entry_attrs)) {
            if (array_key_exists('inetdomainbasedn', $entry_attrs) && !empty($entry_attrs['inetdomainbasedn'])) {
                $domain_root_dn = $entry_attrs['inetdomainbasedn'];
            }
            else {
                if (is_array($entry_attrs[$domain_name_attribute])) {
                    $domain_root_dn = $this->_standard_root_dn($entry_attrs[$domain_name_attribute][0]);
                }
                else {
                    $domain_root_dn = $this->_standard_root_dn($entry_attrs[$domain_name_attribute]);
                }
            }
        }
        else {
            $domain_root_dn = $this->_standard_root_dn($domain);
        }

        return $domain_root_dn;

    }

    /**
     * Probe the root dn with the user credentials.
     *
     * When a list of domains is retrieved, this does not mean the user
     * actually has access. Given the root dn for each domain however, we
     * can in fact attempt to list / search the root dn and see if we get
     * any results. If we don't, maybe this user is not authorized for the
     * domain at all?
     */
    private function _probe_root_dn($entry_root_dn)
    {
        //console("Running for entry root dn: " . $entry_root_dn);
        if (($tmpconn = ldapconnect($this->_ldap_server)) == false) {
            //message("LDAP Error: " . $this->_errstr());
            return false;
        }

        //console("User DN: " . $_SESSION['user']->user_bind_dn);

        if (ldap_bind($tmpconn, $_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw) === false) {
            //message("LDAP Error: " . $this->_errstr());
            return false;
        }

        if (($list_success = ldap_list($tmpconn, $entry_root_dn, '(objectClass=*)', array('*', 'aci'))) === false) {
            //message("LDAP Error: " . $this->_errstr());
            return false;
        }

        return true;
    }

    private function _read($entry_dn, $attributes = array('*'))
    {
        $this->config_set('return_attributes', $attributes);

        $result = $this->search($entry_dn, '(objectclass=*)', 'base');

        if ($result) {
            $this->_log(LOG_DEBUG, "Auth::LDAP::_read() result: " . var_export($result->entries(true), true));
            return $result->entries(true);
        } else {
            return false;
        }
    }

    private function _search($base_dn, $filter = '(objectclass=*)', $attributes = array('*'))
    {
        $this->config_set('return_attributes', $attributes);
        $result = $this->search($base_dn, $filter);
        $this->_log(LOG_DEBUG, "Auth::LDAP::_search on $base_dn with $filter for attributes: " . var_export($attributes, true) . " with result: " . var_export($result, true));
        return $result;
    }

    /**
     * From a domain name, such as 'kanarip.com', create a standard root
     * dn, such as 'dc=kanarip,dc=com'.
     *
     * As the parameter $associatedDomains, either pass it an array (such
     * as may have been returned by ldap_get_entries() or perhaps
     * ldap_list()), where the function will assume the first value
     * ($array[0]) to be the uber-level domain name, or pass it a string
     * such as 'kanarip.nl'.
     *
     * @return string
     */
    private function _standard_root_dn($associatedDomains)
    {
        if (is_array($associatedDomains)) {
            // Usually, the associatedDomain in position 0 is the naming attribute associatedDomain
            if ($associatedDomains['count'] > 1) {
                // Issue a debug message here
                $relevant_associatedDomain = $associatedDomains[0];
            }
            else {
                $relevant_associatedDomain = $associatedDomains[0];
            }
        }
        else {
            $relevant_associatedDomain = $associatedDomains;
        }

        return "dc=" . implode(',dc=', explode('.', $relevant_associatedDomain));
    }

}

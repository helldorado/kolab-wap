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

require_once("Net/LDAP3.php");

/**
 * Kolab LDAP handling abstraction class.
 */
class LDAP extends Net_LDAP3 {
    private $conf;

    /**
     * Class constructor
     */
    public function __construct($domain = null) {
        parent::__construct();

        $this->conf = Conf::get_instance();

        // Causes nesting levels to be too deep...?
        //$this->config_set('config_get_hook', Array($this, "_config_get"));

        $this->config_set("debug", TRUE);
        $this->config_set("log_hook", Array($this, "_log"));

        //$this->config_set("vlv", FALSE);
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
    public function authenticate($username, $password) {
        Log::debug("Auth::LDAP: authentication request for $username");

        if (!$this->connect()) {
            return false;
        }

        $result = $this->login($username, $password);

        if (!$result) {
            return FALSE;
        }

        $_SESSION['user']->user_bind_dn = $result;
        $_SESSION['user']->user_bind_pw = $password;

        return $result;
    }

    public function domain_add($domain, $parent_domain = false, $prepopulate = true) {
        // Apply some routines for access control to this function here.
        if (!empty($parent_domain)) {
            if ($this->domain_info($parent_domain)->count() < 1) {
                $this->_domain_add_new($parent_domain, $prepopulate);
            }

            return $this->_domain_add_alias($domain, $parent_domain);
        }
        else {
            return $this->_domain_add_new($domain, $prepopulate);
        }
    }

    public function domain_edit($domain, $attributes, $typeid = null) {
        // Domain identifier
        $unique_attr = $this->unique_attribute();

        // Now that values have been re-generated where necessary, compare
        // the new domain attributes to the original domain attributes.
        $_domain = $this->domain_find_by_attribute(array($unique_attr => $attributes[$unique_attr]));

        if (empty($_domain)) {
            $_domain = $this->entry_dn($domain);

            if (empty($_domain)) {
                return false;
            }

            $_domain_dn = $domain;
        }
        else {
            $_domain_dn = key($_domain);
        }

        if (!$_domain) {
            console("Could not find domain");
            return false;
        }

        $_domain = $this->domain_info($_domain_dn, array_keys($attributes));

        // We should start throwing stuff over the fence here.
        return $this->modify_entry($_domain_dn, $_domain[$_domain_dn], $attributes);
    }

    public function domain_find_by_attribute($attribute) {
        $base_dn = $this->conf->get('ldap', 'domain_base_dn');

        return $this->entry_find_by_attribute($attribute, $base_dn);
    }

    public function domain_info($domain, $attributes = array('*')) {
        $domain_dn = $this->entry_dn($domain);

        Log::trace("Auth::LDAP::domain_info() \$domain_dn: " . $domain_dn . " and attributes: " . var_export($attributes, TRUE));

        if (!$domain_dn) {
            $domain_base_dn        = $this->conf->get('ldap', 'domain_base_dn');
            $domain_filter         = $this->conf->get('ldap', 'domain_filter');
            $domain_name_attribute = $this->conf->get('ldap', 'domain_name_attribute');
            $domain_filter         = "(&" . $domain_filter . "(" . $domain_name_attribute . "=" . $domain . "))";

            Log::trace("Auth::LDAP::domain_info() uses _search()");
            $result = $this->_search($domain_base_dn, $domain_filter, $attributes);
        } else {
            Log::trace("Auth::LDAP::domain_info() uses _read()");
            $result = $this->_read($domain_dn, $attributes);
        }

        if (!$result) {
            return false;
        }

        Log::trace("Auth::LDAP::domain_info() result: " . var_export($result, TRUE));

        return $result;
    }

    /**
     * Proxy to parent function in order to enable us to insert our
     * configuration.
     */
    public function effective_rights($subject) {
        // Ensure we are bound with the user's credentials
        $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        Log::trace("Auth::LDAP::effective_rights(\$subject = '" . $subject . "')");

        switch ($subject) {
            case "domain":
                return parent::effective_rights($this->conf->get("ldap", "domain_base_dn"));
                break;
            case "group":
                return parent::effective_rights($this->_subject_base_dn("group"));
                break;
            case "resource":
                return parent::effective_rights($this->_subject_base_dn("resource"));
                break;
            case "role":
                return parent::effective_rights($this->_subject_base_dn("role"));
                break;
            case "user":
                return parent::effective_rights($this->_subject_base_dn("user"));
                break;
            default:
                return parent::effective_rights($subject);
                break;
        }

    }

    public function group_add($attrs, $typeid = null) {
        if ($typeid == null) {
            $type_str = 'group';
        }
        else {
            $db   = SQL::get_instance();
            $_key = $db->fetch_assoc($db->query("SELECT `key` FROM group_types WHERE id = ?", $typeid));
            $type_str = $_key['key'];
        }

        // Check if the group_type has a specific base DN specified.
        $base_dn = $this->conf->get($type_str . "_group_base_dn");
        // If not, take the regular user_base_dn
        if (!$base_dn) {
            $base_dn = $this->conf->get("group_base_dn");
        }

        // TODO: The rdn is configurable as well.
        // Use [$type_str . "_"]user_rdn_attr
        $dn = "cn=" . $attrs['cn'] . "," . $base_dn;

        return $this->add_entry($dn, $attrs);
    }

    public function group_delete($group) {
        $group_dn = $this->entry_dn($group);

        if (!$group_dn) {
            return false;
        }

        return $this->delete_entry($group_dn);
    }

    public function group_edit($group, $attributes, $typeid = null) {
        // Group identifier
        $unique_attr = $this->unique_attribute();
        $attributes[$unique_attr] = $group;

        // Now that values have been re-generated where necessary, compare
        // the new group attributes to the original group attributes.
        $_group = $this->entry_find_by_attribute(array($unique_attr => $attributes[$unique_attr]));

        if (!$_group) {
            console("Could not find group");
            return false;
        }

        $_group_dn = key($_group);
        $_group = $this->group_info($_group_dn, array_keys($attributes));

        // We should start throwing stuff over the fence here.
        return $this->modify_entry($_group_dn, $_group[$_group_dn], $attributes);
    }

    public function group_find_by_attribute($attribute) {
        return $this->entry_find_by_attribute($attribute);
    }

    public function group_info($group, $attributes = array('*')) {
        Log::trace("Auth::LDAP::group_info() for group " . var_export($group, TRUE));
        $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        $unique_attr = $this->config_get('unique_attribute', 'nsuniqueid');
        if (!in_array($unique_attr, $attributes)) {
            $attributes[] = $unique_attr;
        }

        $this->config_set('return_attributes', $attributes);

        $group_dn = $this->entry_dn($group);

        Log::trace("group_info() group_dn " . var_export($group_dn, TRUE));

        if (!$group_dn) {
            return false;
        }

        $group_info = $this->_read($group_dn, $attributes);
        Log::trace("Auth::LDAP::group_info() result: " . var_export($group_info, TRUE));
        return $group_info;

    }

    public function group_members_list($group, $recurse = true) {
        $group_dn = $this->entry_dn($group);

        if (!$group_dn) {
            return false;
        }

        return $this->_list_group_members($group_dn, null, $recurse);
    }

    public function list_domains($attributes = array(), $search = array(), $params = array()) {
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
        }

        if (!empty($params['page'])) {
            $this->config_set('list_page', $params['page']);
        }

        if (empty($attributes) || !is_array($attributes)) {
            $attributes = array('*');
        }

        $this->config_set('return_attributes', $attributes);

        $section = $this->conf->get('kolab', 'auth_mechanism');
        $base_dn = $this->conf->get($section, 'domain_base_dn');
        $filter  = $this->conf->get($section, 'domain_filter');

        $kolab_filter = $this->conf->get($section, 'kolab_domain_filter');
        if (empty($filter) && !empty($kolab_filter)) {
            $filter = $kolab_filter;
        }

        $result = $this->search_entries($base_dn, $filter, 'sub', NULL, $search);

        $entries = $this->sort_and_slice($result, $params);

        return Array(
                'list' => $entries,
                'count' => $result->count()
            );
    }

    public function list_groups($attributes = array(), $search = array(), $params = array()) {
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
        }

        if (!empty($params['page'])) {
            $this->config_set('list_page', $params['page']);
        }

        if (empty($attributes) || !is_array($attributes)) {
            $attributes = array('*');
        }

        $this->config_set('return_attributes', $attributes);

        $base_dn = $this->_subject_base_dn("group");
        $filter = $this->conf->get('group_filter');

        $result = $this->search_entries($base_dn, $filter, 'sub', NULL, $search);

        $entries = $this->sort_and_slice($result, $params);

        return Array(
                'list' => $entries,
                'count' => $result->count()
            );
    }

    public function list_resources($attributes = array(), $search = array(), $params = array()) {
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

        $this->config_set("return_attributes", $attributes);

        $base_dn = $this->_subject_base_dn("resource");
        $filter  = $this->conf->get('resource_filter');

        if (!$filter) {
            $filter = '(&(objectclass=*)(!(objectclass=organizationalunit)))';
        }

        if (empty($attributes) || !is_array($attributes)) {
            $attributes = array('*');
        }

        if ($s_filter = $this->search_filter($search)) {
            // join search filter with objectClass filter
            $filter = '(&' . $filter . $s_filter . ')';
        }

        $result = $this->search_entries($base_dn, $filter, 'sub', NULL, $search);

        $entries = $this->sort_and_slice($result, $params);

        return Array(
                'list' => $entries,
                'count' => $result->count()
            );
    }

    public function list_roles($attributes = array(), $search = array(), $params = array()) {
        if (!empty($params['sort_by'])) {
            if (!in_array($params['sort_by'], $attributes)) {
                $attributes[] = $params['sort_by'];
            }
        }

        $base_dn = $this->_subject_base_dn("role");

        // TODO: From config
        $filter  = "(&(objectclass=ldapsubentry)(objectclass=nsroledefinition))";

        if (empty($attributes) || !is_array($attributes)) {
            $attributes = array('*');
        }

        $unique_attr = $this->unique_attribute();
        if (!in_array($unique_attr, $attributes)) {
            $attributes[] = $unique_attr;
        }

        if ($s_filter = $this->search_filter($search)) {
            // join search filter with objectClass filter
            $filter = '(&' . $filter . $s_filter . ')';
        }

        $result = $this->_search($base_dn, $filter, $attributes);

        $entries = $this->sort_and_slice($result, $params);

        return Array(
                'list' => $entries,
                'count' => $result->count()
            );
    }

    public function list_users($attributes = array(), $search = array(), $params = array()) {
        Log::trace("Auth::LDAP::list_users(" . var_export($attributes, TRUE) . ", " . var_export($search, TRUE) . ", " . var_export($params, TRUE));

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

        $this->config_set("return_attributes", $attributes);

        $base_dn = $this->_subject_base_dn("user");
        $filter = $this->conf->get('user_filter');

        Log::trace("Auth::LDAP::list_users() searching entries in $base_dn with $filter, 'sub', NULL, " . var_export($search, TRUE));

        $result = $this->search_entries($base_dn, $filter, 'sub', NULL, $search);

        $entries = $this->sort_and_slice($result, $params);

        return Array(
                'list' => $entries,
                'count' => $result->count()
            );
    }

    public function resource_add($attrs, $typeid = null) {
        if ($typeid == null) {
            $type_str = 'resource';
        }
        else {
            $db   = SQL::get_instance();
            $_key = $db->fetch_assoc($db->query("SELECT `key` FROM resource_types WHERE id = ?", $typeid));
            $type_str = $_key['key'];
        }

        // Check if the resource_type has a specific base DN specified.
        $base_dn = $this->conf->get($type_str . "_resource_base_dn");
        // If not, take the regular user_base_dn
        if (!$base_dn) {
            $base_dn = $this->conf->get("resource_base_dn");
        }

        // TODO: The rdn is configurable as well.
        // Use [$type_str . "_"]user_rdn_attr
        $dn = "cn=" . $attrs['cn'] . "," . $base_dn;

        return $this->add_entry($dn, $attrs);
    }

    public function resource_delete($resource) {
        $resource_dn = $this->entry_dn($resource);

        if (!$resource_dn) {
            return false;
        }

        return $this->delete_entry($resource_dn);
    }

    public function resource_edit($resource, $attributes, $typeid = null) {
        // Resource identifier
        $unique_attr = $this->unique_attribute();
        $attributes[$unique_attr] = $resource;

        console("\$this->domain: " . $this->domain);
        // Now that values have been re-generated where necessary, compare
        // the new resource attributes to the original resource attributes.
        $_resource = $this->entry_find_by_attribute(array($unique_attr => $attributes[$unique_attr]));

        if (!$_resource) {
            console("Could not find resource");
            return false;
        }

        $_resource_dn = key($_resource);
        $_resource = $this->resource_info($_resource_dn, array_keys($attributes));

        // We should start throwing stuff over the fence here.
        return $this->modify_entry($_resource_dn, $_resource[$_resource_dn], $attributes);
    }

    public function resource_find_by_attribute($attribute) {
        return $this->entry_find_by_attribute($attribute);
    }

    public function resource_info($resource, $attributes = array('*')) {
        $resource_dn = $this->entry_dn($resource);

        if (!$resource_dn) {
            return false;
        }

        return $this->_read($resource_dn, $attributes);
    }

    public function resource_members_list($resource, $recurse = true) {
        $resource_dn = $this->entry_dn($resource);

        if (!$resource_dn) {
            return false;
        }

        return $this->_list_resource_members($resource_dn, null, $recurse);
    }

    public function role_add($attrs) {
        if ($typeid == null) {
            $type_str = 'role';
        }
        else {
            $db   = SQL::get_instance();
            $_key = $db->fetch_assoc($db->query("SELECT `key` FROM role_types WHERE id = ?", $typeid));
            $type_str = $_key['key'];
        }

        $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        $base_dn = $this->_subject_base_dn('role');

        // TODO: The rdn is configurable as well.
        // Use [$type_str . "_"]user_rdn_attr
        $dn = "cn=" . $attrs['cn'] . "," . $base_dn;

        return $this->add_entry($dn, $attrs);
    }

    public function role_edit($role, $attributes, $typeid = null) {
        // Resource identifier
        $unique_attr = $this->unique_attribute();
        $attributes[$unique_attr] = $role;

        // Now that values have been re-generated where necessary, compare
        // the new role attributes to the original role attributes.
        $_role = $this->entry_find_by_attribute(array($unique_attr => $attributes[$unique_attr], 'objectclass' => 'ldapsubentry'));

        if (!$_role) {
            Log::error("Could not find role identified with $role.");
            return false;
        }

        $_role_dn = key($_role);
        $_role = $this->role_info($_role_dn, array_keys($attributes));

        // We should start throwing stuff over the fence here.
        return $this->modify_entry($_role_dn, $_role[$_role_dn], $attributes);
    }

    public function role_find_by_attribute($attribute) {
        Log::trace("Finding role by attribute: " . var_export($attribute, TRUE));

        $attribute['objectclass'] = 'ldapsubentry';
        $result = $this->entry_find_by_attribute($attribute);

        if (is_array($result) && count($result) == 0) {
            return key($result);
        }

        return false;
    }

    public function role_info($role, $attributes = array('*')) {
        $role_dn = $this->entry_dn($role);

        if (!$role_dn) {
            return false;
        }

        $unique_attr = $this->unique_attribute();
        if (!in_array($unique_attr, $attributes)) {
            $attributes[] = $unique_attr;
        }

        $result = $this->_search($role_dn, '(objectclass=ldapsubentry)', $attributes);
        Log::trace("Auth::LDAP::role_info() result: " . var_export($result, TRUE));
        return $result->entries(TRUE);
    }

    public function search($base_dn, $filter = '(objectclass=*)', $scope = 'sub', $sort = NULL, $search = Array()) {
        if (isset($_SESSION['user']->user_bind_dn) && !empty($_SESSION['user']->user_bind_dn)) {
            $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);
        }

        Log::trace("Relaying search to parent:" . var_export(func_get_args(), TRUE));
        return parent::search($base_dn, $filter, $scope, $sort, $search);
    }

    public function user_add($attrs, $typeid = null) {
        if ($typeid == null) {
            $type_str = 'user';
        }
        else {
            $db   = SQL::get_instance();
            $_key = $db->fetch_assoc($db->query("SELECT `key` FROM user_types WHERE id = ?", $typeid));
            $type_str = $_key['key'];
        }

        // Check if the user_type has a specific base DN specified.
        $base_dn = $this->_subject_base_dn($type_str . "_user");
        if (empty($base_dn)) {
            $base_dn = $this->_subject_base_dn("user");
        }

        if (!empty($attrs['ou'])) {
            $base_dn = $attrs['ou'];
        }

        console("Base DN now: $base_dn");

        // TODO: The rdn is configurable as well.
        // Use [$type_str . "_"]user_rdn_attr
        $dn = "uid=" . $attrs['uid'] . "," . $base_dn;

        return $this->add_entry($dn, $attrs);
    }

    public function user_edit($user, $attributes, $typeid = null) {
        Log::trace("user.edit() called for $user, attributes", $attributes);

        $unique_attr = $this->config_get('unique_attribute', 'nsuniqueid');

        $attributes[$unique_attr] = $user;

        // Now that values have been re-generated where necessary, compare
        // the new group attributes to the original group attributes.
        $_user = $this->entry_find_by_attribute(array($unique_attr => $attributes[$unique_attr]));

        if (!$_user) {
            console("Could not find user");
            return false;
        }
        $_user_dn = key($_user);
        $_user = $this->user_info($_user_dn, array_keys($attributes));

        console("Auth::LDAP::user_edit() existing \$_user info", $_user);

        // We should start throwing stuff over the fence here.
        return $this->modify_entry($_user_dn, $_user[$_user_dn], $attributes);
    }

    public function user_delete($user) {
        $user_dn = $this->entry_dn($user);

        if (!$user_dn) {
            return false;
        }

        return $this->delete_entry($user_dn);
    }

    public function user_info($user, $attributes = array('*')) {
        Log::trace("Auth::LDAP::user_info() for user " . var_export($user, TRUE));
        $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        $unique_attr = $this->config_get('unique_attribute', 'nsuniqueid');
        if (!in_array($unique_attr, $attributes)) {
            $attributes[] = $unique_attr;
        }

        $this->config_set('return_attributes', $attributes);

        $user_dn = $this->entry_dn($user);

        Log::trace("user_info() user_dn " . var_export($user_dn, TRUE));
        if (!$user_dn) {
            return false;
        }

        return $this->_read($user_dn, $attributes);
    }

    public function user_find_by_attribute($attribute) {
        return $this->entry_find_by_attribute($attribute);
    }

    public function _config_get($key, $default = NULL) {
        $key_parts = explode("_", $key);
        Log::trace(var_export($key_parts));

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

    public function _log($level, $msg) {
        if (strstr($_SERVER["REQUEST_URI"], "/api/")) {
            $str = "(api) ";
        } else {
            $str = "";
        }

        switch ($level) {
            case LOG_DEBUG:
                Log::debug($str . implode("\n", $msg));
                break;
            case LOG_ERR:
                Log::error($str . implode("\n", $msg));
                break;
            case LOG_INFO:
                Log::info($str . implode("\n", $msg));
                break;
            case LOG_WARNING:
                Log::warning($str . implode("\n", $msg));
                break;
            case LOG_ALERT:
            case LOG_CRIT:
            case LOG_EMERG:
            case LOG_NOTICE:
            default:
                Log::trace($str . implode("\n", $msg));
                break;
        }
    }

    private function _subject_base_dn($subject) {
        // Attempt to get a configured base_dn
        $base_dn = $this->conf->get($this->domain, "base_dn");

        if (empty($base_dn)) {
            $base_dn = $this->domain_root_dn($this->domain);
        }

        Log::trace(__FILE__ . "::" . __FUNCTION__ . " using base_dn $base_dn");

        if (empty($subject)) {
            return $base_dn;
        } else {
            $subject_base_dn = $this->conf->get_raw($this->domain, $subject . "_base_dn");
            if (empty($subject_base_dn)) {
                $subject_base_dn = $this->conf->get_raw("ldap", $subject . "_base_dn");
            }
            $base_dn = $this->conf->expand($subject_base_dn, array("base_dn" => $base_dn));
        }

        Log::trace("subject_base_dn for subject $subject results in $base_dn");

        return $base_dn;
    }

    private function legacy_rights($subject) {
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
        $attributes = $this->allowed_attributes($subject[$subject_dn]['objectclass']);
        $attributes = array_merge($attributes['may'], $attributes['must']);

        foreach ($attributes as $attribute) {
            $rights['attributeLevelRights'][$attribute] = $standard_rights;
        }

        return $rights;
    }

    private function sort_and_slice(&$result, &$params) {
        if (!empty($params) && is_array($params)) {
            if (array_key_exists('sort_by', $params)) {
                if (is_array($params['sort_by'])) {
                    $sort = array_shift($params['sort_by']);
                } else {
                    $sort = $params['sort_by'];
                }

                $result->sort($sort);

            }

            if (array_key_exists('page_size', $params) && array_key_exists('page', $params)) {
                $entries = $result->entries(TRUE);
                if ($result->count() > $params['page_size']) {
                    $entries = array_slice($entries, (($params['page'] - 1) * $params['page_size']), $params['page_size'], TRUE);
                }

            } else {
                $entries = $result->entries(TRUE);
            }

            if (array_key_exists('sort_order', $params) && !empty($params['sort_order'])) {
                if ($params['sort_order'] == "DESC") {
                    $entries = array_reverse($entries, TRUE);
                }
            }
        } else {
            $entries = $result->entries(TRUE);
        }

        return $entries;
    }

    private function unique_attribute() {
        $unique_attr = $this->conf->get("unique_attribute");
        return empty($unique_attr) ? 'nsuniqueid' : $unique_attr;
    }

    /**
     * Qualify a username.
     *
     * Where username is 'kanarip@kanarip.com', the function will return an
     * array containing 'kanarip' and 'kanarip.com'. However, where the
     * username is 'kanarip', the domain name is to be assumed the
     * management domain name.
     */
    private function _qualify_id($username) {
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

    private function _domain_add_alias($domain, $parent) {
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

        $entries = $result->entries(TRUE);

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

    private function _domain_add_new($domain) {
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
        $entries = $results->entries(TRUE);
        $domain_entry = array_shift($entries);

        // The root_dn for the parent domain is needed to find the ldbm
        // database.
        if (in_array('inetdomainbasedn', $domain_entry)) {
            $_base_dn = $domain_entry['inetdomainbasedn'];
        } else {
            $_base_dn = $this->_standard_root_dn($this->conf->get('kolab', 'primary_domain'));
        }

        $result = $this->_read("cn=" . str_replace('.', '_', $this->conf->get('kolab', 'primary_domain') . ",cn=ldbm database,cn=plugins,cn=config"), array('nsslapd-directory'));

        Log::trace("Primary domain ldbm database configuration entry: " . var_export($result, TRUE));

        $result = $result[key($result)];
        $directory = str_replace(str_replace('.', '_', $this->conf->get('kolab', 'primary_domain')), str_replace('.','_',$domain_name), $result['nsslapd-directory']);

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
        $entries = $results->entries(TRUE);
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
            if (stristr($aci, "SIE Group") === FALSE) {
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
                        "(targetattr = \"*\") (version 3.0;acl \"Search Access\";allow (read,compare,search)(userdn = \"ldap:///" . $inetdomainbasedn . "\");)",

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
        if (!empty($this->domain_root_dn)) {
            return $this->domain_root_dn;
        }

        if (!$this->connect()) {
            Log::trace("Could not connect");
            return false;
        }

        $bind_dn = $this->config_get("service_bind_dn", $this->conf->get("service_bind_dn"));
        $bind_pw = $this->config_get("service_bind_pw", $this->conf->get("service_bind_pw"));

        if (!$this->bind($bind_dn, $bind_pw)) {
            Log::trace("Could not connect");
            return false;
        }

        Log::trace("Auth::LDAP::domain_root_dn(\$domain = $domain) called");
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

        $entries = $result->entries(TRUE);
        $entry_dn = key($entries);
        $entry_attrs = $entries[$entry_dn];

        if (is_array($entry_attrs)) {
            if (in_array('inetdomainbasedn', $entry_attrs) && !empty($entry_attrs['inetdomainbasedn'])) {
                $this->domain_root_dn = $entry_attrs['inetdomainbasedn'];
            }
            else {
                if (is_array($entry_attrs[$domain_name_attribute])) {
                    $this->domain_root_dn = $this->_standard_root_dn($entry_attrs[$domain_name_attribute][0]);
                }
                else {
                    $this->domain_root_dn = $this->_standard_root_dn($entry_attrs[$domain_name_attribute]);
                }
            }
        }
        else {
            $this->domain_root_dn = $this->_standard_root_dn($domain);
        }

        return $this->domain_root_dn;

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
    private function _probe_root_dn($entry_root_dn) {
        console("Running for entry root dn: " . $entry_root_dn);
        if (($tmpconn = ldapconnect($this->_ldap_server)) == false) {
            //message("LDAP Error: " . $this->_errstr());
            return false;
        }

        console("User DN: " . $_SESSION['user']->user_bind_dn);

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

    private function _read($entry_dn, $attributes = Array('*')) {
        $this->config_set('return_attributes', $attributes);

        $result = $this->search($entry_dn, '(objectclass=*)', 'base');

        Log::trace("Auth::LDAP::_read() result: " . var_export($result->entries(TRUE), TRUE));

        return $result ? $result->entries(TRUE) : FALSE;
    }

    private function _search($base_dn, $filter = '(objectclass=*)', $attributes = Array('*')) {
        $this->config_set('return_attributes', $attributes);
        $result = $this->search($base_dn, $filter);
        Log::trace("Auth::LDAP::_search on $base_dn with $filter for attributes: " . var_export($attributes, TRUE) . " with result: " . var_export($result, TRUE));
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
    private function _standard_root_dn($associatedDomains) {
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

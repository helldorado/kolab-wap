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

/**
 * Kolab LDAP handling abstraction class.
 */
class LDAP
{
    public $_name = "LDAP";

    private $conn;
    private $bind_dn;
    private $bind_pw;

    private $attribute_level_rights_map = array(
            "r" => "read",
            "s" => "search",
            "w" => "write",
            "o" => "delete",
            "c" => "compare",
            "W" => "write",
            "O" => "delete"
        );

    private $entry_level_rights_map = array(
            "a" => "add",
            "d" => "delete",
            "n" => "modrdn",
            "v" => "read"
        );


    // This is the default and should actually be set through Conf.
    private $_ldap_uri = 'ldap://localhost:389/';

    private $conf;

    /**
     * Class constructor
     */
    public function __construct($domain = null)
    {
        $this->conf = Conf::get_instance();

        // See if we are to connect to any domain explicitly defined.
        if (!isset($domain) || empty($domain)) {
            // If not, attempt to get the domain from the session.
            if (isset($_SESSION['user'])) {
                try {
                    $domain = $_SESSION['user']->get_domain();
                } catch (Exception $e) {
                    // TODO: Debug logging
                    error_log("Warning, user not authenticated yet");
                }
            }
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

        // We can also use the parse_url() to pass on the bind dn and pw:
        //
        // $ldap = new LDAP('ldap://uid=svc-kwap,ou=Services,ou=Accounts,dc=kanarip,dc=com:VerySecret@localhost/');
        // and the following line uncommented:
        //
        // echo "<pre>"; print_r(parse_url($ldap_uri)); echo "</pre>";
        //
        // creates:
        //
        // array
        // (
        //    [scheme] => ldap
        //    [host] => localhost
        //    [user] => uid=svc-kwap,ou=Services,ou=Accounts,dc=kanarip,dc=com
        //    [pass] => VerySecret
        //    [path] => /
        // )
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
    public function authenticate($username, $password)
    {
        error_log("LDAP authentication request for $username");

        if (!$this->_connect()) {
            return false;
        }

        // Attempt to explode the username to see if it is in fact a DN,
        // such as would be the case for 'cn=Directory Manager' or
        // 'uid=admin'.
        $subject = $this->entry_dn($username);

        //console($subject);

        if (!$subject) {
            list($this->userid, $this->domain) = $this->_qualify_id($username);
            $root_dn = $this->domain_root_dn($this->domain);

            // Compose a filter to find the subject dn.
            // Use the kolab_user_filter first, if configured, and the user_filter
            // as a fallback.
            // Use the auth_attrs configured.
            $filter = '(&';

            $user_filter = $this->conf->get('kolab_user_filter');

            if (!$user_filter) {
                $user_filter = $this->conf->get('user_filter');
            }

            $filter .= $user_filter;

            $auth_attrs = $this->conf->get_list('auth_attrs');

            if (count($auth_attrs) > 0) {
                $filter .= '(|';

                foreach ($auth_attrs as $attr) {
                    $filter .= '(' . $attr . '=' . $this->userid . ')';
                    $filter .= '(' . $attr . '=' . $this->userid . '@' . $this->domain . ')';
                }

                $filter .= ')';
            } else {
                // Default to uid.
                $filter .= '(|(uid=' . $this->userid . '))';
            }

            $filter .= ')';

            $subject_dn = $this->_get_user_dn($root_dn, $filter);
        } else {
            $subject_dn = $subject;
        }

        if (($bind_ok = $this->_bind($subject_dn, $password)) == true) {
//            $this->_unbind();

            if (isset($_SESSION['user'])) {
                $_SESSION['user']->user_root_dn = $root_dn;
                $_SESSION['user']->user_bind_dn = $subject_dn;
                $_SESSION['user']->user_bind_pw = $password;
                error_log("Successfully bound with User DN: " . $_SESSION['user']->user_bind_dn);
            }
            else {
                error_log("Successfully bound with User DN: " . $subject_dn . " but not saving it to the session");
            }

            // @TODO: return unique attribute
            return $subject_dn;
        }
        else {
            error_log("LDAP Error: " . $this->_errstr());
            return false;
        }
    }

    public function attribute_details($attributes = array())
    {
        $_schema = $this->init_schema();

        $attribs = $_schema->getAll('attributes');

        $attributes_details = array();

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $attribs)) {
                $attrib_details = $attribs[$attribute];

                if (!empty($attrib_details['sup'])) {
                    foreach ($attrib_details['sup'] as $super_attrib) {
                        $_attrib_details = $attribs[$super_attrib];
                        if (is_array($_attrib_details)) {
                            $attrib_details = array_merge($_attrib_details, $attrib_details);
                        }
                    }
                }
            } elseif (array_key_exists(strtolower($attribute), $attribs)) {
                $attrib_details = $attribs[strtolower($attribute)];

                if (!empty($attrib_details['sup'])) {
                    foreach ($attrib_details['sup'] as $super_attrib) {
                        $_attrib_details = $attribs[$super_attrib];
                        if (is_array($_attrib_details)) {
                            $attrib_details = array_merge($_attrib_details, $attrib_details);
                        }
                    }
                }
            } else {
                error_log("No schema details exist for attribute $attribute (which is strange)");
            }

            // The relevant parts only, please
            $attributes_details[$attribute] = Array(
                    'type' => (array_key_exists('single-value', $attrib_details) && $attrib_details['single-value']) ? "text" : "list",
                    'description' => $attrib_details['desc'],
                    'syntax' => $attrib_details['syntax'],
                    'max-length' => (array_key_exists('max_length', $attrib_details)) ? $attrib_details['max-length'] : false,
                );
        }

        return $attributes_details;
    }

    public function allowed_attributes($objectclasses = Array())
    {
        //console("Listing allowed_attributes for objectclasses", $objectclasses);

        $_schema = $this->init_schema();

        if (!is_array($objectclasses)) {
            return false;
        }

        if (empty($objectclasses)) {
            return false;
        }

        $may = Array();
        $must = Array();
        $superclasses = Array();

        foreach ($objectclasses as $objectclass) {
            $superclass = $_schema->superclass($objectclass);
            if (!empty($superclass)) {
                $superclasses = array_merge($superclass, $superclasses);
            }

            $_may = $_schema->may($objectclass);
            if (is_array($_may)) {
                $may = array_merge($may, $_may);
            } /* else {
            } */
            $_must = $_schema->must($objectclass);
            if (is_array($_must)) {
                $must = array_merge($must, $_must);
            } /* else {
                var_dump($_must);
            } */
        }

        return Array('may' => $may, 'must' => $must, 'super' => $superclasses);

    }

    public function domain_add($domain, $parent_domain = false, $prepopulate = true)
    {
        // Apply some routines for access control to this function here.
        if (!empty($parent_domain)) {
            return $this->_domain_add_alias($domain, $parent_domain);
        }
        else {
            return $this->_domain_add_new($domain, $prepopulate);
        }
    }

    public function domain_edit($domain, $attributes, $typeid = null)
    {
        // Domain identifier
        $unique_attr = $this->unique_attribute();
        $attributes[$unique_attr] = $domain;

        // Now that values have been re-generated where necessary, compare
        // the new domain attributes to the original domain attributes.
        $_domain = $this->domain_find_by_attribute(array($unique_attr => $attributes[$unique_attr]));

        if (!$_domain) {
            //console("Could not find domain");
            return false;
        }

        $_domain_dn = key($_domain);
        $_domain = $this->domain_info($_domain_dn, array_keys($attributes));

        // We should start throwing stuff over the fence here.
        return $this->modify_entry($_domain_dn, $_domain[$_domain_dn], $attributes);
    }

    public function domain_find_by_attribute($attribute)
    {
        $conf = Conf::get_instance();
        $base_dn = $conf->get('ldap', 'domain_base_dn');
        return $this->entry_find_by_attribute($attribute, $base_dn);
    }

    public function domain_info($domain, $attributes = array('*'))
    {
        $conf = Conf::get_instance();

        $domain_dn = $this->entry_dn($domain);

        if (!$domain_dn) {
            $domain_base_dn = $conf->get('ldap', 'domain_base_dn');
            $domain_filter = $conf->get('ldap', 'domain_filter');
            $domain_name_attribute = $conf->get('ldap', 'domain_name_attribute');
            $domain_filter = "(&$domain_filter($domain_name_attribute=$domain))";
            $result = self::normalize_result($this->_search($domain_base_dn, $domain_filter, $attributes));
        } else {
            $result = self::normalize_result($this->_search($domain_dn, '(objectclass=*)', $attributes));
        }

        if (!$result) {
            return false;
        }

        //console("domain_info() result:", $result);

        return $result;
    }

    public function effective_rights($subject)
    {
        $effective_rights_control_oid = "1.3.6.1.4.1.42.2.27.9.5.2";

        $supported_controls = $this->supported_controls();

        if (!in_array($effective_rights_control_oid, $supported_controls)) {
            error_log("No getEffectiveRights control in supportedControls");
            return $this->legacy_rights($subject);
        }

        $attributes = array(
                'attributeLevelRights' => array(),
                'entryLevelRights' => array(),
            );

        $output = array();

        $conf = Conf::get_instance();

        $entry_dn = $this->entry_dn($subject);
        if (!$entry_dn) {
            $entry_dn = $conf->get($subject . "_base_dn");
        }
        if (!$entry_dn) {
            $entry_dn = $conf->get("base_dn");
        }

        //console("effective_rights for $subject resolves to $entry_dn");

        $moz_ldapsearch = "/usr/lib64/mozldap/ldapsearch";
        if (!is_file($moz_ldapsearch)) {
            $moz_ldapsearch = "/usr/lib/mozldap/ldapsearch";
        }

        $command = array(
                $moz_ldapsearch,
                '-x',
                '-h',
                $this->_ldap_server,
                '-p',
                $this->_ldap_port,
                '-b',
                '"' . $entry_dn . '"',
                '-D',
                '"' . $_SESSION['user']->user_bind_dn . '"',
                '-w',
                '"' . $_SESSION['user']->user_bind_pw . '"',
                '-J',
                '"' . implode(
                        ':',
                        array(
                                '1.3.6.1.4.1.42.2.27.9.5.2',            // OID
                                'true',                                 // Criticality
                                'dn:' . $_SESSION['user']->user_bind_dn // User DN
                            )
                    ) . '"',
                '-s',
                'base',
                '"(objectclass=*)"',
                '"*"',
            );

        //console("Executing command " . implode(' ', $command));

        exec(implode(' ', $command), $output, $return_code);

        //console("Output", $output, "Return code: " . $return_code);

        $lines = array();
        foreach ($output as $line_num => $line) {
            if (substr($line, 0, 1) == " ") {
                $lines[count($lines)-1] .= trim($line);
            } else {
                $lines[] = trim($line);
            }
        }

        foreach ($lines as $line) {
            $line_components = explode(':', $line);
            $attribute_name = array_shift($line_components);
            $attribute_value = trim(implode(':', $line_components));

            switch ($attribute_name) {
                case "attributeLevelRights":
                    $attributes[$attribute_name] = $this->parse_attribute_level_rights($attribute_value);
                    break;
                case "dn":
                    $attributes[$attribute_name] = $attribute_value;
                    break;
                case "entryLevelRights":
                    $attributes[$attribute_name] = $this->parse_entry_level_rights($attribute_value);
                    break;

                default:
                    break;
            }
        }

        return $attributes;
    }

    public function find_user_groups($member_dn)
    {
        error_log(__FILE__ . "(" . __LINE__ . "): " .  $member_dn);

        $groups = array();

        $root_dn = $this->domain_root_dn($this->domain);

        // TODO: Do not query for both, it's either one or the other
        $entries = $this->_search($root_dn, "(|" .
                "(&(objectclass=groupofnames)(member=$member_dn))" .
                "(&(objectclass=groupofuniquenames)(uniquemember=$member_dn))" .
            ")");

        $entries = self::normalize_result($entries);

        foreach ($entries as $entry_dn => $entry_attributes) {
            $groups[] = $entry_dn;
        }

        return $groups;
    }

    public function get_attribute($subject_dn, $attribute)
    {
        $result = $this->_search($subject_dn, '(objectclass=*)', (array)($attribute));
        $result = self::normalize_result($result);
        $dn = key($result);
        $attr = key($result[$dn]);
        return $result[$dn][$attr];
    }

    public function get_attributes($subject_dn, $attributes)
    {
        $result = $this->_search($subject_dn, '(objectclass=*)', $attributes);
        $result = self::normalize_result($result);

        if (!empty($result)) {
            $result = array_pop($result);
            return $result;
        }

        return false;
    }

    public function group_add($attrs, $typeid = null)
    {
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
        if (!$base_dn)
            $base_dn = $this->conf->get("group_base_dn");

        // TODO: The rdn is configurable as well.
        // Use [$type_str . "_"]user_rdn_attr
        $dn = "cn=" . $attrs['cn'] . "," . $base_dn;

        return $this->_add($dn, $attrs);
    }

    public function group_delete($group)
    {
        $group_dn = $this->entry_dn($group);

        if (!$group_dn) {
            return false;
        }

        return $this->_delete($group_dn);
    }

    public function group_edit($group, $attributes, $typeid = null)
    {
/*
        // Get the type "key" string for the next few settings.
        if ($typeid == null) {
            $type_str = 'group';
        }
        else {
            $db   = SQL::get_instance();
            $_key = $db->fetch_assoc($db->query("SELECT `key` FROM group_types WHERE id = ?", $typeid));
            $type_str = $_key['key'];
        }
*/
        // Group identifier
        $unique_attr = $this->unique_attribute();
        $attributes[$unique_attr] = $group;

        // Now that values have been re-generated where necessary, compare
        // the new group attributes to the original group attributes.
        $_group = $this->entry_find_by_attribute(array($unique_attr => $attributes[$unique_attr]));

        if (!$_group) {
            //console("Could not find group");
            return false;
        }

        $_group_dn = key($_group);
        $_group = $this->group_info($_group_dn, array_keys($attributes));

        // We should start throwing stuff over the fence here.
        return $this->modify_entry($_group_dn, $_group[$_group_dn], $attributes);
    }

    public function group_find_by_attribute($attribute)
    {
        return $this->entry_find_by_attribute($attribute);
    }

    public function group_info($group, $attributes = array('*'))
    {
        $group_dn = $this->entry_dn($group);

        if (!$group_dn) {
            return false;
        }

        return self::normalize_result($this->_search($group_dn, '(objectclass=*)', $attributes));
    }

    public function group_members_list($group, $recurse = true)
    {
        $group_dn = $this->entry_dn($group);

        if (!$group_dn) {
            return false;
        }

        return $this->_list_group_members($group_dn, null, $recurse);
    }

    public function list_domains()
    {
        $domains = $this->domains_list();
        $domains = self::normalize_result($domains);

        return $domains;
    }

    public function list_groups($attributes = array(), $search = array(), $params = array())
    {
        if (!empty($params['sort_by'])) {
            if (!in_array($params['sort_by'], $attributes)) {
                $attributes[] = $params['sort_by'];
            }
        }

        $groups = $this->groups_list($attributes, $search);
        $groups = self::normalize_result($groups);

        if (!empty($params['sort_by'])) {
            $this->sort_result_key = $params['sort_by'];
            uasort($groups, array($this, 'sort_result'));

            if ($params['sort_order'] == 'DESC') {
                $groups = array_reverse($groups, true);
            }
        }

        return $groups;
    }

    public function list_users($attributes = array(), $search = array(), $params = array())
    {
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

        $users = $this->users_list($attributes, $search);
        $users = self::normalize_result($users);

        if (!empty($params['sort_by'])) {
            $this->sort_result_key = $params['sort_by'];
            uasort($users, array($this, 'sort_result'));

            if ($params['sort_order'] == 'DESC') {
                $users = array_reverse($users, true);
            }
        }

        return $users;
    }

    public function list_resources($attributes = array(), $search = array(), $params = array())
    {
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

        $resources = $this->resources_list($attributes, $search);
        $resources = self::normalize_result($resources);

        if (!empty($params['sort_by'])) {
            $this->sort_result_key = $params['sort_by'];
            uasort($resources, array($this, 'sort_result'));

            if ($params['sort_order'] == 'DESC') {
                $resources = array_reverse($resources, true);
            }
        }

        return $resources;
    }

    public function list_roles($attributes = array(), $search = array(), $params = array())
    {
        if (!empty($params['sort_by'])) {
            if (!in_array($params['sort_by'], $attributes)) {
                $attributes[] = $params['sort_by'];
            }
        }

        $roles = $this->roles_list($attributes, $search);
        $roles = self::normalize_result($roles);

        if (!empty($params['sort_by'])) {
            $this->sort_result_key = $params['sort_by'];
            uasort($roles, array($this, 'sort_result'));

            if ($params['sort_order'] == 'DESC') {
                $roles = array_reverse($roles, true);
            }
        }

        return $roles;
    }

    public function resource_add($attrs, $typeid = null)
    {
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
        if (!$base_dn)
            $base_dn = $this->conf->get("resource_base_dn");

        // TODO: The rdn is configurable as well.
        // Use [$type_str . "_"]user_rdn_attr
        $dn = "cn=" . $attrs['cn'] . "," . $base_dn;

        return $this->_add($dn, $attrs);
    }

    public function resource_delete($resource)
    {
        $resource_dn = $this->entry_dn($resource);

        if (!$resource_dn) {
            return false;
        }

        return $this->_delete($resource_dn);
    }

    public function resource_edit($resource, $attributes, $typeid = null)
    {
/*
        // Get the type "key" string for the next few settings.
        if ($typeid == null) {
            $type_str = 'resource';
        }
        else {
            $db   = SQL::get_instance();
            $_key = $db->fetch_assoc($db->query("SELECT `key` FROM resource_types WHERE id = ?", $typeid));
            $type_str = $_key['key'];
        }
*/
        // Group identifier
        $unique_attr = $this->unique_attribute();
        $attributes[$unique_attr] = $resource;

        // Now that values have been re-generated where necessary, compare
        // the new resource attributes to the original resource attributes.
        $_resource = $this->entry_find_by_attribute(array($unique_attr => $attributes[$unique_attr]));

        if (!$_resource) {
            //console("Could not find resource");
            return false;
        }

        $_resource_dn = key($_resource);
        $_resource = $this->resource_info($_resource_dn, array_keys($attributes));

        // We should start throwing stuff over the fence here.
        return $this->modify_entry($_resource_dn, $_resource[$_resource_dn], $attributes);
    }

    public function resource_find_by_attribute($attribute)
    {
        return $this->entry_find_by_attribute($attribute);
    }

    /**
     * Resource attributes
     *
     *
     */
    public function resource_info($resource, $attributes = array('*'))
    {
        $resource_dn = $this->entry_dn($resource);

        if (!$resource_dn)
            return false;

        return self::normalize_result($this->_search($resource_dn, '(objectclass=*)', $attributes));
    }

    public function resource_members_list($resource, $recurse = true)
    {
        $resource_dn = $this->entry_dn($resource);

        if (!$resource_dn) {
            return false;
        }

        return $this->_list_resource_members($resource_dn, null, $recurse);
    }

    public function user_add($attrs, $typeid = null)
    {
        if ($typeid == null) {
            $type_str = 'user';
        }
        else {
            $db   = SQL::get_instance();
            $_key = $db->fetch_assoc($db->query("SELECT `key` FROM user_types WHERE id = ?", $typeid));
            $type_str = $_key['key'];
        }

        // Check if the user_type has a specific base DN specified.
        $base_dn = $this->conf->get($this->domain, $type_str . "_user_base_dn");
        // If not, take the regular user_base_dn
        if (empty($base_dn))
            $base_dn = $this->conf->get($this->domain, "user_base_dn");

        // If no user_base_dn either, take the user type specific from the parent
        // configuration
        if (empty($base_dn))
            $base_dn = $this->conf->get('ldap', $type_str . "_user_base_dn");

        // If still no base dn to add the user to... use the toplevel dn
        if (empty($base_dn))
            $base_dn = $this->conf->get($this->domain, "base_dn");
        if (empty($base_dn))
            $base_dn = $this->conf->get('ldap', "base_dn");

        if (!empty($attrs['ou'])) {
            $base_dn = $attrs['ou'];
        }

        //console("Base DN now: $base_dn");

        // TODO: The rdn is configurable as well.
        // Use [$type_str . "_"]user_rdn_attr
        $dn = "uid=" . $attrs['uid'] . "," . $base_dn;

        return $this->_add($dn, $attrs);
    }

    public function user_edit($user, $attributes, $typeid = null)
    {
/*
        // Get the type "key" string for the next few settings.
        if ($typeid == null) {
            $type_str = 'user';
        }
        else {
            $db   = SQL::get_instance();
            $_key = $db->fetch_assoc($db->query("SELECT `key` FROM user_types WHERE id = ?", $typeid));
            $type_str = $_key['key'];
        }
*/
        $unique_attr = $this->unique_attribute();
        $attributes[$unique_attr] = $user;

        // Now that values have been re-generated where necessary, compare
        // the new group attributes to the original group attributes.
        $_user = $this->entry_find_by_attribute(array($unique_attr => $attributes[$unique_attr]));

        if (!$_user) {
            //console("Could not find user");
            return false;
        }

        $_user_dn = key($_user);
        $_user = $this->user_info($_user_dn, array_keys($attributes));

        //console("Auth::LDAP::user_edit() existing \$_user info", $_user);

        // We should start throwing stuff over the fence here.
        return $this->modify_entry($_user_dn, $_user[$_user_dn], $attributes);
    }

    public function user_delete($user)
    {
        $user_dn = $this->entry_dn($user);

        if (!$user_dn) {
            return false;
        }

        return $this->_delete($user_dn);
    }

    /**
     * User attributes
     *
     *
     */
    public function user_info($user, $attributes = array('*'))
    {
        $user_dn = $this->entry_dn($user);

        if (!$user_dn)
            return false;

        return self::normalize_result($this->_search($user_dn, '(objectclass=*)', $attributes));
    }

    public function user_find_by_attribute($attribute)
    {
        return $this->entry_find_by_attribute($attribute);
    }

    /*
        Translate a domain name into it's corresponding root dn.
    */
    private function domain_root_dn($domain = '')
    {
        $conf = Conf::get_instance();

        if ($domain == '') {
            return false;
        }

        if (!$this->_connect()) {
            return false;
        }

        error_log("Searching for domain $domain");
        error_log("From domain to root dn");

        if (($this->_bind($conf->get('ldap', 'bind_dn'), $conf->get('ldap', 'bind_pw'))) == false) {
            error_log("WARNING: Invalid Service bind credentials supplied");
            $this->_bind($conf->manager_bind_dn, $conf->manager_bind_pw);
        }

        // TODO: Get domain_attr from config
        $results = ldap_search($this->conn, $conf->get('domain_base_dn'), '(associatedDomain=' . $domain . ')');

        if (!$result) {
            // Not a multi-domain setup
            $domain_name = $conf->get('kolab', 'primary_domain');
            return $this->_standard_root_dn($domain_name);
        }

        $domain = ldap_first_entry($this->conn, $results);
        $domain_info = ldap_get_attributes($this->conn, $domain);

//        echo "<pre>"; print_r($domain_info); echo "</pre>";

        // TODO: Also very 389 specific
        if (isset($domain_info['inetDomainBaseDN'][0])) {
            $domain_rootdn = $domain_info['inetDomainBaseDN'][0];
        }
        else {
            $domain_rootdn = $this->_standard_root_dn($domain_info['associatedDomain']);
        }

        $this->_unbind();

        error_log("Using $domain_rootdn");

        return $domain_rootdn;
    }

    public function search($base_dn, $search_filter = '(objectClass=*)', $attributes = array('*'))
    {
        //console("Auth::LDAP::search", $base_dn);

        // We may have been passed on func_get_arg()
        if (is_array($base_dn)) {
            $_base_dn = array_shift($base_dn);

            if (count($base_dn) > 0) {
                $search_filter = array_shift($base_dn);
            } else {
                $search_filter = '(objectclass=*)';
            }

            if (count($base_dn) > 0) {
                $attributes = array_shift($base_dn);
            } else {
                $attributes = array('*');
            }
        } else {
            $_base_dn = $base_dn;
        }

        $result = self::normalize_result($this->__search($_base_dn, $search_filter, $attributes));
        $result = array_keys($result);
        //console($result);

        return $result;
    }

    private function domains_list()
    {
        $section = $this->conf->get('kolab', 'auth_mechanism');
        $base_dn = $this->conf->get($section, 'domain_base_dn');
        $filter  = $this->conf->get($section, 'domain_filter');

        $kolab_filter = $this->conf->get($section, 'kolab_domain_filter');
        if (empty($filter) && !empty($kolab_filter)) {
            $filter = $kolab_filter;
        }

        return $this->_search($base_dn, $filter);
    }

    private function entry_dn($subject)
    {
        //console("entry_dn on subject $subject");
        $is_dn = ldap_explode_dn($subject, 1);
        //console($is_dn);

        if (is_array($is_dn) && array_key_exists("count", $is_dn) && $is_dn["count"] > 0) {
            return $subject;
        }

        $unique_attr = $this->unique_attribute();
        $subject     = $this->entry_find_by_attribute(array($unique_attr => $subject));

        if (!empty($subject)) {
            return key($subject);
        }
    }

    private function entry_find_by_attribute($attribute, $base_dn = null)
    {
        if (empty($attribute) || !is_array($attribute) || count($attribute) > 1) {
            return false;
        }

        if (empty($attribute[key($attribute)])) {
            return false;
        }

        $filter = "(&";

        foreach ($attribute as $key => $value) {
            $filter .= "(" . $key . "=" . $value . ")";
        }

        $filter .= ")";

        if (empty($base_dn)) {
            $base_dn = $this->domain_root_dn($this->domain);
        }

        $result = self::normalize_result($this->_search($base_dn, $filter, array_keys($attribute)));

        if (count($result) > 0) {
            error_log("Results found: " . implode(', ', array_keys($result)));
            return $result;
        }
        else {
            error_log("No result");
            return false;
        }
    }

    private function groups_list($attributes = array(), $search = array())
    {
        $conf = Conf::get_instance();

        $base_dn = $conf->get('group_base_dn');

        if (!$base_dn)
            $base_dn = $conf->get('base_dn');

        $filter  = $conf->get('group_filter');

        if (empty($attributes) || !is_array($attributes)) {
            $attributes = array('*');
        }

        if ($s_filter = $this->_search_filter($search)) {
            // join search filter with objectClass filter
            $filter = '(&' . $filter . $s_filter . ')';
        }

        return $this->_search($base_dn, $filter, $attributes);
    }

    private function init_schema()
    {
        $conf = Conf::get_instance();

        $this->_ldap_uri    = $this->conf->get('ldap_uri');
        $this->_ldap_server = parse_url($this->_ldap_uri, PHP_URL_HOST);
        $this->_ldap_port   = parse_url($this->_ldap_uri, PHP_URL_PORT);
        $this->_ldap_scheme = parse_url($this->_ldap_uri, PHP_URL_SCHEME);

        require_once("Net/LDAP2.php");

        $_ldap_cfg = Array(
                'host' => $this->_ldap_server,
                'port' => $this->_ldap_port,
                'tls' => false,
                'version' => 3,
                'binddn' => $conf->get('bind_dn'),
                'bindpw' => $conf->get('bind_pw')
            );

        $_ldap_schema_cache_cfg = Array(
                'path' => "/tmp/" . $this->_ldap_server . ":" . ($this->_ldap_port ? $this->_ldap_port : '389') . "-Net_LDAP2_Schema.cache",
                'max_age' => 86400,
            );

        $_ldap_schema_cache = new Net_LDAP2_SimpleFileSchemaCache($_ldap_schema_cache_cfg);

        $_ldap = Net_LDAP2::connect($_ldap_cfg);

        $result = $_ldap->registerSchemaCache($_ldap_schema_cache);

        // TODO: We should learn what LDAP tech. we're running against.
        // Perhaps with a scope base objectclass recognize rootdse entry
        $schema_root_dn = $conf->get('schema_root_dn');
        if (!$schema_root_dn) {
            $_schema = $_ldap->schema();
        }

        return $_schema;
    }

    private function legacy_rights($subject)
    {
        $subject_dn = $this->entry_dn($subject);

        $user_is_admin = false;
        $user_is_self = false;

        // List group memberships
        $user_groups = $this->find_user_groups($_SESSION['user']->user_bind_dn);
        //console("User's groups", $user_groups);

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

        $subject    = self::normalize_result($this->_search($subject_dn));

        $attributes = $this->allowed_attributes($subject[$subject_dn]['objectclass']);
        $attributes = array_merge($attributes['may'], $attributes['must']);

        foreach ($attributes as $attribute) {
            $rights['attributeLevelRights'][$attribute] = $standard_rights;
        }

        return $rights;
    }

    private function modify_entry($subject_dn, $old_attrs, $new_attrs)
    {
        //console("OLD ATTRIBUTES", $old_attrs);
        //console("NEW ATTRIBUTES", $new_attrs);

        // TODO: Get $rdn_attr - we have type_id in $new_attrs
        $dn_components = ldap_explode_dn($subject_dn, 0);
        $rdn_components = explode('=', $dn_components[0]);

        $rdn_attr = $rdn_components[0];

        //console("Auth::LDAP::modify_entry() using rdn attribute: " . $rdn_attr);

        $mod_array = Array(
                "add"       => Array(), // For use with ldap_mod_add()
                "del"       => Array(), // For use with ldap_mod_del()
                "replace"   => Array(), // For use with ldap_mod_replace()
                "rename"    => Array(), // For use with ldap_rename()
            );

        // This is me cheating. Remove this special attribute.
        if (array_key_exists('ou', $old_attrs) || array_key_exists('ou', $new_attrs)) {
            $old_ou = $old_attrs['ou'];
            $new_ou = $new_attrs['ou'];
            unset($old_attrs['ou']);
            unset($new_attrs['ou']);
        } else {
            $old_ou = null;
            $new_ou = null;
        }

        // Compare each attribute value of the old attrs with the corresponding value
        // in the new attrs, if any.
        foreach ($old_attrs as $attr => $old_attr_value) {

            if (array_key_exists($attr, $new_attrs)) {
                if (is_array($old_attrs[$attr]) && is_array($new_attrs[$attr])) {
                    $_sort1 = $new_attrs[$attr];
                    sort($_sort1);
                    $_sort2 = $old_attr_value;
                    sort($_sort2);
                } else {
                    $_sort1 = true;
                    $_sort2 = false;
                }

                if (!($new_attrs[$attr] === $old_attr_value) && !($_sort1 === $_sort2)) {
                    //console("Attribute $attr changed from", $old_attr_value, "to", $new_attrs[$attr]);
                    if ($attr === $rdn_attr) {
                        //console("This attribute is the RDN attribute. Let's see if it is multi-valued, and if the original still exists in the new value.");
                        if (is_array($old_attrs[$attr])) {
                            if (!is_array($new_attrs[$attr])) {
                                if (in_array($new_attrs[$attr], $old_attrs[$attr])) {
                                    // TODO: Need to remove all $old_attrs[$attr] values not equal to $new_attrs[$attr], and not equal to the current $rdn_attr value [0]

                                    //console("old attrs. is array, new attrs. is not array. new attr. exists in old attrs.");

                                    $rdn_attr_value = array_shift($old_attrs[$attr]);
                                    $_attr_to_remove = array();

                                    foreach ($old_attrs[$attr] as $value) {
                                        if (strtolower($value) != strtolower($new_attrs[$attr])) {
                                            $_attr_to_remove[] = $value;
                                        }
                                    }

                                    //console("Adding to delete attribute $attr values:" . implode(', ', $_attr_to_remove));

                                    $mod_array['delete'][$attr] = $_attr_to_remove;

                                    if (strtolower($new_attrs[$attr]) !== strtolower($rdn_attr_value)) {
                                        //console("new attrs is not the same as the old rdn value, issuing a rename");
                                        $mod_array['rename']['dn'] = $subject_dn;
                                        $mod_array['rename']['new_rdn'] = $rdn_attr . '=' . $new_attrs[$attr][0];
                                    }

                                } else {
                                    //console("new attrs is not the same as any of the old rdn value, issuing a full rename");
                                    $mod_array['rename']['dn'] = $subject_dn;
                                    $mod_array['rename']['new_rdn'] = $rdn_attr . '=' . $new_attrs[$attr];
                                }
                            } else {
                                // TODO: See if the rdn attr. value is still in $new_attrs[$attr]
                                if (in_array($old_attrs[$attr][0], $new_attrs[$attr])) {
                                    //console("Simply replacing attr $attr as rnd attr value is preserved.");
                                    $mod_array['replace'][$attr] = $new_attrs[$attr];
                                } else {
                                    // TODO: This fails.
                                    $mod_array['rename']['dn'] = $subject_dn;
                                    $mod_array['rename']['new_rdn'] = $rdn_attr . '=' . $new_attrs[$attr][0];
                                    $mod_array['delete'][$attr] = $old_attrs[$attr][0];
                                }
                            }
                        } else {
                            if (!is_array($new_attrs[$attr])) {
                                //console("Renaming " . $old_attrs[$attr] . " to " . $new_attrs[$attr]);
                                $mod_array['rename']['dn'] = $subject_dn;
                                $mod_array['rename']['new_rdn'] = $rdn_attr . '=' . $new_attrs[$attr];
                            } else {
                                //console("Adding to replace");
                                // An additional attribute value is being supplied. Just replace and continue.
                                $mod_array['replace'][$attr] = $new_attrs[$attr];
                                continue;
                            }
                        }

                    } else {
                        if (empty($new_attrs[$attr])) {
                            switch ($attr) {
                                case "userpassword":
                                    break;
                                default:
                                    //console("Adding to del: $attr");
                                    $mod_array['del'][$attr] = (array)($old_attr_value);
                                    break;
                            }
                        } else {
                            //console("Adding to replace: $attr");
                            $mod_array['replace'][$attr] = (array)($new_attrs[$attr]);
                        }
                    }
                } else {
                    //console("Attribute $attr unchanged");
                }
            } else {
                // TODO: Since we're not shipping the entire object back and forth, and only post
                // part of the data... we don't know what is actually removed (think modifiedtimestamp, etc.)
                //console("Group attribute $attr not mentioned in \$new_attrs..., but not explicitly removed... by assumption");
            }
        }

        foreach ($new_attrs as $attr => $value) {
            if (array_key_exists($attr, $old_attrs)) {
                if (empty($value)) {
                    if (!array_key_exists($attr, $mod_array['del'])) {
                        switch ($attr) {
                            case "userpassword":
                                break;
                            default:
                                //console("Adding to del(2): $attr");
                                $mod_array['del'][$attr] = (array)($old_attrs[$attr]);
                                break;
                        }
                    }
                } else {
                    if (!($old_attrs[$attr] === $value) && !($attr === $rdn_attr)) {
                        if (!array_key_exists($attr, $mod_array['replace'])) {
                            //console("Adding to replace(2): $attr");
                            $mod_array['replace'][$attr] = $value;
                        }
                    }
                }
            } else {
                if (!empty($value)) {
                    $mod_array['add'][$attr] = $value;
                }
            }
        }

        if (empty($old_ou)) {
            $subject_dn_components = ldap_explode_dn($subject_dn, 0);
            unset($subject_dn_components["count"]);
            $subject_rdn = array_shift($subject_dn_components);
            $old_ou = implode(',', $subject_dn_components);
        }

        if (!(empty($old_ou) || empty($new_ou)) && !(strtolower($old_ou) === strtolower($new_ou))) {
            $mod_array['rename']['new_parent'] = $new_ou;
            if (empty($mod_array['rename']['dn']) || empty($mod_array['rename']['new_rdn'])) {
                $mod_array['rename']['dn'] = $subject_dn;
                $mod_array['rename']['new_rdn'] = $rdn_attr . '=' . $new_attrs[$rdn_attr];
            }
        }

        //console($mod_array);

        $result = $this->modify_entry_attributes($subject_dn, $mod_array);

        if ($result) {
            return $mod_array;
        }

    }

    private function modify_entry_attributes($subject_dn, $attributes)
    {
        $this->_bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        //console($attributes);

        // Opportunities to set false include failed ldap commands.
        $result = true;

        if (is_array($attributes['rename']) && !empty($attributes['rename'])) {
            $olddn = $attributes['rename']['dn'];
            $newrdn = $attributes['rename']['new_rdn'];
            if (!empty($attributes['rename']['new_parent'])) {
                $new_parent = $attributes['rename']['new_parent'];
            } else {
                $new_parent = null;
            }

            //console("Attempt to rename $olddn to $newrdn,$new_parent");

            $result = ldap_rename($this->conn, $olddn, $newrdn, $new_parent, true);
            if ($result) {
                if ($new_parent) {
                    $subject_dn = $newrdn . ',' . $new_parent;
                } else {
                    $old_parent_dn_components = ldap_explode_dn($olddn, 0);
                    unset($old_parent_dn_components["count"]);
                    $old_rdn = array_shift($old_parent_dn_components);
                    $old_parent_dn = implode(",", $old_parent_dn_components);
                    $subject_dn = $newrdn . ',' . $old_parent_dn;
                }
            }

        }

        if (is_array($attributes['replace']) && !empty($attributes['replace'])) {
            $result = ldap_mod_replace($this->conn, $subject_dn, $attributes['replace']);
        }

        if (!$result) {
            //console("Failed to replace the following attributes on subject " . $subject_dn, $attributes['replace']);
            return false;
        }

        if (is_array($attributes['del']) && !empty($attributes['del'])) {
            $result = ldap_mod_del($this->conn, $subject_dn, $attributes['del']);
        }

        if (!$result) {
            //console("Failed to delete the following attributes", $attributes['del']);
            return false;
        }


        if (is_array($attributes['add']) && !empty($attributes['add'])) {
            $result = ldap_mod_add($this->conn, $subject_dn, $attributes['add']);
        }

        if (!$result) {
            //console("Failed to add the following attributes", $attributes['add']);
            return false;
        }

        if (!$result) {
            error_log("LDAP Error: " . $this->_errstr());
            return false;
        }

        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    private function parse_attribute_level_rights($attribute_value)
    {
        $attribute_value = str_replace(", ", ",", $attribute_value);
        $attribute_values = explode(",", $attribute_value);

        $attribute_value = array();

        foreach ($attribute_values as $access_right) {
            $access_right_components = explode(":", $access_right);
            $access_attribute = strtolower(array_shift($access_right_components));
            $access_value = array_shift($access_right_components);

            $attribute_value[$access_attribute] = array();

            for ($i = 0; $i < strlen($access_value); $i++) {
                $method = $this->attribute_level_rights_map[substr($access_value, $i, 1)];

                if (!in_array($method, $attribute_value[$access_attribute])) {
                    $attribute_value[$access_attribute][] = $method;
                }
            }
        }

        return $attribute_value;
    }

    private function parse_entry_level_rights($attribute_value)
    {
        $_attribute_value = array();

        for ($i = 0; $i < strlen($attribute_value); $i++) {
            $method = $this->entry_level_rights_map[substr($attribute_value, $i, 1)];

            if (!in_array($method, $_attribute_value)) {
                $_attribute_value[] = $method;
            }
        }

        return $_attribute_value;
    }

    private function roles_list($attributes = array(), $search = array())
    {
        $conf = Conf::get_instance();

        $base_dn = $conf->get('base_dn');
        // TODO: From config
        $filter  = "(&(objectclass=ldapsubentry)(objectclass=nsroledefinition))";

        if (empty($attributes) || !is_array($attributes)) {
            $attributes = array('*');
        }

        if ($s_filter = $this->_search_filter($search)) {
            // join search filter with objectClass filter
            $filter = '(&' . $filter . $s_filter . ')';
        }

        return $this->_search($base_dn, $filter, $attributes);
    }

    private function supported_controls()
    {
        $conf = Conf::get_instance();

        $this->_bind($conf->get('bind_dn'), $conf->get('bind_pw'));

        $result = ldap_read($this->conn, "", "(objectclass=*)", array("supportedControl"));
        $result = ldap_get_entries($this->conn, $result);
        $result = self::normalize_result($result);

        return $result['']['supportedcontrol'];
    }

    private function resources_list($attributes = array(), $search = array())
    {
        $conf = Conf::get_instance();

        $base_dn = $conf->get('resource_base_dn');

        if (!$base_dn)
            $base_dn = "ou=Resources," . $conf->get('base_dn');

        $filter  = $conf->get('resource_filter');
        if (!$filter)
            $filter = '(objectclass=*)';

        if (empty($attributes) || !is_array($attributes)) {
            $attributes = array('*');
        }

        if ($s_filter = $this->_search_filter($search)) {
            // join search filter with objectClass filter
            $filter = '(&' . $filter . $s_filter . ')';
        }

        return $this->_search($base_dn, $filter, $attributes);
    }


    private function users_list($attributes = array(), $search = array())
    {
        $conf = Conf::get_instance();

        $base_dn = $conf->get('user_base_dn');

        if (!$base_dn)
            $base_dn = $conf->get('base_dn');

        $filter  = $conf->get('user_filter');

        if (empty($attributes) || !is_array($attributes)) {
            $attributes = array('*');
        }

        if ($s_filter = $this->_search_filter($search)) {
            // join search filter with objectClass filter
            $filter = '(&' . $filter . $s_filter . ')';
        }

        return $this->_search($base_dn, $filter, $attributes);
    }

    public static function normalize_result($__result)
    {
        if (!is_array($__result)) {
            return array();
        }

        $conf = Conf::get_instance();

        $dn_attr = $conf->get($conf->get('kolab', 'auth_mechanism'), 'domain_name_attribute');
        $result  = array();

        for ($x = 0; $x < $__result["count"]; $x++) {
            $dn = $__result[$x]['dn'];
            $result[$dn] = array();
            for ($y = 0; $y < $__result[$x]["count"]; $y++) {
                $attr = $__result[$x][$y];
                if ($__result[$x][$attr]["count"] == 1) {
                    switch ($attr) {
                        case "objectclass":
                            $result[$dn][$attr] = array(strtolower($__result[$x][$attr][0]));
                            break;
                        default:
                            $result[$dn][$attr] = $__result[$x][$attr][0];
                            break;
                    }
                }
                else {
                    $result[$dn][$attr] = array();
                    for ($z = 0; $z < $__result[$x][$attr]["count"]; $z++) {
                        // The first result in the array is the primary domain.
                        if ($z == 0 && $attr == $dn_attr) {
                            $result[$dn]['primary_domain'] = $__result[$x][$attr][$z];
                        }

                        switch ($attr) {
                            case "objectclass":
                                $result[$dn][$attr][] = strtolower($__result[$x][$attr][$z]);
                                break;
                            default:
                                $result[$dn][$attr][] = $__result[$x][$attr][$z];
                                break;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Result sorting callback for uasort()
     */
    public function sort_result($a, $b)
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
        $conf = Conf::get_instance();

        $username_parts = explode('@', $username);
        if (count($username_parts) == 1) {
            $domain_name = $conf->get('primary_domain');
        }
        else {
            $domain_name = array_pop($username_parts);
        }

        return array(implode('@', $username_parts), $domain_name);
    }

    public function user_type_attribute_filter($type = false)
    {
        global $conf;

        // If the user type does not exist, issue warning and continue with
        // the "All attributes" array.
        if (!isset($conf->user_types[$type])) {
            return array('*');
        }

        $attributes_filter = array();

        foreach ($conf->user_types[$type]['attributes'] as $key => $value) {
            $attributes_filter[] = is_array($value) ? $key : $value;
        }

        return $attributes_filter;
    }

    public function user_type_search_filter($type = false)
    {
        global $conf;

        // TODO: If the user type has not been specified we should actually
        // iterate and mix and match:
        //
        // (|(&(type1))(&(type2)))

        // If the user type does not exist, issue warning and continue with
        // the "All" search filter.
        if (!isset($conf->user_types[$type])) {
            return "(objectClass=*)";
        }

        $search_filter = "(&";
        // We want from user_types[$type]['attributes']['objectClasses']
        foreach ($conf->user_types[$type]['attributes']['objectClass'] as $key => $value) {
            $search_filter .= "(objectClass=" . $value . ")";
        }

        $search_filter .= ")";

        print "<li>" . $search_filter;

        return $search_filter;
    }

    /***********************************************************
     ************      Shortcut functions       ****************
     ***********************************************************/

    /*
        Shortcut to ldap_add()
    */
    private function _add($entry_dn, $attributes)
    {
        // Always bind with the session credentials
        $this->_bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        //console("Entry DN", $entry_dn);
        //console("Attributes", $attributes);

        foreach ($attributes as $attr_name => $attr_value) {
            if (empty($attr_value)) {
                unset($attributes[$attr_name]);
            }
        }

        if (($add_result = ldap_add($this->conn, $entry_dn, $attributes)) == false) {
            // Issue warning
            return false;
        }

        return true;
    }

    private function _domain_add_alias($domain, $parent)
    {
        $conf = Conf::get_instance();
        $domain_base_dn = $conf->get('ldap', 'domain_base_dn');
        $domain_filter = $conf->get('ldap', 'domain_filter');

        $domain_name_attribute = $conf->get('ldap', 'domain_name_attribute');

        $domain_filter = '(&(' . $domain_name_attribute . '=' . $parent . ')' . $domain_filter . ')';

        $domain_entry = self::normalize_result($this->_search($domain_base_dn, $domain_filter));

        // TODO: Catch not having found any such parent domain

        $domain_dn = key($domain_entry);

        //    private function modify_entry($subject_dn, $old_attrs, $new_attrs)

        $_old_attr = array($domain_name_attribute => $domain_entry[$domain_dn][$domain_name_attribute]);
        $_new_attr = array($domain_name_attribute => array($domain_entry[$domain_dn][$domain_name_attribute], $domain));

        return $this->modify_entry($domain_dn, $_old_attr, $_new_attr);



    }

    /**
     * Shortcut to ldap_bind()
     */
    private function _bind($dn, $pw)
    {
        $this->_connect();

        if (!$this->conn || !$dn || !$pw) {
            return false;
        }

        if ($dn == $this->bind_dn && $pw == $this->bind_pw) {
            return true;
        }

        // TODO: Debug logging
        error_log("->_bind() Binding with $dn");

        $this->bind_dn = $dn;
        $this->bind_pw = $pw;

        if (($bind_ok = ldap_bind($this->conn, $dn, $pw)) == false) {
            error_log("LDAP Error: " . $this->_errstr());
            // Issue error message
            return false;
        }

        return true;
    }

    /**
     * Shortcut to ldap_connect()
     */
    private function _connect()
    {
        if ($this->conn) {
            return true;
        }

        ldap_set_option(NULL, LDAP_OPT_DEBUG_LEVEL, 9);

        // TODO: Debug logging
        error_log("Connecting to " . $this->_ldap_server . " on port " . $this->_ldap_port);
        $connection = ldap_connect($this->_ldap_server, $this->_ldap_port);

        if ($connection == false) {
            $this->conn = null;
            // TODO: Debug logging
            error_log("Not connected: " . ldap_err2str() .  "(no.) " . ldap_errno());
            return false;
        }

        $this->conn = $connection;

        ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, 3);

        // TODO: Debug logging
        error_log("Connected!");

        return true;
    }

    /**
     *   Shortcut to ldap_delete()
     */
    private function _delete($entry_dn)
    {
        // Always bind with the session credentials
        $this->_bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        if (($delete_result = ldap_delete($this->conn, $entry_dn)) == false) {
            // Issue warning
            return false;
        }
        else {
            return true;
        }
    }

    /**
     * Shortcut to ldap_disconnect()
     */
    private function _disconnect()
    {
        if (!$this->conn) {
            return true;
        }

        if (($result = ldap_close($this->conn)) == true) {
            $this->conn = null;
            $this->bind_dn = null;
            $this->bind_pw = null;

            return true;
        }

        return false;
    }

    /**
     * Shortcut to ldap_err2str() over ldap_errno()
     */
    private function _errstr()
    {
        if ($errno = @ldap_errno($this->conn)) {
            if ($err2str = @ldap_err2str($errno)) {
                return $err2str;
            }
        }

        // Issue warning
        return null;
    }

    /**
     * Shortcut to ldap_get_entries() over ldap_list()
     *
     * Takes a $base_dn and $filter like ldap_list(), and returns an
     * array obtained through ldap_get_entries().
     */
    private function _list($base_dn, $filter)
    {
        if (!$this->conn) {
            return null;
        }

        $ldap_entries = array( "count" => 0 );

        if (($ldap_list = @ldap_list($this->conn, $base_dn, $filter)) == false) {
            //message("LDAP Error: Could not search " . $base_dn . ": " . $this->_errstr() );
        }
        else {
            if (($ldap_entries = @ldap_get_entries($this->conn, $ldap_list)) == false) {
                //message("LDAP Error: No entries for " . $filter . " in " . $base_dn . ": " . $this->_errstr());
            }
        }

        return $ldap_entries;
    }

    private function _search($base_dn, $search_filter = '(objectClass=*)', $attributes = array('*'))
    {
        return $this->__search($base_dn, $search_filter, $attributes);
    }

    /**
     * Shortcut to ldap_search()
     */
    private function __search($base_dn, $search_filter = '(objectClass=*)', $attributes = array('*'))
    {
        if (!$this->_connect()) {
            return false;
        }

        $attributes = (array)($attributes);

        //console("Searching $base_dn with filter: $search_filter, attempting to get attributes", $attributes);

        $this->_bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        if (!in_array($this->unique_attribute(), $attributes)) {
            $attributes[] = $this->unique_attribute();
        }

        if (($search_results = @ldap_search($this->conn, $base_dn, $search_filter, $attributes)) == false) {
            //console("Could not search in " . __METHOD__ . " in " . __FILE__ . " on line " . __LINE__ . ": " . $this->_errstr());
            return false;
        }

        if (($entries = ldap_get_entries($this->conn, $search_results)) == false) {
            //console("Could not get the results of the search: " . $this->_errstr());
            return false;
        }

        //console("__search() entries:", $entries);

        return $entries;
    }

    /**
     * Create LDAP search filter string according to defined parameters.
     */
    private function _search_filter($search)
    {
        if (empty($search) || !is_array($search) || empty($search['params'])) {
            return null;
        }

        $filter = '';
        foreach ((array) $search['params'] as $field => $param) {
            switch ((string)$param['type']) {
            case 'prefix':
                $prefix = '';
                $suffix = '*';
                break;
            case 'suffix':
                $prefix = '*';
                $suffix = '';
                break;
            case 'exact':
                $prefix = '';
                $suffix = '';
                break;
            case 'both':
            default:
                $prefix = '*';
                $suffix = '*';
                break;
            }

            if (is_array($param['value'])) {
                $val_filter = array();
                foreach ($param['value'] as $val) {
                    $value = self::_quote_string($val);
                    $val_filter[] = "($field=$prefix" . $value . "$suffix)";
                }
                $filter .= "(|" . implode($val_filter, '') . ")";
            }
            else {
                $value = self::_quote_string($param['value']);
                $filter .= "($field=$prefix" . $value . "$suffix)";
            }
        }

        // join search parameters with specified operator ('OR' or 'AND')
        if (count($search['params']) > 1) {
            $filter = '(' . ($search['operator'] == 'AND' ? '&' : '|') . $filter . ')';
        }

        return $filter;
    }

    /**
     * Shortcut to ldap_unbind()
     */
    private function _unbind($yes = false, $really = false)
    {
        if ($yes && $really) {
            if ($this->conn) {
                ldap_unbind($this->conn);
            }

            $this->conn    = null;
            $this->bind_dn = null;
            $this->bind_pw = null;
        }
        else {
            // What?
            //
            // - attempt bind as anonymous
            // - in case of fail, bind as user
        }

        return true;
    }

    /*
        Utility functions
     */

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
        error_log("Running for entry root dn: " . $entry_root_dn);
        if (($tmpconn = ldap_connect($this->_ldap_server)) == false) {
            //message("LDAP Error: " . $this->_errstr());
            return false;
        }

        error_log("User DN: " . $_SESSION['user']->user_bind_dn);

        if (($bind_success = ldap_bind($tmpconn, $_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw)) == false) {
            //message("LDAP Error: " . $this->_errstr());
            return false;
        }

        if (($list_success = ldap_list($tmpconn, $entry_root_dn, '(objectClass=*)', array('*', 'aci'))) == false) {
            //message("LDAP Error: " . $this->_errstr());
            return false;
        }

//        print_r(ldap_get_entries($tmpconn, $list_success));
/*
        if (ldap_count_entries($tmpconn, $list_success) == 0) {
            echo "<li>Listed things, but got no results";
            return false;
        }
*/
        return true;
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

    // @TODO: this function isn't used anymore
    private function _get_group_dn($root_dn, $search_filter)
    {
        // TODO: Why does this use privileged credentials?
        if (($this->_bind($this->conf->get('bind_dn'), $this->conf->get('bind_pw'))) == false) {
            $this->_bind($this->conf->get('manager_bind_dn'), $this->conf->get('manager_bind_pw'));
        }

        error_log("Searching for a group dn in $root_dn, with search filter: $search_filter");

        $search_results = ldap_search($this->conn, $root_dn, $search_filter);

        if (ldap_count_entries($this->conn, $search_results) == 0) {
            return false;
        }

        if (($first_entry = ldap_first_entry($this->conn, $search_results)) == false) {
            return false;
        }

        $group_dn = ldap_get_dn($this->conn, $first_entry);
        return $group_dn;
    }

    private function _get_user_dn($root_dn, $search_filter)
    {
        // TODO: Why does this use privileged credentials?
        if (($this->_bind($this->conf->get('bind_dn'), $this->conf->get('bind_pw'))) == false) {
            //message("WARNING: Invalid Service bind credentials supplied");
            $this->_bind($this->conf->get('manager_bind_dn'), $this->conf->get('manager_bind_pw'));
        }

        error_log("Searching for a user dn in $root_dn, with search filter: $search_filter");

        $search_results = ldap_search($this->conn, $root_dn, $search_filter);

        if (!$search_results || ldap_count_entries($this->conn, $search_results) == 0) {
            //message("No entries found for the user dn in " . __METHOD__);
            return false;
        }

        if (($first_entry = ldap_first_entry($this->conn, $search_results)) == false) {
            return false;
        }

        $user_dn = ldap_get_dn($this->conn, $first_entry);
        return $user_dn;
    }


    private function _list_group_members($dn, $entry = null, $recurse = true)
    {
        $group_members = array();

        if (is_array($entry) && in_array('objectclass', $entry)) {
            if (!in_array(array('groupofnames', 'groupofuniquenames', 'groupofurls'), $entry['objectclass'])) {
                error_log("Called _list_groups_members on a non-group!");
            }
            else {
                error_log("Called list_group_members(" . $dn . ")");
            }
        }

        $entry = self::normalize_result($this->_search($dn));

        //console("ENTRIES for \$dn $dn", $entry);

        foreach ($entry[$dn] as $attribute => $value) {
            if ($attribute == "objectclass") {
                foreach ($value as $objectclass) {
                    switch (strtolower($objectclass)) {
                        case "groupofnames":
                        case "kolabgroupofnames":
                            $group_members = array_merge($group_members, $this->_list_group_member($dn, $entry[$dn]['member'], $recurse));
                            break;
                        case "groupofuniquenames":
                        case "kolabgroupofuniquenames":
                            $group_members = array_merge($group_members, $this->_list_group_uniquemember($dn, $entry[$dn]['uniquemember'], $recurse));
                            break;
                        case "groupofurls":
                            $group_members = array_merge($group_members, $this->_list_group_memberurl($dn, $entry[$dn]['memberurl'], $recurse));
                            break;
                    }
                }
            }
        }

        return array_filter($group_members);
    }

    private function _list_group_member($dn, $members, $recurse = true)
    {
        error_log("Called _list_group_member(" . $dn . ")");

        $group_members = array();

        $members = (array)($members);

        if (empty($members)) {
            return $group_members;
        }

        // Use the member attributes to return an array of member ldap objects
        // NOTE that the member attribute is supposed to contain a DN
        foreach ($members as $member) {
            $result = @ldap_read($this->conn, $member, '(objectclass=*)');

            if (!$result) {
                continue;
            }

            $member_entry = self::normalize_result(@ldap_get_entries($this->conn, $result));

            $group_members[$member] = array_pop($member_entry);

            if ($recurse) {
                // Nested groups
                $group_group_members = $this->_list_group_members($member, $member_entry);
                if ($group_group_members) {
                    $group_members = array_merge($group_group_members, $group_members);
                }
            }
        }

        return array_filter($group_members);
    }

    private function _list_group_uniquemember($dn, $uniquemembers, $recurse = true)
    {
        //console("Called _list_group_uniquemember(" . $dn . ")", $entry);

        // Use the member attributes to return an array of member ldap objects
        // NOTE that the member attribute is supposed to contain a DN
        $group_members = array();
        if (empty($uniquemembers)) {
            return $group_members;
        }

        $uniquemembers = (array)($uniquemembers);

        if (is_string($uniquemembers)) {
            //console("uniquemember for entry is not an array");
            $uniquemembers = (array)($uniquemembers);
        }

        foreach ($uniquemembers as $member) {
            $result = @ldap_read($this->conn, $member, '(objectclass=*)');

            if (!$result) {
                continue;
            }

            $member_entry = self::normalize_result(@ldap_get_entries($this->conn, $result));
            $group_members[$member] = array_pop($member_entry);

            if ($recurse) {
                // Nested groups
                $group_group_members = $this->_list_group_members($member, $member_entry);
                if ($group_group_members) {
                    $group_members = array_merge($group_group_members, $group_members);
                }
            }
        }

        return array_filter($group_members);
    }

    private function _list_group_memberurl($dn, $memberurls, $recurse = true)
    {
        error_log("Called _list_group_memberurl(" . $dn . ")");

        // Use the member attributes to return an array of member ldap objects
        // NOTE that the member attribute is supposed to contain a DN

        $group_members = array();

        foreach ((array)($memberurls) as $url) {
            $ldap_uri_components = $this->_parse_memberurl($url);

            $entries = self::normalize_result($this->_search($ldap_uri_components[3], $ldap_uri_components[6]));

            foreach ($entries as $entry_dn => $_entry) {
                $group_members[$entry_dn] = $_entry;
                error_log("Found " . $entry_dn);

                if ($recurse) {
                    // Nested group
                    $group_group_members = $this->_list_group_members($entry_dn, $_entry);
                    if ($group_group_members) {
                        $group_members = array_merge($group_members, $group_group_members);
                    }
                }
            }
        }

        return array_filter($group_members);
    }

    /**
     * memberUrl attribute parser
     *
     * @param string $url URL string
     *
     * @return array URL elements
     */
    private function _parse_memberurl($url)
    {
        error_log("Parsing URL: " . $url);
        preg_match('/(.*):\/\/(.*)\/(.*)\?(.*)\?(.*)\?(.*)/', $url, $matches);
        return $matches;
    }

    /**
     * Returns name of the unique attribute
     */
    private function unique_attribute()
    {
        $conf        = Conf::get_instance();
        $unique_attr = $conf->get('unique_attribute');

        if (!$unique_attr) {
            $unique_attr = 'nsuniqueid';
        }

        return $unique_attr;
    }

    /**
     * Quotes attribute value string
     *
     * @param string $str Attribute value
     * @param bool   $dn  True if the attribute is a DN
     *
     * @return string Quoted string
     */
    private static function _quote_string($str, $dn=false)
    {
        // take firt entry if array given
        if (is_array($str)) {
            $str = reset($str);
        }

        if ($dn) {
            $replace = array(
                ',' => '\2c',
                '=' => '\3d',
                '+' => '\2b',
                '<' => '\3c',
                '>' => '\3e',
                ';' => '\3b',
                "\\"=> '\5c',
                '"' => '\22',
                '#' => '\23'
            );
        }
        else {
            $replace = array(
                '*' => '\2a',
                '(' => '\28',
                ')' => '\29',
                "\\" => '\5c',
                '/' => '\2f'
            );
        }

         return strtr($str, $replace);
    }

}

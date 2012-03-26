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

        $this->domain       = $domain ? $domain : $this->conf->get('primary_domain');
        $this->_ldap_uri    = $this->conf->get('uri');
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

    public function authenticate($username, $password)
    {
        error_log("LDAP authentication request for $username");
        $this->_connect();

        // Attempt to explode the username to see if it is in fact a DN,
        // such as would be the case for 'cn=Directory Manager' or
        // 'uid=admin'.
        $is_dn = ldap_explode_dn($username, 1);
        if (!$is_dn) {
            error_log("Username is not a DN");
            list($this->userid, $this->domain) = $this->_qualify_id($username);
            $root_dn = $this->domain_root_dn($this->domain);
            $user_dn = $this->_get_user_dn($root_dn, '(mail=' . $username . ')');
            error_log("Found user DN: $user_dn for user: $username");
        }
        else {
            $user_dn = $username;
            $root_dn = "";
        }

        if (($bind_ok = $this->_bind($user_dn, $password)) == true) {
            $this->_unbind();

            if (isset($_SESSION['user'])) {
                $_SESSION['user']->user_root_dn = $root_dn;
                $_SESSION['user']->user_bind_dn = $user_dn;
                $_SESSION['user']->user_bind_pw = $password;
                error_log("Successfully bound with User DN: " . $_SESSION['user']->user_bind_dn);
            }
            else {
                error_log("Successfully bound with User DN: " . $user_dn . " but not saving it to the session");
            }

            return true;
        }
        else {
            error_log("LDAP Error: " . $this->_errstr());
            return false;
        }
    }

    public function connect()
    {
        // Apply some routines for access control to this function here.
        return $this->_connect();
    }

    public function domain_add($domain, $domain_alias = false, $prepopulate = true)
    {
        // Apply some routines for access control to this function here.
        if ($domain_alias) {
            return $this->_domain_add_alias($domain, $domain_alias);
        }
        else {
            return $this->_domain_add_new($domain, $prepopulate);
        }
    }

    /*
        Translate a domain name into it's corresponding root dn.
    */
    public function domain_root_dn($domain = '')
    {
        $conf = Conf::get_instance();

        if ($domain == '') {
            return false;
        }

        error_log("Searching for domain $domain");

        $this->_connect();

        error_log("From domain to root dn");

        if (($this->_bind($conf->get('ldap', 'bind_dn'), $conf->get('ldap', 'bind_pw'))) == false) {
            error_log("WARNING: Invalid Service bind credentials supplied");
            $this->_bind($conf->manager_bind_dn, $conf->manager_bind_pw);
        }

        // TODO: Get domain_attr from config
        if (($results = ldap_search($this->conn, $conf->get('domain_base_dn'), '(associatedDomain=' . $domain . ')')) == false) {
            error_log("No results?");
            return false;
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

    public function effective_rights($subject_dn)
    {
        $attributes = array();
        $output = array();

        $conf = Conf::get_instance();

        $command = array(
                // TODO: Very 64-bit specific
                '/usr/lib64/mozldap/ldapsearch',
                '-x',
                '-h',
                // TODO: Get from conf
                'ldap.klab.cc',
                '-b',
                // TODO: Get from conf
                'dc=klab,dc=cc',
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
                '"(entrydn=' . $subject_dn . ')"'

            );

        exec(implode(' ', $command), $output);

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
            if (!in_array($params['sort_by'], $attributes)) {
                $attributes[] = $params['sort_by'];
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

    public function user_add($attrs, $type = null)
    {
        if ($type == null) {
            $type_str = 'user';
        }
        else {
            $db   = SQL::get_instance();
            $_key = $db->fetch_assoc($db->query("SELECT `key` FROM user_types WHERE id = ?", $type));
            $type_str = $_key['key'];
        }

        // Check if the user_type has a specific base DN specified.
        $base_dn = $this->conf->get($this->domain, $type_str . "_user_base_dn");
        // If not, take the regular user_base_dn
        if (!$base_dn)
            $base_dn = $this->conf->get($this->domain, "user_base_dn");

        // If no user_base_dn either, take the user type specific from the parent
        // configuration
        if (!$base_dn)
            $base_dn = $this->conf->get('ldap', $type_str . "_user_base_dn");

        // TODO: The rdn is configurable as well.
        // Use [$type_str . "_"]user_rdn_attr
        $dn = "uid=" . $attrs['uid'] . "," . $base_dn;

        return $this->_add($dn, $attrs);
    }

    public function user_delete($user)
    {
        $is_dn = ldap_explode_dn($user, 1);
        if (!$is_dn) {
            list($this->userid, $this->domain) = $this->_qualify_id($user);
            $root_dn = $this->domain_root_dn($this->domain);
            $user_dn = $this->_get_user_dn($root_dn, '(mail=' . $user . ')');
        }
        else {
            $user_dn = $user;
        }

        if (!$user_dn) {
            return false;
        }

        return $this->_delete($user_dn);
    }

    public function user_find_by_attribute($attribute)
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

        $base_dn = $this->domain_root_dn($this->domain);

        $result = self::normalize_result($this->search($base_dn, $filter, array_keys($attribute)));

        if (count($result) > 0) {
            error_log("Results found: " . implode(', ', array_keys($result)));
            return $result;
        }
        else {
            error_log("No result");
            return false;
        }
    }

    public function user_get_attribute($user_dn, $attribute)
    {
        $result = $this->search($user_dn, '(objectclass=*)', array($attribute));
        $result = self::normalize_result($result);

        if (!empty($result) && array_key_exists($attribute, $result[$user_dn])) {
            return $result[$user_dn][$attribute];
        } else {
            return FALSE;
        }
    }

    public function user_info($user)
    {
        $is_dn = ldap_explode_dn($user, 1);
        if (!$is_dn) {
            list($this->userid, $this->domain) = $this->_qualify_id($user);
            $root_dn = $this->domain_root_dn($this->domain);
            $user_dn = $this->_get_user_dn($root_dn, '(mail=' . $user . ')');
        }
        else {
            $user_dn = $user;
        }

        if (!$user_dn) {
            return false;
        }

        return self::normalize_result($this->search($user_dn));
    }

    public function find_user_groups($member_dn)
    {
        error_log(__FILE__ . "(" . __LINE__ . "): " .  $member_dn);

        $groups = array();

        $root_dn = $this->domain_root_dn($this->domain);

        // TODO: Do not query for both, it's either one or the other
        $entries = $this->search($root_dn, "(|" .
                "(&(objectclass=groupofnames)(member=$member_dn))" .
                "(&(objectclass=groupofuniquenames)(uniquemember=$member_dn))" .
            ")");

        $entries = self::normalize_result($entries);

        foreach ($entries as $entry_dn => $entry_attributes) {
            $groups[] = $entry_dn;
        }

        return $groups;
    }

    public function group_info($group)
    {
        $is_dn = ldap_explode_dn($group, 1);
        if (!$is_dn) {
            $root_dn = $this->domain_root_dn($this->domain);
            $group_dn = $this->_get_group_dn($root_dn, '(mail=' . $group . ')');
        }
        else {
            $group_dn = $group;
        }

        if (!$group_dn) {
            return false;
        }

        return self::normalize_result($this->search($group_dn));
    }

    public function group_members_list($group)
    {
        $is_dn = ldap_explode_dn($group, 1);
        if (!$is_dn) {
            $root_dn = $this->domain_root_dn($this->domain);
            $group_dn = $this->_get_group_dn($root_dn, '(mail=' . $group . ')');
        }
        else {
            $group_dn = $group;
        }

        if (!$group_dn) {
            return false;
        }

        return $this->_list_group_members($group_dn);
    }

    private function search($base_dn, $search_filter = '(objectClass=*)', $attributes = array('*'))
    {
        error_log("Searching $base_dn with filter '$search_filter'");
        return $this->_search($base_dn, $search_filter, $attributes);
    }

    private function domains_list()
    {
        $section = $this->conf->get('kolab', 'auth_mechanism');
        $base_dn = $this->conf->get($section, 'domain_base_dn');
        $filter  = $this->conf->get($section, 'kolab_domain_filter');

        return $this->search($base_dn, $filter);
    }

    private function users_list($attributes = array(), $search = array())
    {
        $conf = Conf::get_instance();

        $base_dn = $conf->get('ldap', 'user_base_dn');
        $filter  = $conf->get('ldap', 'user_filter');

        if (empty($attributes) || !is_array($attributes)) {
            $attributes = array('*');
        }

        if ($s_filter = $this->_search_filter($search)) {
            // join search filter with objectClass filter
            $filter = '(&' . $filter . $s_filter . ')';
        }

        return $this->search($base_dn, $filter, $attributes);
    }

    private function roles_list($attributes = array(), $search = array())
    {
        $conf = Conf::get_instance();

        // TODO: From config
        $base_dn = "dc=klab,dc=cc";
        // TODO: From config
        $filter  = "(&(objectclass=ldapsubentry)(objectclass=nsroledefinition))";

        if (empty($attributes) || !is_array($attributes)) {
            $attributes = array('*');
        }

        if ($s_filter = $this->_search_filter($search)) {
            // join search filter with objectClass filter
            $filter = '(&' . $filter . $s_filter . ')';
        }

        return $this->search($base_dn, $filter, $attributes);
    }

    private function groups_list($attributes = array(), $search = array())
    {
        // TODO: From config
        $base_dn = "ou=Groups,dc=klab,dc=cc";
        // TODO: From config
        $filter  = "(|"
            ."(objectClass=kolabgroupofnames)"
            ."(objectclass=kolabgroupofuniquenames)"
            ."(objectclass=kolabgroupofurls)"
            .")";

        if (empty($attributes) || !is_array($attributes)) {
            $attributes = array('*');
        }

        if ($s_filter = $this->_search_filter($search)) {
            // join search filter with objectClass filter
            $filter = '(&' . $filter . $s_filter . ')';
        }

        return $this->search($base_dn, $filter, $attributes);
    }

    public static function normalize_result($__result)
    {
        $conf = Conf::get_instance();

        $dn_attr = $conf->get($conf->get('kolab', 'auth_mechanism'), 'domain_name_attribute');
        $result = array();

        for ($x = 0; $x < $__result["count"]; $x++) {
            $dn = $__result[$x]['dn'];
            $result[$dn] = array();
            for ($y = 0; $y < $__result[$x]["count"]; $y++) {
                $attr = $__result[$x][$y];
                if ($__result[$x][$attr]["count"] == 1) {
                    $result[$dn][$attr] = $__result[$x][$attr][0];
                }
                else {
                    $result[$dn][$attr] = array();
                    for ($z = 0; $z < $__result[$x][$attr]["count"]; $z++) {
                        if ($z == 0 && $attr == $dn_attr) {
                            $result[$dn]['primary_domain'] = $__result[$x][$attr][$z];
                        }

                        $result[$dn][$attr][] = $__result[$x][$attr][$z];
                    }
                }
            }
        }

        return $result;
    }

    private function parse_attribute_level_rights($attribute_value)
    {
        $attribute_value = str_replace(", ", ",", $attribute_value);
        $attribute_values = explode(",", $attribute_value);

        $attribute_value = array();

        foreach ($attribute_values as $access_right) {
            $access_right_components = explode(":", $access_right);
            $access_attribute = array_shift($access_right_components);
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

    /**
     * Result sorting callback for uasort()
     */
    public function sort_result($a, $b)
    {
        $str1 = $a[$this->sort_result_key];
        $str2 = $b[$this->sort_result_key];

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

    /*

        Shortcut functions

    */

    /*
        Shortcut to ldap_add()
    */

    private function _add($entry_dn, $attributes)
    {
        // Always bind with the session credentials
        $this->_connect();
        $this->_bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        if (($add_result = ldap_add($this->conn, $entry_dn, $attributes)) == false) {
            // Issue warning
            return false;
        }

        return true;
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
        if (!$this->conn) {
            // TODO: Debug logging
            error_log("Connecting to " . $this->_ldap_server . " on port " . $this->_ldap_port);
            $connection = ldap_connect($this->_ldap_server, $this->_ldap_port);

            if ($connection == false) {
                $this->conn = null;
                // TODO: Debug logging
                error_log("Not connected: " . ldap_err2str() .  "(no.) " . ldap_errno());
            }
            else {
                $this->conn = $connection;
            }

            // TODO: Debug logging
            error_log("Connected!");
        }
    }

    /**
     *   Shortcut to ldap_delete()
     */
    private function _delete($entry_dn)
    {
        $this->_connect();
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

    /**
     * Shortcut to ldap_search()
     */
    private function _search($base_dn, $search_filter = '(objectClass=*)', $attributes = array('*'))
    {
        error_log("Searching with user " . $_SESSION['user']->user_bind_dn);
        $this->_bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        if (($search_results = @ldap_search($this->conn, $base_dn, $search_filter, $attributes)) == false) {
            //message("Could not search in " . __METHOD__ . " in " . __FILE__ . " on line " . __LINE__ . ": " . $this->_errstr());
            return false;
        }

        if (($entries = ldap_get_entries($this->conn, $search_results)) == false) {
            //message("Could not get the results of the search: " . $this->_errstr());
            return false;
        }

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
            $value = self::_quote_string($param['value']);

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

            $filter .= "($field=$prefix" . $value . "$suffix)";
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
            ldap_unbind($this->conn);

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

    private function _get_group_dn($root_dn, $search_filter)
    {
        error_log("Searching for a group dn in $root_dn, with search filter: $search_filter");

        $this->_connect();

        if (($this->_bind($this->conf->get('bind_dn'), $this->conf->get('bind_pw'))) == false) {
            $this->_bind($this->conf->get('manager_bind_dn'), $this->conf->get('manager_bind_pw'));
        }

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
        error_log("Searching for a user dn in $root_dn, with search filter: $search_filter");

        $this->_connect();

        if (($this->_bind($this->conf->get('bind_dn'), $this->conf->get('bind_pw'))) == false) {
            //message("WARNING: Invalid Service bind credentials supplied");
            $this->_bind($this->conf->get('manager_bind_dn'), $this->conf->get('manager_bind_pw'));
        }

        $search_results = ldap_search($this->conn, $root_dn, $search_filter);

        if (ldap_count_entries($this->conn, $search_results) == 0) {
            //message("No entries found for the user dn in " . __METHOD__);
            return false;
        }

        if (($first_entry = ldap_first_entry($this->conn, $search_results)) == false) {
            return false;
        }

        $user_dn = ldap_get_dn($this->conn, $first_entry);
        return $user_dn;
    }


    private function _list_group_members($dn, $entry = null)
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

        $entries = self::normalize_result($this->search($dn));

        foreach ($entries as $entry_dn => $entry) {
            if (!isset($entry['objectclass'])) {
                continue;
            }

            foreach ($entry['objectclass'] as $objectclass) {
                switch ($objectclass) {
                case "groupofnames":
                    $group_members = array_merge($group_members, $this->_list_group_member($entry_dn, $entry));
                    break;
                case "groupofuniquenames":
                    $group_members = array_merge($group_members, $this->_list_group_uniquemember($entry_dn, $entry));
                    break;
                case "groupofurls":
                    $group_members = array_merge($group_members, $this->_list_group_memberurl($entry_dn, $entry));
                    break;
                }
            }
        }

        return array_filter($group_members);
    }

    private function _list_group_member($dn, $entry)
    {
        error_log("Called _list_group_member(" . $dn . ")");

        $group_members = array();
        if (empty($entry['member'])) {
            return $group_members;
        }

        // Use the member attributes to return an array of member ldap objects
        // NOTE that the member attribute is supposed to contain a DN
        foreach ($entry['member'] as $member) {
            $result = @ldap_read($this->conn, $member, '(objectclass=*)');

            if (!$result) {
                continue;
            }

            $member_entry = self::normalize_result(@ldap_get_entries($this->conn, $result));
            $group_members[$member] = array_pop($member_entry);

            // Nested groups
//            $group_group_members = $this->_list_group_members($member, $member_entry);
//            if ($group_group_members) {
//                $group_members = array_merge($group_group_members, $group_members);
//            }
        }

        return array_filter($group_members);
    }

    private function _list_group_uniquemember($dn, $entry)
    {
        error_log("Called _list_group_uniquemember(" . $dn . ")");

        // Use the member attributes to return an array of member ldap objects
        // NOTE that the member attribute is supposed to contain a DN
        $group_members = array();
        if (empty($entry['uniquemember'])) {
            return $group_members;
        }

        foreach ($entry['uniquemember'] as $member) {
            $result = @ldap_read($this->conn, $member, '(objectclass=*)');

            if (!$result) {
                continue;
            }

            $member_entry = self::normalize_result(@ldap_get_entries($this->conn, $result));
            $group_members[$member] = array_pop($member_entry);

            // Nested groups
//            $group_group_members = $this->_list_group_members($member, $member_entry);
//            if ($group_group_members) {
//                $group_members = array_merge($group_group_members, $group_members);
//            }
        }

        return array_filter($group_members);
    }

    private function _list_group_memberurl($dn, $entry)
    {
        error_log("Called _list_group_memberurl(" . $dn . ")");

        // Use the member attributes to return an array of member ldap objects
        // NOTE that the member attribute is supposed to contain a DN

        $group_members = array();

        foreach ((array)$entry['memberurl'] as $url) {
            $ldap_uri_components = $this->_parse_memberurl($url);
            $entries = self::normalize_result($this->search($ldap_uri_components[3], $ldap_uri_components[6]));
            foreach ($entries as $entry_dn => $_entry) {
                $group_members[$entry_dn] = $_entry;
                error_log("Found " . $entry_dn);
                // Nested group
//                $group_group_members = $this->_list_group_members($entry_dn, $_entry);
//                if ($group_group_members) {
//                    $group_members = array_merge($group_members, $group_group_members);
//                }
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

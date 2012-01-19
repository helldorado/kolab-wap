<?php

//
// Kolab LDAP handling abstraction class.
//

class LDAP
{

    public $_name = "LDAP";

    // Needs to be protected and not just private
    protected $_connection = NULL;

    protected $user_bind_dn;
    protected $user_bind_pw;

    // This is the default and should actually be set through Conf.
    private $_ldap_uri = 'ldap://localhost:389/';

    private $conf;

    public function __construct($domain = NULL)
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

    /*
         Public functions
     */

    public function add($dn, $attributes)
    {
        return $this->_add($dn, $attributes);
    }

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

        if (($bind_ok = $this->_bind($user_dn, $password)) == TRUE) {
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

            return TRUE;
        }
        else {
            error_log("LDAP Error: " . $this->_errstr());
            return FALSE;
        }
    }

    public function bind($bind_dn, $bind_pw)
    {
        // Apply some routines for access control to this function here.
        return $this->_bind($bind_dn, $bind_pw);
    }

    public function connect()
    {
        // Apply some routines for access control to this function here.
        return $this->_connect();
    }

    public function delete($dn)
    {
        return $this->_delete($dn);
    }

    public function domain_add($domain, $domain_alias = FALSE, $prepopulate = TRUE)
    {
        // Apply some routines for access control to this function here.
        if ($domain_alias) {
            return $this->_domain_add_alias($domain, $domain_alias);
        }
        else {
            return $this->_domain_add_new($domain, $prepopulate);
        }
    }

    public function domain_exists($domain)
    {
        return $this->_ldap->domain_exists($domain);
    }

    public function domain_list($rev_sort = FALSE)
    {
        return $this->_ldap->domain_list($rev_sort);
    }

    /*
        Translate a domain name into it's corresponding root dn.
    */

    public function domain_root_dn($domain = '')
    {
        $conf = Conf::get_instance();

        if ($domain == '') {
            return FALSE;
        }

        error_log("Searching for domain $domain");

        $this->_connect();

        error_log("From domain to root dn");

        if (($this->_bind($conf->get('ldap', 'bind_dn'), $conf->get('ldap', 'bind_pw'))) == FALSE) {
            error_log("WARNING: Invalid Service bind credentials supplied");
            $this->_bind($conf->manager_bind_dn, $conf->manager_bind_pw);
        }

        if (($results = ldap_search($this->_connection, $conf->get('domain_base_dn'), '(associatedDomain=' . $domain . ')')) == FALSE) {
            error_log("No results?");
            return FALSE;
        }

        $domain = ldap_first_entry($this->_connection, $results);
        $domain_info = ldap_get_attributes($this->_connection, $domain);

//        echo "<pre>"; print_r($domain_info); echo "</pre>";

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

    public function domains_list()
    {
        $section = $this->conf->get('kolab', 'auth_mechanism');
        $base_dn = $this->conf->get($section, 'domain_base_dn');
        $filter  = $this->conf->get($section, 'kolab_domain_filter');

        return $this->search($base_dn, $filter);
    }

    public function find_user_groups($member_dn)
    {
        error_log(__FILE__ . "(" . __LINE__ . "): " .  $member_dn);

        $groups = array();

        $root_dn = $this->domain_root_dn($this->domain);

        $entries = $this->search($root_dn, "(|" .
            "(&(objectclass=groupofnames)(member=$member_dn))" .
            "(&(objectclass=groupofuniquenames)(uniquemember=$member_dn))" .
        ")");

        $entries = $this->normalize_result($entries);

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
            $group_dn = $user;
        }

        if (!$group_dn) {
            return FALSE;
        }

        return $this->search($group_dn);
    }

    public function group_members_list($group)
    {
        $is_dn = ldap_explode_dn($group, 1);
        if (!$is_dn) {
            $root_dn = $this->domain_root_dn($this->domain);
            $group_dn = $this->_get_group_dn($root_dn, '(mail=' . $group . ')');
        }
        else {
            $group_dn = $user;
        }

        if (!$group_dn) {
            return FALSE;
        }

        return $this->_list_group_members($group_dn);
    }

    public function groups_list()
    {
        $filter = "(|"
            ."(objectClass=kolabgroupofnames)"
            ."(objectclass=kolabgroupofuniquenames)"
            ."(objectclass=kolabgroupofurls)"
            .")";

        return $this->search("ou=Groups,dc=klab,dc=cc", $filter, array("cn"));
    }

    public function llist($base_dn, $filter)
    {
        return $this->_list($base_dn, $filter);
    }

    public function list_domains()
    {
        $domains = $this->domains_list();
        $domains = $this->normalize_result($domains);

        return $domains;
    }

    public function list_groups()
    {
        $groups = $this->groups_list();
        $groups = $this->normalize_result($groups);

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
        $users = $this->normalize_result($users);

        if (!empty($params['sort_by'])) {
            $this->sort_result_key = $params['sort_by'];
            uasort($users, array($this, 'sort_result'));

            if ($params['sort_order'] == 'DESC') {
                $users = array_reverse($users, true);
            }
        }

        return $users;
    }

    static function normalize_result($__result)
    {
        $conf = Conf::get_instance();

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
                        if ($z == 0 && $attr == $conf->get($conf->get('kolab', 'auth_mechanism'), 'domain_name_attribute')) {
                            $result[$dn]['primary_domain'] = $__result[$x][$attr][$z];
                        }

                        $result[$dn][$attr][] = $__result[$x][$attr][$z];
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
        $str1 = $a[$this->sort_result_key];
        $str2 = $b[$this->sort_result_key];

        return strcmp(mb_strtoupper($str1), mb_strtoupper($str2));
    }

    public function user_add($attrs, $type = NULL) {
        if ($type == NULL) {
            $type_str = 'user';
        }
        else {
//@TODO: sql injection
            $_key = mysql_fetch_assoc(query("SELECT key FROM user_types WHERE id = '" . $type  . "'"));
            $type_str = $_key['key'];
        }

        $base_dn = $this->conf->get($this->domain, $type_str . "_user_base_dn");
        if (!$base_dn) {
            $base_dn = $this->conf->get('ldap', $type_str . "_user_base_dn");
        }

        // TODO: The rdn is configurable as well
        $dn = "uid=" . $attrs['uid'] . "," . $base_dn;

        return $this->add($dn, $attrs);
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
            return FALSE;
        }

        return $this->delete($user_dn);
    }

    public function user_find_by_attribute($attribute)
    {
        if (!is_array($attribute) || count($attribute) > 1) {
            return FALSE;
        }

        $filter = "(&";

        foreach ($attribute as $key => $value) {
            $filter .= "(" . $key . "=" . $value . ")";
        }

        $filter .= ")";

        $base_dn = $this->domain_root_dn($this->domain);

        $result = $this->normalize_result($this->search($base_dn, $filter, array_keys($attribute)));

        if (count($result) > 0) {
            error_log("Results found: " . implode(', ', array_keys($result)));
            return $result;
        }
        else {
            error_log("No result");
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
            return FALSE;
        }

        return $this->search($user_dn);
    }

    public function users_list($attributes = array(), $search = array(), $params = array())
    {
        $base_dn = "ou=People,dc=klab,dc=cc";
        $filter  = "(objectClass=kolabinetorgperson)";

        if (empty($attributes) || !is_array($attributes)) {
            $attributes = array('*');
        }

        if (!empty($search) && is_array($search) && !empty($search['params'])) {
            $s_filter = '';
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

                $s_filter .= "($field=$prefix" . $value . "$suffix)";
            }

            // join search parameters with specified operator ('OR' or 'AND')
            if (count($search['params']) > 1) {
                $s_filter = '(' . ($search['operator'] == 'AND' ? '&' : '|') . $s_filter . ')';
            }

            // join search filter with objectClass filter
            $filter = '(&' . $filter . $s_filter . ')';
        }

        return $this->search($base_dn, $filter, $attributes);
    }

    public function search($base_dn, $search_filter = '(objectClass=*)', $attributes = array('*'))
    {
        error_log("Searching $base_dn with filter '$search_filter'");
        return $this->_search($base_dn, $search_filter, $attributes);
    }

    public function setup()
    {
        return $this->_ldap->setup();
    }

    /*
        Qualify a username.

        Where username is 'kanarip@kanarip.com', the function will return an
        array containing 'kanarip' and 'kanarip.com'. However, where the
        username is 'kanarip', the domain name is to be assumed the
        management domain name.
    */

    public function _qualify_id($username)
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

    /*
        Deprecated, use domain_root_dn()
    */

    public function user_type_attribute_filter($type = FALSE)
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

        echo "<li>"; print_r($attributes_filter);

        return $attributes_filter;
    }

    public function user_type_search_filter($type = FALSE)
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
        Private functions
     */

    private function _domain_add_alias($domain, $domain_alias)
    {
        $this->_ldap->_domain_add_alias($domain, $domain_alias);
    }

    private function _domain_add_new($domain, $populatedomain)
    {
        $this->connect();
        $this->_ldap->_domain_add_new($domain, $populatedomain);
    }

    /*

        Shortcut functions

    */

    /*
        Shortcut to ldap_add()
    */

    private function _add($entry_dn, $attributes)
    {
        $this->_connect();
        $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        if (($add_result = ldap_add($this->_connection, $entry_dn, $attributes)) == FALSE) {
            // Issue warning
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Shortcut to ldap_bind()
     */
    private function _bind($dn, $pw)
    {
        $this->_connect();

        // TODO: Debug logging
        error_log("->_bind() Binding with $dn");

        if (!$dn || !$pw) {
            return FALSE;
        }

        if (($bind_ok = ldap_bind($this->_connection, $dn, $pw)) == FALSE) {
            error_log("LDAP Error: " . $this->_errstr());
            // Issue error message
            return FALSE;
        }
        else {
            return TRUE;
        }
    }

    /**
     * Shortcut to ldap_connect()
     */
    private function _connect()
    {
        if ($this->_connection == FALSE) {
            // TODO: Debug logging
            error_log("Connecting to " . $this->_ldap_server . " on port " . $this->_ldap_port);
            $connection = ldap_connect($this->_ldap_server, $this->_ldap_port);

            if ($connection == FALSE) {
                $this->_connection = FALSE;
                // TODO: Debug logging
                error_log("Not connected: " . ldap_err2str() .  "(no.) " . ldap_errno());
            }
            else {
                $this->_connection = $connection;
            }

            // TODO: Debug logging
            error_log("Connected!");
        }
        else {
            error_log("Already connected");
        }
    }

    /**
     *   Shortcut to ldap_delete()
     */
    private function _delete($entry_dn)
    {
        $this->_connect();
        $this->bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

        if (($delete_result = ldap_delete($this->_connection, $entry_dn)) == FALSE) {
            // Issue warning
            return FALSE;
        }
        else {
            return TRUE;
        }
    }

    /**
     * Shortcut to ldap_disconnect()
     */
    private function _disconnect()
    {
        if ($this->_connection == FALSE) {
            return TRUE;
        }

        if (($result = ldap_close($this->_connection)) == TRUE) {
            $this->_connection = FALSE;
            return TRUE;
        }

        // Issue a warning
        $this->_connection = FALSE;
        $this->_ldap = FALSE;
        return FALSE;
    }

    /**
     * Shortcut to ldap_err2str() over ldap_errno()
     */
    private function _errstr()
    {
        if ($errno = @ldap_errno($this->_connection)) {
            if ($err2str = @ldap_err2str($errno)) {
                return $err2str;
            }
        }

        // Issue warning
        return NULL;
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

        if (($ldap_list = @ldap_list($this->_connection, $base_dn, $filter)) == FALSE) {
            //message("LDAP Error: Could not search " . $base_dn . ": " . $this->_errstr() );
        }
        else {
            if (($ldap_entries = @ldap_get_entries($this->_connection, $ldap_list)) == FALSE) {
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

        if (($search_results = @ldap_search($this->_connection, $base_dn, $search_filter, $attributes)) == FALSE) {
            #message("Could not search in " . __METHOD__ . " in " . __FILE__ . " on line " . __LINE__ . ": " . $this->_errstr());
            return FALSE;
        }

        if (($entries = ldap_get_entries($this->_connection, $search_results)) == FALSE) {
            #message("Could not get the results of the search: " . $this->_errstr());
            return FALSE;
        }

        return $entries;
    }

    /**
     * Shortcut to ldap_unbind()
     */
    private function _unbind($yes = FALSE, $really = FALSE)
    {
        if ($yes && $really) {
            ldap_unbind($this->_connection);
            $this->_connection = FALSE;
        }
        else {
            // What?
            //
            // - attempt bind as anonymous
            // - in case of fail, bind as user
        }

        return TRUE;
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
        if (($tmp_connection = ldap_connect($this->_ldap_server)) == FALSE) {
            //message("LDAP Error: " . $this->_errstr());
            return FALSE;
        }

        error_log("User DN: " . $_SESSION['user']->user_bind_dn);

        if (($bind_success = ldap_bind($tmp_connection, $_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw)) == FALSE) {
            //message("LDAP Error: " . $this->_errstr());
            return FALSE;
        }

        if (($list_success = ldap_list($tmp_connection, $entry_root_dn, '(objectClass=*)', array('*', 'aci'))) == FALSE) {
            #message("LDAP Error: " . $this->_errstr());
            return FALSE;
        }

#        print_r(ldap_get_entries($tmp_connection, $list_success));
/*
        if (ldap_count_entries($tmp_connection, $list_success) == 0) {
            echo "<li>Listed things, but got no results";
            return FALSE;
        }
*/
        return TRUE;
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

################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################
################################################################################

    public function _get_group_dn($root_dn, $search_filter)
    {
        error_log("Searching for a group dn in $root_dn, with search filter: $search_filter");

        $this->_connect();

        if (($this->_bind($this->conf->get('bind_dn'), $this->conf->get('bind_pw'))) == FALSE) {
            $this->_bind($this->conf->get('manager_bind_dn'), $this->conf->get('manager_bind_pw'));
        }

        $search_results = ldap_search($this->_connection, $root_dn, $search_filter);

        if (ldap_count_entries($this->_connection, $search_results) == 0) {
            return FALSE;
        }

        if (($first_entry = ldap_first_entry($this->_connection, $search_results)) == FALSE) {
            return FALSE;
        }

        $group_dn = ldap_get_dn($this->_connection, $first_entry);
        return $group_dn;
    }

    public function _get_user_dn($root_dn, $search_filter)
    {
        error_log("Searching for a user dn in $root_dn, with search filter: $search_filter");

        $this->_connect();

        if (($this->_bind($this->conf->get('bind_dn'), $this->conf->get('bind_pw'))) == FALSE) {
            //message("WARNING: Invalid Service bind credentials supplied");
            $this->_bind($this->conf->get('manager_bind_dn'), $this->conf->get('manager_bind_pw'));
        }

        $search_results = ldap_search($this->_connection, $root_dn, $search_filter);

        if (ldap_count_entries($this->_connection, $search_results) == 0) {
            //message("No entries found for the user dn in " . __METHOD__);
            return FALSE;
        }

        if (($first_entry = ldap_first_entry($this->_connection, $search_results)) == FALSE) {
            return FALSE;
        }

        $user_dn = ldap_get_dn($this->_connection, $first_entry);
        return $user_dn;
    }


    public function _get_email_address()
    {
        return "kanarip@kanarip.com";
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

        $entries = $this->normalize_result($this->search($dn));

        foreach ($entries as $entry_dn => $entry) {
            if (!isset($entry['objectclass'])) {
                continue;
            }

            foreach ( $entry['objectclass'] as $num => $objectclass) {
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

        // Use the member attributes to return an array of member ldap objects
        // NOTE that the member attribute is supposed to contain a DN
        $group_members = array();
        for ($i = 0; $i < (count($entry['member'])-1); $i++) {
            $result  = @ldap_read($this->_connection, $entry['member'][$i], '(objectclass=*)');
            $members = @ldap_get_entries($this->_connection, $result);

            // Nested groups
            $group_group_members = $this->list_group_members($entry['member'][$i]);
            $group_members[] = array_filter(array_merge($group_group_members, $members));
        }

        return array_filter($group_members);
    }

    private function _list_group_uniquemember($dn, $entry)
    {
        error_log("Called _list_group_uniquemember(" . $dn . ")");

        // Use the member attributes to return an array of member ldap objects
        // NOTE that the member attribute is supposed to contain a DN
        $group_members = array();
        if (!isset($entry['uniquemember'])) {
            return $group_members;
        }

        for ($i = 0; $i < (count($entry['uniquemember'])-1); $i++) {
            $result  = @ldap_read($this->_connection, $entry['uniquemember'][$i], '(objectclass=*)');
            $members = @ldap_get_entries($this->_connection, $result);

            // Nested groups
            $group_group_members = $this->list_group_members($entry['uniquemember'][$i]);
            $group_members[] = array_filter(array_merge($group_group_members, $members));
        }

        return $group_members;
    }

    private function _list_group_memberurl($dn, $entry)
    {
        error_log("Called _list_group_memberurl(" . $dn . ")");

        // Use the member attributes to return an array of member ldap objects
        // NOTE that the member attribute is supposed to contain a DN

        $group_members = array();

        if (is_array($entry['memberurl'])) {
            foreach ($entry['memberurl'] as $url) {
                $ldap_uri_components = $this->_parse_memberurl($url);
                $entries = $this->normalize_result($this->search($ldap_uri_components[3], $ldap_uri_components[6]));
                foreach ($entries as $entry_dn => $_entry) {
                    error_log("Found " . $entry_dn);
                    $group_group_members = $this->_list_group_members($entry_dn);

                    if ($group_group_members) {
                        $group_members = array_merge($group_members, $group_group_members);
                    }
                    else {
                        $group_members[] = $entry_dn;
                    }
                }
            }
        }
        else {
            $ldap_uri_components = $this->_parse_memberurl($entry['memberurl']);
            $entries = $this->normalize_result($this->search($ldap_uri_components[3], $ldap_uri_components[6]));

            foreach ($entries as $entry_dn => $_entry) {
                error_log("Found " . $entry_dn);
                $group_group_members = $this->_list_group_members($entry_dn);

                if ($group_group_members) {
                     $group_members = array_merge($group_members, $group_group_members);
                }
                else {
                    $group_members[] = $entry_dn;
                }
            }
        }

        return array_filter($group_members);
    }

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

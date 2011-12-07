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

            if ($domain === NULL) {
                $this->domain = $this->conf->get('primary_domain');
            } else {
                $this->domain = $domain;
            }

            $this->_ldap_uri = $this->conf->get('uri');

            $this->_ldap_server = parse_url($this->_ldap_uri, PHP_URL_HOST);
            $this->_ldap_port = parse_url($this->_ldap_uri, PHP_URL_PORT);
            $this->_ldap_scheme = parse_url($this->_ldap_uri, PHP_URL_SCHEME);

            // Catch cases in which the ldap server port has not been explicitely defined
            if (!$this->_ldap_port) {
                if ($this->_ldap_scheme == "ldaps") {
                    $this->_ldap_port = 636;
                } else {
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
            // Array
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
            if ( !$is_dn )
            {
                error_log("Username is not a DN");
                list($this->userid, $this->domain) = $this->_qualify_id($username);
                $root_dn = $this->_from_domain_to_rootdn($this->domain);
                $user_dn = $this->_get_user_dn($root_dn, '(mail=' . $username . ')');
                error_log("Found user DN: $user_dn for user: $username");
            }
            else
            {
                $user_dn = $username;
                $root_dn = "";
            }

            if ( ( $bind_ok = $this->_bind($user_dn, $password) ) == TRUE )
            {
                $this->_unbind();

                if (isset($_SESSION['user'])) {
                    $_SESSION['user']->user_root_dn = $root_dn;
                    $_SESSION['user']->user_bind_dn = $user_dn;
                    $_SESSION['user']->user_bind_pw = $password;
                    error_log("Successfully bound with User DN: " . $_SESSION['user']->user_bind_dn);
                } else {
                    error_log("Successfully bound with User DN: " . $user_dn . " but not saving it to the session");
                }

                return TRUE;
            }
            else
            {
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

        public function domain_add($domain, $domain_alias = FALSE, $prepopulate = TRUE)
        {
            // Apply some routines for access control to this function here.
            if ( $domain_alias )
                return $this->_domain_add_alias($domain, $domain_alias);
            else
                return $this->_domain_add_new($domain, $prepopulate);
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

            if ( $domain == "" )
                return FALSE;

            error_log("Searching for domain $domain");

            $this->_connect();

            error_log("From domain to root dn");

            if ( ( $this->_bind($conf->get('ldap', 'bind_dn'), $conf->get('ldap', 'bind_pw')) ) == FALSE )
            {
                error_log("WARNING: Invalid Service bind credentials supplied");
                $this->_bind($conf->manager_bind_dn, $conf->manager_bind_pw);
            }

            if ( ($results = ldap_search($this->_connection, $conf->get('domain_base_dn'), '(associatedDomain=' . $domain . ')')) == FALSE )
            {
                error_log("No results?");
                return FALSE;
            }

            $domain = ldap_first_entry($this->_connection, $results);

            $domain_info = ldap_get_attributes($this->_connection, $domain);

//            echo "<pre>"; print_r($domain_info); echo "</pre>";

            if ( isset($domain_info['inetDomainBaseDN'][0]) )
                $domain_rootdn = $domain_info['inetDomainBaseDN'][0];
            else
                $domain_rootdn = $this->_standard_root_dn($domain_info['associatedDomain']);

            $this->_unbind();

            error_log("Using $domain_rootdn");

            return $domain_rootdn;
        }

        public function domains_list() {
            $section = $this->conf->get('kolab', 'auth_mechanism');
            return $this->search($this->conf->get($section, 'domain_base_dn'), $this->conf->get($section, 'kolab_domain_filter'));
        }

        public function llist($base_dn, $filter)
        {
            return $this->_list($base_dn, $filter);
        }

        public function list_domains() {
            return $this->domains_list();
        }

        public function list_users() {
            return $this->users_list();
        }

        static function normalize_result($__result) {
            $conf = Conf::get_instance();

            $result = Array();

            for ($x = 0; $x < $__result["count"]; $x++) {
                $dn = $__result[$x]['dn'];
                $result[$dn] = Array();
                for ($y = 0; $y < $__result[$x]["count"]; $y++) {
                    $attr = $__result[$x][$y];

                    if ($__result[$x][$attr]["count"] == 1) {
                        $result[$dn][$attr] = $__result[$x][$attr][0];

                    } else {
                        $result[$dn][$attr] = Array();
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

        public function users_list() {
            return $this->search("ou=People,dc=klab,dc=cc", "(objectClass=kolabinetorgperson)", Array("uid"));
        }

        public function search($base_dn, $search_filter = '(objectClass=*)', $attributes = Array('*'))
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
            if ( count($username_parts) == 1 )
            {
                $domain_name = $conf->get('primary_domain');
            }
            else
            {
                $domain_name = array_pop($username_parts);
            }
            return array(implode('@', $username_parts), $domain_name);
        }

        /*
            Deprecated, use domain_root_dn()
        */

        public function _from_domain_to_rootdn($domain = '')
        {
            // Issue deprecation warning
            return $this->domain_root_dn($domain);
        }

        public function user_type_attribute_filter($type = FALSE)
        {
            global $conf;

            // If the user type does not exist, issue warning and continue with
            // the "All attributes" array.
            if ( !isset($conf->user_types[$type]) )
                return Array('*');

            $attributes_filter = Array();

            foreach ( $conf->user_types[$type]['attributes'] as $key => $value )
            {
                if ( is_array($value) )
                    $attributes_filter[] = $key;
                else
                    $attributes_filter[] = $value;
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
            if ( !isset($conf->user_types[$type]) )
                return "(objectClass=*)";

            $search_filter = "(&";
            // We want from user_types[$type]['attributes']['objectClasses']
            foreach ( $conf->user_types[$type]['attributes']['objectClass'] as $key => $value )
            {
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
            if ( ( $add_result = ldap_add($this->_connection, $entry_dn, $attributes) ) == FALSE )
            {
                // Issue warning
                return FALSE;
            }
            else
            {
                return TRUE;
            }
        }

        /*
            Shortcut to ldap_bind()
        */

        private function _bind($dn, $pw)
        {
            $this->_connect();

            error_log("->_bind() Binding with $dn");
            if ( !$dn || !$pw )
            {
                return FALSE;
            }

            if ( ( $bind_ok = ldap_bind($this->_connection, $dn, $pw) ) == FALSE )
            {
                error_log("LDAP Error: " . $this->_errstr());
                // Issue error message
                return FALSE;
            }
            else
            {
                return TRUE;
            }

        }

        /*
            Shortcut to ldap_connect()
        */

        private function _connect()
        {
            if ( ( $this->_connection ) == FALSE )
            {
                error_log("Connecting to " . $this->_ldap_server . " on port " . $this->_ldap_port);
                $connection = ldap_connect($this->_ldap_server, $this->_ldap_port);

                if ( $connection == FALSE )
                {
                    $this->_connection = FALSE;
                    error_log("Not connected: " . ldap_err2str() .  "(no.) " . ldap_errno());
                }
                else
                {
                    $this->_connection = $connection;
                }
                error_log("Connected!");
            }
            else {
                error_log("Already connected");
            }
        }

        /*
            Shortcut to ldap_disconnect()
        */

        private function _disconnect()
        {
            if ( ( $this->_connection ) == FALSE )
            {
                return TRUE;
            }
            else
            {
                if ( ( $result = ldap_close($this->_connection) ) == TRUE )
                {
                    $this->_connection = FALSE;
                    return TRUE;
                }
                else
                {
                    // Issue a warning
                    $this->_connection = FALSE;
                    $this->_ldap = FALSE;
                    return FALSE;
                }
            }
        }

        /*
            Shortcut to ldap_err2str() over ldap_errno()
        */

        private function _errstr()
        {
            if ( ( $errno = @ldap_errno($this->_connection) ) == TRUE )
            {
                if ( ( $err2str = @ldap_err2str($errno) ) == TRUE )
                {
                    return $err2str;
                }
                else
                {
                    // Issue warning
                    return NULL;
                }
            }
            else
            {
                // Issue warning
                return NULL;
            }
        }

        /*
            Shortcut to ldap_get_entries() over ldap_list()

            Takes a $base_dn and $filter like ldap_list(), and returns an
            array obtained through ldap_get_entries().
        */

        private function _list($base_dn, $filter)
        {
            $ldap_entries = Array( "count" => 0 );

            if ( ( $ldap_list = @ldap_list($this->_connection, $base_dn, $filter) ) == FALSE )
            {
                #message("LDAP Error: Could not search " . $base_dn . ": " . $this->_errstr() );
            }
            else
            {
                if ( ( $ldap_entries = @ldap_get_entries($this->_connection, $ldap_list) ) == FALSE )
                {
                    #message("LDAP Error: No entries for " . $filter . " in " . $base_dn . ": " . $this->_errstr());
                }
            }

            return $ldap_entries;
        }

        /*
            Shortcut to ldap_search()
        */

        private function _search($base_dn, $search_filter = '(objectClass=*)', $attributes = Array('*'))
        {
            error_log("Searching with user " . $_SESSION['user']->user_bind_dn);
            $this->_bind($_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw);

            if ( ( $search_results = @ldap_search($this->_connection, $base_dn, $search_filter, $attributes) ) == FALSE )
            {
                #message("Could not search in " . __METHOD__ . " in " . __FILE__ . " on line " . __LINE__ . ": " . $this->_errstr());
                return FALSE;
            }
            else
            {
                if ( ( $entries = ldap_get_entries($this->_connection, $search_results) ) == FALSE )
                {
                    #message("Could not get the results of the search: " . $this->_errstr());
                    return FALSE;
                }
                else
                {
                    return $entries;
                }
            }
        }

        /*
            Shortcut to ldap_unbind()
        */

        private function _unbind($yes = FALSE, $really = FALSE)
        {
            if ( $yes && $really )
            {
                ldap_unbind($this->_connection);
                $this->_connection = FALSE;
            }
            else
            {
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

        /*
            Probe the root dn with the user credentials.

            When a list of domains is retrieved, this does not mean the user
            actually has access. Given the root dn for each domain however, we
            can in fact attempt to list / search the root dn and see if we get
            any results. If we don't, maybe this user is not authorized for the
            domain at all?
        */

        private function _probe_root_dn($entry_root_dn)
        {
            error_log("Running for entry root dn: " . $entry_root_dn);
            if ( ( $tmp_connection = ldap_connect($this->_ldap_server) ) == FALSE )
            {
                #message("LDAP Error: " . $this->_errstr());
                return FALSE;
            }

            error_log("User DN: " . $_SESSION['user']->user_bind_dn);

            if ( ( $bind_success = ldap_bind($tmp_connection, $_SESSION['user']->user_bind_dn, $_SESSION['user']->user_bind_pw) ) == FALSE )
            {
                #message("LDAP Error: " . $this->_errstr());
                return FALSE;
            }

            if ( ( $list_success = ldap_list($tmp_connection, $entry_root_dn, '(objectClass=*)', Array('*', 'aci')) ) == FALSE )
            {
                #message("LDAP Error: " . $this->_errstr());
                return FALSE;
            }

#            print_r(ldap_get_entries($tmp_connection, $list_success));
/*
            if ( ( ldap_count_entries($tmp_connection, $list_success) == 0 ) == TRUE )
            {
                echo "<li>Listed things, but got no results";
                return FALSE;
            }
*/
            return TRUE;
        }

        /*
            From a domain name, such as 'kanarip.com', create a standard root
            dn, such as 'dc=kanarip,dc=com'.

            As the parameter $associatedDomains, either pass it an array (such
            as may have been returned by ldap_get_entries() or perhaps
            ldap_list()), where the function will assume the first value
            ($array[0]) to be the uber-level domain name, or pass it a string
            such as 'kanarip.nl'.

            Returns a string.
        */

        private function _standard_root_dn($associatedDomains)
        {
            if ( is_array($associatedDomains) )
            {
                // Usually, the associatedDomain in position 0 is the naming attribute associatedDomain
                if ( $associatedDomains['count'] > 1 )
                {
                    // Issue a debug message here
                    $relevant_associatedDomain = $associatedDomains[0];
                }
                else
                {
                    $relevant_associatedDomain = $associatedDomains[0];
                }
            }
            else
            {
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

        public function _get_user_dn($root_dn, $search_filter)
        {

            error_log("Searching for a user dn in $root_dn, with search filter: $search_filter");

            $this->_connect();

            if ( ( $this->_bind($this->conf->get('bind_dn'), $this->conf->get('bind_pw')) ) == FALSE )
            {
                #message("WARNING: Invalid Service bind credentials supplied");
                $this->_bind($this->conf->get('manager_bind_dn'), $this->conf->get('manager_bind_pw'));
            }

            $search_results = ldap_search($this->_connection, $root_dn, $search_filter);

            if ( ( ldap_count_entries($this->_connection, $search_results) == 0 ) == TRUE )
            {
                #message("No entries found for the user dn in " . __METHOD__);
                return FALSE;
            }

            if ( ( $first_entry = ldap_first_entry($this->_connection, $search_results) ) == FALSE )
                return FALSE;
            else
                $user_dn = ldap_get_dn($this->_connection, $first_entry);

            return $user_dn;
        }


        public function _get_email_address()
        {
            return "kanarip@kanarip.com";
        }

    }
?>


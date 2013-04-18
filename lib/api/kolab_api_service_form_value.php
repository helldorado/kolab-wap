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
 * Service providing functionality related to HTML forms generation/validation.
 */
class kolab_api_service_form_value extends kolab_api_service
{

    /**
     * Returns service capabilities.
     *
     * @param string $domain Domain name
     *
     * @return array Capabilities list
     */
    public function capabilities($domain)
    {
        return array(
            'generate'       => 'r',
            'validate'       => 'r',
            'select_options' => 'r',
            'list_options'   => 'r',
        );
    }

    /**
     * Generation of auto-filled field values.
     *
     * @param array $getdata   GET parameters
     * @param array $postdata  POST parameters. Required parameters:
     *                         - attributes: list of attribute names
     *                         - type_id: Type identifier
     *                         - object_type: Object type (user, group, etc.)
     *
     * @return array Response with attribute name as a key
     */
    public function generate($getdata, $postdata)
    {
        $attribs    = $this->object_type_attributes($postdata['object_type'], $postdata['type_id'], true, $type_key);
        $attributes = (array) $postdata['attributes'];
        $result     = array();

        $postdata['type_key'] = $type_key;

        foreach ($attributes as $attr_name) {
            if (empty($attr_name)) {
                continue;
            }

            $method_name = 'generate_' . strtolower($attr_name) . '_' . strtolower($postdata['object_type']);

            if (!method_exists($this, $method_name)) {
                Log::trace("Method $method_name doesn't exist");

                $method_name = 'generate_' . strtolower($attr_name);

                if (!method_exists($this, $method_name)) {
                    Log::trace("Method $method_name doesn't exist either");
                    continue;
                }
            }

            Log::trace("Executing method $method_name");
            $result[$attr_name] = $this->{$method_name}($postdata, $attribs);
        }

        Log::trace("Returning result: " . var_export($result, TRUE));

        return $result;
    }

    /**
     * Generation of values for fields of type LIST.
     *
     * @param array $getdata   GET parameters
     * @param array $postdata  POST parameters. Required parameters:
     *                         - attribute: attribute name
     *                         - type_id: Type identifier
     *                         - object_type: Object type (user, group, etc.)
     *
     * @return array Response with attribute name as a key
     */
    public function list_options($getdata, $postdata)
    {
        //console($postdata);

        $attribs   = $this->object_type_attributes($postdata['object_type'], $postdata['type_id'], true, $key_name);
        $attr_name = $postdata['attribute'];
        $result    = array(
            // return search value, so client can match response to request
            'search' => $postdata['search'],
            'list'   => array(),
        );

        if (empty($attr_name)) {
            return $result;
        }

        if ($key_name) {
            $postdata['type_key'] = $key_name;
        }

        $method_name = 'list_options_' . strtolower($attr_name) . '_' . strtolower($postdata['object_type']);

        if (!method_exists($this, $method_name)) {
            //console("Method $method_name doesn't exist");

            $method_name = 'list_options_' . strtolower($attr_name);

            if (!method_exists($this, $method_name)) {
                return $result;
            }
        }

        //console($method_name);

        $result['list'] = $this->{$method_name}($postdata, $attribs);

        return $result;
    }

    /**
     * Generation of values for fields of type SELECT.
     *
     * @param array $getdata   GET parameters
     * @param array $postdata  POST parameters. Required parameters:
     *                         - attributes: list of attribute names
     *                         - type_id: Type identifier
     *                         - object_type: Object type (user, group, etc.)
     *
     * @return array Response with attribute name as a key
     */
    public function select_options($getdata, $postdata)
    {
        //console("form_value.select_options postdata", $postdata);
        $attribs    = $this->object_type_attributes($postdata['object_type'], $postdata['type_id'], true, $key_name);
        $attributes = (array) $postdata['attributes'];
        $result     = array();

        if ($key_name) {
            $postdata['type_key'] = $key_name;
        }

        foreach ($attributes as $attr_name) {
            if (empty($attr_name)) {
                continue;
            }

            $method_name = 'select_options_' . strtolower($attr_name);

            if (method_exists($this, $method_name)) {
                $res = $this->{$method_name}($postdata, $attribs);
            }
            else {
                $res = array();
            }

            if (!is_array($res['list'])) {
                $res['list'] = array();
            }

            $result[$attr_name] = $res;
        }

        return $result;
    }

    /**
     * Validation of field values.
     *
     * @param array $getdata   GET parameters
     * @param array $postdata  POST parameters. Required parameters:
     *                         - type_id: Type identifier
     *                         - object_type: Object type (user, group, etc.)
     *
     * @return array Response with attribute name as a key
     */
    public function validate($getdata, $postdata)
    {
        $attribs = $this->object_type_attributes($postdata['object_type'], $postdata['type_id']);
        $result  = array();

        Log::trace("kolab_api_form_value::validate() \$postdata: " . var_export($postdata, TRUE));

        foreach ((array)$postdata as $attr_name => $attr_value) {
            if (empty($attr_name) || $attr_name == 'type_id' || $attr_name == 'object_type') {
                continue;
            }

            $method_name = 'validate_' . strtolower($attr_name) . '_' . strtolower($postdata['object_type']);

            if (!method_exists($this, $method_name)) {
                //console("Method $method_name doesn't exist");

                $method_name = 'validate_' . strtolower($attr_name);

                if (!method_exists($this, $method_name)) {
                    $result[$attr_name] = 'OK';
                    continue;
                }
            }

            if (array_key_exists($attr_name, $attribs['form_fields']) && empty($attribs['form_fields'][$attr_name]['optional'])) {
                $result[$attr_name] = $this->{$method_name}($attr_value, $postdata);
            } else if (array_key_exists($attr_name, $attribs['form_fields']) && empty($attribs['form_fields'][$attr_name]['validate'])) {
                $result[$attr_name] = $attr_value;
            } else {
                try {
                    $result[$attr_name] = $this->{$method_name}($attr_value, $postdata);
                } catch (Exception $e) {
                    Log::debug("Attribute $attr_name did not validate, but it is not a required attribute. Not saving. (Error was: $e)");
                }
            }
        }

        Log::trace("kolab_api_form_value::validate() \$result: " . var_export($result, TRUE));

        return $result;
    }

    private function generate_alias($postdata, $attribs = array())
    {
        $rcpt_pol_aliases = $this->generate_secondary_mail($postdata, $attribs);

        $service = $this->controller->get_service('user');
        $user_attrs  = $service->user_info(Array( "user" => $postdata['id']), null);

        if (!empty($user_attrs['alias'])) {
            $cur_aliases = $user_attrs['alias'];
        } else {
            $cur_aliases = Array();
        }

        if (!is_array($cur_aliases)) {
            $cur_aliases = (array)($cur_aliases);
        }

        sort($rcpt_pol_aliases);
        sort($cur_aliases);

        $form_aliases = array_unique(array_merge($rcpt_pol_aliases, $cur_aliases));

        Log::trace("kolab_api_service_form_value::generate_alias() \$form_aliases: " . var_export($form_aliases, TRUE));

        return array_values($form_aliases);
    }

    private function generate_astaccountcallerid($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['astaccountcallerid'])) {
            // Use Data Please
            foreach ($attribs['auto_form_fields']['astaccountcallerid']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            // TODO: Correct this with what is in 'data'...
            return $this->generate_cn($postdata, $attribs);
        }
    }

    private function generate_astaccountdefaultuser($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['astaccountdefaultuser'])) {
            // Use Data Please
            foreach ($attribs['auto_form_fields']['astaccountdefaultuser']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            return $this->generate_uid($postdata, $attribs);
        }
    }

    private function generate_astaccountmailbox($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['astaccountmailbox'])) {
            // Use Data Please
            foreach ($attribs['auto_form_fields']['astaccountmailbox']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            return $this->generate_uid($postdata, $attribs);
        }
    }

    private function generate_astaccountregistrationcontext($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['astaccountregistrationcontext'])) {
            // Use Data Please
            foreach ($attribs['auto_form_fields']['astaccountregistrationcontext']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            return $this->generate_uid($postdata, $attribs);
        }
    }

    private function generate_astaccountregistrationexten($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['astaccountregistrationexten'])) {
            $search = array(
                'params' => array(
                    'objectclass' => array(
                        'type'  => 'exact',
                        'value' => 'asterisksipuser',
                    ),
                ),
            );

            $auth  = Auth::get_instance($_SESSION['user']->get_domain());
            $conf  = Conf::get_instance();
            $users = $auth->list_users(NULL, Array('astaccountregistrationexten'), $search);

            $lower_astaccountregistrationexten = $conf->get('astaccountregistrationexten_lower_barrier');
            if (!$lower_astaccountregistrationexten) {
                $lower_astaccountregistrationexten = 200;
            }

            // Start at the lower barrier + 1
            $lower_astaccountregistrationexten = ($lower_astaccountregistrationexten + 1);

            $higher_astaccountregistrationexten = $conf->get('astaccountregistrationexten_higher_barrier');
            if (!$higher_astaccountregistrationexten) {
                $higher_astaccountregistrationexten = 300;
            }

            $astaccountregistrationextens = Array();

            foreach ($users['list'] as $dn => $attributes) {
                if (!array_key_exists('astaccountregistrationexten', $attributes)) {
                    continue;
                }

                if ($attributes['astaccountregistrationexten'] > $highest_astaccountregistrationexten) {
                    $astaccountregistrationextens[] = $attributes['astaccountregistrationexten'];
                }
            }

            for ($i = $lower_astaccountregistrationexten; $i < $higher_astaccountregistrationexten; $i++) {
                if (!in_array($i, $astaccountregistrationextens)) {
                    $astaccountregistrationexten = $i;
                    break;
                }
            }

            return $astaccountregistrationexten;
        }
    }

    private function generate_cn($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['cn'])) {
            // Use Data Please
            foreach ($attribs['auto_form_fields']['cn']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            // TODO: Generate using policy from configuration
            $cn = trim($postdata['givenname'] . " " . $postdata['sn']);

            return $cn;
        }
    }

    private function generate_cn_resource($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['cn'])) {
            // Use Data Please
            foreach ($attribs['auto_form_fields']['cn']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            $auth        = Auth::get_instance($_SESSION['user']->get_domain());
            $unique_attr = $this->unique_attribute();
            $cn          = $postdata['cn'];

            $x = 2;
            while (($resource_found = $auth->resource_find_by_attribute(array('cn' => $cn)))) {
                if (!empty($postdata['id'])) {
                    $resource_found_dn = key($resource_found);
                    $resource_found_unique_attr = $auth->get_entry_attribute($resource_found_dn, $unique_attr);
                    //console("resource with mail $mail found", $resource_found_unique_attr);
                    if ($resource_found_unique_attr == $postdata['id']) {
                        //console("that's us.");
                        break;
                    }
                }

                $cn = $postdata['cn'] . ' #' . $x;
                $x++;
            }

            return $cn;
        }
    }

    private function generate_displayname($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['displayname'])) {
            // Use Data Please
            foreach ($attribs['auto_form_fields']['displayname']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            // TODO: Generate using policy from configuration
            $displayname = $postdata['givenname'];
            if ($postdata['sn']) {
                $displayname = $postdata['sn'] . ", " . $displayname;
            }

            // TODO: Figure out what may be sent as an additional comment;
            //
            // Examples:
            //
            //  - van Meeuwen, Jeroen (Kolab Systems)
            //  - Doe, John (Contractor)
            //

            return $displayname;
        }
    }

    private function generate_gidnumber($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['gidnumber'])) {
            $auth = Auth::get_instance($_SESSION['user']->get_domain());
            $conf = Conf::get_instance();

            // TODO: Take a policy to use a known group ID, a known group (by name?)
            // and/or create user private groups.

            $search = Array(
                    'params' => Array(
                            'objectclass' => Array(
                                    'type' => 'exact',
                                    'value' => 'posixgroup',
                                ),
                        ),
                );

            $groups = $auth->list_groups(NULL, Array('gidnumber'), $search);

            $highest_gidnumber = $conf->get('gidnumber_lower_barrier');
            if (!$highest_gidnumber) {
                $highest_gidnumber = 999;
            }

            foreach ($groups['list'] as $dn => $attributes) {
                if (!array_key_exists('gidnumber', $attributes)) {
                    continue;
                }

                if ($attributes['gidnumber'] > $highest_gidnumber) {
                    $highest_gidnumber = $attributes['gidnumber'];
                }
            }

            $gidnumber = ($highest_gidnumber + 1);
            $postdata['gidnumber'] = $gidnumber;
            if (empty($postdata['uidnumber'])) {
                $uidnumber = $this->generate_uidnumber($postdata, $attribs);
                $gidnumber = $this->_highest_of_two($uidnumber, $gidnumber);
            }

            return $gidnumber;
        }
    }

    private function generate_homedirectory($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['homedirectory'])) {
            // Use Data Please
            foreach ($attribs['auto_form_fields']['homedirectory']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            // TODO: Home directory attribute to use
            $uid = $this->generate_uid($postdata, $attribs);

            // TODO: Home directory base path from configuration?

            return '/home/' . $uid;
        }
    }

    private function generate_kolabtargetfolder_resource($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['kolabtargetfolder'])) {
            // Use Data Please
            foreach ($attribs['auto_form_fields']['kolabtargetfolder']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            // TODO: Detect or from config
            $imap_hierarchysep = '/';
            $cn = $this->generate_cn_resource($postdata, $attribs);

            return 'shared' . $imap_hierarchysep . 'Resources' . $imap_hierarchysep . $cn . '@' . $_SESSION['user']->get_domain();
        }
    }

    private function generate_mail($postdata, $attribs = array())
    {
        return $this->generate_primary_mail($postdata, $attribs);
    }

    private function generate_mail_group($postdata, $attribs = array())
    {
        return $this->generate_primary_mail_group($postdata, $attribs);
    }

    private function generate_mail_resource($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['mail'])) {
            // Use Data Please
            foreach ($attribs['auto_form_fields']['mail']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            $resourcedata = kolab_recipient_policy::normalize_groupdata($postdata);
            //console("normalized resource data", $resourcedata);

            // TODO: Normalize $postdata
            $mail_local  = 'resource-' . $postdata['type_key'] . '-' . strtolower($resourcedata['cn']);
            $mail_domain = $_SESSION['user']->get_domain();
            $mail        = $mail_local . '@' . $mail_domain;
            $auth        = Auth::get_instance($_SESSION['user']->get_domain());
            $unique_attr = $this->unique_attribute();

            $x = 2;
            while (($resource_found = $auth->resource_find_by_attribute(array('mail' => $mail)))) {
                if (!empty($postdata['id'])) {
                    $resource_found_dn = key($resource_found);
                    $resource_found_unique_attr = $auth->get_entry_attribute($resource_found_dn, $unique_attr);
                    //console("resource with mail $mail found", $resource_found_unique_attr);
                    if ($resource_found_unique_attr == $postdata['id']) {
                        //console("that's us.");
                        break;
                    }
                }

                $mail = $mail_local . '-' . $x . '@' . $mail_domain;
                $x++;
            }

            return $mail;
        }
    }

    private function generate_mailalternateaddress($postdata, $attribs = array())
    {
        return $this->generate_secondary_mail($postdata, $attribs);
    }

    private function generate_mailhost($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['uidnumber'])) {
            // This value is determined by the Kolab Daemon
            return '';
        }
    }

    private function generate_password($postdata, $attribs = array())
    {
        // TODO: Password complexity policy.
        exec("head -c 200 /dev/urandom | tr -dc _A-Z-a-z-0-9 | head -c15", $userpassword_plain);
        return $userpassword_plain[0];
    }

    private function generate_userpassword($postdata, $attribs = array())
    {
        return $this->generate_password($postdata, $attribs);
    }

    private function generate_primary_mail($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['mail'])) {
            // Use Data Please
            foreach ($attribs['auto_form_fields']['mail']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            if (array_key_exists('uid', $attribs['auto_form_fields'])) {
                if (!array_key_exists('uid', $postdata)) {
                    $postdata['uid'] = $this->generate_uid($postdata, $attribs);
                }
            }

            $primary_mail = kolab_recipient_policy::primary_mail($postdata);

            return $primary_mail;
        }
    }

    private function generate_primary_mail_group($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['mail'])) {
            // Use Data Please
            foreach ($attribs['auto_form_fields']['mail']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            $primary_mail = kolab_recipient_policy::primary_mail_group($postdata);

            return $primary_mail;
        }
    }

    private function generate_secondary_mail($postdata, $attribs = array())
    {
        $secondary_mail_addresses = Array();

        if (isset($attribs['auto_form_fields'])) {
            if (isset($attribs['auto_form_fields']['alias'])) {
                $secondary_mail_key = 'alias';
            } elseif (isset($attribs['auto_form_fields']['mailalternateaddress'])) {
                $secondary_mail_key = 'mailalternateaddress';
            } else {
                throw new Exception("No valid input for secondary mail address(es)", 478);
            }

            foreach ($attribs['auto_form_fields'][$secondary_mail_key]['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 456789);
                }
            }

            if (array_key_exists('uid', $attribs['auto_form_fields'])) {
                if (!array_key_exists('uid', $postdata)) {
                    $postdata['uid'] = $this->generate_uid($postdata, $attribs);
                }
            }

            if (array_key_exists('mail', $attribs['auto_form_fields'])) {
                if (!array_key_exists('mail', $postdata)) {
                    $postdata['mail'] = $this->generate_primary_mail($postdata, $attribs);
                }
            }

            $_secondary_mail_addresses = kolab_recipient_policy::secondary_mail($postdata);

            // TODO: Check for uniqueness. Not sure what to do if not unique.
            $secondary_mail_addresses = Array();

            $auth = Auth::get_instance();
            $conf = Conf::get_instance();

            // Find the authentication mechanism in order to be able to fall back from a
            // '[$domain]' section setting for the mail attributes list, to an '[$auth_mech]'
            // section setting
            $auth_mech = $conf->get($_SESSION['user']->get_domain(), 'auth_mechanism');
            if (empty($auth_mech)) {
                $auth_mech = $conf->get('kolab', 'auth_mechanism');
            }
            if (empty($auth_mech)) {
                $auth_mech = 'ldap';
            }

            $mail_attrs = $conf->get_list($_SESSION['user']->get_domain(), 'mail_attributes');
            if (empty($mail_attrs)) {
                $mail_attrs = $conf->get_list($auth_mech, 'mail_attributes');
            }
            if (empty($mail_attrs)) {
                $mail_attrs = array('mail', 'alias');
            }

            foreach ($_secondary_mail_addresses as $num => $alias) {
                list($_local, $_domain) = explode("@", $alias);
                $local = $_local;

                $x = 2;
                while (($user_found = $auth->find_recipient($local . "@" . $_domain))) {
                    Log::trace(__FUNCTION__ . ": An entry with address " . $local . "@" . $_domain . " was found.");

                    if (!empty($postdata['id'])) {
                        $user_found_dn = key($user_found);
                        $user_found_unique_attr = $auth->get_entry_attribute($user_found_dn, $unique_attr);
                        if ($user_found_unique_attr == $postdata['id']) {
                            Log::trace(__FUNCTION__ . ": Entry with address " . $local . "@" . $_domain . " is actually us.");
                            break;
                        }
                    } // empty($postdata['id'])

                    // Otherwise this is a new user and therefore the entry found with
                    // this address is definitely not us

                    $local = $_local . $x;
                    $x++;
                }

                $secondary_mail_addresses[] = $local . "@" . $_domain;

            }

            if (in_array($postdata['mail'], $secondary_mail_addresses)) {
                Log::trace("Found primary mail as part of the secondary mail addresses");

                unset($secondary_mail_addresses[array_search($postdata['mail'], $secondary_mail_addresses)]);
            }

            return $secondary_mail_addresses;
        }
    }

    private function generate_uid($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['uid'])) {
            // Use Data Please
            foreach ($attribs['auto_form_fields']['uid']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }


            if (empty($postdata['uid'])) {
                $postdata['uid'] = $postdata['sn'];
            }

            $userdata = kolab_recipient_policy::normalize_userdata($postdata);

            $uid = kolab_recipient_policy::uid($userdata);
            Log::debug("uid from recipient policy: " . var_export($uid, TRUE));

            $orig_uid = $uid;

            $auth        = Auth::get_instance($_SESSION['user']->get_domain());
            $unique_attr = $this->unique_attribute();

            $x = 2;
            while (($user_found = $auth->user_find_by_attribute(array('uid' => $uid)))) {
                if (!empty($postdata['id'])) {
                    $user_found_dn = key($user_found);
                    $user_found_unique_attr = $auth->get_entry_attribute($user_found_dn, $unique_attr);
                    //console("user with uid $uid found", $user_found_unique_attr);
                    if ($user_found_unique_attr == $postdata['id']) {
                        //console("that's us.");
                        break;
                    }
                }

                $uid = $orig_uid . $x;
                $x++;
            }

            return $uid;
        }
    }

    private function generate_uidnumber($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['uidnumber'])) {
            $search = array(
                'params' => array(
                    'objectclass' => array(
                        'type'  => 'exact',
                        'value' => 'posixaccount',
                    ),
                ),
            );

            $auth  = Auth::get_instance($_SESSION['user']->get_domain());
            $conf  = Conf::get_instance();
            $users = $auth->list_users(NULL, Array('uidnumber'), $search);

            $highest_uidnumber = $conf->get('uidnumber_lower_barrier');
            if (!$highest_uidnumber) {
                $highest_uidnumber = 999;
            }

            foreach ($users['list'] as $dn => $attributes) {
                if (!array_key_exists('uidnumber', $attributes)) {
                    continue;
                }

                if ($attributes['uidnumber'] > $highest_uidnumber) {
                    $highest_uidnumber = $attributes['uidnumber'];
                }
            }

            $uidnumber = ($highest_uidnumber + 1);
            $postdata['uidnumber'] = $uidnumber;
            if (empty($postdata['gidnumber'])) {
                $gidnumber = $this->generate_gidnumber($postdata, $attribs);
                $uidnumber = $this->_highest_of_two($uidnumber, $gidnumber);
            }

            return $uidnumber;
        }
    }

    private function list_options_kolabdelegate($postdata, $attribs = array())
    {
        // return specified records only, by exact DN attributes
        if (!empty($postdata['list'])) {
            $data['page_size'] = count($postdata['list']);
            $data['search'] = array(
                'entrydn' => array(
                    'value' => $postdata['list'],
                    'type'  => 'exact',
                ),
            );
        }
        // return records with specified string
        else {
            $keyword = array('value' => $postdata['search']);
            $data['page_size'] = 15;
            $data['search']    = array(
                'displayname' => $keyword,
                'cn'          => $keyword,
                'mail'        => $keyword,
            );
        }

        $data['attributes'] = array('displayname', 'mail');

        $service = $this->controller->get_service('users');
        $result  = $service->users_list(null, $data);
        $list    = $result['list'];

        // convert to key=>value array
        foreach ($list as $idx => $value) {
            $list[$idx] = $value['displayname'];
            if (!empty($value['mail'])) {
                $list[$idx] .= ' <' . $value['mail'] . '>';
            }
        }

        return $list;
    }

    private function list_options_member($postdata, $attribs = array())
    {
        return $this->_list_options_members($postdata, $attribs);
    }

    private function list_options_nsrole($postdata, $attribs = array())
    {
        //console("Listing options for attribute 'nsrole', while the expected attribute to use is 'nsroledn'");
        return $this->list_options_nsroledn($postdata, $attribs);
    }

    private function list_options_nsroledn($postdata, $attribs = Array())
    {
        // return specified records only, by exact DN attributes
        if (!empty($postdata['list'])) {
            $data['page_size'] = count($postdata['list']);
            $data['search'] = array(
                'entrydn' => array(
                    'value' => $postdata['list'],
                    'type'  => 'exact',
                ),
            );
        }
        // return records with specified string
        else {
            $keyword = array('value' => $postdata['search']);
            $data['page_size']  = 15;
            $data['search']     = array(
                'displayname' => $keyword,
                'cn'          => $keyword,
                'mail'        => $keyword,
            );
        }

        $data['attributes'] = array('cn');

        $service = $this->controller->get_service('roles');
        $result  = $service->roles_list(null, $data);
        $list    = $result['list'];

        // convert to key=>value array
        foreach ($list as $idx => $value) {
            $list[$idx] = $value['cn'];
        }

        return $list;
    }

    private function list_options_uniquemember($postdata, $attribs = array())
    {
        $result = $this->_list_options_members($postdata, $attribs);
        return $result;
    }

    private function list_options_uniquemember_resource($postdata, $attribs = array())
    {
        return $this->_list_options_resources($postdata, $attribs);
    }

    private function select_options_c($postdata, $attribs = array())
    {
        return $this->_select_options_from_db('c');
    }

    private function select_options_objectclass($postdata, $attribs = array())
    {
        $auth = Auth::get_instance();
        $list = $auth->schema_classes();

        if (is_array($list)) {
            sort($list);
        }

        return array('list' => $list);
    }

    private function select_options_attribute($postdata, $attribs = array())
    {
        $auth = Auth::get_instance();
        $list = $auth->schema_attributes($postdata['classes']);

        if (is_array($list['may'])) {
            // return required + optional
            if (is_array($list['must']) && !empty($list['must'])) {
                $list['may'] = array_unique(array_merge($list['may'], $list['must']));
            }
            sort($list['may']);
        }

        return array(
            'list'     => $list['may'],
            'required' => $list['must']
        );
    }

    private function select_options_ou($postdata, $attribs = array())
    {
        $auth = Auth::get_instance();
        $conf = Conf::get_instance();

        $unique_attr = $this->unique_attribute();
        $object_type = $postdata['object_type'];
        $object_key  = $postdata['type_key'];

        $base_dn = $auth->subject_base_dn($object_key, $object_type);

        if (!empty($postdata['id'])) {
            $subjects = $auth->search($base_dn, '(' . $unique_attr . '=' . $postdata['id'] . ')')->entries(true);

            if ($subjects) {
                $subject = array_shift($subjects);
                $subject_dn = key($subject);
                $subject_dn_components = ldap_explode_dn($subject_dn, 0);

                if ($subject_dn_components) {
                    unset($subject_dn_components['count']);
                    array_shift($subject_dn_components);
                    $default = strtolower(implode(',', $subject_dn_components));
                }
            }
        }

        if (empty($default)) {
            $default = $base_dn;
        }

        $ous  = $auth->search($base_dn, '(objectclass=organizationalunit)');
        $_ous = array();

        foreach ($ous->entries(true) as $ou_dn => $ou_attrs) {
            $_ous[] = strtolower($ou_dn);
        }

        sort($_ous);

        return array(
            'list'    => $_ous,
            'default' => strtolower($default),
        );
    }

    private function select_options_preferredlanguage($postdata, $attribs = array())
    {
        $options = $this->_select_options_from_db('preferredlanguage');
        $conf    = Conf::get_instance();
        $default = $conf->get('default_locale');

        if (!$default) {
            $default = 'en_US';
        }

        if (!empty($postdata['preferredlanguage'])) {
            $default = $postdata['preferredlanguage'];
        }

        $options['default'] = $default;

        return $options;
    }

    private function validate_alias($value)
    {
        $auth = Auth::get_instance();
        $conf = Conf::get_instance();
        if (!is_array($value)) {
            $value = (array)($value);
        }

        foreach ($value as $mail_address) {
            if (!$this->_validate_email_address($mail_address)) {
                throw new Exception("Invalid email address '$mail_address'", 692);
            }

            // Only validate the 'alias' attribute is in any of my domain name
            // spaces if indeed it is listed as a mail attribute.
            if (in_array('alias', $conf->get_list('mail_attributes'))) {
                if (!$this->_validate_email_address_in_any_of_my_domains($mail_address)) {
                    throw new Exception("Email address '$mail_address' not in local domain", 693);
                }
            }
        }

    }

    private function validate_associateddomain($value)
    {
        return $value;

        $auth = Auth::get_instance();
        $conf = Conf::get_instance();

        if (!is_array($value)) {
            $value = (array)($value);
        }

        //console("form_value.validate_associateddomain(\$value)", $value);

        return $value;

    }

    private function validate_astaccountrealmedpassword($value, $postdata)
    {
        if (!array_key_exists('userpassword', $postdata) || empty($postdata['userpassword'])) {
            return $value;
        }

        if (!array_key_exists('uid', $postdata) || empty($postdata['uid'])) {
            $postdata['uid'] = $this->generate_uid($postdata);
        }

        $str = $postdata['uid'] . ":" . $_SESSION['user']->get_domain() . ":" . $postdata['userpassword'];

        Log::trace("Inserting astaccountrealmedpassword with value md5('" . $str . "');");

        return md5($str);
    }

    private function validate_mail($value)
    {
        $auth = Auth::get_instance();
        $conf = Conf::get_instance();
        if (!is_array($value)) {
            $value = (array)($value);
        }

        foreach ($value as $mail_address) {
            if (!$this->_validate_email_address($mail_address)) {
                throw new Exception("Invalid email address '$mail_address'", 692);
            }

            // Only validate the 'mail' attribute is in any of my domain name
            // spaces if indeed it is listed as a mail attribute.
            if (in_array('mail', $conf->get_list('mail_attributes'))) {
                if (!$this->_validate_email_address_in_any_of_my_domains($mail_address)) {
                    throw new Exception("Email address '$mail_address' not in local domain", 693);
                }
            }
        }
    }

    private function validate_mailquota($value)
    {
        //return (int)($value);
        return $value;
    }

    private function validate_mailalternateaddress($value)
    {
        $auth = Auth::get_instance();
        $conf = Conf::get_instance();
        if (!is_array($value)) {
            $value = (array)($value);
        }

        foreach ($value as $mail_address) {
            if (!$this->_validate_email_address($mail_address)) {
                throw new Exception("Invalid email address '$mail_address'", 692);
            }

            // Only validate the 'mailalternateaddress' attribute is in any of my domain name
            // spaces if indeed it is listed as a mail attribute.
            if (in_array('mailalternateaddress', $conf->get_list('mail_attributes'))) {
                if (!$this->_validate_email_address_in_any_of_my_domains($mail_address)) {
                    throw new Exception("Email address '$mail_address' not in local domain", 693);
                }
            }
        }
    }

    private function _highest_of_two($one, $two)
    {
        if ($one > $two) {
            return $one;
        } elseif ($one == $two) {
            return $one;
        } else {
            return $two;
        }
    }

    private function _list_options_members($postdata, $attribs = array())
    {
        // return specified records only, by exact DN attributes
        if (!empty($postdata['list'])) {
            $data['page_size'] = count($postdata['list']);
            $data['search'] = array(
                    'params' => array(
                            'entrydn' => array(
                                    'value' => $postdata['list'],
                                    'type'  => 'exact',
                                ),
                        ),
                    'operator' => 'OR'
                );
        }
        // return records with specified string
        else {
            $keyword = array('value' => $postdata['search'], 'type' => 'both');
            $data['page_size'] = 15;
            $data['search']    = array(
                    'params' => array(
                            'displayname' => $keyword,
                            'cn'          => $keyword,
                            'mail'        => $keyword,
                        ),
                    'operator' => 'OR'
                );
        }

        $data['attributes'] = array('displayname', 'cn', 'mail');

        $service = $this->controller->get_service('users');
        $result  = $service->users_list(null, $data);

        $list    = $result['list'];

        $data['attributes'] = array('cn', 'mail');

        $service = $this->controller->get_service('groups');
        $result  = $service->groups_list(null, $data);
        $list    = array_merge($list, $result['list']);

        // convert to key=>value array
        foreach ($list as $idx => $value) {
            if (!empty($value['displayname'])) {
                $list[$idx] = $value['displayname'];
            } elseif (!empty($value['cn'])) {
                $list[$idx] = $value['cn'];
            } else {
                //console("No display name or cn for $idx");
            }

            if (!empty($value['mail'])) {
                $list[$idx] .= ' <' . $value['mail'] . '>';
            }
        }

        // Sort and slice
        asort($list);

        if (!empty($data['page_size'])) {
            $list = array_slice($list, 0, $data['page_size']);
        }

        return $list;
    }

    private function _list_options_resources($postdata, $attribs = array())
    {
        // return specified records only, by exact DN attributes
        if (!empty($postdata['list'])) {
            $data['page_size'] = count($postdata['list']);
            $data['search'] = array(
                'entrydn' => array(
                    'value' => $postdata['list'],
                    'type'  => 'exact',
                ),
            );
        }
        // return records with specified string
        else {
            $keyword = array('value' => $postdata['search']);
            $data['page_size'] = 15;
            $data['search']    = array(
                'cn'          => $keyword,
            );
        }

        $data['attributes'] = array('cn');

        //console("api/form_value._list_options_resources() searching with data", $data);

        $service = $this->controller->get_service('resources');
        $result  = $service->resources_list(null, $data);
        $list    = $result['list'];

        // convert to key=>value array
        foreach ($list as $idx => $value) {
            if (!empty($value['displayname'])) {
                $list[$idx] = $value['displayname'];
            } elseif (!empty($value['cn'])) {
                $list[$idx] = $value['cn'];
            } else {
                //console("No display name or cn for $idx");
            }

        }

        return $list;
    }

    private function _select_options_from_db($attribute)
    {
        if (empty($attribute)) {
            return false;
        }

        $db     = SQL::get_instance();
        $result = $db->fetch_assoc($db->query("SELECT option_values FROM options WHERE attribute = ?", $attribute));
        $result = json_decode($result['option_values']);

        return array('list' => $result);
    }

    private function _validate_email_address($mail_address)
    {
        $valid = true;

        $at_index = strrpos($mail_address, "@");
        if (is_bool($at_index) && !$at_index) {
            $valid = false;

        } else {
            $domain = substr($mail_address, $at_index+1);
            $local = substr($mail_address, 0, $at_index);

            if (strlen($local) < 1 || strlen($local) > 64) {
                // local part length exceeded
                //console("Local part of email address is longer than permitted");
                $valid = false;

            } else if (strlen($domain) < 1 || strlen($domain) > 255) {
                // domain part length exceeded
                //console("Domain part of email address is longer than permitted");
                $valid = false;

            } else if ($local[0] == '.' || $local[strlen($local)-1] == '.') {
                // local part starts or ends with '.'
                //console("Local part of email address starts or ends with '.'");
                $valid = false;

            } else if (preg_match('/\\.\\./', $local)) {
                //console("Local part contains two consecutive dots");
                // local part has two consecutive dots
                $valid = false;

            } else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
                // character not valid in domain part
                //console("Invalid character in domain part");
                $valid = false;

            } else if (preg_match('/\\.\\./', $domain)) {
                // domain part has two consecutive dots
                //console("Domain part contains two consecutive dots");
                $valid = false;

            } else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\","",$local))) {
                // character not valid in local part unless
                // local part is quoted
                if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\","",$local))) {
                    //console("Unquoted invalid character in local part");
                    $valid = false;
                }
            }

//            if ($valid && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A"))) {
//                // domain not found in DNS
//                $valid = false;
//            }
        }

        return $valid;
    }

    private function _validate_email_address_in_any_of_my_domains($mail_address)
    {
        $at_index = strrpos($mail_address, "@");
        if (is_bool($at_index) && !$at_index) {
            throw new Exception("Invalid email address: No domain name space", 235);
        } else {
            $email_domain = substr($mail_address, $at_index+1);
        }

        $my_primary_domain = $_SESSION['user']->get_domain();

        if ($email_domain == $my_primary_domain) {
            return true;
        }

        $auth          = Auth::get_instance();
        $conf          = Conf::get_instance();
        $all_domains   = $auth->list_domains();
        $all_domains   = $all_domains['list'];
        $valid_domains = array();
        $dna           = $conf->get('domain_name_attribute');
        $valid         = false;

        Log::trace("_validate_email_address_in_any_of_mydomains(\$mail_address = " . var_export($mail_address, TRUE) . ")");
        Log::trace("\$all_domains includes: " . var_export($all_domains, TRUE) . " (must include domain for \$mail_address)");

        foreach ($all_domains as $domain_id => $domain_attrs) {
            if (!is_array($domain_attrs[$dna])) {
                $domain_attrs[$dna] = (array)($domain_attrs[$dna]);
            }

            if (in_array($my_primary_domain, $domain_attrs[$dna])) {
                $valid_domains = array_merge($valid_domains, $domain_attrs[$dna]);
            }
        }

        if (in_array($email_domain, $valid_domains)) {
            $valid = true;
        }

        if ($valid) {
            Log::trace("Found email address to be in one of my domains.");
        } else {
            Log::trace("Found email address to NOT be in one of my domains.");
        }

        return $valid;
    }

}

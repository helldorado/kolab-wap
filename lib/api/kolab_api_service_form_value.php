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
        $attribs    = $this->object_type_attributes($postdata['object_type'], $postdata['type_id']);

        $attributes = (array) $postdata['attributes'];
        $result     = array();

        foreach ($attributes as $attr_name) {
            if (empty($attr_name)) {
                continue;
            }

            $method_name = 'generate_' . strtolower($attr_name) . '_' . strtolower($postdata['object_type']);

            if (!method_exists($this, $method_name)) {
                //console("Method $method_name doesn't exist");

                $method_name = 'generate_' . strtolower($attr_name);

                if (!method_exists($this, $method_name)) {
                    continue;
                }
            }

            $result[$attr_name] = $this->{$method_name}($postdata, $attribs);
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

        foreach ((array)$postdata as $attr_name => $attr_value) {
            if (empty($attr_name) || $attr_name == 'type_id' || $attr_name == 'object_type') {
                continue;
            }

            $method_name = 'validate_' . strtolower($attr_name);

            if (!method_exists($this, $method_name)) {
                $result[$attr_name] = 'OK';
                continue;
            }

            $result[$attr_name] = $this->{$method_name}($attr_value);
        }

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

        $attribs    = $this->object_type_attributes($postdata['object_type'], $postdata['type_id']);
        $attributes = (array) $postdata['attributes'];
        $result     = array();

        foreach ($attributes as $attr_name) {
            if (empty($attr_name)) {
                continue;
            }

            $method_name = 'select_options_' . strtolower($attr_name);

            if (!method_exists($this, $method_name)) {
                $result[$attr_name] = array();
                continue;
            }

            $result[$attr_name] = $this->{$method_name}($postdata, $attribs);
        }

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

        $attribs   = $this->object_type_attributes($postdata['object_type'], $postdata['type_id']);
        $attr_name = $postdata['attribute'];
        $result    = array(
            // return search value, so client can match response to request
            'search' => $postdata['search'],
            'list'   => array(),
        );

        if (empty($attr_name)) {
            return $result;
        }

        $method_name = 'list_options_' . strtolower($attr_name);

        //console($method_name);

        if (!method_exists($this, $method_name)) {
            return $result;
        }

        //console("Still here");

        $result['list'] = $this->{$method_name}($postdata, $attribs);

        return $result;
    }

    private function generate_alias($postdata, $attribs = array())
    {
        return $this->generate_secondary_mail($postdata, $attribs);
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

            foreach ($groups as $dn => $attributes) {
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

    private function generate_mail($postdata, $attribs = array())
    {
        return $this->generate_primary_mail($postdata, $attribs);
    }

    private function generate_mail_group($postdata, $attribs = array())
    {
        return $this->generate_primary_mail_group($postdata, $attribs);
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

            $secondary_mail_addresses = kolab_recipient_policy::secondary_mail($postdata);

            // TODO: Check for uniqueness. Not sure what to do if not unique.

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

            // TODO: Use preferredlanguage
            if (isset($postdata['preferredlanguage'])) {
                //console("Using locale for " . $postdata['preferredlanguage']);
                setlocale(LC_ALL, $postdata['preferredlanguage']);
            }
/*            else {
                //console("No locale specified...!");
            }
*/

            $uid = iconv('UTF-8', 'ASCII//TRANSLIT', $postdata['sn']);
            $uid = strtolower($uid);
            $uid = preg_replace('/[^a-z-_]/i', '', $uid);

            $orig_uid = $uid;

            $auth = Auth::get_instance($_SESSION['user']->get_domain());
            $conf = Conf::get_instance();

            $unique_attr = $conf->get('unique_attribute');
            if (!$unique_attr) {
                $unique_attr = 'nsuniqueid';
            }

            $x = 2;
            while (($user_found = $auth->user_find_by_attribute(array('uid' => $uid)))) {
                if (!empty($postdata['id'])) {
                    $user_found_dn = key($user_found);
                    $user_found_unique_attr = $auth->get_attribute($user_found_dn, $unique_attr);
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
            $auth = Auth::get_instance($_SESSION['user']->get_domain());
            $conf = Conf::get_instance();

            $search = Array(
                    'params' => Array(
                            'objectclass' => Array(
                                    'type' => 'exact',
                                    'value' => 'posixaccount',
                                ),
                        ),
                );

            $users = $auth->list_users(NULL, Array('uidnumber'), $search);

            $highest_uidnumber = $conf->get('uidnumber_lower_barrier');
            if (!$highest_uidnumber) {
                $highest_uidnumber = 999;
            }

            foreach ($users as $dn => $attributes) {
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
        error_log("Listing options for attribute 'nsrole', while the expected attribute to use is 'nsroledn'");
        return $this->list_options_nsroledn($postdata, $attribs);
    }

    private function list_options_nsroledn($postdata, $attribs = Array())
    {
        // return specified records only, by exact DN attributes
        if (!empty($postdata['list'])) {
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
        return $this->_list_options_members($postdata, $attribs);
    }

    private function select_options_c($postdata, $attribs = array())
    {
        return $this->_select_options_from_db('c');
    }

    private function select_options_ou($postdata, $attribs = array())
    {
        $auth = Auth::get_instance();
        $conf = Conf::get_instance();

        $unique_attr = $conf->get('unique_attribute');
        if (!$unique_attr) {
            $unique_attr = 'nsuniqueid';
        }

        $base_dn = $conf->get('user_base_dn');
        if (!$base_dn) {
            $base_dn = $conf->get('base_dn');
        }

        if (!empty($postdata['id'])) {
            $subject = $auth->search($base_dn, '(' . $unique_attr . '=' . $postdata['id'] . ')');
            $subject_dn = $subject[0];
            $subject_dn_components = ldap_explode_dn($subject_dn, 0);
            unset($subject_dn_components['count']);
            array_shift($subject_dn_components);
            $default = strtolower(implode(',', $subject_dn_components));
        } else {
            $default = $base_dn;
        }

        $ous = $auth->search($base_dn, '(objectclass=organizationalunit)');

        $_ous = array();

        foreach ($ous as $ou) {
            $_ous[] = strtolower($ou);
        }

        sort($_ous);

        $_ous['default'] = strtolower($default);

        return $_ous;
    }

    private function select_options_preferredlanguage($postdata, $attribs = array())
    {
        $options = $this->_select_options_from_db('preferredlanguage');

        $conf = Conf::get_instance();
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

    private function _select_options_from_db($attribute)
    {

        if (empty($attribute)) {
            return false;
        }

        $db = SQL::get_instance();
        $result = $db->fetch_assoc($db->query("SELECT option_values FROM options WHERE attribute = ?", $attribute));

        $result = json_decode($result['option_values']);

        if (empty($result)) {
            return false;
        } else {
            return $result;
        }
    }

    private function _list_options_members($postdata, $attribs = array())
    {
        // return specified records only, by exact DN attributes
        if (!empty($postdata['list'])) {
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

        return $list;
    }

    private function _highest_of_two($one, $two) {
        if ($one > $two) {
            return $one;
        } elseif ($one == $two) {
            return $one;
        } else {
            return $two;
        }
    }
}

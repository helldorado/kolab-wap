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
     *                         - user_type_id or group_type_id: Type identifier
     *
     * @return array Response with attribute name as a key
     */
    public function generate($getdata, $postdata)
    {
        if (isset($postdata['user_type_id'])) {
            $attribs = $this->user_type_attributes($postdata['user_type_id']);
        }
        else if (isset($postdata['group_type_id'])) {
            $attribs = $this->group_type_attributes($postdata['group_type_id']);
        }
        else {
            $attribs = array();
        }

        $attributes = (array) $postdata['attributes'];
        $result     = array();

        foreach ($attributes as $attr_name) {
            if (empty($attr_name)) {
                continue;
            }

            $method_name = 'generate_' . strtolower($attr_name);

            if (!method_exists($this, $method_name)) {
                continue;
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
     *                         - user_type_id or group_type_id: Type identifier
     *
     * @return array Response with attribute name as a key
     */
    public function validate($getdata, $postdata)
    {
        if (isset($postdata['user_type_id'])) {
            $attribs = $this->user_type_attributes($postdata['user_type_id']);
        }
        else if (isset($postdata['group_type_id'])) {
            $attribs = $this->group_type_attributes($postdata['group_type_id']);
        }
        else {
            $attribs = array();
        }

        $result = array();

        foreach ((array)$postdata as $attr_name => $attr_value) {
            if (empty($attr_name)) {
                continue;
            }
            if (preg_match('/^[a-z]+_type_id$/i', $attr_name)) {
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
     *                         - user_type_id or group_type_id: Type identifier
     *
     * @return array Response with attribute name as a key
     */
    public function select_options($getdata, $postdata)
    {
        if (isset($postdata['user_type_id'])) {
            $attribs = $this->user_type_attributes($postdata['user_type_id']);
        }
        else if (isset($postdata['group_type_id'])) {
            $attribs = $this->group_type_attributes($postdata['group_type_id']);
        }
        else {
            $attribs = array();
        }

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
     *                         - user_type_id or group_type_id: Type identifier
     *
     * @return array Response with attribute name as a key
     */
    public function list_options($getdata, $postdata)
    {
        if (isset($postdata['user_type_id'])) {
            $attribs = $this->user_type_attributes($postdata['user_type_id']);
        }
        else if (isset($postdata['group_type_id'])) {
            $attribs = $this->group_type_attributes($postdata['group_type_id']);
        }
        else {
            $attribs = array();
        }

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

        if (!method_exists($this, $method_name)) {
            return $result;
        }

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
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['uidnumber'])) {
            $auth = Auth::get_instance($_SESSION['user']->get_domain());

            // TODO: Take a policy to use a known group ID, a known group (by name?)
            // and/or create user private groups.
            return 500;
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

            $primary_mail = kolab_recipient_policy::primary_mail($postdata);

            return $primary_mail;
        }
    }

    private function generate_secondary_mail($postdata, $attribs = array())
    {
        $secondary_mail_address = Array();

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

            $secondary_mail = kolab_recipient_policy::secondary_mail($postdata);

            return $secondary_mail;
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
                console("Using locale for " . $postdata['preferredlanguage']);
                setlocale(LC_ALL, $postdata['preferredlanguage']);
            } else {
                console("No locale specified...!");
            }

            $uid = iconv('UTF-8', 'ASCII//TRANSLIT', $postdata['sn']);
            $uid = strtolower($uid);
            $uid = preg_replace('/[^a-z-_]/i', '', $uid);

            $orig_uid = $uid;

            $auth = Auth::get_instance($_SESSION['user']->get_domain());

            $x = 2;
            while ($auth->user_find_by_attribute(array('uid' => $uid))) {
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

            // TODO: Actually poll $auth for users with a uidNumber set, and take the next one.
            return 500;
        }
    }

    private function select_options_preferredlanguage($postdata, $attribs = array())
    {
        $db        = SQL::get_instance();
        $query     = $db->query("SELECT option_values FROM options WHERE attribute = 'preferredlanguage'");
        $attribute = $db->fetch_assoc($query);

        return json_decode($attribute['option_values']);
    }

    private function list_options_uniquemember($postdata, $attribs = array())
    {
        $service = $this->controller->get_service('users');

        $keyword = array('value' => $postdata['search']);
        $data    = array(
            'attributes' => array('displayname', 'mail'),
            'page_size'  => 15,
            'search'     => array(
                'displayname' => $keyword,
                'cn'          => $keyword,
                'mail'        => $keyword,
            ),
        );

        $result = $service->users_list(null, $data);
        $list   = $result['list'];

        // convert to key=>value array
        foreach ($list as $idx => $value) {
            $list[$idx] = $value['displayname'];
            if (!empty($value['mail'])) {
                $list[$idx] .= ' <' . $value['mail'] . '>';
            }
        }

        return $list;
    }

    private function list_options_nsrole($postdata, $attribs = array())
    {
        $service = $this->controller->get_service('roles');

        $keyword = array('value' => $postdata['search']);
        $data    = array(
            'attributes' => array('displayname', 'mail'),
            'page_size'  => 15,
            'search'     => array(
                'displayname' => $keyword,
                'cn'          => $keyword,
                'mail'        => $keyword,
            ),
        );

        $result = $service->roles_list(null, $data);
        $list   = $result['list'];

        // convert to key=>value array
        foreach ($list as $idx => $value) {
            $list[$idx] = is_array($value['cn']) ? implode('/', $value['cn']) : $value['cn'];
        }

        return $list;
    }
}

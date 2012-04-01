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
 * Service providing user data management
 */
class kolab_api_service_user extends kolab_api_service
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
            'add' => 'w',
            'delete' => 'w',
            'edit' => 'w',
//            'find' => 'r',
//            'find_by_any_attribute' => 'r',
//            'find_by_attribute' => 'r',
//            'find_by_attributes' => 'r',
            'info' => 'r',
        );
    }

    /**
     * Create user.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array|bool User attributes or False on error.
     */
    public function user_add($getdata, $postdata)
    {
        console("user_add()", $postdata);

        $uta             = $this->object_type_attributes('user', $postdata['type_id']);
        $form_service    = $this->controller->get_service('form_value');
        $user_attributes = array();

        if (isset($uta['form_fields'])) {
            foreach ($uta['form_fields'] as $key => $value) {
                if (
                        (!isset($postdata[$key]) || empty($postdata[$key])) &&
                        (!array_key_exists('optional', $value) || !$value['optional'])
                    ) {
                    throw new Exception("Missing input value for $key", 345);
                }
                else {
                    $user_attributes[$key] = $postdata[$key];
                }
            }
        }

        if (isset($uta['auto_form_fields'])) {
            foreach ($uta['auto_form_fields'] as $key => $value) {
                if (empty($postdata[$key])) {
                    console("Key $key empty in \$postdata");
                    // If the attribute is marked as optional, however...
                    if (!array_key_exists('optional', $value) || !$value['optional']) {
                        $postdata['attributes'] = array($key);
                        $res                    = $form_service->generate($getdata, $postdata);
                        $postdata[$key]         = $res[$key];
                        $user_attributes[$key] = $postdata[$key];
                    }
                } else {
                    $user_attributes[$key] = $postdata[$key];
                }
            }
        }

        if (isset($uta['fields'])) {
            foreach ($uta['fields'] as $key => $value) {
                if (empty($postdata[$key])) {
                    $user_attributes[$key] = $uta['fields'][$key];
                } else {
                    $user_attributes[$key] = $uta['fields'][$key];
                }
            }
        }

        console("user_add()", $user_attributes);

        $auth = Auth::get_instance();
        $result = $auth->user_add($user_attributes, $postdata['type_id']);

        if ($result) {
            return $user_attributes;
        }

        return false;
    }

    /**
     * Detete user.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return bool True on success, False on failure
     */
    public function user_delete($getdata, $postdata)
    {
        if (!isset($postdata['user'])) {
            return false;
        }

        // TODO: Input validation
        $auth   = Auth::get_instance();
        $result = $auth->user_delete($postdata['user']);

        if ($result) {
            return $result;
        }

        return false;
    }

    public function user_edit($getdata, $postdata)
    {
        console("\$postdata to user_edit()", $postdata);

        $uta             = $this->object_type_attributes('user', $postdata['type_id']);
        $form_service    = $this->controller->get_service('form_value');
        $user_attributes = array();

        // Get the type "key" string for the next few settings.
        if ($postdata['type_id'] == null) {
            $type_str = 'user';
        }
        else {
            $db   = SQL::get_instance();
            $_key = $db->fetch_assoc($db->query("SELECT `key` FROM user_types WHERE id = ?", $postdata['type_id']));
            $type_str = $_key['key'];
        }

        $conf = Conf::get_instance();

        $unique_attr = $conf->get('unique_attribute');
        if (!$unique_attr) {
            $unique_attr = 'nsuniqueid';
        }
        // TODO: "rdn" is somewhat LDAP specific, but not used as something
        // LDAP specific...?
        $rdn_attr = $conf->get($type_str . '_user_name_attribute');
        if (!$rdn_attr) {
            $rdn_attr = $conf->get('user_name_attribute');
        }
        if (!$rdn_attr) {
            $rdn_attr = 'uid';
        }

        // Obtain the original user's information.
        $auth = Auth::get_instance();
        $auth->connect();

        // Now that values have been re-generated where necessary, compare
        // the new group attributes to the original group attributes.
        $_user = $auth->user_find_by_attribute(Array($unique_attr => $postdata[$unique_attr]));

        if (!$_user) {
            console("Could not find user");
            return false;
        }

        $_user_dn = key($_user);
        $_user = $this->user_info(Array('user' => $_user_dn), Array());

        if (isset($uta['form_fields'])) {
            foreach ($uta['form_fields'] as $key => $value) {
                if (
                        (!isset($postdata[$key]) || empty($postdata[$key])) &&
                        (!array_key_exists('optional', $value) || !$value['optional']) 
                    ) {
                    throw new Exception("Missing input value for $key", 345);
                }
                else {
                    $user_attributes[$key] = $postdata[$key];
                }
            }
        }

        if (isset($uta['auto_form_fields'])) {
            foreach ($uta['auto_form_fields'] as $key => $value) {
                if (empty($postdata[$key])) {
                    switch ($key) {
                        case "userpassword":
                            if (!empty($postdata['userpassword']) && !empty($postdata['userpassword2'])) {
                                if ($postdata['userpassword'] === $postdata['userpassword2']) {
                                    $user_password = $postdata['userpassword'];
                                } else {
                                    throw new Exception("Password mismatch");
                                }
                            } else {
                                $user_attributes[$key] = $_user[$key];
                            }
                            break;
                        default:
                            if (!array_key_exists('optional', $value) || !$value['optional']) {
                                $postdata['attributes'] = array($key);
                                $res                    = $form_service->generate($getdata, $postdata);
                                $postdata[$key]         = $res[$key];
                                $user_attributes[$key]  = $postdata[$key];
                            }
                            break;
                    }
                } else {
                    $user_attributes[$key] = $postdata[$key];
                }
            }
        }

        // The user did not edit these.
        // They're not in $postdata.
        // Only the original user object has the right ones
        if (isset($uta['fields'])) {
            foreach ($uta['fields'] as $key => $value) {
                console("Setting $key from original user's value", $_user[$key]);
                $user_attributes[$key] = $_user[$key];
            }

            $user_attributes[$unique_attr] = $postdata[$unique_attr];
        }     

        // We should start throwing stuff over the fence here.
        $result = $auth->modify_entry($_user_dn, $_user, $user_attributes);

        if ($result) {
            return true;
        }

        return false;

    }
    /**
     * User information.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array|bool User attributes, False on error
     */
    public function user_info($getdata, $postdata)
    {
        if (!isset($getdata['user'])) {
            return false;
        }

        $auth   = Auth::get_instance();
        $conf   = Conf::get_instance();
        $user   = $getdata['user'];
        $result = $auth->user_info($user);

        // normalize result
        $dn                = key($result);
        $result            = $result[$dn];
        $result['entrydn'] = $dn;

        // add user type id to the result
        $result['type_id'] = $this->object_type_id('user', $result['objectclass']);

        // Search for attributes associated with the type_id that are not part
        // of the results returned earlier. Example: nsrole / nsroledn / aci, etc.
        if ($result['type_id']) {
            $uta   = $this->object_type_attributes('user', $result['type_id']);
            $attrs = array();

            foreach ($uta as $field_type => $attributes) {
                foreach ($attributes as $attribute => $data) {
                    if (!array_key_exists($attribute, $result)) {
                        $attrs[] = $attribute;
                    }
                }
            }

            // Insert the persistent, unique attribute
            $unique_attr = $conf->get('unique_attribute');
            if (!$unique_attr) {
                $unique_attr = 'nsuniqueid';
            }

            if (!array_key_exists($unique_attr, $attrs)) {
                $attrs[] = 'nsuniqueid';
            }

            if (!empty($attrs)) {
                $attrs = $auth->get_attributes($result['entrydn'], $attrs);
                if (!empty($attrs)) {
                    $result = array_merge($result, $attrs);
                }
            }
        }

        console($result);

        if ($result) {
            return $result;
        }

        return false;
    }
}

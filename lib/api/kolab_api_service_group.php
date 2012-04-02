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
 *
 */
class kolab_api_service_group extends kolab_api_service
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
            'add'          => 'w',
            'delete'       => 'w',
            'edit'         => 'w',
            'info'         => 'r',
            'members_list' => 'r',
        );
    }

    /**
     * Group create.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array|bool Group attributes or False on failure
     */
    public function group_add($getdata, $postdata)
    {
        $gta = $this->object_type_attributes('group', $postdata['type_id']);
        $group_attributes = array();

        if (isset($gta['form_fields'])) {
            foreach ($gta['form_fields'] as $key => $value) {
                error_log("form field $key");
                if (
                        (!isset($postdata[$key]) || empty($postdata[$key])) &&
                        (!array_key_exists('optional', $value) || !$value['optional'])
                    ) {
                    throw new Exception("Missing input value for $key", 345);
                }
                else {
                    $group_attributes[$key] = $postdata[$key];
                }
            }
        }

        if (isset($gta['auto_form_fields'])) {
            foreach ($gta['auto_form_fields'] as $key => $value) {
                if (empty($postdata[$key])) {
                    if (!array_key_exists('optional', $value) || !$value['optional']) {
                        $postdata['attributes'] = array($key);
                        $res                    = $form_service->generate($getdata, $postdata);
                        $postdata[$key]         = $res[$key];
                        $group_attributes[$key]  = $postdata[$key];
                    }
                } else {
                    $group_attributes[$key] = $postdata[$key];
                }
            }
        }

        if (isset($gta['fields'])) {
            foreach ($gta['fields'] as $key => $value) {
                if (empty($postdata[$key])) {
                    $group_attributes[$key] = $gta['fields'][$key];
                } else {
                    $group_attributes[$key] = $postdata[$key];
                }
            }
        }

        $auth   = Auth::get_instance();
        $result = $auth->group_add($group_attributes, $postdata['type_id']);

        if ($result) {
            return $group_attributes;
        }

        return FALSE;
    }

    /**
     * Group delete.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return bool True on success, False on failure
     */
    public function group_delete($getdata, $postdata)
    {
        if (empty($postdata['group'])) {
            return FALSE;
        }

        // TODO: Input validation
        $auth   = Auth::get_instance();
        $result = $auth->group_delete($postdata['group']);

        if ($result) {
            return $result;
        }

        return FALSE;
    }

    public function group_edit($getdata, $postdata)
    {
        $gta             = $this->object_type_attributes('group', $postdata['type_id']);
        $form_service    = $this->controller->get_service('form_value');
        $group_attributes = array();

        // Get the type "key" string for the next few settings.
        if ($postdata['type_id'] == null) {
            $type_str = 'group';
        }
        else {
            $db   = SQL::get_instance();
            $_key = $db->fetch_assoc($db->query("SELECT `key` FROM group_types WHERE id = ?", $postdata['type_id']));
            $type_str = $_key['key'];
        }

        $conf = Conf::get_instance();

        // Group identifier
        $unique_attr = $conf->get('unique_attribute');
        if (!$unique_attr) {
            $unique_attr = 'nsuniqueid';
        }
        $group_attributes[$unique_attr] = $postdata['id'];
        unset($postdata['id']);

        // TODO: "rdn" is somewhat LDAP specific, but not used as something
        // LDAP specific...?
        $rdn_attr = $conf->get($type_str . '_group_name_attribute');
        if (!$rdn_attr) {
            $rdn_attr = $conf->get('group_name_attribute');
        }
        if (!$rdn_attr) {
            $rdn_attr = 'cn';
        }

        if (isset($gta['form_fields'])) {
            foreach ($gta['form_fields'] as $key => $value) {
                if (
                        (!isset($postdata[$key]) || empty($postdata[$key])) &&
                        (!array_key_exists('optional', $value) || !$value['optional'])
                    ) {
                    throw new Exception("Missing input value for $key", 345);
                }
                else {
                    $group_attributes[$key] = $postdata[$key];
                } 
            }
        }

        if (isset($gta['auto_form_fields'])) {
            foreach ($gta['auto_form_fields'] as $key => $value) {
                if (empty($postdata[$key])) {
                    if (!array_key_exists('optional', $value) || !$value['optional']) {
                        $postdata['attributes'] = array($key);
                        $res                    = $form_service->generate($getdata, $postdata);
                        $postdata[$key]         = $res[$key];
                        $group_attributes[$key]  = $postdata[$key];
                    }
                } else {
                    $group_attributes[$key] = $postdata[$key];
                }
            }
        }

        if (isset($gta['fields'])) {
            foreach ($gta['fields'] as $key => $value) {
                if (empty($postdata[$key])) {
                    $group_attributes[$key] = $gta['fields'][$key];
                } else {
                    $group_attributes[$key] = $postdata[$key];
                }
            }
        }

        $auth = Auth::get_instance();
        $auth->connect();

        // Now that values have been re-generated where necessary, compare
        // the new group attributes to the original group attributes.
        $_group = $auth->group_find_by_attribute(array($unique_attr => $postdata['id']));

        if (!$_group) {
            console("Could not find group");
            return false;
        }

        $_group_dn = key($_group);
        $_group = $this->group_info(Array('group' => $_group_dn), Array());

        // We should start throwing stuff over the fence here.
        $result = $auth->modify_entry($_group_dn, $_group, $group_attributes);

        if ($result) {
            return true;
        }

        return false;
    }

    /**
     * Group information.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array|bool Group attributes or False on failure
     */
    public function group_info($getdata, $postdata)
    {
        if (empty($getdata['group'])) {
            return false;
        }

        $auth   = Auth::get_instance();
        $result = $auth->group_info($getdata['group']);

        // normalize result
        $result = $this->parse_result_attributes('group', $result);

        if ($result) {
            return $result;
        }

        return false;
    }

    /**
     * Group members listing.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array List of group members ('list' and 'count' items)
     */
    public function group_members_list($getdata, $postdata)
    {
        $auth = Auth::get_instance();

        if (empty($getdata['group'])) {
            //error_log("Empty \$getdata['group']");
            return FALSE;
        }

        $result = $auth->group_members_list($getdata['group']);

        return array(
            'list'  => $result,
            'count' => count($result),
        );
    }
}

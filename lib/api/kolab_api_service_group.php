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
                if (!isset($postdata[$key]) || $postdata[$key] === '') {
                    throw new Exception("Missing input value for $key", 345);
                }
                else {
                    $group_attributes[$key] = $postdata[$key];
                }
            }
        }

        if (isset($gta['auto_form_fields'])) {
            foreach ($gta['auto_form_fields'] as $key => $value) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
                else {
                    $group_attributes[$key] = $postdata[$key];
                }
            }
        }

        if (isset($gta['fields'])) {
            foreach ($gta['fields'] as $key => $value) {
                if (!isset($postdata[$key]) || empty($postdata[$key])) {
                    $group_attributes[$key] = $gta['fields'][$key];
                }
                else {
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
            return FALSE;
        }

        $auth   = Auth::get_instance();
        $result = $auth->group_info($getdata['group']);

        // normalize result
        $dn                = key($result);
        $result            = $result[$dn];
        $result['entrydn'] = $dn;

        // add group type id to the result                                                                                                                       
        $result['type_id'] = $this->object_type_id('group', $result['objectclass']);

        //console($result);

        if ($result) {
            return $result;
        }

        return FALSE;
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

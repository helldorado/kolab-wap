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
class kolab_api_service_type extends kolab_api_service
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
        $auth = Auth::get_instance();

        //$effective_rights = $auth->list_rights('user');

        $rights = array();

        // @TODO: set rights according to user group or sth
        if ($_SESSION['user']->get_userid() == 'cn=Directory Manager') {
            $rights['add'] = "w";
            $rights['delete'] = "w";
            $rights['edit'] = "w";
        }

        $rights['effective_rights'] = "r";

        return $rights;
    }

    /**
     * Create user.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array|bool User attributes or False on error.
     */
    public function type_add($getdata, $postdata)
    {
        //console("type_add()", $postdata);

        $type_attributes = $this->parse_input_attributes('type', $postdata);

        //console("type_add()", $type_attributes);

//        $auth   = Auth::get_instance();
//        $result = $auth->type_add($type_attributes, $postdata['type_id']);

        if ($result) {
            return $type_attributes;
        }

        return false;
    }

    /**
     * Detete type.
     *
     * @param array $get  GET parameters
     * @param array $post POST parameters
     *
     * @return bool True on success, False on failure
     */
    public function type_delete($getdata, $postdata)
    {
        if (empty($postdata['type']) || empty($postdata['id'])) {
            return false;
        }

        if (!in_array($postdata['type'], $this->supported_types_db)) {
            return false;
        }

        $object_name = $postdata['type'];
        $object_id   = $postdata['id'];

        $this->db->query("DELETE FROM {$object_name}_types WHERE id = ?", array($object_id));

        return (bool) $this->db->affected_rows();
    }

    /**
     * Update type.
     *
     * @param array $get  GET parameters
     * @param array $post POST parameters
     *
     * @return bool True on success, False on failure
     */
    public function type_edit($getdata, $postdata)
    {
        //console("\$postdata to type_edit()", $postdata);

        $type_attributes = $this->parse_input_attributes('type', $postdata);
        $type            = $postdata['id'];

//        $auth   = Auth::get_instance();
//        $result = $auth->type_edit($type, $type_attributes, $postdata['type_id']);

        // Return the $mod_array
        if ($result) {
            return $result;
        }

        return false;

    }

    public function type_effective_rights($getdata, $postdata)
    {
//        $auth = Auth::get_instance();
//        $effective_rights = $auth->list_rights(empty($getdata['user']) ? 'user' : $getdata['user']);
//        return $effective_rights;
        return array();
    }

    /**
     * User information.
     *
     * @param array $get  GET parameters
     * @param array $post POST parameters
     *
     * @return array|bool User attributes, False on error
     */
    public function type_info($getdata, $postdata)
    {
        if (!isset($getdata['type'])) {
            return false;
        }

//        $auth   = Auth::get_instance();
//        $result = $auth->type_info($getdata['type']);

//        Log::trace("type.info on " . $getdata['type'] . " result: " . var_export($result, TRUE));
        // normalize result
//        $result = $this->parse_result_attributes('type', $result);

//        Log::trace("type.info on " . $getdata['type'] . " parsed result: " . var_export($result, TRUE));

        if ($result) {
            return $result;
        }

        return false;
    }
}

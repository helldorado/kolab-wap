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
        //console("kolab_api_service_group::capabilities");

        $auth = Auth::get_instance();

        $effective_rights = $auth->list_rights('user');

        //console("effective_rights", $effective_rights);

        $rights = array();

        if (in_array('add', $effective_rights['entryLevelRights'])) {
            $rights['add'] = "w";
        }

        if (in_array('delete', $effective_rights['entryLevelRights'])) {
            $rights['delete'] = "w";
        }

        if (in_array('modrdn', $effective_rights['entryLevelRights'])) {
            $rights['edit'] = "w";
        }

        if (in_array('read', $effective_rights['entryLevelRights'])) {
            $rights['info'] = "r";
            $rights['find'] = "r";
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
    public function user_add($getdata, $postdata)
    {
        //console("user_add()", $postdata);

        $user_attributes = $this->parse_input_attributes('user', $postdata);

        //console("user_add()", $user_attributes);

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
        //console("user_delete()", $getdata, $postdata);
        if (!isset($postdata['id'])) {
            return false;
        }

        // TODO: Input validation
        $auth   = Auth::get_instance();
        $result = $auth->user_delete($postdata['id']);

        if ($result) {
            return $result;
        }

        return false;
    }

    public function user_edit($getdata, $postdata)
    {
        //console("\$postdata to user_edit()", $postdata);

        if ($postdata['mailquota-unit'] == 'gb') {
            $postdata['mailquota'] *= 1024*1024;
        }
        if ($postdata['mailquota-unit'] == 'mb') {
            $postdata['mailquota'] *= 1024;
        }

        $user_attributes = $this->parse_input_attributes('user', $postdata);
        $user            = $postdata['id'];

        //console("\$user_attributes as result from parse_input_attributes", $user_attributes);

        $auth   = Auth::get_instance();
        $result = $auth->user_edit($user, $user_attributes, $postdata['type_id']);

        // Return the $mod_array
        if ($result) {
            return $result;
        }

        return false;

    }

    public function user_effective_rights($getdata, $postdata)
    {
        $auth = Auth::get_instance();
        $effective_rights = $auth->list_rights(empty($getdata['id']) ? 'user' : $getdata['id']);
        return $effective_rights;
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
        if (!isset($getdata['id'])) {
            return false;
        }

        $auth   = Auth::get_instance();
        $result = $auth->user_info($getdata['id']);

        Log::trace("user.info on " . $getdata['id'] . " result: " . var_export($result, TRUE));
        // normalize result
        $result = $this->parse_result_attributes('user', $result);

        Log::trace("user.info on " . $getdata['id'] . " parsed result: " . var_export($result, TRUE));

        if ($result) {
            return $result;
        }

        return false;
    }

    /**
     * Find user and return his data.
     * It is a combination of user.info and users.list with search capabilities
     * If the search returns only one record we'll return user data.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array|bool User attributes, False on error
     */
    public function user_find($get, $post)
    {
        $auth       = Auth::get_instance();
        $attributes = array('');
        $params     = array('page_size' => 2);
        $search     = $this->parse_list_search($post);

        // find user(s)
        $users = $auth->list_users(null, $attributes, $search, $params);

        if (empty($users) || empty($users['list']) || $users['count'] > 1) {
            return false;
        }

        // get user data
        $result = $auth->user_info(key($users['list']));

        // normalize result
        $result = $this->parse_result_attributes('user', $result);

        if ($result) {
            return $result;
        }

        return false;
    }

}

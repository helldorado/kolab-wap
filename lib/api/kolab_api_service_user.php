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
        $user_attributes = $this->parse_input_attributes('user', $postdata); 
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
        console("user_delete()", $getdata, $postdata);
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

        $user_attributes = $this->parse_input_attributes('user', $postdata);
        $user            = $postdata['id'];

        $auth   = Auth::get_instance();
        $result = $auth->user_edit($user, $user_attributes, $postdata['type_id']);

        // @TODO: return unique attribute (?), it can change on edit
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
        $result = $auth->user_info($getdata['user']);

        // normalize result
        $result = $this->parse_result_attributes('user', $result); 

        if ($result) {
            return $result;
        }

        return false;
    }
}

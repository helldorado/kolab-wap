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
        //console("kolab_api_service_group::capabilities");

        $auth             = Auth::get_instance();
        $effective_rights = $auth->list_rights('group');
        $rights           = array();

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
            $rights['members_list'] = "r";
        }

        $rights['effective_rights'] = "r";

        return $rights;
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
        $group_attributes = $this->parse_input_attributes('group', $postdata);

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
        if (empty($postdata['id'])) {
            return FALSE;
        }

        // TODO: Input validation
        $auth   = Auth::get_instance();
        $result = $auth->group_delete($postdata['id']);

        if ($result) {
            return $result;
        }

        return FALSE;
    }

    public function group_edit($getdata, $postdata)
    {
        //console("group_edit \$postdata", $postdata);

        $group_attributes = $this->parse_input_attributes('group', $postdata);
        $group            = $postdata['id'];

        $auth   = Auth::get_instance();
        $result = $auth->group_edit($postdata['id'], $group_attributes, $postdata['type_id']);

        // @TODO: return unique attribute or all attributes as group_add()
        if ($result) {
            return true;
        }

        return false;
    }

    public function group_effective_rights($getdata, $postdata)
    {
        $auth = Auth::get_instance();
        $effective_rights = $auth->list_rights(empty($getdata['id']) ? 'group' : $getdata['id']);
        return $effective_rights;
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
        if (empty($getdata['id'])) {
            return false;
        }

        $auth   = Auth::get_instance();
        $result = $auth->group_info($getdata['id']);

        // normalize result
        $result = $this->parse_result_attributes('group', $result);

        Log::trace("group_info() result: " . var_export($result, TRUE));

        if ($result) {
            return $result;
        }

        return false;
    }

    /**
     * Find group and return its data.
     * It is a combination of group.info and groups.list with search capabilities
     * If the search returns only one record we'll return group data.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array|bool Group attributes, False on error
     */
    public function group_find($get, $post)
    {
        $auth       = Auth::get_instance();
        $attributes = array('');
        $params     = array('page_size' => 2);
        $search     = $this->parse_list_search($post);

        // find group(s)
        $groups = $auth->list_groups(null, $attributes, $search, $params);

        if (empty($groups) || empty($groups['list']) || $groups['count'] > 1) {
            return false;
        }

        // get group data
        $result = $auth->group_info(key($groups['list']));

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
        Log::trace("group_members_list() for group " . $getdata['id']);
        $auth = Auth::get_instance();

        if (empty($getdata['id'])) {
            //console("Empty \$getdata['id']");
            return FALSE;
        }

        $result = $auth->group_members_list($getdata['id'], false);

        Log::trace("group_members_list() result: " . var_export($result, TRUE));

        return array(
            'list'  => $result,
            'count' => count($result),
        );
    }
}

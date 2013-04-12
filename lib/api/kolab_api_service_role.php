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
class kolab_api_service_role extends kolab_api_service
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
        //console("kolab_api_service_role::capabilities");

        $auth             = Auth::get_instance();
        $effective_rights = $auth->list_rights('role');
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
    public function role_add($getdata, $postdata)
    {
        $role_attributes = $this->parse_input_attributes('role', $postdata);

        $auth   = Auth::get_instance();
        $result = $auth->role_add($role_attributes, $postdata['type_id']);

        if ($result) {
            return $role_attributes;
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
    public function role_delete($getdata, $postdata)
    {
        if (empty($postdata['role'])) {
            return FALSE;
        }

        // TODO: Input validation
        $auth   = Auth::get_instance();
        $result = $auth->role_delete($postdata['role']);

        if ($result) {
            return $result;
        }

        return FALSE;
    }

    public function role_edit($getdata, $postdata)
    {
        //console("role_edit \$postdata", $postdata);

        $role_attributes = $this->parse_input_attributes('role', $postdata);
        $role            = $postdata['id'];

        $auth   = Auth::get_instance();
        $result = $auth->role_edit($postdata['id'], $role_attributes, $postdata['type_id']);

        // @TODO: return unique attribute or all attributes as role_add()
        if ($result) {
            return true;
        }

        return false;
    }

    public function role_effective_rights($getdata, $postdata)
    {
        $auth = Auth::get_instance();

        // Roles are special in that they are ldapsubentries.
        if (!empty($getdata['role'])) {
            $unique_attr = $this->unique_attribute();
            $role        = $auth->role_find_by_attribute(Array($unique_attr => $getdata['role']));

            if (is_array($role) && count($role) == 1) {
                $role_dn = key($role);
            }
        }

        $effective_rights = $auth->list_rights(empty($role_dn) ? 'role' : $role_dn);

        return $effective_rights;
    }

    /**
     * Role information.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array|bool Role attributes or False on failure
     */
    public function role_info($getdata, $postdata)
    {
        //console("api::role.info \$getdata, \$postdata", $getdata, $postdata);

        if (empty($getdata['role'])) {
            return false;
        }

        $auth   = Auth::get_instance();
        $result = $auth->role_info($getdata['role']);

        // normalize result
        $result = $this->parse_result_attributes('role', $result);

        if ($result) {
            return $result;
        }

        return false;
    }

    /**
     * Find role and return its data.
     * It is a combination of role.info and roles.list with search capabilities
     * If the search returns only one record we'll return role data.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array|bool Role attributes, False on error
     */
    public function role_find($get, $post)
    {
        $auth       = Auth::get_instance();
        $attributes = array('');
        $params     = array('page_size' => 2);
        $search     = $this->parse_list_search($post);

        // find role(s)
        $roles = $auth->list_roles(null, $attributes, $search, $params);

        if (empty($roles) || empty($roles['list']) || $roles['count'] > 1) {
            return false;
        }

        // get role data
        $result = $auth->role_info(key($roles['list']));

        // normalize result
        $result = $this->parse_result_attributes('role', $result);

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
     * @return array List of role members ('list' and 'count' items)
     */
    public function role_members_list($getdata, $postdata)
    {
        $auth = Auth::get_instance();

        if (empty($getdata['role'])) {
            //console("Empty \$getdata['role']");
            return FALSE;
        }

        $result = $auth->role_members_list($getdata['role'], false);

        return array(
            'list'  => $result,
            'count' => count($result),
        );
    }
}

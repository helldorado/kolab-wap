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
 * Service providing resource data management
 */
class kolab_api_service_resource extends kolab_api_service
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
        $effective_rights = $auth->list_rights('resource');
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
        }

        $rights['effective_rights'] = "r";

        return $rights;
    }

    /**
     * Create resource.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array|bool User attributes or False on error.
     */
    public function resource_add($getdata, $postdata)
    {
        //console("resource_add()", $postdata);

        $resource_attributes = $this->parse_input_attributes('resource', $postdata);

        //console("resource_add()", $resource_attributes);

        // TODO: The cn needs to be unique
        $auth = Auth::get_instance();
        $result = $auth->resource_add($resource_attributes, $postdata['type_id']);

        if ($result) {
            return $resource_attributes;
        }

        return false;
    }

    /**
     * Detete resource.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return bool True on success, False on failure
     */
    public function resource_delete($getdata, $postdata)
    {
        //console("resource_delete()", $getdata, $postdata);
        if (!isset($postdata['resource'])) {
            return false;
        }

        // TODO: Input validation
        $auth   = Auth::get_instance();
        $result = $auth->resource_delete($postdata['resource']);

        if ($result) {
            return $result;
        }

        return false;
    }

    public function resource_edit($getdata, $postdata)
    {
        //console("\$postdata to resource_edit()", $postdata);

        $resource_attributes = $this->parse_input_attributes('resource', $postdata);

        //console("\$resource_attributes as result from parse_input_attributes", $resource_attributes);

        $resource            = $postdata['id'];

        $auth   = Auth::get_instance();
        $result = $auth->resource_edit($resource, $resource_attributes, $postdata['type_id']);

        // Return the $mod_array
        if ($result) {
            return $result;
        }

        return false;

    }

    public function resource_effective_rights($getdata, $postdata)
    {
        $auth = Auth::get_instance();
        $effective_rights = $auth->list_rights(empty($getdata['resource']) ? 'resource' : $getdata['resource']);
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
    public function resource_info($getdata, $postdata)
    {
        if (!isset($getdata['resource'])) {
            return false;
        }

        $auth   = Auth::get_instance();
        $result = $auth->resource_info($getdata['resource']);

        // normalize result
        $result = $this->parse_result_attributes('resource', $result);

        //console($result);

        if ($result) {
            return $result;
        }

        return false;
    }

    /**
     * Find resource and return its data.
     * It is a combination of resource.info and resources.list with search capabilities
     * If the search returns only one record we'll return resource data.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array|bool Resource attributes, False on error
     */
    public function resource_find($get, $post)
    {
        $auth       = Auth::get_instance();
        $attributes = array('');
        $params     = array('page_size' => 2);
        $search     = $this->parse_list_search($post);

        // find resource(s)
        $resources = $auth->list_resources(null, $attributes, $search, $params);

        if (empty($resources) || empty($resources['list']) || $resources['count'] > 1) {
            return false;
        }

        // get resource data
        $result = $auth->resource_info(key($resources['list']));

        // normalize result
        $result = $this->parse_result_attributes('resource', $result);

        if ($result) {
            return $result;
        }

        return false;
    }

}

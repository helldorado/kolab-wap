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
 * Service providing shared folder data management
 */
class kolab_api_service_sharedfolder extends kolab_api_service
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
        $effective_rights = $auth->list_rights('sharedfolder');
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
     * Create a shared folder.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array|bool User attributes or False on error.
     */
    public function sharedfolder_add($getdata, $postdata)
    {
        //console("sharedfolder_add()", $postdata);

        $sharedfolder_attributes = $this->parse_input_attributes('sharedfolder', $postdata);

        //console("sharedfolder_add()", $sharedfolder_attributes);

        // TODO: The cn needs to be unique
        $auth = Auth::get_instance();
        $result = $auth->sharedfolder_add($sharedfolder_attributes, $postdata['type_id']);

        if ($result) {
            return $sharedfolder_attributes;
        }

        return false;
    }

    /**
     * Detete a shared folder.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return bool True on success, False on failure
     */
    public function sharedfolder_delete($getdata, $postdata)
    {
        //console("sharedfolder_delete()", $getdata, $postdata);
        if (!isset($postdata['sharedfolder'])) {
            return false;
        }

        // TODO: Input validation
        $auth   = Auth::get_instance();
        $result = $auth->sharedfolder_delete($postdata['sharedfolder']);

        if ($result) {
            return $result;
        }

        return false;
    }

    public function sharedfolder_edit($getdata, $postdata)
    {
        //console("\$postdata to sharedfolder_edit()", $postdata);

        $sharedfolder_attributes = $this->parse_input_attributes('sharedfolder', $postdata);

        //console("\$sharedfolder_attributes as result from parse_input_attributes", $sharedfolder_attributes);

        $sharedfolder   = $postdata['id'];

        $auth   = Auth::get_instance();
        $result = $auth->sharedfolder_edit($sharedfolder, $sharedfolder_attributes, $postdata['type_id']);

        // Return the $mod_array
        if ($result) {
            return $result;
        }

        return false;

    }

    public function sharedfolder_effective_rights($getdata, $postdata)
    {
        $auth = Auth::get_instance();
        $effective_rights = $auth->list_rights(empty($getdata['sharedfolder']) ? 'sharedfolder' : $getdata['sharedfolder']);
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
    public function sharedfolder_info($getdata, $postdata)
    {
        if (!isset($getdata['sharedfolder'])) {
            return false;
        }

        $auth   = Auth::get_instance();
        $result = $auth->sharedfolder_info($getdata['sharedfolder']);

        // normalize result
        $result = $this->parse_result_attributes('sharedfolder', $result);

        //console($result);

        if ($result) {
            return $result;
        }

        return false;
    }

    /**
     * Find a shared folder and return its data.
     * It is a combination of sharedfolder.info and sharedfolders.list with search capabilities
     * If the search returns only one record we'll return sharedfolder data.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array|bool Resource attributes, False on error
     */
    public function sharedfolder_find($get, $post)
    {
        $auth       = Auth::get_instance();
        $attributes = array('');
        $params     = array('page_size' => 2);
        $search     = $this->parse_list_search($post);

        // find shared folder(s)
        $sharedfolders = $auth->list_sharedfolders(null, $attributes, $search, $params);

        if (empty($sharedfolders) || empty($sharedfolders['list']) || $sharedfolders['count'] > 1) {
            return false;
        }

        // get shared folder data
        $result = $auth->sharedfolder_info(key($sharedfolders['list']));

        // normalize result
        $result = $this->parse_result_attributes('sharedfolder', $result);

        if ($result) {
            return $result;
        }

        return false;
    }

}

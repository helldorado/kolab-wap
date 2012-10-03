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
 * Service providing object types management
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

        $rights['info'] = "r";
        $rights['effective_rights'] = "r";

        return $rights;
    }

    /**
     * Create type.
     *
     * @param array $get  GET parameters
     * @param array $post POST parameters
     *
     * @return array|bool Type attributes or False on error.
     */
    public function type_add($getdata, $postdata)
    {
        if (!in_array($postdata['type'], $this->supported_types_db)) {
            return false;
        }

        if (empty($postdata['name']) || empty($postdata['key'])) {
            return false;
        }

        if (empty($postdata['attributes']) || !is_array($postdata['attributes'])) {
            return false;
        }

        // @TODO: check privileges

        $type  = $postdata['type'];
        $query = array(
            'key'         => $postdata['key'],
            'name'        => $postdata['name'],
            'description' => $postdata['description'] ? $postdata['description'] : '',
            'attributes'  => json_encode($postdata['attributes']),
        );

        if ($postdata['type'] == 'user') {
            $query['used_for'] = $postdata['used_for'] == 'hosted' ? 'hosted' : null;
        }

        $query   = array_map(array($this->db, 'escape'), $query);
        $columns = array_map(array($this->db, 'escape_identifier'), array_keys($query));

        $this->db->query("INSERT INTO {$type}_types"
            . " (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $query) . ")");

        if (!($id = $this->db->last_insert_id())) {
            return false;
        }

        // update cache
        $this->cache['object_types'][$type][$id] = $postdata;

        $postdata['id'] = $id;

        return $postdata;
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

        // @TODO: check privileges

        $this->db->query("DELETE FROM {$object_name}_types WHERE id = " . intval($object_id));

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
        if (empty($postdata['type']) || empty($postdata['id'])) {
            return false;
        }

        if (empty($postdata['name']) || empty($postdata['key'])) {
            return false;
        }

        if (empty($postdata['attributes']) || !is_array($postdata['attributes'])) {
            return false;
        }

        // @TODO: check privileges
        $type  = $postdata['type'];
        $query = array(
            'key'         => $postdata['key'],
            'name'        => $postdata['name'],
            'description' => $postdata['description'] ? $postdata['description'] : '',
            'attributes'  => json_encode($postdata['attributes']),
        );

        if ($postdata['type'] == 'user') {
            $query['used_for'] = $postdata['used_for'] == 'hosted' ? 'hosted' : null;
        }

        foreach ($query as $idx => $value) {
            $query[$idx] = $this->db->escape_identifier($idx) . " = " . $this->db->escape($value);
        }

        $result = $this->db->query("UPDATE {$type}_types SET "
            . implode(', ', $query) . " WHERE id = " . intval($postdata['id']));

        if (!$result) {
            return false;
        }

        // update cache
        $this->cache['object_types'][$type][$id] = $postdata;

        return $postdata;
    }

    public function type_effective_rights($getdata, $postdata)
    {
        $effective_rights = array();
        // @TODO: set rights according to user group or sth
        if ($_SESSION['user']->get_userid() == 'cn=Directory Manager') {
            $attr_acl = array('read', 'write', 'delete');
            $effective_rights = array(
                'entryLevelRights' => array(
                    'read', 'add', 'delete', 'write',
                ),
                'attributeLevelRights' => array(
                    'key'         => $attr_acl,
                    'name'        => $attr_acl,
                    'description' => $attr_acl,
                    'used_for'    => $attr_acl,
                    'attributes'  => $attr_acl,
                ),
            );
        }

        return $effective_rights;
    }

    /**
     * Type information.
     *
     * @param array $get  GET parameters
     * @param array $post POST parameters
     *
     * @return array|bool Type data, False on error
     */
    public function type_info($getdata, $postdata)
    {
        if (empty($getdata['type']) || empty($getdata['id'])) {
            return false;
        }

        if (!in_array($getdata['type'], $this->supported_types_db)) {
            return false;
        }

        $object_name = $getdata['type'];
        $object_id   = $getdata['id'];
        $types       = $this->object_types($object_name);

        return !empty($types[$object_id]) ? $types[$object_id] : false;
    }
}

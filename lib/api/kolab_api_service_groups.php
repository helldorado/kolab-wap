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
 * Service providing groups listing
 */
class kolab_api_service_groups extends kolab_api_service
{
    public $list_attribs = array(
        'cn',
        'gidnumber',
        'objectclass',
        'mail',
    );

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
            'list' => 'r',
        );
    }

    /**
     * Groups listing (with searching).
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array List result with 'list' and 'count' items
     */
    public function groups_list($get, $post)
    {
        $auth = Auth::get_instance();

        // returned attributes
        if (!empty($post['attributes']) && is_array($post['attributes'])) {
            // get only supported attributes
            $attributes = array_intersect($this->list_attribs, $post['attributes']);
            // need to fix array keys
            $attributes = array_values($attributes);
        }
        if (empty($attributes)) {
            $attributes = (array)$this->list_attribs[0];
        }

        $search = array();
        $params = array();

        // searching
        if (!empty($post['search']) && is_array($post['search'])) {
            $params = $post['search'];
            foreach ($params as $idx => $param) {
                // get only supported attributes
                if (!in_array($idx, $this->list_attribs)) {
                    unset($params[$idx]);
                    continue;
                }

                // search string
                if (empty($param['value'])) {
                    unset($params[$idx]);
                    continue;
                }
            }

            $search['params'] = $params;
            if (!empty($post['search_operator'])) {
                $search['operator'] = $post['search_operator'];
            }
        }

        if (!empty($post['sort_by'])) {
            // check if sort attribute is supported
            if (in_array($post['sort_by'], $this->list_attribs)) {
                $params['sort_by'] = $post['sort_by'];
            }
        }

        if (!empty($post['sort_order'])) {
            $params['sort_order'] = $post['sort_order'] == 'DESC' ? 'DESC' : 'ASC';
        }

        $groups = $auth->list_groups(null, $attributes, $search, $params);
        $count  = count($groups);

        // pagination
        if (!empty($post['page_size']) && $count) {
            $size   = (int) $post['page_size'];
            $page   = !empty($post['page']) ? $post['page'] : 1;
            $page   = max(1, (int) $page);
            $offset = ($page - 1) * $size;

            $groups = array_slice($groups, $offset, $size, true);
        }

        return array(
            'list'  => $groups,
            'count' => $count,
        );
    }
}

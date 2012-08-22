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
 * Service providing users listing
 */
class kolab_api_service_users extends kolab_api_service
{
    public $list_attribs = array(
            'uid',
            'cn',
            'displayname',
            'sn',
            'givenname',
            'mail',
            'objectclass',
            'uidnumber',
            'gidnumber',
            'mailhost',
            'entrydn',
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
     * Users listing (with searching).
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array List result with 'list' and 'count' items
     */
    public function users_list($get, $post)
    {
        $auth = Auth::get_instance();

        $attributes = $this->parse_list_attributes($post);
        $params = $this->parse_list_params($post);
        $search = $this->parse_list_search($post);

        $users = $auth->list_users(null, $attributes, $search, $params);

        Log::trace("users.list result: " . var_export($users, TRUE));

        return $users;
    }

}

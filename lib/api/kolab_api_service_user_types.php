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
class kolab_api_service_user_types extends kolab_api_service
{
    public function capabilities($domain)
    {
        return array(
            'list' => 'r',
        );
    }

    public function user_types_list($get, $post)
    {
        $sql_result = $this->db->query("SELECT * FROM user_types");
        $user_types = array();

        while ($row = $this->db->fetch_assoc($sql_result)) {
            $user_types[$row['id']] = array();

            foreach ($row as $key => $value) {
                if ($key != "id") {
                    if ($key == "attributes") {
                        $user_types[$row['id']][$key] = json_decode(unserialize($value), true);
                    }
                    else {
                        $user_types[$row['id']][$key] = $value;
                    }
                }
            }
        }

        return array(
            'list'  => $user_types,
            'count' => count($user_types),
        );
    }
}

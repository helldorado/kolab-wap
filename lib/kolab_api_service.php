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
 * Interface class for Kolab Admin Services
 */
abstract class kolab_api_service
{
    protected $controller;
    protected $db;

    /**
     * Class constructor.
     *
     * @param kolab_api_controller Controller
     */
    public function __construct($ctrl)
    {
        $this->db         = SQL::get_instance();
        $this->controller = $ctrl;
    }

    /**
     * Advertise this service's capabilities
     */
    abstract public function capabilities($domain);

    /**
     * Returns attributes of specified user type.
     *
     * @param int  $type_id  User type identifier
     * @param bool $required Throws exception on empty ID
     *
     * @return array User type attributes
     */
    protected function user_type_attributes($type_id, $required = true)
    {
        if (empty($type_id)) {
            if ($required) {
                throw new Exception($this->controller->translate('user.notypeid'), 34);
            }

            return array();
        }

        $sql_result = $this->db->query("SELECT attributes FROM user_types WHERE id = ?", $type_id);
        $user_type  = $this->db->fetch_assoc($sql_result);

        if (empty($user_type)) {
            throw new Exception($this->controller->translate('user.invalidtypeid'), 35);
        }

        $uta = json_decode(unserialize($user_type['attributes']), true);

        return $uta;
    }

}

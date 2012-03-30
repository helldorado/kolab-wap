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
    protected $cache = array();

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
     * @param string $object_name  Name of the object (user, group, etc.)
     * @param int    $type_id      User type identifier
     * @param bool   $required     Throws exception on empty ID
     *
     * @return array User type attributes
     */
    protected function object_type_attributes($object_name, $type_id, $required = true)
    {
        $supported = array('group', 'user');
        if (!in_array($object_name, $supported)) {
        
        }
    
        if (empty($type_id)) {
            if ($required) {
                throw new Exception($this->controller->translate($object_name . '.notypeid'), 34);
            }

            return array();
        }

        $object_types = $this->object_types($object_name);

        if (empty($object_types[$type_id])) {
            throw new Exception($this->controller->translate($object_name . '.invalidtypeid'), 35);
        }

        return $object_types[$type_id]['attributes'];
    }

    /**
     * Detects object type ID for specified objectClass attribute value
     *
     * @param string $object_name   Name of the object (user, group, etc.)
     * @param array  $object_class  Value of objectClass attribute
     *
     * @return int Object type identifier
     */
    protected function object_type_id($object_name, $object_class)
    {
        if (empty($object_class)) {
            return null;
        }

        $object_class = array_map('strtolower', $object_class);
        $object_types = $this->object_types($object_name);
        $type_score   = -1;
        $type_id      = null;

        console("Data objectClasses: " . implode(", ", $object_class));

        foreach ($object_types as $idx => $elem) {
            $ref_class = $elem['attributes']['fields']['objectclass'];

            if (empty($ref_class)) {
                continue;
            }

            console("Reference objectclasses for " . $elem['key'] . ": " . implode(", ", $ref_class));

            // Eliminate the duplicates between the $data_ocs and $ref_ocs
            $_object_class = array_diff($object_class, $ref_class);
            $_ref_class    = array_diff($ref_class, $object_class);

            $differences   = count($_object_class) + count($_ref_class);
            $commonalities = count($object_class) - $differences;
            $elem_score    = $differences > 0 ? ($commonalities / $differences) : $commonalities;

//            console("\$object_class not in \$ref_class (" . $elem['key'] . "): " . implode(", ", $_object_class));
//            console("\$ref_class not in \$object_class (" . $elem['key'] . "): " . implode(", ", $_ref_class));
            console("Score for $object_name type " . $elem['name'] . ": " . $elem_score . "(" . $commonalities . "/" . $differences . ")");

            if ($elem_score > $type_score) {
                $type_id    = $idx;
                $type_score = $elem_score;
            }
        }

        return $type_id;
    }

    /**
     * Returns object types definitions.
     *
     * @param string $object_name  Name of the object (user, group, etc.)
     *
     * @return array Object types.
     */
    protected function object_types($object_name)
    {
        if (!empty($this->cache['object_types']) && !empty($this->cache['object_types'][$object_name])) {
            return $this->cache['object_types'][$object_name];
        }

        $supported = array('group', 'user');
        if (!in_array($object_name, $supported)) {
            return array();
        }

        $sql_result   = $this->db->query("SELECT * FROM {$object_name}_types");
        $object_types = array();

        while ($row = $this->db->fetch_assoc($sql_result)) {
            $object_types[$row['id']] = array();

            foreach ($row as $key => $value) {
                if ($key != "id") {
                    if ($key == "attributes") {
                        $object_types[$row['id']][$key] = json_decode($value, true);
                    }
                    else {
                        $object_types[$row['id']][$key] = $value;
                    }
                }
            }
        }

        return $this->cache['object_types'][$object_name] = $object_types;
    }

}

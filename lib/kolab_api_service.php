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
        $supported = array('domain', 'group', 'resource', 'role', 'user');
        if (!$object_name || !in_array($object_name, $supported)) {
            return array();
        }

        if (empty($type_id)) {
            if ($required) {
                throw new Exception($this->controller->translate($object_name . '.notypeid'), 34);
            }

            return array();
        }

        $object_types = $this->object_types($object_name);

        if (empty($object_types[$type_id])) {
            if ($object_name == 'domain') {
                $result = array(
                        'auto_form_fields' => array(),
                        'form_fields' => array(
                                'associateddomain' => array(
                                        'type' => 'list'
                                    ),
                            ),
                        'fields' => array(
                                'objectclass' => array(
                                        'top',
                                        'domainrelatedobject',
                                    ),
                            ),
                    );

                //console("object_type_attributes('domain', $type_id);", $result);

                return $result;

            } else {
                throw new Exception($this->controller->translate($object_name . '.invalidtypeid'), 35);
            }
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
        if ($object_name == 'domain') return 1;

        if (empty($object_class)) {
            return null;
        }

        $object_class = array_map('strtolower', $object_class);
        $object_types = $this->object_types($object_name);
        $type_score   = -1;
        $type_id      = null;

        //console("Data objectClasses: " . implode(", ", $object_class));

        foreach ($object_types as $idx => $elem) {
            $ref_class = $elem['attributes']['fields']['objectclass'];

            if (empty($ref_class)) {
                continue;
            }

            //console("Reference objectclasses for " . $elem['key'] . ": " . implode(", ", $ref_class));

            // Eliminate the duplicates between the $data_ocs and $ref_ocs
            $_object_class = array_diff($object_class, $ref_class);
            $_ref_class    = array_diff($ref_class, $object_class);

            $differences   = count($_object_class) + count($_ref_class);
            $commonalities = count($object_class) - $differences;
            $elem_score    = $differences > 0 ? ($commonalities / $differences) : $commonalities;

            //console("\$object_class not in \$ref_class (" . $elem['key'] . "): " . implode(", ", $_object_class));
            //console("\$ref_class not in \$object_class (" . $elem['key'] . "): " . implode(", ", $_ref_class));
            //console("Score for $object_name type " . $elem['name'] . ": " . $elem_score . "(" . $commonalities . "/" . $differences . ")");

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
        $supported = array('group', 'resource', 'user');
        if (!$object_name || !in_array($object_name, $supported)) {
            return array();
        }


        if (!empty($this->cache['object_types']) && !empty($this->cache['object_types'][$object_name])) {
            return $this->cache['object_types'][$object_name];
        }

        $conf = Conf::get_instance();
        $unique_attr = $conf->get('unique_attribute');
        if (!$unique_attr) {
            $unique_attr = 'nsuniqueid';
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

        //console("Object types for " . $object_name, $object_types);

//         return $object_types;

        return $this->cache['object_types'][$object_name] = $object_types;

    }

    /**
     * Parses input (for add/edit) attributes
     *
     * @param string $object_name  Name of the object (user, group, etc.)
     * @param array  $attrs        Entry attributes
     *
     * @return array Entry attributes
     */
    protected function parse_input_attributes($object_name, $attribs)
    {
        $type_attrs   = $this->object_type_attributes($object_name, $attribs['type_id']);

        //console("parse_input_attributes", $type_attrs);
        //console("called with \$attribs", $attribs);

        $form_service = $this->controller->get_service('form_value');

        // With the result, start validating the input
        $form_service->validate(null, $attribs);

        $result       = array();

        if (isset($type_attrs['form_fields'])) {
            foreach ($type_attrs['form_fields'] as $key => $value) {
                //console("Running parse input attributes for key $key");

                if (empty($attribs[$key]) && empty($value['optional'])) {
                    //console("\$attribs['" . $key . "'] is empty, and the field is not optional");
                    throw new Exception("Missing input value for $key", 345);
                }
                else {
                    //console("Either \$attribs['" . $key . "'] is empty or the field is optional");
                    $result[$key] = $attribs[$key];
                }
            }
        }

        if (isset($type_attrs['auto_form_fields'])) {
            foreach ($type_attrs['auto_form_fields'] as $key => $value) {
                if (empty($attribs[$key])) {
                    if (empty($value['optional'])) {
                        $attribs['attributes'] = array($key);
                        $res                   = $form_service->generate(null, $attribs);
                        $attribs[$key]         = $res[$key];
                        $result[$key]          = $attribs[$key];
                    }
                } else {
                    $result[$key] = $attribs[$key];
                }
            }
        }

        if (isset($type_attrs['fields'])) {
            foreach ($type_attrs['fields'] as $key => $value) {
                if (empty($attribs[$key])) {
                    $result[$key] = $type_attrs['fields'][$key];
                } else {
                    $result[$key] = $attribs[$key];
                }
            }
        }

        //console("parse_input_attributes result", $result);

        return $result;
    }

    /**
     * Parses result attributes
     *
     * @param string $object_name  Name of the object (user, group, etc.)
     * @param array  $attrs        Entry attributes
     *
     * @return array Entry attributes
     */
    protected function parse_result_attributes($object_name, $attrs = array())
    {
        //console("parse_result_attributes($object_name, \$attrs = ", $attrs);

        if (empty($attrs) || !is_array($attrs)) {
            return $attrs;
        }

        $conf        = Conf::get_instance();
        $auth        = Auth::get_instance();
        $dn          = key($attrs);
        $attrs       = $attrs[$dn];
        $extra_attrs = array();

        // add group type id to the result
        $attrs['type_id'] = $this->object_type_id($object_name, $attrs['objectclass']);

        if (empty($attrs['type_id'])) {
            if ($object_name == 'domain') {
                $attrs['type_id'] = 1;
            }
        }

        // Search for attributes associated with the type_id that are not part
        // of the results returned earlier. Example: nsrole / nsroledn / aci, etc.
        // @TODO: this should go to LDAP class
        if ($attrs['type_id']) {
            $uta = $this->object_type_attributes($object_name, $attrs['type_id']);

            foreach ((array)$uta as $field_type => $attributes) {
                foreach ($attributes as $attribute => $data) {
                    if (!array_key_exists($attribute, $attrs)) {
                        $extra_attrs[] = $attribute;
                    }
                }
            }
        }

        // Insert the persistent, unique attribute
        $unique_attr = $conf->get('unique_attribute');
        if (!$unique_attr) {
            $unique_attr = 'nsuniqueid';
        }

        if (!array_key_exists($unique_attr, $attrs)) {
            $extra_attrs[] = $unique_attr;
        }

        // Get extra attributes
        if (!empty($extra_attrs)) {
            $extra_attrs = $auth->get_attributes($dn, $extra_attrs);
            if (!empty($extra_attrs)) {
                $attrs = array_merge($attrs, $extra_attrs);
            }
        }

        // Replace unique attribute with 'id' key
        $attrs['id'] = $attrs[$unique_attr];
        unset($attrs[$unique_attr]);

        return $attrs;
    }

}

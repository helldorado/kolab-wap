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
    protected $cache = array();
    protected $conf;
    protected $controller;
    protected $db;
    protected $supported_types_db = array('group', 'resource', 'role', 'sharedfolder', 'user');
    protected $supported_types    = array('domain', 'group', 'resource', 'role', 'sharedfolder', 'user');

    /**
     * Class constructor.
     *
     * @param kolab_api_controller Controller
     */
    public function __construct($ctrl)
    {
        $this->controller = $ctrl;
        $this->conf       = Conf::get_instance();
        $this->db         = SQL::get_instance();
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
     * @param string $key_name     Reference to a variable which will be set to type key
     *
     * @return array User type attributes
     */
    protected function object_type_attributes($object_name, $type_id, $required = true, &$key_name = null)
    {
        if (!$object_name || !in_array($object_name, $this->supported_types)) {
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
                        'aci' => array(
                            'type' => 'list',
                            'optional' => true,
                        ),
                        'associateddomain' => array(
                            'type' => 'list'
                        ),
                        'inetdomainbasedn' => array(
                            'optional' => true,
                        ),
                        'inetdomainstatus' => array(
                            'optional' => true,
                        ),
                    ),
                    'fields' => array(
                        'objectclass' => array(
                            'top',
                            'domainrelatedobject',
                            'inetdomain',
                        ),
                    ),
                );

                return $result;
            }
            else {
                throw new Exception($this->controller->translate($object_name . '.invalidtypeid'), 35);
            }
        }

        $key_name = $object_types[$type_id]['key'];

        return $object_types[$type_id]['attributes'];
    }

    /**
     * Detects object type ID for specified objectClass attribute value
     *
     * @param string $object_name   Name of the object (user, group, etc.)
     * @param array  $attributes    Array of attributes and values
     *
     * @return int Object type identifier
     */
    protected function object_type_id($object_name, $attributes)
    {
        if ($object_name == 'domain') return 1;

        $object_class = $attributes['objectclass'];

        if (empty($object_class)) {
            return null;
        }

        $object_types = $this->object_types($object_name);

        if (count($object_types) == 1) {
            return key($object_types);
        }

        $object_class = array_map('strtolower', $object_class);
        $object_keys  = array_keys($attributes);
        $keys_count   = count($object_keys);
        $type_score   = null;
        $type_id      = null;

        Log::trace("kolab_api_service::object_type_id objectClasses: " . implode(", ", $object_class));

        foreach ($object_types as $idx => $elem) {
            $ref_class = $elem['attributes']['fields']['objectclass'];

            if (empty($ref_class)) {
                continue;
            }

            Log::trace("Reference objectclasses for " . $elem['key'] . ": " . implode(", ", $ref_class));

            $elem_keys_score   = 0;
            $elem_values_score = 0;

            // Eliminate the duplicates between the $data_ocs and $ref_ocs
            $_object_class = array_diff($object_class, $ref_class);
            $_ref_class    = array_diff($ref_class, $object_class);

            // Object classes score
            $differences   = count($_object_class) + count($_ref_class);
            $commonalities = count($object_class) - $differences;
            $elem_score    = $differences > 0 ? ($commonalities / $differences) : $commonalities;

            // Attributes score
            if ($keys_count) {
                $ref_keys = array_unique(array_merge(
                    array_keys((array) $elem['attributes']['auto_form_fields']),
                    array_keys((array) $elem['attributes']['form_fields']),
                    array_keys($elem['attributes']['fields'])
                ));

                $elem_keys_score = $keys_count - count(array_diff($object_keys, $ref_keys));
            }

            // Static attributes score
            $elem_values_score = 0;
            foreach ((array) $elem['attributes']['fields'] as $attr => $value) {
                $v = $attributes[$attr];
                if (is_array($value)) {
                    $value = implode('', $value);
                }
                if (is_array($v)) {
                    $v = implode('', $v);
                }
                $elem_values_score += intval($v == $value);
            }

            // Position in tree score
            if (!empty($elem['attributes']['fields']['ou'])) {
                if (!empty($attributes['ou'])) {
                    if (strtolower($elem['attributes']['fields']['ou']) == strtolower($attributes['ou'])) {
                        Log::trace("object_type " . $elem['key'] . " fields ou setting matches entry, bumping scores.");
                        $elem_score += 2;
                        $elem_keys_score += 10;
                    }
                }
            }

            // On the likely chance that the object is a resource (types of which likely have the same
            // set of objectclass attribute values), consider the other attributes. (#853)
            if ($object_name == 'resource') {
                //console("From database", $elem);
                //console("Element key is " . $elem['key'] . " and \$attributes['mail'] is " . $attributes['mail']);
                if (strpos($attributes['mail'], 'resource-' . $elem['key'] . '-') === 0) {
                    $elem_score += 10;
                }
            }

            $elem_score .= ':' . $elem_keys_score . ':' . $elem_values_score;

//            Log::trace("\$object_class not in \$ref_class (" . $elem['key'] . "): " . implode(", ", $_object_class));
//            Log::trace("\$ref_class not in \$object_class (" . $elem['key'] . "): " . implode(", ", $_ref_class));
            Log::trace("Score for $object_name type " . $elem['name'] . ": " . $elem_score . " (" . $commonalities . "/" . $differences . ")");

            // Compare last and current element (object type) score
            if ($this->score_compare($elem_score, $type_score)) {
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
        if (!$object_name || !in_array($object_name, $this->supported_types_db)) {
            return array();
        }

        $conf = Conf::get_instance();

        $devel_mode = $conf->get('kolab_wap', 'devel_mode');

        if ($devel_mode == null) {
            if (!empty($this->cache['object_types']) && !empty($this->cache['object_types'][$object_name])) {
                return $this->cache['object_types'][$object_name];
            }
        }

        $sql_result   = $this->db->query("SELECT * FROM {$object_name}_types ORDER BY name");
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

        if ($devel_mode == null) {
            return $this->cache['object_types'][$object_name] = $object_types;
        } else {
            return $object_types;
        }

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

        Log::trace("kolab_api_service::parse_input_attributes for $object_name: " . var_export($type_attrs, TRUE));
        Log::trace("called with \$attribs: " . var_export($attribs, TRUE));

        $form_service = $this->controller->get_service('form_value');

        // With the result, start validating the input
        $validate_result = $form_service->validate(null, $attribs);

        $special_attr_validate = Array();

        foreach ($validate_result as $attr_name => $value) {
            if (!empty($value) && $value !== "OK" && $value !== 0) {
                $special_attr_validate[$attr_name] = $value;
            }
        }

        Log::trace("kolab_api_service::parse_input_attributes() \$special_attr_validate: " . var_export($special_attr_validate, TRUE));

        $result       = array();

        if (isset($type_attrs['form_fields'])) {
            foreach ($type_attrs['form_fields'] as $key => $value) {
                Log::trace("Running parse input attributes for key $key");

                if (empty($attribs[$key]) && empty($value['optional'])) {
                    Log::error("\$attribs['" . $key . "'] is empty, and the field is not optional");
                    throw new Exception("Missing input value for $key", 345);
                }
                else {
                    Log::trace("Either \$attribs['" . $key . "'] is empty or the field is optional");
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
                if (!is_array($value)) {
                    $value2 = $this->conf->expand($value);
                    if ($value !== $value2) {
                        Log::trace("Made value " . var_export($value, TRUE) . " in to: " . var_export($value2, TRUE));
                        $value = $value2;
                    }
                }

                if (empty($attribs[$key])) {
                    $result[$key] = $type_attrs['fields'][$key] = $value;
                } else {
                    $result[$key] = $attribs[$key] = $value;
                }
            }
        }

        $result = array_merge($result, $special_attr_validate);

        Log::trace("parse_input_attributes result (merge of \$result and \$special_attr_validate)", $result);

        return $result;
    }

    protected function parse_list_attributes($post)
    {
        $attributes = Array();
        // Attributes to return
        if (!empty($post['attributes']) && is_array($post['attributes'])) {
            // get only supported attributes
            $attributes = array_intersect($this->list_attribs, $post['attributes']);
            // need to fix array keys
            $attributes = array_values($attributes);
        }

        if (empty($attributes)) {
            $attributes = (array)$this->list_attribs[0];
        }

        return $attributes;
    }

    protected function parse_list_params($post)
    {
        $params = Array();
        if (!empty($post['sort_by'])) {
            if (is_array($post['sort_by'])) {
                $params['sort_by'] = Array();
                foreach ($post['sort_by'] as $attrib) {
                    if (in_array($attrib, $this->list_attribs)) {
                        $params['sort_by'][] = $attrib;
                    }
                }
            } else {
                // check if sort attribute is supported
                if (in_array($post['sort_by'], $this->list_attribs)) {
                    $params['sort_by'] = $post['sort_by'];
                }
            }
        }

        if (!empty($post['sort_order'])) {
            $params['sort_order'] = $post['sort_order'] == 'DESC' ? 'DESC' : 'ASC';
        }

        if (!empty($post['page'])) {
            $params['page'] = $post['page'];
        }

        if (!empty($post['page_size'])) {
            $params['page_size'] = $post['page_size'];
        }

        return $params;
    }

    protected function parse_list_search($post)
    {
        $search = Array();
        // Search parameters
        if (!empty($post['search']) && is_array($post['search'])) {
            if (array_key_exists('params', $post['search'])) {
                $search = $post['search'];
            } else {
                $search['params'] = $post['search'];
            }
            if (!empty($post['search_operator'])) {
                $search['operator'] = $post['search_operator'];
            }
        }
        return $search;
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

        $auth        = Auth::get_instance();
        $dn          = key($attrs);
        $attrs       = $attrs[$dn];
        $extra_attrs = array();
        $type_id     = $this->object_type_id($object_name, $attrs);
        $unique_attr = $this->unique_attribute();

        // Search for attributes associated with the type_id that are not part
        // of the result returned earlier. Example: nsrole / nsroledn / aci, etc.
        // @TODO: this should go to LDAP class
        if ($type_id) {
            $uta = $this->object_type_attributes($object_name, $type_id);

            $attributes = array_merge(
                array_keys((array) $uta['auto_form_fields']),
                array_keys((array) $uta['form_fields']),
                array_keys((array) $uta['fields'])
            );
            $attributes = array_filter($attributes);
            $attributes = array_unique($attributes);

            $object_attributes = array_keys($attrs);

            // extra attributes
            $extra_attrs = array_diff($attributes, $object_attributes);

            // remove attributes not listed in object type definition
            // @TODO: make this optional?
            $attributes = array_flip(array_merge($attributes, array($unique_attr)));
            $attrs = array_intersect_key($attrs, $attributes);
        }

        // Insert the persistent, unique attribute
        if (!array_key_exists($unique_attr, $attrs)) {
            $extra_attrs[] = $unique_attr;
        }

        // Get extra attributes
        if (!empty($extra_attrs)) {
            $extra_attrs = $auth->get_entry_attributes($dn, array_values($extra_attrs));

            if (!empty($extra_attrs)) {
                $attrs = array_merge($attrs, $extra_attrs);
            }
        }
        // Replace unique attribute with 'id' key
        $attrs['id'] = $attrs[$unique_attr];
        unset($attrs[$unique_attr]);

        // add object type id to the result
        $attrs['type_id'] = $type_id;

        return $attrs;
    }

    /**
     * Compare two score values
     *
     * @param string $s1 Score
     * @param string $s2 Score
     *
     * @return bool True when $s1 is greater than $s2
     */
    protected function score_compare($s1, $s2)
    {
        if (empty($s2) && !empty($s1)) {
            return true;
        }

        $s1 = explode(':', $s1);
        $s2 = explode(':', $s2);

        foreach ($s1 as $key => $val) {
            if ($val > $s2[$key]) {
                return true;
            }
            if ($val < $s2[$key]) {
                return false;
            }
        }

        return false;
    }

    /**
     * Returns name of unique attribute
     *
     * @return string Unique attribute name
     */
    protected function unique_attribute()
    {
        $conf        = Conf::get_instance();
        $unique_attr = $conf->get('unique_attribute');

        if (!$unique_attr) {
            $unique_attr = 'nsuniqueid';
        }

        return $unique_attr;
    }
}

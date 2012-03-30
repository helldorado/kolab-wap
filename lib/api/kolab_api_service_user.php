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
 * Service providing user data management
 */
class kolab_api_service_user extends kolab_api_service
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
        return array(
            'add' => 'w',
            'delete' => 'w',
//            'edit' => 'w',
//            'find' => 'r',
//            'find_by_any_attribute' => 'r',
//            'find_by_attribute' => 'r',
//            'find_by_attributes' => 'r',
            'info' => 'r',
        );
    }

    /**
     * Create user.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array|bool User attributes or False on error.
     */
    public function user_add($getdata, $postdata)
    {
        $uta             = $this->object_type_attributes('user', $postdata['type_id']);
        $form_service    = $this->controller->get_service('form_value');
        $user_attributes = array();

        if (isset($uta['form_fields'])) {
            foreach ($uta['form_fields'] as $key => $value) {
                if (!isset($postdata[$key]) || empty($postdata[$key])) {
                    throw new Exception("Missing input value for $key", 345);
                }
                else {
                    $user_attributes[$key] = $postdata[$key];
                }
            }
        }

        if (isset($uta['auto_form_fields'])) {
            foreach ($uta['auto_form_fields'] as $key => $value) {
                if (empty($postdata[$key])) {
                    $postdata['attributes'] = array($key);
                    $res                    = $form_service->generate($getdata, $postdata);
                    $postdata[$key]         = $res[$key];
                }
                $user_attributes[$key] = $postdata[$key];
            }
        }

        if (isset($uta['fields'])) {
            foreach ($uta['fields'] as $key => $value) {
                if (!isset($postdata[$key]) || empty($postdata[$key])) {
                    $user_attributes[$key] = $uta['fields'][$key];
                } else {
                    $user_attributes[$key] = $postdata[$key];
                }
            }
        }

        $auth = Auth::get_instance();
        $result = $auth->user_add($user_attributes, $postdata['type_id']);

        if ($result) {
            return $user_attributes;
        }

        return false;
    }

    /**
     * Detete user.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return bool True on success, False on failure
     */
    public function user_delete($getdata, $postdata)
    {
        if (!isset($postdata['user'])) {
            return false;
        }

        // TODO: Input validation
        $auth   = Auth::get_instance();
        $result = $auth->user_delete($postdata['user']);

        if ($result) {
            return $result;
        }

        return false;
    }

    /**
     * User information.
     *
     * @param array $get   GET parameters
     * @param array $post  POST parameters
     *
     * @return array|bool User attributes, False on error
     */
    public function user_info($getdata, $postdata)
    {
        if (!isset($getdata['user'])) {
            return false;
        }

        $auth   = Auth::get_instance();
        $user   = $getdata['user'];
        $result = $auth->user_info($user);

        // normalize result
        $dn                = key($result);
        $result            = $result[$dn];
        $result['entrydn'] = $dn;

        // add user type id to the result
        $result['type_id'] = $this->object_type_id('user', $result['objectclass']);

        // Search for attributes associated with the type_id that are not part
        // of the results returned earlier. Example: nsrole / nsroledn / aci, etc.
        if ($result['type_id']) {
            $uta   = $this->object_type_attributes('user', $result['type_id']);
            $attrs = array();

            foreach ($uta as $field_type => $attributes) {
                foreach ($attributes as $attribute => $data) {
                    if (!array_key_exists($attribute, $result)) {
                        $attrs[] = $attribute;
                    }
                }
            }

            if (!empty($attrs)) {
                $attrs = $auth->user_attributes($result['entrydn'], $attrs);
                if (!empty($attrs)) {
                    $result = array_merge($result, $attrs);
                }
            }
        }

        if ($result) {
            return $result;
        }

        return false;
    }
}

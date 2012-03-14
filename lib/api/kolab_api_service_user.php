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
class kolab_api_service_user extends kolab_api_service
{
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

    public function user_add($getdata, $postdata)
    {
        $uta             = $this->user_type_attributes($postdata['user_type_id']);
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
                    $postdata['attribute'] = $key;
                    $res                   = $form_service->generate($getdata, $postdata);
                    $postdata[$key]        = $res[$key];
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
        $result = $auth->user_add($user_attributes, $postdata['user_type_id']);

        if ($result) {
            return $user_attributes;
        }

        return FALSE;
    }

    public function user_delete($getdata, $postdata)
    {
        if (!isset($postdata['user'])) {
            return FALSE;
        }

        // TODO: Input validation
        $auth   = Auth::get_instance();
        $result = $auth->user_delete($postdata['user']);

        if ($result) {
            return $result;
        }

        return FALSE;
    }

    public function user_info($getdata, $postdata)
    {
        if (!isset($getdata['user'])) {
            return FALSE;
        }

        $auth   = Auth::get_instance();
        $result = $auth->user_info($getdata['user']);

        if ($result) {
            return $result;
        }

        return FALSE;
    }
}

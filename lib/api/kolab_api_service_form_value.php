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
 * Service providing functionality related to HTML forms generation/validation.
 */
class kolab_api_service_form_value extends kolab_api_service
{

    public function capabilities($domain)
    {
        return array(
            'generate' => 'r',
        );
    }

    /**
     * Generation of auto-filled field values.
     *
     * @param array $getdata   GET parameters
     * @param array $postdata  POST parameters. Required parameters:
     *                         - attribute: attribute name
     *                         - user_type_id or group_type_id: Type identifier
     *
     * @return array Response with attribute name as a key
     */
    public function generate($getdata, $postdata)
    {
        if (empty($postdata['attribute'])) {
            throw new Exception($this->controller->translate('form_value.noattribute'), 35);
        }

        $method_name = 'generate_' . strtolower($postdata['attribute']);

        if (!method_exists($this, $method_name)) {
            throw new Exception($this->controller->translate('form_value.unknownattribute'), 36);
        }

        if (isset($postdata['user_type_id'])) {
            $attribs = $this->user_type_attributes($postdata['user_type_id']);
        }
        else if (isset($postdata['group_type_id'])) {
            $attribs = $this->group_type_attributes($postdata['group_type_id']);
        }
        else {
            $attribs = array();
        }

        return $this->{$method_name}($postdata, $attribs);
    }


    private function generate_cn($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['cn'])) {
            // Use Data Please
            foreach ($attribs['auto_form_fields']['cn']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            $cn = trim($postdata['givenname'] . " " . $postdata['sn']);

            return array("cn" => $cn);
        }
    }

    private function generate_displayname($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['displayname'])) {
            // Use Data Please
            foreach ($attribs['auto_form_fields']['displayname']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            $displayname = $postdata['givenname'];
            if ($postdata['sn']) {
                $displayname = $postdata['sn'] . ", " . $displayname;
            }

            return array("displayname" => $displayname);
        }
    }

    private function generate_homedirectory($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['homedirectory'])) {
            // Use Data Please
            foreach ($attribs['auto_form_fields']['homedirectory']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            $uid = iconv('UTF-8', 'ASCII//TRANSLIT', $postdata['sn']);
            $uid = strtolower($uid);
            $uid = preg_replace('/[^a-z-_]/i', '', $uid);

            $orig_uid = $uid;

            $auth = Auth::get_instance($_SESSION['user']->get_domain());

            $x = 2;
            while ($auth->user_find_by_attribute(array('uid' => $uid))) {
                $uid = $orig_uid . $x;
                $x++;
            }

            // TODO: Home directory base path from configuration?

            return array('homedirectory' => '/home/'.$uid);
        }
    }

    private function generate_mail($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['mail'])) {
            // Use Data Please
            foreach ($attribs['auto_form_fields']['mail']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            $givenname = iconv('UTF-8', 'ASCII//TRANSLIT', $postdata['givenname']);
            $sn        = iconv('UTF-8', 'ASCII//TRANSLIT', $postdata['sn']);

            $givenname = strtolower($givenname);
            $sn        = strtolower($sn);

            $givenname = preg_replace('/[^a-z-_]/i', '', $givenname);
            $sn        = preg_replace('/[^a-z-_]/i', '', $sn);

            $local = trim($givenname . '.' . $sn, '.');
            $mail  = $local . '@' . $_SESSION['user']->get_domain();

            $orig_mail = $mail;

            $auth = Auth::get_instance($_SESSION['user']->get_domain());

            $x = 2;
            while ($auth->user_find_by_attribute(array('mail' => $mail))) {
                list($mail_local, $mail_domain) = explode('@', $orig_mail);
                $mail = $mail_local . $x . '@' . $mail_domain;
                $x++;
            }

            return array('mail' => $mail);
        }
    }

    private function generate_password($postdata, $attribs = array())
    {
        exec("head -c 200 /dev/urandom | tr -dc _A-Z-a-z-0-9 | head -c15", $userpassword_plain);
        $userpassword_plain = $userpassword_plain[0];
        return array('password' => $userpassword_plain);
    }

    private function generate_uid($postdata, $attribs = array())
    {
        if (isset($attribs['auto_form_fields']) && isset($attribs['auto_form_fields']['uid'])) {
            // Use Data Please
            foreach ($attribs['auto_form_fields']['uid']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            $uid = iconv('UTF-8', 'ASCII//TRANSLIT', $postdata['sn']);
            $uid = strtolower($uid);
            $uid = preg_replace('/[^a-z-_]/i', '', $uid);

            $orig_uid = $uid;

            $auth = Auth::get_instance($_SESSION['user']->get_domain());

            $x = 2;
            while ($auth->user_find_by_attribute(array('uid' => $uid))) {
                $uid = $orig_uid . $x;
                $x++;
            }

            return array('uid' => $uid);
        }
    }

    private function generate_userpassword($postdata, $attribs = array())
    {
        $password = $this->generate_password($getdata, $postdata, $attribs);
        return array('userpassword' => $password['password']);
    }

}

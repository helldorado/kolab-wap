<?php

/**
 *
 */
class kolab_api_service_form_value extends kolab_api_service
{

    public function capabilities($domain)
    {
        return array(
            'generate_cn' => 'w',
            'generate_displayname' => 'w',
            'generate_mail' => 'w',
            'generate_password' => 'r',
            'generate_uid' => 'w',
            'generate_userpassword' => 'r',
//            'info' => 'r',
        );
    }

    public function generate_cn($getdata, $postdata)
    {
        if (!isset($postdata['user_type_id'])) {
            throw new Exception("No user type ID specified", 34);
        }

        $sql_result = $this->db->query("SELECT attributes FROM user_types WHERE id = ?", $postdata['user_type_id']);
        $user_type  = $this->db->fetch_assoc($sql_result);

        $uta = json_decode(unserialize($user_type['attributes']), true);

        if (isset($uta['auto_form_fields']) && isset($uta['auto_form_fields']['cn'])) {
            // Use Data Please
            foreach ($uta['auto_form_fields']['cn']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            return array("cn" => $postdata['givenname'] . " " . $postdata['sn']);
        }
    }

    public function generate_displayname($getdata, $postdata)
    {
        if (!isset($postdata['user_type_id'])) {
            throw new Exception("No user type ID specified", 34);
        }

        $sql_result = $this->db->query("SELECT attributes FROM user_types WHERE id = ?", $postdata['user_type_id']);
        $user_type  = $this->db->fetch_assoc($sql_result);

        $uta = json_decode(unserialize($user_type['attributes']), true);

        if (isset($uta['auto_form_fields']) && isset($uta['auto_form_fields']['displayname'])) {
            // Use Data Please
            foreach ($uta['auto_form_fields']['displayname']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            return array("displayname" => $postdata['sn'] . ", " . $postdata['givenname']);
        }

    }

    public function generate_mail($getdata, $postdata)
    {
        if (!isset($postdata['user_type_id'])) {
            throw new Exception("No user type ID specified", 34);
        }

        $sql_result = $this->db->query("SELECT attributes FROM user_types WHERE id = ?", $postdata['user_type_id']);
        $user_type  = $this->db->fetch_assoc($sql_result);

        $uta = json_decode(unserialize($user_type['attributes']), true);

        if (isset($uta['auto_form_fields']) && isset($uta['auto_form_fields']['mail'])) {
            // Use Data Please
            foreach ($uta['auto_form_fields']['mail']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            $givenname = iconv('UTF-8', 'ASCII//TRANSLIT', $postdata['givenname']);
            $sn        = iconv('UTF-8', 'ASCII//TRANSLIT', $postdata['sn']);

            $givenname = strtolower($givenname);
            $sn        = str_replace(' ', '', $sn);
            $sn        = strtolower($sn);

            $mail = $givenname . "." . $sn . "@" . $_SESSION['user']->get_domain();

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

    public function generate_password($getdata, $postdata)
    {
        exec("head -c 200 /dev/urandom | tr -dc _A-Z-a-z-0-9 | head -c15", $userpassword_plain);
        $userpassword_plain = $userpassword_plain[0];
        return array('password' => $userpassword_plain);
    }

    public function generate_uid($getdata, $postdata)
    {
        if (!isset($postdata['user_type_id'])) {
            throw new Exception("No user type ID specified", 34);
        }

        $sql_result = $this->db->query("SELECT attributes FROM user_types WHERE id = ?", $postdata['user_type_id']);
        $user_type  = $this->db->fetch_assoc($sql_result);

        $uta = json_decode(unserialize($user_type['attributes']), true);

        if (isset($uta['auto_form_fields']) && isset($uta['auto_form_fields']['uid'])) {
            // Use Data Please
            foreach ($uta['auto_form_fields']['uid']['data'] as $key) {
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
            }

            $uid = iconv('UTF-8', 'ASCII//TRANSLIT', $postdata['sn']);
            $uid = strtolower($uid);
            $uid = str_replace(' ', '', $uid);

            $orig_uid = $uid;

            $auth = Auth::get_instance($_SESSION['user']->get_domain());

            $user = $auth->user_find_by_attribute(array('uid' => $uid));

            $x = 2;
            while ($auth->user_find_by_attribute(array('uid' => $uid))) {
                $uid = $orig_uid . $x;
                $x++;
            }

            return array('uid' => $uid);
        }
    }

    public function generate_userpassword($getdata, $postdata)
    {
        $password = $this->generate_password($getdata, $postdata);
        return array('userpassword' => $password['password']);
    }

}

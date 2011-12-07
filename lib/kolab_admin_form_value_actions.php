<?php

    /**
     *
     */
    class kolab_admin_form_value_actions extends kolab_admin_service
    {
        public function capabilities($domain)
        {
            return array(
                    'list' => 'l',
                    'info' => 'r',
                );
        }

        public function generate_cn($getdata, $postdata) {
            if (!isset($postdata['user_type_id'])) {
                throw new Exception("No user type ID specified", 34);
            }

            $user_type = mysql_fetch_assoc(query("SELECT attributes FROM user_types WHERE id = '" . $postdata['user_type_id'] ."'"));

            $uta = json_decode(unserialize($user_type['attributes']), true);

            if (isset($uta['auto_form_fields']) && isset($uta['auto_form_fields']['cn'])) {
                // Use Data Please
                foreach ($uta['auto_form_fields']['cn']['data'] as $key) {
                    if (!isset($postdata[$key])) {
                        throw new Exception("Key not set: " . $key, 12356);
                    }
                }

                return Array("cn" => $postdata['givenname'] . " " . $postdata['sn']);
            }

        }

        public function generate_displayname($getdata, $postdata) {
            if (!isset($postdata['user_type_id'])) {
                throw new Exception("No user type ID specified", 34);
            }

            $user_type = mysql_fetch_assoc(query("SELECT attributes FROM user_types WHERE id = '" . $postdata['user_type_id'] ."'"));

            $uta = json_decode(unserialize($user_type['attributes']), true);

            if (isset($uta['auto_form_fields']) && isset($uta['auto_form_fields']['displayname'])) {
                // Use Data Please
                foreach ($uta['auto_form_fields']['displayname']['data'] as $key) {
                    if (!isset($postdata[$key])) {
                        throw new Exception("Key not set: " . $key, 12356);
                    }
                }

                return Array("displayname" => $postdata['sn'] . ", " . $postdata['givenname']);
            }

        }

        public function generate_mail($getdata, $postdata) {
            if (!isset($postdata['user_type_id'])) {
                throw new Exception("No user type ID specified", 34);
            }

            $user_type = mysql_fetch_assoc(query("SELECT attributes FROM user_types WHERE id = '" . $postdata['user_type_id'] ."'"));

            $uta = json_decode(unserialize($user_type['attributes']), true);

            if (isset($uta['auto_form_fields']) && isset($uta['auto_form_fields']['mail'])) {
                // Use Data Please
                foreach ($uta['auto_form_fields']['mail']['data'] as $key) {
                    if (!isset($postdata[$key])) {
                        throw new Exception("Key not set: " . $key, 12356);
                    }
                }

                $givenname = iconv('UTF-8', 'ASCII//TRANSLIT', $postdata['givenname']);
                $sn = iconv('UTF-8', 'ASCII//TRANSLIT', $postdata['sn']);

                $givenname = strtolower($givenname);

                $sn = str_replace(' ', '', $sn);
                $sn = strtolower($sn);

                return Array('mail' => $givenname . "." . $sn . "@" . $_SESSION['user']->get_domain());
            }

        }

        public function generate_password($getdata, $postdata) {
            exec("head -c 200 /dev/urandom | tr -dc _A-Z-a-z-0-9 | head -c15", $userpassword_plain);
            $userpassword_plain = $userpassword_plain[0];
            return Array('password' => $userpassword_plain);
        }

        public function generate_uid($getdata, $postdata) {
            if (!isset($postdata['user_type_id'])) {
                throw new Exception("No user type ID specified", 34);
            }

            $user_type = mysql_fetch_assoc(query("SELECT attributes FROM user_types WHERE id = '" . $postdata['user_type_id'] ."'"));

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

                return Array('uid' => $uid);
            }

        }

        public function generate_userpassword($getdata, $postdata) {
            $password = $this->generate_password($getdata, $postdata);
            return Array('userpassword' => $password['password']);
        }

    }

?>

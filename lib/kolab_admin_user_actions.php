<?php

    /**
     *
     */
    class kolab_admin_user_actions extends kolab_admin_service
    {
        public function capabilities($domain)
        {
            return array(
                    'list' => 'l',
                    'info' => 'r',
                );
        }

        public function user_add($getdata, $postdata) {
            if (!isset($postdata['user_type_id'])) {
                throw new Exception("No user type ID specified", 346781);
            }

            $user_type = mysql_fetch_assoc(query("SELECT attributes FROM user_types WHERE id = '" . $postdata['user_type_id'] ."'"));

            $uta = json_decode(unserialize($user_type['attributes']), true);

            $user_attributes = Array();

            if (isset($uta['form_fields'])) {
                foreach ($uta['form_fields'] as $key => $value) {
                    error_log("form field $key");
                    if (!isset($postdata[$key]) || empty($postdata[$key])) {
                        throw new Exception("Missing input value for $key", 345);
                    } else {
                        $user_attributes[$key] = $postdata[$key];
                    }
                }
            }

            if (isset($uta['auto_form_fields'])) {
                foreach ($uta['auto_form_fields'] as $key => $value) {
                    if (!isset($postdata[$key])) {
                        throw new Exception("Key not set: " . $key, 12356);
                    } else {
                        $user_attributes[$key] = $postdata[$key];
                    }
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
            } else {
                return FALSE;
            }
        }

        public function user_delete($getdata, $postdata) {
            // TODO: Input validation
            $auth = Auth::get_instance();
            $result = $auth->user_delete($postdata);
            if ($result) {
                return $result;
            } else {
                return FALSE;
            }
        }
    }

?>

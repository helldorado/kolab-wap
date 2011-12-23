<?php

    /**
     *
     */
    class kolab_admin_group_actions extends kolab_admin_api_service
    {
        public function capabilities($domain)
        {
            return array(
                    'add' => 'w',
                    'delete' => 'w',
                    'info' => 'r',
                    'members_list' => 'r',
                );
        }

        public function group_add($getdata, $postdata) {
            if (!isset($postdata['group_type_id'])) {
                throw new Exception("No group type ID specified", 346781);
            }

            $group_type = mysql_fetch_assoc(query("SELECT attributes FROM group_types WHERE id = '" . $postdata['group_type_id'] ."'"));

            $gta = json_decode(unserialize($group_type['attributes']), true);

            $group_attributes = Array();

            if (isset($gta['form_fields'])) {
                foreach ($gta['form_fields'] as $key => $value) {
                    error_log("form field $key");
                    if (!isset($postdata[$key]) || empty($postdata[$key])) {
                        throw new Exception("Missing input value for $key", 345);
                    } else {
                        $group_attributes[$key] = $postdata[$key];
                    }
                }
            }

            if (isset($gta['auto_form_fields'])) {
                foreach ($gta['auto_form_fields'] as $key => $value) {
                    if (!isset($postdata[$key])) {
                        throw new Exception("Key not set: " . $key, 12356);
                    } else {
                        $group_attributes[$key] = $postdata[$key];
                    }
                }
            }

            if (isset($gta['fields'])) {
                foreach ($gta['fields'] as $key => $value) {
                    if (!isset($postdata[$key]) || empty($postdata[$key])) {
                        $group_attributes[$key] = $gta['fields'][$key];
                    } else {
                        $group_attributes[$key] = $postdata[$key];
                    }
                }
            }

            $auth = Auth::get_instance();
            $result = $auth->group_add($group_attributes, $postdata['group_type_id']);
            if ($result) {
                return $group_attributes;
            } else {
                return FALSE;
            }
        }

        public function group_delete($getdata, $postdata) {
            // TODO: Input validation
            $auth = Auth::get_instance();
            if (!isset($postdata['group'])) {
                return FALSE;
            }

            $result = $auth->group_delete($postdata['group']);
            if ($result) {
                return $result;
            } else {
                return FALSE;
            }
        }

        public function group_info($getdata, $postdata) {
            $auth = Auth::get_instance();

            if (!isset($getdata['group'])) {
                return FALSE;
            }

            $result = $auth->group_info($getdata['group']);

            if ($result) {
                return $result;
            } else {
                return FALSE;
            }
        }

        public function group_members_list($getdata, $postdata) {
            $auth = Auth::get_instance();

            if (!isset($getdata['group'])) {
                return FALSE;
            }

            $result = $auth->group_members_list($getdata['group']);

            if ($result) {
                return $result;
            } else {
                return FALSE;
            }
        }
   }

?>

<?php

/**
 *
 */
class kolab_user_actions extends kolab_api_service
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
        if (!isset($postdata['user_type_id'])) {
            throw new Exception($this->controller::translate('user.notypeid'), 346781);
        }

        $sql_result = $this->db->query("SELECT attributes FROM user_types WHERE id = ?", $postdata['user_type_id']);
        $user_type  = $this->db->fetch_assoc($sql_result);

        $uta = json_decode(unserialize($user_type['attributes']), true);

        $user_attributes = array();

        if (isset($uta['form_fields'])) {
            foreach ($uta['form_fields'] as $key => $value) {
                error_log("form field $key");
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
                if (!isset($postdata[$key])) {
                    throw new Exception("Key not set: " . $key, 12356);
                }
                else {
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

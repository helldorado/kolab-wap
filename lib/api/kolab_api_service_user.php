<?php

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
                    $method         = 'generate_' . $key;
                    $res            = $form_service->$method($getdata, $postdata);
                    $postdata[$key] = $res[$key];
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

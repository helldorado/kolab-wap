<?php

/**
 *
 */
class kolab_user_types_actions extends kolab_api_service
{
    public function capabilities($domain)
    {
        return array(
            'list' => 'r',
        );
    }

    public function user_types_list($get, $post)
    {
        $result = query("SELECT * FROM user_types");
        $user_types = array();

        while ($row = mysql_fetch_assoc($result)) {
            $user_types[$row['id']] = array();

            foreach ($row as $key => $value) {
                if ($key != "id") {
                    if ($key == "attributes") {
                        $user_types[$row['id']][$key] = json_decode(unserialize($value), true);
                    }
                    else {
                        $user_types[$row['id']][$key] = $value;
                    }
                }
            }
        }

        return $user_types;
    }
}

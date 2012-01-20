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
        $sql_result = $this->db->query("SELECT * FROM user_types");
        $user_types = array();

        while ($row = mysql_fetch_assoc($sql_result)) {
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

        return array(
            'list'  => $user_types,
            'count' => count($user_types),
        );
    }
}

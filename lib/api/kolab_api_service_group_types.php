<?php

/**
 *
 */
class kolab_api_service_group_types extends kolab_api_service
{
    public function capabilities($domain)
    {
        return array(
            'list' => 'r',
        );
    }

    public function group_types_list($get, $post)
    {
        $sql_result = $this->db->query("SELECT * FROM group_types");
        $group_types = array();

        while ($row = $this->db->fetch_assoc($sql_result)) {
            $group_types[$row['id']] = array();

            foreach ($row as $key => $value) {
                if ($key != "id") {
                    if ($key == "attributes") {
                        $group_types[$row['id']][$key] = json_decode(unserialize($value), true);
                    }
                    else {
                        $group_types[$row['id']][$key] = $value;
                    }
                }
            }
        }

        return $group_types;
    }
}

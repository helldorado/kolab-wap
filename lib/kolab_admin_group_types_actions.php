<?php

    /**
     *
     */
    class kolab_admin_group_types_actions extends kolab_admin_service
    {
        public function capabilities($domain)
        {
            return array(
                    'list' => 'r',
                );
        }

        public function group_types_list($get, $post) {
            $result = query("SELECT * FROM group_types");
            $group_types = Array();

            while ($row = mysql_fetch_assoc($result)) {
                $group_types[$row['id']] = Array();

                foreach ($row as $key => $value) {
                    if ($key != "id") {
                        if ($key == "attributes") {
                            $group_types[$row['id']][$key] = json_decode(unserialize($value), true);
                        } else {
                            $group_types[$row['id']][$key] = $value;
                        }
                    }
                }
            }

            return $group_types;

        }
    }

?>

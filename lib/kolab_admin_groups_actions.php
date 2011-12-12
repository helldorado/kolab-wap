<?php

    /**
     *
     */
    class kolab_admin_groups_actions extends kolab_admin_service
    {
        public function capabilities($domain)
        {
            return array(
                    'list' => 'r',
                    'search' =>  'r',
                );
        }

        public function groups_list($get, $post) {
            $auth = Auth::get_instance();
            $groups = $auth->list_groups();
            $groups = $auth->normalize_result($groups);
            return $groups;

        }
   }

?>

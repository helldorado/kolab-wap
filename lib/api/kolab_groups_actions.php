<?php

    /**
     *
     */
    class kolab_groups_actions extends kolab_api_service
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
            return $groups;

        }
   }

?>

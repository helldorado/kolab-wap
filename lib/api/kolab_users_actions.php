<?php

    /**
     *
     */
    class kolab_users_actions extends kolab_api_service
    {
        public function capabilities($domain)
        {
            return array(
                    'list' => 'r',
                );
        }

        public function users_list($get, $post) {
            $auth = Auth::get_instance();
            $users = $auth->list_users();
            $users = $auth->normalize_result($users);
            return $users;

        }
    }

?>

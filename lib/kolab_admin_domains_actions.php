<?php

    /**
     *
     */
    class kolab_admin_domains_actions extends kolab_admin_service
    {
        public function capabilities($domain)
        {
            return array(
                    'list' => 'r',
//                     'search' => 'r',
                );
        }

        public function domains_list($get, $post) {
            $auth = Auth::get_instance();
            $domains = $auth->normalize_result($auth->list_domains());
            return $domains;
        }
    }

?>
